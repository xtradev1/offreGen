<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les comptes vus par l'équipe (administration, docs/plans/2026-11-19_admin-comptes). Hors portée d'artisan par
 * nature : ces lectures traversent les comptes. Elles ne servent que des routes réservées à l'équipe.
 *
 * @phpstan-type Membre array{id: int, courriel: string, entreprise: string, role: string, offres_max: int|null, acces_en_attente: int, artisans: int, invite_le: string|null}
 * @phpstan-type Compte array{id: int, courriel: string, entreprise: string, demo: int, offres_max: int|null, offres_offertes: int, demo_fin: string|null, cree_par: int|null, apporte_par: int|null, cree_le: string, createur: string|null, apporteur: string|null, dictees: int, derniere: string|null}
 * @phpstan-type Evenement array{quand: string, sorte: string, detail: string|null, precision: string|null, acteur: string|null}
 */
final class DepotEquipe
{
    /** « artisans » : ceux que le membre a amenés ; l'équipe n'en fait pas partie. « invite_le » : son entrée dans l'équipe. */
    private const MEMBRES = "SELECT a.id, a.courriel, a.entreprise, IF(a.administrateur = 1, 'administrateur', a.role) AS role, a.offres_max, a.acces_en_attente,
                (SELECT COUNT(*) FROM artisan f WHERE f.apporte_par = a.id AND f.administrateur = 0 AND f.role IS NULL) AS artisans,
                (SELECT MAX(j.quand) FROM journal_admin j WHERE j.artisan_id = a.id AND j.geste IN ('membre_cree', 'membre_promu')) AS invite_le
         FROM artisan a
         WHERE (a.administrateur = 1 OR a.role IN ('moderateur', 'commercial'))";

    /**
     * Les artisans : ni un administrateur, ni un membre de l'équipe ; et, pour un commercial, seulement les siens.
     * « createur » suit l'artisan aujourd'hui (cree_par) ; « apporteur » l'a amené et ne change jamais (apporte_par).
     */
    private const COMPTES = "SELECT a.id, a.courriel, a.entreprise, a.demo, a.offres_max, a.offres_offertes, a.demo_fin, a.cree_par, a.apporte_par, a.cree_le,
                c.entreprise AS createur, ap.entreprise AS apporteur,
                (SELECT COUNT(*) FROM enregistrement e WHERE e.artisan_id = a.id) AS dictees,
                (SELECT MAX(e.recu_le) FROM enregistrement e WHERE e.artisan_id = a.id) AS derniere
         FROM artisan a LEFT JOIN artisan c ON c.id = a.cree_par LEFT JOIN artisan ap ON ap.id = a.apporte_par
         WHERE a.administrateur = 0 AND a.role IS NULL AND (:tout = 1 OR a.cree_par = :acteur)";

    public function __construct(private readonly Base $base)
    {
    }

    /**
     * @param string $type « demos », « essais », « comptes », ou tout autre mot pour tous
     * @param int|null $apporteur seulement les artisans amenés par ce compte ; null, tous
     * @return list<Compte> les plus récemment actifs d'abord, 200 au plus
     */
    public function artisans(PorteeEquipe $p, string $type, string $recherche, ?int $apporteur = null): array
    {
        $sql = self::COMPTES;
        $params = self::portee($p);
        if ($apporteur !== null) {
            $sql .= ' AND a.apporte_par = :par';
            $params += ['par' => $apporteur];
        }
        // Un essai est un compte avec un plafond d'offres (PlafondEssai) ; un compte n'en a pas.
        $sql .= match ($type) {
            'demos' => ' AND a.demo = 1',
            'essais' => ' AND a.demo = 0 AND a.offres_max IS NOT NULL',
            'comptes' => ' AND a.demo = 0 AND a.offres_max IS NULL',
            default => '',
        };
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
     * Les administrateurs d'abord, puis les commerciaux, puis les modérateurs, chacun par nom.
     *
     * @return list<Membre>
     */
    public function membres(): array
    {
        /** @var list<Membre> */
        return $this->base->lire(self::MEMBRES . " ORDER BY a.administrateur DESC, IF(a.administrateur = 1, '', a.role), a.entreprise, a.id");
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

    /** Un membre invité n'a pas encore choisi son mot de passe : le choisir remet la marque à zéro (DepotArtisans). */
    public function attendreAcces(int $id): void
    {
        $this->base->ecrire('UPDATE artisan SET acces_en_attente = 1 WHERE id = :id', ['id' => $id]);
    }

    /** Le nombre d'offres de l'espace artisan d'un membre (R4 : lui-même ne le change pas). */
    public function changerOffres(int $id, int $offres): void
    {
        $this->base->ecrire("UPDATE artisan SET offres_max = :n WHERE id = :id AND administrateur = 0 AND role IN ('moderateur', 'commercial')", ['n' => $offres, 'id' => $id]);
    }

    /** Le nom d'un compte, pour dire qui a amené des artisans, même s'il a quitté l'équipe. */
    public function nomDuCompte(int $id): ?string
    {
        $nom = $this->base->valeur('SELECT entreprise FROM artisan WHERE id = :id', ['id' => $id]);
        return is_string($nom) ? $nom : null;
    }

    /**
     * Les artisans suivis par l'un passent à l'autre (départ d'un commercial, Xavier, 2026-11-19). Celui qui les a
     * amenés (apporte_par) reste noté : une commission en dépend.
     *
     * @return int le nombre d'artisans repris
     */
    public function reattribuer(int $ancienCreateur, int $nouveauCreateur): int
    {
        return $this->base->ecrire('UPDATE artisan SET cree_par = :n WHERE cree_par = :a', ['n' => $nouveauCreateur, 'a' => $ancienCreateur]);
    }
}
