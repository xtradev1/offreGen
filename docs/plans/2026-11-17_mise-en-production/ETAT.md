---
chantier: Mise en production d'offreGen
slug: 2026-11-17_mise-en-production
phase: 4
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Attendre les réponses de Xavier sur `MAQUETTE-completer.html`. Ensuite, étape 4 (« Compléter ») : envoi de courriels, mot de passe oublié et changé (avec la déconnexion des autres appareils, M6), administration des comptes d'essai (droit administrateur, F1 du sous-chantier 26), purge selon Q1 (M11), alertes (B9), reprise d'un enregistrement (sous-chantier 27). Chaque morceau avec ses tests, puis recette sur le test.

## En attente de toi

- Répondre sous chaque écran de `MAQUETTE-completer.html` (P1 à P4, A1 à A3, R1, C1) et aux trois questions Q1 à Q3, puis coller « Copier le texte ».
- Q1 surtout : la durée de conservation des voix et des textes en production (deux décisions se contredisent, 7 et 14 novembre).
- Jouer la recette regroupée (`RECETTE.md`).
- Choisir l'orthographe du domaine (`easy-offre.ch` ou `easy-offres.ch`) et créer la boîte d'envoi.

## Suivi Origin

- Projet 44 offreGen, tâche 458 « Passage à l'échelle », sous-tâche 459 « Porte 1 : production » (conversation rattachée le 2026-11-18). Sous-tâches voisines : 460 (compte créé par l'exploitant), 463 (juridique), 469 (paiement).

## Décisions

- 2026-11-17, Xavier : « il faut que tu prépares le test […] tu vas relister tout ce qui n'a pas été fait, tout ce qui pose problème. Et ensuite, on va travailler pour faire en sorte qu'on puisse passer en production le plus vite possible. En respectant et faisant les choses bien. »
- 2026-11-17, Xavier : formules d'abonnement, la recharge pour les abonnés est gardée, le pack sans abonnement est écarté (« ça casse le système »).
- 2026-11-18, Xavier : « va jusquau 4 avec une remarque quand meme c'est que je veux que tu respect le design de l'app du moins que y'ai une cohérence visuel sur tout le site ».
- 2026-11-18, Xavier : « oui va pour fable pour de la relecture ».
- 2026-11-18, Xavier : domaine envisagé « easy-offre.ch » (le DNS le dit inexistant ce jour, donc probablement libre) ; « j'aimerais bien que le paiement puisse être rapidement mis en place ».
- 2026-11-18, Xavier : « tu peux faire une tache production ou te coller a une tache qui convient dans offreGen » : sous-tâche 459 retenue.

## Journal

- 2026-11-17 : inventaire (`INVENTAIRE.md`) établi d'après les `ETAT.md` de chaque sous-chantier, `docs/DEPLOIEMENT.md`, `docs/SECURITE.md`, la crontab du site de test, les routes et la liste des PR. Plan en neuf étapes (`PLAN.md`).
- 2026-11-18 : étape 1 faite. `ETAT.md` des sous-chantiers 01, 10 à 18, 21, 22, 25, 28 remis à jour (accords de commit et de PR caducs, questions restées ouvertes gardées), 14 clos, index du chantier de la voix à l'offre complété (27 à 29). Roadmap resynchronisé (décisions et livrés anciens archivés tels quels). Recette regroupée écrite. Sur le test : démo remise à zéro, compte `essai-recette@exemple.ch` (2 offres) créé, mot de passe dans `~/.offregen-recette-compte` (600), jamais affiché. A41 vu au navigateur (assistant, catalogue vide, 0 erreur JS).
- 2026-11-18 : étape 3 du `PLAN.md`, vérifications automatiques : `.github/workflows/controles.yml` (tests, PHPStan, audit des dépendances, migrations sur une base créée par le job, aucun secret, actions épinglées par SHA). Première exécution verte sur la PR #3. La fusion dans `main` attend la recette et l'accord de Xavier.
- 2026-11-18 : étape 2, revue indépendante : six relecteurs (cinq Opus, un Fable), synthèse dans `REVUE.md`. Aucune fuite entre artisans, aucun accès sans connexion. Corrigés avec un test chacun : H1 à H5, M1 à M3, M5, M8 à M10, M12 à M15, B1 à B6. M4 écarté par la mesure (le test enregistre les vraies adresses). M7 reporté avec sa raison. M6, M11, B9 vont avec l'étape 5. Déployé sur le test (18d49a0), sonde verte, cookie `secure` vu sur le site.
- 2026-11-18 : étape 4, phase 3 : maquette `MAQUETTE-completer.html`, écrans dessinés avec la vraie `public/css/app.css` et la structure des pages rendues (demande de Xavier : cohérence visuelle sur tout le site). Défaut trouvé aux captures et corrigé : la liste d'offres copiée de la base de développement contenait le nom et une adresse de Xavier, remplacée par des offres inventées (contrôle : 0 occurrence). `check.js` vert (1440 et 390 px), captures par section relues. Gate visuel ouvert.

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
