# PROJECT_ROADMAP

## Project identity
- Name : offreGen
- Goal : application d'offres vocales pour artisans. L'artisan décrit les travaux à voix haute pendant la visite, l'application produit une offre à partir de son catalogue, il la corrige à la voix ou à l'écran.
- Owner : Xavier Frelechoz (XtraDev)
- Created : 2026-11-05
- Last updated : 2026-11-25

## Executive summary
- L'application tourne de bout en bout sur le site de test (`easy-offres.xtradev.ch`) : dictée ou import, Whisper et Qwen 27B chez Infomaniak, offre rangée par pièce, corrections, catalogue, clients, PDF, facture avec QR-facture. Suite verte et PHPStan sans erreur au déploiement de `e046e57` (2026-11-23). La page d'accueil de Dicta est en ligne sur le test (`/`, `/decouvrir`).
- Tout est sur la branche `chantier-voix-offre` (PR #3, ouverte le 12.11, 77 commits hors de `main`). Rien n'est recetté par Xavier : une recette regroupée l'attend.
- Chantier en cours : la mise en production (`docs/plans/2026-11-17_mise-en-production/`), 13 points bloquants, plan en 9 étapes. Première production visée : comptes d'essai plafonnés créés par Xavier ; le paiement suit vite (demande de Xavier du 18.11).
- Premier artisan intéressé : Luigi, peintre, 20 offres d'essai (`docs/terrain/2026-11-17_luigi-peintre.md`).

## Context
- Données : adresses et état des logements des clients de l'artisan, transmis à Infomaniak. Une dictée peut contenir par accident des données sensibles (santé d'un occupant, par exemple).
- Hébergement Infomaniak, serveur Cloud. IA chez Infomaniak (produit 357) : Whisper pour la transcription, Qwen 3.8 27B (bêta) pour l'extraction.
- Dépôt GitHub privé `xtradev1/offreGen`, plan Free. Copie de travail dans WSL (`~/offreGen`) ; la copie de SynologyDrive n'est plus tenue à jour.
- Suivi du temps : projet Origin 44. Chantier de la voix à l'offre : tâches 315, 400, 403, 414, 422, 428, 434, 438, 442, 447, 452. Mise en production : tâche 458, sous-tâche 459. Responsive : tâche 478. Options et variantes : tâche 547 (sous-tâches 548 à 554 ; 499 en doublon d'estimation, signalé). Prospects : tâche 616.
- Règles opposables : `CODING_RULES.md`, `docs/SECURITE.md`, `docs/DEPLOIEMENT.md`.

## Decisions
- Décisions du 2026-11-05 au 2026-11-07 (pile, socle, maquette, environnement, report de l'audit externe) → `docs/roadmap-archive-decisions.md`.
- Décisions du 12.11 (IA réelle en local d'abord, dépendances `symfony/http-client` et `symfony/mime`) → `docs/roadmap-archive-decisions.md`.
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
- [2026-11-20] Responsive (téléphone, tablette, PC) : décisions de Xavier en cinq tours de maquette (R1 à R10, B2 à B4, points 1 à 9, 2b, 3 A, 4a B, 4b, 5, 7a), consignées telles quelles dans `docs/plans/2026-11-19_responsive/ETAT.md`. Notables : champs de l'offre à 16 px (zoom de l'iPhone), cibles de 48 px, facture annulée avec PDF « Annulée » et date d'envoi, pastilles de marque à 7:1.
- [2026-11-23] Options et variantes (décisions de Xavier, planche des douze modèles) : une variante retenue efface ses sœurs sur le document, l'écran garde la ligne remplacée barrée ; facture : ligne « Payé jusqu'au …, escompte de 2 % » sous le total, offre : phrase du paiement seule ; la facture garde la pièce (`facture_ligne.piece`) ; PDF resserré gardé s'il gagne une page, page 2 de signature gardée sinon. Dépendance : faces italiques IBM Plex (OFL) dans `public/fonts/`. Détail : `docs/plans/2026-11-22_options-variantes/ETAT.md`.
- [2026-11-24] **Sans rechargement** (choix D1 à D10 du 23.11) : morceaux de page découpés dans le noyau, script maison `fluide.js`, file d'envoi à clé unique (table `envoi`), service worker (copies par compte, 14 jours au plus), fichiers versionnés par l'empreinte de leur contenu (`fichier()`). Les fenêtres de l'offre et « Prendre » attendent le serveur (choix A de Xavier, 24.11). Détail : `docs/plans/2026-11-23_sans-rechargement/ETAT.md`.
- [2026-11-23] Commerciaux (Xavier) : deux codes par commercial. Le code de parrainage donne un mois gratuit à l'artisan et la commission au commercial (pourcentage réservé aux commerciaux). Le code personnel ouvre un seul compte, payant : prix plein sous 5 artisans payants, moitié dès 5, offert dès 10, aucune commission pour personne. Échange de codes entre commerciaux : fraude des deux, repérée au passage au paiement (IBAN, IDE, adresse, téléphone). Espace de démonstration marqué « Démonstration », envoi bloqué ; 200 dictées de démonstration par mois et par commercial, alerte à Xavier à 80 %, ajouter ou bloquer d'un clic. Règles C1, C9, C13 à C17 de `docs/commerciaux/PROJET-regles-commission.md`, tâche Origin 555.
- [2026-11-24] Dictée et vocabulaire du métier (sous-chantier 30, recette MP-A8) : le catalogue de l'artisan part en `prompt` de Whisper (produits les plus utilisés d'abord, 500 caractères au plus). Écartés après mesure : un lexique par métier écrit à la main, une couche phonétique en code, une règle de « réflexion » dans la consigne (54 produits visés sur 54 avec et sans elle). Détail : `docs/plans/2026-11-07_voix-offre/30-dictee-mal-comprise/ETAT.md`.
- [2026-11-24] **Prospects** (réponses de Xavier à la maquette, P1 à P9, puis sa décision du même jour) : un catalogue pour que les commerciaux s'organisent entre eux, sans ordres ; chacun prend lui-même une fiche ; un collègue voit le nom, la localité, le métier, qui la suit et depuis quand, sans téléphone ni notes (P2 = A) ; les notes suivent la fiche (P6 = A) ; la liste ne donne aucun droit, la commission reste réglée par C1. Une fiche reste suivie tant qu'on ne la lâche pas, sans plafond, « pour l'instant » (remplace P3 = B et le plafond de 30). À confirmer : un compte d'un collègue se voit en cherchant son nom (exception à « le commercial ne voit que ses artisans » du 19.11). Détail : `docs/plans/2026-11-24_prospects/ETAT.md`.

## Current state
### Done
- Livré avant le 2026-11-12 (socle, maquette, environnement, sous-chantiers 01 et 10) → `docs/roadmap-archive-done.md`.
- [x] Sous-chantiers 11 à 18, 21, 22, 24, 25, 28 livrés en code sur la branche, déployés sur le test (dernier code : 9fe1570, sonde verte). État de chacun : `docs/plans/2026-11-07_voix-offre/ETAT.md` et les `ETAT.md` des sous-chantiers, remis à jour le 2026-11-18.
- [x] Responsive (tâche 478) : cinq tours livrés sur le test (`57068d7`, sonde verte), 7a validé, « tout bon pour l'instant » (Xavier, 2026-11-22). Détail : `docs/plans/2026-11-19_responsive/ETAT.md`.
- [x] Inventaire de ce qui manque avant la production, plan en 9 étapes, recette regroupée (`docs/plans/2026-11-17_mise-en-production/`).

### In progress
- [ ] Options et variantes (tâche 547) : livré en code et déployé sur le test (`e046e57`, sonde verte), planche close. Reste avant la mise en service : le banc sur les vraies dictées de Xavier (fiches 21 à 28), puis la clôture du chantier. Détail : `docs/plans/2026-11-22_options-variantes/ETAT.md`.
- [ ] Mise en production : étapes 1 et 2 faites, contrôles GitHub en place. Le 18.11 : courriels, liens d'accès et mot de passe, droit administrateur, purge écrite et éteinte, alertes, dictée à l'abri, livrés en code et revus (588 tests), commit `475ef57` déployé sur le test (`PLAN-completer.md`, `REVUE.md`). Écrans en attente de la marque (gate visuel). Le 20.11 : page d'accueil de Dicta validée par Xavier (« je te valide le tout ») et déployée sur le test, connexion en carte sur grand écran, `/decouvrir`, logo de l'application vers le site (`2091e9b`).
- [ ] Sans rechargement (tâche 593) : livré et déployé sur le test (`93a61d1`, sonde verte), recette de Xavier attendue (`RECETTE.md`), deux choix ouverts (mention dans la politique de confidentialité, durée de garde). Détail : `docs/plans/2026-11-23_sans-rechargement/ETAT.md`.
- [ ] Dictée mal comprise (sous-chantier 30, Origin 514) : lots 1, 2 et 8 faits ; le lot 8 (catalogue en `prompt` de Whisper) est en service sur le test (`8bb25b1`, sonde verte). Reste : la dictée A6 refaite par Xavier, puis les lots 3 à 7 (fenêtre de choix, verdict, jauge, revue). Détail : `docs/plans/2026-11-07_voix-offre/30-dictee-mal-comprise/ETAT.md`.
- [ ] Prospects, le catalogue des commerciaux (`docs/plans/2026-11-24_prospects/`, copie `~/offreGen-prospects`, Origin 616) : rubrique « Prospects » de l'administration en service sur le test (`ec8bab0`, sonde verte, migration `Version20261124210001`), fiche gardée jusqu'à ce qu'on la lâche, sans plafond. Gate prod ouvert : recette de Xavier attendue (`RECETTE.md`, sept scénarios). Les vrais prospects attendent le site de production.
- [ ] Documents et réglages (`docs/plans/2026-11-24_documents-reglages/`, copie `~/offreGen-documents`, Origin projet 44, tâche à créer sur accord de Xavier) : choix de Xavier L1 à V3 appliqués. « Phrases du document » devient une rubrique des réglages, hors de l'assistant ; filigrane « Aperçu » retiré de Facturer et du brouillon ; une variante reprend la quantité et l'unité de la ligne remplacée, et un produit d'une autre unité passe dans « Relire ». En service sur le test (`2ddda06`, sonde verte, aucune migration), recette de Claude conforme. Gate prod ouvert : recette de Xavier attendue (`RECETTE.md`, quatre scénarios).

### Next
- [ ] Recette regroupée par Xavier sur le test (`docs/plans/2026-11-17_mise-en-production/RECETTE.md`).
- [ ] Fusion de la PR #3 dans `main`, sur accord de Xavier, après revue et recette.
- [ ] Xavier : site de production, base, domaine, boîte d'envoi, produit IA et accès SSH dédiés ; support de sauvegarde ; textes légaux.
- [ ] Paiement (Stripe), juste après la première production : compte Stripe et situation TVA de Xavier.
- [ ] Corpus de vraies dictées (sous-chantier 29) ; journal des appels IA (sous-chantier 26).
- [ ] Convention de sous-traitance (art. 9 LPD) prête avant le premier artisan pilote.
- [ ] Page d'accueil avant la production : pages légales (mentions, protection des données, conditions), vraies vignettes des douze modèles, réglage d'envoi de courriels du test (sinon les demandes d'essai ne partent pas).
- [ ] Commerciaux (Origin 555) : règles C13 à C17 de `docs/commerciaux/PROJET-regles-commission.md` à coder (espace de démonstration, plafond de 200 dictées, compte personnel, remise). Avant le code : réponses de Xavier sur les questions 12 et 14 du juriste, l'effet du blocage sur les démos ouvertes, R3 et R4 (remise à zéro par le commercial, 30 démos par jour).

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
- Délai après la visite : la file d'attente de Whisper chez Infomaniak varie (47 s puis 5 min pour le même fichier, 17.11), non facturée et non mesurée de façon fiable. Le 2026-11-23, Xavier a trouvé la transcription lente sur le test (dictée avec options), sans mesure.
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
- 2026-11-20 : plusieurs sessions dans `~/offreGen` ; une session a construit sur les fichiers non commités d'une autre puis commité sans eux. `~/offreGen` garde en double les fichiers non commités des tours 1 à 4 du responsive, mêlés à d'autres : ne pas les commiter depuis là. Une copie propre par chantier.
- 2026-11-24 : le téléphone garde 14 jours des pages avec noms, adresses et prix des clients : à écrire dans la politique de confidentialité (question ouverte à Xavier). « Se déconnecter » sans réseau efface ces copies puis tombe sur la page d'erreur de Chrome : noté, pas corrigé.
- Obsolètes (résolus) : accès de Claude au test (accordé le 14.11) ; jeton Infomaniak (fourni le 14.11) ; format `data` et envoi multipart de Whisper (vérifiés le 14.11) ; ffmpeg sur le test (déposé le 14.11).
- 2026-11-24 : prospects. Les fiches portent des données personnelles (raisons individuelles, personnes de contact, notes libres) : mention dans la politique de confidentialité et durée de garde à fixer avec le lot juridique (Origin 463), rien n'efface une fiche aujourd'hui. Registre du commerce et registre IDE hors de la version 1 (accès non confirmé, registre IDE annoncé comme remplacé par le BER). Une démo finie supprimée détache sa fiche, qui perd la trace de la démo. Détail : `docs/plans/2026-11-24_prospects/REVUE.md` et `RECETTE.md`. Depuis le 24.11, ni plafond ni libération à date : rien n'empêche un commercial de garder 100 fiches ; question posée à Xavier (l'administrateur doit-il pouvoir libérer une fiche ?).
- 2026-11-24 : documents. Le filigrane « Aperçu » ne marquait que l'aperçu d'une facture pas encore créée ; il est retiré. Les PDF d'une démo ne portent aucune marque « Démonstration » : une démo sort une facture sans marque, comme avant ce retrait. Suite : Origin 557 et 509. Détail : `docs/plans/2026-11-24_documents-reglages/ETAT.md`.

## Archives
- Décisions anciennes → `docs/roadmap-archive-decisions.md`
- Livré archivé → `docs/roadmap-archive-done.md`

## Session log
- Entrées plus anciennes → `docs/roadmap-archive-2026-11.md`.
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
- [2026-11-22] **Responsive livré sur le test**
  - Audit, cinq tours de maquette et de décisions, corrections au téléphone, sur tablette et au PC ; recette par l'écran, 980 tests.
  - Reporté depuis une copie propre après le commit des modèles ; 2a et 2b gardés dans leur version du chantier des modèles.
  - Détail : `docs/plans/2026-11-19_responsive/ETAT.md`.
- [2026-11-23] **Options et variantes déployées sur le test**
  - Options, variantes, choix du client, facture qui suit ; italique, escompte sous le total, variantes sœurs retirées, pièces sur la facture, PDF resserré.
  - Planche des douze modèles validée par Xavier ; contrôle de compatibilité vert (`f13de2e`, 1254 tests sur la base migrée), `e046e57` déployé, sonde verte.
  - Détail : `docs/plans/2026-11-22_options-variantes/ETAT.md`.
- [2026-11-23] **Application sans rechargement déployée sur le test**
  - Lots 1 à 5, revue (4 bloquantes, 19 importantes corrigées), comparatif avant / après : sur réseau de chantier, médiane à l'écran 2,1 s → 632 ms, 31 → 10 rechargements, gestes gardés hors réseau.
  - Banc et service worker corrigés en route ; `93a61d1` déployé, sonde verte. Recette de Xavier attendue.
  - Détail : `docs/plans/2026-11-23_sans-rechargement/ETAT.md`.
- [2026-11-23] **Commerciaux : codes, compte personnel, plafond des démos**
  - Deux codes par commercial (parrainage avec commission, personnel sans commission) ; compte personnel payant, moitié dès 5 artisans payants, offert dès 10 ; échange de codes repéré au paiement.
  - Espace de démonstration marqué, envoi bloqué, 200 dictées par mois, alerte à 80 %. Projet de règles v0.3, commit `9cf9b49` (document seul, rien à déployer).
  - Tâche Origin 555. Détail : `docs/commerciaux/PROJET-regles-commission.md`.
- [2026-11-24] **Dictée : vocabulaire du métier (sous-chantier 30, lot 8)**
  - Recette MP-A8 : « à laquer » transcrit « à la quai ». Le catalogue de l'artisan part en `prompt` de Whisper : travertin, plinthes et crépi corrigés au banc, sans régression ; « à la quai » reste.
  - Lexique par métier, couche phonétique et règle de réflexion mesurés puis écartés : le modèle d'extraction rattrape déjà les mots mal transcrits.
  - `8bb25b1` en service sur le test, sonde verte. Détail : `docs/plans/2026-11-07_voix-offre/30-dictee-mal-comprise/ETAT.md`.
- [2026-11-24] **Prospects : rubrique de l'administration en service sur le test**
  - Proposition, maquette validée par Xavier (P1 à P9), SPEC, plan, code, revue lourde (trois relecteurs, onze défauts corrigés), recette cliquée sur le test ; un défaut de fuseau trouvé par cette recette et corrigé.
  - Commits `aa4d39f` et `5d60a46` déployés, sonde verte. Recette de Xavier attendue.
  - Détail : `docs/plans/2026-11-24_prospects/ETAT.md`.
- [2026-11-24] **Prospects : fiche gardée jusqu'à ce qu'on la lâche, sans plafond**
  - Décision de Xavier : plus de libération 7 jours après un pas manqué, plus de plafond de 30 ; un pas se date dans l'année. Tâche Origin 616 créée.
  - Tests rouges puis verts, suite complète 1608 tests ; `ec8bab0` déployé, sonde verte, relu en cliquant sur le test.
  - Détail : `docs/plans/2026-11-24_prospects/ETAT.md`.
- [2026-11-24] **Documents et réglages en service sur le test**
  - Maquette et choix de Xavier (L1 à V3), code, revue (six défauts mineurs corrigés), 1550 tests verts ; aucune migration.
  - `2ddda06` déployé, sonde verte ; recette de Claude sur le test conforme sur les quatre scénarios. Non vérifié : la ligne provisoire d'un ajout hors réseau. Recette de Xavier attendue.
  - Détail : `docs/plans/2026-11-24_documents-reglages/ETAT.md`.
