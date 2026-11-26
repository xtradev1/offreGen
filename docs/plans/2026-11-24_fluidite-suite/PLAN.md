# PLAN : fluidité, suite de la recette du 24.11

Réponses de Xavier du 2026-11-24 (texte collé, mots exacts dans `ETAT.md`) : D2 A, D3 A, D4 A, D5 « Valider ». D1 sans choix, avec une consigne : « si l'interface apparait cela doit fonctionner mais l'interface ne doit pas apparaitre en 2s c'est trop lent trouve une autre solution ». Ordre : SR-6, SR-1, MP-A14, MP-A19, puis revue et recette au banc. Pas de déploiement sans son accord.

## 1. SR-6 : « Envoyer » ne regarde que l'offre affichée

- `fluide.js` : chaque geste garde l'offre où il a été fait (`item.offre` : adresse de base `/offres/N` ou `/factures/N`, et son nom lu dans la barre du haut) ; une quantité ou un prix porte un libellé (« quantité 9, « Échafaudage… » »). Le bandeau « Hors réseau » nomme l'offre et le changement : « 1 changement attend (offre 2, quantité 9) ». Export `fluide.enAttentePour(base)` : les gestes en attente de cette offre.
- `apercu-pdf.js` : `enAttente()` passe par `enAttentePour` (base = l'adresse de l'aperçu sans `/apercu`). Changement d'une autre offre : « Envoyer » part ; l'envoi noté attend le réseau, l'écran le dit (« Offre partagée. « Envoyée » sera notée au retour du réseau »). Changement de cette offre (D4 A) : le message le nomme et « Envoyer » attend, comme aujourd'hui.
- Preuve : `banc/sr5-sr6.js chromium aucun 1 2` (« Envoyer » de l'offre 1 part, POST `/offres/1/envoyee` en file) ; `… aucun 1 1` (message nommé, rien ne part) ; `… plus` (réseau revenu : inchangé).

## 2. SR-1 : les gestes branchés dès que l'offre paraît, sans retarder l'affichage

Cause : `fluide.js` et `app.js` en `defer` tournent après la fin du téléchargement de la page ; la quantité passe par `app.js` (`corrections`, branché au `DOMContentLoaded`).

- `fluide.js` chargé dans l'en-tête sans `defer` : il est gardé dans le téléphone (adresse versionnée, cache d'un an, service worker) et s'exécute en moins d'une milliseconde ; il n'écoute que le document, rien à attendre de la page. Les gestes « C'est juste » et corbeille sont prêts avant la première ligne affichée.
- Quantité, prix et choix du client : les écouteurs de `corrections()` passent d'`app.js` dans `fluide.js` (même code, déplacé ; `choixSansJs` reste dans `app.js`).
- Les autres touchers faits avant la fin du chargement de la page (ouvrir la fiche d'une ligne, « + Ligne », fenêtres) : `fluide.js` les retient et les rejoue dès qu'`app.js` est prêt (au plus la fin du téléchargement de la page, 0,1 à 0,2 s sur le chantier), au lieu de partir en page entière.
- Coût, à mesurer : après un déploiement qui change `fluide.js` (rare), la première page attend ce fichier avant de s'afficher.
- Preuve : `banc/sr1-debut.js` (toucher dès l'affichage : « sur place » pour les trois gestes, plus l'ouverture d'une ligne) ; `banc/sr1-fenetre.js` et `SR1_NEUF=1` (temps d'affichage avant et après) ; WebKit.

## 3. MP-A14 : les pièces en puces dans la fiche d'une ligne (D3 A)

- `templates/ligne.html.twig` : à la place du champ et de sa `datalist`, des boutons radio `piece` (Général et les pièces de l'offre, celle de la ligne cochée) et un champ `piece_autre` (« Autre pièce »). Sans JavaScript, tout marche.
- `LigneControleur::corriger` : `piece_autre` non vide l'emporte sur `piece`.
- `app.js` : taper dans « Autre pièce » décoche les puces ; toucher une puce vide « Autre pièce ».
- Tests : fiche rendue avec les puces et la pièce de la ligne cochée ; `piece=Chambre` déplace la ligne ; `piece_autre=Bureau` crée la pièce ; isolation inchangée (route existante).

## 4. MP-A19 : « Créer la fiche » ouvre la fiche remplie (D2 A)

- `offre_client.html.twig` : « Créer la fiche « X » » devient un lien vers `/clients/nouveau?offre=N&dictee=1` (et `&suite=apercu`) ; il se charge dans la même fenêtre.
- `ClientControleur::nouveau` : `dictee=1` et une offre de l'artisan sans client avec un nom dicté : nom et adresse remplis d'après la dictée (même découpage que `choisirPourOffre`, mis en commun). Bandeau « Remplie d'après la dictée : corrigez le nom s'il est mal écrit, puis enregistrez. » ; avec `suite=apercu`, le bouton dit « Enregistrer et voir le PDF ».
- Tests : la fiche préremplie ; l'offre d'un autre artisan ne remplit rien ; l'enregistrement rattache la fiche et mène au PDF (déjà couvert, revérifié).

## 5. Revue, recette, livraison

- Suite PHPUnit sur `offregen_fluidite_test`, PHPStan.
- Recette par l'interface au banc (Chromium et WebKit) : les quatre points, plus `banc/recette-gestes.js` du chantier précédent (non-régression des gestes).
- Revue par un relecteur indépendant (Opus), diff entier.
- Commit par chemins, fetch et rebase, push sur `chantier-voix-offre`. Déploiement sur le test : seulement avec l'accord de Xavier.
