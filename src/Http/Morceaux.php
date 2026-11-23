<?php

declare(strict_types=1);

namespace OffreGen\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Morceaux de page (chantier sans rechargement, D9 A, 2026-11-23). Un formulaire envoyé par fetch demande, par
 * l'en-tête X-Morceaux, les éléments de la page qui ont pu changer (leurs id). Le noyau fait l'action comme d'habitude,
 * rend la page où menait la redirection (même session : les messages de l'action, « Annuler » compris, y paraissent),
 * et ne renvoie que ces éléments. Le serveur fait le même travail qu'avant (l'envoi, puis la page), en une requête,
 * et le téléphone reçoit 1 à 3 Ko au lieu d'une page entière et de ses fichiers. Sans JavaScript, rien ne change.
 */
final class Morceaux
{
    public const DEMANDE = 'X-Morceaux';
    /** La page dont on veut les morceaux, à la place de la cible de la redirection (l'ajout depuis « + Ligne »). */
    public const PAGE = 'X-Morceaux-Page';
    /** La page où est l'artisan : une action qui mène à un autre écran (l'aperçu après le client) y envoie le navigateur. */
    public const ICI = 'X-Morceaux-Ici';
    /** Dans la réponse : l'adresse où menait l'action, ancre comprise (#l12 : la ligne touchée). */
    public const ADRESSE = 'X-Morceaux-Adresse';
    /** Dans la réponse : la page ne se découpe pas (autre écran, renvoi ailleurs) ; le navigateur y va. */
    public const ALLER = 'X-Morceaux-Aller';
    /** Dans la réponse : 401, la session est perdue ; le geste attend la reconnexion. */
    public const SESSION = 'X-Morceaux-Session';
    /** Dans la réponse : 403, le jeton du formulaire est refusé. */
    public const JETON = 'X-Morceaux-Jeton';

    /** La clé unique d'un geste (lot 4) : rejoué, il n'est fait qu'une fois (Noyau, DepotEnvois). */
    public const CLE = 'X-Cle-Envoi';
    /** Le refus d'un geste dont l'envoi précédent tourne encore : fluide.js le réessaiera (il cherche « déjà en cours »). */
    public const EN_COURS = "Envoi déjà en cours\u{00A0}: il repartira.";

    private const MAXIMUM = 60;

    /** La clé du geste, si elle a la forme d'une clé du téléphone (crypto.randomUUID ou équivalent). */
    public static function cle(Request $requete): ?string
    {
        $cle = $requete->headers->get(self::CLE);
        return $cle !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $cle) === 1 ? $cle : null;
    }

    /**
     * Les id demandés, ou null si la requête ne demande pas de morceaux. Un id qui n'a pas la forme d'un id de gabarit
     * est ignoré ; une liste vide demande les seuls messages.
     *
     * @return list<string>|null
     */
    public static function demandes(Request $requete): ?array
    {
        $valeur = $requete->headers->get(self::DEMANDE);
        if ($valeur === null) {
            return null;
        }
        $ids = preg_split('/\s+/', trim($valeur), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_filter($ids, static fn (string $id): bool => preg_match('/^[a-z][a-z0-9-]{0,39}$/', $id) === 1);
        return array_slice(array_values(array_unique($ids)), 0, self::MAXIMUM);
    }

    /**
     * La page à découper : l'en-tête X-Morceaux-Page s'il désigne une page du site, sinon la cible de la redirection.
     * Null : la cible est hors du site, elle ne se découpe pas.
     */
    public static function page(Request $requete, string $cible): ?string
    {
        $voulue = $requete->headers->get(self::PAGE);
        $chemin = $voulue !== null && self::local($voulue) ? $voulue : $cible;
        if (!self::local($chemin)) {
            return null;
        }
        $diese = strpos($chemin, '#');
        return $diese === false ? $chemin : substr($chemin, 0, $diese);
    }

    /**
     * L'action mène à un autre écran que celui de l'artisan (X-Morceaux-Ici), sans page demandée : rien à découper,
     * le navigateur y va. Seul le chemin compte (/catalogue?cat=x est l'écran /catalogue).
     */
    public static function ailleurs(Request $requete, string $cible): bool
    {
        $ici = $requete->headers->get(self::ICI);
        if ($requete->headers->has(self::PAGE) || $ici === null || !self::local($ici) || !self::local($cible)) {
            return false;
        }
        return parse_url($ici, PHP_URL_PATH) !== parse_url($cible, PHP_URL_PATH);
    }

    /** Une adresse du site : un chemin qui commence par une seule barre, sans hôte ni barre oblique inverse. */
    public static function local(string $adresse): bool
    {
        return preg_match('#^/(?![/\\\\])#', $adresse) === 1 && !str_contains($adresse, '\\') && parse_url($adresse, PHP_URL_HOST) === null;
    }

    /**
     * Les éléments demandés de la page rendue, à la suite ; un <template data-absent="id"> pour chacun qui n'y est plus
     * (une ligne supprimée, une carte partie) ; puis les messages de la page et la ligne à ouvrir.
     *
     * @param list<string> $ids
     */
    public static function extraire(string $html, array $ids): string
    {
        $page = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $sortie = '';
        foreach ($ids as $id) {
            $element = $page->getElementById($id);
            $sortie .= $element === null ? '<template data-absent="' . $id . '"></template>' : $page->saveHtml($element);
        }
        foreach ($page->querySelectorAll('.app > .snack, [data-focus-ligne]') as $message) {
            $sortie .= $page->saveHtml($message);
        }
        return $sortie;
    }

    /** La réponse aux morceaux : jamais gardée, l'adresse de la page en en-tête. */
    public static function reponse(string $corps, string $adresse): Response
    {
        return new Response($corps, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            self::ADRESSE => $adresse,
        ]);
    }

    /** Le navigateur va à l'adresse : la réponse n'est pas une page à découper (autre écran, renvoi ailleurs). */
    public static function aller(string $adresse): Response
    {
        return new Response('', 200, ['Cache-Control' => 'no-store', self::ADRESSE => $adresse, self::ALLER => '1']);
    }
}
