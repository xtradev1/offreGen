<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Les écrans des prospects (docs/plans/2026-11-24_prospects, maquette validée par Xavier le 2026-11-24). Le point
 * dur : un commercial voit qu'un collègue suit un artisan, sans lire son téléphone ni ses notes, par aucune route
 * (P2 = A), et n'écrit que sur les fiches qu'il tient. L'horloge de test est au 7 novembre 2026.
 */
final class ProspectsEcransTest extends TestCase
{
    private Jeu $jeu;
    /** @var array<string, string> */
    private array $motsDePasse = [];

    private const ROUX = ['entreprise' => 'Peinture Roux Sàrl', 'localite' => '2800 Delémont', 'metier' => 'peintre', 'telephone' => '079 555 01 42', 'courriel' => 'marc@roux-peinture.example',
        'contact' => 'Marc Roux', 'origine' => 'grossiste', 'prend' => '1'];
    /** Ce qu'un collègue ne doit lire nulle part. */
    private const SECRETS = ['079 555 01 42', '0795550142', 'roux-peinture.example', 'Marc Roux', 'Quatre à cinq offres'];

    protected function setUp(): void
    {
        // A est Nina, B est Léo, commerciaux ; une modératrice et un administrateur.
        $this->jeu = new Jeu();
        $pdo = BaseDeTest::pdo();
        $pdo->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Nina Rey', guide_equipe_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->a}");
        $pdo->exec("UPDATE artisan SET role = 'commercial', entreprise = 'Léo Favre', guide_equipe_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->b}");
        $pdo->exec("UPDATE artisan SET role = 'moderateur' WHERE id = " . $this->creerCompte('mod@exemple.ch', 'Maud Perret'));
        $pdo->exec('UPDATE artisan SET administrateur = 1 WHERE id = ' . $this->creerCompte('chef@exemple.ch', 'Le Chef'));
    }

    private function creerCompte(string $courriel, string $nom): int
    {
        $this->motsDePasse[$courriel] = Monde::conteneur()->comptes()->creer($courriel, $nom, false, null, 10);
        return (int) BaseDeTest::ligne("SELECT id FROM artisan WHERE courriel = '$courriel'")['id'];
    }

    private function navigateur(string $courriel): Navigateur
    {
        static $ip = 60;
        $n = new Navigateur(Monde::conteneur(), '198.51.100.' . $ip++);
        self::assertSame(303, $n->connecter($courriel, $this->motsDePasse[$courriel] ?? Jeu::MOT_DE_PASSE)->getStatusCode());
        return $n;
    }

    /**
     * Un envoi de formulaire : le jeton vient d'une page qui en porte un, relue avant chaque envoi.
     *
     * @param array<string, string> $champs
     */
    private function poster(Navigateur $n, string $chemin, array $champs): \Symfony\Component\HttpFoundation\Response
    {
        $n->get('/admin/prospects/nouveau');
        return $n->post($chemin, $champs);
    }

    /** Nina saisit Peinture Roux par l'écran et note une phrase ; rend le numéro de la fiche. */
    private function fichDeNina(Navigateur $nina): int
    {
        $nina->get('/admin/prospects/nouveau');
        $reponse = $this->poster($nina, '/admin/prospects', self::ROUX);
        self::assertSame(303, $reponse->getStatusCode(), (string) $reponse->getContent());
        $id = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM prospect')['id'];
        self::assertSame("/admin/prospects/$id", $reponse->headers->get('Location'));
        self::assertSame(303, $this->poster($nina, "/admin/prospects/$id/note", ['texte' => 'Quatre à cinq offres par mois, faites sur Word.'])->getStatusCode());
        return $id;
    }

    public function testLaRubriqueEstAuMenuDesTroisRolesEtDitParOuCommencer(): void
    {
        foreach (['a@exemple.ch', 'mod@exemple.ch', 'chef@exemple.ch'] as $courriel) {
            $page = (string) $this->navigateur($courriel)->get('/admin/prospects')->getContent();
            self::assertStringContainsString('href="/admin/prospects" aria-current="page"', $page, $courriel);
            self::assertStringContainsString("Notez l'artisan que vous venez de voir", $page, $courriel);
            self::assertSame($courriel !== 'a@exemple.ch', str_contains($page, 'vue=retard'), "$courriel : « En retard » pour qui lit tout");
        }
    }

    public function testChercherPuisCreerPuisLireSaFiche(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        self::assertStringContainsString('Tapez au moins trois lettres', (string) $nina->get('/admin/prospects/chercher?q=ro')->getContent());
        $vide = (string) $nina->get('/admin/prospects/chercher?q=roux+pein')->getContent();
        self::assertStringContainsString('Aucune fiche et aucun compte à ce nom', $vide);
        self::assertStringContainsString('href="/admin/prospects/nouveau?nom=roux%20pein"', $vide);
        self::assertStringContainsString('value="roux pein"', (string) $nina->get('/admin/prospects/nouveau?nom=roux+pein')->getContent());

        $id = $this->fichDeNina($nina);
        $fiche = $nina->get("/admin/prospects/$id");
        self::assertSame(200, $fiche->getStatusCode());
        self::assertStringContainsString('no-store', (string) $fiche->headers->get('Cache-Control'));
        foreach (['Peinture Roux Sàrl', '2800 Delémont', 'Peintre', '079 555 01 42', 'Quatre à cinq offres', 'Créer une démo', 'Je lâche ce prospect', 'aria-pressed="true">À contacter'] as $attendu) {
            self::assertStringContainsString($attendu, (string) $fiche->getContent());
        }
        // La recherche retrouve la fiche dans n'importe quel ordre de mots, sans la forme juridique.
        self::assertStringContainsString("/admin/prospects/$id", (string) $nina->get('/admin/prospects/chercher?q=roux+pein')->getContent());
        self::assertStringContainsString('1 suivi par vous', (string) $nina->get('/admin/prospects')->getContent());
    }

    public function testUneSaisieRefuseeSeDitSurLeFormulaire(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $nina->get('/admin/prospects/nouveau');
        $reponse = $this->poster($nina, '/admin/prospects', ['telephone' => '12'] + self::ROUX);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('Vérifiez ce numéro', (string) $reponse->getContent());
        self::assertStringContainsString('value="Peinture Roux Sàrl"', (string) $reponse->getContent());
        self::assertSame(0, BaseDeTest::compter('prospect'));
        // Sans jeton, rien ne s'écrit.
        self::assertNotSame(303, $nina->post('/admin/prospects', self::ROUX, [], false)->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('prospect'));
    }

    public function testUnCollegueVoitLeNomEtQuiSuitRienDAutre(): void
    {
        $id = $this->fichDeNina($this->navigateur('a@exemple.ch'));
        $leo = $this->navigateur('b@exemple.ch');
        $pages = [
            'liste' => (string) $leo->get('/admin/prospects?vue=tous')->getContent(),
            'fiche' => (string) $leo->get("/admin/prospects/$id")->getContent(),
            'recherche' => (string) $leo->get('/admin/prospects/chercher?q=roux')->getContent(),
            'filtre' => (string) $leo->get('/admin/prospects?vue=tous&q=roux')->getContent(),
            'libres' => (string) $leo->get('/admin/prospects?vue=libres')->getContent(),
        ];
        foreach (['liste', 'fiche', 'recherche', 'filtre'] as $page) {
            self::assertStringContainsString('Peinture Roux Sàrl', $pages[$page], $page);
            self::assertStringContainsString('Nina Rey', $pages[$page], $page);
        }
        self::assertStringContainsString('Le téléphone et les notes restent chez Nina Rey', $pages['fiche']);
        self::assertStringNotContainsString('Peinture Roux', $pages['libres']);
        foreach ($pages as $nom => $page) {
            foreach (self::SECRETS as $secret) {
                self::assertStringNotContainsString($secret, $page, "$nom montre « $secret »");
            }
            self::assertStringNotContainsString('Je prends', $page, $nom);
        }
        // Aucun geste, par aucune route : la fiche d'un autre n'existe pas pour écrire.
        $leo->get('/admin/prospects');
        $gestes = ['lacher' => [], 'etat' => ['etat' => 'contacte'], 'pas' => ['texte' => 'Rappeler', 'date' => '2026-11-10'], 'pas-fait' => [], 'note' => ['texte' => 'Je passe.'], 'demo' => []];
        foreach ($gestes as $geste => $champs) {
            self::assertSame(404, $this->poster($leo, "/admin/prospects/$id/$geste", $champs)->getStatusCode(), $geste);
        }
        // « Je prends » sur une fiche tenue : rien ne s'écrit, la fiche se rouvre et le dit.
        $pris = $this->poster($leo, "/admin/prospects/$id/prendre", []);
        self::assertSame([303, "/admin/prospects/$id"], [$pris->getStatusCode(), $pris->headers->get('Location')]);
        self::assertStringContainsString('déjà suivi', (string) $leo->get("/admin/prospects/$id")->getContent());
        self::assertSame(404, $this->poster($leo, '/admin/prospects/999999/prendre', [])->getStatusCode());
        self::assertSame(404, $leo->get("/admin/prospects/$id/modifier")->getStatusCode());
        self::assertSame(404, $this->poster($leo, "/admin/prospects/$id", ['entreprise' => 'Volé', 'confirme' => '1'] + self::ROUX)->getStatusCode());
        self::assertSame(404, $leo->get("/admin/prospects/$id/fusion")->getStatusCode());
        self::assertSame(404, $leo->get('/admin/prospects/999999')->getStatusCode());
        self::assertSame(404, $leo->get('/admin/prospects/abc')->getStatusCode());
        $fiche = BaseDeTest::ligne("SELECT entreprise, etat, suivi_par, pas_texte, compte_id FROM prospect WHERE id = $id");
        self::assertSame(['Peinture Roux Sàrl', 'a_contacter', $this->jeu->a, null, null], [$fiche['entreprise'], $fiche['etat'], (int) $fiche['suivi_par'], $fiche['pas_texte'], $fiche['compte_id']]);
        self::assertSame(1, BaseDeTest::compter('prospect_note'));
    }

    public function testLeDoublonSeSignaleSansRienReveler(): void
    {
        $id = $this->fichDeNina($this->navigateur('a@exemple.ch'));
        $leo = $this->navigateur('b@exemple.ch');
        $leo->get('/admin/prospects/nouveau');
        $saisie = ['entreprise' => 'Roux Rénovation', 'courriel' => '', 'contact' => ''] + self::ROUX;
        $avertissement = $this->poster($leo, '/admin/prospects', $saisie);
        self::assertSame(200, $avertissement->getStatusCode());
        $page = (string) $avertissement->getContent();
        foreach (['peut-être déjà noté', 'Peinture Roux Sàrl', 'Même téléphone', 'suivi par Nina Rey', "C'est une autre entreprise", 'name="confirme" value="1"'] as $attendu) {
            self::assertStringContainsString($attendu, $page);
        }
        foreach (['roux-peinture.example', 'Marc Roux', 'Quatre à cinq offres'] as $secret) {
            self::assertStringNotContainsString($secret, $page);
        }
        self::assertSame(1, BaseDeTest::compter('prospect'));
        self::assertSame(303, $this->poster($leo, '/admin/prospects', $saisie + ['confirme' => '1'])->getStatusCode());
        self::assertSame(2, BaseDeTest::compter('prospect'));
        // Un compte déjà chez nous : son nom, sa sorte, qui l'a amené.
        $this->creerCompte('client@exemple.ch', 'Paysages du Doubs');
        $deja = (string) $this->poster($leo, '/admin/prospects', ['entreprise' => 'Paysages du Doubs', 'localite' => 'Saint-Ursanne', 'prend' => '1'])->getContent();
        self::assertStringContainsString('Paysages du Doubs <i class="badge role">Essai</i>', $deja);
        self::assertStringContainsString('Déjà chez', $deja);
        self::assertStringContainsString('Paysages du Doubs', (string) $leo->get('/admin/prospects/chercher?q=paysages')->getContent());
        self::assertGreaterThan(0, $id);
    }

    public function testCeuxQuiLisentToutEtLaFusionReserveeALAdministrateur(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $id = $this->fichDeNina($nina);
        foreach (['mod@exemple.ch', 'chef@exemple.ch'] as $courriel) {
            $n = $this->navigateur($courriel);
            $fiche = (string) $n->get("/admin/prospects/$id")->getContent();
            foreach (['079 555 01 42', 'Quatre à cinq offres', 'suivi par Nina Rey'] as $attendu) {
                self::assertStringContainsString($attendu, $fiche, $courriel);
            }
            // Lire tout ne donne aucun geste sur la fiche d'un autre : ni attribuer, ni retirer.
            self::assertStringNotContainsString("/admin/prospects/$id/note", $fiche, $courriel);
            self::assertStringNotContainsString('Je lâche', $fiche, $courriel);
            $n->get('/admin/prospects');
            self::assertSame(404, $this->poster($n, "/admin/prospects/$id/lacher", [])->getStatusCode(), $courriel);
            self::assertSame(303, $this->poster($n, "/admin/prospects/$id/prendre", [])->getStatusCode(), $courriel);
            self::assertSame($this->jeu->a, (int) BaseDeTest::ligne("SELECT suivi_par FROM prospect WHERE id = $id")['suivi_par'], $courriel);
            self::assertSame($courriel === 'chef@exemple.ch', str_contains($fiche, "/admin/prospects/$id/fusion"), $courriel);
            self::assertSame($courriel === 'chef@exemple.ch' ? 200 : 404, $n->get("/admin/prospects/$id/fusion")->getStatusCode(), $courriel);
        }
        // Une seconde fiche du même artisan, puis la fusion par l'administrateur.
        $leo = $this->navigateur('b@exemple.ch');
        $leo->get('/admin/prospects/nouveau');
        self::assertSame(303, $this->poster($leo, '/admin/prospects', ['entreprise' => 'Roux Peinture', 'localite' => 'Courroux', 'telephone' => '', 'courriel' => '', 'contact' => '', 'ide' => 'CHE-123.456.789'] + self::ROUX)->getStatusCode());
        $autre = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM prospect')['id'];
        $chef = $this->navigateur('chef@exemple.ch');
        self::assertStringContainsString('Roux Peinture', (string) $chef->get("/admin/prospects/$id/fusion")->getContent());
        foreach (['a@exemple.ch', 'mod@exemple.ch'] as $courriel) {
            $n = $this->navigateur($courriel);
            $n->get('/admin/prospects');
            self::assertSame(404, $this->poster($n, "/admin/prospects/$id/fusion", ['avec' => (string) $autre])->getStatusCode(), $courriel);
        }
        // Deux fiches suivies par deux personnes : la fusion se refuse et le dit. Léo lâche la sienne, elle se fait.
        $refus = $this->poster($chef, "/admin/prospects/$id/fusion", ['avec' => (string) $autre]);
        self::assertSame(422, $refus->getStatusCode());
        self::assertStringContainsString('suivies par deux personnes, Nina Rey et Léo Favre', (string) $refus->getContent());
        self::assertSame(2, BaseDeTest::compter('prospect'));
        self::assertSame(303, $this->poster($leo, "/admin/prospects/$autre/lacher", [])->getStatusCode());
        self::assertSame(303, $this->poster($chef, "/admin/prospects/$id/fusion", ['avec' => (string) $autre])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('prospect'));
        self::assertStringContainsString('CHE-123.456.789', (string) $nina->get("/admin/prospects/$id")->getContent());
    }

    public function testLeParcoursDUneFiche(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $id = $this->fichDeNina($nina);
        // Le prochain pas, dû aujourd'hui : il monte dans « À faire ».
        self::assertSame(422, $this->poster($nina, "/admin/prospects/$id/pas", ['texte' => 'Rappeler', 'date' => '2027-03-01'])->getStatusCode());
        self::assertSame(303, $this->poster($nina, "/admin/prospects/$id/pas", ['texte' => 'Rappeler pour fixer la visite', 'date' => '2026-11-07'])->getStatusCode());
        $liste = (string) $nina->get('/admin/prospects')->getContent();
        foreach (['À faire', 'Rappeler pour fixer la visite', 'prévu aujourd&#039;hui', 'href="tel:0795550142"', "/admin/prospects/$id/pas-fait"] as $attendu) {
            self::assertStringContainsString($attendu, $liste);
        }
        self::assertSame(303, $this->poster($nina, "/admin/prospects/$id/pas-fait", [])->getStatusCode());
        self::assertStringContainsString('Fait&nbsp;: Rappeler pour fixer la visite', str_replace("\u{00A0}", '&nbsp;', (string) $nina->get("/admin/prospects/$id")->getContent()));
        self::assertSame(303, $this->poster($nina, "/admin/prospects/$id/etat", ['etat' => 'rendez_vous'])->getStatusCode());
        self::assertSame(422, $this->poster($nina, "/admin/prospects/$id/etat", ['etat' => 'ailleurs'])->getStatusCode());
        self::assertStringContainsString('aria-pressed="true">Rendez-vous', (string) $nina->get("/admin/prospects/$id")->getContent());
        // Modifier la fiche.
        self::assertStringContainsString('value="2800 Delémont"', (string) $nina->get("/admin/prospects/$id/modifier")->getContent());
        self::assertSame(303, $this->poster($nina, "/admin/prospects/$id", ['contact' => 'Marc et Anne Roux'] + self::ROUX)->getStatusCode());
        self::assertStringContainsString('Marc et Anne Roux', (string) $nina->get("/admin/prospects/$id")->getContent());
        // Lâcher : la fiche est libre, Léo la voit entière, notes comprises (P6 = A), et la prend.
        $lache = $this->poster($nina, "/admin/prospects/$id/lacher", []);
        self::assertSame('/admin/prospects', $lache->headers->get('Location'));
        $leo = $this->navigateur('b@exemple.ch');
        $libres = (string) $leo->get('/admin/prospects?vue=libres')->getContent();
        self::assertStringContainsString('suivi avant par Nina Rey', $libres);
        self::assertStringContainsString("/admin/prospects/$id/prendre", $libres);
        self::assertStringContainsString('Quatre à cinq offres', (string) $leo->get("/admin/prospects/$id")->getContent());
        self::assertSame(303, $this->poster($leo, "/admin/prospects/$id/prendre", [])->getStatusCode());
        self::assertSame($this->jeu->b, (int) BaseDeTest::ligne("SELECT suivi_par FROM prospect WHERE id = $id")['suivi_par']);
        self::assertStringNotContainsString('079 555 01 42', (string) $nina->get("/admin/prospects/$id")->getContent());
    }

    /**
     * Recette du 2026-11-24 sur le site de test : « mercredi 26.11 » pour un jeudi. Le serveur est à l'heure de Zurich,
     * le poste de développement en UTC : le jour de la semaine se lisait dans un autre fuseau que la date.
     */
    public function testLeJourDeLaSemaineNeDependPasDuFuseauDuServeur(): void
    {
        $fuseau = date_default_timezone_get();
        try {
            foreach (['UTC', 'Europe/Zurich', 'America/New_York'] as $zone) {
                date_default_timezone_set($zone);
                $nina = $this->navigateur('a@exemple.ch');
                $id = $this->fichDeNina($nina);
                // Le 10 novembre 2026 est un mardi.
                self::assertSame(303, $this->poster($nina, "/admin/prospects/$id/pas", ['texte' => 'Rappeler', 'date' => '2026-11-10'])->getStatusCode(), $zone);
                self::assertStringContainsString('Rappeler · mardi 10.11', (string) $nina->get('/admin/prospects')->getContent(), $zone);
                BaseDeTest::pdo()->exec('DELETE FROM prospect');
            }
        } finally {
            date_default_timezone_set($fuseau);
        }
    }

    public function testCreerUneDemoDepuisLaFiche(): void
    {
        $nina = $this->navigateur('a@exemple.ch');
        $id = $this->fichDeNina($nina);
        $reponse = $this->poster($nina, "/admin/prospects/$id/demo", []);
        self::assertSame(303, $reponse->getStatusCode());
        $compte = (int) BaseDeTest::ligne("SELECT compte_id FROM prospect WHERE id = $id")['compte_id'];
        self::assertSame("/admin/artisans/$compte/qr", $reponse->headers->get('Location'));
        self::assertSame(200, $nina->get("/admin/artisans/$compte/qr")->getStatusCode());
        $fiche = (string) $nina->get("/admin/prospects/$id")->getContent();
        self::assertStringContainsString('Démo en cours', $fiche);
        self::assertStringContainsString("/admin/artisans/$compte", $fiche);
        self::assertStringNotContainsString("/admin/prospects/$id/demo", $fiche);
        self::assertStringNotContainsString('Je lâche', $fiche);
        // Léo voit que l'artisan a une démo, sans lien vers un compte qui n'est pas le sien.
        $leo = (string) $this->navigateur('b@exemple.ch')->get("/admin/prospects/$id")->getContent();
        self::assertStringContainsString('Démo chez', $leo);
        self::assertStringNotContainsString("/admin/artisans/$compte", $leo);
        // Sans métier, la démo se refuse et le dit.
        $nina->get('/admin/prospects/nouveau');
        $this->poster($nina, '/admin/prospects', ['entreprise' => 'Sols Berbier', 'localite' => 'Moutier', 'prend' => '1']);
        $sans = (int) BaseDeTest::ligne('SELECT MAX(id) AS id FROM prospect')['id'];
        $refus = $this->poster($nina, "/admin/prospects/$sans/demo", []);
        self::assertSame(422, $refus->getStatusCode());
        self::assertStringContainsString('Indiquez d&#039;abord le métier', (string) $refus->getContent());
    }
}
