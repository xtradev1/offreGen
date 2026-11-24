<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

/**
 * Les prospects (docs/plans/2026-11-24_prospects) : le catalogue des artisans rencontrés, que l'équipe se partage.
 * Hors portée d'artisan par nature, comme DepotEquipe : ces lectures ne servent que des routes réservées à l'équipe.
 * Ce dépôt rend toujours la fiche entière ; c'est Compte\Prospects qui décide de ce qu'un lecteur en voit.
 *
 * Une fiche est « tenue » quand quelqu'un la suit et que son échéance n'est pas passée (ou qu'un compte lui est lié).
 * L'échéance : sept jours après le prochain pas, à défaut sept jours après la dernière action. Rien n'écrit la
 * libération : elle se calcule ici, à la lecture, et prendre() la constate.
 *
 * @phpstan-type Fiche array{id: int, entreprise: string, nom_cle: string, npa: string|null, localite: string, metier: string|null, metier_saisi: string|null, telephone: string|null, telephone_cle: string|null, courriel: string|null, contact: string|null, origine: string|null, ide: string|null, etat: string, suivi_par: int|null, suivi_depuis: string|null, suivi_avant: int|null, libre_depuis: string|null, pas_texte: string|null, pas_le: string|null, derniere_action_le: string, compte_id: int|null, cree_par: int|null, cree_le: string, suiveur: string|null, avant: string|null, createur: string|null, echeance: string, tenue: int, c_demo: int|null, c_demo_fin: string|null, c_offres_max: int|null, c_dictees: int, c_derniere: string|null}
 * @phpstan-type Champs array{entreprise: string, nom_cle: string, npa: string|null, localite: string, metier: string|null, metier_saisi: string|null, telephone: string|null, telephone_cle: string|null, courriel: string|null, contact: string|null, origine: string|null, ide: string|null}
 * @phpstan-type CompteProche array{id: int, entreprise: string, demo: int, offres_max: int|null, apporteur: string|null}
 */
final class DepotProspects
{
    public const JOURS_LIBRE = 7;

    /**
     * Le jour de la dernière action se lit deux heures après l'heure UTC de la base : le jour de Zurich, été comme
     * hiver, à une heure près en faveur de celui qui suit (revue du 2026-11-24, C4).
     */
    private const ECHEANCE = 'DATE_ADD(COALESCE(p.pas_le, DATE(DATE_ADD(p.derniere_action_le, INTERVAL 2 HOUR))), INTERVAL ' . self::JOURS_LIBRE . ' DAY)';

    /** Celui qui suit est encore dans l'équipe : les fiches d'un membre retiré sont libres (revue, R1). */
    private const DANS_L_EQUIPE = "p.suivi_par IN (SELECT m.id FROM artisan m WHERE m.administrateur = 1 OR m.role IN ('moderateur', 'commercial'))";

    private const FICHES = 'SELECT p.id, p.entreprise, p.nom_cle, p.npa, p.localite, p.metier, p.metier_saisi, p.telephone, p.telephone_cle, p.courriel, p.contact, p.origine, p.ide, p.etat,
                p.suivi_par, p.suivi_depuis, p.suivi_avant, p.libre_depuis, p.pas_texte, p.pas_le, p.derniere_action_le, p.compte_id, p.cree_par, p.cree_le,
                s.entreprise AS suiveur, av.entreprise AS avant, cr.entreprise AS createur, ' . self::ECHEANCE . ' AS echeance, %s AS tenue,
                c.demo AS c_demo, c.demo_fin AS c_demo_fin, c.offres_max AS c_offres_max,
                (SELECT COUNT(*) FROM enregistrement e WHERE e.artisan_id = c.id) AS c_dictees,
                (SELECT MAX(e.recu_le) FROM enregistrement e WHERE e.artisan_id = c.id) AS c_derniere
         FROM prospect p LEFT JOIN artisan s ON s.id = p.suivi_par LEFT JOIN artisan av ON av.id = p.suivi_avant
              LEFT JOIN artisan cr ON cr.id = p.cree_par LEFT JOIN artisan c ON c.id = p.compte_id';

    /** Les comptes d'artisans : ni l'équipe, ni les démos d'entraînement. */
    private const COMPTES = 'SELECT a.id, a.entreprise, a.demo, a.offres_max, ap.entreprise AS apporteur
         FROM artisan a LEFT JOIN artisan ap ON ap.id = a.apporte_par
         WHERE a.administrateur = 0 AND a.role IS NULL AND a.entrainement = 0';

    public function __construct(private readonly Base $base)
    {
    }

    /** La condition « tenue », avec son paramètre du jour (un nom par emploi : PDO ne répète pas un paramètre). */
    private static function tenue(string $jour): string
    {
        return '(p.suivi_par IS NOT NULL AND ' . self::DANS_L_EQUIPE . ' AND (p.compte_id IS NOT NULL OR ' . self::ECHEANCE . " >= :$jour))";
    }

    /**
     * @param Champs $champs
     * @return int la fiche créée
     */
    public function inserer(array $champs, ?int $suiviPar, int $creePar, \DateTimeImmutable $quand): int
    {
        $q = Dates::versBase($quand);
        $this->base->ecrire(
            'INSERT INTO prospect (entreprise, nom_cle, npa, localite, metier, metier_saisi, telephone, telephone_cle, courriel, contact, origine, ide, suivi_par, suivi_depuis, derniere_action_le, cree_par, cree_le)
             VALUES (:entreprise, :nom_cle, :npa, :localite, :metier, :metier_saisi, :telephone, :telephone_cle, :courriel, :contact, :origine, :ide, :suivi, :depuis, :action, :cree_par, :cree_le)',
            $champs + ['suivi' => $suiviPar, 'depuis' => $suiviPar === null ? null : $q, 'action' => $q, 'cree_par' => $creePar, 'cree_le' => $q],
        );
        return $this->base->dernierId();
    }

    /** @param Champs $champs */
    public function modifier(int $id, array $champs, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire(
            'UPDATE prospect SET entreprise = :entreprise, nom_cle = :nom_cle, npa = :npa, localite = :localite, metier = :metier, metier_saisi = :metier_saisi, telephone = :telephone,
                    telephone_cle = :telephone_cle, courriel = :courriel, contact = :contact, origine = :origine, ide = :ide, derniere_action_le = :q WHERE id = :id',
            $champs + ['q' => Dates::versBase($quand), 'id' => $id],
        );
    }

    /**
     * @param string $aujourdhui le jour à Zurich, AAAA-MM-JJ
     * @return Fiche|null
     */
    public function fiche(int $id, string $aujourdhui): ?array
    {
        /** @var Fiche|null */
        return $this->base->un(sprintf(self::FICHES, self::tenue('j1')) . ' WHERE p.id = :id', ['j1' => $aujourdhui, 'id' => $id]);
    }

    /**
     * @param string $vue « miens » : tenues par l'acteur, le pas le plus proche d'abord ; « libres » : que personne ne
     *        tient, hors « pas intéressé » ; « retard » : tenues, pas dépassé ; tout autre mot : toutes, par nom
     * @param list<string> $mots les mots cherchés dans le nom réduit (nom_cle), tous exigés, dans n'importe quel ordre
     * @return list<Fiche> 200 au plus
     */
    public function lister(string $vue, int $acteur, array $mots, string $aujourdhui): array
    {
        $params = ['j1' => $aujourdhui];
        [$ou, $ordre] = match ($vue) {
            'miens' => ['p.suivi_par = :a AND ' . self::tenue('j2'), 'p.pas_le IS NULL, p.pas_le, p.entreprise, p.id'],
            'libres' => ['NOT ' . self::tenue('j2') . " AND p.etat <> 'pas_interesse'", 'p.entreprise, p.id'],
            'retard' => [self::tenue('j2') . ' AND p.pas_le < :j3', 'p.pas_le, p.entreprise, p.id'],
            default => ['1 = 1', 'p.entreprise, p.id'],
        };
        $params += match ($vue) {
            'miens' => ['a' => $acteur, 'j2' => $aujourdhui],
            'libres' => ['j2' => $aujourdhui],
            'retard' => ['j2' => $aujourdhui, 'j3' => $aujourdhui],
            default => [],
        };
        // Le nom seulement : une recherche ne doit rien rendre des champs qu'un collègue ne voit pas.
        foreach (array_slice($mots, 0, 4) as $i => $mot) {
            $ou .= " AND p.nom_cle LIKE :m$i";
            $params += ["m$i" => '%' . addcslashes($mot, '%_\\') . '%'];
        }
        /** @var list<Fiche> */
        return $this->base->lire(sprintf(self::FICHES, self::tenue('j1')) . " WHERE $ou ORDER BY $ordre LIMIT 200", $params);
    }

    /**
     * Les nombres des onglets. « tenues » : les fiches que l'acteur tient sans compte lié, celles que le plafond compte.
     *
     * @return array{miens: int, libres: int, tous: int, retard: int, tenues: int}
     */
    public function compter(int $acteur, string $aujourdhui): array
    {
        $t = self::tenue('j1');
        $l = $this->base->un(
            "SELECT COUNT(*) AS tous,
                    COALESCE(SUM(t.tenue = 1 AND t.suivi_par = :a1), 0) AS miens,
                    COALESCE(SUM(t.tenue = 0 AND t.etat <> 'pas_interesse'), 0) AS libres,
                    COALESCE(SUM(t.tenue = 1 AND t.pas_le < :j2), 0) AS retard,
                    COALESCE(SUM(t.tenue = 1 AND t.suivi_par = :a2 AND t.compte_id IS NULL), 0) AS tenues
             FROM (SELECT p.suivi_par, p.etat, p.pas_le, p.compte_id, $t AS tenue FROM prospect p) t",
            ['j1' => $aujourdhui, 'j2' => $aujourdhui, 'a1' => $acteur, 'a2' => $acteur],
        ) ?? [];
        $n = static fn (string $cle): int => is_numeric($l[$cle] ?? null) ? (int) $l[$cle] : 0;
        return ['miens' => $n('miens'), 'libres' => $n('libres'), 'tous' => $n('tous'), 'retard' => $n('retard'), 'tenues' => $n('tenues')];
    }

    /**
     * Les fiches qui ressemblent à une saisie : même numéro IDE, même téléphone, même courriel, ou même nom dans la
     * même localité. Une clé nulle ne ressemble à rien.
     *
     * @param array{ide: string|null, telephone_cle: string|null, courriel: string|null, nom_cle: string, npa: string|null, localite: string} $cles
     * @return list<array{id: int, motif: string}>
     */
    public function ressemblants(array $cles, ?int $sauf): array
    {
        /** @var list<array{id: int, motif: string}> */
        return $this->base->lire(
            "SELECT p.id, CASE WHEN p.ide = :ide1 THEN 'ide' WHEN p.telephone_cle = :tel1 THEN 'telephone' WHEN p.courriel = :mail1 THEN 'courriel' ELSE 'nom' END AS motif
             FROM prospect p
             WHERE (p.ide = :ide2 OR p.telephone_cle = :tel2 OR p.courriel = :mail2 OR (p.nom_cle = :nom AND (p.npa = :npa OR p.localite = :loc)))
               AND p.id <> :sauf
             ORDER BY (p.ide <=> :ide3) DESC, p.id LIMIT 5",
            ['ide1' => $cles['ide'], 'ide2' => $cles['ide'], 'ide3' => $cles['ide'], 'tel1' => $cles['telephone_cle'], 'tel2' => $cles['telephone_cle'],
                'mail1' => $cles['courriel'], 'mail2' => $cles['courriel'], 'nom' => $cles['nom_cle'], 'npa' => $cles['npa'], 'loc' => $cles['localite'], 'sauf' => $sauf ?? 0],
        );
    }

    /**
     * Les comptes d'artisans qui portent ce nom, sans fiche de prospect : déjà chez nous. Le nom seulement : un
     * courriel deviné ne doit pas désigner le compte d'un collègue (revue du 2026-11-24, S3).
     *
     * @return list<CompteProche>
     */
    public function comptesRessemblants(string $nom): array
    {
        /** @var list<CompteProche> */
        return $this->base->lire(
            self::COMPTES . ' AND a.entreprise = :nom AND NOT EXISTS (SELECT 1 FROM prospect p WHERE p.compte_id = a.id) ORDER BY a.id LIMIT 5',
            ['nom' => $nom],
        );
    }

    /**
     * Les comptes d'artisans dont le nom contient la recherche, sans fiche de prospect. Sert à « chercher avant de
     * créer » : le nom, la sorte de compte et qui l'a amené, rien d'autre.
     *
     * @param list<string> $mots tous exigés dans le nom du compte, sans égard aux accents (classement de la base)
     * @return list<CompteProche> 20 au plus ; aucun sans mot
     */
    public function chercherComptes(array $mots): array
    {
        if ($mots === []) {
            return [];
        }
        $ou = '';
        $params = [];
        foreach (array_slice($mots, 0, 4) as $i => $mot) {
            $ou .= " AND a.entreprise LIKE :m$i";
            $params += ["m$i" => '%' . addcslashes($mot, '%_\\') . '%'];
        }
        /** @var list<CompteProche> */
        return $this->base->lire(
            self::COMPTES . $ou . ' AND NOT EXISTS (SELECT 1 FROM prospect p WHERE p.compte_id = a.id) ORDER BY a.entreprise, a.id LIMIT 20',
            $params,
        );
    }

    /**
     * « Je prends » : seulement une fiche que personne ne tient. Le pas de l'ancien suiveur s'efface ; « pas
     * intéressé » redevient « à contacter ».
     *
     * @return bool false si la fiche était tenue : rien n'est écrit
     */
    public function prendre(int $id, int $acteur, string $aujourdhui, \DateTimeImmutable $quand): bool
    {
        $q = Dates::versBase($quand);
        return $this->base->ecrire(
            "UPDATE prospect p SET p.suivi_par = :a, p.suivi_depuis = :q1, p.suivi_avant = NULL, p.libre_depuis = NULL, p.pas_texte = NULL, p.pas_le = NULL,
                    p.derniere_action_le = :q2, p.etat = IF(p.etat = 'pas_interesse', 'a_contacter', p.etat)
             WHERE p.id = :id AND NOT " . self::tenue('j1'),
            ['a' => $acteur, 'q1' => $q, 'q2' => $q, 'id' => $id, 'j1' => $aujourdhui],
        ) === 1;
    }

    /**
     * La fiche redevient libre, de la main de celui qui la suit. Une fiche liée à un compte ne se lâche pas.
     *
     * @return bool false si l'acteur ne la suivait pas
     */
    public function lacher(int $id, int $acteur, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire(
            'UPDATE prospect SET suivi_avant = suivi_par, suivi_par = NULL, suivi_depuis = NULL, libre_depuis = :q, pas_texte = NULL, pas_le = NULL
             WHERE id = :id AND suivi_par = :a AND compte_id IS NULL',
            ['q' => Dates::versBase($quand), 'id' => $id, 'a' => $acteur],
        ) === 1;
    }

    public function changerEtat(int $id, string $etat, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire('UPDATE prospect SET etat = :e, derniere_action_le = :q WHERE id = :id', ['e' => $etat, 'q' => Dates::versBase($quand), 'id' => $id]);
    }

    /** Le prochain pas ; null et null : plus de pas prévu. */
    public function poserPas(int $id, ?string $texte, ?string $date, \DateTimeImmutable $quand): void
    {
        $this->base->ecrire('UPDATE prospect SET pas_texte = :t, pas_le = :d, derniere_action_le = :q WHERE id = :id', ['t' => $texte, 'd' => $date, 'q' => Dates::versBase($quand), 'id' => $id]);
    }

    /** Une note datée. $action : la note compte comme une action sur la fiche et repousse son échéance. */
    public function noter(int $id, ?int $auteur, string $texte, \DateTimeImmutable $quand, bool $action = true): void
    {
        $q = Dates::versBase($quand);
        $this->base->ecrire('INSERT INTO prospect_note (prospect_id, auteur_id, texte, cree_le) VALUES (:p, :a, :t, :q)', ['p' => $id, 'a' => $auteur, 't' => $texte, 'q' => $q]);
        if ($action) {
            $this->base->ecrire('UPDATE prospect SET derniere_action_le = :q WHERE id = :id', ['q' => $q, 'id' => $id]);
        }
    }

    /** @return list<array{texte: string, cree_le: string, auteur: string|null}> les plus récentes d'abord, 100 au plus */
    public function notes(int $id): array
    {
        /** @var list<array{texte: string, cree_le: string, auteur: string|null}> */
        return $this->base->lire(
            'SELECT n.texte, n.cree_le, a.entreprise AS auteur FROM prospect_note n LEFT JOIN artisan a ON a.id = n.auteur_id WHERE n.prospect_id = :p ORDER BY n.cree_le DESC, n.id DESC LIMIT 100',
            ['p' => $id],
        );
    }

    /**
     * La démo créée depuis la fiche : l'état de la fiche se lit désormais sur ce compte.
     *
     * @return bool false si la fiche avait déjà un compte : rien n'est écrit
     */
    public function lierCompte(int $id, int $compteId, \DateTimeImmutable $quand): bool
    {
        return $this->base->ecrire('UPDATE prospect SET compte_id = :c, derniere_action_le = :q WHERE id = :id AND compte_id IS NULL', ['c' => $compteId, 'q' => Dates::versBase($quand), 'id' => $id]) === 1;
    }

    /**
     * Deux fiches d'un même artisan n'en font plus qu'une : les notes passent sur la fiche gardée, ses champs vides
     * prennent ceux de l'autre, l'autre disparaît. À appeler dans une transaction.
     *
     * @param bool $reprendreSuivi la fiche gardée était libre et l'autre tenue : celui qui tenait l'autre tient la gardée,
     *        avec son prochain pas
     */
    public function fusionner(int $garde, int $absorbee, bool $reprendreSuivi): void
    {
        $lire = fn (int $id): ?array => $this->base->un('SELECT metier, metier_saisi, telephone, telephone_cle, courriel, contact, origine, ide, compte_id, suivi_par, suivi_depuis, pas_texte, pas_le, derniere_action_le FROM prospect WHERE id = :id', ['id' => $id]);
        $a = $lire($garde);
        $b = $lire($absorbee);
        if ($a === null || $b === null) {
            throw new \DomainException('Fiche introuvable.');
        }
        $this->base->ecrire('UPDATE prospect_note SET prospect_id = :g WHERE prospect_id = :b', ['g' => $garde, 'b' => $absorbee]);
        // Le numéro IDE et le compte lié sont uniques : l'autre fiche disparaît avant que la gardée les reprenne.
        $this->base->ecrire('DELETE FROM prospect WHERE id = :b', ['b' => $absorbee]);
        if ($reprendreSuivi) {
            $texte = static fn (mixed $v): ?string => is_string($v) ? $v : null;
            $this->base->ecrire(
                'UPDATE prospect SET suivi_par = :s, suivi_depuis = :d, suivi_avant = NULL, libre_depuis = NULL, pas_texte = :t, pas_le = :p, derniere_action_le = :a WHERE id = :g',
                ['s' => is_int($b['suivi_par']) ? $b['suivi_par'] : null, 'd' => $texte($b['suivi_depuis']), 't' => $texte($b['pas_texte']), 'p' => $texte($b['pas_le']), 'a' => $texte($b['derniere_action_le']), 'g' => $garde],
            );
        }
        // Le téléphone va avec sa clé, le métier avec son texte. Le lieu de la fiche gardée reste entier (revue, C6).
        foreach ([['metier', 'metier_saisi'], ['telephone', 'telephone_cle'], ['courriel'], ['contact'], ['origine'], ['ide'], ['compte_id']] as $groupe) {
            if ($a[$groupe[0]] !== null || $b[$groupe[0]] === null) {
                continue;
            }
            foreach ($groupe as $colonne) {
                $valeur = $b[$colonne];
                $this->base->ecrire("UPDATE prospect SET $colonne = :v WHERE id = :g", ['v' => is_int($valeur) || is_string($valeur) ? $valeur : null, 'g' => $garde]);
            }
        }
    }
}
