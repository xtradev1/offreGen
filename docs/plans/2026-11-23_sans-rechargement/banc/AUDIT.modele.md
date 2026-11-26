# Audit : rechargements, asynchrone, connexion faible

Relevé du 2026-11-23, chantier `2026-11-23_sans-rechargement`. Toutes les durées viennent du banc (`banc/`), aucune n'est estimée sauf mention contraire. La maquette (`MAQUETTE-sans-rechargement.html`) reprend ces chiffres avec les captures et les choix à faire.

## En bref

@@BREF@@

## Méthode

- **Code lu.** Les 90 gabarits (372 éléments interactifs relevés par `banc/elements.py`), `config/routes.php` (130 routes, une soixantaine d'envois), tout `public/js/app.js` (2 113 lignes) et les contrôleurs des gestes mesurés.
- **Environnement.** Copie `~/offreGen-fluidite` (tête de `chantier-voix-offre`, `081218c`), base `offregen_fluidite_vue`, remise à l'instantané avant chaque mesure. Données inventées : compte de démo Peintre, 274 produits, 42 clients, offre 3 de 33 lignes (4 à vérifier, 2 sans prix, 2 prix dictés, une option, une variante, une ligne libre), offre 4 envoyée, offre 5 archivée.
- **Serveur comme le test.** `php -S` derrière `banc/routeur.php`, qui imite l'Apache du site de test relevé par curl : pages et fichiers compressés, aucune durée de cache, ETag suffixé « -gzip » (un `If-None-Match` reçoit tout le fichier). Contrôle : `app.css` pèse 20 607 octets compressés en local comme sur le test.
- **Navigateur.** Chromium de Playwright, écran de téléphone 390 x 844, un clic par geste, comme l'artisan.
- **Réseaux simulés** (latence ajoutée à chaque requête, débits) : local ; fibre 25 ms (aller-retour mesuré vers le test depuis le poste de Xavier) ; 4G 100 ms, 9 et 3 Mbit/s ; chantier 500 ms, 400 et 200 kbit/s.
- **Deux caches.** Périmé : chaque page redemande feuille de style, script, polices et logos (ce que fait le test quand la fraîcheur devinée par le navigateur est passée ; elle dépend de l'âge du dernier déploiement). Gardé : rien n'est redemandé (ce que donnerait le lot 1).
- **Temps mesurés.** « Affiché » : du toucher au premier affichage de la nouvelle page (ou à l'écran mis à jour pour un geste sans rechargement). « Tout chargé » : jusqu'à la fin du chargement (polices comprises). « Sans rechargement » : la même requête envoyée par fetch sans suivre la redirection ; le serveur fait le même travail, il manque la réponse utile (ligne et totaux, 1 à 2 Ko compressés, estimés).
- **Limites.** Pas de vrai téléphone (son processeur rend le rechargement plus coûteux qu'ici, l'envoi seul presque pas), pas de Safari, pas de perte de paquets (les chiffres du chantier sont optimistes). Une passe par geste et par réseau : la latence simulée rend les mesures stables (écart constaté entre deux passes : quelques dizaines de millisecondes). Les écrans de l'équipe ont été lus sans être chronométrés. Le banc compte 346 octets pour le worker de PDF.js, qu'il charge mal : le fichier pèse 379 Ko compressés (mesuré sur le disque), ajoutés à la main à « Générer l'offre » et « Créer la facture » quand le cache est périmé.

## Constats

@@CONSTATS@@

## Tous les gestes

« req. » : requêtes au serveur. Cache périmé. « sans rechargement » : la même requête envoyée seule.

@@TABLEAU@@

## Ce qui garde un rechargement, et pourquoi

- Connexion, mot de passe oublié, choix du mot de passe, changement de mot de passe : la session change, partir d'une page propre est plus sûr.
- Déconnexion : la session est détruite ; avec le hors réseau, elle doit aussi vider ce que le téléphone garde.
- Démo, passage à l'essai, retour à son espace : le compte change.
- « Créer la facture », « Émettre », annuler une facture : actes définitifs, le numéro vient du serveur. La réponse du serveur doit arriver avant d'afficher la suite ; le rechargement qui la suit pourrait disparaître, pour un gain faible.
- Changer d'écran (ouvrir une offre, un onglet, la relecture, l'aperçu PDF) : une page entière est normale ; le lot 1 la rend légère, le lot 4 la rend lisible sans réseau.

## Annexes

- Relevé brut des 372 éléments interactifs des gabarits (formulaires, liens, champs envoyés seuls), gabarit par gabarit : `banc/elements.txt`. Les 61 gestes du tableau les regroupent.
- Mesures brutes : `banc/resultats.jsonl` (une ligne par mesure, requêtes détaillées) ; synthèse : `banc/synthese.json`.
- Coupure de réseau et réseau lent : `banc/hors-ligne.json`, captures dans `MAQUETTE-sans-rechargement-img/`.
- Dictée : `banc/dictees.jsonl`.
