# Ce qui manque avant la production

Relevé du 17 novembre 2026, à la demande de Xavier : « relister tout ce qui n'a pas été fait, tout ce qui pose problème », pour passer en production vite et bien. Chaque point vient d'un fichier lu ou d'une commande lancée ce jour-là.

## Première production visée

Des comptes d'essai gratuits, créés par Xavier, plafonnés en offres (premier : Luigi, 20 offres), sur un vrai site de production avec de vraies données. Le paiement et les abonnements viennent ensuite : ils ne bloquent pas un essai gratuit.

## A. Bloquant pour cette première production

| # | Manque | Constat | Qui |
|---|---|---|---|
| A1 | Recettes en attente | 13 sous-chantiers attendent ta recette (01, 10, 11, 12, 13, 15, 16, 17, 18, 21, 22, 25, 28), plus la revue et la recette finales du 24 (lot 9). Les jouer une à une prendrait des heures : une recette unique, regroupée, sur le site de test. | Claude l'écrit, Xavier la joue |
| A2 | Code hors de `main` | La PR #3 est ouverte depuis le 12 novembre ; `main` a 77 commits de retard. Une revue indépendante du diff avant fusion était prévue au sous-chantier 01 (connexion, isolation, import, secrets) et n'a pas eu lieu. | Claude (revue), Xavier (accord de fusion) |
| A3 | Aucune intégration continue | `.github/workflows` n'existe pas. Le parcours de `docs/DEPLOIEMENT.md` (tests, analyse, audit des dépendances et test d'isolation à chaque PR ; test déployé depuis `main` ; workflow « Mettre en production ») n'est pas construit. Le test se déploie à la main depuis le poste de travail. | Claude |
| A4 | Pas de site de production | Ni site, ni base, ni `.env`, ni domaine, ni produit IA de production. À vérifier sur le serveur : PHP 8.5, extension bcmath (QR-facture), ffmpeg (celui du test date d'août 2024, « à remplacer avant la production »). | Xavier (Manager Infomaniak), Claude (liste exacte et vérifications) |
| A5 | Mot de passe | Pas de « mot de passe oublié », pas de changement de mot de passe par l'artisan. Un artisan qui l'oublie dépend de Xavier. | Claude |
| A6 | Envoi de courriels | Rien n'est configuré. Il en faut pour le mot de passe oublié et les alertes. | Xavier (adresse d'envoi), Claude |
| A7 | Création des comptes en production | Aujourd'hui en ligne de commande (`bin/creer-compte --offres=N`). En production, la clé SSH n'acceptera que le déploiement : il faut un écran d'administration minimal (créer un compte d'essai, changer son plafond), premier morceau du sous-chantier 26. | Claude |
| A8 | Sauvegardes | Base chiffrée, copiée hors de l'hébergement, restauration essayée et chronométrée : rien n'est en place. | Claude (script), Xavier (support de sauvegarde) |
| A9 | Purge des audios | `bin/purger-audio` existe mais aucune tâche planifiée ne le lance (crontab du test lue ce jour) : la conservation de 30 jours décidée n'est pas appliquée. | Claude |
| A10 | Alertes | Rien ne prévient d'une erreur, d'un traitement en échec ou d'un site arrêté. | Claude |
| A11 | Textes légaux | Ni politique de confidentialité (loi sur la protection des données : traitement par l'IA d'Infomaniak, adresses envoyées à swisstopo, conservation des audios), ni conditions de l'essai, ni mentions légales. | Xavier (texte, avec l'agent de relecture XtraDev), Claude (liste de ce qu'ils doivent dire) |
| A12 | Sécurité relue | `docs/SECURITE.md` : la relecture ligne à ligne avant la première production (avec `owasp-security` et `conformite-mandats-clients`) n'est pas faite, et beaucoup de cases restent vides alors que le travail existe (le test d'isolation route par route, par exemple). La relecture à part de la connexion, des droits et de l'isolation, prévue « par Fable », n'a pas eu lieu. | Claude ; Fable seulement si Xavier le demande |
| A13 | Postes coupés sans prévenir | Au-delà de 300 lignes, l'extraction coupe en silence. Il faut avertir l'artisan. | Claude |

## B. Fortement conseillé avant les premiers artisans

| # | Manque | Pourquoi |
|---|---|---|
| B1 | Enregistrement à l'abri (sous-chantier 27) | Une coupure de réseau pendant l'envoi fait perdre une dictée qui peut durer 30 minutes : le pire moment pour un premier essai. |
| B2 | Mesure de qualité sur de vraies dictées (sous-chantier 29) | 96 % vient du banc inventé ; la seule vraie dictée a donné 5 à 7 lignes justes sur 7. Les fiches sont prêtes, les dictées manquent. |
| B3 | Journal des appels IA et des temps d'attente (sous-chantier 26, lots 2 et 3) | Sans lui, ni coût réel par offre, ni mesure de la file d'attente de Whisper (47 s puis 5 min observées). |
| B4 | Modèle en bêta | Qwen 27B est marqué « coming_soon » et bêta chez Infomaniak. Il répond, mais peut changer. Prévoir le modèle de secours et le réglage pour basculer. |

## C. Pour vendre, après l'essai

Paiement Stripe ; quota d'offres remis à zéro chaque mois ; recharge pour les abonnés ; formules Solo, Pro, Entreprise (`26-couts-ia/OFFRES-ABONNEMENT.md`) ; comptes multiples pour Entreprise ; extraction optimisée (sous-chantier 20) ; facture rangée par pièce.

## D. Dette et désordre

- Les fichiers `ETAT.md` des sous-chantiers 01, 10, 11, 12, 13 demandent encore l'accord de pousser la branche, déjà poussée ; le 14 attend une réponse donnée le 12 novembre.
- `PROJECT_ROADMAP.md` n'a pas été mis à jour depuis plusieurs jours ; la mémoire du projet n'a jamais été clôturée.
- `docs/DEPLOIEMENT.md` garde deux « (à préciser) » : le mécanisme de maintenance et la page témoin.
- `docs/DEPLOIEMENT.md`, section « À prouver sur le vrai hébergement » : neuf essais, aucun fait.
