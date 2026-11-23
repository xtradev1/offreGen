<?php

declare(strict_types=1);

// Routeur de mesure pour php -S (chantier « sans rechargement », 2026-11-23). Hors dépôt : il imite l'Apache du site
// de test, mesuré le 2026-11-23 par curl, pour que les poids et les temps mesurés en local soient ceux du test :
// - fichiers statiques compressés (gzip), Last-Modified, ETag suffixé « -gzip » ;
// - aucune durée de cache ; un If-None-Match ne vaut jamais 304 (l'ETag reçu porte « -gzip », Apache compare à
//   l'ETag d'origine) : le navigateur retélécharge tout ; If-Modified-Since seul vaudrait 304 ;
// - pages compressées.
// MESURE_CACHE=froid : « no-cache » sur les fichiers, chaque page les redemande (cas d'un cache périmé, ETag cassé).
// MESURE_CACHE=chaud : fichiers frais pour un an (cas d'un cache valide, ou du correctif proposé).
// MESURE_CACHE=apres : les règles du public/.htaccess du lot 1 (2026-11-23) : adresse versionnée (?v=) ou PDF.js gardés un
// an, polices et images 7 jours, feuilles de style et scripts sans version revalidés ; aucun ETag (FileETag None), le
// 304 par If-Modified-Since.
$racine = getenv('MESURE_RACINE') ?: '/home/xavie/offreGen-fluidite/public';
$mode = getenv('MESURE_CACHE') ?: 'froid';
$chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$chemin = is_string($chemin) ? $chemin : '/';
$fichier = $chemin !== '/' ? realpath($racine . $chemin) : false;
$types = [
    'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'txt' => 'text/plain',
    'webmanifest' => 'application/manifest+json',
];
$gzip = str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip');

if ($fichier !== false && str_starts_with($fichier, $racine . '/') && is_file($fichier)) {
    $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) {
        http_response_code(403);
        return true;
    }
    $mtime = (int) filemtime($fichier);
    $taille = (int) filesize($fichier);
    $etag = sprintf('"%x-%x"', $taille, $mtime * 1000000);
    header('Content-Type: ' . $types[$ext]);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Vary: Accept-Encoding');
    if ($mode === 'apres') {
        $version = preg_match('/(^|&)v=[0-9a-f]{12}(&|$)/', $_SERVER['QUERY_STRING'] ?? '') === 1;
        header('Cache-Control: ' . ($version || str_starts_with($chemin, '/js/vendor/') ? 'public, max-age=31536000, immutable'
            : (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff2', 'woff'], true) ? 'public, max-age=604800' : 'no-cache')));
    } else {
        header($mode === 'chaud' ? 'Cache-Control: public, max-age=31536000' : 'Cache-Control: no-cache');
    }
    $inm = $mode === 'apres' ? null : ($_SERVER['HTTP_IF_NONE_MATCH'] ?? null);
    $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
    // Comportement d'Apache avec mod_deflate : l'ETag reçu (« …-gzip ») n'égale jamais l'ETag d'origine.
    if (($inm !== null && trim($inm) === $etag) || ($inm === null && $ims !== null && strtotime($ims) >= $mtime)) {
        http_response_code(304);
        return true;
    }
    $corps = (string) file_get_contents($fichier);
    if ($gzip) {
        $corps = (string) gzencode($corps, 6);
        header('Content-Encoding: gzip');
        if ($mode !== 'apres') {
            header('ETag: ' . substr($etag, 0, -1) . '-gzip"');
        }
    } elseif ($mode !== 'apres') {
        header('ETag: ' . $etag);
    }
    header('Content-Length: ' . strlen($corps));
    echo $corps;
    return true;
}

// Pages : compressées comme chez l'hébergeur, sauf l'envoi d'une dictée (le traitement suit la réponse dans le même
// processus, sa longueur annoncée doit rester juste).
$_SERVER['SCRIPT_FILENAME'] = $racine . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
chdir($racine);
if ($gzip && !($chemin === '/import' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    ob_start('ob_gzhandler');
}
require $racine . '/index.php';
