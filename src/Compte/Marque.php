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
    /**
     * Les douze modèles retenus par Xavier (maquettes des 19 et 20 novembre 2026), dans l'ordre de l'écran. Chacun a son
     * gabarit, templates/document/<clé>.html.twig. La clé tient dans artisan.modele, VARCHAR(12).
     */
    public const MODELES = ['sobre' => 'Sobre', 'lettre' => 'Lettre', 'atelier' => 'Atelier', 'bandeau' => 'Bandeau', 'moderne' => 'Moderne', 'lateral' => 'Latéral',
        'enseigne' => 'Enseigne', 'technique' => 'Technique', 'recap' => 'Récapitulatif', 'chantier' => 'Chantier', 'epure' => 'Épuré', 'marge' => 'Marge'];

    /**
     * Les quatre familles de l'écran « Ma marque » (Xavier, 2026-11-19), dans l'ordre ; chaque modèle est dans une seule.
     *
     * @var array<string, array{nom: string, modeles: list<string>}>
     */
    public const FAMILLES = [
        'classique' => ['nom' => 'Classiques', 'modeles' => ['sobre', 'lettre', 'atelier']],
        'couleur' => ['nom' => 'Couleur', 'modeles' => ['bandeau', 'moderne', 'lateral', 'enseigne']],
        'detail' => ['nom' => 'Détaillés', 'modeles' => ['technique', 'recap', 'chantier']],
        'simple' => ['nom' => 'Simples', 'modeles' => ['epure', 'marge']],
    ];

    /** Le modèle proposé pour chaque métier du catalogue (Catalogue\CatalogueFictif) ; tout autre métier : Sobre. */
    private const PAR_METIER = ['peintre' => 'marge', 'platrier' => 'chantier', 'carreleur' => 'sobre', 'macon' => 'bandeau', 'sanitaire' => 'technique',
        'menuisier' => 'atelier', 'sols' => 'moderne', 'paysagiste' => 'epure'];

    /** Couleurs proposées d'un toucher, après celle du logo. */
    public const TEINTES = ['#1D4E89', '#2E7D32', '#B23A2E', '#8A5A2B', '#0F7C8C', '#3B3F46'];

    public const COULEUR_DEFAUT = '#1D4E89';

    /** Contraste minimal d'un texte sur le blanc (WCAG, niveau AA). */
    private const CONTRASTE_MIN = 4.5;

    /** Contraste d'une couleur « bien visible » (WCAG, niveau AAA) : sous ce seuil, une couleur tapée est signalée (2b). */
    private const CONTRASTE_BIEN = 7.0;

    public string $modele;

    /**
     * Le gabarit du document porte le nom du modèle (templates/document/<modèle>.html.twig) : un modèle hors de la liste
     * devient Sobre ici même, quel que soit l'appelant.
     *
     * @param string|null $logo nom du fichier dans le dossier des logos, null sans logo
     */
    public function __construct(
        string $modele = 'sobre',
        public ?string $couleurChoisie = null,
        public ?string $logo = null,
        public ?string $couleurDuLogo = null,
    ) {
        $this->modele = self::modeleValide($modele);
    }

    public function couleur(): string
    {
        return $this->couleurChoisie ?? self::COULEUR_DEFAUT;
    }

    /** La couleur pour du texte, des filets et des fonds sous texte blanc : assombrie si elle est trop claire. */
    public function principale(): string
    {
        return self::foncer($this->couleur(), self::CONTRASTE_MIN);
    }

    /**
     * Une couleur tapée, ou celle du logo, qui ne tient pas 7:1 sur le blanc (2b, maquette du 5e tour du chantier
     * responsive, validée par Xavier le 2026-11-20) : deux versions plus foncées de la même teinte. « visible » est celle
     * que le document emploie déjà (principale()), « bien » tient 7:1. app.js (avisCouleur) refait ce calcul à la frappe.
     *
     * @return array{contraste: float, bien: string, visible: string}|null null si la couleur tient déjà 7:1
     */
    public static function propositions(string $couleur): ?array
    {
        $c = self::contraste($couleur, '#FFFFFF');
        return $c >= self::CONTRASTE_BIEN ? null
            : ['contraste' => $c, 'bien' => self::foncer($couleur, self::CONTRASTE_BIEN), 'visible' => self::foncer($couleur, self::CONTRASTE_MIN)];
    }

    /** Un contraste pour l'écran, arrondi vers le bas au dixième (jamais plus beau qu'il n'est) : « 1 », « 2,2 », « 7,3 ». */
    public static function rapport(float $contraste): string
    {
        return (string) preg_replace('/,0$/', '', number_format(floor($contraste * 10) / 10, 1, ',', ''));
    }

    /** La couleur assombrie par pas de 8 % jusqu'à tenir le contraste demandé sur le blanc. */
    private static function foncer(string $couleur, float $seuil): string
    {
        [$r, $v, $b] = self::rvb($couleur);
        for ($i = 0; $i < 40 && self::contraste(self::hex($r, $v, $b), '#FFFFFF') < $seuil; $i++) {
            [$r, $v, $b] = [(int) round($r * 0.92), (int) round($v * 0.92), (int) round($b * 0.92)];
        }
        return self::hex($r, $v, $b);
    }

    /**
     * La couleur pour un texte posé sur sa propre teinte (titres de poste de Chantier) : le fond teinté retire du
     * contraste, la couleur principale s'assombrit encore jusqu'à tenir le même seuil sur ce fond.
     */
    public function surTeinte(): string
    {
        [$r, $v, $b] = self::rvb($this->principale());
        for ($i = 0; $i < 40 && self::contraste(self::hex($r, $v, $b), $this->teinte(0.9)) < self::CONTRASTE_MIN; $i++) {
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
        // « .pdf » : dans le PDF, pied, bande et colonne sortent de .doc (document/_base.html.twig). « !important » : une
        // classe de couleur l'emporte sur la règle du modèle, plus précise, qui pose le filet sans couleur.
        $p = $this->principale();
        return ".doc .c-texte, .pdf .c-texte { color: $p !important; }\n"
            . ".doc .c-fond, .pdf .c-fond { background-color: $p !important; color: #FFFFFF !important; }\n"
            . ".doc.c-filet, .doc .c-filet, .pdf .c-filet { border-color: $p !important; }\n"
            . ".doc .c-bord-gauche { border-left-color: $p !important; }\n"
            . '.doc .c-teinte, .pdf .c-teinte { background-color: ' . $this->teinte(0.9) . " !important; }\n"
            . '.doc .c-teinte-forte { background-color: ' . $this->teinte(0.82) . " !important; }\n"
            . '.doc .c-teinte.c-texte { color: ' . $this->surTeinte() . " !important; }\n";
    }

    /**
     * Change avec le contenu de la feuille (la couleur, mais aussi ses règles) : son adresse en dépend, le navigateur peut
     * la garder un jour. Calculée sur la couleur seule, elle aurait laissé aux comptes existants la feuille d'avant les
     * modèles du bâtiment, sans ses classes nouvelles (2026-11-20).
     */
    public function version(): string
    {
        return substr(hash('sha256', $this->css() . $this->modele), 0, 10);
    }

    /** « #1d4e89 » donne « #1D4E89 » ; null pour tout le reste. */
    public static function couleurValide(string $saisie): ?string
    {
        $t = strtoupper(trim($saisie));
        return preg_match('/^#[0-9A-F]{6}$/', $t) === 1 ? $t : null;
    }

    /** Le modèle proposé pour le métier du compte : réglé d'office à sa création, puis marqué à l'écran. L'artisan choisit. */
    public static function pourMetier(?string $metier): string
    {
        return self::PAR_METIER[$metier ?? ''] ?? 'sobre';
    }

    /**
     * Le corps du nom de l'entreprise dans Enseigne, en pixels de la feuille : 58 jusqu'à 18 caractères, 46 jusqu'à 24,
     * 36 jusqu'à 32, 28 au-delà. À chaque palier le nom tient sur une ligne de la feuille (classe t<corps> de
     * document.css).
     */
    public static function corpsEnseigne(string $entreprise): int
    {
        $longueur = mb_strlen($entreprise);
        return match (true) {
            $longueur <= 18 => 58,
            $longueur <= 24 => 46,
            $longueur <= 32 => 36,
            default => 28,
        };
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
