<?php

declare(strict_types=1);

namespace OffreGen\Audio;

use OffreGen\Compte\Activite;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotArtisans;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\FileTraitement;
use OffreGen\Donnees\Portee;
use OffreGen\Client\Rapprochement;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\Statut;
use OffreGen\Donnees\VerrouPerdu;
use OffreGen\Offre\Extracteur;
use OffreGen\Systeme\Chronometre;
use OffreGen\Systeme\Courriel;
use OffreGen\Systeme\Messagerie;
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
    /**
     * Bail du verrou, prolongé par le pouls du chronomètre tant que le traitement vit (D4 et D5 de Xavier, 2026-11-24).
     * Il était de 900 s : un traitement mort bloquait la dictée 15 minutes (offres 70 et 87 du test).
     */
    public const DUREE_VERROU_SECONDES = Chronometre::BAIL_SECONDES;
    public const CONSERVATION_AUDIO_JOURS = 30;
    /** Au-delà, le lot est tenu pour perdu et l'audio renvoyé. */
    public const ATTENTE_TRANSCRIPTION_MAX_HEURES = 2;
    /** Juste après l'import : une dictée courte est transcrite dans ce délai, l'offre suit sans attendre cron. */
    public const ATTENTE_IMMEDIATE_SECONDES = 90;
    /**
     * Rythme des redemandes du texte (Xavier, 2026-11-25) : chaque seconde pendant les ATTENTE_RAPIDE_SECONDES premières
     * secondes, puis toutes les INTERVALLE_LENT_SECONDES. Le texte arrive en général en moins de 10 s ; une attente qui
     * traîne n'envoie pas une requête par seconde chez Infomaniak (limite générale de leur API : 60 par minute et par
     * clé, non confirmée pour la transcription, aucun en-tête de limite relevé le 2026-11-25).
     */
    public const INTERVALLE_ATTENTE_SECONDES = 1;
    public const INTERVALLE_LENT_SECONDES = 3;
    public const ATTENTE_RAPIDE_SECONDES = 30;

    /** Un passage de l'écran d'attente plus récent que cela : l'artisan est devant son offre. */
    private const SUIVI_RECENT_SECONDES = 20;

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
        /** Mesure de la durée avant la transcription ; null : pas de mesure (tests sans fichier). */
        private readonly ?DureeAudio $duree = null,
        private readonly int $dureeMaxSecondes = 1800,
        private readonly string $nomApplication = 'Dicta',
        ?Chronometre $chrono = null,
    ) {
        $this->chrono = $chrono ?? Chronometre::inactif();
    }

    private readonly Chronometre $chrono;

    /**
     * @param int $attenteSecondes temps pendant lequel on redemande une transcription pas encore prête
     *                             (traitement juste après l'import) ; 0 pour cron, qui repasse chaque minute
     * @param string $processus qui traite, pour le chronomètre : Chronometre::APRES_REPONSE ou Chronometre::CRON
     * @return string le statut atteint (celui d'avant si un autre traitement tient le verrou)
     */
    public function avancer(string $uuid, int $attenteSecondes = 0, string $processus = Chronometre::CRON): string
    {
        $debut = $this->horloge->now();
        $limite = $debut->modify("+$attenteSecondes seconds");
        while (true) {
            $statut = $this->avancerUneFois($uuid, $processus);
            if ($statut !== Statut::TRANSCRIPTION || $this->horloge->now() >= $limite) {
                return $statut;
            }
            $this->horloge->sleep(self::intervalle($debut, $this->horloge->now()));
        }
    }

    /** Secondes avant la prochaine redemande du texte, selon le temps déjà attendu. */
    public static function intervalle(\DateTimeImmutable $depuis, \DateTimeImmutable $maintenant): int
    {
        return $maintenant->getTimestamp() - $depuis->getTimestamp() < self::ATTENTE_RAPIDE_SECONDES
            ? self::INTERVALLE_ATTENTE_SECONDES : self::INTERVALLE_LENT_SECONDES;
    }

    private function avancerUneFois(string $uuid, string $processus): string
    {
        $jeton = bin2hex(random_bytes(16));
        $maintenant = $this->horloge->now();
        // Un verrou expiré sans avoir été rendu : le traitement d'avant est mort en route (revue du 2026-11-18, M9).
        if ($this->file->noterAbandon($uuid, $maintenant, self::TENTATIVES_MAX, $maintenant->modify('+' . self::CONSERVATION_AUDIO_JOURS . ' days'))) {
            $this->prevenirEchec($uuid, false);
        }
        if (!$this->file->prendreVerrou($uuid, $jeton, $maintenant->modify('+' . self::DUREE_VERROU_SECONDES . ' seconds'), $maintenant)) {
            return $this->file->lire($uuid)['statut'] ?? 'inconnu';
        }
        try {
            $tenu = $this->file->lire($uuid);
            if ($tenu !== null) {
                $this->chrono->ouvrirPassage($tenu['id'], $uuid, $jeton, $tenu['tentatives'] + 1, $processus);
            }
            return $this->enchainer($uuid, $jeton);
        } catch (\Throwable $e) {
            // Une erreur hors de toute étape (réponse coupée, lot sans numéro) laisse quand même sa trace (revue E1).
            if ($this->chrono->echouerOuvertes($e) === 0 && !Chronometre::verrouPerdu($e)) {
                $this->chrono->noterErreur($e);
            }
            if (Chronometre::verrouPerdu($e)) {
                // Un autre traitement a repris la main : ce n'est pas un échec de cet enregistrement. Le pouls coupe l'appel
                // en cours, que le client HTTP enveloppe dans sa propre exception.
                $this->journal->warning("offreGen, $uuid : verrou perdu, un autre traitement a repris cet enregistrement.");
                return $this->file->lire($uuid)['statut'] ?? 'inconnu';
            }
            if ($e instanceof TranscriptionEchouee) {
                $this->file->transcriptionAReprendre($uuid, $jeton);
            }
            // Trop long : réessayer ne le raccourcira pas, l'échec est immédiat.
            $note = $this->file->noterEchec($uuid, $jeton, $e->getMessage(), $e instanceof AudioTropLong ? 1 : self::TENTATIVES_MAX,
                $this->horloge->now()->modify('+' . self::CONSERVATION_AUDIO_JOURS . ' days'));
            // Verrou repris entre-temps : l'échec n'est pas noté, et le courriel ne part pas d'ici (revue T2).
            $statut = $note ?? ($this->file->lire($uuid)['statut'] ?? 'inconnu');
            $this->journal->error("offreGen, traitement de $uuid ($statut) : " . $e->getMessage());
            if ($note === Statut::ECHEC) {
                $this->prevenirEchec($uuid, $e instanceof AudioTropLong);
            }
            return $statut;
        } finally {
            $this->chrono->fermerPassage();
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
                    // Une dictée continuée (recette AB-1, 2026-11-24) : une transcription par partie, références
                    // séparées d'un espace. Le site web ne peut pas lancer ffmpeg pour recoller les fichiers.
                    $chemins = array_map($this->stockage->chemin(...), [$e['fichier'], ...DepotEnregistrements::suites($e['fichiers_suite'])]);
                    $secondes = $this->chrono->mesurer('mesure', fn (): ?float => $this->verifierDuree($chemins));
                    if ($secondes !== null) {
                        $this->file->noterDuree($uuid, (int) round($secondes * 1000));
                    }
                    $vocabulaire = VocabulaireDictee::depuis((new DepotProduits($this->base, new Portee($e['artisan_id'])))->designationsParUsage());
                    // Chaque partie lancée se paie : sa référence est notée tout de suite, et un passage repris après une
                    // panne repart de la première partie qui manque (revue du 2026-11-24).
                    $references = $e['transcription_ref'] === null || $e['transcription_ref'] === '' ? [] : explode(' ', $e['transcription_ref']);
                    for ($i = count($references); $i < count($chemins); $i++) {
                        $references[] = $this->transcripteur->demarrer($chemins[$i], $vocabulaire);
                        if ($i + 1 < count($chemins)) {
                            $this->file->transcriptionEnCours($uuid, $jeton, implode(' ', $references));
                        }
                    }
                    $this->file->transcriptionDemarree($uuid, $jeton, implode(' ', $references), $this->horloge->now());
                    // Le lot travaille chez le prestataire : l'étape reste ouverte jusqu'au passage qui reçoit le texte.
                    $this->chrono->commencer('transcription');
                    break;
                case Statut::TRANSCRIPTION:
                    try {
                        $texte = $this->textes(explode(' ', (string) $e['transcription_ref']));
                    } catch (TranscriptionEchouee $perdu) {
                        $this->chrono->finirOuverte('transcription', $perdu);
                        throw $perdu;
                    } catch (\Throwable $passagere) {
                        if (Chronometre::verrouPerdu($passagere)) {
                            throw $passagere;
                        }
                        // Revue du 2026-11-18 (M8) : une erreur passagère pendant l'attente ne compte pas de tentative ;
                        // le délai de ATTENTE_TRANSCRIPTION_MAX_HEURES borne l'attente.
                        $this->journal->warning("offreGen, $uuid : résultat de la transcription pas encore lisible (" . $passagere::class . ').');
                        $texte = null;
                    }
                    if ($texte === null) {
                        $depuis = $e['transcription_depuis'] === null ? null : Dates::depuisBase($e['transcription_depuis']);
                        if ($depuis !== null && $depuis->modify('+' . self::ATTENTE_TRANSCRIPTION_MAX_HEURES . ' hours') < $this->horloge->now()) {
                            $trop = new TranscriptionEchouee('Transcription sans résultat après ' . self::ATTENTE_TRANSCRIPTION_MAX_HEURES . ' heures.');
                            $this->chrono->finirOuverte('transcription', $trop);
                            throw $trop;
                        }
                        return Statut::TRANSCRIPTION;
                    }
                    $this->chrono->finirOuverte('transcription');
                    $this->file->transcrit($uuid, $jeton, $texte);
                    break;
                case Statut::TRANSCRIT:
                    $offreId = $this->creerOffre($e, $jeton);
                    $this->prevenir($e['artisan_id'], $offreId, $uuid);
                    return Statut::PRET;
                default:
                    return $e['statut'];
            }
        }
    }

    /**
     * Le texte de toutes les parties, dans l'ordre, ou null tant qu'une seule n'est pas prête.
     *
     * @param list<string> $references
     */
    private function textes(array $references): ?string
    {
        $textes = [];
        foreach ($references as $reference) {
            $texte = $this->transcripteur->resultat($reference);
            if ($texte === null) {
                return null;
            }
            $textes[] = $texte;
        }
        return implode("\n", array_filter($textes, static fn (string $t): bool => $t !== ''));
    }

    /**
     * Revue du 2026-11-18 (H1) : le site web d'Infomaniak ne peut pas lancer ffprobe, la durée n'est donc pas lue à
     * l'import. Ici, en ligne de commande, elle l'est, avant tout appel payant. Une dictée en parties se mesure entière.
     *
     * @param list<string> $chemins
     * @return float|null la durée lue en secondes (parties lisibles additionnées) ; null sans mesure possible
     */
    private function verifierDuree(array $chemins): ?float
    {
        if ($this->duree === null) {
            return null;
        }
        $lue = false;
        $complete = true;
        // Une partie illisible n'efface pas les autres : les parties lisibles se comptent quand même (revue du 2026-11-24).
        $secondes = 0.0;
        foreach ($chemins as $chemin) {
            $partie = $this->duree->mesurer($chemin, $this->chrono->pouls(...));
            if ($partie === null) {
                $complete = false;
                $this->journal->warning('offreGen, traitement : durée illisible, transcription sans contrôle de durée de cette partie.');
                continue;
            }
            $secondes += $partie;
            $lue = true;
        }
        if ($secondes > $this->dureeMaxSecondes + DureeAudio::TOLERANCE_SECONDES) {
            throw new AudioTropLong(sprintf('Enregistrement trop long : %d min, %d min au maximum.', (int) ceil($secondes / 60), intdiv($this->dureeMaxSecondes, 60)));
        }
        // Une partie illisible : la durée lue est partielle, celle du téléphone reste (revue M8).
        return $lue && $complete ? $secondes : null;
    }

    /** @param array{id: int, artisan_id: int, uuid: string, transcription: string|null} $e */
    private function creerOffre(array $e, string $jeton): int
    {
        $portee = new Portee($e['artisan_id']);
        // L'activité du compte règle la consigne, le rangement des lignes et le titre d'une offre sans titre dicté.
        $activite = Activite::lire($this->artisans->parId($e['artisan_id'])['activite'] ?? null);
        // L'extraction (un appel de modèle, plusieurs secondes) se fait hors transaction.
        $resultat = $this->extracteur->extraire((string) $e['transcription'], (new DepotProduits($this->base, $portee))->tous(), $activite);
        $lignes = $resultat->lignes;
        $offres = new DepotOffres($this->base, $portee);
        // Rapprochement du client, puis transaction de l'offre : l'étape se ferme une fois l'offre validée.
        $ecriture = $this->chrono->commencer('ecriture');
        $maintenant = $this->horloge->now();
        $titre = $resultat->titre ?? $activite->mots()['titre_defaut'] . ' du ' . $maintenant->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y');
        // Plus de 80 % de ressemblance avec une fiche : l'offre la désigne. Sinon le nom dicté reste une
        // proposition, que l'artisan confirme ou remplace (règle de Xavier du 2026-11-12).
        $clientId = $resultat->client === null ? null : Rapprochement::meilleur($resultat->client, (new DepotClients($this->base, $portee))->pourRapprochement(), $resultat->lieu);

        try {
            $offreId = $this->base->transaction(function () use ($offres, $e, $jeton, $titre, $lignes, $maintenant, $resultat, $clientId): int {
                $offreId = $offres->idParEnregistrement($e['id'])
                    ?? $offres->creerDepuisEnregistrement($e['id'], $titre, $lignes, $maintenant, $clientId, $resultat->client, $resultat->lieu, $resultat->postesCoupes);
                // Si le verrou est perdu ici, VerrouPerdu annule aussi l'offre : jamais d'offre sans statut prêt.
                $this->file->pret($e['uuid'], $jeton, $maintenant, $maintenant->modify('+' . self::CONSERVATION_AUDIO_JOURS . ' days'));
                return $offreId;
            });
        } catch (\Throwable $x) {
            $ecriture->echouer($x);
            throw $x;
        }
        $ecriture->finir();
        return $offreId;
    }

    /** Le courriel part après la validation : son échec ne défait pas une offre prête. */
    private function prevenir(int $artisanId, int $offreId, string $uuid): void
    {
        try {
            $artisan = $this->artisans->parId($artisanId);
            // Un compte de démonstration a une adresse inventée : le courriel reviendrait en erreur.
            if ($artisan === null || $artisan['demo'] === 1) {
                return;
            }
            // L'artisan peut ne pas vouloir ce courriel (réglage « Courriels », Xavier, 2026-11-23).
            if ($artisan['courriel_offre_prete'] !== 1) {
                return;
            }
            // S'il attend à l'écran, l'application lui ouvre l'offre : un courriel de plus serait du bruit. Le courriel
            // part quand l'offre sort sans lui : application fermée, téléphone en veille, reprise par le cron.
            if ($this->devantLEcran($uuid)) {
                return;
            }
            $lien = rtrim($this->adresseApplication, '/') . '/offres/' . $offreId;
            Courriel::offrePrete($lien)->envoyer($this->messagerie, $artisan['courriel'], $this->nomApplication, $this->adresseApplication);
        } catch (\Throwable $e) {
            $this->journal->error("offreGen, courriel « offre prête » pour l'offre $offreId : " . $e->getMessage());
        }
    }

    /**
     * La dictée vient de passer en échec (Xavier, 2026-11-24) : même règle que « offre prête ». Rien pour une démo,
     * rien si l'artisan n'en veut pas, rien s'il est devant l'écran d'attente, qui lui montre l'échec. Le courriel ne
     * part qu'au passage en échec, une seule fois : un enregistrement en échec n'est plus repris.
     */
    private function prevenirEchec(string $uuid, bool $tropLong): void
    {
        try {
            $e = $this->file->lire($uuid);
            $artisan = $e === null ? null : $this->artisans->parId($e['artisan_id']);
            if ($artisan === null || $artisan['demo'] === 1 || $artisan['courriel_dictee_echec'] !== 1 || $this->devantLEcran($uuid)) {
                return;
            }
            Courriel::dicteeEchouee(
                $this->file->recuLe($uuid) ?? $this->horloge->now(),
                rtrim($this->adresseApplication, '/') . '/',
                $tropLong ? (int) ceil($this->dureeMaxSecondes / 60) : null,
            )->envoyer($this->messagerie, $artisan['courriel'], $this->nomApplication, $this->adresseApplication);
        } catch (\Throwable $x) {
            $this->journal->error("offreGen, courriel « dictée en échec » pour $uuid : " . $x->getMessage());
        }
    }

    private function devantLEcran(string $uuid): bool
    {
        $suivi = $this->file->suiviLe($uuid);
        return $suivi !== null && $suivi > $this->horloge->now()->modify('-' . self::SUIVI_RECENT_SECONDES . ' seconds');
    }
}
