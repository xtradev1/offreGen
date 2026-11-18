---
chantier: Mise en production d'offreGen
slug: 2026-11-17_mise-en-production
phase: 4
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Après le choix de marque : appliquer la direction à `public/css/app.css` (toute l'application, mode sombre compris), refaire les écrans refusés (P1, P2, P4, A2, R1) dans cette marque, et les soumettre. En parallèle, ce qui est validé et sans écran : envoi de courriels, jetons de mot de passe et d'accès (A3), droit administrateur, purge écrite et désactivée (Q1), alertes vers l'adresse de Xavier (Q3).

## En attente de toi

- Dans `MAQUETTE-sobre.html` : valider la direction (S1), choisir l'accent (S2), coller « Copier le texte ».
- Jouer la recette regroupée (`RECETTE.md`).
- Choisir l'orthographe du domaine (`easy-offre.ch` ou `easy-offres.ch`) et créer la boîte `notifications@`.

## Suivi Origin

- Projet 44 offreGen, tâche 458 « Passage à l'échelle », sous-tâche 459 « Porte 1 : production » (conversation rattachée le 2026-11-18). Sous-tâches voisines : 460 (compte créé par l'exploitant), 463 (juridique), 469 (paiement).

## Décisions

- 2026-11-17, Xavier : « il faut que tu prépares le test […] tu vas relister tout ce qui n'a pas été fait, tout ce qui pose problème. Et ensuite, on va travailler pour faire en sorte qu'on puisse passer en production le plus vite possible. En respectant et faisant les choses bien. »
- 2026-11-17, Xavier : formules d'abonnement, la recharge pour les abonnés est gardée, le pack sans abonnement est écarté (« ça casse le système »).
- 2026-11-18, Xavier : « va jusquau 4 avec une remarque quand meme c'est que je veux que tu respect le design de l'app du moins que y'ai une cohérence visuel sur tout le site ».
- 2026-11-18, Xavier : « oui va pour fable pour de la relecture ».
- 2026-11-18, Xavier : domaine envisagé « easy-offre.ch » (le DNS le dit inexistant ce jour, donc probablement libre) ; « j'aimerais bien que le paiement puisse être rapidement mis en place ».
- 2026-11-18, Xavier : « tu peux faire une tache production ou te coller a une tache qui convient dans offreGen » : sous-tâche 459 retenue.
- 2026-11-18, Xavier, réponses à `MAQUETTE-completer.html` (texte copié tel quel) :
  - P1 : Pas valider · je trouve qu'il manque un aspect de marque il gfaut définir la marque pour les couleurs et y'a trop de blanc
  - P2 : Pas valider · on peut faire plus propre et jolie non ?
  - P3 : B · et avec le petit oeil emoticione...
  - P4 : Pas valider
  - A1 : Valider
  - A2 : Pas valider · il faut plus travailler l'ui ça doit avoir une super experience
  - A3 : A
  - R1 : B · mais il faut que le tesxte soit plus clair
  - C1 : Valider
  - Q1 : A : audio effacé 30 jours après l'offre, texte gardé · mais pour l'instant désactivé sera activé en production quand je déciderai
  - Q2 : notifications@ sur le domaine de production
  - Q3 : mon adresse habituelle
- 2026-11-18, Xavier (questions posées après la maquette) : nom affiché « Easy Offre » ; « Identité propre » pour la marque (distincte de XtraDev) ; P4 refusé pour « Même remarque que P1 » (le parcours lui va, le design non).
- 2026-11-18, Xavier, sur `MAQUETTE-marque.html` : « C'est un peu trop basique ton truc, ça fait un peu moche », « 3 propositions hyper originales », « commencer par définir quelle valeur on est censé transmettre », puis les couleurs (théorie, Europe, Suisse, confiance). Les trois directions de `MAQUETTE-marque.html` sont écartées.
- 2026-11-18, Xavier, sur `MAQUETTE-identite.html` : V1 « Pas valider · enlève décontracté on est sur un produit, simple, sobre, efficace, qualité suisse et adapter au métier » ; I1 « aucun on est pas une agence de design plus sobre on est avec des artisants » ; I2 « neutre ». Carnet, Mètre et Raoul écartés ; ton neutre lu comme : ni tutoiement ni vouvoiement, formulations à l'infinitif ou nominales.
- 2026-11-18, Xavier, sur `MAQUETTE-sobre.html` : « alors pas B, mais j'hésite entre A et C, mais effectivement j'irais plus sur C le nom aussi pas est moyen est-ce que tu as d'autres idée ». B écarté, C pressenti, nom « Easy Offre » remis en question (domaine et boîte `notifications@` en attente du nom).

## Journal

- 2026-11-17 : inventaire (`INVENTAIRE.md`) établi d'après les `ETAT.md` de chaque sous-chantier, `docs/DEPLOIEMENT.md`, `docs/SECURITE.md`, la crontab du site de test, les routes et la liste des PR. Plan en neuf étapes (`PLAN.md`).
- 2026-11-18 : étape 1 faite. `ETAT.md` des sous-chantiers 01, 10 à 18, 21, 22, 25, 28 remis à jour (accords de commit et de PR caducs, questions restées ouvertes gardées), 14 clos, index du chantier de la voix à l'offre complété (27 à 29). Roadmap resynchronisé (décisions et livrés anciens archivés tels quels). Recette regroupée écrite. Sur le test : démo remise à zéro, compte `essai-recette@exemple.ch` (2 offres) créé, mot de passe dans `~/.offregen-recette-compte` (600), jamais affiché. A41 vu au navigateur (assistant, catalogue vide, 0 erreur JS).
- 2026-11-18 : étape 3 du `PLAN.md`, vérifications automatiques : `.github/workflows/controles.yml` (tests, PHPStan, audit des dépendances, migrations sur une base créée par le job, aucun secret, actions épinglées par SHA). Première exécution verte sur la PR #3. La fusion dans `main` attend la recette et l'accord de Xavier.
- 2026-11-18 : étape 2, revue indépendante : six relecteurs (cinq Opus, un Fable), synthèse dans `REVUE.md`. Aucune fuite entre artisans, aucun accès sans connexion. Corrigés avec un test chacun : H1 à H5, M1 à M3, M5, M8 à M10, M12 à M15, B1 à B6. M4 écarté par la mesure (le test enregistre les vraies adresses). M7 reporté avec sa raison. M6, M11, B9 vont avec l'étape 5. Déployé sur le test (18d49a0), sonde verte, cookie `secure` vu sur le site.
- 2026-11-18 : étape 4, phase 3 : maquette `MAQUETTE-completer.html`, écrans dessinés avec la vraie `public/css/app.css` et la structure des pages rendues (demande de Xavier : cohérence visuelle sur tout le site). Défaut trouvé aux captures et corrigé : la liste d'offres copiée de la base de développement contenait le nom et une adresse de Xavier, remplacée par des offres inventées (contrôle : 0 occurrence). `check.js` vert (1440 et 390 px), captures par section relues. Gate visuel ouvert.
- 2026-11-18 : réponses de Xavier reçues. Validés : A1, A3 (courriel d'accès), C1, Q1 A (purge écrite mais désactivée jusqu'à sa décision), Q2, Q3. Refusés : P1, P2, P4, A2 ; P3 B avec un œil ; R1 B avec un texte plus clair. Constat commun de P1, P2 et A2 : l'application n'a pas d'identité de marque (couleurs, nom, trop de blanc) ; c'est un travail sur toute l'application, pas sur ces seuls écrans. Gate visuel toujours ouvert.
- 2026-11-18 : maquette `MAQUETTE-marque.html` : trois directions (A Atelier, B Signal, C Sapin) appliquées aux vrais écrans (connexion, liste des offres, une offre) et au courriel, même logo pour les trois. Défaut trouvé aux captures et corrigé : le jaune de B illisible en texte, remplacé par un jaune foncé (couleur calculée vérifiée). Offre de démonstration rendue en local : la colonne PC contenait des données réelles, écartée (contrôle : 0 occurrence). Gate visuel ouvert sur la marque ; les écrans refusés seront refaits dans la direction choisie.
- 2026-11-18 : `MAQUETTE-identite.html` : cinq valeurs (simple, décontracté, juste, du métier, suisse), ce que disent les sources sur les couleurs (Jonauskaite 2020, Labrecque et Milne 2012, Broeder 2018 et 2019, Poste et CFF ; aucune étude suisse trouvée sur couleur et confiance), six règles qui en découlent, trois identités prototypées (Carnet, Mètre, Raoul) sur connexion, offres, offre, dictée et courriel, contrastes mesurés. Défauts trouvés et corrigés : tirets demi-cadratins des prix ronds, polices non chargées pour les courriels, deux affirmations non vérifiées retirées. `check.js` vert, captures relues.
- 2026-11-18 : `MAQUETTE-sobre.html` : une seule direction (fond pierre, en-tête de couleur, listes à filets, IBM Plex Sans, marque carré et trois lignes), trois accents à choisir (bleu ardoise, vert sapin, jaune signal), mode sombre montré. Défaut trouvé et corrigé : texte secondaire et étiquettes d'état entre 5,4 et 6,9:1 alors que la page annonce 7:1, couleurs foncées jusqu'à 7:1 partout (mesuré). Chiffres à chasse fixe vérifiés (largeur de 1111.11 = 8888.88). `check.js` vert, captures des trois accents relues.

## Preuves

```
$ ls .github/workflows : No such file or directory
$ git log --oneline main..chantier-voix-offre | wc -l : 77
$ gh pr list : #3 « De la voix à l'offre… » OPEN depuis le 2026-11-12
$ crontab -l (site de test) : seulement bin/traiter-file, pas de bin/purger-audio
$ grep routes : connexion, déconnexion ; ni mot de passe oublié ni changement de mot de passe
```

Étape 1 (2026-11-18) :

```
$ env APP_ENV=test vendor/bin/phpunit
OK (509 tests, 3121 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ git diff --stat 9fe1570 HEAD -- src templates public config migrations bin
(vide : le test a le dernier code)
$ php-8.5 bin/reinitialiser-demo demo-peintre@exemple.ch peintre (test)
Compte demo-peintre@exemple.ch remis à zéro : catalogue peintre (12 produits), 3 fichier(s) audio supprimé(s).
$ outils/compte-essai-test.sh creer essai-recette@exemple.ch "Peinture Recette (essai)" 2
Compte créé : <masqué> (essai, 2 offres)
$ node recette-a41.js (390 px, test)
après connexion /bienvenue/entreprise
catalogue Tout Le catalogue est vide. « Nouveau produit », en bas, ajoute le premier.
erreurs JS 0
```

Étapes 2 et 3 (2026-11-18) :

```
$ gh run view 44274735896 (Contrôles, PR #3)
No security vulnerability advisories found.
[OK] Successfully migrated to version
[OK] No errors
OK (509 tests, 3121 assertions)
$ env APP_ENV=test vendor/bin/phpunit (après les corrections)
OK (535 tests, 3626 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ bash outils/deployer-test.sh
SONDE VERTE
DÉPLOYÉ : 18d49a09443fa53c43516c0e2ba5e64fe93387ab sur https://easy-offres.xtradev.ch
$ curl -sI https://easy-offres.xtradev.ch/connexion | grep -i set-cookie
set-cookie: offregen=<masqué>; ...; path=/; secure; HttpOnly; SameSite=lax
```
