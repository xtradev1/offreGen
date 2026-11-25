<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * La file des enregistrements à traiter, vue par le traitement de fond (après réponse, cron).
 * Seule lecture hors portée sur les enregistrements : le traitement agit pour tous les artisans.
 * Chaque écriture exige le jeton du verrou : un traiteur qui a perdu son verrou n'écrit plus rien.
 *
 * @phpstan-type ATraiter array{id: int, artisan_id: int, uuid: string, fichier: string|null, fichiers_suite: string|null, statut: string, transcription_ref: string|null, transcription_depuis: string|null, transcription: string|null, tentatives: int}
 */
final class FileTraitement
{
    public function __construct(private readonly Base $base)
    {
    }

    /** @return list<string> uuid des enregistrements ni prêts ni en échec, dont le verrou est libre ou expiré */
    public function aReprendre(\DateTimeImmutable $maintenant, int $limite): array
    {
        /** @var list<string> */
        return array_column($this->base->lire(
            'SELECT uuid FROM enregistrement
             WHERE statut NOT IN (:pret, :echec) AND (verrou_jusqu_a IS NULL OR verrou_jusqu_a < :m)
             ORDER BY recu_le, id LIMIT ' . max(1, $limite),
            ['pret' => Statut::PRET, 'echec' => Statut::ECHEC, 'm' => Dates::versBase($maintenant)],
        ), 'uuid');
    }

    /**
     * Les dictées ni prêtes ni en échec : combien en tout, combien reçues avant $avant, et la plus ancienne (VeilleDictees).
     *
     * @return array{en_cours: int, en_retard: int, plus_ancienne: string|null}
     */
    public function enAttente(\DateTimeImmutable $avant): array
    {
        $r = $this->base->lire(
            'SELECT COUNT(*) AS en_cours, COALESCE(SUM(recu_le < :avant), 0) AS en_retard, MIN(recu_le) AS plus_ancienne
             FROM enregistrement WHERE statut NOT IN (:pret, :echec)',
            ['avant' => Dates::versBase($avant), 'pret' => Statut::PRET, 'echec' => Statut::ECHEC],
        )[0] ?? [];
        return ['en_cours' => (int) ($r['en_cours'] ?? 0), 'en_retard' => (int) ($r['en_retard'] ?? 0),
            'plus_ancienne' => is_string($r['plus_ancienne'] ?? null) ? $r['plus_ancienne'] : null];
    }

    /** Prend le verrou si personne ne le tient ; une seule requête, donc un seul gagnant. */
    public function prendreVerrou(string $uuid, string $jeton, \DateTimeImmutable $jusqua, \DateTimeImmutable $maintenant): bool
    {
        return $this->base->ecrire(
            'UPDATE enregistrement SET verrou_jeton = :j, verrou_jusqu_a = :jusqua
             WHERE uuid = :u AND statut NOT IN (:pret, :echec) AND (verrou_jusqu_a IS NULL OR verrou_jusqu_a < :m)',
            ['j' => $jeton, 'jusqua' => Dates::versBase($jusqua), 'u' => $uuid, 'pret' => Statut::PRET,
             'echec' => Statut::ECHEC, 'm' => Dates::versBase($maintenant)],
        ) === 1;
    }

    /**
     * Verrou expiré sans avoir été rendu : le traitement qui le tenait est mort en route (processus tué, serveur
     * redémarré). Compte une tentative et libère le verrou ; à la dernière, l'enregistrement passe en échec. Sans
     * cela, il était repris toutes les quinze minutes, sans fin, et repayé (revue du 2026-11-18, M9).
     *
     * @return bool vrai si l'enregistrement vient de passer en échec (l'artisan est prévenu, une seule fois)
     */
    public function noterAbandon(string $uuid, \DateTimeImmutable $maintenant, int $tentativesMax, \DateTimeImmutable $supprimerApres): bool
    {
        $abandonne = $this->base->ecrire(
            'UPDATE enregistrement SET tentatives = tentatives + 1, verrou_jeton = NULL, verrou_jusqu_a = NULL,
                    erreur = :e
             WHERE uuid = :u AND verrou_jeton IS NOT NULL AND verrou_jusqu_a < :m AND statut NOT IN (:pret, :echec)',
            ['e' => 'Traitement interrompu en route, repris.', 'u' => $uuid, 'm' => Dates::versBase($maintenant),
             'pret' => Statut::PRET, 'echec' => Statut::ECHEC],
        );
        if ($abandonne !== 1) {
            return false;
        }
        return $this->base->ecrire(
            'UPDATE enregistrement SET statut = :echec, supprimer_apres = :sa, erreur = :e
             WHERE uuid = :u AND tentatives >= :max AND statut NOT IN (:pret, :echec2)',
            ['echec' => Statut::ECHEC, 'sa' => Dates::versBase($supprimerApres), 'e' => 'Traitement interrompu trop souvent.',
             'u' => $uuid, 'max' => $tentativesMax, 'pret' => Statut::PRET, 'echec2' => Statut::ECHEC],
        ) === 1;
    }

    /**
     * Le pouls du traitement (sous-chantier 31) : repousse la fin du verrou tant que celui qui le tient vit encore.
     * Faux si un autre traitement l'a repris. La nouvelle fin diffère toujours de l'ancienne (pouls espacés de 20 s au
     * moins) : MariaDB ne compte pas une ligne inchangée.
     */
    public function prolonger(string $uuid, string $jeton, \DateTimeImmutable $jusqua): bool
    {
        return $this->base->ecrire(
            'UPDATE enregistrement SET verrou_jusqu_a = :jusqua WHERE uuid = :u AND verrou_jeton = :j',
            ['jusqua' => Dates::versBase($jusqua), 'u' => $uuid, 'j' => $jeton],
        ) === 1;
    }

    /** Durée de l'audio mesurée au serveur (ffprobe), qui remplace celle donnée par le téléphone. */
    public function noterDuree(string $uuid, int $millisecondes): void
    {
        $this->base->ecrire('UPDATE enregistrement SET duree_ms = :d WHERE uuid = :u', ['d' => $millisecondes, 'u' => $uuid]);
    }

    public function rendreVerrou(string $uuid, string $jeton): void
    {
        $this->base->ecrire(
            'UPDATE enregistrement SET verrou_jeton = NULL, verrou_jusqu_a = NULL WHERE uuid = :u AND verrou_jeton = :j',
            ['u' => $uuid, 'j' => $jeton],
        );
    }

    /** @return ATraiter|null */
    public function lire(string $uuid): ?array
    {
        /** @var ATraiter|null */
        return $this->base->un(
            'SELECT id, artisan_id, uuid, fichier, fichiers_suite, statut, transcription_ref, transcription_depuis, transcription, tentatives FROM enregistrement WHERE uuid = :u',
            ['u' => $uuid],
        );
    }

    public function transcriptionDemarree(string $uuid, string $jeton, string $reference, \DateTimeImmutable $depuis): void
    {
        $this->exiger($this->base->ecrire(
            'UPDATE enregistrement SET statut = :s, transcription_ref = :r, transcription_depuis = :d WHERE uuid = :u AND verrou_jeton = :j',
            ['s' => Statut::TRANSCRIPTION, 'r' => $reference, 'd' => Dates::versBase($depuis), 'u' => $uuid, 'j' => $jeton],
        ));
    }

    /** Les références des parties déjà lancées d'une dictée continuée, notées avant de lancer la suivante. */
    public function transcriptionEnCours(string $uuid, string $jeton, string $references): void
    {
        $this->exiger($this->base->ecrire(
            'UPDATE enregistrement SET transcription_ref = :r WHERE uuid = :u AND verrou_jeton = :j AND statut = :s',
            ['r' => $references, 'u' => $uuid, 'j' => $jeton, 's' => Statut::RECU],
        ));
    }

    /** Le lot de transcription est perdu : l'enregistrement repart de « reçu » et l'audio sera renvoyé. */
    public function transcriptionAReprendre(string $uuid, string $jeton): void
    {
        $this->base->ecrire(
            'UPDATE enregistrement SET statut = :s, transcription_ref = NULL, transcription_depuis = NULL
             WHERE uuid = :u AND verrou_jeton = :j AND statut = :t',
            ['s' => Statut::RECU, 'u' => $uuid, 'j' => $jeton, 't' => Statut::TRANSCRIPTION],
        );
    }

    public function transcrit(string $uuid, string $jeton, string $texte): void
    {
        $this->exiger($this->base->ecrire(
            'UPDATE enregistrement SET statut = :s, transcription = :t WHERE uuid = :u AND verrou_jeton = :j',
            ['s' => Statut::TRANSCRIT, 't' => $texte, 'u' => $uuid, 'j' => $jeton],
        ));
    }

    /** À appeler dans la transaction qui crée l'offre : si le verrou est perdu, l'offre est annulée avec. */
    public function pret(string $uuid, string $jeton, \DateTimeImmutable $pretLe, \DateTimeImmutable $supprimerApres): void
    {
        $this->exiger($this->base->ecrire(
            'UPDATE enregistrement SET statut = :s, pret_le = :p, supprimer_apres = :sa, erreur = NULL WHERE uuid = :u AND verrou_jeton = :j',
            ['s' => Statut::PRET, 'p' => Dates::versBase($pretLe), 'sa' => Dates::versBase($supprimerApres), 'u' => $uuid, 'j' => $jeton],
        ));
    }

    /**
     * Les affectations lisent toutes « tentatives + 1 » avant que tentatives change : le résultat ne dépend
     * pas de l'ordre d'évaluation, qui varie selon le mode SQL.
     *
     * @param \DateTimeImmutable $supprimerApres date de suppression de l'audio si l'enregistrement passe en échec
     * @return string|null le statut après l'échec : inchangé, ou echec quand les tentatives sont épuisées ; null quand
     *                     le verrou n'est plus à ce traitement (rien n'est noté : revue T2 du sous-chantier 31)
     */
    public function noterEchec(string $uuid, string $jeton, string $message, int $tentativesMax, \DateTimeImmutable $supprimerApres): ?string
    {
        $touche = $this->base->ecrire(
            'UPDATE enregistrement
             SET statut = IF(tentatives + 1 >= :max, :echec, statut),
                 supprimer_apres = IF(tentatives + 1 >= :max2, :sa, supprimer_apres),
                 tentatives = tentatives + 1, erreur = :e
             WHERE uuid = :u AND verrou_jeton = :j',
            ['e' => mb_substr($message, 0, 1000), 'max' => $tentativesMax, 'max2' => $tentativesMax, 'echec' => Statut::ECHEC,
             'sa' => Dates::versBase($supprimerApres), 'u' => $uuid, 'j' => $jeton],
        );
        if ($touche !== 1) {
            return null;
        }
        return (string) $this->base->valeur('SELECT statut FROM enregistrement WHERE uuid = :u', ['u' => $uuid]);
    }

    /** Le moment où l'enregistrement est arrivé au serveur : c'est par lui que l'artisan reconnaît sa dictée. */
    public function recuLe(string $uuid): ?\DateTimeImmutable
    {
        $v = $this->base->valeur('SELECT recu_le FROM enregistrement WHERE uuid = :u', ['u' => $uuid]);
        return is_string($v) ? Dates::depuisBase($v) : null;
    }

    /** Le dernier passage de l'écran d'attente sur cet enregistrement ; null s'il n'y est jamais venu. */
    public function suiviLe(string $uuid): ?\DateTimeImmutable
    {
        $v = $this->base->valeur('SELECT suivi_le FROM enregistrement WHERE uuid = :u', ['u' => $uuid]);
        return is_string($v) ? Dates::depuisBase($v) : null;
    }

    /**
     * Les suites seules comptent aussi : l'ancienne version, après un retour arrière, ne vide que « fichier ».
     *
     * @return list<array{uuid: string, fichier: string|null, fichiers_suite: string|null}> audios dont la date de suppression est passée
     */
    public function aPurger(\DateTimeImmutable $maintenant): array
    {
        /** @var list<array{uuid: string, fichier: string|null, fichiers_suite: string|null}> */
        return $this->base->lire(
            'SELECT uuid, fichier, fichiers_suite FROM enregistrement
             WHERE (fichier IS NOT NULL OR fichiers_suite IS NOT NULL) AND supprimer_apres IS NOT NULL AND supprimer_apres < :m',
            ['m' => Dates::versBase($maintenant)],
        );
    }

    public function audioSupprime(string $uuid): void
    {
        $this->base->ecrire('UPDATE enregistrement SET fichier = NULL, fichiers_suite = NULL WHERE uuid = :u', ['u' => $uuid]);
    }

    private function exiger(int $lignesTouchees): void
    {
        if ($lignesTouchees !== 1) {
            throw new VerrouPerdu('Verrou perdu : un autre traitement a repris cet enregistrement.');
        }
    }
}
