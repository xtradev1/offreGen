<?php

declare(strict_types=1);

namespace OffreGen\Facture;

/**
 * Ce que montre la section de paiement d'une facture (récépissé et section paiement), déjà mis en forme selon la
 * norme SIX : IBAN par groupes de quatre, référence groupée, montant avec une espace pour les milliers.
 * Construite par QrFacture::section, qui a validé la charge utile.
 *
 * Les images ont deux formes : en ligne pour l'écran (la CSP refuse les images en data:), en data: pour le PDF
 * (dompdf lit ainsi le SVG).
 */
final readonly class SectionPaiement
{
    /**
     * @param list<string> $creancier lignes « Compte / Payable à », sans l'IBAN
     * @param list<string>|null $debiteur lignes « Payable par » ; null : cadre vide à remplir à la main
     */
    public function __construct(
        public string $texte,
        public string $svg,
        public string $compte,
        public array $creancier,
        public ?string $reference,
        public string $informations,
        public ?array $debiteur,
        public string $monnaie,
        public string $montant,
    ) {
    }

    public function svgEnDonnees(): string
    {
        return self::donnees($this->svg);
    }

    /** Cadre vide de « Payable par » : 52 × 20 mm au récépissé, 65 × 25 mm dans la section paiement. */
    public function cadre(string $partie, bool $enDonnees = false): string
    {
        $svg = $partie === 'recu' ? QrFacture::cadre(52, 20) : QrFacture::cadre(65, 25);
        return $enDonnees ? self::donnees($svg) : $svg;
    }

    private static function donnees(string $svg): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
