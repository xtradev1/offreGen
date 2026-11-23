# SPEC : une application qui ne recharge plus

Chantier `2026-11-23_sans-rechargement`, tâche Origin 504. Les mesures sont dans `AUDIT.md` ; les choix à faire, dans `MAQUETTE-sans-rechargement.html` (D1 à D10). Ce texte suit les réponses conseillées ; il sera corrigé selon les réponses de Xavier.

## Problème

Constaté dans le code et mesuré le 2026-11-23 (`AUDIT.md`) :

- La plupart des gestes de l'offre (« C'est juste », corbeille, « Annuler », prix dicté, ligne, objet, client, représentant, rabais, conditions, pièce, ligne hors catalogue, « Terminé » après un ajout), de la relecture, de la liste des offres et du brouillon de facture envoient un formulaire, puis rechargent la page entière, alors qu'une ligne, une carte ou un total change.
- Les gestes déjà sans rechargement (quantité, prix, option, textes) reçoivent l'offre entière en réponse, deux requêtes à chaque chiffre.
- La fenêtre « + Ligne » retélécharge tout le catalogue à chaque ouverture et à chaque produit ajouté ; ses 274 produits portent chacun un jeton CSRF aléatoire (36,7 Ko compressés au lieu de 9,6 Ko).
- Le serveur de test n'envoie aucune durée de cache pour la feuille de style, le script et les polices, et son ETag suffixé « -gzip » empêche la réponse 304 : un rechargement retélécharge environ 140 Ko de fichiers qui n'ont pas changé.
- Sans réseau, tout geste qui recharge ouvre la page d'erreur du navigateur ; une quantité tapée sans réseau n'est pas renvoyée quand le réseau revient, seulement quand l'artisan quitte l'offre (constat du banc, voir `AUDIT.md`).
- Sur un réseau lent, deux corbeilles touchées à 0,7 s d'écart retirent les deux lignes, mais la page qui arrive n'offre qu'un « Annuler » : la première ligne ne peut plus être remise.
- Sans session (expirée, ou cookie perdu), une quantité tapée n'est pas enregistrée et l'écran reste sur « Enregistrement… » sans jamais le dire.

## Utilisateurs

- L'artisan, au téléphone, sur un chantier, souvent sur un réseau faible ; au PC au bureau.
- L'équipe (commerciaux, administration) au PC : écrans rarement utilisés, hors du gros du chantier.

## Périmètre

1. **Socle** : durée de cache longue et adresse versionnée pour les fichiers statiques, ETag corrigé ; un seul jeton CSRF par page là où une liste en répète un par ligne ; boutons bloqués pendant un envoi.
2. **L'offre sans rechargement** : chaque geste qui ne change qu'un morceau d'écran envoie par fetch ; le serveur répond par les morceaux recalculés (ligne, totaux, carte, état) au lieu d'une redirection ; l'écran change au toucher (affichage anticipé), et revient en arrière avec un message si le serveur refuse. Même chose pour la relecture, la liste des offres, le brouillon de facture. La ligne s'ouvre en fenêtre au téléphone comme au PC.
3. **Ajouter et catalogue** : catalogue et clients gardés dans le téléphone et tenus à jour quand la connexion est bonne ; « + Ligne », le rattachement et la fiche produit s'ouvrent sans requête ; un produit ajouté part en un envoi léger ; après l'enregistrement d'un produit ou d'un client, seule sa ligne revient ; tri des offres et catégories du catalogue dans le navigateur.
4. **Hors réseau** : un service worker sert les fichiers et les pages déjà vues ; les gestes faits sans réseau sur une offre ouverte attendent dans une file rangée dans le téléphone, chacun avec une clé unique que le serveur reconnaît ; un bandeau dit l'état du réseau et des envois ; tout est effacé à la déconnexion.
5. **PDF et envoi** : « Envoyer » partage le PDF déjà affiché ; « envoyée » est notée sans rechargement ; le PDF est gardé au serveur tant que l'offre ne change pas.

## Hors périmètre

- Une application JavaScript complète (rendu des écrans dans le navigateur) : les calculs (TVA, rabais, arrondi, options) resteraient en double.
- Toute bibliothèque qui donne la forme des écrans (htmx, Turbo) : `CODING_RULES.md` les écarte, sauf décision contraire de Xavier (D9).
- Les gestes où le rechargement sert : connexion, déconnexion, mot de passe, démo, passage à l'essai, « Créer la facture », « Émettre », annuler une facture. Ils profitent du socle, sans plus.
- Les écrans de l'équipe (administration) : socle seulement.
- La dictée : déjà sans rechargement, à l'abri dans le téléphone.
- Une offre ou une facture créée sans réseau : il faut le serveur (transcription, numéro).

## Critères de succès

Mesurés par le banc du chantier (`banc/`), réseau de chantier simulé (500 ms par requête, 400 et 200 kbit/s), et recettés à la main sur le site de test :

- « C'est juste », corbeille, « Annuler », prix dicté, option : l'écran change en moins de 100 ms ; une seule requête ; moins de 3 Ko reçus ; l'écran ne saute pas.
- Ouvrir une offre : aucun fichier statique redemandé quand le navigateur les a déjà ; moins de 15 Ko reçus.
- « + Ligne » : la fenêtre s'ouvre sans requête quand le catalogue est dans le téléphone ; ajouter trois produits puis « Terminé » : trois requêtes au plus, aucun rechargement.
- Sans réseau, sur une offre ouverte : aucune page d'erreur du navigateur ; chaque geste est gardé, puis appliqué une seule fois au retour du réseau, même si l'envoi est rejoué.
- Deux gestes touchés vite, sur réseau lent : chacun garde son « Annuler ».
- Session perdue pendant un envoi : l'écran le dit, le geste attend la reconnexion, rien n'est perdu.
- Sans JavaScript, chaque formulaire marche encore comme aujourd'hui.
- La suite de tests reste verte ; un test par réponse en morceaux (droits, CSRF, isolation entre artisans comprises).

## Contraintes

- `CODING_RULES.md` : pas de cadre applicatif, PHP lisible, accès aux données par `src/Donnees/` avec l'identifiant de l'artisan, CSRF sur chaque envoi, droits refusés par défaut, test d'isolation route par route, recette à la souris.
- La CSP refuse le JavaScript en ligne : tout passe par des fichiers de `public/js/`. Le service worker est un fichier de `public/` servi en JavaScript.
- `app.js` est modifié par d'autres chantiers : travailler dans une copie à soi, sur la tête de `chantier-voix-offre`, en petits commits.
- Les règles de calcul restent au serveur ; le téléphone n'affiche qu'un total provisoire (D7).
- Ce qui existe et se réutilise : `fichesEnFenetre` (fenêtres du bas), `corrections` (envois au fil de la frappe), `brancherAuto` (réponses JSON), `abri` (rangement dans le téléphone par compte, IndexedDB), la clé de dictée que le serveur reconnaît (`enregistrement.cle`).

## Risques

- Safari peut effacer après 7 jours sans visite ce que le site a rangé dans le téléphone : la file d'envoi doit partir au plus tôt et dire quand elle attend.
- Deux appareils sur la même offre, dont un hors réseau : le dernier envoi gagne ; à afficher.
- Une session expirée pendant que des envois attendent : reconnexion, puis jeton neuf, sans perdre la file.
- Données de clients rangées dans le téléphone : effacement à la déconnexion, rangement par compte, mention dans la politique de confidentialité (LPD).
- Partage du PDF sur iPhone après une attente réseau : refus possible du navigateur (hypothèse, à vérifier).
- Estimation : environ 47 h (lots 1 à 5, revue et recette), contre 16 h prévues dans la tâche 504.
