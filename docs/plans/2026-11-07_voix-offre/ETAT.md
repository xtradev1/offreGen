---
chantier: De la voix à l'offre
slug: 2026-11-07_voix-offre
phase: 2
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier, en local : sous-chantiers 01, 10, 11, 12 et 13 (`RECETTE.md` de chacun). Puis commit et PR sur son accord, et sous-chantier 02 (site de test).

## En attente de toi

- Contredire, si besoin, deux choix retenus faute de réponse : comptes démo des prospects en production pilote ; production pilote ajoutée au chantier (sous-chantier 09).
- Site de test avec de vraies données de testeurs (2026-11-14) : dire si Claude y accède quand même, ou si les vraies données vont sur un second site sans accès de Claude.
- Sous-chantier 23 : écrire la durée de conservation des voix et des textes, et le texte du consentement des testeurs (sans eux, pas de vrais testeurs).
- Faire un essai avec ta propre voix, depuis le téléphone : seule la dictée de synthèse est passée chez Infomaniak.

## Suivi Origin

- Projet 44 offreGen, tâche 315 « De la voix à l'offre ».
- Sous-tâches : 316 Environnement, 317 Cadrage, 318 Squelette PHP et import audio, 319 Transcription Whisper Infomaniak, 320 Extraction et rapprochement catalogue, 321 Commandes vocales, 322 Banc de mesure et optimisation.
- En cours : 318 Squelette PHP et import audio (317 Cadrage fait le 2026-11-07).

## Journal

Append-only. Une ligne datée par événement. On ajoute, on ne réécrit pas.

- 2026-11-07 : chantier ouvert, branche `chantier-voix-offre` partie de `environnement-wsl` (PR non fusionnée, pour garder la description de l'environnement). Conversation rattachée à la sous-tâche Origin 317.
- 2026-11-07 : phase 0, profil écrit dans la session principale sans sondage délégué : le dépôt compte 27 fichiers, tous déjà lus dans cette session ; un agent aurait relu la même chose.
- 2026-11-07 : questions de cadre envoyées à Xavier.
- 2026-11-07 : réponses de Xavier : 1 et 2 acceptés, 3 A avec des comptes de démo, 4 oui avec démarrage immédiat après l'import, 5 « entre A et B » (vraies visites et visites rejouées), 6 profil illisible (le lien `\\wsl.localhost` ne s'ouvre pas chez lui). Reportées dans `CODING_RULES.md`, `docs/SECURITE.md` et le roadmap.
- 2026-11-07 : phase 1, `SPEC.md` écrit. Recherche de l'existant sans agent : aucun code applicatif dans le dépôt (27 fichiers, liste du 2026-11-07). Questions posées en une fois au lieu du skill `brainstorming` question par question : Xavier avait déjà répondu à l'essentiel en phase 0.
- 2026-11-07 : gate intention ouvert.
- 2026-11-07 : gate intention franchi. Xavier : périmètre « c'est bon », conservation 30 jours, 200 Mo, serveur Cloud, test sur `esay-offre.xtradev.ch`, PR `environnement-wsl` fusionnée (9c0ce79). Ordre des données : Claude crée les données de développement, puis Xavier, puis un artisan. Question de Xavier sur ses essais sans accès de Claude : production pilote ajoutée (SPEC, point 16). Comptes démo des prospects en production pilote : recommandation retenue sans réponse explicite.
- 2026-11-07 : phase 2, découpage en neuf sous-chantiers : plus de trois sous-systèmes, plus de dix lots prévisibles, une partie bloquée par le jeton.
- 2026-11-07 : sous-chantier 01 : plan écrit, lot 1 fait (Xavier : « tu peux donc commencer le lot 1 »). Suivi Origin sur 318.
- 2026-11-07 : sous-chantier 01 : lots 2 à 8 faits (Xavier : « enchaîne jusqu'au dernier lot »). Livré en code, non recetté par Xavier, non commité. Détail et preuves dans `01-socle-import/ETAT.md`.
- 2026-11-12 : sous-chantier 10 ouvert et livré en code (Xavier : « fais en sorte que tout puisse être fait en local »). Suivi Origin sur 319. Détail et preuves dans `10-ia-locale/ETAT.md`.
- 2026-11-12 : sous-chantier 11 ouvert et livré en code (Xavier : « Où est-ce qu'il est le bouton pour que je puisse directement parler »). Reprend 05 et la partie locale de 08. Aucune sous-tâche Origin ne le couvre. Détail et preuves dans `11-ecrans/ETAT.md`.
- 2026-11-12 : sous-chantier 12 ouvert et livré en code (retour de recette de Xavier sur 11). Détail et preuves dans `12-generer-offre/ETAT.md`.
- 2026-11-12 : sous-chantier 13 ouvert et livré en code (retour de Xavier sur 12). Détail et preuves dans `13-fenetres/ETAT.md`.
- 2026-11-12 : sous-chantiers 01, 10, 11, 12 et 13 commités sur `chantier-voix-offre` (176a2a7) et poussés ; PR à ouvrir par Xavier (gh absent). Sous-chantier 14 ouvert, maquette écrite.
- 2026-11-12 : demandes de Xavier ordonnées en sous-chantiers 15 à 21 ; 15 livré en code.
- 2026-11-12 : écart à la SPEC découvert (sous-chantier 10) : l'extraction envoie tout le catalogue en un appel, la SPEC veut des candidats et deux appels au plus. Xavier veut comparer les deux versions. Détail dans `13-fenetres/ETAT.md`.
- 2026-11-14 : Xavier : domaine de test `easy-offre` (la SPEC disait « esay », corrigée) ; « site de test on pourra faire données inventées et vraie de test ». bcmath installé dans WSL (`php -m` le liste) : le lot 6b du 18 peut partir.
- 2026-11-14 : Xavier : « enregistre toutes les voix, textes pour l'instant car ils seront utilisés pour l'entraînement. J'avertirai les gens qui testent », et « pouvoir consulter les stats à tout moment et pouvoir rejouer les audio, textes ». Change la conservation de 30 jours de la SPEC (gate intention du 2026-11-07). Sous-chantier 23 ouvert. Constat : l'audio est effacé 30 jours après l'offre par `bin/purger-audio`, aucun cron ne le lance encore (`crontab -l` : vide) ; la transcription et le texte entendu par ligne restent ; la réponse brute du modèle, les durées et la version corrigée par l'artisan à côté de celle du modèle ne sont pas gardées.
- 2026-11-14 : Xavier, sous-chantier 23 : statistiques pour lui seul (« oui seul moi »), sur tous les comptes ; coût « estimé et réel » ; rejouer veut dire les deux : réécouter et relire, et relancer transcription et extraction pour comparer (« la version optimisée »). Liste de statistiques proposée le même jour, acceptée sans retouche.
- 2026-11-14 : documentation d'Infomaniak relue (Playwright) : la discussion v2 rend `usage` (jetons entrants, sortants, de raisonnement) ; le résultat d'une transcription ne rend aucune durée ni aucun coût ; `GET /1/ai/{produit}/consumptions` existe, contenu de la réponse non décrit, à lire avec le jeton. `GET /1/ai/models` exige le jeton (401 sans). Tarifs publiés (page française) : Qwen/Qwen3.8-27B-FP8 CHF 0.40 et 3.00 par million de jetons entrants et sortants, Whisper V3 CHF 0.006 la minute.
- 2026-11-14 : `.env` du développement basculé sur Infomaniak (copie de l'ancien : `.env.avant-infomaniak`). Le numéro collé par Xavier (6 chiffres, celui du Manager) rend 403 ; `GET /1/ai` donne `product_id` 357, qui répond. Un seul jeton pour les deux réglages.
- 2026-11-14 : `GET /1/ai/models` : Qwen/Qwen3.8-27B-FP8 `coming_soon` et bêta, mais il répond ; seul modèle de langage `ready` : mistralai/Ministral-3-14B-Instruct-2512. Qwen respecte `reasoning_effort: none` (0 jeton de raisonnement) et l'outil imposé.
- 2026-11-14 : transcription chez Infomaniak : envoi multipart accepté, `data` en texte brut, lot prêt en 3 interrogations. Un lot inconnu rend 403 `access_result_forbidden` (le serveur local rendait 404) : traité comme lot perdu, audio renvoyé ; `bin/verifier-ia` l'accepte. Tests écrits avant, vus rouges.
- 2026-11-14 : parcours cliqué en 390 px, dictée de synthèse, accueil puis offre : prête en 10 s, 6 lignes justes ; journal : 1266 jetons envoyés, 335 reçus. Coût estimé aux tarifs publiés : environ 0,4 centime. Constat : « Madame Exemple, à Bulle » rattachée à la fiche « Famille Exemple, 1700 Fribourg ». Xavier : « si c'est pas la même localité ça ne doit pas marcher ».

## Sous-chantiers

Par livrable testable de bout en bout, dans l'ordre de dépendance.

| # | Sous-chantier | Dossier | Dépend de | État |
|---|---|---|---|---|
| 01 | Socle et import : squelette, connexion, isolation, comptes démo, import audio, traitement immédiat et cron, avec transcripteur et extracteur factices ; l'offre produite s'affiche | `01-socle-import/` | rien | livré en code, recette de Xavier attendue |
| 02 | Site de test : serveur Cloud, déploiement depuis `main`, workflows GitHub, liste « À prouver » | `02-site-test/` | 01 | à faire |
| 03 | Extraction et catalogue chez Infomaniak : clé et nom du modèle, ambiguïtés, second avis « À vérifier », chargement du catalogue CSV. L'extraction réelle tourne déjà en local (10) | `03-extraction/` | 10 | clé et modèle vérifiés chez Infomaniak (2026-11-14) ; le reste à faire |
| 04 | Transcription Whisper chez Infomaniak : clé, numéro de produit, vérifier le format de `data` et l'envoi multipart (décisions fragiles du 10) | `04-transcription/` | 10, jeton | clé, numéro, `data` et multipart vérifiés chez Infomaniak (2026-11-14) |
| 05 | Écran de l'offre et corrections à l'écran | repris par 11 | 01 | livré en code dans 11 |
| 06 | Commandes vocales | `06-commandes/` | 04, 05 | à faire |
| 07 | Banc de mesure : données fictives de Claude, puis visites de Xavier | `07-banc/` | 03, 04 | à faire |
| 08 | Enregistrement dans le navigateur | partie locale reprise par 11 | 02 | sur le PC : livré en code dans 11 ; sur le téléphone : attend le site de test en HTTPS |
| 09 | Production pilote : double authentification, relecture sécurité, sauvegardes, premier déploiement | `09-production-pilote/` | 02 à 07 | à faire |
| 10 | IA réelle en local : Whisper dans WSL et Qwen 27B dans Ollama, au protocole d'Infomaniak (demande de Xavier du 2026-11-12) | `10-ia-locale/` | 01 | livré en code, recette de Xavier attendue |
| 11 | Écrans de la maquette : enregistrer dans le navigateur, onglets, catalogue modifiable, réglages, offre corrigeable (demande de Xavier du 2026-11-12) | `11-ecrans/` | 10 | livré en code, recette de Xavier attendue |
| 12 | Générer l'offre : fenêtre Nouvelle offre, sauvegarde sûre, client, en-tête, document et PDF (retour de Xavier du 2026-11-12) | `12-generer-offre/` | 11 | livré en code, recette de Xavier attendue |
| 13 | Fenêtres qui montent : bouton rond Nouvelle offre, fiche produit par-dessus la liste, offres d'exemple de la démo (retour de Xavier du 2026-11-12) | `13-fenetres/` | 12 | livré en code, recette de Xavier attendue |
| 14 | Marque et modèles : logo, couleur, six modèles d'offre et de facture, QR-facture (demande de Xavier du 2026-11-12) | `14-marque-modeles/` | 13 | maquette, réponse de Xavier attendue |
| 15 | Enregistrement court et suivi en direct : 5 s au minimum, dictée vide donne une offre vide, étapes qui avancent | `15-suivi-envoi/` | 13 | livré en code, recette de Xavier attendue |
| 16 | Marque et modèles dans l'application : logo, couleur, modèles retenus | `16-marque/` | 14 | livré en code, recette de Xavier attendue |
| 17 | Clients : liste, fiche, rattachement aux offres | `17-clients/` | 12 | livré en code (liste, fiche, client de l'offre, rapprochement à 80 %), essai de Xavier attendu |
| 18 | Facture : « Facturer » depuis l'offre, numéro modifiable, QR-facture, PDF | `18-facture/` | 14, 16 | lots 1 à 5 commités (5de18b4) ; lot 6a (adresses) commité (54acafa) ; lot 6b (QR-facture, section de paiement à l'écran et au PDF) livré en code ; reste le lot 7 (démo et recette) |
| 19 | Envoi par courriel de l'offre et de la facture | (à ouvrir) | 18 | plus tard : « pdf pour l'instant » (Xavier, 2026-11-12) |
| 20 | Extraction optimisée (cadrage, section 5) à côté de l'actuelle, banc de comparaison | (à ouvrir) | 10 | plus tard (Xavier, 2026-11-12) |
| 21 | Liste des offres : en préparation, échecs hors quota, tri, archives | `21-liste-offres/` | 15 | livré en code, recette de Xavier attendue |
| 22 | Réglages mieux rangés : sommaire, assistant, enregistrement | `22-reglages/` | 16 | livré en code (sommaire, assistant, enregistrement automatique), essai de Xavier attendu |
| 23 | Tout garder pour l'entraînement : audio, transcription, réponse du modèle, offre avant et après corrections, durées ; statistiques ; réécouter et rejouer (demande de Xavier du 2026-11-14) | `23-garder-rejouer/` (à ouvrir) | 10, 15 | réponses de Xavier reçues, maquette à faire |

## Décisions

Ce qui a été tranché sans demander, et pourquoi.

| Date | Décision | Raison |
|---|---|---|
| 2026-11-07 | Le chantier couvre les sous-tâches 317 à 322 ; cette session vise 317 (cadrage) puis 318 (squelette et import). | Demande de Xavier : « on peut faire le cadrage puis le squelette ». |
| 2026-11-07 | Comptes démo administrés par commandes `bin/`, sans écran d'administration ; pas d'inscription libre. | Le moins de surface exposée tant que rien n'est vendu. |
| 2026-11-07 | Transcripteur factice qui lit un texte de référence. | Avancer et tester sans jeton, et sans payer Whisper à chaque test. |
| 2026-11-07 | Transcription, extraction et commandes vocales chez Infomaniak attendent le jeton. Le banc peut démarrer en local (faster-whisper, Ollama). | Jeton pas encore fourni (« je vais voir »). |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `.claude/atoz-profile.md` | 0 | écrit, à valider |
| `docs/plans/2026-11-07_voix-offre/ETAT.md` | 0 | écrit |
| `docs/plans/2026-11-07_voix-offre/SPEC.md` | 1 | écrit, gate intention |

## Preuves

Commande exécutée et sortie collée. Pas de résumé.

### Phase 0

```
$ git fetch --prune origin && git checkout main && git merge --ff-only origin/main && git log --oneline -3
Already up to date.
3a28c6f Merge pull request #1 from xtradev1/maquette-ux
4f51cf5 Maquette d'ergonomie cliquable et décisions de Xavier
89b138d Socle du projet : règles, sécurité, déploiement et cadrage produit
$ git branch --merged main | grep environnement-wsl
(rien : non fusionnée)
```
