<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Donnees\DepotEtapes;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/** La page des dictées de l'administrateur (sous-chantier 31, PLAN lot 6). */
final class DicteesPageTest extends TestCase
{
    private Jeu $jeu;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $etapes = new DepotEtapes($this->jeu->base);
        $t = static fn (string $h): \DateTimeImmutable => new \DateTimeImmutable("2026-11-24 $h", new \DateTimeZone('UTC'));
        // Dictée de A : rapide. Dictée de B : l'offre 87 (extraction lente, panne, reprise).
        $a = $this->jeu->enregistrement['a'];
        $b = $this->jeu->enregistrement['b'];
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET recu_le = '2026-11-24 10:00:00', navigateur = 'Mozilla/5.0 (iPhone) Safari/604.1 Version/18.1', duree_ms = 94000 WHERE id = $a");
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET recu_le = '2026-11-24 11:05:43', tentatives = 1 WHERE id = $b");
        $etapes->ajouter($a, null, 1, 'reception', 'web', $t('10:00:00.000'), $t('10:00:00.400'));
        $etapes->ajouter($a, null, 1, 'ia_extraction', 'apres_reponse', $t('10:00:01.000'), $t('10:00:07.000'));
        $etapes->ajouter($b, null, 1, 'reception', 'web', $t('11:05:43.000'), $t('11:05:43.600'));
        $etapes->ajouter($b, null, 1, 'ia_extraction', 'apres_reponse', $t('11:06:51.500'), $t('11:07:50.900'));
        $id = $etapes->ouvrir($b, null, 1, 'ecriture', 'apres_reponse', $t('11:07:52.400'), null);
        $etapes->interrompre([$id]);
        $etapes->ajouter($b, null, 2, 'reprise', 'cron', $t('11:07:52.400'), $t('11:21:00.300'));
        $etapes->ajouter($b, null, 2, 'ia_extraction', 'cron', $t('11:21:00.300'), $t('11:21:06.300'));
    }

    private function admin(): Navigateur
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1, role = NULL, guide_equipe_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->a}");
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('a@exemple.ch');
        return $n;
    }

    public function testLAdministrateurVoitLesStatistiquesEtLaListe(): void
    {
        $r = $this->admin()->get('/admin/dictees');
        self::assertSame(200, $r->getStatusCode(), (string) $r->getContent());
        $page = (string) $r->getContent();
        self::assertStringContainsString('2 dictées', $page);
        self::assertStringContainsString('Safari, iPhone', $page);
        // Le maximum de l'extraction (59,4 + 6 s) mène au rapport de la dictée de B.
        self::assertMatchesRegularExpression('#<a href="/admin/dictees/' . $this->jeu->enregistrement['b'] . '">1\x{00A0}min\x{00A0}05\x{00A0}s</a>#u', $page);
        self::assertStringContainsString('class="accroc"', $page);
        self::assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    public function testLeFiltreReprisesNeGardeQueLaDicteeAccrochee(): void
    {
        $page = (string) $this->admin()->get('/admin/dictees?filtre=accrocs')->getContent();
        self::assertStringContainsString('1 dictée.', $page);
        self::assertStringNotContainsString('/admin/dictees/' . $this->jeu->enregistrement['a'] . '"', explode('Statistiques de la période', $page)[1] ?? '');
    }

    public function testLaPeriodeExclutLesDicteesHorsBornes(): void
    {
        $page = (string) $this->admin()->get('/admin/dictees?du=2026-11-25&au=2026-11-26')->getContent();
        self::assertStringContainsString('0 dictée', $page);
        self::assertStringContainsString('Aucune dictée chronométrée', $page);
    }

    public function testLeCsvDonneUneLigneParDicteeAvecLesDureesEnMs(): void
    {
        $r = $this->admin()->get('/admin/dictees.csv');
        self::assertSame(200, $r->getStatusCode());
        self::assertStringStartsWith('text/csv', (string) $r->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="dictees-', (string) $r->headers->get('Content-Disposition'));
        $texte = (string) $r->getContent();
        self::assertStringStartsWith("\u{FEFF}recue;offre;compte;", $texte);
        $lignes = array_map(static fn (string $l): array => str_getcsv($l, ';', '"', ''), explode("\n", trim(substr($texte, 3))));
        self::assertCount(3, $lignes, 'en-tête et deux dictées');
        $colonnes = array_flip(array_map('strval', $lignes[0]));
        $b = $lignes[1];
        // Deux dictées seulement : aucune ne dépasse deux fois la médiane de sa colonne.
        self::assertSame(['2026-11-24 12:05:43', '2', '65400', '787900', ''],
            [$b[$colonnes['recue']], $b[$colonnes['tentatives']], $b[$colonnes['ia_extraction_ms']], $b[$colonnes['reprise_ms']], $b[$colonnes['lentes']]]);
    }

    /** Revue sécurité : une valeur qu'Excel prendrait pour une formule sort précédée d'une apostrophe. */
    public function testLeCsvNeutraliseLesFormules(): void
    {
        $b = $this->jeu->enregistrement['b'];
        BaseDeTest::pdo()->exec("UPDATE dictee_etape SET modele = '=HYPERLINK(1)' WHERE enregistrement_id = $b AND etape = 'ia_extraction'");
        $texte = (string) $this->admin()->get('/admin/dictees.csv')->getContent();
        self::assertStringContainsString("'=HYPERLINK(1)", $texte);
        self::assertStringNotContainsString(';=HYPERLINK', $texte);
    }

    public function testLeRapportMontreUneFriseParTentativeEtLaReprise(): void
    {
        $page = (string) $this->admin()->get('/admin/dictees/' . $this->jeu->enregistrement['b'])->getContent();
        self::assertStringContainsString('Tentative 1, après réponse', $page);
        self::assertStringContainsString('Tentative 2, cron', $page);
        self::assertStringContainsString('verrou d\'un traitement mort, frise coupée', $page);
        self::assertStringContainsString('interrompue', $page);
        self::assertSame(404, $this->admin()->get('/admin/dictees/999999')->getStatusCode());
    }

    public function testUnModerateurNiUnArtisanNeVoientLaPage(): void
    {
        $n = new Navigateur(Monde::conteneur());
        $n->connecter('b@exemple.ch');
        self::assertSame(404, $n->get('/admin/dictees')->getStatusCode());
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 0, role = 'moderateur', guide_equipe_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->a}");
        $m = new Navigateur(Monde::conteneur());
        $m->connecter('a@exemple.ch');
        foreach (['/admin/dictees', '/admin/dictees.csv', '/admin/dictees/' . $this->jeu->enregistrement['a']] as $chemin) {
            self::assertSame(404, $m->get($chemin)->getStatusCode(), $chemin);
        }
        self::assertStringNotContainsString('/admin/dictees', (string) $m->get('/admin/plus')->getContent(), '« Plus » du modérateur sans l\'entrée');
    }

    public function testParPagesDeCent(): void
    {
        $pdo = BaseDeTest::pdo();
        for ($i = 0; $i < 100; $i++) {
            $uuid = sprintf('99999999-9999-4999-8999-%012d', $i);
            $pdo->exec("INSERT INTO enregistrement (artisan_id, uuid, nom_origine, type_mime, taille, fichier, statut, recu_le)
                VALUES ({$this->jeu->a}, '$uuid', 'x.webm', 'audio/webm', 10, NULL, 'pret', '2026-11-20 08:00:00')");
        }
        $n = $this->admin();
        self::assertStringContainsString('page 1 sur 2', (string) $n->get('/admin/dictees')->getContent());
        $page2 = (string) $n->get('/admin/dictees?page=2')->getContent();
        self::assertStringContainsString('page 2 sur 2', $page2);
        self::assertSame(2, substr_count(explode('<ul class="dictees-cartes', $page2)[1] ?? '', '<li>'), '102 dictées : deux sur la seconde page');
    }
}
