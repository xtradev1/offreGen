<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les comptes vus par l'équipe (administration, docs/plans/2026-11-19_admin-comptes). Hors portée d'artisan par
 * nature : ces lectures traversent les comptes. Elles ne servent que des routes réservées à l'équipe.
 *
 * @phpstan-type Membre array{id: int, courriel: string, entreprise: string, role: string, offres_max: int|null, artisans: int}
 * @phpstan-type Compte array{id: int, courriel: string, entreprise: string, demo: int, offres_max: int|null, offres_offertes: int, demo_fin: string|null, cree_par: int|null, cree_le: string, createur: string|null, dictees: int, derniere: string|null}
 * @phpstan-type Evenement array{quand: string, sorte: string, detail: string|null, precision: string|null, acteur: string|null}
 */
final class DepotEquipe
{
    private const MEMBRES = "SELECT a.id, a.courriel, a.entreprise, IF(a.administrateur = 1, 'administrateur', a.role) AS role, a.offres_max,
                (SELECT COUNT(*) FROM artisan f WHERE f.cree_par = a.id) AS artisans
         FROM artisan a
         WHERE (a.administrateur = 1 OR a.role IN ('moderateur', 'commercial'))";

    /** Les artisans : ni un administrateur, ni un membre de l'équipe ; et, pour un commercial, seulement les siens. */
    private const COMPTES = "SELECT a.id, a.courriel, a.entreprise, a.demo, a.offres_max, a.offres_offertes, a.demo_fin, a.cree_par, a.cree_le,
                c.entreprise AS createur,
                (SELECT COUNT(*) FROM enregistrement e WHERE e.artisan_id = a.id) AS dictees,
                (SELECT MAX(e.recu_le) FROM enregistrement e WHERE e.artisan_id = a.id) AS derniere
         FROM artisan a LEFT JOIN artisan c ON c.id = a.cree_par
         WHERE a.administrateur = 0 AND a.role IS NULL AND (:tout = 1 OR a.cree_par = :acteur)";

    public function __construct(private readonly Base $base)
    {
    }

    /**
     * @param string $type « demos », « comptes », ou tout autre mot pour tous
     * @return list<Compte> les plus récemment actifs d'abord, 200 au plus
     */
    public function artisans(PorteeEquipe $p, string $type, string $recherche): array
    {
        $sql = self::COMPTES;
        $params = self::portee($p);
        if ($type === 'demos' || $type === 'comptes') {
            $sql .= ' AND a.demo = ' . ($type === 'demos' ? '1' : '0');
        }
        if ($recherche !== '') {
            // Les jokers de LIKE se cherchent tels quels.
            $motif = '%' . addcslashes($recherche, '%_\\') . '%';
            $sql .= ' AND (a.entreprise LIKE :q1 OR a.courriel LIKE :q2)';
            $params += ['q1' => $motif, 'q2' => $motif];
        }
        /** @var list<Compte> */
        return $this->base->lire($sql . ' ORDER BY COALESCE(derniere, a.cree_le) DESC, a.id DESC LIMIT 200', $params);
    }

    /** @return Compte|null null si le compte n'existe pas, n'est pas un artisan, ou est hors de la portée */
    public function artisan(PorteeEquipe $p, int $id): ?array
    {
        /** @var Compte|null */
        return $this->base->un(self::COMPTES . ' AND a.id = :id', self::portee($p) + ['id' => $id]);
    }

    /**
     * Ajoute des offres au plafond d'un artisan de la portée. $limite : total d'offres offertes à ne pas dépasser sur
     * ce compte ; null, aucune limite. Un compte sans plafond n'en reçoit pas.
     *
     * @return bool false si rien n'a été écrit
     */
    public function ajouterOffres(PorteeEquipe $p, int $id, int $ajout, ?int $limite): bool
    {
        return $this->base->ecrire(
            'UPDATE artisan SET offres_max = offres_max + :n1, offres_offertes = offres_offertes + :n2
             WHERE id = :id AND offres_max IS NOT NULL AND administrateur = 0 AND role IS NULL
               AND (:tout = 1 OR cree_par = :acteur) AND (:sans = 1 OR offres_offertes + :n3 <= :limite)',
            self::portee($p) + ['n1' => $ajout, 'n2' => $ajout, 'n3' => $ajout, 'id' => $id, 'sans' => $limite === null ? 1 : 0, 'limite' => $limite ?? 0],
        ) === 1;
    }

    /**
     * Ce qui s'est passé sur un compte, sans aucun contenu d'offre : dictées reçues, offres prêtes, échecs, gestes
     * de l'équipe. À n'appeler qu'après artisan(), qui vérifie la portée.
     *
     * @return list<Evenement> les douze plus récents
     */
    public function evenements(int $artisanId): array
    {
        /** @var list<Evenement> */
        return $this->base->lire(
            "SELECT quand, sorte, detail, `precision`, acteur FROM (
                SELECT e.recu_le AS quand, IF(e.statut = 'echec', 'echec', 'recue') AS sorte, NULL AS detail, NULL AS `precision`, NULL AS acteur
                  FROM enregistrement e WHERE e.artisan_id = :a1
                UNION ALL
                SELECT e.pret_le, 'prete', NULL, NULL, NULL FROM enregistrement e WHERE e.artisan_id = :a2 AND e.pret_le IS NOT NULL
                UNION ALL
                SELECT j.quand, 'geste', j.geste, j.detail, m.entreprise FROM journal_admin j LEFT JOIN artisan m ON m.id = j.acteur_id WHERE j.artisan_id = :a3
             ) t ORDER BY quand DESC LIMIT 12",
            ['a1' => $artisanId, 'a2' => $artisanId, 'a3' => $artisanId],
        );
    }

    /** @return array{tout: int, acteur: int} */
    private static function portee(PorteeEquipe $p): array
    {
        return ['tout' => $p->toutVoir ? 1 : 0, 'acteur' => $p->acteurId];
    }

    /**
     * Les administrateurs d'abord, puis les autres par nom.
     *
     * @return list<Membre>
     */
    public function membres(): array
    {
        /** @var list<Membre> */
        return $this->base->lire(self::MEMBRES . ' ORDER BY a.administrateur DESC, a.entreprise, a.id');
    }

    /** @return Membre|null */
    public function membre(int $id): ?array
    {
        /** @var Membre|null */
        return $this->base->un(self::MEMBRES . ' AND a.id = :id', ['id' => $id]);
    }

    /** Donne un rôle à un compte ; le créateur n'est noté que s'il n'y en avait pas. */
    public function rattacher(int $id, string $role, int $createur): void
    {
        $this->base->ecrire('UPDATE artisan SET role = :r, cree_par = COALESCE(cree_par, :c) WHERE id = :id', ['r' => $role, 'c' => $createur, 'id' => $id]);
    }

    public function changerRole(int $id, ?string $role): void
    {
        $this->base->ecrire('UPDATE artisan SET role = :r WHERE id = :id', ['r' => $role, 'id' => $id]);
    }

    /**
     * Les artisans créés par l'un passent à l'autre (départ d'un commercial, Xavier, 2026-11-19).
     *
     * @return int le nombre d'artisans repris
     */
    public function reattribuer(int $ancienCreateur, int $nouveauCreateur): int
    {
        return $this->base->ecrire('UPDATE artisan SET cree_par = :n WHERE cree_par = :a', ['n' => $nouveauCreateur, 'a' => $ancienCreateur]);
    }
}
