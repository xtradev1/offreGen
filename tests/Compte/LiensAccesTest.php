<?php

declare(strict_types=1);

namespace OffreGen\Tests\Compte;

use OffreGen\Compte\LiensAcces;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mot de passe oublié, courriel d'accès d'un nouveau compte (A3 = A), changement du mot de passe dans les réglages
 * (P4) : décisions de Xavier du 2026-11-18 sur MAQUETTE-completer.html.
 */
final class LiensAccesTest extends TestCase
{
    private const NOUVEAU = 'nouveau-mot-de-passe-1';

    private Jeu $jeu;
    private Dossiers $dossiers;
    private MockClock $horloge;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->horloge = new MockClock('2026-11-18 10:00:00', 'UTC');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function navigateur(string $ip = '127.0.0.1'): Navigateur
    {
        return new Navigateur(Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']), $ip);
    }

    /** La demande, puis le courriel, qui part après la réponse. */
    private function demander(Navigateur $n, string $courriel): Response
    {
        $n->get('/mot-de-passe/oubli');
        $reponse = $n->post('/mot-de-passe/oubli', ['courriel' => $courriel]);
        $n->c->apresReponse()->executer();
        return $reponse;
    }

    /** @return list<string> le chemin de chaque lien reçu, dans l'ordre */
    private function liensRecus(): array
    {
        $liens = [];
        $courriels = $this->dossiers->courriels();
        sort($courriels);
        foreach ($courriels as $f) {
            if (preg_match('#https://exemple\.test(/mot-de-passe/[A-Za-z0-9_-]{43})\b#', (string) file_get_contents($f), $m) === 1) {
                $liens[] = $m[1];
            }
        }
        return $liens;
    }

    private function choisir(Navigateur $n, string $lien, string $mdp = self::NOUVEAU, ?string $confirmation = null): Response
    {
        $n->get($lien);
        return $n->post($lien, ['mot_de_passe' => $mdp, 'confirmation' => $confirmation ?? $mdp]);
    }

    public function testLaConnexionProposeMotDePasseOublie(): void
    {
        self::assertStringContainsString('href="/mot-de-passe/oubli"', (string) $this->navigateur()->get('/connexion')->getContent());
    }

    public function testLeLienRecuChangeLeMotDePasseEtConnecte(): void
    {
        $n = $this->navigateur();
        $reponse = $this->demander($n, 'A@exemple.ch ');
        self::assertSame(200, $reponse->getStatusCode());
        self::assertStringContainsString('Si un compte existe', (string) $reponse->getContent());
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString("To: a@exemple.ch\r\n", (string) file_get_contents($courriels[0]));
        [$lien] = $this->liensRecus();

        $page = $n->get($lien);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('name="confirmation"', (string) $page->getContent());
        $reponse = $n->post($lien, ['mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(303, $reponse->getStatusCode());
        self::assertSame('/', $reponse->headers->get('Location'));
        self::assertSame(200, $n->get('/')->getStatusCode());

        self::assertSame(303, $this->navigateur('198.51.100.1')->connecter('a@exemple.ch', self::NOUVEAU)->getStatusCode());
        self::assertSame(200, $this->navigateur('198.51.100.2')->connecter('a@exemple.ch')->getStatusCode());
    }

    /** La page ne dit pas si le compte existe, et rien ne part. */
    public function testUnCompteInconnuRecoitLaMemeReponseSansCourriel(): void
    {
        $connu = (string) $this->demander($this->navigateur(), 'a@exemple.ch')->getContent();
        $inconnu = (string) $this->demander($this->navigateur(), 'personne@exemple.ch')->getContent();
        $sansJeton = static fn (string $page): string => (string) preg_replace(['/name="_csrf" value="[^"]+"/', '/a@exemple\.ch|personne@exemple\.ch/'], '', $page);
        self::assertSame($sansJeton($connu), $sansJeton($inconnu));
        self::assertCount(1, $this->dossiers->courriels());
    }

    public function testLeJetonNEstGardeQueSousFormeDEmpreinte(): void
    {
        $this->demander($this->navigateur(), 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        $jeton = substr($lien, strlen('/mot-de-passe/'));
        self::assertSame(0, BaseDeTest::compter('jeton_acces', "empreinte = '$jeton'"));
        self::assertSame(1, BaseDeTest::compter('jeton_acces', "empreinte = '" . hash('sha256', $jeton) . "'"));
    }

    public function testLeLienExpireApresUneHeure(): void
    {
        $n = $this->navigateur();
        $this->demander($n, 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        $this->horloge->modify('+61 minutes');
        $page = $n->get($lien);
        self::assertSame(410, $page->getStatusCode());
        self::assertStringContainsString('href="/mot-de-passe/oubli"', (string) $page->getContent());
        // Un envoi du formulaire resté ouvert dans un onglet : le jeton CSRF est valable, le lien non.
        $n->get('/mot-de-passe/oubli');
        self::assertSame(410, $n->post($lien, ['mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU])->getStatusCode());
        self::assertSame(200, $this->navigateur()->connecter('a@exemple.ch', self::NOUVEAU)->getStatusCode());
    }

    public function testLeLienNeSertQuUneFois(): void
    {
        $n = $this->navigateur();
        $this->demander($n, 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        self::assertSame(303, $this->choisir($n, $lien)->getStatusCode());
        self::assertSame(410, $this->navigateur()->get($lien)->getStatusCode());
    }

    public function testUtiliserUnLienAnnuleLesAutres(): void
    {
        $n = $this->navigateur();
        $this->demander($n, 'a@exemple.ch');
        $this->horloge->modify('+1 minute');
        $this->demander($n, 'a@exemple.ch');
        [$premier, $second] = $this->liensRecus();
        self::assertSame(303, $this->choisir($n, $second)->getStatusCode());
        self::assertSame(410, $this->navigateur()->get($premier)->getStatusCode());
    }

    public function testTroisDemandesParHeureAuPlus(): void
    {
        $n = $this->navigateur();
        for ($i = 0; $i < 4; $i++) {
            self::assertSame(200, $this->demander($n, 'a@exemple.ch')->getStatusCode());
        }
        self::assertCount(LiensAcces::DEMANDES_PAR_HEURE, $this->dossiers->courriels());
        $this->horloge->modify('+61 minutes');
        $this->demander($n, 'a@exemple.ch');
        self::assertCount(LiensAcces::DEMANDES_PAR_HEURE + 1, $this->dossiers->courriels());
    }

    public function testUnMotDePasseRefuseNeConsommePasLeLien(): void
    {
        $n = $this->navigateur();
        $this->demander($n, 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        $court = $this->choisir($n, $lien, 'court', 'court');
        self::assertSame(422, $court->getStatusCode());
        self::assertStringContainsString('10 caractères', (string) $court->getContent());
        $different = $this->choisir($n, $lien, self::NOUVEAU, self::NOUVEAU . 'x');
        self::assertSame(422, $different->getStatusCode());
        self::assertSame(303, $this->choisir($n, $lien)->getStatusCode());
    }

    public function testUnCompteDeDemonstrationNeRecoitRien(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1 WHERE id = {$this->jeu->a}");
        $this->demander($this->navigateur(), 'a@exemple.ch');
        self::assertSame([], $this->dossiers->courriels());
    }

    public function testLeLienDAccesDUnNouveauCompteVautSeptJours(): void
    {
        $c = Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']);
        $c->comptes()->creer('luigi@exemple.ch', 'Peinture Luigi', false, null, 20);
        $c->liensAcces()->envoyerAcces('luigi@exemple.ch');
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        $texte = (string) file_get_contents($courriels[0]);
        self::assertStringContainsString("To: luigi@exemple.ch\r\n", $texte);
        self::assertStringContainsString("7\u{00A0}jours", $texte);
        [$lien] = $this->liensRecus();
        $this->horloge->modify('+6 days 23 hours');
        $n = $this->navigateur();
        self::assertSame(303, $this->choisir($n, $lien)->getStatusCode());
        // Un nouveau compte part vers l'assistant du premier lancement.
        self::assertStringStartsWith('/bienvenue/', (string) $n->get('/')->headers->get('Location'));
    }

    public function testLeLienDAccesExpireApresSeptJours(): void
    {
        $c = Monde::conteneur($this->horloge, $this->dossiers->env() + ['APP_URL' => 'https://exemple.test']);
        $c->comptes()->creer('luigi@exemple.ch', 'Peinture Luigi', false, null, 20);
        $c->liensAcces()->envoyerAcces('luigi@exemple.ch');
        [$lien] = $this->liensRecus();
        $this->horloge->modify('+7 days 1 minute');
        self::assertSame(410, $this->navigateur()->get($lien)->getStatusCode());
    }

    public function testChangerSonMotDePasseDeconnecteLesAutresAppareils(): void
    {
        $telephone = $this->navigateur('198.51.100.1');
        $telephone->connecter('a@exemple.ch');
        $pc = $this->navigateur('198.51.100.2');
        $pc->connecter('a@exemple.ch');

        $pc->get('/reglages/mot-de-passe');
        $reponse = $pc->post('/reglages/mot-de-passe', ['ancien' => Jeu::MOT_DE_PASSE, 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(303, $reponse->getStatusCode());
        self::assertSame(200, $pc->get('/')->getStatusCode());
        self::assertSame(303, $telephone->get('/')->getStatusCode());
        self::assertSame(303, $this->navigateur('198.51.100.3')->connecter('a@exemple.ch', self::NOUVEAU)->getStatusCode());
    }

    public function testChangerSonMotDePasseAnnuleLesLiensEnCours(): void
    {
        $this->demander($this->navigateur(), 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        $n->get('/reglages/mot-de-passe');
        $n->post('/reglages/mot-de-passe', ['ancien' => Jeu::MOT_DE_PASSE, 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(410, $this->navigateur()->get($lien)->getStatusCode());
    }

    public function testUnMauvaisAncienMotDePasseNeChangeRien(): void
    {
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        $n->get('/reglages/mot-de-passe');
        $reponse = $n->post('/reglages/mot-de-passe', ['ancien' => 'faux', 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertSame(303, $this->navigateur('198.51.100.9')->connecter('a@exemple.ch')->getStatusCode());
    }

    /**
     * Revue du 2026-11-18 (connexion, défaut 1) : un changement vérifié avant qu'un lien d'oubli ne serve ne doit pas
     * écraser le mot de passe choisi par le lien. Le changement ne s'écrit que si la session est encore la bonne.
     */
    public function testUnChangementEnRetardSurLaSessionNEcrasePasLeLien(): void
    {
        $c = Monde::conteneur($this->horloge);
        $requete = \Symfony\Component\HttpFoundation\Request::create('/reglages/mot-de-passe', 'POST');
        $requete->setSession(new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
        $c->authentification()->ouvrirSession($requete, $this->jeu->a, 0);
        // Entre la vérification de l'ancien mot de passe et l'écriture, un lien d'oubli a servi.
        BaseDeTest::pdo()->exec("UPDATE artisan SET version_session = 1 WHERE id = {$this->jeu->a}");
        self::assertFalse($c->authentification()->remplacerMotDePasse($requete, $this->jeu->a, 'mot-de-passe-du-tiers'));
        self::assertSame(200, $this->navigateur('198.51.100.7')->connecter('a@exemple.ch', 'mot-de-passe-du-tiers')->getStatusCode());
    }

    /** Revue du 2026-11-18 (connexion, défaut 2) : avec une session volée, l'ancien mot de passe ne se devine pas. */
    public function testLAncienMotDePasseEstFreineCommeLaConnexion(): void
    {
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        for ($i = 0; $i < 5; $i++) {
            $n->get('/reglages/mot-de-passe');
            self::assertSame(422, $n->post('/reglages/mot-de-passe', ['ancien' => "faux-$i", 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU])->getStatusCode());
        }
        $n->get('/reglages/mot-de-passe');
        $reponse = $n->post('/reglages/mot-de-passe', ['ancien' => Jeu::MOT_DE_PASSE, 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(429, $reponse->getStatusCode());
        $this->horloge->modify('+16 minutes');
        $n->get('/reglages/mot-de-passe');
        self::assertSame(303, $n->post('/reglages/mot-de-passe', ['ancien' => Jeu::MOT_DE_PASSE, 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU])->getStatusCode());
    }

    /** Revue du 2026-11-18 (connexion, défaut 3) : la démonstration est partagée, personne ne la confisque. */
    public function testUnCompteDeDemonstrationGardeSonMotDePasse(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1 WHERE id = {$this->jeu->a}");
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        self::assertStringNotContainsString('href="/reglages/mot-de-passe"', (string) $n->get('/reglages')->getContent());
        self::assertSame(403, $n->get('/reglages/mot-de-passe')->getStatusCode());
        $n->get('/reglages');
        self::assertSame(403, $n->post('/reglages/mot-de-passe', ['ancien' => Jeu::MOT_DE_PASSE, 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU])->getStatusCode());
        self::assertSame(303, $this->navigateur('198.51.100.8')->connecter('a@exemple.ch')->getStatusCode());
    }

    /** Une session ouverte avant cette version n'a pas de numéro : elle reste ouverte au déploiement. */
    public function testUneSessionSansNumeroResteOuverteTantQueRienNeChange(): void
    {
        $n = $this->navigateur();
        $n->connecter('a@exemple.ch');
        $n->session->remove('version_session');
        self::assertSame(200, $n->get('/')->getStatusCode());
    }

    /**
     * Signalé par Xavier le 2026-11-19 : sans l'adresse du compte dans la page, un gestionnaire de mots de passe
     * range le nouveau mot de passe sans identifiant, ou sur une autre entrée.
     */
    public function testLesPagesDeMotDePassePortentLAdresseDuCompte(): void
    {
        $champ = '/<input[^>]*autocomplete="username"[^>]*value="a@exemple\.ch"[^>]*readonly/';
        $n = $this->navigateur();
        $this->demander($n, 'a@exemple.ch');
        [$lien] = $this->liensRecus();
        self::assertMatchesRegularExpression($champ, (string) $n->get($lien)->getContent());
        // Mot de passe refusé : la page revient avec l'adresse.
        $refus = $n->post($lien, ['mot_de_passe' => 'court', 'confirmation' => 'court']);
        self::assertSame(422, $refus->getStatusCode());
        self::assertMatchesRegularExpression($champ, (string) $refus->getContent());

        $pc = $this->navigateur('198.51.100.2');
        $pc->connecter('a@exemple.ch');
        $page = (string) $pc->get('/reglages/mot-de-passe')->getContent();
        self::assertMatchesRegularExpression($champ, $page);
        // L'identifiant précède les mots de passe : c'est l'ordre que lisent les gestionnaires.
        self::assertLessThan(strpos($page, 'name="ancien"'), strpos($page, 'autocomplete="username"'));
        $refus = $pc->post('/reglages/mot-de-passe', ['ancien' => 'faux', 'mot_de_passe' => self::NOUVEAU, 'confirmation' => self::NOUVEAU]);
        self::assertSame(422, $refus->getStatusCode());
        self::assertMatchesRegularExpression($champ, (string) $refus->getContent());
    }
}
