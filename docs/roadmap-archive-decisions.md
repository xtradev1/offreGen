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
- [2026-11-06] **Entorse au socle :** audit externe reporté. Xavier : « tant que l'app ne tourne pas chez au moins 4-5 artisans ça ne vaut pas la peine ». Déclencheur : l'application en service chez 4 à 5 artisans. Le pilote tourne donc avec de vraies données sans audit externe.
- [2026-11-07] Connexion réelle dès le squelette, et comptes de démo pour présenter offreGen et le faire tester (Xavier). Détail dans `docs/plans/2026-11-07_voix-offre/SPEC.md`.
- [2026-11-07] Traitement d'un audio : démarré tout de suite après l'import, puis cron chaque minute pour vérifier et terminer ce qui n'a pas été fait (Xavier).
- [2026-11-07] Dépendances ajoutées : `symfony/security-core` et `symfony/password-hasher` figées en 7.4, parce que `security-csrf` 7.4 les tirait en 8.1, hors de la branche LTS. `twig/twig` 3, PHPUnit 12, PHPStan 2.
- [2026-11-07] Dépendances ajoutées au sous-chantier 01 : `doctrine/migrations` 3.9 (DBAL 4), `monolog/monolog` 3, `symfony/clock` 7.4 (horloge que les tests figent). `symfony/string` et `symfony/console`, tirés par les migrations, figés en 7.4. Audit sans alerte.
- [2026-11-07] **Entorse au socle :** de vraies visites au banc de mesure, avec l'accord des clients, en plus de visites rejouées (Xavier : « on peut bosser avec de vraies données, c'est le mieux »). Compensations : stockage dans WSL hors dépôt et hors SynologyDrive, envoi seulement à Infomaniak, Claude ne lit ni l'audio ni la transcription d'une vraie visite, suppression en fin de banc. Conditions dans la SPEC du chantier.
- [2026-11-07] Les en-têtes de sécurité sont posés deux fois : par `public/.htaccess` chez l'hébergeur, par `Noyau` en secours et en local. Un test exige que les deux copies restent identiques.

## Archivées le 2026-11-24
- [2026-11-12] IA réelle en local d'abord (Xavier : « ensuite on ajoutera juste les clés API ») : Whisper turbo dans WSL sur le processeur, Qwen 27B dans l'Ollama de Windows (variante `offregen-qwen3.8`, contexte 16 384 jetons). Le PHP parle le protocole d'Infomaniak ; passer au serveur ne change que le `.env`. Détail : `docs/plans/2026-11-07_voix-offre/10-ia-locale/`.
- [2026-11-12] Dépendances : `symfony/http-client` et `symfony/mime` 7.4. Hors dépôt : `python3.12-venv` dans Ubuntu, faster-whisper 1.2.1 dans `~/offregen-ia`.
