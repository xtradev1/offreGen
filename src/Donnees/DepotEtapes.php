<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les étapes chronométrées des dictées (sous-chantier 31), écrites par le traitement de fond et par la réception.
 * Hors portée, comme FileTraitement : le traitement agit pour tous les artisans. Les étapes ne portent que des durées ;
 * seul finirRejeu écrit un texte, la transcription refaite par un rejeu. Un rejeu a ses propres étapes (`rejeu_id`) ;
 * la dictée elle-même a `rejeu_id` NULL.
 *
 * @phpstan-type Ouverte array{id: int, etape: string, debut: string}
 */
final class DepotEtapes
{
    public const EN_COURS = 'en_cours';
    public const OK = 'ok';
    public const ERREUR = 'erreur';
    public const INTERROMPUE = 'interrompue';

    public function __construct(private readonly Base $base)
    {
    }

    public function ouvrir(int $enregistrementId, ?int $rejeuId, int $tentative, string $etape, string $processus, \DateTimeImmutable $debut, ?string $modele): int
    {
        $this->base->ecrire(
            'INSERT INTO dictee_etape (enregistrement_id, rejeu_id, tentative, etape, processus, debut, issue, modele)
             VALUES (:e, :r, :t, :et, :p, :d, :i, :m)',
            ['e' => $enregistrementId, 'r' => $rejeuId, 't' => min(255, $tentative), 'et' => $etape, 'p' => $processus,
             'd' => self::versBase($debut), 'i' => self::EN_COURS, 'm' => $modele === null ? null : mb_substr($modele, 0, 100)],
        );
        return $this->base->dernierId();
    }

    /** Une étape déjà finie quand on l'écrit : attente, reprise, réception, envoi. */
    public function ajouter(int $enregistrementId, ?int $rejeuId, int $tentative, string $etape, string $processus, \DateTimeImmutable $debut, \DateTimeImmutable $fin): void
    {
        // Une seule requête : un passage qui lirait entre deux écritures prendrait l'étape pour abandonnée (revue M7).
        $this->base->ecrire(
            'INSERT INTO dictee_etape (enregistrement_id, rejeu_id, tentative, etape, processus, debut, fin, duree_ms, issue)
             VALUES (:e, :r, :t, :et, :p, :d, :f, :ms, :i)',
            ['e' => $enregistrementId, 'r' => $rejeuId, 't' => min(255, $tentative), 'et' => $etape, 'p' => $processus,
             'd' => self::versBase($debut), 'f' => self::versBase($fin), 'ms' => self::ms($debut, $fin), 'i' => self::OK],
        );
    }

    public function terminer(int $id, \DateTimeImmutable $debut, \DateTimeImmutable $fin, string $issue, ?string $erreur, ?string $modele,
        ?int $jetonsEnvoyes, ?int $jetonsRecus, ?int $premierOctetMs): void
    {
        $this->base->ecrire(
            'UPDATE dictee_etape SET fin = :f, duree_ms = :d, issue = :i, erreur = :er, modele = COALESCE(:m, modele),
                    jetons_envoyes = :je, jetons_recus = :jr, premier_octet_ms = :po
             WHERE id = :id AND issue = :encours',
            ['f' => self::versBase($fin), 'd' => self::ms($debut, $fin), 'i' => $issue,
             'er' => $erreur === null ? null : mb_substr($erreur, 0, 190), 'm' => $modele === null ? null : mb_substr($modele, 0, 100),
             'je' => $jetonsEnvoyes, 'jr' => $jetonsRecus, 'po' => $premierOctetMs, 'id' => $id, 'encours' => self::EN_COURS],
        );
    }

    /**
     * Les étapes laissées en cours par un passage mort, sauf l'attente de la transcription, qui dure d'un passage à
     * l'autre par nature (le lot travaille chez le prestataire).
     *
     * @return list<Ouverte>
     */
    public function abandonnees(int $enregistrementId, ?int $rejeuId): array
    {
        /** @var list<Ouverte> */
        return $this->base->lire(
            "SELECT id, etape, debut FROM dictee_etape
             WHERE enregistrement_id = :e AND rejeu_id <=> :r AND issue = :i AND etape <> 'transcription' ORDER BY debut, id",
            ['e' => $enregistrementId, 'r' => $rejeuId, 'i' => self::EN_COURS],
        );
    }

    /** @param list<int> $ids */
    public function interrompre(array $ids): void
    {
        foreach ($ids as $id) {
            $this->base->ecrire('UPDATE dictee_etape SET issue = :i WHERE id = :id AND issue = :encours',
                ['i' => self::INTERROMPUE, 'id' => $id, 'encours' => self::EN_COURS]);
        }
    }

    /** @return array{issue: string, fin: string|null}|null la dernière étape écrite de la dictée (ou du rejeu) */
    public function derniere(int $enregistrementId, ?int $rejeuId): ?array
    {
        /** @var array{issue: string, fin: string|null}|null */
        return $this->base->un(
            'SELECT issue, fin FROM dictee_etape WHERE enregistrement_id = :e AND rejeu_id <=> :r ORDER BY debut DESC, id DESC LIMIT 1',
            ['e' => $enregistrementId, 'r' => $rejeuId],
        );
    }

    /** @return Ouverte|null l'étape de ce nom encore en cours, quel que soit le passage qui l'a ouverte */
    public function ouverte(int $enregistrementId, ?int $rejeuId, string $etape): ?array
    {
        /** @var Ouverte|null */
        return $this->base->un(
            'SELECT id, etape, debut FROM dictee_etape WHERE enregistrement_id = :e AND rejeu_id <=> :r AND etape = :et AND issue = :i
             ORDER BY debut DESC, id DESC LIMIT 1',
            ['e' => $enregistrementId, 'r' => $rejeuId, 'et' => $etape, 'i' => self::EN_COURS],
        );
    }

    public function recuLe(int $enregistrementId): ?\DateTimeImmutable
    {
        $v = $this->base->valeur('SELECT recu_le FROM enregistrement WHERE id = :e', ['e' => $enregistrementId]);
        return is_string($v) ? Dates::depuisBase($v) : null;
    }

    /** Début de la première étape de ce nom de la dictée elle-même ; null si elle n'existe pas. */
    public function debutDe(int $enregistrementId, string $etape): ?\DateTimeImmutable
    {
        $v = $this->base->valeur(
            'SELECT debut FROM dictee_etape WHERE enregistrement_id = :e AND rejeu_id IS NULL AND etape = :et ORDER BY debut, id LIMIT 1',
            ['e' => $enregistrementId, 'et' => $etape],
        );
        return is_string($v) ? self::depuisBase($v) : null;
    }

    /** Vrai tant que la dictée n'a que sa réception (et l'envoi du téléphone) : le traitement n'a pas commencé. */
    public function seulementReception(int $enregistrementId): bool
    {
        return (int) $this->base->valeur(
            "SELECT COUNT(*) FROM dictee_etape WHERE enregistrement_id = :e AND rejeu_id IS NULL AND etape NOT IN ('reception', 'envoi')",
            ['e' => $enregistrementId],
        ) === 0;
    }

    public function finReception(int $enregistrementId): ?\DateTimeImmutable
    {
        $v = $this->base->valeur(
            "SELECT MAX(fin) FROM dictee_etape WHERE enregistrement_id = :e AND rejeu_id IS NULL AND etape = 'reception'",
            ['e' => $enregistrementId],
        );
        return is_string($v) ? self::depuisBase($v) : null;
    }

    /**
     * Un rejeu à exécuter (traitement de fond) : la dictée, son audio et son compte.
     *
     * @return array{id: int, enregistrement_id: int, artisan_id: int, fichier: string|null, fichiers_suite: string|null, statut: string}|null
     */
    public function rejeu(int $id): ?array
    {
        /** @var array{id: int, enregistrement_id: int, artisan_id: int, fichier: string|null, fichiers_suite: string|null, statut: string}|null */
        return $this->base->un(
            'SELECT r.id, r.enregistrement_id, e.artisan_id, e.fichier, e.fichiers_suite, r.statut
             FROM dictee_rejeu r JOIN enregistrement e ON e.id = r.enregistrement_id WHERE r.id = :id',
            ['id' => $id],
        );
    }

    public function finirRejeu(int $id, string $statut, \DateTimeImmutable $finiLe, ?string $transcription, ?string $titre, ?int $lignes, ?string $erreur): void
    {
        $this->base->ecrire(
            "UPDATE dictee_rejeu SET statut = :s, fini_le = :f, transcription = :t, titre = :ti, lignes = :l, erreur = :e
             WHERE id = :id AND statut = 'en_cours'",
            ['s' => $statut, 'f' => Dates::versBase($finiLe), 't' => $transcription, 'ti' => $titre === null ? null : mb_substr($titre, 0, 190),
             'l' => $lignes === null ? null : min(65535, $lignes), 'e' => $erreur === null ? null : mb_substr($erreur, 0, 190), 'id' => $id],
        );
    }

    public static function versBase(\DateTimeImmutable $d): string
    {
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    public static function depuisBase(string $d): \DateTimeImmutable
    {
        return new \DateTimeImmutable($d, new \DateTimeZone('UTC'));
    }

    public static function ms(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return max(0, (int) round(((float) $fin->format('U.u') - (float) $debut->format('U.u')) * 1000));
    }
}
