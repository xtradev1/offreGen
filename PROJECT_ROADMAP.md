# PROJECT_ROADMAP

## Project identity
- Name : offreGen
- Goal : application d'offres vocales pour artisans. L'artisan décrit les travaux à voix haute pendant la visite, l'application produit une offre à partir de son catalogue, il la corrige à la voix ou à l'écran.
- Owner : Xavier Frelechoz (XtraDev)
- Created : 2026-11-05
- Last updated : 2026-11-06

## Executive summary
- Aucune ligne d'application n'existe. Le socle de sécurité et de déploiement est commité et poussé sur GitHub. Une maquette d'ergonomie cliquable en est à sa version 2.
- Le produit est cadré dans `docs/plans/2026-11-05_mesures-ia/cadrage-produit.md`, avec des mesures réelles (Whisper, correction vocale) et des hypothèses économiques.
- Le premier risque est l'extraction juste d'une offre à partir d'une vraie visite. Le coût de l'IA est faible dans tous les scénarios calculés.

## Context
- Données : adresses et état des logements des clients de l'artisan, transmis à Infomaniak. Une dictée peut contenir par accident des données sensibles (santé d'un occupant, par exemple).
- Hébergement Infomaniak (PHP 8.5 disponible selon Xavier). IA chez Infomaniak : Whisper pour la transcription, un modèle de langue pour l'extraction et les corrections.
- Dépôt GitHub privé `xtradev1/offreGen`, plan Free. Dépôt conservé dans SynologyDrive.
- Suivi du temps : projet Origin 44, sans tâche pour l'instant.
- Règles opposables : `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`.

## Decisions
- [2026-11-05] Pile : PHP avec composants Symfony 7.4 LTS et bibliothèques choisies, sans Laravel. Raison de Xavier : « j'aime pas travailler à l'intérieur de l'environnement », et il veut éviter d'être bloqué dans un système qu'il ne connaît pas. Liste dans `CODING_RULES.md`.
- [2026-11-05] Contrepartie acceptée par Xavier : droits refusés par défaut, accès aux données en un seul endroit, isolation entre clients testée route par route.
- [2026-11-05] Parcours développement, test, production. Un seul geste humain : lancer la mise en production du commit recetté (`docs/DEPLOIEMENT.md`).
- [2026-11-05] Production : aucun accès direct de Claude. Environnements de développement accessibles.
- [2026-11-05] Sous-agents : compteur d'erreurs, bascule sur Opus après 2 erreurs de suite (Xavier avait dit « 2-3 », seuil modifiable).
- [2026-11-05] SynologyDrive conservé par choix de Xavier. Risque signalé : `.git` et `.env` synchronisés.
- [2026-11-06] Produit : le cadrage du labo devient offreGen. Architecture de la section 5 du cadrage retenue : deux appels au modèle par offre, le code fait les calculs et la recherche dans le catalogue, les corrections renvoient des modifications ciblées.
- [2026-11-06] Ergonomie, demande de Xavier : une ligne doit pouvoir être effacée en un clic. Annulation ou confirmation : à départager par des tests d'utilisation avec des artisans.
- [2026-11-06] GitHub Free conservé par Xavier (refus de Pro). **Entorse au socle :** ni branche protégée, ni secret par environnement. Compensations dans `docs/SECURITE.md` section 3.
- [2026-11-06] Application web, sur iPhone et Android. Priorité à l'import d'un fichier enregistré avec l'application du téléphone, qui marche sans réseau. Traitement asynchrone : l'offre est prête le soir, sans besoin de temps réel. L'enregistrement dans l'application et le mode hors ligne viennent ensuite.
- [2026-11-06] Ergonomie, demande de Xavier : « ça doit être utilisable depuis le pouce ». Règle dans `CODING_RULES.md`.
- [2026-11-06] Offre prête : un courriel dans tous les cas, plus une notification.
- [2026-11-06] Contrôle de compréhension, idée de Xavier : un second modèle bon marché, chez Infomaniak pour rester en Suisse, note chaque ligne extraite. Sous un seuil, la ligne s'affiche en rouge. Seuil et fiabilité de la note à mesurer.
- [2026-11-06] Calculs (TVA, ordre remise et TVA, remise sur variante, arrondi aux 5 centimes) : réglages fixés une fois par l'artisan.
- [2026-11-06] Pas de conversation avec le modèle : chaque commande vocale est traitée seule. L'historique à garder se décidera à l'usage et après le test du cache d'Infomaniak.
- [2026-11-06] Relecture du code sensible (connexion, droits, filtre par client, appels de fonctions) par Fable, sur proposition de Xavier. Au-delà de 30 $ par lancement, accord préalable.
- [2026-11-06] Maquette, réponses de Xavier : suppression d'une ligne en un clic avec « Annuler » (à confirmer par les tests chez les artisans), ligne incertaine marquée « À vérifier » sans pourcentage, création d'une offre validée, écran de l'offre à revoir.
- [2026-11-06] Besoins ajoutés par Xavier : ligne libre pour l'offre d'un fournisseur, sans créer de produit au catalogue ; travail plus difficile que d'habitude compté par majoration du prix ou par ligne de supplément, au choix de chaque artisan ; acomptes (par exemple à 10 jours) ; recherche dans le catalogue ; aperçu plus propre. Proposés dans la maquette v2.
- [2026-11-06] Maquette v2, réponses de Xavier : lignes sur une seule ligne ; rien de la dictée à l'écran ; une seule fenêtre pour ajouter ; ligne libre au prix de vente ou au prix d'achat plus marge ; majoration ou ligne de supplément validée, sans motif sur le document du client ; catalogue « bien mieux » ; aperçu validé, « un poil petit sur téléphone ». Acompte à revoir : il se décide au moment de l'envoi, avec son délai. L'unité doit se changer d'un toucher. Maquette v3.
- [2026-11-07] Maquette v3, réponses de Xavier : unité sans réponse, gardée telle quelle. Acompte à revoir : « l'acompte doit être un truc en plus, par exemple générer un acompte une fois l'offre figée ». Maquette v4 : l'envoi fige l'offre, « Générer un acompte » crée une facture d'acompte à part (pourcentage, délai, TVA comprise, place du code QR), plusieurs possibles sans dépasser 100 %.
- [2026-11-07] Acompte à part validé par Xavier, avec une demande : « il faut pouvoir mettre un pourcentage précis ou un chiffre précis ». Ajouté : champs pourcentage et montant liés, montant repris au centime, limite au total de l'offre.
- [2026-11-07] Environnement de développement : PHP 8.5, Composer et MariaDB dans WSL (accord de Xavier). Jeton d'API Infomaniak : Xavier l'ajoutera plus tard.
- [2026-11-06] **Entorse au socle :** audit externe reporté. Xavier : « tant que l'app ne tourne pas chez au moins 4-5 artisans ça ne vaut pas la peine ». Déclencheur : l'application en service chez 4 à 5 artisans. Le pilote tourne donc avec de vraies données sans audit externe.

## Current state
### Done
- [x] Socle posé : `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`, sonde d'exposition, `public/.htaccess`, fichiers témoins, `.gitignore`, `.gitattributes`.
- [x] Cadrage produit et scripts de mesure archivés dans `docs/plans/2026-11-05_mesures-ia/`.
- [x] Premier commit sur `main`, poussé sur GitHub le 2026-11-06.
- [x] Maquette d'ergonomie cliquable, trois métiers fictifs : `docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html`. Versions 2 et 3 le 2026-11-06, version 4 le 2026-11-07, après les retours de Xavier.

### In progress
- [ ] Aucun.

### Next
- [ ] Fusionner la PR de la maquette (Xavier).
- [ ] Chantier « de la voix à l'offre » : brancher l'enregistrement, la transcription, l'extraction et les commandes vocales, mesurer et optimiser. Bloqué par l'environnement de développement et l'accès à l'API d'Infomaniak (voir Blocked).
- [ ] Test d'import : enregistrer 30 à 60 min avec l'application native d'un iPhone et d'un Android, puis importer le fichier dans une page web. Compter les gestes, noter les formats obtenus.
- [ ] Test d'enregistrement dans l'application web : écran verrouillé, réseau coupé en cours de route (après le test d'import).
- [ ] Test d'extraction complète sur 5 vraies visites (section 9 du cadrage, risque principal).
- [ ] Tests Infomaniak : ratio réel de Whisper, formats audio acceptés, taille maximale d'un fichier, minimum facturé, appels d'outils, limites de débit.
- [ ] Choisir l'environnement de développement (PHP, Composer et Docker absents du poste).
- [ ] Écrire les workflows GitHub, dont l'audit quotidien des dépendances.
- [ ] Créer le site de test et dérouler la liste « À prouver » de `docs/DEPLOIEMENT.md`.
- [ ] Convention de sous-traitance (art. 9 LPD) prête avant le premier artisan pilote.

### Blocked
- [ ] Test d'extraction complète.
- reason : il faut de vrais enregistrements de visite, avec le devis que l'artisan aurait rédigé.
- [ ] Chantier « de la voix à l'offre ».
- reason : environnement WSL à installer (accord du 2026-11-07) ; jeton d'API Infomaniak à fournir par Xavier ; les mesures du 2026-11-05 ont tourné en local (faster-whisper, Ollama).

## References and links
- Title : Cadrage produit et mesures
- Link : docs/plans/2026-11-05_mesures-ia/
- Notes : mesures sur la RTX 3090 de Xavier, prix Infomaniak lus le 2026-11-05.
- Title : Packagist, état des bibliothèques
- Link : https://packagist.org/
- Notes : relevé du 2026-11-05 (versions, téléchargements, avis).
- Title : Symfony 7.4
- Link : https://symfony.com/releases/7.4
- Notes : correctifs de sécurité jusqu'en novembre 2029. Une autre page de Symfony parle de 3 ans au lieu de 4, à contrôler.
- Title : GitHub, secrets par environnement
- Link : https://docs.github.com/actions/security-guides/using-secrets-in-github-actions
- Notes : sur dépôt privé, réservés à Pro, Team ou Enterprise.

## Risks / points to clarify
- Enregistrement dans l'application web : un navigateur mobile peut couper le micro quand l'écran se verrouille (hypothèse, à tester sur iPhone et Android), et le réseau manque souvent sur un chantier. L'import d'un fichier natif contourne ces deux problèmes. Une heure d'audio pèse environ 14 Mo en Opus à 32 kbit/s, et 115 Mo en WAV 16 kHz mono.
- Import sur iPhone : le glisser-déposer n'existe pas sur téléphone. Le chemin probable passe par « Partager », puis « Enregistrer dans Fichiers », puis le choix du fichier dans la page (hypothèse, à compter au test d'import).
- Formats : m4a, mp3, amr, ogg ou wav selon le téléphone. Si Whisper d'Infomaniak ne les accepte pas tous, il faut convertir, et la présence d'un outil de conversion sur l'hébergement n'est pas vérifiée.
- Enregistrement d'une conversation : si le client parle pendant l'enregistrement, son accord est requis (art. 179ter du Code pénal suisse, à faire confirmer par un juriste). L'application doit le rappeler à l'artisan.
- Délai après la visite : Whisper d'Infomaniak est asynchrone, et son ratio réel est inconnu. Piste à tester : transcrire chaque morceau pendant l'enregistrement.
- Le coût de Whisper dépend de ce ratio, non mesuré chez Infomaniak. Même dix fois plus lent que prévu, il reste de l'ordre de quelques dizaines de centimes par offre.
- GitHub Free : un push direct sur `main` reste possible, et tout job qui cite un secret le reçoit. Les compensations ne sont pas encore prouvées.
- Pilote sans audit externe : le cœur de la sécurité n'est éprouvé que par nos tests et la relecture par Fable.
- Audit externe : prestataire et budget à définir. Ordre de grandeur lu chez un vendeur suisse : CHF 4 500 à 15 000, sans devis.
- Accès de Claude à l'environnement de test : à confirmer par Xavier.
- Économie : environ 220 clients à 49 CHF pour 10 000 CHF par mois, avant charges sociales et impôts (calcul du 2026-11-06, le cadrage en donnait 235). L'acquisition de clients et l'import des catalogues sont le frein, à valider par le test de disposition à payer.
- Facturation : la facture d'acompte entre dans le produit (2026-11-07). La facture de solde, le code QR suisse et le suivi des paiements restent à cadrer.
- Offre figée après l'envoi : rien n'est prévu pour corriger une offre envoyée (nouvelle version, annulation).
- `.htaccess` jamais exécuté sur Apache, branche HTTPS de la sonde jamais exercée. Rien n'est prouvé sur Infomaniak.

## Session log
- [2026-11-05] Roadmap initialized. Cadrage, choix de la pile, pose du socle.
- [2026-11-06] Cadrage produit reçu et archivé. Règle d'ergonomie, relecture par Fable, report de l'audit externe, GitHub Free, import audio prioritaire. Premier commit. Maquette v1, réponses de Xavier, maquette v2 (lignes, ajout, ligne libre, majoration, acomptes, catalogue, aperçu), réponses de Xavier, maquette v3 (unité, acompte à l'envoi).
- [2026-11-07] Réponses sur la v3, maquette v4 (acompte à part, offre figée après l'envoi).
