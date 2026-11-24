---
chantier: Fluidité, suite de la recette du 24.11 (SR-1, MP-A19, MP-A14, SR-6)
slug: 2026-11-24_fluidite-suite
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen). Aucune tâche pour ce chantier : question posée à Xavier le 2026-11-24 (proposition : « Fluidité, suite de la recette du 24.11 », 11 h, sous-tâches selon l'ordre de la maquette).
- Conversation rattachée au projet 44, sans tâche, le 2026-11-24.

## Prochaine action

Attendre l'accord de Xavier pour déployer `7bb8dd8` (ou la tête du moment) sur le test, puis sa recette sur iPhone (`RECETTE.md`). Défaut remonté : retour en phase 5. Recette validée : `statut: termine`, `/roadmap-sync`.

## En attente de toi

- Dire si je déploie `7bb8dd8` sur le test (ordre du 2026-11-24 : ne plus déployer sans ton accord).
- Après le déploiement : dérouler `RECETTE.md` (F-1 à F-7) sur l'iPhone.
- Dire si M7 (offre gardée avant un déploiement, rouverte sans réseau, sans JavaScript) se corrige maintenant ou plus tard.
- Origin : créer la tâche du chantier dans le projet 44 (question du 2026-11-24 restée sans réponse).

## Journal

- 2026-11-24 : chantier ouvert sur la consigne de Xavier (SR-1, MP-A19, MP-A14, SR-6 de la recette générale ; maquette avant le code). Définitions des quatre points lues dans `~/offreGen-recette/docs/plans/2026-11-17_mise-en-production/MAQUETTE-recette-generale.html` (fichier non commité d'une autre session, lu seulement).
- 2026-11-24 : copie `~/offreGen-fluidite` passée de la branche locale `chantier-fluidite` (62f5c85, propre) à la tête de `origin/chantier-voix-offre` (`ff1ee21`), détachée ; `composer install`. Base de mesure `offregen_fluidite_vue` remise par l'instantané, migrations jouées jusqu'à `Version20261124140001` (aussi sur `offregen_fluidite_test`), instantané réécrit. Serveur du banc relancé (port 8093). Scripts de cette session dans `banc/` ; ils s'appuient sur `../2026-11-23_sans-rechargement/banc/commun.js` (copiés à côté de lui pour tourner).
- 2026-11-24 : phase 0 sautée (profil et `CODING_RULES.md` lus, inchangés depuis le chantier précédent) ; phase 2 sautée (quatre points courts, un seul chantier) ; phases 1 et 3 réunies dans la maquette, comme au chantier précédent.
- 2026-11-24, SR-1 : cause trouvée par le banc. L'offre s'affiche avant que `fluide.js` et `app.js` (chargés en `defer`) tournent ; un toucher dans l'intervalle part en formulaire ordinaire et recharge la page (réseau de chantier : écran changé après 1,85 s, fini après 4,16 s ; 0,05 s et 0,64 s une fois les gestes branchés). Fenêtre : 0,01 à 0,18 s quand les scripts sont gardés, 1,8 à 2,0 s sur le chantier quand ils manquent (après un déploiement). Écarté : préchargements (geste à 0,8 s à 15 s : 1 à 2 ms, Chromium), WebKit local (60 à 120 ms à toute heure, coût de Playwright). Non vérifié : l'iPhone de Xavier sur un vrai réseau (WebKit ne se bride pas ici).
- 2026-11-24, SR-6 : non reproduit dans le parcours seul (offres 1 et 2, Chromium et WebKit, retour par lien et par historique) ni après SR-5 avec réseau revenu. Reproduit après SR-5 fait sur une autre offre, réseau toujours coupé : « Envoyer » de l'offre 1 bloqué, « Des changements de l'offre attendent le réseau », alors que le changement est sur l'offre 2. Cause : `apercu-pdf.js`, `enAttente()` compte toute la file de `fluide.js`, sans regarder l'offre.
- 2026-11-24, MP-A14 : la fiche d'une ligne a déjà un champ « Pièce » relié à une `datalist` (`templates/ligne.html.twig:80`), visible seulement au clavier. Affichage sur iPhone non vérifié.
- 2026-11-24, MP-A19 : « Créer la fiche » (`creer=1`) crée la fiche avec `client_dicte` et l'adresse lue dans `lieu_dicte` (`ClientControleur.php:190-197`), puis suit vers le PDF (`data-sortir`).
- 2026-11-24 : `MAQUETTE-fluidite-suite.html` écrite (captures « avant » et « proposé » du banc, retouchées dans la page sans rien enregistrer, base remise après chaque script), kit injecté, `check.js` vert, captures relues (téléphone et bureau, frise, rangée de trois écrans, bloc D4) ; deux défauts de la page trouvés et corrigés (échelle de la frise sans « 0 s » au téléphone, libellé coupé). `SPEC.md` écrit. Gate ouvert (intention et visuel ensemble).
- 2026-11-24 : réponses de Xavier (texte collé, mots exacts dans les Décisions) ; gate franchi. D1 sans choix, avec une consigne qui écarte A, B et C : nouvelle solution (PLAN, point 2), décidée sans nouveau gate puisque Xavier demande de la trouver et de l'optimiser. `PLAN.md` écrit (phase 4).
- 2026-11-24, phase 5, SR-6 : `fluide.js` range l'offre de chaque geste (`offre`, `offreNom`), nomme une quantité, un prix ou un choix (« quantité 9 ») et le nom de la ligne ; `enAttentePour(base)` ; événement `geste-parti`. `apercu-pdf.js` : « Envoyer » ne regarde que l'offre affichée, message qui nomme le changement (D4 A), aperçu refait quand cette offre n'attend plus rien, « PDF partagé. L'envoi sera noté au retour du réseau. » hors réseau. Bandeau « Hors réseau » : « (offre 2, quantité 9) ».
- 2026-11-24, phase 5, SR-1 : `fluide.js` sans `defer` dans l'en-tête ; écouteurs de quantité et de prix déplacés d'`app.js` (`corrections`) dans `fluide.js` ; `app.js` demandé par `fluide.js` (`data-app`) une fois celui-ci exécuté, démarrage au `DOMContentLoaded` ou aussitôt, puis `fluide.appPrete()` ; touchers sur ce qu'`app.js` ouvre en fenêtre retenus et rejoués ; `sw.js` relève aussi `data-app`. Essais écartés au banc : `fluide.js` bloquant à côté d'`app.js` (offre à 2,0 s sur le chantier après un déploiement qui change les scripts), `fetchpriority` (sans effet). Défauts de mon banc trouvés et corrigés en route : attente du service worker par une fonction asynchrone dans `waitForFunction` (jamais attendu : la mesure « scripts neufs » était faite sans rien en cache) ; indicateur « lignes visibles » de Playwright avant le premier affichage réel quand la feuille de style manque (seul le premier affichage compte).
- 2026-11-24, phase 5, MP-A14 : `ligne.html.twig` en puces (`piece`) et « Autre pièce » (`piece_autre`, qui l'emporte, `LigneControleur`) ; `app.js` décoche les puces quand on tape une autre pièce et inversement. Tests `OffreParPieceTest` (2 neufs, rouges avant) ; `ActiviteEcransTest` adapté (titre du groupe de puces au lieu du libellé du champ).
- 2026-11-24, phase 5, MP-A19 : lien « Créer la fiche » vers `/clients/nouveau?offre=N&dictee=1` (et `suite=apercu`) ; `ClientControleur::nouveau` remplit nom et adresse d'après la dictée (`depuisLaDictee`, partagé avec `choisirPourOffre`) ; titre « Fiche du client », bandeau, bouton « Enregistrer et voir le PDF », lieu non découpé « Lieu entendu dans la dictée, à reporter ». Test `ClientsEcranTest` neuf (rouge avant) ; l'offre d'un autre artisan donne 404. `SocleFluiditeTest` adapté (`data-app`).
- 2026-11-24, phase 6 : suite complète verte, PHPStan sans erreur ; banc : SR-1, SR-6, MP-A14, MP-A19 sous Chromium et WebKit, recettes du chantier précédent (14 gestes, hors réseau) sans régression (preuves plus bas).
- 2026-11-24, phase 7 : relecteur indépendant (Opus, lecture seule) ; aucune bloquante, 6 importantes et 10 mineures, relues dans le code ; I1 à I6 et M1 à M6 corrigées, M7 et M10 (préexistantes) laissées en points ouverts, M8 écartée, M9 compromis voulu (`REVUE.md`). Preuves neuves au banc : I1 (`sans-app.js`), I2 (`ordre-gestes.js`), I3 (`sr5-sr6.js` sans toucher « Envoyer »). Non-régression rejouée : 14 gestes, hors réseau, SR-6, MP-A14 et MP-A19 sous Chromium et WebKit, gestes dès l'affichage ; suite complète et PHPStan verts.
- 2026-11-24, livraison : commit `e002880` rebasé sur 17 commits d'autres sessions (un conflit dans `fluide.js`, `reprendre` : gardé ma version avec les deux arguments neufs de `ligneEnAttente`) ; vérifié que la branche n'avait pas touché `corrections()` et que `brancher()` garde tous les démarrages de la branche (seul ajout : `piecesDeLaLigne`). Migrations jouées (`Version20261124230001`), instantané du banc réécrit. Version rebasée : phpunit OK (1577 tests, 12758 assertions), PHPStan sans erreur ; banc rejoué (14 gestes, hors réseau, MP-A14 et MP-A19 sous les deux moteurs, SR-6, gestes dès l'affichage, ordre des gestes). Poussé : `7c0274b..7bb8dd8` sur `chantier-voix-offre`. Rien déployé. `RECETTE.md` écrit, gate prod ouvert.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-24 | Dossier de chantier à part (`2026-11-24_fluidite-suite`), lié au chantier précédent | Deux des quatre points sont des fonctions neuves (MP-A19, MP-A14) ; l'ETAT précédent fait 631 lignes et garde son gate prod ouvert. |
| 2026-11-24 | Captures « proposé » faites sur l'application du banc, CSP contournée pour la capture seulement (`bypassCSP`) | Mes styles en ligne étaient refusés par la CSP de l'application ; rien de cela n'entre dans le code. |
| 2026-11-24 | SR-6, changement d'une autre offre en attente : corrigé sans demander (D4 ne porte que sur le changement de l'offre affichée) | Défaut avéré : message faux et envoi bloqué d'un PDF juste. |
| 2026-11-24 | D1 SR-1 : « (pas de réponse). Commentaire : il me semblait avoir réduit le temps ce que je veux c'est que l'on puisse avancer et cliquer directement si l'interface apparait cela doit fonctionner mais l'interface ne doit pas apparaitre en 2s c'est trop lent trouve une autre solution, c'est quoi ton amorce. optimise moi ça de manière bien y'a surment moyen de faire ça super bien. » | Réponse de Xavier. Appliqué : `fluide.js` chargé sans `defer` dans l'en-tête (gardé dans le téléphone, prêt avant la première ligne), quantité déplacée d'`app.js` dans `fluide.js`, autres touchers retenus jusqu'à `app.js` (0,1 à 0,2 s) au lieu d'une page entière. L'amorce de la maquette est abandonnée : elle retenait le toucher jusqu'à 2 s après un déploiement. |
| 2026-11-24 | D2 MP-A19 : « A. Ouvrir la fiche remplie, « Enregistrer et voir le PDF » » ; D3 MP-A14 : « A. Les pièces en puces, plus « Autre pièce » (conseillé) » ; D4 SR-6 : « A. Nommer le changement et attendre (conseillé) » ; D5 Ordre : « Valider » | Réponses de Xavier. |
| 2026-11-24 | Conseils : D1 A (amorce), D2 B (nom modifiable sur place), D3 A (puces), D4 A (attendre), D5 ordre SR-6, SR-1, MP-A14, MP-A19 | Raisons dans la maquette. D2 B s'écarte de la lettre de la demande (« ouvrir la fiche ») : signalé à Xavier, à lui de trancher. |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | écrit, à valider avec la maquette |
| `MAQUETTE-fluidite-suite.html` et `MAQUETTE-fluidite-suite-img/` | 3 | écrite, contrôlée, captures relues |
| `PLAN.md` | 4 | écrit |
| `REVUE.md` | 7 | écrit, trouvailles et verdicts |
| `RECETTE.md` | 8 | écrit, à dérouler par Xavier après le déploiement |
| `banc/` | 1 à 7 | scripts de mesure, de capture et de recette, résultats JSON ; ni session ni mot de passe |

Hors dépôt : ceux du chantier précédent (copie `~/offreGen-fluidite`, bases `offregen_fluidite_vue` et `offregen_fluidite_test`, `~/offreGen-fluidite-banc`) ; captures PNG dans le scratchpad de la session.

## Preuves

### SR-1 : geste dès l'affichage, comparé à 10 s après (`banc/sr1-debut.js`, Chromium)

```
4g        juste      debut     visible   273 ms | au toucher : scripts oui, interactive | écran   167 ms, confirmé   315 ms, sur place
4g        juste      apres10s  visible   264 ms | au toucher : scripts oui, complete    | écran    39 ms, confirmé   183 ms, sur place
4g        corbeille  debut     visible   259 ms | au toucher : scripts oui, interactive | écran   152 ms, confirmé   278 ms, sur place
4g        corbeille  apres10s  visible   252 ms | au toucher : scripts oui, complete    | écran   113 ms, confirmé   260 ms, sur place
4g        quantite   debut     visible   258 ms | au toucher : scripts oui, interactive | écran    58 ms, confirmé   201 ms, sur place
4g        quantite   apres10s  visible   281 ms | au toucher : scripts oui, complete    | écran    64 ms, confirmé   204 ms, sur place
chantier  juste      debut     visible   674 ms | au toucher : scripts NON, loading     | écran  1849 ms, confirmé  4155 ms, RECHARGÉE
chantier  juste      apres10s  visible   658 ms | au toucher : scripts oui, complete    | écran    47 ms, confirmé   643 ms, sur place
chantier  corbeille  debut     visible   666 ms | au toucher : scripts NON, loading     | écran  1847 ms, confirmé  4157 ms, RECHARGÉE
chantier  corbeille  apres10s  visible   664 ms | au toucher : scripts oui, complete    | écran   120 ms, confirmé   725 ms, sur place
chantier  quantite   debut     visible   653 ms | au toucher : scripts NON, loading     | écran  1882 ms, confirmé  4157 ms, RECHARGÉE
chantier  quantite   apres10s  visible   655 ms | au toucher : scripts oui, complete    | écran    59 ms, confirmé   676 ms, sur place
```

### SR-1 : durée de la fenêtre sans gestes (`banc/sr1-fenetre.js`)

```
scripts gardés
fibre     /offres/3   32 lignes | lignes visibles   145 ms, gestes branchés   253 ms :   108 ms sans gestes
fibre     /offres/1    3 lignes | lignes visibles   181 ms, gestes branchés   193 ms :    12 ms sans gestes
fibre     /offres/2    6 lignes | lignes visibles   158 ms, gestes branchés   185 ms :    27 ms sans gestes
4g        /offres/3   32 lignes | lignes visibles   264 ms, gestes branchés   276 ms :    12 ms sans gestes
4g        /offres/1    3 lignes | lignes visibles   234 ms, gestes branchés   245 ms :    11 ms sans gestes
4g        /offres/2    6 lignes | lignes visibles   232 ms, gestes branchés   243 ms :    11 ms sans gestes
chantier  /offres/3   32 lignes | lignes visibles   674 ms, gestes branchés   851 ms :   177 ms sans gestes
chantier  /offres/1    3 lignes | lignes visibles   664 ms, gestes branchés   771 ms :   107 ms sans gestes
chantier  /offres/2    6 lignes | lignes visibles   643 ms, gestes branchés   762 ms :   119 ms sans gestes
scripts absents (SR1_NEUF=1 : cache des fichiers et cache du navigateur vidés)
fibre     /offres/3   32 lignes | lignes visibles   254 ms, gestes branchés   267 ms :    13 ms sans gestes
fibre     /offres/1    3 lignes | lignes visibles   121 ms, gestes branchés   175 ms :    54 ms sans gestes
fibre     /offres/2    6 lignes | lignes visibles   160 ms, gestes branchés   208 ms :    48 ms sans gestes
4g        /offres/3   32 lignes | lignes visibles   208 ms, gestes branchés   393 ms :   185 ms sans gestes
4g        /offres/1    3 lignes | lignes visibles   189 ms, gestes branchés   366 ms :   177 ms sans gestes
4g        /offres/2    6 lignes | lignes visibles   187 ms, gestes branchés   370 ms :   183 ms sans gestes
chantier  /offres/3   32 lignes | lignes visibles   639 ms, gestes branchés  2681 ms :  2042 ms sans gestes
chantier  /offres/1    3 lignes | lignes visibles   639 ms, gestes branchés  2440 ms :  1801 ms sans gestes
chantier  /offres/2    6 lignes | lignes visibles   621 ms, gestes branchés  2438 ms :  1817 ms sans gestes
```

### SR-1 : préchargements en cours (`banc/sr1-profil.js`, application ouverte comme à la première fois de l'heure)

```
chantier geste à   800 ms | écran     1 ms, confirmé   612 ms sur place | en vol : rien
chantier geste à  4000 ms | écran     1 ms, confirmé   648 ms sur place | en vol : GET /offres/3/ajouter, GET /offres/3/ajouter (sw)
chantier geste à 15000 ms | écran     1 ms, confirmé   644 ms sur place | en vol : GET /offres/4 (sw)
4g geste à   800 ms | écran     1 ms, confirmé   151 ms sur place | en vol : rien
4g geste à 15000 ms | écran     1 ms, confirmé   154 ms sur place | en vol : rien
```

### SR-1 : WebKit, iPhone 13, banc local (`banc/sr1-webkit.js`, extrait)

```
webkit juste      geste     0 ms après l'affichage (visible 137 ms) | sw true, idle undefined, scripts oui complete | écran    66 ms sur place
webkit juste      geste  5000 ms après l'affichage (visible 156 ms) | sw true, idle undefined, scripts oui complete | écran    60 ms sur place
webkit corbeille  geste     0 ms après l'affichage (visible 304 ms) | sw true, idle undefined, scripts oui complete | écran    69 ms sur place
webkit quantite   geste     0 ms après l'affichage (visible 159 ms) | sw true, idle undefined, scripts oui complete | écran   117 ms sur place
```

### SR-6 : SR-5 sur l'offre 2, SR-6 sur l'offre 1, réseau coupé (`node banc/sr5-sr6.js chromium aucun 1 2`)

```
SR-6 offre                 /offres/1          HORS LIGNE file [{"action":"/offres/2/lignes/4","corps":"quantite=9","gardee":true}] garde ["/offres/2/lignes/4"]
SR-6 aperçu 2              /offres/1/apercu   HORS LIGNE file [{"action":"/offres/2/lignes/4","corps":"quantite=9","gardee":true}] garde ["/offres/2/lignes/4"]
SR-6 après « Envoyer »     /offres/1/apercu   HORS LIGNE file [...] bandeaux ["Hors réseau : 1 changement attend, il partira au retour du réseau.","Des changements de l’offre attendent le réseau : ce PDF ne les contient pas encore. Il se refera dès qu’ils seront partis."]
POST : aucun
```

### SR-6 : réseau revenu, rien ne reste (`node banc/sr5-sr6.js chromium plus`, de même `webkit plus` et `webkit offre`)

```
réseau revenu sur Plus     /plus              en ligne  file [] garde [] bandeaux []
SR-6 aperçu 2              /offres/1/apercu   en ligne  file [] garde [] bandeaux []
SR-6 après « Envoyer »     /offres/1/apercu   en ligne  file [] garde [] bandeaux ["Offre 1 envoyée"]
POST : /offres/1/lignes/1   -> 200 /offres/1/envoyee   -> 200
```

### Phase 3 : contrôle de la maquette (`check.js`, puis captures ciblées)

```
bureau : largeur 1440/1440, images 11, cassées 0, kit oui
telephone : largeur 390/390, images 11, cassées 0, kit oui
OK.
tel largeur 390 erreurs 0 | recap : D4 SR-6, changement de cette offre : A. Nommer le changement et attendre (conseillé)
pc largeur 1440 erreurs 0 | recap : D4 SR-6, changement de cette offre : A. Nommer le changement et attendre (conseillé)
```

### Phase 6 : SR-1 après correction, gestes dès l'affichage (`banc/sr1-debut.js chantier`)

```
chantier  juste      debut     visible   687 ms | au toucher : scripts oui, loading     | écran    85 ms, confirmé  2831 ms, sur place
chantier  corbeille  debut     visible   667 ms | au toucher : scripts oui, loading     | écran    76 ms, confirmé  2145 ms, sur place
chantier  fiche      debut     visible   672 ms | au toucher : scripts oui, loading     | écran  1014 ms, confirmé  2267 ms, sur place
chantier  fiche      apres10s  visible   645 ms | au toucher : scripts oui, complete    | écran   658 ms, confirmé   659 ms, sur place
chantier  quantite   debut     visible   681 ms | au toucher : scripts oui, loading     | écran    77 ms, confirmé  2151 ms, sur place
```

### Phase 6 : SR-1, affichage avec les scripts gardés (`banc/sr1-fenetre.js`, après)

```
fibre     /offres/3   32 lignes | lignes visibles   328 ms, gestes branchés   342 ms
4g        /offres/3   32 lignes | lignes visibles   300 ms, gestes branchés   313 ms
chantier  /offres/3   32 lignes | lignes visibles   653 ms, gestes branchés   664 ms
chantier  /offres/1    3 lignes | lignes visibles   652 ms, gestes branchés   665 ms
chantier  /offres/2    6 lignes | lignes visibles   654 ms, gestes branchés   665 ms
```

### Phase 6 : SR-1, déploiement qui change les scripts, style gardé (`banc/sr1-scripts-neufs.js`, deux passes chacun)

```
defer      chantier  scripts ôtés 2 | premier affichage   620 ms, gestes  2563 ms | css fini 581, fluide fini 2553, app fini 3492
essai0     chantier  scripts ôtés 2 | premier affichage  2004 ms, gestes  1954 ms | css fini 572, fluide fini 1839, app fini 2161
priorite   chantier  scripts ôtés 2 | premier affichage  2008 ms, gestes  1954 ms | css fini 555, fluide fini 1814, app fini 2142
injecte    chantier  scripts ôtés 2 | premier affichage  1644 ms, gestes  1916 ms | css fini 578, fluide fini 1477, app fini 3994
defer      4g        scripts ôtés 2 | premier affichage   312 ms, gestes   333 ms
injecte    4g        scripts ôtés 2 | premier affichage   468 ms, gestes   416 ms
```
(defer : avant ; essai0 : fluide.js bloquant, app.js defer ; priorite : essai0 avec fetchpriority ; injecte : retenu.)

### Phase 6 : SR-6 après correction (`banc/sr5-sr6.js`)

```
== chromium aucun 1 2 (changement en attente sur l'offre 2, aperçu de l'offre 1)
SR-6 après « Envoyer »  /offres/1/apercu  HORS LIGNE  bandeaux ["Hors réseau : 2 changements attendent (offre 2, quantité 9), ils partiront au retour du réseau.","PDF partagé. L’envoi sera noté au retour du réseau."]
== webkit aucun 1 2 : identique
== chromium aucun 1 1 (changement en attente sur l'offre 1)
SR-6 après « Envoyer »  bandeaux [..., "Ce PDF n’a pas encore votre changement (quantité 9, « Échafaudage, montage et location »). Il se refera dès qu’il sera parti."]
RETOUR=1 : retour du réseau : {"refait":true,"file":0} ; POST : /offres/1/lignes/1 -> 200
== chromium plus 1 1 (réseau revenu avant) : bandeaux ["Offre 1 envoyée"] ; POST : /offres/1/lignes/1 -> 200 /offres/1/envoyee -> 200
```

### Phase 6 : MP-A14 et MP-A19 par l'interface (`banc/recette-suite.js`, Chromium et WebKit, mêmes sorties)

```
A14 puces proposées                                  ["Général","Séjour","Chambre","Cuisine","Salle de bain"]
A14 puce cochée à l’ouverture                        Général
A14 pièce après (base)                               Chambre
A14 page rechargée                                   false
A14 puces cochées après « bureau » tapé              0
A14 pièce après « bureau » (base)                    Bureau
A19 fiche dans la fenêtre, sans page entière         true
A19 nom rempli                                       Madame Gerber-Monin
A19 adresse remplie                                  Rue du Lac | 12 | 2000 | Neuchâtel
A19 bouton                                           Enregistrer et voir le PDF
A19 fiches en base avant l’enregistrement            0
A19 après « Enregistrer et voir le PDF »             /offres/2/apercu
A19 client de l’offre (base)                         Madame Gerber-Monnin
erreurs JavaScript                                   []
```

### Phase 6 : non-régression (recettes du chantier précédent) et suite

```
recette-gestes.js local : 14 gestes, tous « sans rechargement », aucune erreur
recette-hors-ligne.js : en base au retour du réseau {"etiquette":"0","quantite":"9000","lignes_en_plus":1,"produit_ajoute":"1","cles":"3"} ; session revenue 4000 ; erreurs JavaScript []
phpunit : OK (1489 tests, 12361 assertions)
phpstan : [OK] No errors
```

### Phase 7 : preuves des corrections de la revue

```
$ node banc/sans-app.js            (I1 : app.js refusé par le réseau, sans service worker)
scripts {"fluide":true,"app":"undefined"} | après le toucher : /offres/3/lignes/39 | erreurs []
$ node banc/ordre-gestes.js        (I2 : 5 rangé sans réseau, 7 tapé pendant le chargement, réseau de chantier)
7 tapé pendant : loading | écran : 7 | base (millièmes) : 7000
POST dans l’ordre : /offres/1/lignes/1 quantite=5& ; /offres/1/lignes/1 quantite=7&
$ I3=1 RETOUR=1 node banc/sr5-sr6.js chromium aucun 1 1   (I3 : « Envoyer » jamais touché)
retour du réseau : {"refait":true,"file":0}
$ node banc/sr1-debut.js chantier  (après les corrections)
chantier  juste      debut     visible   694 ms | au toucher : scripts oui, loading     | écran    71 ms, confirmé   781 ms, sur place
chantier  corbeille  debut     visible   648 ms | au toucher : scripts oui, loading     | écran    87 ms, confirmé   777 ms, sur place
chantier  fiche      debut     visible   668 ms | au toucher : scripts oui, loading     | écran   785 ms, confirmé   786 ms, sur place
chantier  quantite   debut     visible   667 ms | au toucher : scripts oui, loading     | écran    87 ms, confirmé   791 ms, sur place
phpunit : OK (1489 tests, 12365 assertions) ; phpstan : [OK] No errors
```
