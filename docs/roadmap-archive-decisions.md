# Décisions archivées du roadmap

Déplacées telles quelles de `PROJECT_ROADMAP.md` le 2026-11-18 (les ~20 plus récentes restent dans le roadmap).

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
- [2026-11-06] Ergonomie, demande de Xavier : « ça doit être utilisable depuis le pouce ». Règle dans `CODING_RULES.md`.
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
- [2026-11-07] Environnement de développement : PHP 8.5, Composer et MariaDB dans WSL (accord de Xavier), installés et vérifiés le jour même (`CODING_RULES.md`). Composer remplacé par l'installeur officiel à la demande de Xavier. Jeton d'API Infomaniak : Xavier l'ajoutera plus tard.
- [2026-11-07] Copie de travail : clone dans WSL (`~/offreGen`), GitHub comme seule référence, accord de Xavier. Remplace la décision du 2026-11-05 sur SynologyDrive. Raisons : lenteur de `/mnt/c` depuis WSL, synchronisation de `vendor/`, `.git` et `.env` par SynologyDrive.
- [2026-11-07] Règles de code complétées par Xavier : arborescence, nommage en français pour le métier, CSRF (`CODING_RULES.md`).
