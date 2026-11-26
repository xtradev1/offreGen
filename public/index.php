<?php

declare(strict_types=1);

use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\Request;

// Serveur intégré de PHP en développement : un fichier statique autorisé est servi tel quel,
// comme le fait public/.htaccess chez l'hébergeur.
if (PHP_SAPI === 'cli-server') {
    $chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($chemin) && $chemin !== '/' && is_file(__DIR__ . $chemin)) {
        // La liste de public/.htaccess, à l'identique (.txt : copies des écrans de la page de relecture, 2026-11-25).
        if (preg_match('/\.(css|js|mjs|png|jpe?g|gif|svg|webp|avif|ico|woff2?|txt|webmanifest)$/i', $chemin) === 1) {
            return false;
        }
        // Tout autre fichier de public/ (.htaccess, témoins de la sonde) est refusé, comme chez l'hébergeur.
        // Sans cela, php -S le prend pour le script et la requête arrive sur la route « / ».
        http_response_code(404);
        exit;
    }
}
header_remove('X-Powered-By');
// Jamais d'erreur PHP à l'écran, quel que soit le réglage du serveur : une erreur de lecture du .env en montrait un
// morceau (revue du 2026-11-18, M13). Elle part au journal du serveur ; le visiteur voit une page d'erreur sobre.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $conteneur = Conteneur::depuisEnvironnement(dirname(__DIR__));
    $reponse = $conteneur->noyau()->traiter(Request::createFromGlobals());
} catch (\Throwable $e) {
    error_log('offreGen, démarrage : ' . $e::class);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Le service est momentanément indisponible.\n";
    exit;
}
// Sans fastcgi_finish_request (php -S en développement), le navigateur attendait la fin du traitement
// avant de recevoir la réponse de l'envoi (constaté le 2026-11-12). Longueur annoncée et connexion
// fermée : il la tient pour complète dès qu'elle est arrivée.
if ($conteneur->apresReponse()->nombre() > 0 && !function_exists('fastcgi_finish_request')) {
    $reponse->headers->set('Connection', 'close');
    $reponse->headers->set('Content-Length', (string) strlen((string) $reponse->getContent()));
}
$reponse->send();

// La réponse est partie : l'artisan a la main. Le travail inscrit pendant la requête
// (démarrage du traitement d'un audio) continue ici.
if ($conteneur->apresReponse()->nombre() > 0) {
    // Le navigateur fermé ne doit pas couper l'envoi de l'audio à la transcription.
    ignore_user_abort(true);
    set_time_limit(300);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    // La session reste verrouillée jusqu'à la fin du script : sans cette ligne, toute autre page du même
    // artisan (la préparation, l'état demandé chaque seconde) attendait la fin du traitement, une vingtaine
    // de secondes (constaté le 2026-11-12, en local et, par le même verrou, sous PHP-FPM).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $conteneur->apresReponse()->executer();
}
