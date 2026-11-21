# PROJECT_ROADMAP

## Project identity
- Name : offreGen
- Goal : application d'offres vocales pour artisans. L'artisan décrit les travaux à voix haute pendant la visite, l'application produit une offre à partir de son catalogue, il la corrige à la voix ou à l'écran.
- Owner : Xavier Frelechoz (XtraDev)
- Created : 2026-11-05
- Last updated : 2026-11-21

## Executive summary
- L'application tourne de bout en bout sur le site de test (`easy-offres.xtradev.ch`) : dictée ou import, Whisper et Qwen 27B chez Infomaniak, offre rangée par pièce, corrections, catalogue, clients, PDF, facture avec QR-facture. 981 tests verts le 2026-11-20, PHPStan sans erreur. La page d'accueil de Dicta est en ligne sur le test (`/`, `/decouvrir`).
- Tout est sur la branche `chantier-voix-offre` (PR #3, ouverte le 12.11, 77 commits hors de `main`). Rien n'est recetté par Xavier : une recette regroupée l'attend.
- Chantier en cours : la mise en production (`docs/plans/2026-11-17_mise-en-production/`), 13 points bloquants, plan en 9 étapes. Première production visée : comptes d'essai plafonnés créés par Xavier ; le paiement suit vite (demande de Xavier du 18.11).
- Premier artisan intéressé : Luigi, peintre, 20 offres d'essai (`docs/terrain/2026-11-17_luigi-peintre.md`).

## Context
- Données : adresses et état des logements des clients de l'artisan, transmis à Infomaniak. Une dictée peut contenir par accident des données sensibles (santé d'un occupant, par exemple).
- Hébergement Infomaniak, serveur Cloud. IA chez Infomaniak (produit 357) : Whisper pour la transcription, Qwen 3.8 27B (bêta) pour l'extraction.
- Dépôt GitHub privé `xtradev1/offreGen`, plan Free. Copie de travail dans WSL (`~/offreGen`) ; la copie de SynologyDrive n'est plus tenue à jour.
- Suivi du temps : projet Origin 44. Chantier de la voix à l'offre : tâches 315, 400, 403, 414, 422, 428, 434, 438, 442, 447, 452. Mise en production : tâche 458, sous-tâche 459.
- Règles opposables : `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`.

## Decisions
- Décisions du 2026-11-05 au 2026-11-07 (pile, socle, maquette, environnement, report de l'audit externe) → `docs/roadmap-archive-decisions.md`.
- [2026-11-07] Traitement d'un audio : démarré tout de suite après l'import, puis cron chaque minute pour vérifier et terminer ce qui n'a pas été fait (Xavier).
- [2026-11-07] **Entorse au socle :** de vraies visites au banc de mesure, avec l'accord des clients, en plus de visites rejouées (Xavier : « on peut bosser avec de vraies données, c'est le mieux »). Compensations : stockage dans WSL hors dépôt et hors SynologyDrive, envoi seulement à Infomaniak, Claude ne lit ni l'audio ni la transcription d'une vraie visite, suppression en fin de banc. Conditions dans la SPEC du chantier.
- [2026-11-07] Dépendances ajoutées : `symfony/security-core` et `symfony/password-hasher` figées en 7.4, parce que `security-csrf` 7.4 les tirait en 8.1, hors de la branche LTS. `twig/twig` 3, PHPUnit 12, PHPStan 2.
- [2026-11-07] Dépendances ajoutées au sous-chantier 01 : `doctrine/migrations` 3.9 (DBAL 4), `monolog/monolog` 3, `symfony/clock` 7.4 (horloge que les tests figent). `symfony/string` et `symfony/console`, tirés par les migrations, figés en 7.4. Audit sans alerte.
- [2026-11-07] Les en-têtes de sécurité sont posés deux fois : par `public/.htaccess` chez l'hébergeur, par `Noyau` en secours et en local. Un test exige que les deux copies restent identiques.
- [2026-11-12] IA réelle en local d'abord (Xavier : « ensuite on ajoutera juste les clés API ») : Whisper turbo dans WSL sur le processeur, Qwen 27B dans l'Ollama de Windows (variante `offregen-qwen3.8`, contexte 16 384 jetons). Le PHP parle le protocole d'Infomaniak ; passer au serveur ne change que le `.env`. Détail : `docs/plans/2026-11-07_voix-offre/10-ia-locale/`.
- [2026-11-12] Dépendances : `symfony/http-client` et `symfony/mime` 7.4. Hors dépôt : `python3.12-venv` dans Ubuntu, faster-whisper 1.2.1 dans `~/offregen-ia`.
- [2026-11-14] **Entorse au protocole de déploiement :** le site de test (`easy-offres.xtradev.ch`) est déployé par Claude en SSH avec `outils/deployer-test.sh`, en attendant le workflow GitHub (sous-chantier 02). Mêmes étapes que le workflow, sans maintenance ni sauvegarde (données inventées seulement). Déclencheur de fin : le workflow GitHub en place. Accès de Claude au test accordé par Xavier tant que les données sont inventées.
- [2026-11-14] ffmpeg statique 7.0.2 (johnvansickle.com, août 2024) déposé hors du site de test : le serveur n'a pas ffmpeg (logo, AMR et 3GP, durée minimale). Version sans correctifs depuis 2024 sur des fichiers venus de l'extérieur : à remplacer par une version suivie avant la production.
- [2026-11-17] Acquisition, cadre posé par Xavier : prix visé de 35 à 50 CHF par mois ; objectifs de 300 à 400 abonnés fin avril 2027 et de 1000 fin 2027 ; marketing limité en heures ; pas de budget de pub de 10 000 CHF par mois. Idées de Xavier : traduire le produit en allemand (il le parle) pour couvrir la Suisse sans le Tessin ; élargir plus tard aux autres petits indépendants. Stratégie, seuils et réserves de Claude dans `docs/strategie/acquisition.md`. Tâche Origin 458 « Passage à l'échelle ».
- [2026-11-17] Limites d'une offre (Xavier) : 30 minutes de dictée, 300 postes. Le téléphone garde l'enregistrement jusqu'à ce que l'offre soit traitée (sous-chantier 27).
- [2026-11-17] Compte d'essai : plafond absolu d'offres (`bin/creer-compte --offres=N`, `outils/compte-essai-test.sh`). Catalogue vide au départ, l'artisan crée le sien.
- [2026-11-17] Prix dicté (Xavier) : le catalogue reste par défaut ; une note « Prix dicté » sous la ligne, « Prendre », puis « changer le catalogue oui ou non ».
- [2026-11-17] Offre par pièce (maquette 28 validée) : pièce relevée par un second appel au modèle, sans le catalogue (dans l'appel principal elle faisait régresser l'extraction) ; « Général » en tête, sans sous-total dans le PDF.
- [2026-11-17] Formules, base de discussion (`26-couts-ia/OFFRES-ABONNEMENT.md`) : Solo 44.90 HT pour 50 offres, Pro 89 pour 150, Entreprise 179 pour 300 et 3 comptes, recharge 25 CHF pour 25 offres réservée aux abonnés. Pack sans abonnement écarté (Xavier : « ça casse le système »). Coûts fixes : CHF 400 à 500 par an.
- [2026-11-18] Mise en production : relecture de la connexion et de l'isolation par Fable acceptée par Xavier (« oui va pour fable pour de la relecture »), en plus de la revue par Opus. Domaine envisagé par Xavier : `easy-offre.ch`. Le paiement doit suivre vite la première production.
- [2026-11-18] Identité (Xavier) : le produit s'appelle **Dicta** (`dicta.ch` réservé d'après sa charte), en-tête et boutons anthracite `#23272D`, terracotta `#C4552D` en accent, mot « offre », logo « le cône et la bulle », encore modifiable. Charte, verbatim, jetons CSS et logos dans `docs/identite/`. Reste à l'appliquer à l'application (feuille de style en bleu, nom « offreGen » dans les courriels).
- [2026-11-19] Identité appliquée à l'application (branche `chantier-voix-offre`, non déployée) : couleurs et polices de la charte dans `app.css`, nom par défaut « Dicta » (`APP_NOM`). Dépendance ajoutée : polices Archivo et Inter (licence OFL) hébergées dans `public/fonts/`, parce que la CSP refuse toute police externe. Les tailles de `CODING_RULES.md` (64, 56, 48 px) priment sur la charte, qui a été corrigée.
- [2026-11-20] Page d'accueil de Dicta (variante B, version 3) servie à `/` au visiteur sans session, à `/decouvrir` pour tous ; le logo de l'application y mène. Formulaire « Bon pour essai » : le courriel du visiteur part à `info@xtradev.ch` tant que l'inscription n'existe pas. Premier mois offert, puis 44.90 HT par mois. Détail : `docs/plans/2026-11-17_mise-en-production/ETAT.md`.
- [2026-11-20] **Affirmations maintenues par Xavier contre l'avis du relecteur :** « 100% » dans la croix du tampon, « 100 % conçue en Suisse », « l'une des seules applications de ce genre dont l'IA tourne en Suisse ». À faire relire par un juriste avant la production, avec la croix suisse.

## Current state
### Done
- Livré avant le 2026-11-12 (socle, maquette, environnement, sous-chantiers 01 et 10) → `docs/roadmap-archive-done.md`.
- [x] Sous-chantiers 11 à 18, 21, 22, 24, 25, 28 livrés en code sur la branche, déployés sur le test (dernier code : 9fe1570, sonde verte). État de chacun : `docs/plans/2026-11-07_voix-offre/ETAT.md` et les `ETAT.md` des sous-chantiers, remis à jour le 2026-11-18.
- [x] Inventaire de ce qui manque avant la production, plan en 9 étapes, recette regroupée (`docs/plans/2026-11-17_mise-en-production/`).

### In progress
- [ ] Mise en production : étapes 1 et 2 faites, contrôles GitHub en place. Le 18.11 : courriels, liens d'accès et mot de passe, droit administrateur, purge écrite et éteinte, alertes, dictée à l'abri, livrés en code et revus (588 tests), commit `475ef57` déployé sur le test (`PLAN-completer.md`, `REVUE.md`). Écrans en attente de la marque (gate visuel). Le 20.11 : page d'accueil de Dicta validée par Xavier (« je te valide le tout ») et déployée sur le test, connexion en carte sur grand écran, `/decouvrir`, logo de l'application vers le site (`2091e9b`).

### Next
- [ ] Recette regroupée par Xavier sur le test (`docs/plans/2026-11-17_mise-en-production/RECETTE.md`).
- [ ] Fusion de la PR #3 dans `main`, sur accord de Xavier, après revue et recette.
- [ ] Xavier : site de production, base, domaine, boîte d'envoi, produit IA et accès SSH dédiés ; support de sauvegarde ; textes légaux.
- [ ] Paiement (Stripe), juste après la première production : compte Stripe et situation TVA de Xavier.
- [ ] Corpus de vraies dictées (sous-chantier 29) ; journal des appels IA (sous-chantier 26).
- [ ] Convention de sous-traitance (art. 9 LPD) prête avant le premier artisan pilote.
- [ ] Page d'accueil avant la production : pages légales (mentions, protection des données, conditions), vraies vignettes des douze modèles, réglage d'envoi de courriels du test (sinon les demandes d'essai ne partent pas).

### Blocked
- [ ] Mesure de la qualité réelle (sous-chantier 29).
- reason : il faut les dictées de Xavier (fiches prêtes dans `OffreGen/corpus/FICHES.md`, hors dépôt).
- [ ] Site de production.
- reason : à créer par Xavier dans le Manager Infomaniak ; Claude n'y a aucun accès.

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
- Title : Stratégie d'acquisition
- Link : docs/strategie/acquisition.md
- Notes : discussion du 2026-11-17. Objectifs de Xavier, estimations de Claude (hypothèses, sans mesure), trois portes, canaux, règles de pub, événements.
- Title : Coûts de l'IA, prix, paiement, concurrents
- Link : docs/plans/2026-11-07_voix-offre/26-couts-ia/
- Notes : `PROFILS-USAGE.md`, `PRIX-ABONNEMENT.md`, `OFFRES-ABONNEMENT.md` (17.11). Relevé Infomaniak `GET /1/ai/357/consumptions` : total par jour et par modèle.
- Title : Mise en production
- Link : docs/plans/2026-11-17_mise-en-production/
- Notes : `INVENTAIRE.md` (A1 à A13, B, C, D), `PLAN.md`, `RECETTE.md`, `ETAT.md`.
- Title : Page d'accueil de Dicta, maquette v3
- Link : docs/plans/2026-11-17_mise-en-production/MAQUETTE-site-v3.html
- Notes : planche et page ; la page de l'application est générée hors dépôt (`templates/presentation.html.twig`, `public/css/site.css`, `public/js/site.js`), questions ouvertes S1, R5, R6.

## Risks / points to clarify
- 2026-11-18 : trois concurrents romands en français déjà en ligne (Chiffr, SecretarIA, Opus), moins chers que les CHF 39 prévus ; prix, périmètre et objectif de clients à revoir. Détail : `docs/strategie/concurrents.md`.
- Enregistrement dans l'application web : un navigateur mobile peut couper le micro quand l'écran se verrouille (hypothèse, à tester sur iPhone et Android), et le réseau manque souvent sur un chantier. L'import d'un fichier natif contourne ces deux problèmes. Une heure d'audio pèse environ 14 Mo en Opus à 32 kbit/s, et 115 Mo en WAV 16 kHz mono.
- Import sur iPhone : le glisser-déposer n'existe pas sur téléphone. Le chemin probable passe par « Partager », puis « Enregistrer dans Fichiers », puis le choix du fichier dans la page (hypothèse, à compter au test d'import).
- Enregistrement d'une conversation : si le client parle pendant l'enregistrement, son accord est requis (art. 179ter du Code pénal suisse, à faire confirmer par un juriste). L'application doit le rappeler à l'artisan.
- Délai après la visite : la file d'attente de Whisper chez Infomaniak varie (47 s puis 5 min pour le même fichier, 17.11), non facturée et non mesurée de façon fiable.
- Qualité : 96 % de lignes justes sur le banc inventé ; la seule vraie dictée (Luigi) a donné 5 à 7 lignes justes sur 7. Qwen à température 0 varie d'une passe à l'autre.
- Qwen 3.8 27B est en bêta (« coming_soon ») chez Infomaniak : modèle de secours et réglage de bascule à prévoir.
- GitHub Free : un push direct sur `main` reste possible, et tout job qui cite un secret le reçoit. Les compensations ne sont pas encore prouvées.
- Pilote sans audit externe : le cœur de la sécurité n'est éprouvé que par nos tests et la relecture par Fable.
- Audit externe : prestataire et budget à définir. Ordre de grandeur lu chez un vendeur suisse : CHF 4 500 à 15 000, sans devis.
- Offre figée après l'envoi : rien n'est prévu pour corriger une offre envoyée (nouvelle version, annulation).
- `.htaccess` exécuté sur le test, mais les neuf essais « À prouver » de `docs/DEPLOIEMENT.md` ne sont pas faits.
- Conservation : tranchée le 18.11 (Q1 = A) : audio effacé 30 jours après l'offre, texte gardé ; purge écrite, éteinte tant que `PURGE_AUDIO=1` n'est pas dans le `.env` (décision de Xavier). À écrire dans la politique de confidentialité, avec la dictée gardée en clair dans le navigateur du téléphone.
- Frein de connexion : une connexion réussie ne remet pas à zéro les échecs du même courriel. Raffinement possible (le compteur par adresse IP, lui, ne doit pas se remettre à zéro).
- Acquisition (2026-11-17) : selon Claude, 300 à 400 abonnés fin avril 2027 est irréaliste (40 à 60 plausibles), et 1000 fin 2027 exige un canal de distribution ou une sortie de Romandie. Taille du marché non vérifiée (environ 10 000 entreprises du second œuvre en Romandie, de mémoire) : à compter dans STATENT (Origin 475).
- Allemand : aucune couche de traduction dans le code (constaté le 2026-11-17), qualité de la dictée en dialecte inconnue (banc, Origin 476).
- TWINT chez Stripe est indiqué « B2C » : à confirmer pour des entreprises. TVA de XtraDev (assujetti ou non) à clarifier avec un fiduciaire.
- Domaine : le test s'appelle `easy-offres` (avec un s), la production envisagée `easy-offre.ch` (sans). Une seule orthographe à choisir.
- 2026-11-20 : la phrase de la page d'accueil « Vos dictées ne sont ni conservées ni utilisées pour entraîner ses modèles » (mots de Xavier) est fausse : l'audio reste sur le serveur (`PURGE_AUDIO=0`) et la transcription est gardée. Question S1 ouverte.
- Obsolètes (résolus) : accès de Claude au test (accordé le 14.11) ; jeton Infomaniak (fourni le 14.11) ; format `data` et envoi multipart de Whisper (vérifiés le 14.11) ; ffmpeg sur le test (déposé le 14.11).

## Archives
- Décisions anciennes → `docs/roadmap-archive-decisions.md`
- Livré archivé → `docs/roadmap-archive-done.md`

## Session log
- [2026-11-05] Roadmap initialized. Cadrage, choix de la pile, pose du socle.
- [2026-11-06] Cadrage produit reçu et archivé. Règle d'ergonomie, relecture par Fable, report de l'audit externe, GitHub Free, import audio prioritaire. Premier commit. Maquette v1, réponses de Xavier, maquette v2 (lignes, ajout, ligne libre, majoration, acomptes, catalogue, aperçu), réponses de Xavier, maquette v3 (unité, acompte à l'envoi).
- [2026-11-07] Réponses sur la v3, maquette v4 (acompte à part, offre figée après l'envoi), validée et fusionnée. Environnement WSL installé, clone de travail. Chantier « de la voix à l'offre » ouvert, phase 0.
- [2026-11-07] Chantier : cadrage, découpage en neuf sous-chantiers, sous-chantier 01 enchaîné jusqu'au lot 8 (Xavier : « enchaîne jusqu'au dernier lot »). Défauts trouvés en route : variables d'environnement ignorées sous Ubuntu, en-têtes de sécurité absents hors Apache, purge oubliée pour les échecs, variable PHP avalée par une espace insécable.
- [2026-11-12] Sous-chantier 10 : documentation d'Infomaniak lue, Whisper local au même protocole, extraction par Qwen dans Ollama, test de bout en bout et parcours cliqué sur les vrais services.
- [2026-11-12 au 16] **Sous-chantiers 11 à 25**, écrans, offre, clients, facture, ergonomie, site de test monté (14.11) et branché sur Infomaniak.
  - Détail : `docs/plans/2026-11-07_voix-offre/ETAT.md`.
- [2026-11-17] Stratégie d'acquisition discutée et notée (`docs/strategie/acquisition.md`), tâche Origin 458 créée (19 sous-tâches).
- [2026-11-17] **Luigi, limites, pièces, prix dicté, formules**
  - Réunion avec Luigi ; limites 30 min et 300 postes ; plafond des comptes d'essai ; sous-chantier 28 (pièces) et prix dicté livrés sur le test.
  - Coûts, prix, paiement et formules étudiés. Détail : `26-couts-ia/`, `docs/terrain/2026-11-17_luigi-peintre.md`.
- [2026-11-18] **Mise en production ouverte**
  - Inventaire, plan, recette regroupée ; `ETAT.md` des sous-chantiers remis à jour ; démo du test remise à zéro, compte `essai-recette` créé.
  - Détail : `docs/plans/2026-11-17_mise-en-production/`.
- [2026-11-20] **Page d'accueil de Dicta en ligne sur le test**
  - Page à `/` (visiteur) et `/decouvrir`, formulaire d'essai, connexion en carte, logo de l'application vers le site, scène qui avance aussi avec « Réduire les animations ».
  - Validée par Xavier ; restent la phrase « ni conservées », les pages légales, les vignettes.
  - Détail : `docs/plans/2026-11-17_mise-en-production/ETAT.md`.
