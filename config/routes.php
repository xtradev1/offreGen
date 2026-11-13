<?php

declare(strict_types=1);

use OffreGen\Compte\Marque;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\AccueilControleur;
use OffreGen\Http\Controleur\CatalogueControleur;
use OffreGen\Http\Controleur\ClientControleur;
use OffreGen\Http\Controleur\LigneControleur;
use OffreGen\Http\Controleur\ReglagesControleur;
use OffreGen\Http\Controleur\ConnexionControleur;
use OffreGen\Http\Controleur\EnregistrementControleur;
use OffreGen\Http\Controleur\FactureControleur;
use OffreGen\Http\Controleur\ImportControleur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Http\Controleur\RangementControleur;
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
    $client = static fn (): ClientControleur => new ClientControleur($c);
    $facture = static fn (): FactureControleur => new FactureControleur($c);
    $ligneFacture = static fn (): LigneControleur => new LigneControleur($c, LigneControleur::FACTURE);

    $routes = [
        new Route('connexion', '/connexion', ['GET'], static fn (Request $r): Response => $connexion()->afficher($r), Route::PUBLIC),
        new Route('connexion_envoi', '/connexion', ['POST'], static fn (Request $r): Response => $connexion()->soumettre($r), Route::PUBLIC),
        new Route('deconnexion', '/deconnexion', ['POST'], static fn (Request $r): Response => $connexion()->deconnecter($r), Route::ARTISAN),
        new Route('accueil', '/', ['GET'], static fn (Request $r): Response => $accueil()->liste($r), Route::ARTISAN),
        new Route('import', '/import', ['GET'], static fn (Request $r): Response => $import()->afficher($r), Route::ARTISAN),
        new Route('import_envoi', '/import', ['POST'], static fn (Request $r): Response => $import()->envoyer($r), Route::ARTISAN),
        new Route('enregistrement', '/enregistrements/{uuid}', ['GET'], static fn (Request $r, array $p): Response => (new EnregistrementControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('reglages_marque_css', '/reglages/marque.css', ['GET'], static fn (Request $r): Response => (new ReglagesControleur($c))->css($r), Route::ARTISAN),
        new Route('reglages_logo', '/reglages/logo', ['GET'], static fn (Request $r): Response => (new ReglagesControleur($c))->logo($r), Route::ARTISAN),
        new Route('reglages_logo_envoyer', '/reglages/logo', ['POST'], static fn (Request $r): Response => (new ReglagesControleur($c))->envoyerLogo($r), Route::ARTISAN),
        new Route('reglages_logo_retirer', '/reglages/logo/retirer', ['POST'], static fn (Request $r): Response => (new ReglagesControleur($c))->retirerLogo($r), Route::ARTISAN),
        new Route('enregistrement_etat', '/enregistrements/{uuid}/etat', ['GET'], static fn (Request $r, array $p): Response => (new EnregistrementControleur($c))->etat($r, $p), Route::ARTISAN),
        new Route('enregistrement_reessayer', '/enregistrements/{uuid}/reessayer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->reessayer($r, $p), Route::ARTISAN),
        new Route('enregistrement_retirer', '/enregistrements/{uuid}/retirer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->retirer($r, $p), Route::ARTISAN),
        new Route('offre_archiver', '/offres/{id}/archiver', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->archiver($r, $p), Route::ARTISAN),
        new Route('offre_supprimer', '/offres/{id}/supprimer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->supprimer($r, $p), Route::ARTISAN),
        new Route('offre_restaurer', '/offres/{id}/restaurer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->restaurer($r, $p), Route::ARTISAN),
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
        new Route('clients', '/clients', ['GET'], static fn (Request $r): Response => $client()->liste($r), Route::ARTISAN),
        new Route('client_creer', '/clients', ['POST'], static fn (Request $r): Response => $client()->creer($r), Route::ARTISAN),
        new Route('client_nouveau', '/clients/nouveau', ['GET'], static fn (Request $r): Response => $client()->nouveau($r), Route::ARTISAN),
        new Route('client', '/clients/{id}', ['GET'], static fn (Request $r, array $p): Response => $client()->afficher($r, $p), Route::ARTISAN),
        new Route('client_modifier', '/clients/{id}', ['POST'], static fn (Request $r, array $p): Response => $client()->modifier($r, $p), Route::ARTISAN),
        new Route('client_supprimer', '/clients/{id}/supprimer', ['POST'], static fn (Request $r, array $p): Response => $client()->supprimer($r, $p), Route::ARTISAN),
        new Route('offre_client', '/offres/{id}/client', ['GET'], static fn (Request $r, array $p): Response => $client()->choixPourOffre($r, $p), Route::ARTISAN),
        new Route('offre_client_choisir', '/offres/{id}/client', ['POST'], static fn (Request $r, array $p): Response => $client()->choisirPourOffre($r, $p), Route::ARTISAN),
        // Factures (sous-chantier 18). Les lignes d'un brouillon passent par LigneControleur, comme celles de l'offre.
        new Route('factures', '/factures', ['GET'], static fn (Request $r): Response => $facture()->liste($r), Route::ARTISAN),
        new Route('offre_facturer', '/offres/{id}/facturer', ['GET'], static fn (Request $r, array $p): Response => $facture()->nouvelle($r, $p), Route::ARTISAN),
        new Route('offre_facturer_creer', '/offres/{id}/facturer', ['POST'], static fn (Request $r, array $p): Response => $facture()->creer($r, $p), Route::ARTISAN),
        new Route('facture', '/factures/{id}', ['GET'], static fn (Request $r, array $p): Response => $facture()->afficher($r, $p), Route::ARTISAN),
        new Route('facture_document', '/factures/{id}/document', ['GET'], static fn (Request $r, array $p): Response => $facture()->document($r, $p), Route::ARTISAN),
        new Route('facture_emission', '/factures/{id}/emettre', ['GET'], static fn (Request $r, array $p): Response => $facture()->confirmationEmission($r, $p), Route::ARTISAN),
        new Route('facture_emettre', '/factures/{id}/emettre', ['POST'], static fn (Request $r, array $p): Response => $facture()->emettre($r, $p), Route::ARTISAN),
        new Route('facture_pdf', '/factures/{id}/pdf', ['GET'], static fn (Request $r, array $p): Response => $facture()->pdf($r, $p), Route::ARTISAN),
        new Route('facture_annulation', '/factures/{id}/annuler', ['GET'], static fn (Request $r, array $p): Response => $facture()->confirmationAnnulation($r, $p), Route::ARTISAN),
        new Route('facture_annuler', '/factures/{id}/annuler', ['POST'], static fn (Request $r, array $p): Response => $facture()->annuler($r, $p), Route::ARTISAN),
        new Route('facture_entete', '/factures/{id}/modifier', ['GET'], static fn (Request $r, array $p): Response => $facture()->entete($r, $p), Route::ARTISAN),
        new Route('facture_entete_enregistrer', '/factures/{id}/modifier', ['POST'], static fn (Request $r, array $p): Response => $facture()->modifierEntete($r, $p), Route::ARTISAN),
        new Route('facture_supprimer', '/factures/{id}/supprimer', ['POST'], static fn (Request $r, array $p): Response => $facture()->supprimer($r, $p), Route::ARTISAN),
        new Route('facture_ajout', '/factures/{id}/ajouter', ['GET'], static fn (Request $r, array $p): Response => $ligneFacture()->pageAjout($r, $p), Route::ARTISAN),
        new Route('facture_ligne_ajouter', '/factures/{id}/lignes', ['POST'], static fn (Request $r, array $p): Response => $ligneFacture()->ajouter($r, $p), Route::ARTISAN),
        new Route('facture_ligne', '/factures/{id}/lignes/{ligne}', ['GET'], static fn (Request $r, array $p): Response => $ligneFacture()->page($r, $p), Route::ARTISAN),
        new Route('facture_ligne_corriger', '/factures/{id}/lignes/{ligne}', ['POST'], static fn (Request $r, array $p): Response => $ligneFacture()->corriger($r, $p), Route::ARTISAN),
        new Route('facture_ligne_juste', '/factures/{id}/lignes/{ligne}/juste', ['POST'], static fn (Request $r, array $p): Response => $ligneFacture()->confirmer($r, $p), Route::ARTISAN),
        new Route('facture_ligne_supprimer', '/factures/{id}/lignes/{ligne}/supprimer', ['POST'], static fn (Request $r, array $p): Response => $ligneFacture()->supprimer($r, $p), Route::ARTISAN),
        new Route('facture_ligne_restaurer', '/factures/{id}/lignes/{ligne}/restaurer', ['POST'], static fn (Request $r, array $p): Response => $ligneFacture()->restaurer($r, $p), Route::ARTISAN),
        new Route('reglages', '/reglages', ['GET'], static fn (Request $r): Response => $reglages()->afficher($r), Route::ARTISAN),
        new Route('reglages_enregistrer', '/reglages', ['POST'], static fn (Request $r): Response => $reglages()->enregistrer($r), Route::ARTISAN),
        new Route('bienvenue_passer', '/bienvenue/passer', ['POST'], static fn (Request $r): Response => $reglages()->passer($r), Route::ARTISAN),
    ];
    // Une adresse fixe par rubrique et par étape de l'assistant : aucun paramètre à vérifier.
    foreach (array_keys(ReglagesControleur::RUBRIQUES) as $nom) {
        $routes[] = new Route("reglages_$nom", "/reglages/$nom", ['GET'], static fn (Request $r): Response => $reglages()->rubrique($r, $nom), Route::ARTISAN);
    }
    foreach (array_keys(Marque::MODELES) as $nom) {
        $routes[] = new Route("reglages_modele_$nom", "/reglages/modeles/$nom", ['GET'], static fn (Request $r): Response => $reglages()->apercuModele($r, $nom), Route::ARTISAN);
    }
    foreach (array_keys(ReglagesControleur::ETAPES) as $nom) {
        $routes[] = new Route("bienvenue_$nom", "/bienvenue/$nom", ['GET'], static fn (Request $r): Response => $reglages()->etape($r, $nom), Route::ARTISAN);
        $routes[] = new Route("bienvenue_{$nom}_enregistrer", "/bienvenue/$nom", ['POST'], static fn (Request $r): Response => $reglages()->enregistrerEtape($r, $nom), Route::ARTISAN);
    }
    return $routes;
};
