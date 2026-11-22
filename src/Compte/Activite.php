<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * L'activité d'un compte (décision de Xavier du 2026-11-22, docs/plans/2026-11-22_familles-activite) : un socle
 * commun, et ce qui change d'une activité à l'autre tient ici. Les mots des écrans et des documents, le rangement des
 * lignes (par pièce, par zone, ou par catégorie du catalogue) et, dans Offre\ExtracteurApi, deux phrases de la consigne.
 * Le métier du compte fixe son activité ; le titulaire peut la changer dans ses réglages.
 *
 * « Famille » nomme déjà les groupes de modèles de « Ma marque » (Marque::FAMILLES) : d'où « activité ».
 */
enum Activite: string
{
    case Batiment = 'batiment';
    case Exterieur = 'exterieur';
    case Prestations = 'prestations';

    /** Les métiers de Catalogue\CatalogueFictif hors du bâtiment ; tout autre métier : Bâtiment. */
    private const METIERS = ['paysagiste' => self::Exterieur, 'informatique' => self::Prestations, 'conseil' => self::Prestations,
        'marketing' => self::Prestations, 'prestation' => self::Prestations];

    /**
     * Les tournures du bâtiment dans les phrases que l'application fabrique (conditions de paiement, phrases par défaut
     * du modèle Lettre, libellés d'acompte proposés), et ce qu'en dit un prestataire. Voir dire().
     */
    private const PRESTATIONS = [
        'la fin des travaux' => 'la fin de la prestation',
        'en cours de travaux' => 'en cours de prestation',
        'début des travaux' => 'début de la prestation',
        'mi-chantier' => 'mi-parcours',
        'les travaux suivants' => 'les prestations suivantes',
        'Les travaux étant terminés' => 'La prestation étant terminée',
        'confié ces travaux' => 'confié cette prestation',
    ];

    /** L'activité écrite en base ; une valeur inconnue ou absente vaut le Bâtiment, l'activité d'avant ce réglage. */
    public static function lire(?string $cle): self
    {
        return self::tryFrom($cle ?? '') ?? self::Batiment;
    }

    public static function duMetier(?string $metier): self
    {
        return self::METIERS[$metier ?? ''] ?? self::Batiment;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Batiment => 'Bâtiment',
            self::Exterieur => 'Extérieur',
            self::Prestations => 'Prestations',
        };
    }

    /** Sous le libellé, dans le réglage « Votre activité ». */
    public function exemples(): string
    {
        return match ($this) {
            self::Batiment => 'Peinture, plâtrerie, carrelage, maçonnerie, sanitaire, menuiserie, sols.',
            self::Exterieur => 'Jardin, paysage, entretien extérieur.',
            self::Prestations => 'Informatique, conseil, marketing, autres services.',
        };
    }

    /** Faux : les lignes ne se rangent ni par pièce ni par zone, l'offre sort par catégorie du catalogue. */
    public function rangeParGroupe(): bool
    {
        return $this !== self::Prestations;
    }

    /**
     * Les mots des écrans et des documents, mêmes clés pour les trois activités. Une valeur vide : rien ne s'affiche
     * (titre du bloc et champ « Accès » de l'en-tête, pour les Prestations). « groupe » : le rangement d'une ligne,
     * au féminin dans les trois cas ; les Prestations gardent « pièce » pour les offres faites avant le changement.
     *
     * @return array<string, string>
     */
    public function mots(): array
    {
        $batiment = [
            'dictee' => 'Parlez, pièce par pièce.',
            'dictee_nom' => 'Visite',
            'dictee_objet' => 'la visite',
            'titre_defaut' => 'Visite',
            'premiere' => 'visite',
            'groupe' => 'pièce',
            'bloc_titre' => 'Chantier',
            'bloc_adresse' => 'Adresse du chantier',
            'bloc_contact' => 'Contact sur place',
            'bloc_acces' => 'Accès',
            'debut' => 'Début des travaux',
            'date' => 'Date de la visite',
            'non_compris_exemple' => 'Peinture de finition',
            'doc_lieu' => 'Chantier',
            'doc_adresse' => 'Adresse',
            'doc_fiche' => 'Chantier',
            'doc_detail' => 'Détail des travaux',
            'lettre_date' => 'Visite du',
            'travaux' => 'travaux',
        ];
        return match ($this) {
            self::Batiment => $batiment,
            self::Exterieur => [
                'dictee' => 'Parlez, zone par zone.',
                'groupe' => 'zone',
                'bloc_titre' => "Lieu d'intervention",
                'bloc_adresse' => 'Adresse',
                'non_compris_exemple' => 'Évacuation des déchets verts',
                'doc_lieu' => 'Lieu',
                'doc_fiche' => "Lieu d'intervention",
            ] + $batiment,
            self::Prestations => [
                'dictee' => 'Parlez, prestation par prestation.',
                'dictee_nom' => 'Dictée',
                'dictee_objet' => 'votre dictée',
                'titre_defaut' => 'Offre',
                'premiere' => 'offre',
                'bloc_titre' => '',
                'bloc_adresse' => 'Lieu',
                'bloc_contact' => 'Contact',
                'bloc_acces' => '',
                'debut' => 'Début',
                'date' => "Date de l'échange",
                'non_compris_exemple' => 'Licences des logiciels',
                'doc_lieu' => 'Lieu',
                'doc_adresse' => 'Lieu',
                'doc_fiche' => 'Informations',
                'doc_detail' => 'Détail des prestations',
                'lettre_date' => 'Échange du',
                'travaux' => 'prestations',
            ] + $batiment,
        };
    }

    /**
     * Une phrase fabriquée par l'application, dans les mots de l'activité : « à 30 jours dès la fin des travaux » devient
     * « dès la fin de la prestation » pour un prestataire. Jamais pour un texte tapé par l'utilisateur : ses mots restent
     * les siens.
     */
    public function dire(string $phrase): string
    {
        return $this === self::Prestations ? strtr($phrase, self::PRESTATIONS) : $phrase;
    }

    /**
     * Les métiers rangés sous leur activité, pour l'écran de création d'une démo : dans l'ordre des activités, puis
     * dans celui des libellés reçus.
     *
     * @param array<string, string> $libelles clé du métier => libellé
     * @return list<array{nom: string, metiers: array<string, string>}>
     */
    public static function grouper(array $libelles): array
    {
        $groupes = [];
        foreach (self::cases() as $activite) {
            $metiers = array_filter($libelles, static fn (string $cle): bool => self::duMetier($cle) === $activite, ARRAY_FILTER_USE_KEY);
            if ($metiers !== []) {
                $groupes[] = ['nom' => $activite->libelle(), 'metiers' => $metiers];
            }
        }
        return $groupes;
    }
}
