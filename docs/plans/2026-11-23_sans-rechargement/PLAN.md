# PLAN : une application qui ne recharge plus

Chantier `2026-11-23_sans-rechargement`. Réponses de Xavier du 2026-11-23 : D1 à D10 comme conseillé (voir `ETAT.md`, Décisions). Ordre des lots : 1 à 5, puis revue, banc rejoué, comparatif avant-après, déploiement sur le test (accord de Xavier du 2026-11-23, s'il n'y a pas de gros problème).

## Contrat technique, de bout en bout

### Fichiers statiques (lot 1, D1)

- Fonction Twig `fichier('/css/app.css')` : rend `/css/app.css?v=<empreinte du contenu>` (12 caractères, xxh3). Tous les gabarits l'emploient pour leurs feuilles de style, scripts et images. L'empreinte suit le contenu : un style changé change l'adresse, sans rien à faire à la main (commentaire de Xavier sur D1).
- `public/.htaccess` : adresse avec `?v=` : `Cache-Control: public, max-age=31536000, immutable` ; polices et images sans version : 7 jours ; feuille de style et script sans version : `no-cache` ; `/sw.js` : `no-cache` ; `FileETag None` (le 304 passe par `Last-Modified`, prouvé sur le test).
- Le routeur du banc (`banc/routeur.php`) imite ces règles pour la mesure d'après.

### Jeton CSRF (lot 1)

`Garde::jeton()` masque le jeton une fois par requête : tous les formulaires d'une page portent la même valeur, la page suivante un autre masque (la parade contre BREACH tient à chaque réponse). La compression n'a plus 277 valeurs aléatoires à porter.

### Morceaux de page (lot 2, D2, D3, D9)

Requête : le formulaire existant, envoyé par fetch, avec
- `X-Morceaux: id1 id2 …` : les identifiants des éléments de la page à renvoyer ;
- `X-Morceaux-Page: /offres/3` (facultatif) : la page dont on veut les morceaux, à la place de la cible de la redirection (l'ajout depuis la fenêtre « + Ligne ») ;
- `X-Cle-Envoi: <identifiant unique>` (lot 4).

Serveur, dans `Noyau` (aucun contrôleur ne change) :
1. l'action tourne comme aujourd'hui ;
2. si elle répond par une redirection vers une adresse du site, le noyau rend cette page par une requête interne, même session (les messages laissés par l'action, « Annuler » compris, y paraissent) ;
3. `Http\Morceaux` en extrait les éléments demandés par leur `id` (`Dom\HTMLDocument`), et toujours les messages (`.app > .snack`, `[data-focus-ligne]`) ;
4. réponse 200 : les morceaux à la suite, un `<template data-absent="id">` par élément disparu, en-tête `X-Morceaux-Adresse` (adresse finale, avec son ancre).

Autres cas : 422 (formulaire refusé) et 409 passent tels quels ; session perdue : 401 avec `X-Morceaux-Session: perdue` au lieu du renvoi à la connexion ; jeton refusé : 403 avec `X-Morceaux-Jeton: refuse`. Sans JavaScript, rien ne change : formulaires et 303.

Client, `public/js/fluide.js` (nouveau fichier, chargé avant `app.js` : moins de conflits avec les autres chantiers) : `fluide.envoyer(form, {morceaux, anticiper, defaire})` envoie, applique (remplace chaque élément par son id, retire les absents, pose les messages en pile), défait le geste si le serveur refuse, met le geste en file s'il n'y a pas de réseau (lot 4). Boutons et gestes d'une même ligne : un envoi à la fois, le suivant attend.

Identifiants des morceaux :
- offre et brouillon de facture : `l<id>` (déjà là), `lignes`, `entete-offre`, `totaux`, `options`, `conditions`, `total-fixe`, `renvoi`, `actions-offre`, `pc-<id>` (carte de la colonne PC) ;
- liste des offres : `o<id>` (carte), `compte-offres`, `archives` ;
- catalogue et clients : `p<id>`, `c<id>`, `clist`.

Gestes anticipés (l'écran change au toucher, l'envoi suit) :

| Geste | Au toucher | Morceaux demandés | Refus |
|---|---|---|---|
| « C'est juste » | l'étiquette part | la ligne, l'en-tête, la barre du bas | l'étiquette revient, message |
| Quantité, prix | montant de la ligne et total provisoires | la ligne et ses variantes (offre par pièce : `lignes`), totaux, barre | la valeur revient |
| Corbeille, glisser | la ligne part, « Annuler » à elle | totaux, barre, variantes, `options` | la ligne revient |
| « Annuler » | la ligne revient à sa place | la ligne, totaux, barre | elle repart |
| Prix dicté, option retenue | rien (une case cochée reste cochée) | `lignes`, `options`, totaux, barre | la case revient |
| Fenêtres (client, représentant, objet, rabais, conditions, pièce, ligne) | la fenêtre se ferme à la réponse | en-tête, `lignes`, `options`, totaux, conditions, barre | la fenêtre montre le refus |
| Archiver, supprimer, restaurer une offre | la carte part (ou revient), « Annuler » à elle | `compte-offres`, `archives` | la carte revient |
| Relecture, « C'est juste » | la ligne suivante, déjà dans la page | aucun | retour à la ligne refusée |

### Ajouter et catalogue (lot 3, D4)

- La fenêtre « + Ligne » se précharge en arrière-plan sur l'offre ouverte : après le chargement, quand le navigateur est libre, priorité basse, jamais pendant un envoi de l'artisan (commentaire de Xavier sur D4). Elle s'ouvre sans requête. Un produit touché part seul (`X-Morceaux-Page` : la ligne arrive dans l'offre derrière) ; la fenêtre reste et dit ce qui est entré ; « Terminé » la ferme, sans rechargement.
- Chaque page porte la version du catalogue (`data-catalogue` : empreinte des produits) : version changée, préchargement refait.
- Tri des offres et catégorie du catalogue : dans le navigateur, sans requête.
- Produit ou client enregistré dans sa fenêtre : sa ligne seule revient (`p<id>`, `c<id>`), la liste entière seulement pour un nouveau.

### Hors réseau (lot 4, D5, D6, D7)

- `public/sw.js`, portée `/` : fichiers versionnés en cache d'abord ; pages de l'artisan réseau d'abord (6 s au plus), puis la copie gardée, marquée « hors réseau » ; jamais la connexion, l'administration, les PDF, l'état d'une dictée. Cache rangé par compte.
- Ce qui est gardé (D6) : chaque page vue, puis, à l'ouverture de l'application quand la connexion est bonne et que le navigateur est libre : la liste, le catalogue, les clients, les offres modifiées depuis 30 jours (seulement celles qui ont changé depuis la copie), une à la fois (D4).
- Se connecter demande le réseau ; connecté, puis réseau perdu : les offres gardées restent lisibles et modifiables (commentaire de Xavier sur D5).
- File d'envoi (IndexedDB, par compte) : chaque geste fait sans réseau attend, avec sa clé unique ; envoyé dans l'ordre au retour du réseau, avec le jeton du moment ; refusé : message ; session perdue : attente de la reconnexion, rien n'est perdu.
- Table `envoi` (artisan, clé, adresse, reçu le) : une clé déjà vue ne fait rien deux fois et renvoie les morceaux de la même page.
- Bandeau : hors réseau, changements en attente, envoi en cours.
- Total provisoire (D7) : estimé dans le téléphone par différence avec le dernier total du serveur, marqué « provisoire », remplacé par celui du serveur au retour du réseau.
- Déconnexion : le script efface caches, file et dictées du compte ; la réponse porte `Clear-Site-Data`.

### PDF et envoi (lot 5, D8)

- PDF gardé au serveur tant que son contenu ne change pas (`var/cache/pdf/<artisan>/<empreinte du HTML>.pdf`), avec un ETag : le navigateur revalide et reçoit un 304.
- Aperçu : les octets du PDF lus une fois, dessinés par PDF.js et gardés pour le partage ; « Envoyer » partage sans nouvelle requête (le geste de l'artisan reste valable pour le téléphone) ; « envoyée » notée par fetch, l'écran le dit, sans rechargement.

## Lots

| Lot | Contenu | Tests (PHPUnit) | Banc |
|---|---|---|---|
| 1 | `fichier()`, `.htaccess`, jeton par requête, boutons bloqués pendant l'envoi | adresse qui suit le contenu ; une valeur de jeton par page, valide, différente à la page suivante ; en-têtes du `.htaccess` relus | poids d'une offre ouverte |
| 2 | `Morceaux`, noyau, ids des gabarits, `fluide.js`, gestes de l'offre, de la relecture, de la liste, du brouillon de facture, fenêtres sans rechargement | morceaux d'une correction, d'une suppression (« Annuler » dans les messages), d'un refus 422, 409 ; session perdue 401 ; jeton 403 ; isolation : les morceaux d'une offre d'un autre artisan sont refusés | 47 gestes |
| 3 | préchargement de « + Ligne », ajout par morceaux, version du catalogue, tri et catégorie dans le navigateur, ligne seule après un produit ou un client | `X-Morceaux-Page` hors du site refusé ; version du catalogue qui change avec un produit | ajout de trois produits |
| 4 | service worker, préchargement, file d'envoi, table `envoi`, bandeau, total provisoire, déconnexion | clé rejouée : une seule ligne ajoutée ; clé d'un autre artisan ignorée ; `Clear-Site-Data` à la déconnexion | coupures (`banc/hors-ligne.js`) |
| 5 | PDF gardé, ETag, partage du PDF affiché, envoyée sans rechargement | même offre : même PDF, 304 ; offre changée : autre PDF | « Générer », « Envoyer » |

Recette par l'interface (Playwright), sur le banc local puis sur le site de test après le déploiement.

## Hors plan

- Une offre ou une facture créée sans réseau, la dictée (déjà à l'abri), les écrans de l'équipe : voir `SPEC.md`.
- La mention des données gardées dans le téléphone dans la politique de confidentialité : texte à écrire par Xavier (chantier juridique).

## Écarts au plan, décidés en route (2026-11-23)

Après la revue (`REVUE.md`) et le banc :

- Les actes qui mènent ailleurs une fois faits (`data-sortir` : « Créer la facture », le client puis l'aperçu) n'entrent jamais dans la file : la confirmation du serveur reste obligatoire, comme la SPEC le veut.
- Chaque geste porte son compte (`X-Compte`) : un autre compte en session reçoit un 409 et rien n'est fait.
- Les messages d'une action (« Annuler », un refus) sont rangés avec sa clé et rendus au geste rejoué ; une réservation de clé abandonnée plus de 3 minutes est libérée.
- Aucun morceau demandé : la page n'est pas rendue, ses messages restent pour la page suivante (relecture, geste rejoué d'un autre écran).
- Version du catalogue (`data-catalogue`) : remplacée par la fenêtre « + Ligne » refaite en arrière-plan après chaque usage.
- PDF : gardés par le service worker pour le compte (revalidés par leur ETag, ouverts sans réseau), 14 jours au plus comme les pages ; `no-store` pour le navigateur ; au serveur dans `var/cache/offres-pdf` (dompdf range ses polices dans `var/cache/pdf`).
- Déconnexion : pages, PDF et file du compte effacés ; les dictées à l'abri restent (elles partent quand leur offre est prête) ; `Clear-Site-Data` « cache » seulement.
- Le bandeau du réseau se pose au-dessus des boutons du bas, à la place du premier bandeau « Annuler ».
