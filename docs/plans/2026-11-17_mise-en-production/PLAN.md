# Plan : la première mise en production

Ordre choisi pour aller vite sans sauter d'étape. Chaque étape renvoie aux numéros de `INVENTAIRE.md`. Une étape ne se ferme qu'avec sa preuve écrite dans `ETAT.md`.

| Étape | Contenu | Qui | Dépend de |
|---|---|---|---|
| 1. Ranger | Mettre à jour les `ETAT.md` en retard et le roadmap (D) ; écrire la recette regroupée (A1). | Claude | |
| 2. Revoir | Revue indépendante du diff de la PR #3 : connexion, isolation, import, secrets, appels à l'IA (A2) ; corriger ce qu'elle trouve, chaque faille prouvée par un test. | Claude (agents Opus), Fable si Xavier le demande | 1 |
| 3. Recetter | Xavier joue la recette regroupée sur le site de test, en une ou deux séances. | Xavier | 1 |
| 4. Intégrer | Workflows GitHub : tests, phpstan, audit des dépendances, test d'isolation à chaque PR ; fusion de la PR #3 dans `main` ; déploiement du test depuis `main` (A3). | Claude, accord de fusion de Xavier | 2, 3 |
| 5. Compléter | Mot de passe oublié et changement de mot de passe (A5) ; envoi de courriels (A6) ; écran d'administration minimal pour les comptes d'essai (A7) ; purge planifiée (A9) ; alertes (A10) ; avertissement des postes coupés (A13) ; enregistrement à l'abri (B1). Chaque morceau : test, recette sur le test. | Claude | 4 |
| 6. Préparer la production | Xavier crée le site, la base, le domaine, la boîte d'envoi, le produit IA de production et ses accès ; Claude écrit le workflow « Mettre en production », la clé SSH limitée au déploiement, les sauvegardes (A8), et prouve sur le test les essais de `DEPLOIEMENT.md` (A4). | Xavier et Claude | 4 |
| 7. Textes légaux | Liste de ce que les textes doivent dire (A11) ; texte de Xavier relu par l'agent XtraDev ; pages dans l'application. | Xavier, Claude | |
| 8. Décider | Relecture de `SECURITE.md` ligne à ligne (A12) ; tableau de ce qui est prouvé ; décision de Xavier de mettre en production. | Claude, puis Xavier | 5, 6, 7 |
| 9. Ouvrir | Première mise en production par le workflow ; sonde verte ; comptes d'essai (Luigi d'abord) ; une semaine de surveillance rapprochée. | Xavier lance, Claude surveille par les journaux qu'on lui donne | 8 |

En parallèle, dès maintenant : les dictées du corpus (B2), puis le journal des appels IA (B3).

Ce qui ne bloque pas cette première production : tout le bloc C de l'inventaire (paiement et formules).
