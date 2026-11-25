<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Adresse\Adresse;
use OffreGen\Audio\DureeAudio;
use OffreGen\Audio\StockageAudio;
use OffreGen\Audio\Traiteur;
use OffreGen\Audio\Transcripteur;
use OffreGen\Audio\TranscripteurFactice;
use OffreGen\Compte\Activite;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotEtapes;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Offre\Extracteur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\ExtracteurFactice;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Systeme\Chronometre;
use OffreGen\Systeme\Messagerie;
use OffreGen\Systeme\MessagerieFichier;
use OffreGen\Offre\ResultatExtraction;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Dossiers;
use OffreGen\Tests\Outils\Jeu;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class TraiteurTest extends TestCase
{
    private const UUID = '11111111-1111-4111-8111-111111111111';

    private Jeu $jeu;
    private Dossiers $dossiers;
    private MockClock $horloge;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->dossiers = new Dossiers();
        $this->horloge = new MockClock('2026-11-07 10:00:00', 'UTC');
        (new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a)))
            ->creer(self::UUID, 'visite.m4a', 'audio/x-m4a', 4322, "{$this->jeu->a}/" . self::UUID . '.m4a', $this->horloge->now());
    }

    protected function tearDown(): void
    {
        $this->dossiers->effacer();
    }

    private function traiteur(?Transcripteur $t = null, ?Extracteur $x = null, ?Messagerie $m = null, ?int $dureeMax = null): Traiteur
    {
        return new Traiteur(
            $this->jeu->base,
            new FileTraitement($this->jeu->base),
            $t ?? new TranscripteurSansFichier(),
            $x ?? new ExtracteurFactice(),
            $m ?? new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], $this->horloge),
            new DepotArtisans($this->jeu->base),
            new StockageAudio($this->dossiers->env()['AUDIO_DOSSIER']),
            $this->horloge,
            new NullLogger(),
            'https://exemple.test',
            $dureeMax === null ? null : new DureeAudio(),
            $dureeMax ?? 1800,
        );
    }

    /** @return array<string, mixed> */
    private function etat(): array
    {
        return BaseDeTest::ligne("SELECT statut, tentatives, erreur, verrou_jeton, supprimer_apres, pret_le FROM enregistrement WHERE uuid = '" . self::UUID . "'");
    }

    private function offresDeLEnregistrement(): int
    {
        return BaseDeTest::compter('offre o JOIN enregistrement e ON e.id = o.enregistrement_id', "e.uuid = '" . self::UUID . "'");
    }

    public function testParcoursDeRecuAPret(): void
    {
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        $e = $this->etat();
        self::assertSame([Statut::PRET, 0, null, '2026-11-07 10:00:00', '2026-12-07 10:00:00'],
            [$e['statut'], $e['tentatives'], $e['verrou_jeton'], $e['pret_le'], $e['supprimer_apres']]);
        self::assertSame(1, $this->offresDeLEnregistrement());
        // Le catalogue de A compte un produit : une ligne, prise chez A.
        self::assertSame(1, BaseDeTest::compter('ligne l JOIN offre o ON o.id = l.offre_id JOIN enregistrement e ON e.id = o.enregistrement_id',
            "e.uuid = '" . self::UUID . "' AND l.produit_id = {$this->jeu->produit['a']} AND l.artisan_id = {$this->jeu->a}"));
        $courriels = $this->dossiers->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString('https://exemple.test/offres/', (string) file_get_contents($courriels[0]));
        // Le courriel signe du nom de l'application (Dicta, charte du 2026-11-18), plus du nom du dépôt.
        // Depuis la mise en forme (maquette v8, 2026-11-23), le fichier porte deux versions : la signature ferme le texte.
        self::assertMatchesRegularExpression('/\R\RDicta\R--=_dicta_/', (string) file_get_contents($courriels[0]));
        self::assertStringNotContainsString('offreGen', (string) file_get_contents($courriels[0]));
    }

    /** Sous-chantier 30, lot 8 : la dictée part avec le catalogue de son artisan, jamais celui d'un autre. */
    public function testLaDicteePartAvecLeCatalogueDeSonArtisan(): void
    {
        $ecoute = new TranscripteurEcoute();

        self::assertSame(Statut::PRET, $this->traiteur($ecoute)->avancer(self::UUID));
        self::assertSame(['Offre. Peinture murale a.'], $ecoute->vocabulaires);
    }

    public function testSansCatalogueLaDicteePartSansVocabulaire(): void
    {
        BaseDeTest::pdo()->exec("DELETE FROM produit WHERE artisan_id = {$this->jeu->a}");
        $ecoute = new TranscripteurEcoute();

        self::assertSame(Statut::PRET, $this->traiteur($ecoute)->avancer(self::UUID));
        self::assertSame([''], $ecoute->vocabulaires);
    }

    /** Un compte de démonstration a une adresse inventée : avec un vrai envoi, le courriel reviendrait en erreur. */
    public function testUnCompteDeDemonstrationNeRecoitPasDeCourriel(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 1 WHERE id = {$this->jeu->a}");
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertSame([], $this->dossiers->courriels());
    }

    public function testUneDicteeSansParoleDonneUneOffreVideSansAppelerLeModele(): void
    {
        // Règle de Xavier (2026-11-12) : s'il n'a rien dit, l'offre est vide, et elle compte quand même.
        $muet = new class implements Transcripteur {
            public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
            {
                return 'ref-muet';
            }

            public function resultat(string $reference): string
            {
                return "  \n ";
            }
        };
        $modele = new MockHttpClient(static function (): never {
            throw new \LogicException('Le modèle ne doit pas être appelé pour une dictée vide.');
        });
        $extracteur = new ExtracteurApi($modele, 'https://api.exemple.test/v1', 'jeton', 'qwen-test', new NullLogger());

        self::assertSame(Statut::PRET, $this->traiteur($muet, $extracteur)->avancer(self::UUID));
        self::assertSame(1, $this->offresDeLEnregistrement());
        self::assertSame(0, BaseDeTest::compter('ligne l JOIN offre o ON o.id = l.offre_id JOIN enregistrement e ON e.id = o.enregistrement_id',
            "e.uuid = '" . self::UUID . "'"));
    }

    public function testExceptionALExtractionPuisRepriseSansDoublon(): void
    {
        $capricieux = new ExtracteurCapricieux(1);
        self::assertSame(Statut::TRANSCRIT, $this->traiteur(x: $capricieux)->avancer(self::UUID));
        $e = $this->etat();
        self::assertSame([Statut::TRANSCRIT, 1, 'Modèle injoignable', null], [$e['statut'], $e['tentatives'], $e['erreur'], $e['verrou_jeton']]);
        self::assertSame(0, $this->offresDeLEnregistrement());

        self::assertSame(Statut::PRET, $this->traiteur(x: $capricieux)->avancer(self::UUID));
        self::assertSame(1, $this->offresDeLEnregistrement());
        self::assertNull($this->etat()['erreur']);
        // Relancé une fois prêt : rien ne bouge, toujours une seule offre.
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertSame(1, $this->offresDeLEnregistrement());
    }

    public function testUnSeulTraiteurTientLeVerrou(): void
    {
        $file = new FileTraitement($this->jeu->base);
        self::assertTrue($file->prendreVerrou(self::UUID, str_repeat('a', 32), $this->horloge->now()->modify('+900 seconds'), $this->horloge->now()));
        self::assertFalse($file->prendreVerrou(self::UUID, str_repeat('b', 32), $this->horloge->now()->modify('+900 seconds'), $this->horloge->now()));

        // Le second traiteur trouve le verrou pris : il ne fait rien.
        self::assertSame(Statut::RECU, $this->traiteur()->avancer(self::UUID));
        self::assertSame(str_repeat('a', 32), $this->etat()['verrou_jeton']);
        self::assertNotContains(self::UUID, $file->aReprendre($this->horloge->now(), 10));

        // Verrou expiré (traitement mort en route) : cron le reprend.
        $this->horloge->modify('+16 minutes');
        self::assertContains(self::UUID, $file->aReprendre($this->horloge->now(), 10));
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
    }

    private function chrono(): Chronometre
    {
        return new Chronometre(new DepotEtapes($this->jeu->base), new FileTraitement($this->jeu->base), $this->horloge, new NullLogger());
    }

    private function traiteurChronometre(Chronometre $chrono, ?Transcripteur $t = null): Traiteur
    {
        return new Traiteur($this->jeu->base, new FileTraitement($this->jeu->base), $t ?? new TranscripteurSansFichier(), new ExtracteurFactice(),
            new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], $this->horloge), new DepotArtisans($this->jeu->base),
            new StockageAudio($this->dossiers->env()['AUDIO_DOSSIER']), $this->horloge, new NullLogger(), 'https://exemple.test',
            chrono: $chrono);
    }

    /** @return list<string> étape, issue, tentative et processus de chaque ligne du chronomètre, dans l'ordre d'écriture */
    private function etapesChrono(): array
    {
        $r = BaseDeTest::pdo()->query("SELECT CONCAT(d.etape, ' ', d.issue, ' ', d.tentative, ' ', d.processus) FROM dictee_etape d
            JOIN enregistrement e ON e.id = d.enregistrement_id WHERE e.uuid = '" . self::UUID . "' ORDER BY d.id");
        self::assertNotFalse($r);
        /** @var list<string> */
        return $r->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Sous-chantier 31 : chaque étape d'un traitement complet est écrite, avec son processus et sa tentative. */
    public function testLesEtapesDUnTraitementCompletSontChronometrees(): void
    {
        self::assertSame(Statut::PRET, $this->traiteurChronometre($this->chrono())->avancer(self::UUID, 0, Chronometre::APRES_REPONSE));
        self::assertSame([
            'attente ok 1 apres_reponse',
            'mesure ok 1 apres_reponse',
            'transcription ok 1 apres_reponse',
            'ecriture ok 1 apres_reponse',
        ], $this->etapesChrono());
    }

    /** Une transcription qui n'est pas prête au premier passage se ferme au passage du cron qui reçoit le texte. */
    public function testLaTranscriptionSeFermeAuPassageQuiRecoitLeTexte(): void
    {
        $lent = new TranscripteurLent();
        $chrono = $this->chrono();
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteurChronometre($chrono, $lent)->avancer(self::UUID, 0, Chronometre::APRES_REPONSE));
        $this->horloge->sleep(42);
        $lent->pret = true;
        self::assertSame(Statut::PRET, $this->traiteurChronometre($chrono, $lent)->avancer(self::UUID));
        self::assertSame(['attente ok 1 apres_reponse', 'mesure ok 1 apres_reponse', 'transcription ok 1 apres_reponse', 'ecriture ok 1 cron'],
            $this->etapesChrono());
        self::assertSame(42000, BaseDeTest::ligne("SELECT duree_ms FROM dictee_etape WHERE etape = 'transcription'")['duree_ms']);
    }

    /**
     * D4 et D5 de Xavier (2026-11-24) : un traitement mort ne bloque plus la dictée 15 minutes. Son verrou expire au bout
     * du bail de 150 s ; le passage suivant marque l'étape abandonnée et écrit la reprise.
     */
    public function testUnTraitementMortEstReprisApresLeBailDe150Secondes(): void
    {
        $file = new FileTraitement($this->jeu->base);
        $mort = $this->chrono();
        $jeton = str_repeat('d', 32);
        self::assertTrue($file->prendreVerrou(self::UUID, $jeton, $this->horloge->now()->modify('+' . Traiteur::DUREE_VERROU_SECONDES . ' seconds'), $this->horloge->now()));
        $id = (int) BaseDeTest::ligne("SELECT id FROM enregistrement WHERE uuid = '" . self::UUID . "'")['id'];
        $mort->ouvrirPassage($id, self::UUID, $jeton, 1, Chronometre::APRES_REPONSE);
        $mort->commencer('mesure');
        // Le processus meurt ici : ni fin d'étape, ni verrou rendu.

        $this->horloge->sleep(149);
        self::assertSame(Statut::RECU, $this->traiteurChronometre($this->chrono())->avancer(self::UUID), 'verrou encore valable');
        $this->horloge->sleep(2);
        self::assertSame(Statut::PRET, $this->traiteurChronometre($this->chrono())->avancer(self::UUID));

        self::assertSame([
            'attente ok 1 apres_reponse',
            'mesure interrompue 1 apres_reponse',
            'reprise ok 2 cron',
            'mesure ok 2 cron',
            'transcription ok 2 cron',
            'ecriture ok 2 cron',
        ], $this->etapesChrono());
        self::assertSame(151000, BaseDeTest::ligne("SELECT duree_ms FROM dictee_etape WHERE etape = 'reprise'")['duree_ms']);
    }

    /** Revue E1 : une erreur levée après une étape « ok » (réponse coupée) laisse une ligne « erreur » ; l'attente suit. */
    public function testUneErreurHorsEtapeLaisseSaTraceEtLAttenteSuit(): void
    {
        $chrono = $this->chrono();
        $coupee = new class implements Extracteur {
            public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction
            {
                throw new \RuntimeException('Extraction : réponse coupée par la limite de jetons de sortie.');
            }
        };
        $t = new Traiteur($this->jeu->base, new FileTraitement($this->jeu->base), new TranscripteurSansFichier(), $coupee,
            new MessagerieFichier($this->dossiers->env()['COURRIELS_DOSSIER'], $this->horloge), new DepotArtisans($this->jeu->base),
            new StockageAudio($this->dossiers->env()['AUDIO_DOSSIER']), $this->horloge, new NullLogger(), 'https://exemple.test', chrono: $chrono);
        self::assertSame(Statut::TRANSCRIT, $t->avancer(self::UUID, 0, Chronometre::APRES_REPONSE));
        $this->horloge->sleep(60);
        self::assertSame(Statut::PRET, $this->traiteurChronometre($chrono)->avancer(self::UUID));
        self::assertSame([
            'attente ok 1 apres_reponse', 'mesure ok 1 apres_reponse', 'transcription ok 1 apres_reponse', 'erreur erreur 1 apres_reponse',
            'attente ok 2 cron', 'ecriture ok 2 cron',
        ], $this->etapesChrono());
        self::assertSame(60000, BaseDeTest::ligne("SELECT duree_ms FROM dictee_etape WHERE etape = 'attente' AND tentative = 2")['duree_ms']);
    }

    /**
     * Revue T2 : un traitement qui a perdu son verrou n'écrit pas l'échec et n'envoie pas le courriel « dictée en échec »
     * à la place de celui qui tient la dictée.
     */
    public function testUnEchecApresVerrouPerduNEnvoiePasDeCourriel(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0, courriel_dictee_echec = 1 WHERE id = {$this->jeu->a}");
        $voleurQuiEchoue = new class (self::UUID) implements Transcripteur {
            public function __construct(private readonly string $uuid)
            {
            }

            public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
            {
                // Un autre traitement a repris la dictée et l'a passée en échec ; celui-ci tombe ensuite en panne.
                BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = '" . str_repeat('z', 32) . "', statut = 'echec' WHERE uuid = '{$this->uuid}'");
                throw new \RuntimeException('502 Bad Gateway');
            }

            public function resultat(string $reference): ?string
            {
                return null;
            }
        };
        self::assertSame(Statut::ECHEC, $this->traiteur($voleurQuiEchoue)->avancer(self::UUID));
        self::assertSame(0, $this->etat()['tentatives'], 'aucune tentative notée par celui qui a perdu le verrou');
        self::assertSame([], $this->dossiers->courriels());
    }

    public function testVerrouVoleEnCoursDeRouteNEcritRienEtNeCompteRien(): void
    {
        $voleur = new TranscripteurVoleur(self::UUID);
        self::assertSame(Statut::RECU, $this->traiteur($voleur)->avancer(self::UUID));
        $e = $this->etat();
        self::assertSame([Statut::RECU, 0], [$e['statut'], $e['tentatives']]);
        // Le verrou de l'autre traitement est intact.
        self::assertSame(TranscripteurVoleur::JETON, $e['verrou_jeton']);
    }

    public function testCinqEchecsDonnentEchec(): void
    {
        $toujours = new ExtracteurCapricieux(PHP_INT_MAX);
        $statuts = [];
        for ($i = 0; $i < 5; $i++) {
            $statuts[] = $this->traiteur(x: $toujours)->avancer(self::UUID);
        }
        self::assertSame([Statut::TRANSCRIT, Statut::TRANSCRIT, Statut::TRANSCRIT, Statut::TRANSCRIT, Statut::ECHEC], $statuts);
        self::assertSame(5, $this->etat()['tentatives']);
        // En échec, ni cron ni un nouvel appel ne le reprennent.
        self::assertNotContains(self::UUID, (new FileTraitement($this->jeu->base))->aReprendre($this->horloge->now(), 10));
        self::assertSame(Statut::ECHEC, $this->traiteur()->avancer(self::UUID));
        self::assertSame(5, $this->etat()['tentatives']);
    }

    public function testTranscriptionPasEncorePreteRendLaMain(): void
    {
        $lent = new TranscripteurLent();
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur($lent)->avancer(self::UUID));
        self::assertNull($this->etat()['verrou_jeton']);
        self::assertSame(0, $this->etat()['tentatives']);
        $lent->pret = true;
        self::assertSame(Statut::PRET, $this->traiteur($lent)->avancer(self::UUID));
        self::assertSame(1, $lent->demarrages);
    }

    public function testLotDeTranscriptionPerduRenvoieLAudio(): void
    {
        $perdu = new TranscripteurPerdu();
        self::assertSame(Statut::RECU, $this->traiteur($perdu)->avancer(self::UUID));
        $e = BaseDeTest::ligne("SELECT statut, tentatives, transcription_ref, transcription_depuis, verrou_jeton FROM enregistrement WHERE uuid = '" . self::UUID . "'");
        self::assertSame([Statut::RECU, 1, null, null, null], [$e['statut'], $e['tentatives'], $e['transcription_ref'], $e['transcription_depuis'], $e['verrou_jeton']]);
        // Au passage suivant, l'audio repart et la transcription aboutit.
        self::assertSame(Statut::PRET, $this->traiteur($perdu)->avancer(self::UUID));
        self::assertSame(2, $perdu->demarrages);
    }

    public function testTranscriptionSansResultatApresDeuxHeuresRenvoieLAudio(): void
    {
        $lent = new TranscripteurLent();
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur($lent)->avancer(self::UUID));
        self::assertSame('2026-11-07 10:00:00', $this->etatDepuis());
        $this->horloge->modify('+119 minutes');
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur($lent)->avancer(self::UUID));
        $this->horloge->modify('+2 minutes');
        self::assertSame(Statut::RECU, $this->traiteur($lent)->avancer(self::UUID));
        self::assertSame(1, $this->etat()['tentatives']);
    }

    public function testTraitementImmediatAttendLaTranscriptionPuisPasseLaMain(): void
    {
        // Prête au 4e appel : 3 attentes d'une seconde (3 s chacune jusqu'au 2026-11-25), sous les 90 s permises.
        $lent = new TranscripteurPretApres(4);
        self::assertSame(Statut::PRET, $this->traiteur($lent)->avancer(self::UUID, Traiteur::ATTENTE_IMMEDIATE_SECONDES));
        self::assertSame('2026-11-07 10:00:03', $this->horloge->now()->format('Y-m-d H:i:s'));
    }

    /** Après 30 s d'attente, la redemande passe à toutes les 1,5 s : 31 appels de 0 à 30 s, puis 31,5, 33… le 41e à 45 s. */
    public function testLaRedemandeRalentitApresTrenteSecondes(): void
    {
        $lent = new TranscripteurPretApres(41);
        self::assertSame(Statut::PRET, $this->traiteur($lent)->avancer(self::UUID, Traiteur::ATTENTE_IMMEDIATE_SECONDES));
        self::assertSame('2026-11-07 10:00:45', $this->horloge->now()->format('Y-m-d H:i:s'));
    }

    public function testTraitementImmediatRendLaMainACronApresQuatreVingtDixSecondes(): void
    {
        $jamais = new TranscripteurLent();
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur($jamais)->avancer(self::UUID, Traiteur::ATTENTE_IMMEDIATE_SECONDES));
        self::assertSame('2026-11-07 10:01:30', $this->horloge->now()->format('Y-m-d H:i:s'));
        self::assertNull($this->etat()['verrou_jeton']);
        self::assertSame(1, $jamais->demarrages);
    }

    public function testCronNAttendPas(): void
    {
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur(new TranscripteurLent())->avancer(self::UUID));
        self::assertSame('2026-11-07 10:00:00', $this->horloge->now()->format('Y-m-d H:i:s'));
    }

    public function testAucunCourrielQuandLArtisanAttendSonOffreALEcran(): void
    {
        // L'écran d'attente a interrogé le serveur il y a cinq secondes : l'application ouvrira l'offre elle-même.
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET suivi_le = '2026-11-07 09:59:55' WHERE uuid = '" . self::UUID . "'");
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertSame(1, $this->offresDeLEnregistrement());
        self::assertCount(0, $this->dossiers->courriels());
    }

    public function testUnCourrielQuandLEcranDAttenteNEstPlusLa(): void
    {
        // Téléphone en veille ou application fermée depuis cinq minutes : l'offre sort sans l'artisan.
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET suivi_le = '2026-11-07 09:55:00' WHERE uuid = '" . self::UUID . "'");
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertCount(1, $this->dossiers->courriels());
    }

    public function testAucunCourrielQuandLArtisanNEnVeutPas(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET courriel_offre_prete = 0 WHERE id = {$this->jeu->a}");
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertSame(1, $this->offresDeLEnregistrement());
        self::assertCount(0, $this->dossiers->courriels());
    }

    /**
     * Le contenu des courriels écrits, dans l'ordre.
     *
     * @return list<string>
     */
    private function courriels(): array
    {
        $fichiers = $this->dossiers->courriels();
        sort($fichiers);
        return array_map(static fn (string $f): string => (string) file_get_contents($f), $fichiers);
    }

    private function echouerCinqFois(): void
    {
        $toujours = new ExtracteurCapricieux(PHP_INT_MAX);
        for ($i = 0; $i < 5; $i++) {
            $this->traiteur(x: $toujours)->avancer(self::UUID);
        }
        self::assertSame(Statut::ECHEC, $this->etat()['statut']);
    }

    /** Xavier, 2026-11-24 : l'artisan qui a rangé son téléphone apprend que sa dictée ne deviendra pas une offre. */
    public function testUneDicteeEnEchecPrevientLArtisanUneFois(): void
    {
        $this->echouerCinqFois();
        $courriels = $this->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString("To: a@exemple.ch\r\n", $courriels[0]);
        self::assertStringContainsString("Votre dictée n'a pas pu être traitée", $courriels[0]);
        // La dictée se reconnaît à son heure d'arrivée, en heure de Zurich.
        self::assertStringContainsString("samedi 7 novembre à 11\u{00A0}h\u{00A0}00", $courriels[0]);
        self::assertStringContainsString('https://exemple.test/', $courriels[0]);
        // En échec, l'enregistrement n'est plus repris : aucun second courriel.
        $this->traiteur()->avancer(self::UUID);
        self::assertCount(1, $this->courriels());
    }

    public function testAucunCourrielDEchecPourUneDemoNiSansLeReglageNiDevantLEcran(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET courriel_dictee_echec = 0 WHERE id = {$this->jeu->a}");
        $this->echouerCinqFois();
        self::assertSame([], $this->courriels());

        BaseDeTest::pdo()->exec("UPDATE artisan SET courriel_dictee_echec = 1, demo = 1 WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'transcrit', tentatives = 4 WHERE uuid = '" . self::UUID . "'");
        $this->traiteur(x: new ExtracteurCapricieux(1))->avancer(self::UUID);
        self::assertSame(Statut::ECHEC, $this->etat()['statut']);
        self::assertSame([], $this->courriels());

        // L'écran d'attente montre l'échec lui-même.
        BaseDeTest::pdo()->exec("UPDATE artisan SET demo = 0 WHERE id = {$this->jeu->a}");
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'transcrit', tentatives = 4, suivi_le = '2026-11-07 09:59:55' WHERE uuid = '" . self::UUID . "'");
        $this->traiteur(x: new ExtracteurCapricieux(1))->avancer(self::UUID);
        self::assertSame(Statut::ECHEC, $this->etat()['statut']);
        self::assertSame([], $this->courriels());
    }

    public function testUnTraitementMortTropSouventPrevientAussi(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = 'mort', verrou_jusqu_a = '2026-11-07 09:00:00', tentatives = 4 WHERE uuid = '" . self::UUID . "'");
        self::assertSame(Statut::ECHEC, $this->traiteur()->avancer(self::UUID));
        $courriels = $this->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString("Votre dictée n'a pas pu être traitée", $courriels[0]);
    }

    private function etatDepuis(): ?string
    {
        $v = BaseDeTest::ligne("SELECT transcription_depuis FROM enregistrement WHERE uuid = '" . self::UUID . "'")['transcription_depuis'];
        return is_string($v) ? $v : null;
    }

    /** Une dictée qui nomme le client : « Madame Rossi », lieu « Delémont ». */
    private function dicteeAvecClient(string $nom): Extracteur
    {
        return new class ($nom) implements Extracteur {
            public function __construct(private string $nom)
            {
            }

            public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction
            {
                return new ResultatExtraction('Cuisine', [], $this->nom, 'Delémont');
            }
        };
    }

    /** @return array<string, mixed> */
    private function clientDeLOffre(): array
    {
        return BaseDeTest::ligne("SELECT o.client_id, o.client_dicte, o.lieu_dicte FROM offre o JOIN enregistrement e ON e.id = o.enregistrement_id WHERE e.uuid = '" . self::UUID . "'");
    }

    /**
     * Revue du 2026-11-18 (H1) : chez Infomaniak, le site web ne peut pas lancer ffprobe, la durée n'est pas lue à
     * l'import. Le traitement de fond la mesure avant tout appel payant ; trop long, échec définitif, sans transcription.
     */
    public function testUnEnregistrementTropLongEchoueAvantLaTranscription(): void
    {
        $dossier = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}";
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', "$dossier/" . self::UUID . '.m4a');
        $appele = new class implements Transcripteur {
            public bool $appele = false;

            public function demarrer(string $chemin, string $vocabulaire = ''): string
            {
                $this->appele = true;
                return 'lot';
            }

            public function resultat(string $reference): string
            {
                return 'texte';
            }
        };
        // 27,9 s de dictée pour 10 s permises (plus la tolérance de 5 s).
        self::assertSame(Statut::ECHEC, $this->traiteur(t: $appele, dureeMax: 10)->avancer(self::UUID));
        self::assertFalse($appele->appele);
        self::assertStringContainsString('trop long', (string) $this->etat()['erreur']);
        // Réessayer ne raccourcirait pas la dictée : le courriel dit d'en dicter une plus courte.
        $courriels = $this->courriels();
        self::assertCount(1, $courriels);
        self::assertStringContainsString('Votre dictée est trop longue', $courriels[0]);
        self::assertStringContainsString("plus de 1\u{00A0}minute, la limite", $courriels[0]);
    }

    public function testUnEnregistrementDansLaLimitePasse(): void
    {
        $dossier = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}";
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', "$dossier/" . self::UUID . '.m4a');
        self::assertSame(Statut::PRET, $this->traiteur(dureeMax: 1800)->avancer(self::UUID));
    }

    /**
     * Revue du 2026-11-18 (M8) : pendant l'attente de la transcription, une erreur passagère du service (réseau, 503)
     * comptait une tentative ; cinq en quinze secondes mettaient l'enregistrement en échec.
     */
    public function testUneErreurPassagerePendantLAttenteNeCompteRien(): void
    {
        $instable = new class implements Transcripteur {
            public int $appels = 0;

            public function demarrer(string $chemin, string $vocabulaire = ''): string
            {
                return 'lot-1';
            }

            public function resultat(string $reference): ?string
            {
                $this->appels++;
                throw new \RuntimeException('Résultat de la transcription : 503');
            }
        };
        $traiteur = $this->traiteur(t: $instable);
        for ($i = 0; $i < 6; $i++) {
            self::assertSame(Statut::TRANSCRIPTION, $traiteur->avancer(self::UUID));
        }
        self::assertSame(6, $instable->appels);
        self::assertSame(0, (int) $this->etat()['tentatives']);
    }

    /**
     * Revue du 2026-11-18 (M9) : un traitement tué en route (processus arrêté, serveur redémarré) laissait son verrou
     * expirer sans rien compter ; l'enregistrement était repris toutes les quinze minutes, sans fin, et repayé.
     */
    public function testUnTraitementMortEnRouteCompteUneTentative(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = 'mort', verrou_jusqu_a = '2026-11-07 09:00:00', tentatives = 1 WHERE uuid = '" . self::UUID . "'");
        self::assertSame(Statut::PRET, $this->traiteur()->avancer(self::UUID));
        self::assertSame(2, (int) $this->etat()['tentatives']);
    }

    public function testLeCinquiemeTraitementMortMetEnEchec(): void
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = 'mort', verrou_jusqu_a = '2026-11-07 09:00:00', tentatives = 4 WHERE uuid = '" . self::UUID . "'");
        self::assertSame(Statut::ECHEC, $this->traiteur()->avancer(self::UUID));
        self::assertSame(0, $this->offresDeLEnregistrement());
        self::assertNotNull($this->etat()['supprimer_apres']);
    }

    /** Revue du 2026-11-18 (A13) : les postes au-delà du plafond de l'offre ne disparaissent plus en silence. */
    public function testLesPostesCoupesSontNotesSurLOffre(): void
    {
        $coupe = new class implements Extracteur {
            public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction
            {
                return new ResultatExtraction('Immeuble', [new LigneExtraite('Poste 1', 1000, 'pce', 100, false, null)], null, null, 12);
            }
        };
        self::assertSame(Statut::PRET, $this->traiteur(x: $coupe)->avancer(self::UUID));
        $offre = BaseDeTest::ligne("SELECT o.postes_coupes FROM offre o JOIN enregistrement e ON e.id = o.enregistrement_id WHERE e.uuid = '" . self::UUID . "'");
        self::assertSame(12, (int) $offre['postes_coupes']);
    }

    /**
     * Activité du compte (2026-11-22) : le traitement la donne à l'extracteur, et le titre d'une offre sans titre dicté
     * la suit (« Visite du … » au Bâtiment, « Offre du … » pour une prestation).
     *
     * @return array{0: Activite, 1: string} l'activité reçue par l'extracteur, le titre de l'offre
     */
    private function traiterSansTitre(): array
    {
        $temoin = new class implements Extracteur {
            public ?Activite $recue = null;

            public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction
            {
                $this->recue = $activite;
                return new ResultatExtraction(null, [new LigneExtraite('Poste 1', 1000, 'pce', 100, false, null)]);
            }
        };
        self::assertSame(Statut::PRET, $this->traiteur(x: $temoin)->avancer(self::UUID));
        $titre = BaseDeTest::ligne("SELECT o.titre FROM offre o JOIN enregistrement e ON e.id = o.enregistrement_id WHERE e.uuid = '" . self::UUID . "'")['titre'];
        self::assertNotNull($temoin->recue);
        return [$temoin->recue, (string) $titre];
    }

    public function testLeTraitementDonneLeBatimentParDefaut(): void
    {
        self::assertSame([Activite::Batiment, 'Visite du 07.11.2026'], $this->traiterSansTitre());
    }

    public function testLeTraitementDonneLActiviteDuCompteALExtracteur(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET activite = 'prestations' WHERE id = {$this->jeu->a}");
        self::assertSame([Activite::Prestations, 'Offre du 07.11.2026'], $this->traiterSansTitre());
    }

    /** Règle de Xavier (2026-11-12) : à plus de 80 % de ressemblance, l'offre rejoint la fiche. */
    public function testLeClientDicteRejointSaFiche(): void
    {
        $fiche = static fn (int $artisan, string $nom): int => (new DepotClients(new Base(BaseDeTest::pdo()), new Portee($artisan)))
            ->creer(['nom' => $nom, 'adresse' => new Adresse(), 'telephone' => null, 'courriel' => null], new \DateTimeImmutable('2026-11-01'));
        $rossi = $fiche($this->jeu->a, 'Mme Rossi');
        // La même cliente chez B ne compte pas : chez A, la fiche est seule en tête.
        $fiche($this->jeu->b, 'Madame Rossi');
        self::assertSame(Statut::PRET, $this->traiteur(x: $this->dicteeAvecClient('Madame Rossi'))->avancer(self::UUID));
        self::assertSame(['client_id' => $rossi, 'client_dicte' => 'Madame Rossi', 'lieu_dicte' => 'Delémont'], $this->clientDeLOffre());
    }

    /** Règle de Xavier (2026-11-14) : même nom, autre localité que celle dictée (Delémont), pas de rattachement. */
    public function testUneFicheDUneAutreLocaliteNEstPasRattachee(): void
    {
        (new DepotClients(new Base(BaseDeTest::pdo()), new Portee($this->jeu->a)))
            ->creer(['nom' => 'Mme Rossi', 'adresse' => new Adresse('Rue du Marché', '1', '1630', 'Bulle'), 'telephone' => null, 'courriel' => null], new \DateTimeImmutable('2026-11-01'));
        self::assertSame(Statut::PRET, $this->traiteur(x: $this->dicteeAvecClient('Madame Rossi'))->avancer(self::UUID));
        self::assertSame(['client_id' => null, 'client_dicte' => 'Madame Rossi', 'lieu_dicte' => 'Delémont'], $this->clientDeLOffre());
    }

    /** Sinon le nom dicté reste une proposition : aucune fiche ne naît sans l'artisan. */
    public function testUnClientInconnuResteAConfirmer(): void
    {
        $avant = BaseDeTest::compter('client', '1 = 1');
        self::assertSame(Statut::PRET, $this->traiteur(x: $this->dicteeAvecClient('Famille Dupont'))->avancer(self::UUID));
        self::assertSame(['client_id' => null, 'client_dicte' => 'Famille Dupont', 'lieu_dicte' => 'Delémont'], $this->clientDeLOffre());
        self::assertSame($avant, BaseDeTest::compter('client', '1 = 1'));
    }

    public function testCourrielEnPanneNeDefaitPasLOffre(): void
    {
        $panne = new class implements Messagerie {
            public function envoyer(string $destinataire, string $sujet, string $texte): void
            {
                throw new \RuntimeException('Serveur de courriel injoignable');
            }
        };
        self::assertSame(Statut::PRET, $this->traiteur(m: $panne)->avancer(self::UUID));
        self::assertSame(0, $this->etat()['tentatives']);
        self::assertSame(1, $this->offresDeLEnregistrement());
    }

    /** Recette AB-1 (Xavier, 2026-11-24) : une dictée continuée « à la suite » arrive en parties, une seule offre. */
    private function enParties(string ...$suites): void
    {
        $chemins = array_map(fn (string $s): string => "{$this->jeu->a}/" . self::UUID . $s, $suites);
        BaseDeTest::pdo()->prepare('UPDATE enregistrement SET fichiers_suite = ? WHERE uuid = ?')
            ->execute([json_encode($chemins), self::UUID]);
    }

    public function testUneDicteeEnPartiesEstTranscritePartieParPartieEtLesTextesJoints(): void
    {
        $this->enParties('-2.webm', '-3.m4a');
        $parties = new TranscripteurParPartie();
        self::assertSame(Statut::PRET, $this->traiteur($parties)->avancer(self::UUID));
        self::assertSame([self::UUID . '.m4a', self::UUID . '-2.webm', self::UUID . '-3.m4a'], $parties->envoyes);
        $e = BaseDeTest::ligne("SELECT transcription, transcription_ref FROM enregistrement WHERE uuid = '" . self::UUID . "'");
        self::assertSame("Texte de 1.\nTexte de 2.\nTexte de 3.", $e['transcription']);
        self::assertSame('lot-1 lot-2 lot-3', $e['transcription_ref']);
        self::assertSame(1, $this->offresDeLEnregistrement());
    }

    /** Une partie pas encore transcrite : on attend, sans renvoyer les autres. */
    public function testUnePartiePasPreteFaitAttendreLeTout(): void
    {
        $this->enParties('-2.webm');
        $parties = new TranscripteurParPartie();
        $parties->enAttente = ['lot-2'];
        self::assertSame(Statut::TRANSCRIPTION, $this->traiteur($parties)->avancer(self::UUID));
        $parties->enAttente = [];
        self::assertSame(Statut::PRET, $this->traiteur($parties)->avancer(self::UUID));
        self::assertCount(2, $parties->envoyes);
        self::assertSame("Texte de 1.\nTexte de 2.", BaseDeTest::ligne("SELECT transcription FROM enregistrement WHERE uuid = '" . self::UUID . "'")['transcription']);
    }

    /** La limite des 30 minutes vaut pour la dictée entière : deux parties dans la limite, ensemble au-delà. */
    public function testLaLimiteDeDureeVautPourLaSommeDesParties(): void
    {
        $dossier = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}";
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        // 27,9 s chacune, 55,8 s ensemble, pour 40 s permises (plus la tolérance de 5 s).
        copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', "$dossier/" . self::UUID . '.m4a');
        copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', "$dossier/" . self::UUID . '-2.ogg');
        $this->enParties('-2.ogg');
        $parties = new TranscripteurParPartie();
        self::assertSame(Statut::ECHEC, $this->traiteur(t: $parties, dureeMax: 40)->avancer(self::UUID));
        self::assertSame([], $parties->envoyes);
        self::assertStringContainsString('trop long', (string) $this->etat()['erreur']);
    }

    /**
     * Revue du 2026-11-24 (sécurité, point 1) : une partie que ffprobe ne lit pas ne doit pas faire sauter le contrôle
     * pour les autres. Les parties lisibles se comptent quand même.
     */
    public function testUnePartieIllisibleNeFaitPasSauterLaLimite(): void
    {
        $dossier = $this->dossiers->env()['AUDIO_DOSSIER'] . "/{$this->jeu->a}";
        if (!is_dir($dossier)) {
            mkdir($dossier, 0700, true);
        }
        copy(dirname(__DIR__) . '/fixtures/audio/dictee-peintre.ogg', "$dossier/" . self::UUID . '.m4a');
        file_put_contents("$dossier/" . self::UUID . '-2.webm', "\x1A\x45\xDF\xA3 pas un audio lisible");
        $this->enParties('-2.webm');
        $parties = new TranscripteurParPartie();
        self::assertSame(Statut::ECHEC, $this->traiteur(t: $parties, dureeMax: 20)->avancer(self::UUID));
        self::assertSame([], $parties->envoyes);
    }

    /**
     * Revue du 2026-11-24 (points 2 des deux relecteurs) : chaque transcription lancée se paie. Une panne au lancement
     * de la partie 2 ne doit pas faire relancer la partie 1 au passage suivant.
     */
    public function testUnePanneAuMilieuNeRelancePasLesPartiesDejaParties(): void
    {
        $this->enParties('-2.webm', '-3.m4a');
        $parties = new TranscripteurParPartie();
        $parties->panneUneFois = self::UUID . '-2.webm';
        self::assertSame(Statut::RECU, $this->traiteur($parties)->avancer(self::UUID));
        self::assertSame('lot-1', BaseDeTest::ligne("SELECT transcription_ref FROM enregistrement WHERE uuid = '" . self::UUID . "'")['transcription_ref']);
        self::assertSame(Statut::PRET, $this->traiteur($parties)->avancer(self::UUID));
        self::assertSame([self::UUID . '.m4a', self::UUID . '-2.webm', self::UUID . '-3.m4a'], $parties->envoyes);
        self::assertSame("Texte de 1.\nTexte de 2.\nTexte de 3.", BaseDeTest::ligne("SELECT transcription FROM enregistrement WHERE uuid = '" . self::UUID . "'")['transcription']);
    }
}

/** Rend « Texte de N. » pour la N-ième partie envoyée ; les lots de $enAttente ne sont pas encore prêts. */
final class TranscripteurParPartie implements Transcripteur
{
    /** @var list<string> */
    public array $envoyes = [];
    /** @var list<string> */
    public array $enAttente = [];
    /** Le nom de la partie dont le premier lancement échoue (service en panne). */
    public ?string $panneUneFois = null;

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        if (basename($cheminAudio) === $this->panneUneFois) {
            $this->panneUneFois = null;
            throw new \RuntimeException('502 Bad Gateway');
        }
        $this->envoyes[] = basename($cheminAudio);
        return 'lot-' . count($this->envoyes);
    }

    public function resultat(string $reference): ?string
    {
        return in_array($reference, $this->enAttente, true) ? null : 'Texte de ' . substr($reference, 4) . '.';
    }
}

/** Garde le vocabulaire reçu à chaque envoi. */
final class TranscripteurEcoute implements Transcripteur
{
    /** @var list<string> */
    public array $vocabulaires = [];

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        $this->vocabulaires[] = $vocabulaire;
        return 'ref-ecoute';
    }

    public function resultat(string $reference): string
    {
        return TranscripteurFactice::TEXTE;
    }
}

/** Le factice vérifie que le fichier existe ; ici, l'audio n'est jamais écrit sur disque. */
final class TranscripteurSansFichier implements Transcripteur
{
    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        return 'ref-test';
    }

    public function resultat(string $reference): string
    {
        return TranscripteurFactice::TEXTE;
    }
}

final class TranscripteurLent implements Transcripteur
{
    public bool $pret = false;
    public int $demarrages = 0;

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        $this->demarrages++;
        return 'ref-lente';
    }

    public function resultat(string $reference): ?string
    {
        return $this->pret ? 'Texte enfin prêt.' : null;
    }
}

final class TranscripteurPretApres implements Transcripteur
{
    private int $appels = 0;

    public function __construct(private readonly int $appelsAvantPret)
    {
    }

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        return 'lot-patient';
    }

    public function resultat(string $reference): ?string
    {
        return ++$this->appels >= $this->appelsAvantPret ? 'Texte enfin là.' : null;
    }
}

/** Le premier lot échoue chez le prestataire, le second aboutit. */
final class TranscripteurPerdu implements Transcripteur
{
    public int $demarrages = 0;

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        $this->demarrages++;
        return 'lot-' . $this->demarrages;
    }

    public function resultat(string $reference): string
    {
        if ($reference === 'lot-1') {
            throw new \OffreGen\Audio\TranscriptionEchouee('Transcription failed chez le prestataire.');
        }
        return 'Texte du second envoi.';
    }
}

/** Pendant l'envoi, un autre traitement (verrou expiré) prend la main. */
final class TranscripteurVoleur implements Transcripteur
{
    public const JETON = 'cccccccccccccccccccccccccccccccc';

    public function __construct(private readonly string $uuid)
    {
    }

    public function demarrer(string $cheminAudio, string $vocabulaire = ''): string
    {
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET verrou_jeton = '" . self::JETON . "' WHERE uuid = '{$this->uuid}'");
        return 'ref-volee';
    }

    public function resultat(string $reference): ?string
    {
        return null;
    }
}

final class ExtracteurCapricieux implements Extracteur
{
    public function __construct(private int $echecsRestants)
    {
    }

    public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): \OffreGen\Offre\ResultatExtraction
    {
        if ($this->echecsRestants > 0) {
            $this->echecsRestants--;
            throw new \RuntimeException('Modèle injoignable');
        }
        return (new ExtracteurFactice())->extraire($transcription, $catalogue);
    }
}
