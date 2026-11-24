<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Audio\Rejeu;
use OffreGen\Audio\StockageAudio;
use OffreGen\Audio\Transcripteur;
use OffreGen\Audio\TranscripteurFactice;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotDictees;
use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\PorteeEquipe;
use OffreGen\Offre\ExtracteurFactice;
use OffreGen\Systeme\Chronometre;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

/**
 * Rejouer une dictée depuis son rapport (sous-chantier 31, D2 de Xavier, PLAN lot 7) : transcription et appels refaits,
 * chronométrés à part ; l'offre, les compteurs et les courriels ne bougent pas.
 */
final class RejeuDicteeTest extends TestCase
{
    private Jeu $jeu;
    private Dossiers $dossiers;
    private Navigateur $n;
    private int $id;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->id = $this->jeu->enregistrement['a'];
        $audio = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}";
        @mkdir($audio, 0777, true);
        copy(dirname(__DIR__) . '/fixtures/audio/visite.m4a', "$audio/{$this->jeu->uuid['a']}.m4a");
        BaseDeTest::pdo()->exec("UPDATE artisan SET administrateur = 1, role = NULL, guide_equipe_le = '2026-11-01 08:00:00' WHERE id = {$this->jeu->a}");
        $this->n = new Navigateur(Monde::conteneur(env: $this->dossiers->env()));
        $this->n->connecter('a@exemple.ch');
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function rejouer(): int
    {
        $this->n->get("/admin/dictees/{$this->id}");
        return $this->n->post("/admin/dictees/{$this->id}/rejouer", [])->getStatusCode();
    }

    /** @return array<string, mixed> */
    private function offre(): array
    {
        return BaseDeTest::ligne("SELECT o.titre, (SELECT COUNT(*) FROM ligne l WHERE l.offre_id = o.id) AS lignes,
            (SELECT GROUP_CONCAT(l.designation, '|', l.quantite_milliemes) FROM ligne l WHERE l.offre_id = o.id) AS contenu
            FROM offre o WHERE o.id = {$this->jeu->offre['a']}");
    }

    public function testLeRejeuRefaitLaDicteeSansToucherALOffre(): void
    {
        $avant = $this->offre();
        self::assertSame(303, $this->rejouer());
        self::assertSame('en_cours', BaseDeTest::ligne("SELECT statut FROM dictee_rejeu WHERE enregistrement_id = {$this->id}")['statut']);

        $this->n->c->apresReponse()->executer();

        $rejeu = BaseDeTest::ligne("SELECT id, statut, transcription, lignes, erreur, demande_par FROM dictee_rejeu WHERE enregistrement_id = {$this->id}");
        self::assertSame(['fini', TranscripteurFactice::TEXTE, $this->jeu->a], [$rejeu['statut'], $rejeu['transcription'], $rejeu['demande_par']], (string) $rejeu['erreur']);
        self::assertGreaterThan(0, $rejeu['lignes']);
        self::assertSame($avant, $this->offre(), 'offre intacte');
        self::assertSame([], $this->dossiers->courriels(), 'aucun courriel');

        $r = BaseDeTest::pdo()->query("SELECT DISTINCT processus FROM dictee_etape WHERE rejeu_id = {$rejeu['id']}");
        self::assertNotFalse($r);
        self::assertSame(['rejeu'], $r->fetchAll(\PDO::FETCH_COLUMN));
        $etapes = BaseDeTest::pdo()->query("SELECT etape FROM dictee_etape WHERE rejeu_id = {$rejeu['id']}");
        self::assertNotFalse($etapes);
        self::assertSame(['transcription'], $etapes->fetchAll(\PDO::FETCH_COLUMN), 'factices : seule l\'attente du texte est chronométrée hors des API');

        $page = (string) $this->n->get("/admin/dictees/{$this->id}")->getContent();
        self::assertStringContainsString('Rejeu du', $page);
        self::assertStringContainsString(TranscripteurFactice::TEXTE, $page);
        // Recette du 2026-11-24 : les codes de statut (« pret », « en_cours », « recu ») s'affichaient tels quels.
        self::assertStringContainsString('<tr><th>Original</th><td>reçue</td>', $page);
        self::assertStringNotContainsString('>recu<', $page);
    }

    public function testUnSeulRejeuEnCoursEtPasSansAudio(): void
    {
        $premier = $this->rejouer();
        $second = $this->rejouer();
        self::assertSame([303, 303], [$premier, $second]);
        self::assertSame(1, BaseDeTest::compter('dictee_rejeu', "enregistrement_id = {$this->id}"), 'second refusé tant que le premier tourne');
        self::assertStringContainsString('déjà en cours', (string) $this->n->get("/admin/dictees/{$this->id}")->getContent());

        BaseDeTest::pdo()->exec("UPDATE enregistrement SET fichier = NULL WHERE id = {$this->id}");
        BaseDeTest::pdo()->exec("UPDATE dictee_rejeu SET statut = 'fini' WHERE enregistrement_id = {$this->id}");
        // Sans audio, le rapport n'a plus de bouton : l'envoi direct (jeton pris sur « Plus ») est refusé par le serveur.
        self::assertStringNotContainsString('/rejouer', (string) $this->n->get("/admin/dictees/{$this->id}")->getContent());
        $this->n->get('/admin/plus');
        self::assertSame(303, $this->n->post("/admin/dictees/{$this->id}/rejouer", [])->getStatusCode());
        self::assertSame(1, BaseDeTest::compter('dictee_rejeu', "enregistrement_id = {$this->id}"), 'audio supprimé : rien de lancé');
    }

    /** Revue S2 et S3 : deux rejeux au plus sur tout le site, rien pour un audio plus long que permis ; geste noté au journal. */
    public function testPlafondDuSiteDureeEtJournal(): void
    {
        $b = $this->jeu->enregistrement['b'];
        $pdo = BaseDeTest::pdo();
        $pdo->exec("INSERT INTO dictee_rejeu (enregistrement_id, demande_par, demande_le, statut) VALUES ($b, NULL, '2026-11-07 10:00:00', 'en_cours'), ($b, NULL, '2026-11-07 10:00:00', 'en_cours')");
        self::assertSame(303, $this->rejouer());
        self::assertSame(0, BaseDeTest::compter('dictee_rejeu', "enregistrement_id = {$this->id}"), 'déjà deux rejeux en cours sur le site');
        self::assertStringContainsString('rejeux en cours sur le site', (string) $this->n->get("/admin/dictees/{$this->id}")->getContent());

        $pdo->exec("DELETE FROM dictee_rejeu");
        $pdo->exec("UPDATE enregistrement SET duree_ms = 3 * 3600 * 1000 WHERE id = {$this->id}");
        self::assertSame(303, $this->rejouer());
        self::assertSame(0, BaseDeTest::compter('dictee_rejeu', '1 = 1'), 'audio de 3 h : pas de transcription payante');

        $pdo->exec("UPDATE enregistrement SET duree_ms = 60000 WHERE id = {$this->id}");
        self::assertSame(303, $this->rejouer());
        self::assertSame(1, BaseDeTest::compter('journal_admin', "geste = 'rejeu_dictee' AND acteur_id = {$this->jeu->a} AND detail = 'dictée {$this->id}'"));
    }

    /** Revue E2 : un rejeu dont la transcription n'arrive jamais ferme son attente en erreur, sans étape laissée ouverte. */
    public function testUnRejeuSansTexteFermeSonAttenteEnErreur(): void
    {
        $horloge = new MockClock('2026-11-07 10:00:00', 'UTC');
        $base = $this->jeu->base;
        $chrono = new Chronometre(new DepotEtapes($base), new FileTraitement($base), $horloge, new NullLogger());
        $muet = new class implements Transcripteur {
            public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
            {
                return 'lot-muet';
            }

            public function resultat(string $reference): ?string
            {
                return null;
            }
        };
        $id = (new DepotDictees($base, new PorteeEquipe($this->jeu->a, true)))->creerRejeu($this->id, $this->jeu->a, $horloge->now(), $horloge->now()->modify('-40 minutes'), 2);
        self::assertNotNull($id);
        (new Rejeu($base, new DepotEtapes($base), $muet, new ExtracteurFactice(), new DepotArtisans($base),
            new StockageAudio($this->dossiers->env()['AUDIO_DOSSIER']), $chrono, new NullLogger()))->executer($id);

        self::assertSame('echec', BaseDeTest::ligne("SELECT statut FROM dictee_rejeu WHERE id = $id")['statut']);
        $etapes = BaseDeTest::pdo()->query("SELECT CONCAT(etape, ' ', issue) FROM dictee_etape WHERE rejeu_id = $id");
        self::assertNotFalse($etapes);
        self::assertSame(['transcription erreur'], $etapes->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testSansJetonNiDroitRienNeSeLance(): void
    {
        $this->n->get("/admin/dictees/{$this->id}");
        self::assertSame(403, $this->n->post("/admin/dictees/{$this->id}/rejouer", [], avecJeton: false)->getStatusCode());
        $artisan = new Navigateur(Monde::conteneur(env: $this->dossiers->env()));
        $artisan->connecter('b@exemple.ch');
        $artisan->get('/');
        self::assertSame(404, $artisan->post("/admin/dictees/{$this->id}/rejouer", [])->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('dictee_rejeu', '1 = 1'));
    }
}
