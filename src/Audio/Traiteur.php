<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Donnees\Base;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Client\Rapprochement;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\Statut;
use OffreGen\Donnees\VerrouPerdu;
use OffreGen\Offre\Extracteur;
use OffreGen\Offre\Messagerie;
use Symfony\Component\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Fait avancer un enregistrement d'étape en étape jusqu'à ce qu'il faille attendre (transcription
 * pas encore prête) ou qu'il soit fini. Appelé juste après l'import, puis par cron chaque minute :
 * l'un ou l'autre peut s'arrêter n'importe où, l'autre reprend à l'étape enregistrée.
 */
final class Traiteur
{
    public const TENTATIVES_MAX = 5;
    public const DUREE_VERROU_SECONDES = 900;
    public const CONSERVATION_AUDIO_JOURS = 30;
    /** Au-delà, le lot est tenu pour perdu et l'audio renvoyé. */
    public const ATTENTE_TRANSCRIPTION_MAX_HEURES = 2;
    /** Juste après l'import : une dictée courte est transcrite dans ce délai, l'offre suit sans attendre cron. */
    public const ATTENTE_IMMEDIATE_SECONDES = 90;
    public const INTERVALLE_ATTENTE_SECONDES = 3;

    public function __construct(
        private readonly Base $base,
        private readonly FileTraitement $file,
        private readonly Transcripteur $transcripteur,
        private readonly Extracteur $extracteur,
        private readonly Messagerie $messagerie,
        private readonly DepotArtisans $artisans,
        private readonly StockageAudio $stockage,
        private readonly ClockInterface $horloge,
        private readonly LoggerInterface $journal,
        private readonly string $adresseApplication,
    ) {
    }

    /**
     * @param int $attenteSecondes temps pendant lequel on redemande une transcription pas encore prête
     *                             (traitement juste après l'import) ; 0 pour cron, qui repasse chaque minute
     * @return string le statut atteint (celui d'avant si un autre traitement tient le verrou)
     */
    public function avancer(string $uuid, int $attenteSecondes = 0): string
    {
        $limite = $this->horloge->now()->modify("+$attenteSecondes seconds");
        while (true) {
            $statut = $this->avancerUneFois($uuid);
            if ($statut !== Statut::TRANSCRIPTION || $this->horloge->now() >= $limite) {
                return $statut;
            }
            $this->horloge->sleep(self::INTERVALLE_ATTENTE_SECONDES);
        }
    }

    private function avancerUneFois(string $uuid): string
    {
        $jeton = bin2hex(random_bytes(16));
        $maintenant = $this->horloge->now();
        if (!$this->file->prendreVerrou($uuid, $jeton, $maintenant->modify('+' . self::DUREE_VERROU_SECONDES . ' seconds'), $maintenant)) {
            return $this->file->lire($uuid)['statut'] ?? 'inconnu';
        }
        try {
            return $this->enchainer($uuid, $jeton);
        } catch (VerrouPerdu $e) {
            // Un autre traitement a repris la main : ce n'est pas un échec de cet enregistrement.
            $this->journal->warning("offreGen, $uuid : " . $e->getMessage());
            return $this->file->lire($uuid)['statut'] ?? 'inconnu';
        } catch (\Throwable $e) {
            if ($e instanceof TranscriptionEchouee) {
                $this->file->transcriptionAReprendre($uuid, $jeton);
            }
            $statut = $this->file->noterEchec($uuid, $jeton, $e->getMessage(), self::TENTATIVES_MAX,
                $this->horloge->now()->modify('+' . self::CONSERVATION_AUDIO_JOURS . ' days'));
            $this->journal->error("offreGen, traitement de $uuid ($statut) : " . $e->getMessage());
            return $statut;
        } finally {
            $this->file->rendreVerrou($uuid, $jeton);
        }
    }

    private function enchainer(string $uuid, string $jeton): string
    {
        while (true) {
            $e = $this->file->lire($uuid) ?? throw new \RuntimeException('Enregistrement disparu.');
            switch ($e['statut']) {
                case Statut::RECU:
                    if ($e['fichier'] === null) {
                        throw new \RuntimeException("Plus d'audio pour cet enregistrement.");
                    }
                    $reference = $this->transcripteur->demarrer($this->stockage->chemin($e['fichier']));
                    $this->file->transcriptionDemarree($uuid, $jeton, $reference, $this->horloge->now());
                    break;
                case Statut::TRANSCRIPTION:
                    $texte = $this->transcripteur->resultat((string) $e['transcription_ref']);
                    if ($texte === null) {
                        $depuis = $e['transcription_depuis'] === null ? null : Dates::depuisBase($e['transcription_depuis']);
                        if ($depuis !== null && $depuis->modify('+' . self::ATTENTE_TRANSCRIPTION_MAX_HEURES . ' hours') < $this->horloge->now()) {
                            throw new TranscriptionEchouee('Transcription sans résultat après ' . self::ATTENTE_TRANSCRIPTION_MAX_HEURES . ' heures.');
                        }
                        return Statut::TRANSCRIPTION;
                    }
                    $this->file->transcrit($uuid, $jeton, $texte);
                    break;
                case Statut::TRANSCRIT:
                    $offreId = $this->creerOffre($e, $jeton);
                    $this->prevenir($e['artisan_id'], $offreId);
                    return Statut::PRET;
                default:
                    return $e['statut'];
            }
        }
    }

    /** @param array{id: int, artisan_id: int, uuid: string, transcription: string|null} $e */
    private function creerOffre(array $e, string $jeton): int
    {
        $portee = new Portee($e['artisan_id']);
        // L'extraction (un appel de modèle, plusieurs secondes) se fait hors transaction.
        $resultat = $this->extracteur->extraire((string) $e['transcription'], (new DepotProduits($this->base, $portee))->tous());
        $lignes = $resultat->lignes;
        $offres = new DepotOffres($this->base, $portee);
        $maintenant = $this->horloge->now();
        $titre = $resultat->titre ?? 'Visite du ' . $maintenant->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y');
        // Plus de 80 % de ressemblance avec une fiche : l'offre la désigne. Sinon le nom dicté reste une
        // proposition, que l'artisan confirme ou remplace (règle de Xavier du 2026-11-12).
        $clientId = $resultat->client === null ? null : Rapprochement::meilleur($resultat->client, (new DepotClients($this->base, $portee))->pourRapprochement(), $resultat->lieu);

        return $this->base->transaction(function () use ($offres, $e, $jeton, $titre, $lignes, $maintenant, $resultat, $clientId): int {
            $offreId = $offres->idParEnregistrement($e['id'])
                ?? $offres->creerDepuisEnregistrement($e['id'], $titre, $lignes, $maintenant, $clientId, $resultat->client, $resultat->lieu);
            // Si le verrou est perdu ici, VerrouPerdu annule aussi l'offre : jamais d'offre sans statut prêt.
            $this->file->pret($e['uuid'], $jeton, $maintenant, $maintenant->modify('+' . self::CONSERVATION_AUDIO_JOURS . ' days'));
            return $offreId;
        });
    }

    /** Le courriel part après la validation : son échec ne défait pas une offre prête. */
    private function prevenir(int $artisanId, int $offreId): void
    {
        try {
            $artisan = $this->artisans->parId($artisanId);
            if ($artisan === null) {
                return;
            }
            $lien = rtrim($this->adresseApplication, '/') . '/offres/' . $offreId;
            $this->messagerie->envoyer(
                $artisan['courriel'],
                'Votre offre est prête',
                "Bonjour,\r\n\r\nL'offre préparée à partir de votre enregistrement est prête à relire :\r\n$lien\r\n\r\noffreGen",
            );
        } catch (\Throwable $e) {
            $this->journal->error("offreGen, courriel « offre prête » pour l'offre $offreId : " . $e->getMessage());
        }
    }
}
