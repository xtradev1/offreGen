<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Envoyer (D8, maquette 24) et facturer en trois touchers (maquette 25, réponses de Xavier du 2026-11-15).
 * D8 : « Envoyer » passe par le partage du téléphone, le PDF joint ; l'offre est notée envoyée quand le partage a
 * réussi. Ensuite, l'action principale de l'offre devient « Facturer », qui s'ouvre dans une feuille.
 * E1 : la feuille montre l'aperçu ; rien n'est créé avant « Créer la facture ». E2 : le bouton s'appelle « Créer la
 * facture ». E3 : pas de page de confirmation. E4 : 20/30/40/50 % ou un montant en CHF, TVA comprise. E5 : la facture
 * créée s'ouvre en PDF ; un brouillon ne sert qu'à corriger les lignes d'une finale et n'a pas encore de numéro.
 *
 * L'offre du jeu : 12,5 m² à 18.50, soit 231.25 HT, 18.73 de TVA, 250.00 TTC arrondi.
 */
final class EnvoyerFacturerTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        // Une offre avec son client et un plan de trois acomptes, comme la démo.
        BaseDeTest::pdo()->exec("UPDATE offre SET client_id = {$this->jeu->client['a']} WHERE id = {$this->jeu->offre['a']}");
        $this->a->get('/reglages');
        $this->a->post('/reglages', ['paiement_type' => 'acomptes', 'paiement_acomptes_choix' => '30/30/40', 'retour' => '/reglages']);
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    /**
     * @param array<string, string> $champs
     * @return array{int, string} le code et l'adresse de la redirection
     */
    private function facturer(array $champs): array
    {
        $this->page($this->offre . '/facturer');
        $r = $this->a->post($this->offre . '/facturer', $champs + ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '2026-050', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer']);
        return [$r->getStatusCode(), (string) ($r->headers->get('Location') ?? $r->getContent())];
    }

    public function testEnvoyerPartageLePdfEtNoteLEnvoi(): void
    {
        $apercu = $this->page($this->offre . '/apercu');
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/pdf\" download=\"Offre-\d+\.pdf\" data-partager data-titre=\"[^\"]+\">.*Envoyer</a>#s", $apercu);
        self::assertMatchesRegularExpression("#<form method=\"post\" action=\"{$this->offre}/envoyee\" data-apres-partage>#", $apercu);

        self::assertNull(BaseDeTest::ligne("SELECT envoyee_le FROM offre WHERE id = {$this->jeu->offre['a']}")['envoyee_le']);
        $r = $this->a->post($this->offre . '/envoyee', []);
        self::assertSame(303, $r->getStatusCode());
        self::assertNotNull(BaseDeTest::ligne("SELECT envoyee_le FROM offre WHERE id = {$this->jeu->offre['a']}")['envoyee_le']);
        $offre = $this->page($this->offre);
        self::assertMatchesRegularExpression('#<span class="status pret">Envoyée le \d{2}\.\d{2}\.\d{4}</span>#', $offre);
        // E1 : « Facturer » ouvre la feuille par-dessus l'offre (la page seule sans JavaScript).
        self::assertMatchesRegularExpression("#<a class=\"btn primary grand\" href=\"{$this->offre}/facturer\" data-en-feuille>Facturer#", $offre);
    }

    /** La feuille : l'acompte suivant choisi, les puces, le bouton qui dit le montant ; plus de « Vérifier avant d'émettre ». */
    public function testLaFeuilleFacturer(): void
    {
        $page = $this->page($this->offre . '/facturer');
        self::assertMatchesRegularExpression('#<form id="facturer" method="post" action="' . $this->offre . '/facturer" data-facturer data-apercu="' . $this->offre . '/facturer/apercu" data-sortir>#', $page);
        foreach (['20', '30', '40', '50', 'chf'] as $v) {
            self::assertMatchesRegularExpression('#<input type="radio" name="pourcent" value="' . $v . '"' . ($v === '30' ? ' checked' : '') . '>#', $page, $v);
        }
        self::assertMatchesRegularExpression('#<input id="montant" name="montant"[^>]*inputmode="decimal"#', $page);
        // 30 % de 231.25 HT = 69.38, TVA 5.62, 75.00 TTC.
        self::assertStringContainsString("<button class=\"btn primary grand\" type=\"submit\" form=\"facturer\" name=\"suite\" value=\"creer\" data-bouton-creer data-attente=\"Création…\">Créer la facture · CHF\u{00A0}75.00</button>", $page);
        self::assertStringContainsString('data-apercu-mini', $page);
        self::assertStringContainsString('Aperçu', $page);
        self::assertStringNotContainsString('Vérifier avant', $page);
        self::assertStringNotContainsString('Émettre', $page);
    }

    /** Trois touchers : Facturer (GET), Créer la facture (POST), et le PDF prêt à envoyer. */
    public function testCreerLaFactureLEmetEtOuvreSonPdf(): void
    {
        [$code, $vers] = $this->facturer(['pourcent' => '25']);
        self::assertSame(303, $code, $vers);
        $f = BaseDeTest::ligne("SELECT * FROM facture WHERE numero = '2026-050'");
        self::assertSame(['emise', 25, null], [$f['statut'], (int) $f['acompte_pourcent'], $f['acompte_nombre']]);
        self::assertSame("/factures/{$f['id']}/apercu", $vers);
        self::assertStringContainsString("Acompte 1 (25\u{00A0}%), selon offre", (string) BaseDeTest::ligne("SELECT designation FROM facture_ligne WHERE facture_id = {$f['id']}")['designation'],
            'Hors plan : pas de « sur 3 ».');

        $apercu = $this->page("/factures/{$f['id']}/apercu");
        self::assertMatchesRegularExpression("#data-pdf=\"/factures/{$f['id']}/pdf\"#", $apercu);
        self::assertMatchesRegularExpression("#href=\"/factures/{$f['id']}/pdf\" download=\"Facture-2026-050\.pdf\" data-partager#", $apercu);
    }

    public function testLAcompteDuPlanGardeSonRang(): void
    {
        $this->facturer([]);
        $f = BaseDeTest::ligne("SELECT * FROM facture WHERE numero = '2026-050'");
        self::assertSame([30, 3], [(int) $f['acompte_pourcent'], (int) $f['acompte_nombre']]);
        self::assertStringContainsString("Acompte 1 sur 3 (30\u{00A0}%)", (string) BaseDeTest::ligne("SELECT designation FROM facture_ligne WHERE facture_id = {$f['id']}")['designation']);
    }

    /** E4 : un montant tapé TVA comprise ; la facture tombe dessus. */
    public function testUnAcompteEnFrancsTvaComprise(): void
    {
        [$code, $vers] = $this->facturer(['pourcent' => 'chf', 'montant' => "100.00"]);
        self::assertSame(303, $code, $vers);
        $f = BaseDeTest::ligne("SELECT * FROM facture WHERE numero = '2026-050'");
        self::assertSame(['emise', 10000, null], [$f['statut'], (int) $f['ttc'], $f['acompte_pourcent']]);
        self::assertSame("Acompte 1, selon offre n°\u{00A0}1", (string) BaseDeTest::ligne("SELECT designation FROM facture_ligne WHERE facture_id = {$f['id']}")['designation']);
    }

    public function testUnMontantImpossibleEstRefuseSansRienEcrire(): void
    {
        foreach (['' => 'Indiquez le montant', '0' => 'Indiquez le montant', 'abc' => 'Indiquez le montant', '250.05' => 'reste à facturer'] as $montant => $message) {
            [$code, $page] = $this->facturer(['pourcent' => 'chf', 'montant' => (string) $montant]);
            self::assertSame(422, $code, "montant « $montant »");
            self::assertStringContainsString($message, $page, "montant « $montant »");
        }
        [$code, $page] = $this->facturer(['pourcent' => '120']);
        self::assertSame(422, $code);
        self::assertStringContainsString('Pourcentage entier, de 1 à 100', $page);
        self::assertSame(0, BaseDeTest::compter('facture'));
    }

    /** E1 : l'aperçu se recalcule à chaque choix, et n'écrit rien. */
    public function testLApercuNEcritRien(): void
    {
        $this->page($this->offre . '/facturer');
        $apercu = $this->page($this->offre . '/facturer/apercu?genre=acompte&pourcent=chf&montant=100&numero=2026-050&date=2026-11-07&echeance=2026-12-07');
        self::assertStringContainsString('data-a-payer="10000"', $apercu);
        self::assertStringContainsString("data-bouton=\"Créer la facture · CHF\u{00A0}100.00\"", $apercu);
        self::assertStringContainsString('<article class="paper apercu">', $apercu);
        self::assertStringContainsString('2026-050', $apercu);
        $finale = $this->page($this->offre . '/facturer/apercu?genre=finale&numero=2026-050&date=2026-11-07&echeance=2026-12-07');
        self::assertStringContainsString('data-a-payer="25000"', $finale);
        self::assertStringContainsString('Peinture murale a', $finale);
        $refus = $this->a->get($this->offre . '/facturer/apercu?genre=acompte&pourcent=chf&montant=9999');
        self::assertSame(422, $refus->getStatusCode());
        self::assertStringContainsString('reste à facturer', (string) $refus->getContent());
        self::assertSame(0, BaseDeTest::compter('facture'));
    }

    /** E5 : « Corriger des lignes » ouvre un brouillon de la finale, sans numéro ; un acompte n'en a pas besoin. */
    public function testCorrigerDesLignesSeulementPourLaFinale(): void
    {
        [$code] = $this->facturer(['suite' => 'corriger']);
        self::assertSame(422, $code);
        self::assertSame(0, BaseDeTest::compter('facture'));

        $proposes = $this->page($this->offre . '/facturer');
        self::assertMatchesRegularExpression('#name="numero" value="(2026-001)"#', $proposes);
        [$code, $vers] = $this->facturer(['genre' => 'finale', 'numero' => '2026-001', 'suite' => 'corriger']);
        self::assertSame(303, $code, $vers);
        $f = BaseDeTest::ligne('SELECT * FROM facture');
        self::assertSame(['brouillon', null, 'finale'], [$f['statut'], $f['numero'], $f['genre']]);
        self::assertSame("/factures/{$f['id']}", $vers);
        // Un numéro tapé, différent du proposé, reste réservé au brouillon.
        BaseDeTest::pdo()->exec('DELETE FROM facture');
        $this->facturer(['genre' => 'finale', 'numero' => 'F-7', 'suite' => 'corriger']);
        self::assertSame('F-7', BaseDeTest::ligne('SELECT numero FROM facture')['numero']);
    }

    /** Revue du 25 : un double toucher (ou un réseau lent qui renvoie) ne crée qu'une facture. */
    public function testUnDoubleEnvoiNeCreeQuUneFacture(): void
    {
        $page = $this->page($this->offre . '/facturer');
        self::assertSame(1, preg_match('#<input type="hidden" name="jeton" value="([a-f0-9]{32})">#', $page, $m), 'La feuille porte un jeton.');
        $champs = ['genre' => 'acompte', 'pourcent' => '30', 'numero' => '2026-050', 'date' => '2026-11-07', 'echeance' => '2026-12-07', 'suite' => 'creer', 'jeton' => $m[1] ?? ''];
        $premier = $this->a->post($this->offre . '/facturer', $champs);
        $this->page($this->offre . '/facturer'); // le jeton CSRF du navigateur de test ; le jeton de la feuille reste le premier
        $second = $this->a->post($this->offre . '/facturer', ['numero' => '2026-051'] + $champs);
        self::assertSame(1, BaseDeTest::compter('facture'));
        self::assertSame($premier->headers->get('Location'), $second->headers->get('Location'), 'Le second envoi retrouve la facture du premier.');
        self::assertMatchesRegularExpression('#<button class="btn primary grand" type="submit" form="facturer" name="suite" value="creer" data-bouton-creer data-attente="Création…">#', $page);
    }

    /**
     * Revue du 25 : un acompte refait (« Annuler et refaire ») qui attend en brouillon ne serait pas déduit par la
     * finale ; la finale ne s'émet donc pas tant qu'il attend, et un acompte ne s'émet plus après la finale émise.
     */
    public function testUnAcompteEnBrouillonEtLaFinaleNeSeCroisentPas(): void
    {
        [, $vers] = $this->facturer([]);
        $acompte = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE numero = '2026-050'")['id'];
        $this->page("/factures/$acompte/annuler");
        $this->a->post("/factures/$acompte/annuler", ['refaire' => '1']);
        $refait = (int) BaseDeTest::ligne("SELECT id FROM facture WHERE genre = 'acompte' AND statut = 'brouillon'")['id'];
        [$code, $page] = $this->facturer(['genre' => 'finale', 'numero' => '']);
        self::assertSame(422, $code);
        self::assertStringContainsString('attend en brouillon', $page);
        self::assertSame(0, BaseDeTest::compter('facture', "genre = 'finale'"));

        // Finale émise par la base (cas d'avant ce contrôle) : l'acompte refait ne s'émet plus.
        $artisan = $this->jeu->a;
        BaseDeTest::pdo()->exec("INSERT INTO facture (artisan_id, offre_id, numero, genre, statut, date_facture, echeance, titre, client_nom, tva_pour_dix_mille, arrondi_cinq, ht, tva, arrondi, ttc, deja_facture, a_payer, cree_le, emise_le)
            VALUES ($artisan, {$this->jeu->offre['a']}, '2026-099', 'finale', 'emise', '2026-11-07', '2026-12-07', 'Offre a', 'Client a', 810, 1, 23125, 1873, 2, 25000, 0, 25000, NOW(), NOW())");
        $this->page("/factures/$refait");
        $r = $this->a->post("/factures/$refait/emettre", []);
        self::assertSame("/factures/$refait", $r->headers->get('Location'));
        self::assertSame(1, BaseDeTest::compter('facture', "id = $refait AND statut = 'brouillon'"));
        self::assertStringContainsString('finale', $this->page("/factures/$refait"));
    }

    /** E1 : si l'émission est empêchée (ligne sans prix), rien ne reste en base ; la feuille dit pourquoi. */
    public function testUneEmissionEmpecheeNeLaissePasDeBrouillon(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET prix_centimes = 0 WHERE offre_id = {$this->jeu->offre['a']}");
        [$code, $page] = $this->facturer(['genre' => 'finale']);
        self::assertSame(422, $code);
        self::assertStringContainsString('ligne sans prix', $page);
        self::assertSame(0, BaseDeTest::compter('facture'));
    }
}
