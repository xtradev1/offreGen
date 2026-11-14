<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * La marque de l'artisan sur ses documents : modèle, couleur, logo (sous-chantier 16, maquette validée
 * par Xavier le 2026-11-12). Le document n'emploie jamais la couleur brute pour du texte : principale()
 * l'assombrit jusqu'à rester lisible sur le papier blanc.
 */
final readonly class Marque
{
    /** Les six modèles retenus par Xavier, dans l'ordre de la maquette. */
    public const MODELES = ['sobre' => 'Sobre', 'bandeau' => 'Bandeau', 'atelier' => 'Atelier', 'technique' => 'Technique', 'moderne' => 'Moderne', 'epure' => 'Épuré'];

    /** Couleurs proposées d'un toucher, après celle du logo. */
    public const TEINTES = ['#1D4E89', '#2E7D32', '#B23A2E', '#8A5A2B', '#0F7C8C', '#3B3F46'];

    public const COULEUR_DEFAUT = '#1D4E89';

    /** Contraste minimal d'un texte sur le blanc (WCAG, niveau AA). */
    private const CONTRASTE_MIN = 4.5;

    /** @param string|null $logo nom du fichier dans le dossier des logos, null sans logo */
    public function __construct(
        public string $modele = 'sobre',
        public ?string $couleurChoisie = null,
        public ?string $logo = null,
        public ?string $couleurDuLogo = null,
    ) {
    }

    public function couleur(): string
    {
        return $this->couleurChoisie ?? self::COULEUR_DEFAUT;
    }

    /** La couleur pour du texte, des filets et des fonds sous texte blanc : assombrie si elle est trop claire. */
    public function principale(): string
    {
        [$r, $v, $b] = self::rvb($this->couleur());
        for ($i = 0; $i < 40 && self::contraste(self::hex($r, $v, $b), '#FFFFFF') < self::CONTRASTE_MIN; $i++) {
            [$r, $v, $b] = [(int) round($r * 0.92), (int) round($v * 0.92), (int) round($b * 0.92)];
        }
        return self::hex($r, $v, $b);
    }

    /** La couleur principale fondue dans le blanc, pour un fond léger : 0.9 garde 10 % de couleur. */
    public function teinte(float $blanc = 0.9): string
    {
        [$r, $v, $b] = self::rvb($this->principale());
        $m = static fn (int $c): int => (int) round($c + (255 - $c) * $blanc);
        return self::hex($m($r), $m($v), $m($b));
    }

    /**
     * La couleur du document en classes CSS : la CSP du site refuse les styles en ligne, l'aperçu charge donc
     * cette feuille par /reglages/marque.css ; le PDF la reçoit dans sa balise style. CSS 2.1 pour dompdf.
     */
    public function css(): string
    {
        $p = $this->principale();
        return ".doc .c-texte { color: $p; }\n"
            . ".doc .c-fond { background-color: $p; color: #FFFFFF; }\n"
            . ".doc.c-filet, .doc .c-filet { border-color: $p; }\n"
            . '.doc .c-teinte { background-color: ' . $this->teinte(0.9) . "; }\n"
            . '.doc .c-teinte-forte { background-color: ' . $this->teinte(0.82) . "; }\n";
    }

    /** Change avec la couleur : l'adresse de la feuille en dépend, le navigateur peut la garder. */
    public function version(): string
    {
        return substr(hash('sha256', $this->principale() . $this->modele), 0, 10);
    }

    /** « #1d4e89 » donne « #1D4E89 » ; null pour tout le reste. */
    public static function couleurValide(string $saisie): ?string
    {
        $t = strtoupper(trim($saisie));
        return preg_match('/^#[0-9A-F]{6}$/', $t) === 1 ? $t : null;
    }

    public static function modeleValide(string $saisie): string
    {
        return array_key_exists($saisie, self::MODELES) ? $saisie : 'sobre';
    }

    /** Rapport de contraste entre deux couleurs, de 1 à 21. */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $lin = static function (int $c): float {
            $s = $c / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        [$r, $v, $b] = self::rvb($hex);
        return 0.2126 * $lin($r) + 0.7152 * $lin($v) + 0.0722 * $lin($b);
    }

    /** @return array{int, int, int} */
    private static function rvb(string $hex): array
    {
        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    private static function hex(int $r, int $v, int $b): string
    {
        return sprintf('#%02X%02X%02X', $r, $v, $b);
    }
}
