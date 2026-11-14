<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Adresse\Adresse;
use OffreGen\Audio\StockageAudio;
use OffreGen\Audio\Traiteur;
use OffreGen\Audio\Transcripteur;
use OffreGen\Audio\TranscripteurFactice;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Offre\Extracteur;
use OffreGen\Offre\ExtracteurApi;
use OffreGen\Offre\ExtracteurFactice;
use OffreGen\Offre\Messagerie;
use OffreGen\Offre\MessagerieFichier;
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

    private function traiteur(?Transcripteur $t = null, ?Extracteur $x = null, ?Messagerie $m = null): Traiteur
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
    }

    public function testUneDicteeSansParoleDonneUneOffreVideSansAppelerLeModele(): void
    {
        // Règle de Xavier (2026-11-12) : s'il n'a rien dit, l'offre est vide, et elle compte quand même.
        $muet = new class implements Transcripteur {
            public function demarrer(string $cheminAudio): string
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
        // Prête au 4e appel : 3 attentes de 3 s, sous les 90 s permises.
        $lent = new TranscripteurPretApres(4);
        self::assertSame(Statut::PRET, $this->traiteur($lent)->avancer(self::UUID, Traiteur::ATTENTE_IMMEDIATE_SECONDES));
        self::assertSame('2026-11-07 10:00:09', $this->horloge->now()->format('Y-m-d H:i:s'));
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

            public function extraire(string $transcription, array $catalogue): ResultatExtraction
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
}

/** Le factice vérifie que le fichier existe ; ici, l'audio n'est jamais écrit sur disque. */
final class TranscripteurSansFichier implements Transcripteur
{
    public function demarrer(string $cheminAudio): string
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

    public function demarrer(string $cheminAudio): string
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

    public function demarrer(string $cheminAudio): string
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

    public function demarrer(string $cheminAudio): string
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

    public function demarrer(string $cheminAudio): string
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

    public function extraire(string $transcription, array $catalogue): \OffreGen\Offre\ResultatExtraction
    {
        if ($this->echecsRestants > 0) {
            $this->echecsRestants--;
            throw new \RuntimeException('Modèle injoignable');
        }
        return (new ExtracteurFactice())->extraire($transcription, $catalogue);
    }
}
