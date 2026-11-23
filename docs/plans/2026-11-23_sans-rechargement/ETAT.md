---
chantier: Application sans rechargement (fluidité, connexion faible, cache)
slug: 2026-11-23_sans-rechargement
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 504 « Application sans rechargement de page (valider une ligne, créer un produit) », 16 h. Sous-tâches : 505 relevé, maquette et décisions (3 h) ; 506 relecture sans rechargement (5 h) ; 507 créer un produit dans une fenêtre (4 h) ; 508 revue et recette sur connexion lente (4 h).
- Tâche 593 « Application sans rechargement, suite : les cinq lots du chantier », 47 h, créée le 2026-11-23 après les réponses de Xavier (l'outil n'ajoute pas de sous-tâche à la 504). Sous-tâches : 594 lot 1 socle (3 h) ; 595 lot 2 l'offre sans rechargement (12 h) ; 596 lot 3 ajouter et catalogue (8 h) ; 597 lot 4 hors réseau (16 h) ; 598 lot 5 PDF et envoi (3 h) ; 599 revue, comparatif, déploiement et recette (5 h). Elle reprend 506, 507 et 508, à fermer par Xavier.
- Conversation rattachée à 505 le 2026-11-23, puis à 594 (lot 1), puis à 599 (revue, comparatif, déploiement) le même jour.

## Prochaine action

Attendre la recette de Xavier (`RECETTE.md`) et ses trois choix de la page `COMPARATIF-sans-rechargement.html`. Défauts remontés : retour en phase 5, sans repasser les gates. Recette validée : `statut: termine`, `/roadmap-sync`, puis suppression de ce qui vit hors dépôt (liste dans « Artefacts »).

## En attente de toi

- Dérouler `RECETTE.md` sur https://easy-offres.xtradev.ch (commit `93a61d1` déployé le 2026-11-23 à 21 h 29, migration `Version20261123230001` jouée) et dire ce qui casse.
- Répondre aux trois choix posés dans `COMPARATIF-sans-rechargement.html` : mention dans la politique de confidentialité ; durée de garde dans le téléphone (14 jours aujourd'hui) ; fenêtres de l'offre et « Prendre 30.00 » qui attendent le serveur (écart à D2).
- Dire si « Se déconnecter » sans réseau (copies du téléphone effacées, puis page d'erreur de Chrome, session encore ouverte au serveur) se corrige maintenant ou plus tard.
- La production : par le workflow de déploiement, à ton heure (gate prod).

## Journal

- 2026-11-23 : chantier ouvert à la demande de Xavier : prendre chaque page, chaque élément, chaque interaction ; dire si le clic synchrone (une requête, la page rechargée) est utile, si l'asynchrone peut le remplacer et ce qu'il fait gagner ; tout tester (le clic, ajouter un produit, parler) ; tenir sur une connexion faible, cache des produits du client ; maquette avec les écrans, avantages et inconvénients.
- 2026-11-23 : copie `~/offreGen-fluidite` détachée sur `origin/chantier-voix-offre` (`081218c`), `.env` et `.env.test.local` copiés de `~/offreGen`, `composer install`. Base de mesure `offregen_fluidite_vue` (sans suffixe `_test` : aucune suite ne la vide), droits recopiés de `offregen_test` (relus par `SHOW GRANTS`), migrations jouées jusqu'à `Version20261123210001`.
- 2026-11-23 : phase 0 sautée : profil et `CODING_RULES.md` présents et lus. Le profil du 2026-11-07 est périmé sur deux points (commandes « à créer », accès au test « à décider ») : signalé, pas corrigé ici.
- 2026-11-23 : compte de mesure `fluidite@exemple.ch` (démo Peintre, mot de passe dans `~/.offregen-fluidite-compte`, hors dépôt). Jeu inventé semé par SQL : 274 produits, 42 clients ; offre 3 de 33 lignes (4 à vérifier, 2 sans prix, 2 prix dictés, une option, une variante, une ligne libre), offre 4 envoyée, offre 5 archivée (`banc/semis.py`, `banc/offre-mesure.sql`). Instantané remis avant chaque mesure (`banc/base.sh`, 0,9 s ; sessions en fichiers, non touchées).
- 2026-11-23 : site de test, lecture seule par curl de fichiers statiques et de `/connexion` : gzip actif ; aucune durée de cache ; ETag suffixé « -gzip » qui empêche le 304 (preuve plus bas). Aller-retour depuis le poste de Xavier : 25 ms. Rien d'autre n'a touché le test.
- 2026-11-23 : inventaire statique des 90 gabarits (`banc/elements.py`, `banc/elements.txt`) : 372 éléments interactifs ; lecture complète de `public/js/app.js` (2 113 lignes), regroupée en 61 gestes avec leur verdict (`banc/inventaire.js`).
- 2026-11-23 : banc Playwright (`banc/`), 47 gestes, réseaux local, fibre, 4G, chantier, cache périmé ; 12 gestes représentatifs rejoués cache gardé. Défauts du banc trouvés et corrigés en route : envois simulés non comptés (événements arrivés après la fin du comptage) ; premier affichage non relevé ; premier affichage compté depuis le début de la navigation et non depuis le toucher (corrigé : affiché = durée - (fin du chargement - premier affichage)) ; « Archiver » et « Supprimer » de la liste mesurés par un clic forcé qui touchait la carte et ouvrait l'offre (vu dans les requêtes : GET au lieu de POST), rejoués avec un vrai glissé ; « Générer » cache gardé mesuré avant que PDF.js soit en cache, rejoué après un premier aperçu ; worker de PDF.js compté 346 octets au lieu de 379 Ko (corrigé à la main d'après le fichier). Défaut de mes données corrigé : l'offre envoyée avait une ligne sans prix, donc « Relire » à la place de « Facturer ».
- 2026-11-23 : coupures et lenteur (`banc/hors-ligne.js`) : page d'erreur du navigateur pour « C'est juste », « + Ligne », ouvrir une offre ; quantité tapée sans réseau non renvoyée au retour du réseau, envoyée seulement en quittant l'offre (24 000 avant, 24 000 huit secondes après le retour du réseau, 27 000 après avoir quitté la page). La capture « une seconde après le toucher » est écartée : Playwright attend la première image de la nouvelle page avant de photographier.
- 2026-11-23 : dictée réelle de 28 s (fichier de test `dictee-peintre.ogg`, faux micro de Chromium, transcription et extraction chez Infomaniak, deux dictées) : fibre, envoi 0,3 s, offre prête après 56 s ; chantier, envoi 4,6 s, offre prête après 25 s.
- 2026-11-23 : vérifié par le banc : après « C'est juste », la page rechargée revient sur l'ancre de la ligne (défilement 1 365 px) ; après la corbeille, l'offre revient tout en haut (défilement 0, redirection sans ancre).
- 2026-11-23 : `AUDIT.md`, `SPEC.md`, `MAQUETTE-sans-rechargement.html` écrits ; phase 2 (décomposition) sautée : un seul chantier, des lots au plan. Gate ouvert (intention et visuel ensemble).
- 2026-11-23 : réponses de Xavier aux choix D1 à D10 (texte collé, mots exacts dans les Décisions) : toutes les réponses conseillées, avec trois commentaires (D1, D4, D5). Consigne : enchaîner les lots 1 à 5, puis une revue de son côté qui reteste tout ce qui a été testé, puis le comparatif avant-après global et détaillé ; déploiement sur le test sans redemander s'il n'y a pas de gros problème ; aucune question.
- 2026-11-23 : gate visuel franchi (réponses de Xavier). Phase 4 : `PLAN.md` écrit (contrat technique de bout en bout). Copie passée sur la branche locale `chantier-fluidite`, à la tête de `origin/chantier-voix-offre` (`7bfe78c` ; deux commits depuis la mesure, sans rapport avec les écrans mesurés : textes de l'onboarding, documentation). Base de test du chantier `offregen_fluidite_test` (droits recopiés de `offregen_test`, migrations jouées), posée dans `.env.test.local` de la copie.
- 2026-11-23 : lots 1 à 5 écrits dans `~/offreGen-fluidite` (branche `chantier-fluidite`), commit `bf9b5ab` : `fichier()` et cache de `.htaccess` ; morceaux de page découpés dans le noyau, clé d'envoi (`envoi`, migration `Version20261123230001`) ; `public/js/fluide.js` (file d'envoi, écran changé au toucher, totaux provisoires) ; « + Ligne » préparé à l'ouverture de l'offre ; service worker `public/sw.js` (copies par compte, 14 jours) ; PDF gardé au serveur (`PdfGarde`, ETag). Suite verte, PHPStan sans erreur, recettes par l'interface sur le banc (gestes, listes, hors réseau par vraie coupure du serveur, PDF).
- 2026-11-23 : phase 7, revue par quatre relecteurs sur Opus (serveur, navigateur, régressions, sécurité) : 4 bloquantes, 19 importantes, corrigées (commit `3eee05a`), une en partie (mention dans la politique de confidentialité, à Xavier) ; détail dans `REVUE.md`. Régression trouvée en corrigeant C-B2 (choix du client dans « Facturer »), corrigée.
- 2026-11-23 : banc « après » : le service worker lisait les polices citées par `document.css` (900 Ko à l'ouverture de « Facturer ») ; corrigé (commit `19792a4`).
- 2026-11-23 : défaut du banc trouvé pendant le comparatif : le bridage du réseau (CDP) ne touchait que la page, jamais le service worker ; chaque page qu'il allait chercher passait à la vitesse locale (« ouvrir une offre en 166 ms sur le chantier »). Corrigé dans `banc/commun.js` (le service worker reçoit le même profil, `Target.attachToTarget`) ; mesures fausses gardées à part (`banc/apres-resultats-sw-libre.jsonl`), fibre, 4G et chantier remesurés.
- 2026-11-23 : défaut du code trouvé grâce au banc corrigé : deux écrans plus lents qu'avant sur le chantier (catalogue 2,3 s avant, 2,6 s après ; « Facturer » 0,7 s avant, 2,0 s après). Le service worker gardait, un par un, les fichiers cités par une page avant de la rendre. Corrigé : la page part dès ses en-têtes, les fichiers manquants sont cherchés 5 s plus tard (1,3 s et 0,7 s). Même commit : le préchargement se met en pause 3 s après toute requête de l'écran (D4, il ne cédait pas une fois lancé), et l'accord « 1 changement attend, il partira ». Commit `93a61d1`, campagne refaite sur les quatre réseaux (`banc/apres-resultats-avant-correctif-sw.jsonl` garde la précédente).
- 2026-11-23 : « Générer » remesuré honnêtement : avant le chantier, chaque ouverture refaisait le PDF ; le cas comparé est donc une offre modifiée juste avant (PDF refait, 2,0 s sur le chantier contre 11,8 s). Le PDF déjà vu (réponse « rien n'a changé », 4 Ko) est donné à part ; le « premier usage » du banc se fait maintenant sur la base remise.
- 2026-11-23 : vérifications ajoutées par l'interface, banc local : session perdue (`banc/recette-session.json`) ; pause du préchargement (`banc/preuve-prechargement.json`) ; style modifié pris sans vider de cache, D1 (`banc/preuve-d1.json`, `app.css` remis, dépôt propre) ; corbeille mesurée dans la page, 6 ms du clic au masquage (`banc/mesure-corbeille.json`) ; navigateur au JavaScript coupé (`banc/recette-sans-js.json`) : « C'est juste », quantité, corbeille et « Annuler » marchent comme avant ; « Archiver » de la liste est recouvert par la carte sans JavaScript, défaut ancien (le chantier n'a ajouté que des attributs à la carte), noté.
- 2026-11-23 : comparatif avant / après `COMPARATIF-sans-rechargement.html` (images dans `COMPARATIF-sans-rechargement-img/`) : global, critères de la SPEC un par un, gestes clés, hors réseau, réponses à D1, D4 et D5, revue, détail des 47 gestes sur quatre réseaux, limites, trois choix pour Xavier. Contrôlé par `check.js` et par un parcours cliqué (`banc/regarder-comparatif.js`) qui a trouvé deux défauts de la page, corrigés : en-tête de tableau collant qui couvrait la première ligne, id `detail` en double (le choix du réseau ne changeait rien).
- 2026-11-23 : déployé sur le test, commit `93a61d1` poussé sur `chantier-voix-offre` (tête partagée `7bfe78c` avant, rebase propre), `outils/deployer-test.sh` depuis `~/offreGen-deploiement-fluidite` : 1440 tests verts sur `offregen_deploiement_test`, PHPStan sans erreur, migration jouée, sonde verte. En-têtes de cache relus par curl depuis Windows. Compte de démo créé sur le test pour la recette (`fluidite-test@exemple.ch`, `bin/creer-compte --demo --metier=peintre`, mot de passe dans `~/.offregen-test-fluidite-compte`) ; recette légère par l'interface (`banc/recette-test.json`).
- 2026-11-23 : trouvé en écrivant la recette, non corrigé : « Se déconnecter » sans réseau efface les copies du téléphone puis tombe sur la page d'erreur de Chrome (la session reste ouverte au serveur). Phase 8 : `RECETTE.md` écrit, gate prod ouvert.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-23 | Mesures en local, derrière un routeur qui imite l'Apache du test (`banc/routeur.php`) : gzip, ETag « -gzip », pas de durée de cache | `php -S` ne compresse rien et n'envoie aucun en-tête de cache : les poids auraient été faux. Contrôle : `app.css` pèse 20 607 octets compressés en local comme sur le test. |
| 2026-11-23 | Réseaux simulés par Chromium (latence ajoutée à chaque requête, débits) : local ; fibre 25 ms ; 4G 100 ms, 9 et 3 Mbit/s ; chantier 500 ms, 400 et 200 kbit/s | Reproductible. La fibre reprend l'aller-retour mesuré vers le test. Une vraie 3G perd aussi des paquets : non simulé, les chiffres du chantier sont donc optimistes. |
| 2026-11-23 | Deux états du cache : « périmé » (chaque page redemande CSS, JS et polices, ce que fait le test quand la fraîcheur devinée est passée) et « gardé » (fichiers gardés) | La fraîcheur que devine le navigateur dépend de l'âge du dernier déploiement : le vrai cas est entre les deux. |
| 2026-11-23 | Le chemin sans rechargement se mesure en envoyant la même requête par fetch, sans suivre la redirection | Le serveur fait le même travail. Manque la réponse utile (ligne et totaux, 1 à 2 Ko compressés) : estimée, pas mesurée. |
| 2026-11-23 | Temps « affiché » : du toucher au premier affichage de la nouvelle page ; « tout chargé » à part | Ce que l'artisan voit ; les polices arrivent après sans bloquer la lecture. |
| 2026-11-23 | Cache gardé rejoué sur 12 gestes représentatifs seulement (`banc/chaud-ids.txt`) | Durée de la campagne ; les autres gestes à page entière suivent le même schéma (mêmes fichiers). |
| 2026-11-23 | Gate intention et gate visuel réunis dans la maquette ; les questions de phase 1 y sont posées sous chaque écran (D1 à D10) | Demande de Xavier : « une maquette comme d'habitude avec les différents écrans, avantages, inconvénients ». |
| 2026-11-23 | Conseil : morceaux de page renvoyés par le serveur, script maison, écran changé au toucher ; pas de bibliothèque, pas d'application JavaScript complète | `CODING_RULES.md` (pas de paquet qui donne la structure) ; les calculs restent au serveur ; sans JavaScript, tout marche encore. À trancher par Xavier (D9). |
| 2026-11-23 | D1 Socle : « Valider. Commentaire : mon seul point c'est qu'il faut vérifier la version du cache du style car si je change un peu le style ça doit fonctionner et je veux pas devoir a chaque fois géréer les emmerde avec le cache » | Réponse de Xavier. Appliqué : l'adresse de chaque fichier porte l'empreinte de son contenu, calculée par le serveur à chaque page ; aucune version à tenir à la main ; un test le prouve. |
| 2026-11-23 | D2 Gestes de l'offre : « A. L'écran change tout de suite, l'envoi suit (conseillé) » | Réponse de Xavier. |
| 2026-11-23 | D3 Ce qui garde la réponse du serveur : « Valider » | Réponse de Xavier. |
| 2026-11-23 | D4 Catalogue dans le téléphone : « A. À l'ouverture de l'application, puis à chaque changement (conseillé). Commentaire : en arrière fond et c'est secondaire les actions que le client fais sont prioritaire ce qui veut dire que ça doit pas gener l'utilisation. » | Réponse de Xavier. Appliqué : préchargement quand le navigateur est libre, priorité basse, suspendu pendant un envoi de l'artisan. |
| 2026-11-23 | D5 Hors réseau : « A. Tout ce qui touche une offre déjà ouverte : les changements attendent dans le téléphone (conseillé). Commentaire : comment il sait ce qu'il doit sauvegarder et récupéré et si on tente de se connecter sans réseaux la ça nmarche pas, mais si on se connecte puis on perds la connexion alors on peut voir encore les offres » | Réponse de Xavier. La question « comment il sait » reçoit sa réponse dans le compte rendu ; comportement appliqué : connexion avec réseau seulement, offres gardées lisibles ensuite. |
| 2026-11-23 | D6 Ce que le téléphone garde : « A. Catalogue, clients et offres des 30 derniers jours (conseillé) » | Réponse de Xavier. |
| 2026-11-23 | D7 Total sans réseau : « A. Total provisoire calculé dans le téléphone, recalculé par le serveur au retour du réseau » | Réponse de Xavier. |
| 2026-11-23 | D8 PDF et envoi : « Valider » ; D9 Approche : « A. Morceaux de page, script maison (conseillé) » ; D10 Ordre : « Valider » | Réponses de Xavier. |
| 2026-11-23 | Les morceaux se découpent dans le noyau, sur la page que rendait déjà la redirection : aucun contrôleur ne change | Une seule mécanique pour tous les gestes, sans double rendu à tenir ; le serveur fait le même travail qu'aujourd'hui (l'envoi puis la page), en une requête, et n'envoie que les morceaux. |
| 2026-11-23 | Nouveau script `public/js/fluide.js` à côté d'`app.js` | `app.js` est modifié par d'autres chantiers : moins de conflits au rebase ; un fichier, une responsabilité (CODING_RULES). |
| 2026-11-23 | Total provisoire hors réseau estimé par différence avec le dernier total du serveur, pas en recopiant `Totaux` en JavaScript | Les calculs restent à un seul endroit (`Totaux`, CODING_RULES et hors périmètre de la SPEC) ; l'estimation est juste pour un seul taux de TVA et des rabais en pour cent, approchée sinon, et marquée « provisoire ». |
| 2026-11-23 | Revue de phase 7 par sous-agents sur Opus, trois lentilles et `owasp-security`, sans Fable | Le profil demande Fable pour la connexion et les droits ; le CLAUDE.md global le réserve à une demande de Xavier, et il l'emporte. |
| 2026-11-23 | Banc « après » : le service worker reçoit le même bridage de réseau que la page (session CDP sur sa cible), et chaque mesure note combien de service workers sont bridés | Sans lui, tout GET relayé par le service worker passait à la vitesse locale : les chiffres des pages étaient faux sur fibre, 4G et chantier. |
| 2026-11-23 | « Générer » se compare au cas « offre modifiée juste avant » ; le PDF déjà vu est donné à part | Avant le chantier, chaque ouverture refaisait le PDF : comparer un PDF gardé à un PDF refait aurait gonflé le gain. |
| 2026-11-23 | Le service worker rend une page dès ses en-têtes ; les fichiers qu'elle cite et qui manquent encore sont cherchés 5 s plus tard | Les attendre coûtait un aller-retour par fichier neuf avant l'affichage (catalogue et « Facturer » plus lents qu'avant sur le chantier). |
| 2026-11-23 | Préchargement en pause tant que l'écran a fait une requête dans les 3 dernières secondes | D4 : « les actions que le client fait sont prioritaires ». Vérifié : aucune page préchargée pendant 6,7 s de gestes, reprise 3,5 s après. |
| 2026-11-23 | Dans le détail du comparatif, « pareil » sous 50 ms d'écart | Écart entre deux passages du même geste au banc (le champ de la fiche client, mécanisme inchangé, sort à +30 et +44 ms). |
| 2026-11-23 | « Se déconnecter » sans réseau : noté, pas corrigé ce soir | Cas rare, rien de perdu au serveur ; la correction (refuser tant que le serveur ne répond pas) touche un geste sensible et mérite sa propre recette. À Xavier de dire quand. |
| 2026-11-23 | Compte de démo propre au chantier sur le test (`fluidite-test@exemple.ch`) pour la recette par l'interface | Les comptes existants appartiennent à d'autres sessions ou à Xavier (mémoire du projet) ; une seule connexion par script (cinq échecs bloquent le courriel et l'IP). |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `AUDIT.md` | 1 | écrit, chiffres du banc |
| `SPEC.md` | 1 | validée (choix D1 à D10) |
| `MAQUETTE-sans-rechargement.html` et `MAQUETTE-sans-rechargement-img/` | 3 | écrite, contrôlée par `check.js`, captures relues |
| `PLAN.md` | 4 | écrit, écarts décidés en route à la fin |
| `REVUE.md` | 7 | écrit, trouvailles et preuves |
| `COMPARATIF-sans-rechargement.html` et `COMPARATIF-sans-rechargement-img/` | 6 à 8 | écrit, contrôlé par `check.js` et par un parcours cliqué, captures relues |
| `RECETTE.md` | 8 | écrit, à dérouler par Xavier |
| `banc/` | 1 à 8 | scripts, mesures brutes avant (`resultats.jsonl`) et après (`apres-resultats.jsonl`, plus les deux campagnes écartées), comparatif, recettes ; ni session, ni mot de passe, ni instantané de la base |

Hors dépôt, à supprimer à la clôture : copies `~/offreGen-fluidite` et `~/offreGen-deploiement-fluidite` (branches locales `chantier-fluidite` et `deploiement-fluidite`), bases `offregen_fluidite_vue` et `offregen_fluidite_test`, dossier `~/offreGen-fluidite-banc` (routeur, instantané, mot de passe du compte de mesure), fichier `~/.offregen-fluidite-compte`. Sur le test : le compte de démo `fluidite-test@exemple.ch` (fichier `~/.offregen-test-fluidite-compte`), à garder tant que la recette n'est pas faite.

## Preuves

### Phase 1 : en-têtes du site de test (lecture seule, 2026-11-23)

```
$ curl -s -o /dev/null -D - -H 'Accept-Encoding: gzip, deflate, br' https://easy-offres.xtradev.ch/css/app.css
HTTP/1.1 200 OK
server: Apache
last-modified: Mon, 23 Nov 2026 13:00:18 GMT
etag: "16758-660f12cf67c80-gzip"
vary: Accept-Encoding
content-encoding: gzip
content-length: 20607

$ curl … -H 'If-None-Match: "16758-660f12cf67c80-gzip"' -H 'If-Modified-Since: Mon, 23 Nov 2026 13:00:18 GMT' …/css/app.css
code=200 octets=20607
$ curl … -H 'If-Modified-Since: Mon, 23 Nov 2026 13:00:18 GMT' …/css/app.css
code=304 octets=0
(même résultat pour /js/app.js, 30 993 octets, et /fonts/inter-latin.woff2, 48 252 octets)
```

### Phase 1 : campagne du banc (extraits, cache périmé)

```
$ sh banc/campagne.sh   (puis banc/suite.sh et banc/reprise.sh)
47 gestes, profils local, fibre, 4g, chantier, cache froid, 1 passe(s)
4g       actuel  liste-ouvrir-offre        550 ms   8 req   162 Ko fcp 432 dcl 362
4g       actuel  offre-juste               714 ms   9 req   162 Ko fcp 484 dcl 470
4g       simule  offre-juste               138 ms   1 req     1 Ko statut 0
4g       actuel  offre-corbeille           789 ms   9 req   162 Ko fcp 468 dcl 452
4g       simule  offre-corbeille           140 ms   1 req     1 Ko statut 0
4g       actuel  offre-quantite            320 ms   2 req    25 Ko
4g       simule  offre-quantite            128 ms   1 req     1 Ko statut 0
4g       actuel  ajout-ouvrir              232 ms   1 req    36 Ko
4g       actuel  ajout-produit             358 ms   2 req    37 Ko
4g       simule  ajout-produit             142 ms   1 req     1 Ko statut 0
4g       actuel  ajout-termine             543 ms   8 req   162 Ko fcp 344 dcl 337
4g       actuel  client-offre              968 ms  11 req   211 Ko fcp 484 dcl 476
4g       simule  client-offre              137 ms   1 req     1 Ko statut 0
4g       actuel  generer                  1584 ms  12 req   277 Ko fcp 364 dcl 656
4g       actuel  facturer-creer            971 ms  14 req   282 Ko fcp 340 dcl 599
chantier actuel  liste-ouvrir-offre       4283 ms   8 req   162 Ko fcp 2128 dcl 2255
chantier actuel  offre-juste              4871 ms   9 req   162 Ko fcp 2616 dcl 2832
chantier simule  offre-juste               572 ms   1 req     1 Ko statut 0
chantier actuel  offre-corbeille          4964 ms   9 req   162 Ko fcp 2616 dcl 2825
chantier simule  offre-corbeille           590 ms   1 req     1 Ko statut 0
chantier actuel  offre-quantite           1647 ms   2 req    25 Ko
chantier simule  offre-quantite            576 ms   1 req     1 Ko statut 0
chantier actuel  ajout-ouvrir             1315 ms   1 req    36 Ko
chantier actuel  ajout-produit            1993 ms   2 req    37 Ko
chantier simule  ajout-produit             564 ms   1 req     1 Ko statut 0
chantier actuel  ajout-termine            4254 ms   8 req   162 Ko fcp 2048 dcl 2254
chantier actuel  client-offre             6379 ms  11 req   211 Ko fcp 2592 dcl 2790
chantier simule  client-offre              569 ms   1 req     1 Ko statut 0
chantier actuel  generer                 16353 ms  12 req   277 Ko fcp 2228 dcl 6808
chantier actuel  facturer-creer           8295 ms  14 req   296 Ko fcp 2296 dcl 6826
# liste-archiver rejoué par banc/reprise.sh (glissé réel) :
4g       actuel  liste-archiver            693 ms   9 req   143 Ko fcp 468 dcl 468
4g       simule  liste-archiver            136 ms   1 req     1 Ko statut 0
chantier actuel  liste-archiver           4863 ms   9 req   143 Ko fcp 2636 dcl 2835
chantier simule  liste-archiver            594 ms   1 req     1 Ko statut 0
```

### Phase 1 : cache gardé, réseau de chantier

```
12 gestes, profils fibre, 4g, chantier, cache chaud, 1 passe(s)
chantier actuel  liste-ouvrir-offre       1098 ms   8 req    24 Ko fcp 612 dcl 1035
chantier actuel  onglet-clients            670 ms   8 req     5 Ko fcp 596 dcl 633
chantier actuel  plus-catalogue           1357 ms  11 req    20 Ko fcp 1280 dcl 1308
chantier actuel  offre-juste              1664 ms   9 req    25 Ko fcp 1164 dcl 1597
chantier actuel  offre-corbeille          1774 ms   9 req    25 Ko fcp 1168 dcl 1612
chantier actuel  offre-ouvrir-ligne        693 ms   8 req     5 Ko fcp 612 dcl 644
chantier actuel  ajout-termine            1067 ms   8 req    25 Ko fcp 592 dcl 1034
chantier actuel  client-offre             3224 ms  11 req    74 Ko fcp 1128 dcl 1559
chantier actuel  relire-juste             1249 ms   9 req     4 Ko fcp 1184 dcl 1190
chantier actuel  catalogue-categorie       797 ms  11 req    11 Ko fcp 596 dcl 759
# generer et facturer-creer rejoués par banc/suite.sh après un premier aperçu (PDF.js en cache) :
chantier actuel  generer                  2038 ms  12 req     4 Ko fcp 608 dcl 631
chantier actuel  facturer-creer           2131 ms  14 req    22 Ko fcp 652 dcl 670
```

### Phase 1 : coupures et lenteur (`banc/hors-ligne.json`)

```json
[
 {
  "cas": "hors-ligne-juste",
  "adresse": "chrome-error://chromewebdata/",
  "titre": "localhost",
  "texte": "Appuyez sur le dinosaure pour jouer\n\nVoici quelques conseils :\n\nVérifiez les câbles réseau, le modem et le routeur.\nReconnectez-vous au réseau Wi-Fi\nExécutez le"
 },
 {
  "cas": "hors-ligne-juste-base",
  "a_verifier_ligne_14": "1"
 },
 {
  "cas": "hors-ligne-quantite",
  "message": "Non enregistré : pas de connexion",
  "avant": "24000",
  "apres_retour_du_reseau_8s": "24000",
  "apres_avoir_quitte_la_page": "27000"
 },
 {
  "cas": "hors-ligne-ajout",
  "adresse": "chrome-error://chromewebdata/",
  "texte": "Appuyez sur le dinosaure pour jouer\n\nVoici quelques conseils :\n\nVérifiez les câbles réseau, le modem et le routeur.\nReconnectez-vous au réseau Wi-Fi\nExécutez le"
 },
 {
  "cas": "hors-ligne-ouvrir",
  "adresse": "chrome-error://chromewebdata/",
  "texte": "Appuyez sur le dinosaure pour jouer\n\nVoici quelques conseils :\n\nVérifiez les câbles réseau, le modem et le routeur.\nReconnectez-vous au réseau Wi-Fi\nExécutez le"
 },
 {
  "cas": "lent-juste",
  "bouton_apres_1s": "remplacé",
  "rendu_ms": 4873
 },
 {
  "cas": "lent-une-corbeille",
  "snack": [
   "« Heure de régie » suppriméeAnnuler"
  ],
  "lignes_retirees": "37"
 },
 {
  "cas": "lent-deux-corbeilles",
  "reponses": [
   "303 /offres/3/lignes/37/supprimer",
   "303 /offres/3/lignes/36/supprimer"
  ],
  "lignes_retirees": "36,37",
  "snack": [
   "« Lasure, balcon » suppriméeAnnuler"
  ]
 },
 {
  "cas": "lent-deux-archiver",
  "reponses": [
   "303 /offres/2/archiver",
   "second toucher impossible : page.click: Timeout 3000ms exceeded."
  ],
  "adresse": "/",
  "titre": "Mes offres · Dicta",
  "snack": [
   "« Rafraîchissement du séjour » archivéeAnnuler"
  ],
  "archivee": "1"
 },
 {
  "cas": "session-expiree-quantite",
  "message": "Enregistrement…",
  "avant": "24000",
  "apres": "24000",
  "adresse": "/offres/3"
 }
]
```

### Phase 1 : dictées réelles (`banc/dictees.jsonl`, sans le détail des requêtes)

```
{"profil":"fibre","statutEnvoi":303,"envoi":303,"offrePrete":56307,"offreAffichee":56308,"lignes":6,"requetes":63,"octets":190416,"envoiOctets":1044,"suivis":53,"suiviOctets":31642,"offre":"/offres/6"}
{"profil":"chantier","statutEnvoi":303,"envoi":4635,"offrePrete":24768,"offreAffichee":24768,"lignes":6,"requetes":20,"octets":164782,"envoiOctets":1044,"suivis":10,"suiviOctets":5981,"offre":"/offres/7"}
```

### Phase 3 : contrôle de la maquette (`check.js`)

```
bureau : largeur 1440/1440, images 17, cassées 0, kit oui
telephone : largeur 390/390, images 17, cassées 0, kit oui
OK. Captures à regarder : C:\Users\xavie\AppData\Local\Temp\claude\42fd285555aa8bf3\2b7eef8e-b575-4b46-bc07-408b5fbc51a7\scratchpad\banc\controle
```
### Phase 6 à 8 : déploiement sur le test (`outils/deployer-test.sh`, journal complet)

```
déploiement de 93a61d1, 21:25:25

== Contrôles du dépôt
commit 93a61d17e6e2ff412214c7f586e74ec439e532b9 (Sans rechargement : la page part sans attendre ses fichiers, le préchargement cède aux gestes)

== Verrou
verrou pris

== Tests et analyse
base des tests : offregen_deploiement_test, reconstruite de zéro
OK (1440 tests, 12015 assertions)
analyse statique : sans erreur

== Construction
73M, 5485 fichiers

== Envoi
sites/easy-offres.xtradev.ch/releases/93a61d17e6e2ff412214c7f586e74ec439e532b9

== Liens, migrations, bascule, cron
wsl : [notice] Migrating up to OffreGen\Migrations\Version20261123230001
 
[notice] finished in 15.2ms, used 8M memory, 1 migrations executed, 1 sql queries

 [OK] Successfully migrated to version:                                         
      OffreGen\Migrations\Version20261123230001                                 


en service : releases/93a61d17e6e2ff412214c7f586e74ec439e532b9 ; précédente : releases/7bfe78cef54a1bfeff20fbd11b208e0e0eb23ea9

== Attente de la nouvelle version
/sante annonce 93a61d17e6e2ff412214c7f586e74ec439e532b9 après 0 s

== Sonde d'exposition
OK     témoin / : 200
OK     chemin inexistant : 404
OK     témoin refusé /.sonde-temoin : 403
OK     témoin refusé /sonde-temoin.php : 403
OK     témoin refusé /sonde-temoin.bin : 403
OK     témoin refusé /sonde-temoin.sql : 403
OK     /.env : 403
OK     /.env.local : 403
OK     /.env.production : 403
OK     /.git/config : 403
OK     /.git/HEAD : 403
OK     /.github/workflows/ : 403
OK     /.htaccess : 403
OK     /.user.ini : 403
OK     /composer.json : 404
OK     /composer.lock : 403
OK     /package.json : 404
OK     /package-lock.json : 404
OK     /vendor/autoload.php : 403
OK     /vendor/composer/installed.json : 404
OK     /node_modules/ : 404
OK     /storage/logs/laravel.log : 404
OK     /logs/ : 404
OK     /backup.sql : 403
OK     /dump.sql : 403
OK     /database.sql : 403
OK     /docker-compose.yml : 403
OK     /uploads/ : 404
OK     http vers https : 301 https://easy-offres.xtradev.ch/
OK     en-tête : X-Content-Type-Options nosniff
OK     en-tête : Content-Security-Policy
OK     en-tête : Referrer-Policy
OK     en-tête : protection contre le cadrage
OK     en-tête : Strict-Transport-Security
OK     X-Powered-By absent
LU     /sante : {"commit":"93a61d17e6e2ff412214c7f586e74ec439e532b9","base":"ok","migrations":"ok"}
OK     commit déployé : 93a61d17e6e2ff412214c7f586e74ec439e532b9
OK     base joignable
OK     migrations à jour
SONDE VERTE

DÉPLOYÉ : 93a61d17e6e2ff412214c7f586e74ec439e532b9 sur https://easy-offres.xtradev.ch
fin : 21:29:02
```

### Phase 8 : en-têtes du site de test (curl depuis Windows, après le déploiement)

```
--- /css/app.css?v=be5114002580
HTTP/1.1 200 OK
cache-control: public, max-age=31536000, immutable
--- /sw.js
HTTP/1.1 200 OK
cache-control: no-cache
--- /css/app.css
HTTP/1.1 200 OK
cache-control: no-cache
--- /fonts/inter-latin.woff2
HTTP/1.1 200 OK
cache-control: public, max-age=604800
--- /connexion
HTTP/1.1 200 OK
cache-control: no-cache, private
--- /sante
{"commit":"93a61d17e6e2ff412214c7f586e74ec439e532b9","base":"ok","migrations":"ok"}
```

### Phase 6 : comparatif, banc corrigé, campagne finale (`node banc/comparatif.js`)

```
réseau     gestes  vu avant  vu après  tout avant  tout après  Ko avant  Ko après  rechargés avant  après   (très fréquents : vu avant, après)
local          47       156        87         158         102      5508       297               31     10    14 gestes : 115 -> 49
fibre          47       230       105         253         118      5507       297               31     10    14 gestes : 193 -> 45
4g             47       416       179         571         187      5507       297               31     10    14 gestes : 363 -> 43
chantier       47      2111       632        4292         680      5521       297               31     10    14 gestes : 2072 -> 49
```

### Phase 6 : D1, style modifié (`banc/preuve-d1.json`)

```
{
 "quand": "23.11.2026 21:24:38",
 "avant": {
  "adresse": "/css/app.css?v=be5114002580",
  "regle": "",
  "controle": true
 },
 "pendant": {
  "adresse": "/css/app.css?v=996a0bb9277b",
  "regle": "1",
  "controle": true
 },
 "apres": {
  "adresse": "/css/app.css?v=be5114002580",
  "regle": "",
  "controle": true
 },
 "fichierRemis": true,
 "ok": true,
 "texte": "Téléphone qui a déjà gardé le style (service worker aux commandes). Style de départ : <code>/css/app.css?v=be5114002580</code>. Une règle ajoutée à app.css, page suivante : <code>/css/app.css?v=996a0bb9277b</code>, règle appliquée, sans rien vider. Fichier remis : retour à <code>/css/app.css?v=be5114002580</code>."
}
```

### Phase 6 : D4, pause du préchargement (`banc/preuve-prechargement.json`)

```
{
 "prechargees": [
  {
   "t": 4043,
   "url": "/"
  },
  {
   "t": 14234,
   "url": "/catalogue"
  },
  {
   "t": 14712,
   "url": "/clients"
  },
  {
   "t": 15164,
   "url": "/offres/3"
  },
  {
   "t": 15629,
   "url": "/offres/2"
  },
  {
   "t": 16093,
   "url": "/offres/4"
  },
  {
   "t": 16561,
   "url": "/offres/1"
  }
 ],
 "activite": [
  4072,
  10774
 ],
 "partiesPendantLActivite": 0,
 "reprise": 3460,
 "ok": true,
 "texte": "Téléphone neuf, réseau 4G simulé : le préchargement part (/ à 4,0 s) ; l'écran fait alors une requête toutes les 0,7 s pendant 6,7 s : aucune page préchargée ne part pendant ce temps ; la suivante part 3,5 s après la dernière requête de l'écran. 7 pages préchargées en tout."
}
```

### Phase 6 : session perdue (`banc/recette-session.json`)

```
{
 "bandeau": "Session expirée : 1 changement attend. Se reconnecter",
 "avant": 18,
 "pendant": 18,
 "apres": "7 en base (7 tapé sans session)",
 "ok": true,
 "erreurs": []
}
```

### Phase 6 : corbeille mesurée dans la page, ms du clic au masquage (`banc/mesure-corbeille.json`)

```
{
 "local": [
  5.7,
  5.7,
  5.6,
  6.3,
  5.5
 ],
 "chantier": [
  5.4,
  5.9,
  5.7,
  6.2,
  5.5
 ]
}
```

### Phase 6 : navigateur au JavaScript coupé (`banc/recette-sans-js.json`)

```
[
 {
  "cle": "scripts de la page exécutés",
  "valeur": false
 },
 {
  "cle": "« C'est juste » : en base (a_verifier)",
  "valeur": "0"
 },
 {
  "cle": "« C'est juste » : étiquette à l'écran",
  "valeur": 0
 },
 {
  "cle": "quantité 27 : en base (millièmes)",
  "valeur": "27000"
 },
 {
  "cle": "corbeille : supprimée en base",
  "valeur": "1"
 },
 {
  "cle": "corbeille : « Annuler » à l'écran",
  "valeur": 1
 },
 {
  "cle": "liste : bouton archiver présent",
  "valeur": 1
 },
 {
  "cle": "archiver : en base (archivee_le posée)",
  "valeur": "0"
 }
]
```

### Phase 8 : recette légère sur le site de test (`banc/recette-test.json`)

```
[
 {
  "cle": "connexion",
  "valeur": "acceptée, arrivée sur /bienvenue/entreprise"
 },
 {
  "cle": "offres trouvées",
  "valeur": [
   "/offres/80"
  ]
 },
 {
  "cle": "service worker aux commandes",
  "valeur": true
 },
 {
  "cle": "caches",
  "valeur": [
   "offregen-etat",
   "offregen-fichiers-2026-11-23-N",
   "offregen-pages-N"
  ]
 },
 {
  "cle": "feuille de style versionnée",
  "valeur": "/css/app.css?v=be5114002580"
 },
 {
  "cle": "corbeille : ligne partie après (ms)",
  "valeur": 24
 },
 {
  "cle": "corbeille : confirmée après (ms)",
  "valeur": 111
 },
 {
  "cle": "bandeau",
  "valeur": "« Protection des sols et des meubles » suppriméeAnnuler"
 },
 {
  "cle": "Annuler : ligne revenue après (ms)",
  "valeur": 47
 },
 {
  "cle": "page rechargée pendant les deux gestes",
  "valeur": false
 },
 {
  "cle": "envois par morceaux (POST)",
  "valeur": [
   {
    "url": "/offres/80/lignes/330/supprimer",
    "statut": 200,
    "page": null
   },
   {
    "url": "/offres/80/lignes/330/restaurer",
    "statut": 200,
    "page": null
   }
  ]
 },
 {
  "cle": "ligne",
  "valeur": "Protection des sols et des meubles"
 },
 {
  "cle": "après déconnexion : caches de pages",
  "valeur": []
 },
 {
  "cle": "erreurs JavaScript",
  "valeur": []
 }
]
```

### Phase 6 : contrôle du comparatif (`check.js`, puis `banc/regarder-comparatif.js`)

```
bureau : largeur 1440/1440, images 23, cassées 0, kit oui
telephone : largeur 390/390, images 23, cassées 0, kit oui
tel ids en double : aucun
tel {"reseau":"4g","lignes":9,"cellule4g":"block 43 ms à l'écranfini 199 ms · 1 req. · 2,2 Ko · sur place","celluleChantier":"none","largeur":390}
tel récapitulatif : Mention dans la politique de confidentialité : (pas de réponse) | Durée de garde dans le téléphone : A. 14 jours (actuel) | Fenêtres de l'offre et prix dicté : (pas de réponse)
bureau ids en double : aucun
bureau {"reseau":"4g","lignes":9,"cellule4g":"block 43 ms à l'écranfini 199 ms · 1 req. · 2,2 Ko · sur place","celluleChantier":"none","largeur":1440}
bureau récapitulatif : Mention dans la politique de confidentialité : (pas de réponse) | Durée de garde dans le téléphone : A. 14 jours (actuel) | Fenêtres de l'offre et prix dicté : (pas de réponse)
erreurs : aucune
```
