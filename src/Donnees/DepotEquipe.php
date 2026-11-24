<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les comptes vus par l'équipe (administration, docs/plans/2026-11-19_admin-comptes). Hors portée d'artisan par
 * nature : ces lectures traversent les comptes. Elles ne servent que des routes réservées à l'équipe.
 *
 * @phpstan-type Membre array{id: int, courriel: string, entreprise: string, role: string, offres_max: int|null, acces_en_attente: int, artisans: int, invite_le: string|null}
 * @phpstan-type Compte array{id: int, courriel: string, entreprise: string, demo: int, offres_max: int|null, offres_offertes: int, demo_fin: string|null, cree_par: int|null, apporte_par: int|null, cree_le: string, createur: string|null, apporteur: string|null, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, dictees: int, derniere: string|null}
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
    private const COMPTES = "SELECT a.id, a.courriel, a.entreprise, a.demo, a.offres_max, a.offres_offertes, a.demo_fin, a.cree_par, a.apporte_par, a.cree_le, a.metier, a.metier_saisi, a.courriel_saisi,
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
        // Les démos d'entraînement des membres ne sont pas des clients (c.b3.note ; vu à la recette du 2026-11-23).
        $sql = self::COMPTES . ' AND a.entrainement = 0';
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

    /**
     * Le suivi (chantier onboarding, lot 7 ; O10 de Xavier) : les démos encore ouvertes dans la portée du membre, avec
     * leur activité. « ouverte_le » : le premier lien de la démo utilisé par le prospect. Les démos d'entraînement n'y
     * sont pas.
     *
     * @return list<array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, ouverte_le: string|null, demo_fin: string, offres_max: int|null, dictees: int, derniere_dictee: string|null}>
     */
    public function suivi(PorteeEquipe $p, \DateTimeImmutable $maintenant): array
    {
        /** @var list<array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, ouverte_le: string|null, demo_fin: string, offres_max: int|null, dictees: int, derniere_dictee: string|null}> */
        return $this->base->lire(
            "SELECT a.id, a.entreprise, a.metier, a.metier_saisi, a.courriel_saisi, a.demo_fin, a.offres_max,
                    (SELECT MIN(j.utilise_le) FROM jeton_acces j WHERE j.artisan_id = a.id) AS ouverte_le,
                    (SELECT COUNT(*) FROM enregistrement e WHERE e.artisan_id = a.id) AS dictees,
                    (SELECT MAX(e.recu_le) FROM enregistrement e WHERE e.artisan_id = a.id) AS derniere_dictee
             FROM artisan a
             WHERE a.administrateur = 0 AND a.role IS NULL AND (:tout = 1 OR a.cree_par = :acteur)
               AND a.demo = 1 AND a.entrainement = 0 AND a.demo_fin IS NOT NULL AND a.demo_fin > :maintenant
             ORDER BY a.demo_fin, a.id",
            self::portee($p) + ['maintenant' => Dates::versBase($maintenant)],
        );
    }

    /**
     * Les chiffres du suivi d'un membre (O10, O19) : les artisans qu'il a amenés (apporte_par, qui ne change jamais),
     * dont ceux à l'essai et les abonnés. « Abonné » : un compte sans plafond d'offres, jusqu'à la formule du lot B1
     * de l'administration.
     *
     * @return array{amenes: int, essai: int, abonnes: int}
     */
    public function chiffres(int $membreId): array
    {
        /** @var array{amenes: int|string|null, essai: int|string|null, abonnes: int|string|null} $l */
        $l = $this->base->un(
            "SELECT COUNT(*) AS amenes, SUM(demo = 0 AND offres_max IS NOT NULL) AS essai, SUM(demo = 0 AND offres_max IS NULL) AS abonnes
             FROM artisan WHERE apporte_par = :m AND entrainement = 0 AND administrateur = 0 AND role IS NULL",
            ['m' => $membreId],
        );
        return ['amenes' => (int) ($l['amenes'] ?? 0), 'essai' => (int) ($l['essai'] ?? 0), 'abonnes' => (int) ($l['abonnes'] ?? 0)];
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

    /**
     * Ce qui fait d'un compte de démonstration une démo de l'équipe : qui l'a créée et amenée, quand elle finit, et le
     * courriel du prospect s'il est déjà connu. L'assistant de bienvenue est noté comme passé : le prospect arrive
     * sur ses offres et peut dicter tout de suite (Xavier, 2026-11-20). Les réglages restent ouverts dans « Plus ».
     */
    public function poserDemo(int $id, int $acteur, \DateTimeImmutable $maintenant, \DateTimeImmutable $fin, ?string $courrielSaisi): void
    {
        $this->base->ecrire(
            'UPDATE artisan SET cree_par = :a1, apporte_par = :a2, demo_fin = :f, courriel_saisi = :c, reglages_faits_le = :q WHERE id = :id AND demo = 1',
            ['a1' => $acteur, 'a2' => $acteur, 'f' => Dates::versBase($fin), 'c' => $courrielSaisi, 'q' => Dates::versBase($maintenant), 'id' => $id],
        );
    }

    /** Une démo d'entraînement : hors du suivi et des chiffres, sans « amené par » (chantier onboarding, lot 8). */
    public function marquerEntrainement(int $id): void
    {
        $this->base->ecrire('UPDATE artisan SET entrainement = 1, apporte_par = NULL WHERE id = :id AND demo = 1', ['id' => $id]);
    }

    /** Les gestes d'un membre depuis une date (journal_admin) : la limite des démos d'entraînement. */
    public function gestes(int $acteur, string $geste, \DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            'SELECT COUNT(*) FROM journal_admin WHERE acteur_id = :a AND geste = :g AND quand > :d',
            ['a' => $acteur, 'g' => $geste, 'd' => Dates::versBase($depuis)],
        );
    }

    /** La bienvenue d'un membre : à montrer tant qu'elle n'a pas été vue (chantier onboarding, lot 8). */
    public function bienvenueAMontrer(int $membreId): bool
    {
        return $this->base->valeur('SELECT 1 FROM artisan WHERE id = :id AND guide_equipe_le IS NULL', ['id' => $membreId]) !== false;
    }

    public function bienvenueVue(int $membreId, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire('UPDATE artisan SET guide_equipe_le = COALESCE(guide_equipe_le, :q) WHERE id = :id', ['q' => Dates::versBase($quand), 'id' => $membreId]);
    }

    /** Le courriel tapé par le prospect d'une démo. Non confirmé : il ne devient pas l'adresse du compte. */
    public function saisirCourriel(int $id, string $courriel): void
    {
        $this->base->ecrire('UPDATE artisan SET courriel_saisi = :c WHERE id = :id AND demo = 1', ['c' => $courriel, 'id' => $id]);
    }

    /** Les démos créées par ce membre depuis une date, d'après le journal : une démo supprimée ou reprise compte encore. */
    public function demosCreees(int $acteur, \DateTimeImmutable $depuis): int
    {
        return (int) $this->base->valeur(
            "SELECT COUNT(*) FROM journal_admin WHERE acteur_id = :a AND geste = 'demo_creee' AND quand > :d",
            ['a' => $acteur, 'd' => Dates::versBase($depuis)],
        );
    }

    /**
     * Une démo, pour la page d'entrée du prospect : hors portée d'équipe, celui qui tient le lien y entre.
     *
     * @return array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, demo_fin: string|null, presente_par: string|null, cree_par: int|null, entrainement: int}|null
     */
    public function demo(int $id): ?array
    {
        /** @var array{id: int, entreprise: string, metier: string|null, metier_saisi: string|null, courriel_saisi: string|null, demo_fin: string|null, presente_par: string|null, cree_par: int|null, entrainement: int}|null */
        return $this->base->un('SELECT a.id, a.entreprise, a.metier, a.metier_saisi, a.courriel_saisi, a.demo_fin, COALESCE(c.nom_affiche, c.entreprise) AS presente_par, a.cree_par, a.entrainement
             FROM artisan a LEFT JOIN artisan c ON c.id = a.cree_par WHERE a.id = :id AND a.demo = 1', ['id' => $id]);
    }

    /**
     * Les démos de l'équipe dont la fin tombe dans la fenêtre des rappels (Xavier, 2026-11-24) et qui n'ont pas encore
     * reçu le dernier : ouvertes, avec un courriel tapé par le prospect, hors démos d'entraînement.
     *
     * @return list<array{id: int, demo_fin: string, courriel_saisi: string, demo_rappel_2j_le: string|null, presente_par: string|null}>
     */
    public function demosARappeler(\DateTimeImmutable $maintenant, \DateTimeImmutable $finAvant): array
    {
        /** @var list<array{id: int, demo_fin: string, courriel_saisi: string, demo_rappel_2j_le: string|null, presente_par: string|null}> */
        return $this->base->lire(
            'SELECT a.id, a.demo_fin, a.courriel_saisi, a.demo_rappel_2j_le, COALESCE(c.nom_affiche, c.entreprise) AS presente_par
             FROM artisan a LEFT JOIN artisan c ON c.id = a.cree_par
             WHERE a.demo = 1 AND a.entrainement = 0 AND a.courriel_saisi IS NOT NULL AND a.demo_rappel_12h_le IS NULL
               AND a.demo_fin > :m AND a.demo_fin <= :f
             ORDER BY a.demo_fin, a.id',
            ['m' => Dates::versBase($maintenant), 'f' => Dates::versBase($finAvant)],
        );
    }

    /**
     * Note le rappel avant de l'envoyer, en une requête : deux passages du cron en même temps n'en envoient qu'un. Le
     * dernier rappel note aussi le premier : une démo qui entre en retard dans la fenêtre ne reçoit que le dernier.
     *
     * @return bool vrai si ce passage a gagné le droit d'envoyer
     */
    public function noterRappelDemo(int $id, bool $dernier, \DateTimeImmutable $quand): bool
    {
        $q = Dates::versBase($quand);
        return $dernier
            ? $this->base->ecrire('UPDATE artisan SET demo_rappel_12h_le = :q, demo_rappel_2j_le = COALESCE(demo_rappel_2j_le, :q2) WHERE id = :id AND demo = 1 AND demo_rappel_12h_le IS NULL', ['q' => $q, 'q2' => $q, 'id' => $id]) === 1
            : $this->base->ecrire('UPDATE artisan SET demo_rappel_2j_le = :q WHERE id = :id AND demo = 1 AND demo_rappel_2j_le IS NULL', ['q' => $q, 'id' => $id]) === 1;
    }

    /** Le nom d'un membre de l'équipe dans les courriels, s'il en a choisi un. */
    public function nomAffiche(int $id): ?string
    {
        $nom = $this->base->valeur('SELECT nom_affiche FROM artisan WHERE id = :id', ['id' => $id]);
        return is_string($nom) ? $nom : null;
    }

    public function changerNomAffiche(int $id, ?string $nom): void
    {
        $this->base->ecrire('UPDATE artisan SET nom_affiche = :n WHERE id = :id', ['n' => $nom, 'id' => $id]);
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
