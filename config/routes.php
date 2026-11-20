<?php

declare(strict_types=1);

use OffreGen\Compte\Marque;
use OffreGen\Donnees\EtatBase;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Controleur\AccueilControleur;
use OffreGen\Http\Controleur\AdminControleur;
use OffreGen\Http\Controleur\AdresseControleur;
use OffreGen\Http\Controleur\CatalogueControleur;
use OffreGen\Http\Controleur\ClientControleur;
use OffreGen\Http\Controleur\LigneCatalogueControleur;
use OffreGen\Http\Controleur\LigneControleur;
use OffreGen\Http\Controleur\ReglagesControleur;
use OffreGen\Http\Controleur\ConnexionControleur;
use OffreGen\Http\Controleur\EnregistrementControleur;
use OffreGen\Http\Controleur\FactureControleur;
use OffreGen\Http\Controleur\ImportControleur;
use OffreGen\Http\Controleur\MotDePasseControleur;
use OffreGen\Http\Controleur\OffreControleur;
use OffreGen\Http\Controleur\PieceControleur;
use OffreGen\Http\Controleur\RangementControleur;
use OffreGen\Http\Controleur\RelectureControleur;
use OffreGen\Http\Controleur\SanteControleur;
use OffreGen\Http\Controleur\VosPrixControleur;
use OffreGen\Http\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Chaque route déclare son droit : Route::PUBLIC, Route::ARTISAN, ou pour l'administration Route::EQUIPE,
// Route::MODERATION, Route::ADMIN. Sans droit, 403.
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
    $motDePasse = static fn (): MotDePasseControleur => new MotDePasseControleur($c);
    $admin = static fn (): AdminControleur => new AdminControleur($c);

    $routes = [
        // État pour la sonde d'exposition, après chaque déploiement (docs/DEPLOIEMENT.md). Lecture seule.
        new Route('sante', '/sante', ['GET'], static fn (): Response => (new SanteControleur(
            static fn (): EtatBase => new EtatBase($c->base()),
            $c->racine() . '/REVISION',
            $c->racine() . '/migrations',
        ))->etat(), Route::PUBLIC),
        new Route('connexion', '/connexion', ['GET'], static fn (Request $r): Response => $connexion()->afficher($r), Route::PUBLIC),
        new Route('connexion_envoi', '/connexion', ['POST'], static fn (Request $r): Response => $connexion()->soumettre($r), Route::PUBLIC),
        new Route('deconnexion', '/deconnexion', ['POST'], static fn (Request $r): Response => $connexion()->deconnecter($r), Route::ARTISAN),
        // Mot de passe oublié et lien d'accès d'un nouveau compte (P1, P3, A3, 2026-11-18). « oubli » avant {jeton}.
        new Route('mot_de_passe_oubli', '/mot-de-passe/oubli', ['GET'], static fn (Request $r): Response => $motDePasse()->oubli($r), Route::PUBLIC),
        new Route('mot_de_passe_demander', '/mot-de-passe/oubli', ['POST'], static fn (Request $r): Response => $motDePasse()->demander($r), Route::PUBLIC),
        new Route('mot_de_passe_lien', '/mot-de-passe/{jeton}', ['GET'], static fn (Request $r, array $p): Response => $motDePasse()->lien($r, $p), Route::PUBLIC),
        new Route('mot_de_passe_choisir', '/mot-de-passe/{jeton}', ['POST'], static fn (Request $r, array $p): Response => $motDePasse()->choisir($r, $p), Route::PUBLIC),
        // Changer son mot de passe (P4) : les autres appareils sont déconnectés.
        new Route('reglages_mot_de_passe', '/reglages/mot-de-passe', ['GET'], static fn (Request $r): Response => $motDePasse()->reglages($r), Route::ARTISAN),
        new Route('reglages_mot_de_passe_changer', '/reglages/mot-de-passe', ['POST'], static fn (Request $r): Response => $motDePasse()->changer($r), Route::ARTISAN),
        // « / » : la liste des offres pour l'artisan connecté. Le visiteur sans session y voit la page de présentation de Dicta
        // (Xavier, 2026-11-20 : « mets la page à / maintenant »), statique : aucune donnée lue ni écrite. « ?etat=avant »
        // montre l'état d'avant l'ouverture des inscriptions.
        new Route('accueil', '/', ['GET'], static fn (Request $r): Response => $accueil()->liste($r), Route::ARTISAN,
            static fn (Request $r): Response => new Response($c->twig()->render(
                'presentation.html.twig',
                ['avant' => $r->query->getString('etat') === 'avant'],
            ))),
        // « Plus » (décision D2, 2026-11-15) : catalogue, réglages, import, déconnexion. Lecture seule.
        new Route('plus', '/plus', ['GET'], static fn (): Response => new Response($c->twig()->render('plus.html.twig')), Route::ARTISAN),
        new Route('import', '/import', ['GET'], static fn (Request $r): Response => $import()->afficher($r), Route::ARTISAN),
        new Route('import_envoi', '/import', ['POST'], static fn (Request $r): Response => $import()->envoyer($r), Route::ARTISAN),
        new Route('enregistrement', '/enregistrements/{uuid}', ['GET'], static fn (Request $r, array $p): Response => (new EnregistrementControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('reglages_marque_css', '/reglages/marque.css', ['GET'], static fn (Request $r): Response => (new ReglagesControleur($c))->css($r), Route::ARTISAN),
        new Route('reglages_logo', '/reglages/logo', ['GET'], static fn (Request $r): Response => (new ReglagesControleur($c))->logo($r), Route::ARTISAN),
        new Route('reglages_logo_envoyer', '/reglages/logo', ['POST'], static fn (Request $r): Response => (new ReglagesControleur($c))->envoyerLogo($r), Route::ARTISAN),
        new Route('reglages_logo_retirer', '/reglages/logo/retirer', ['POST'], static fn (Request $r): Response => (new ReglagesControleur($c))->retirerLogo($r), Route::ARTISAN),
        // Aide à la saisie d'une adresse (sous-chantier 18) : rien n'est écrit.
        new Route('adresses_localites', '/adresses/localites', ['GET'], static fn (Request $r): Response => (new AdresseControleur($c))->localites($r), Route::ARTISAN),
        new Route('adresses_suggestions', '/adresses/suggestions', ['GET'], static fn (Request $r): Response => (new AdresseControleur($c))->suggestions($r), Route::ARTISAN),
        new Route('adresses_verification', '/adresses/verification', ['GET'], static fn (Request $r): Response => (new AdresseControleur($c))->verification($r), Route::ARTISAN),
        new Route('enregistrement_etat', '/enregistrements/{uuid}/etat', ['GET'], static fn (Request $r, array $p): Response => (new EnregistrementControleur($c))->etat($r, $p), Route::ARTISAN),
        new Route('enregistrement_reessayer', '/enregistrements/{uuid}/reessayer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->reessayer($r, $p), Route::ARTISAN),
        new Route('enregistrement_retirer', '/enregistrements/{uuid}/retirer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->retirer($r, $p), Route::ARTISAN),
        new Route('offre_archiver', '/offres/{id}/archiver', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->archiver($r, $p), Route::ARTISAN),
        new Route('offre_supprimer', '/offres/{id}/supprimer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->supprimer($r, $p), Route::ARTISAN),
        new Route('offre_restaurer', '/offres/{id}/restaurer', ['POST'], static fn (Request $r, array $p): Response => (new RangementControleur($c))->restaurer($r, $p), Route::ARTISAN),
        new Route('offre', '/offres/{id}', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('offre_entete', '/offres/{id}/modifier', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->entete($r, $p), Route::ARTISAN),
        new Route('offre_entete_enregistrer', '/offres/{id}/modifier', ['POST'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->modifierEntete($r, $p), Route::ARTISAN),
        // Renommer une pièce de l'offre (sous-chantier 28, P5).
        new Route('offre_piece', '/offres/{id}/pieces', ['GET'], static fn (Request $r, array $p): Response => (new PieceControleur($c))->page($r, $p), Route::ARTISAN),
        new Route('offre_piece_renommer', '/offres/{id}/pieces', ['POST'], static fn (Request $r, array $p): Response => (new PieceControleur($c))->renommer($r, $p), Route::ARTISAN),
        new Route('offre_document', '/offres/{id}/document', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->document($r, $p), Route::ARTISAN),
        new Route('offre_pdf', '/offres/{id}/pdf', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->pdf($r, $p), Route::ARTISAN),
        // Ligne et catalogue : créer le produit, rattacher à un produit (décision D6, 2026-11-15).
        new Route('ligne_creer_produit', '/offres/{id}/lignes/{ligne}/produit', ['POST'], static fn (Request $r, array $p): Response => (new LigneCatalogueControleur($c))->creerProduit($r, $p), Route::ARTISAN),
        new Route('ligne_rattacher_choix', '/offres/{id}/lignes/{ligne}/rattacher', ['GET'], static fn (Request $r, array $p): Response => (new LigneCatalogueControleur($c))->choix($r, $p), Route::ARTISAN),
        new Route('ligne_rattacher', '/offres/{id}/lignes/{ligne}/rattacher', ['POST'], static fn (Request $r, array $p): Response => (new LigneCatalogueControleur($c))->rattacher($r, $p), Route::ARTISAN),
        // Prix dicté à côté du catalogue (Xavier, 2026-11-17) : « Prendre », puis « Mettre aussi au catalogue ? ».
        new Route('ligne_prix_dicte', '/offres/{id}/lignes/{ligne}/prix-dicte', ['POST'], static fn (Request $r, array $p): Response => (new LigneCatalogueControleur($c))->prendrePrixDicte($r, $p), Route::ARTISAN),
        new Route('ligne_prix_dicte_catalogue', '/offres/{id}/lignes/{ligne}/prix-dicte/catalogue', ['POST'], static fn (Request $r, array $p): Response => (new LigneCatalogueControleur($c))->prixDicteAuCatalogue($r, $p), Route::ARTISAN),
        // « Générer l'offre » : le PDF dans l'application (décision D7 A, 2026-11-15). Lecture seule.
        new Route('offre_apercu', '/offres/{id}/apercu', ['GET'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->apercu($r, $p), Route::ARTISAN),
        // Le partage du téléphone a réussi : l'offre est notée envoyée (décision D8, 2026-11-15).
        new Route('offre_envoyee', '/offres/{id}/envoyee', ['POST'], static fn (Request $r, array $p): Response => (new OffreControleur($c))->envoyee($r, $p), Route::ARTISAN),
        // Une facture émise : son PDF dans l'application, et « Envoyer » (D7 A, D8).
        new Route('facture_apercu', '/factures/{id}/apercu', ['GET'], static fn (Request $r, array $p): Response => $facture()->apercu($r, $p), Route::ARTISAN),
        // Relecture au pouce, une ligne à la fois (décision D5, 2026-11-15).
        new Route('offre_relire', '/offres/{id}/relire', ['GET'], static fn (Request $r, array $p): Response => (new RelectureControleur($c))->afficher($r, $p), Route::ARTISAN),
        new Route('offre_relire_valider', '/offres/{id}/relire/{ligne}', ['POST'], static fn (Request $r, array $p): Response => (new RelectureControleur($c))->valider($r, $p), Route::ARTISAN),
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
        // « Vos prix » : les produits sans prix, un à la fois (chantier des métiers, 2026-11-19). Chemins fixes d'abord.
        new Route('vos_prix', '/vos-prix', ['GET'], static fn (Request $r): Response => (new VosPrixControleur($c))->afficher($r), Route::ARTISAN),
        new Route('vos_prix_annuler', '/vos-prix/annuler', ['POST'], static fn (Request $r): Response => (new VosPrixControleur($c))->annuler($r), Route::ARTISAN),
        new Route('vos_prix_plus_tard', '/vos-prix/plus-tard', ['POST'], static fn (Request $r): Response => (new VosPrixControleur($c))->plusTard($r), Route::ARTISAN),
        new Route('vos_prix_agir', '/vos-prix/{id}', ['POST'], static fn (Request $r, array $p): Response => (new VosPrixControleur($c))->agir($r, $p), Route::ARTISAN),
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
        // L'aperçu de la facture que « Créer la facture » créerait (maquette 25, E1) : rien n'est écrit.
        new Route('offre_facturer_apercu', '/offres/{id}/facturer/apercu', ['GET'], static fn (Request $r, array $p): Response => $facture()->apercuNouvelle($r, $p), Route::ARTISAN),
        new Route('offre_facturer_creer', '/offres/{id}/facturer', ['POST'], static fn (Request $r, array $p): Response => $facture()->creer($r, $p), Route::ARTISAN),
        new Route('facture', '/factures/{id}', ['GET'], static fn (Request $r, array $p): Response => $facture()->afficher($r, $p), Route::ARTISAN),
        new Route('facture_document', '/factures/{id}/document', ['GET'], static fn (Request $r, array $p): Response => $facture()->document($r, $p), Route::ARTISAN),
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
    // Administration (docs/plans/2026-11-19_admin-comptes/PLAN-lot-A.md) : 404 pour qui n'a pas le rôle.
    $routes[] = new Route('admin', '/admin', ['GET'], static fn (Request $r): Response => $admin()->accueil($r), Route::EQUIPE);
    $routes[] = new Route('admin_tableau', '/admin/tableau', ['GET'], static fn (): Response => $admin()->ossature('tableau', 'Tableau de bord'), Route::ADMIN);
    $routes[] = new Route('admin_aider', '/admin/a-aider', ['GET'], static fn (): Response => $admin()->ossature('aider', 'À aider'), Route::MODERATION);
    $routes[] = new Route('admin_suivi', '/admin/suivi', ['GET'], static fn (): Response => $admin()->ossature('suivi', 'Suivi'), Route::EQUIPE);
    $routes[] = new Route('admin_artisans', '/admin/artisans', ['GET'], static fn (Request $r): Response => $admin()->artisans($r), Route::EQUIPE);
    $routes[] = new Route('admin_artisan', '/admin/artisans/{id}', ['GET'], static fn (Request $r, array $p): Response => $admin()->artisan($p), Route::EQUIPE);
    $routes[] = new Route('admin_artisan_offres', '/admin/artisans/{id}/offres', ['POST'], static fn (Request $r, array $p): Response => $admin()->artisanOffres($r, $p), Route::EQUIPE);
    // L'équipe : « nouveau » avant {id}.
    $routes[] = new Route('admin_equipe', '/admin/equipe', ['GET'], static fn (): Response => $admin()->equipe(), Route::ADMIN);
    $routes[] = new Route('admin_membre_creer', '/admin/equipe', ['POST'], static fn (Request $r): Response => $admin()->membreCreer($r), Route::ADMIN);
    $routes[] = new Route('admin_membre_nouveau', '/admin/equipe/nouveau', ['GET'], static fn (): Response => $admin()->membreNouveau(), Route::ADMIN);
    $routes[] = new Route('admin_membre', '/admin/equipe/{id}', ['GET'], static fn (Request $r, array $p): Response => $admin()->membre($p), Route::ADMIN);
    $routes[] = new Route('admin_membre_enregistrer', '/admin/equipe/{id}', ['POST'], static fn (Request $r, array $p): Response => $admin()->membreEnregistrer($r, $p), Route::ADMIN);
    $routes[] = new Route('admin_membre_acces', '/admin/equipe/{id}/acces', ['POST'], static fn (Request $r, array $p): Response => $admin()->membreAcces($r, $p), Route::ADMIN);
    $routes[] = new Route('admin_membre_role', '/admin/equipe/{id}/role', ['POST'], static fn (Request $r, array $p): Response => $admin()->membreRole($r, $p), Route::ADMIN);
    $routes[] = new Route('admin_plus', '/admin/plus', ['GET'], static fn (): Response => $admin()->plus(), Route::EQUIPE);
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
