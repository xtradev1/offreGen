<?php

declare(strict_types=1);

namespace OffreGen\Donnees;

use OffreGen\Compte\Activite;

/**
 * Les comptes. Hors portée par nature : on cherche un compte avant de savoir qui est connecté.
 * Sert la connexion et les commandes de bin/, jamais une page qui liste des comptes.
 *
 * @phpstan-type Artisan array{id: int, courriel: string, mot_de_passe: string, entreprise: string, demo: int, plafond_jour: int|null, offres_max: int|null, administrateur: int, version_session: int, role: string|null, cree_par: int|null, demo_fin: string|null, offres_offertes: int, metier: string|null, metier_saisi: string|null, activite: string, courriel_offre_prete: int, courriel_dictee_echec: int}
 */
final class DepotArtisans
{
    private const COLONNES = 'id, courriel, mot_de_passe, entreprise, demo, plafond_jour, offres_max, administrateur, version_session, role, cree_par, demo_fin, offres_offertes, metier, metier_saisi, activite, courriel_offre_prete, courriel_dictee_echec';

    public function __construct(private readonly Base $base)
    {
    }

    /** @return Artisan|null */
    public function parCourriel(string $courriel): ?array
    {
        /** @var Artisan|null */
        return $this->base->un('SELECT ' . self::COLONNES . ' FROM artisan WHERE courriel = :c', ['c' => self::normaliser($courriel)]);
    }

    /** @return Artisan|null */
    public function parId(int $id): ?array
    {
        /** @var Artisan|null */
        return $this->base->un('SELECT ' . self::COLONNES . ' FROM artisan WHERE id = :id', ['id' => $id]);
    }

    public function creer(string $courriel, string $hache, string $entreprise, bool $demo, ?int $plafond, \DateTimeImmutable $quand, ?int $offresMax = null): int
    {
        $this->base->ecrire(
            'INSERT INTO artisan (courriel, mot_de_passe, entreprise, demo, plafond_jour, offres_max, cree_le)
             VALUES (:c, :h, :e, :d, :p, :m, :q)',
            ['c' => self::normaliser($courriel), 'h' => $hache, 'e' => $entreprise, 'd' => $demo ? 1 : 0, 'p' => $plafond, 'm' => $offresMax, 'q' => Dates::versBase($quand)],
        );
        return $this->base->dernierId();
    }

    /** Plafond total d'offres d'un compte d'essai ; null : aucun plafond. */
    public function changerOffresMax(int $id, ?int $offresMax): void
    {
        $this->base->ecrire('UPDATE artisan SET offres_max = :m WHERE id = :id', ['m' => $offresMax, 'id' => $id]);
    }

    public function changerMotDePasse(int $id, string $hache): void
    {
        $this->base->ecrire('UPDATE artisan SET mot_de_passe = :h WHERE id = :id', ['h' => $hache, 'id' => $id]);
    }

    /**
     * Nouveau mot de passe choisi par l'artisan : le numéro de session avance, les sessions ouvertes ailleurs tombent.
     * $versionAttendue : l'écriture n'a lieu que si le numéro n'a pas bougé entre-temps (un lien d'oubli a servi,
     * ou un autre changement est passé) ; sans elle (lien d'oubli), elle a toujours lieu.
     *
     * @return int|null le nouveau numéro de session ; null si le numéro avait déjà changé
     */
    public function remplacerMotDePasse(int $id, string $hache, ?int $versionAttendue = null): ?int
    {
        return $this->base->transaction(function () use ($id, $hache, $versionAttendue): ?int {
            $touchees = $this->base->ecrire(
                'UPDATE artisan SET mot_de_passe = :h, version_session = version_session + 1, acces_en_attente = 0 WHERE id = :id'
                . ($versionAttendue === null ? '' : ' AND version_session = :v'),
                ['h' => $hache, 'id' => $id] + ($versionAttendue === null ? [] : ['v' => $versionAttendue]),
            );
            if ($touchees !== 1) {
                return null;
            }
            return (int) $this->base->valeur('SELECT version_session FROM artisan WHERE id = :id', ['id' => $id]);
        });
    }

    public function changerAdministrateur(int $id, bool $administrateur): void
    {
        $this->base->ecrire('UPDATE artisan SET administrateur = :a WHERE id = :id', ['a' => $administrateur ? 1 : 0, 'id' => $id]);
    }

    /** Le métier du compte (clé de CatalogueFictif), sous « autre » le texte tapé, et l'activité que ce métier fixe. */
    public function changerMetier(int $id, ?string $metier, ?string $metierSaisi, Activite $activite): void
    {
        $this->base->ecrire('UPDATE artisan SET metier = :m, metier_saisi = :s, activite = :a WHERE id = :id', ['m' => $metier, 's' => $metierSaisi, 'a' => $activite->value, 'id' => $id]);
    }

    /** L'activité choisie par le titulaire dans ses réglages : ni le métier ni le catalogue ne changent. */
    public function changerActivite(int $id, Activite $activite): void
    {
        $this->base->ecrire('UPDATE artisan SET activite = :a WHERE id = :id', ['a' => $activite->value, 'id' => $id]);
    }

    /**
     * Une démonstration devient un essai : plus de plafond par jour ni de date de fin, un nombre total d'offres,
     * et l'adresse de l'artisan à la place de l'adresse inventée de la démonstration.
     *
     * @return bool faux si le compte n'est plus une démonstration (passé ou remis à zéro entre-temps)
     */
    public function sortirDeDemo(int $id, int $offresMax, string $courriel): bool
    {
        return $this->base->ecrire(
            'UPDATE artisan SET demo = 0, plafond_jour = NULL, demo_fin = NULL, offres_max = :m, courriel = :c WHERE id = :id AND demo = 1',
            ['m' => $offresMax, 'c' => self::normaliser($courriel), 'id' => $id],
        ) === 1;
    }

    /**
     * Les métiers tapés sous « autre », du plus fréquent au plus rare. Majuscules et accents ne séparent pas
     * deux saisies (classement de la colonne) ; le texte rendu est l'une d'elles.
     *
     * @return list<array{texte: string, nombre: int}>
     */
    public function metiersSaisis(): array
    {
        /** @var list<array{texte: string, nombre: int}> */
        return $this->base->lire(
            "SELECT MIN(metier_saisi) AS texte, COUNT(*) AS nombre FROM artisan
             WHERE metier = 'autre' AND metier_saisi IS NOT NULL
             GROUP BY metier_saisi ORDER BY nombre DESC, texte",
            [],
        );
    }

    public static function normaliser(string $courriel): string
    {
        return mb_strtolower(trim($courriel));
    }
}
