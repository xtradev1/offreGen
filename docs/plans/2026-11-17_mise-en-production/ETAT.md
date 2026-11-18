---
chantier: Mise en production d'offreGen
slug: 2026-11-17_mise-en-production
phase: 4
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Étape 2 du `PLAN.md` : revue indépendante du diff de la PR #3 (agents Opus, puis Fable sur la connexion et l'isolation), corrections prouvées par un test. Puis étapes 3 (vérifications GitHub) et 4 (compléter l'application, design cohérent avec l'existant).

## En attente de toi

- Jouer la recette regroupée (`RECETTE.md`), séance A au téléphone, séance B sur le PC.
- Choisir l'orthographe du domaine de production (`easy-offre.ch` ou `easy-offres.ch`, comme le test), le commander, et créer la boîte d'envoi (proposition : `notifications@`).
- Pour le paiement, plus tard : un compte Stripe, ta situation TVA (fiduciaire), la formule de lancement.

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
