<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les dictées chronométrées, pour la page de l'administrateur (sous-chantier 31). Tous les comptes, jamais le texte
 * d'une dictée : des durées, des jetons, le navigateur. Exige une portée de l'équipe qui voit tout ; la route est en
 * plus réservée à l'administrateur.
 *
 * @phpstan-import-type Dictee from \OffreGen\Audio\RapportDictees
 * @phpstan-import-type Etape from \OffreGen\Audio\RapportDictees
 * @phpstan-type EtapeRapport array{id: int, rejeu_id: int|null, enregistrement_id: int, etape: string, tentative: int, processus: string, debut: string, fin: string|null, duree_ms: int|null, issue: string, erreur: string|null, modele: string|null, jetons_envoyes: int|null, jetons_recus: int|null, premier_octet_ms: int|null}
 */
final class DepotDictees
{
    private const COLONNES = 'e.id, e.uuid, e.artisan_id, e.recu_le, e.pret_le, e.statut, e.tentatives, e.taille, e.type_mime, e.navigateur, e.origine, e.duree_ms, o.id AS offre_id';

    public function __construct(private readonly Base $base, PorteeEquipe $portee)
    {
        if (!$portee->toutVoir) {
            throw new \LogicException('Les dictées de tous les comptes ne se lisent qu\'avec une portée qui voit tout.');
        }
    }

    /**
     * Les dictées reçues dans la période (bornes UTC, null : sans borne), des plus récentes aux plus anciennes.
     *
     * @return list<Dictee>
     */
    public function dictees(?\DateTimeImmutable $du, ?\DateTimeImmutable $au): array
    {
        [$ou, $p] = self::periode($du, $au);
        /** @var list<Dictee> */
        return $this->base->lire(
            'SELECT ' . self::COLONNES . ' FROM enregistrement e LEFT JOIN offre o ON o.enregistrement_id = e.id AND o.artisan_id = e.artisan_id
             WHERE ' . $ou . ' ORDER BY e.recu_le DESC, e.id DESC',
            $p,
        );
    }

    /**
     * Les étapes des dictées de la période, sans les rejeux.
     *
     * @return list<Etape>
     */
    public function etapes(?\DateTimeImmutable $du, ?\DateTimeImmutable $au): array
    {
        [$ou, $p] = self::periode($du, $au);
        /** @var list<Etape> */
        return $this->base->lire(
            'SELECT d.enregistrement_id, d.etape, d.tentative, d.processus, d.debut, d.fin, d.duree_ms, d.issue, d.erreur, d.modele,
                    d.jetons_envoyes, d.jetons_recus, d.premier_octet_ms
             FROM dictee_etape d JOIN enregistrement e ON e.id = d.enregistrement_id
             WHERE d.rejeu_id IS NULL AND ' . $ou . ' ORDER BY d.enregistrement_id, d.debut, d.id',
            $p,
        );
    }

    /** @return Dictee|null */
    public function dictee(int $id): ?array
    {
        /** @var Dictee|null */
        return $this->base->un(
            'SELECT ' . self::COLONNES . ' FROM enregistrement e LEFT JOIN offre o ON o.enregistrement_id = e.id AND o.artisan_id = e.artisan_id
             WHERE e.id = :id',
            ['id' => $id],
        );
    }

    /**
     * Toutes les étapes d'une dictée, rejeux compris, dans l'ordre où elles ont commencé.
     *
     * @return list<EtapeRapport>
     */
    public function etapesDe(int $id): array
    {
        /** @var list<EtapeRapport> */
        return $this->base->lire(
            'SELECT id, rejeu_id, enregistrement_id, etape, tentative, processus, debut, fin, duree_ms, issue, erreur, modele,
                    jetons_envoyes, jetons_recus, premier_octet_ms
             FROM dictee_etape WHERE enregistrement_id = :id ORDER BY debut, id',
            ['id' => $id],
        );
    }

    /**
     * Ce que la dictée a donné la première fois, pour le comparer à un rejeu : le texte transcrit, le titre et le nombre
     * de lignes de l'offre.
     *
     * @return array{transcription: string|null, titre: string|null, lignes: int}
     */
    public function original(int $id): array
    {
        /** @var array{transcription: string|null, titre: string|null, lignes: int} */
        return $this->base->un(
            'SELECT e.transcription, o.titre, (SELECT COUNT(*) FROM ligne l WHERE l.offre_id = o.id) AS lignes
             FROM enregistrement e LEFT JOIN offre o ON o.enregistrement_id = e.id AND o.artisan_id = e.artisan_id WHERE e.id = :id',
            ['id' => $id],
        ) ?? ['transcription' => null, 'titre' => null, 'lignes' => 0];
    }

    /** @return list<array{id: int, demande_le: string, fini_le: string|null, statut: string, transcription: string|null, titre: string|null, lignes: int|null, erreur: string|null}> */
    public function rejeux(int $id): array
    {
        /** @var list<array{id: int, demande_le: string, fini_le: string|null, statut: string, transcription: string|null, titre: string|null, lignes: int|null, erreur: string|null}> */
        return $this->base->lire(
            'SELECT id, demande_le, fini_le, statut, transcription, titre, lignes, erreur FROM dictee_rejeu WHERE enregistrement_id = :id ORDER BY demande_le DESC, id DESC',
            ['id' => $id],
        );
    }

    /** Un rejeu de cette dictée est-il en cours depuis cette date ? (Un rejeu mort plus ancien ne bloque plus.) */
    public function rejeuEnCours(int $id, \DateTimeImmutable $depuis): bool
    {
        return (int) $this->base->valeur(
            "SELECT COUNT(*) FROM dictee_rejeu WHERE enregistrement_id = :id AND statut = 'en_cours' AND demande_le >= :d",
            ['id' => $id, 'd' => Dates::versBase($depuis)],
        ) > 0;
    }

    public function creerRejeu(int $id, int $demandePar, \DateTimeImmutable $quand): int
    {
        $this->base->ecrire(
            "INSERT INTO dictee_rejeu (enregistrement_id, demande_par, demande_le, statut) VALUES (:id, :p, :q, 'en_cours')",
            ['id' => $id, 'p' => $demandePar, 'q' => Dates::versBase($quand)],
        );
        return $this->base->dernierId();
    }

    public function audioPresent(int $id): bool
    {
        return $this->base->valeur('SELECT fichier FROM enregistrement WHERE id = :id', ['id' => $id]) !== null;
    }

    /** @return array{string, array<string, string>} */
    private static function periode(?\DateTimeImmutable $du, ?\DateTimeImmutable $au): array
    {
        $ou = ['1 = 1'];
        $p = [];
        if ($du !== null) {
            $ou[] = 'e.recu_le >= :du';
            $p['du'] = Dates::versBase($du);
        }
        if ($au !== null) {
            $ou[] = 'e.recu_le < :au';
            $p['au'] = Dates::versBase($au);
        }
        return [implode(' AND ', $ou), $p];
    }
}
