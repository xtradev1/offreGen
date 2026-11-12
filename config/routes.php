<?php

declare(strict_types=1);

use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\AccueilControleur;
use OffreGen\Http\Controleur\CatalogueControleur;
use OffreGen\Http\Controleur\LigneControleur;
use OffreGen\Http\Controleur\ReglagesControleur;
use OffreGen\Http\Controleur\ConnexionControleur;
use OffreGen\Http\Controleur\EnregistrementControleur;
use OffreGen\Http\Controleur\ImportControleur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Http\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Chaque route déclare son droit : Route::PUBLIC ou Route::ARTISAN. Sans droit, 403.
// Tout POST passe par le contrôle CSRF du noyau. Une route artisan exige une session.
// Les tests parcourent cette liste (tests/Http/NoyauTest.php, tests/Http/IsolationRoutesTest.php).
return static function (Conteneur $c): array {
    $connexion = static fn (): ConnexionControleur => new ConnexionControleur($c->authentification(), $c->twig());
    $accueil = static fn (): AccueilControleur => new AccueilControleur($c);
    $import = static fn (): ImportControleur => new ImportControleur($c);
    $ligne = static fn (): LigneControleur => new LigneControleur($c);
    $catalogue = static fn (): CatalogueControleur => new CatalogueControleur($c);
    $reglages = static fn (): ReglagesControleur => new ReglagesControleur($c);

    return [
        new Route('connexion', '/connexion', ['GET'], static fn (Request $r): Response => $connexion()->afficher($r), Route::PUBLIC),
        new Route('connexion_envoi', '/connexion', ['POST'], static fn (Request $r): Response => $connexion()->soumettre($r), Route::PUBLIC),
        new Route('deconnexion', '/deconnexion', ['POST'], static fn (Request $r): Response => $connexion()->deconnecter($r), Route::ARTISAN),
        new Route('accueil', '/', ['GET'], static fn (Request $r): Response => $accueil()->liste($r), Route::ARTISAN),
        new Route('import', '/import', ['GET'], static fn (Request $r): Response => $import()->afficher($r), Route::ARTISAN),
        new Route('import_envoi', '/import', ['POST'], static fn (Request $r): Response => $import()->envoyer($r), Route::ARTISAN),
        new Route('enregistrement', '/enregistrements/{uuid}', ['GET'], static fn (Request $r, array $p): Response => (new EnregistrementControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('offre', '/offres/{id}', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('offre_entete', '/offres/{id}/modifier', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->entete($r, $p), Route::ARTISAN),
        new Route('offre_entete_enregistrer', '/offres/{id}/modifier', ['POST'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->modifierEntete($r, $p), Route::ARTISAN),
        new Route('offre_document', '/offres/{id}/document', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->document($r, $p), Route::ARTISAN),
        new Route('offre_pdf', '/offres/{id}/pdf', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->pdf($r, $p), Route::ARTISAN),
        new Route('offre_ajout', '/offres/{id}/ajouter', ['GET'], static fn (Request $r, array $p): Response => $ligne()->pageAjout($r, $p), Route::ARTISAN),
        new Route('ligne_ajouter', '/offres/{id}/lignes', ['POST'], static fn (Request $r, array $p): Response => $ligne()->ajouter($r, $p), Route::ARTISAN),
        new Route('ligne', '/offres/{id}/lignes/{ligne}', ['GET'], static fn (Request $r, array $p): Response => $ligne()->page($r, $p), Route::ARTISAN),
        new Route('ligne_corriger', '/offres/{id}/lignes/{ligne}', ['POST'], static fn (Request $r, array $p): Response => $ligne()->corriger($r, $p), Route::ARTISAN),
        new Route('ligne_juste', '/offres/{id}/lignes/{ligne}/juste', ['POST'], static fn (Request $r, array $p): Response => $ligne()->confirmer($r, $p), Route::ARTISAN),
        new Route('ligne_supprimer', '/offres/{id}/lignes/{ligne}/supprimer', ['POST'], static fn (Request $r, array $p): Response => $ligne()->supprimer($r, $p), Route::ARTISAN),
        new Route('ligne_restaurer', '/offres/{id}/lignes/{ligne}/restaurer', ['POST'], static fn (Request $r, array $p): Response => $ligne()->restaurer($r, $p), Route::ARTISAN),
        new Route('catalogue', '/catalogue', ['GET'], static fn (Request $r): Response => $catalogue()->liste($r), Route::ARTISAN),
        new Route('produit_creer', '/catalogue', ['POST'], static fn (Request $r): Response => $catalogue()->creer($r), Route::ARTISAN),
        new Route('produit_nouveau', '/catalogue/nouveau', ['GET'], static fn (Request $r): Response => $catalogue()->nouveau($r), Route::ARTISAN),
        new Route('produit', '/catalogue/{id}', ['GET'], static fn (Request $r, array $p): Response => $catalogue()->fiche($r, $p), Route::ARTISAN),
        new Route('produit_modifier', '/catalogue/{id}', ['POST'], static fn (Request $r, array $p): Response => $catalogue()->modifier($r, $p), Route::ARTISAN),
        new Route('produit_suppression', '/catalogue/{id}/supprimer', ['GET'], static fn (Request $r, array $p): Response => $catalogue()->confirmerSuppression($r, $p), Route::ARTISAN),
        new Route('produit_supprimer', '/catalogue/{id}/supprimer', ['POST'], static fn (Request $r, array $p): Response => $catalogue()->supprimer($r, $p), Route::ARTISAN),
        new Route('reglages', '/reglages', ['GET'], static fn (Request $r): Response => $reglages()->afficher($r), Route::ARTISAN),
        new Route('reglages_enregistrer', '/reglages', ['POST'], static fn (Request $r): Response => $reglages()->enregistrer($r), Route::ARTISAN),
    ];
};
