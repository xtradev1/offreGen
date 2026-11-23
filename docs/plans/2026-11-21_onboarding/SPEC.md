# SPEC : onboarding du commercial et de l'artisan

Demande de Xavier, 2026-11-21 : « créer l'onboarding commercial, et l'onboarding des usagers / artisans ».
Cadre donné le même jour : onboarding commercial = l'arrivée d'un commercial ET le parcours de vente qu'il déroule ; artisan = la démo lancée par un commercial et le passage de la démo à l'essai ; maquette d'abord, puis code ; ce chantier reprend ce qui reste des lots 6 et 7 du chantier de l'administration et la tâche Origin 468.

## 1. Problème (constaté dans le code de `origin/chantier-voix-offre`, tête `77954ce`)

Côté artisan :
- Une démo s'ouvre sur « Mes offres » avec deux offres d'exemple. Rien ne dit quoi faire ensuite : l'assistant de bienvenue est noté comme passé à la création d'une démo (`DepotEquipe::poserDemo`), et aucun écran ne guide la première dictée.
- Le guidage existe hors de l'application : la fiche d'essai envoyée à la main (`docs/terrain/2026-11-18_essai-artisans.txt`, huit étapes, questions fréquentes). Un commercial qui n'est pas Xavier n'a ni cette fiche ni la manière de s'en servir.
- `Comptes::passerEnEssai` ne relance pas l'assistant. Un artisan passé à l'essai n'est jamais invité à saisir son entreprise, son adresse, sa TVA ni son IBAN : sa première vraie offre part avec le nom provisoire « Démo <métier> » s'il ne va pas lui-même dans Plus, Réglages.
- `ImportControleur` ne lit jamais `demo_fin` : après 7 jours, une démo dicte encore tant qu'il lui reste des offres. Pas d'écran de fin, pas de fenêtre de rappel, pas de « Demander un appel » (lot 6 du plan de l'administration, non construit).
- Aucune page d'aide dans l'application (aucun gabarit d'aide sous `templates/`).

Côté commercial :
- Un membre reçoit un lien d'accès, choisit son mot de passe et arrive sur `/admin` : liste de ses artisans, « Créer une démo », QR, message. Rien ne lui explique son rôle, la démo en trente secondes, ce qu'il peut promettre ni ce qui lui vaut une commission.
- Aucun suivi : pas de « À rappeler », pas de « Bientôt la fin », pas d'activité de la démo (a-t-il dicté ?), tableau de bord vide (lot 7, non construit).
- L'argumentaire existe en morceaux : `docs/strategie/usp.md` (formulations permises et interdites), `docs/strategie/acquisition.md` section 12 (questions du rendez-vous), `docs/terrain/2026-11-18_questions-adequation.md`. Aucun n'est accessible au commercial.

## 2. Utilisateurs

| Qui | Rôle dans le code | Ce qu'il fait dans ce chantier |
|---|---|---|
| Commercial | `Role::COMMERCIAL`, portée limitée à ses artisans | arrive dans l'équipe, apprend le produit, mène un rendez-vous, lance une démo, suit et relance |
| Administrateur (Xavier) | `Role::ADMINISTRATEUR` | invite le commercial, voit tous les suivis |
| Équipe Support | remplace le modérateur (décision T5 du chantier support) | hors de ce chantier : « Aide et questions » et le circuit des questions |
| Prospect en démo | compte `demo = 1`, `demo_fin` posé, entré par `/d/{jeton}` | découvre, dicte, demande un appel ou passe à l'essai |
| Artisan à l'essai | compte `demo = 0`, plafond total (`offres_max`) | règle son entreprise et ses prix, envoie sa première vraie offre |

## 3. Périmètre (mis à jour le 2026-11-22, après les maquettes v2 à v6)

Le détail des écrans est dans les maquettes ; les décisions et les mots exacts de Xavier dans `ETAT.md` ; les lots dans `PLAN.md`.

### A. Arrivée du commercial
- A1. Trois écrans de bienvenue au premier passage dans `/admin`, puis un guide à anneau (« cliquez ici ») jusqu'à sa démo d'entraînement (O11).
- A2. Démo d'entraînement : une vraie démo, marquée « entraînement », hors limite de 10 par jour, sans `apporte_par`, avec retour direct dans son espace sans mot de passe (O12, variante B).
- A3. Aide à la vente dans `/admin`, imprimable : la démo au rendez-vous, les questions, les objections (prix annoncé, 99 % et import à trancher dans la page des textes), le message de démo. « Ce qu'on ne dit pas » en attente (O31).

### B. Suivi du commercial (reprend les lots 6 et 7 de l'administration)
- B1. Démos en cours et leur activité, « Pas encore dicté » après 48 heures ; chiffres : amenés, à l'essai, abonnés (O10, O19).
- B2. Sa part seulement : 10 % des paiements arrivés, hors TVA, l'annuel sur une ligne « En plus » le mois où il arrive, détail par paiement ; taux propre par commercial fixé dans `/admin`, valable pour les paiements à venir (O32 à O36). Après le lot B3 de l'administration.

### C. Démo, côté prospect
- C1. Guide à anneau à l'ouverture d'une démo et d'un essai sans démo : le micro, les conseils (anneau sur le bouton seulement, O21), « Terminer », l'attente sans promesse de courriel, les repères 1, 2, 3 de la première offre et « Compris » (O13, O14).
- C2. Fin de démo : fenêtre des deux derniers jours, écran de fin, dictée et import refusés après `demo_fin`.

### D. Passage à l'essai
- D1. Mise en route en cinq étapes, « Passer le guide » en haut : entreprise et IBAN, TVA en quatre boutons, logo, modèle et couleur en blocs, prix un par un avec le prix de la démo proposé (O15, O28, O34). Le guide de l'artisan ne se rejoue pas (O1).
- D2. Avertissement avant l'envoi d'une offre qui porte encore le nom provisoire, « Envoyer quand même » (O7, O16).
- D3. « Demander de l'aide » mène à « Aide et questions » du chantier support (T11).

### E. Mesure
- E1. Première dictée, première offre générée, première offre envoyée, passage à l'essai : lus dans les tables existantes (`enregistrement`, `offre.envoyee_le`, `journal_admin`), sans table neuve.

## 4. Hors périmètre

| Exclu | Pourquoi |
|---|---|
| Inscription à l'essai par le site, formule, mois gratuit, paiement | lot B de l'administration (B1 à B5), préalables de Xavier non fournis |
| Calcul et versement des commissions | règle non fixée par Xavier ; le chantier montre seulement ce que le code note déjà (`apporte_par`) |
| Arrivée du modérateur et de l'administrateur | aucun recrutement annoncé ; « À aider » suffit au modérateur |
| Formation en présentiel, vidéo de démonstration | tâche 472, hors application |
| Parrainage | tâche 470 |
| Allemand | tâche 477, après le banc de dictée |
| Suppression des démos au 30e jour | lot 8 de l'administration, sans lien avec l'onboarding ; il y reste |
| Courriels de relance automatiques au prospect (J+1, J+3) | non demandés ; Xavier veut « rappeler sans forcer » (maquette v3) ; à rouvrir si les démos restent sans dictée |
| « Aide et questions », circuit des questions, « Demander un appel » | chantier support, décision T11 du 2026-11-22 |
| « Ce qu'on ne dit pas » | O31 : Xavier ne la traite pas maintenant |
| « À aider » du modérateur, tableau de bord de l'administrateur | le rôle de modérateur disparaît (T5) ; l'administrateur voit tous les suivis dans `/admin/suivi` |
| Paiements, règle complète de commission | lot B3 de l'administration ; Xavier écrit la règle ailleurs (O33) |

## 5. Critères de succès

- Un commercial invité, sans aide de Xavier, ouvre son espace, fait sa démo d'entraînement et lance une démo pour un prospect en moins de dix minutes (parcours cliqué au téléphone, chronométré).
- Un prospect ouvre le lien d'une démo et lance sa première dictée en moins de soixante secondes, sans lire la fiche d'essai (parcours cliqué).
- Après le 7e jour, une démo refuse dictée et import, montre l'écran de fin et « Demander un appel » (test automatique sur l'horloge).
- Le suivi d'un commercial montre ses démos et seulement les siennes ; hors portée, 404 (test d'isolation).
- Un artisan passé à l'essai voit l'assistant ; sa première offre envoyée porte son entreprise et plus « Démo » (test).
- Chaque texte vu par un prospect ou un commercial est relu par `relecteur-xtradev` jusqu'à « Prêt ».
- Recette de Xavier sur le site de test, au téléphone et sur PC.

## 6. Contraintes

- `CODING_RULES.md` : pouce, 48 px par cible, 64 px pour la barre du bas, un seul bouton principal par écran ; charte Dicta (`docs/identite/`).
- `docs/strategie/usp.md` : formulations permises et interdites, opposables au kit de vente.
- Existant à réutiliser : `ReglagesControleur` (assistant, étapes), `_ruban_demo.html.twig`, `Demos`, `LiensAcces`, `PorteeEquipe`, `JournalAdmin`, `DepotEquipe`, `vos_prix.html.twig`, table `demande_appel` (migration du lot 1, à vérifier au plan).
- Isolation : toute nouvelle route `/admin` passe par `PorteeEquipe` et reçoit son test d'isolation ; revue lourde en phase 7 (droits et jetons, surface sensible du profil).
- Copie à soi `~/offreGen-onboarding`, base `offregen_onboarding_test`. `public/js/app.js` et `public/css/app.css` sont modifiés par d'autres sessions : les styles neufs vont dans un fichier à part, comme `demo.css` et `admin.css`.
- Recette sur le site de test, jamais en local.

## 7. Risques et points à trancher

1. Aucun commercial n'est recruté à ce jour (rien dans le dépôt ni dans Origin). Construire l'arrivée d'un commercial avant le premier, c'est deviner ses besoins. Proposition : A3 (kit) et B (suivi) d'abord, ils servent Xavier dès aujourd'hui ; A1 et A2 en dernier lot, à couper si personne n'arrive.
2. Démo d'entraînement (A2) : elle compte dans la limite de 10 démos par jour du commercial et dans `apporte_par`. Proposition : une démo marquée « entraînement », hors limite, sans commission, supprimée avec les autres.
3. Repères de la première offre (C2) : Xavier veut « vraiment simple ». Trois repères montrés une fois, ou aucun et seulement la page d'aide ? Proposition : trois, avec un seul bouton « Compris ».
4. Assistant au passage à l'essai (D1) : obligatoire avant la première offre envoyée, ou avec « Plus tard » comme aujourd'hui ? Proposition : « Plus tard » gardé, et l'envoi d'une offre bloqué tant que l'entreprise porte le nom provisoire « Démo <métier> ».
5. Kit de vente (A3) : dans `/admin` seulement, ou aussi en PDF pour un commercial sans téléphone au rendez-vous ? Proposition : la page, imprimable par le navigateur.
6. Commission : le commercial voit-il ses chiffres (amenés, passés à l'essai) ? Proposition : oui, sans montant tant que la règle n'existe pas.
7. Chevauchement : le chantier de l'administration (`~/offreGen-admin`, dernier commit le 2026-11-20 à 16:15) garde-t-il une session en cours sur les lots 6 et 7 ? Si oui, deux sessions écrivent les mêmes fichiers. À confirmer par Xavier avant la phase 4.

## Annexe : périmètre initial du 2026-11-21, remplacé par la section 3

### A. Arrivée du commercial
- A1. Premier lancement de l'espace commercial : trois écrans courts au premier passage sur `/admin` (son rôle et ce qu'il voit ; une démo en trente secondes ; son suivi et ce qui est noté « amené par »), puis jamais plus, rouvrables depuis l'aide.
- A2. Démo d'entraînement : à la fin du premier lancement, le commercial ouvre une démo à son nom et dicte une visite, pour connaître le produit avant de le montrer.
- A3. Kit de vente dans `/admin` (page « Aide à la vente », imprimable) : la démonstration en deux minutes, le déroulé d'un rendez-vous (questions de `acquisition.md` section 12), les objections et leurs réponses (prix, données en Suisse, erreurs de l'IA, accord du client pour l'enregistrement), ce qu'on ne promet pas (`usp.md`), le message de démo.

### B. Parcours de vente outillé (reprend les lots 6 et 7 de l'administration)
- B1. « Demander un appel » dans la démo (ruban, écran de fin) : la demande arrive dans le suivi du commercial qui a créé la démo, avec un courriel à ce commercial, ou à `ALERTES_A` s'il n'y en a pas.
- B2. Suivi du commercial : ses démos actives avec leur activité (offres dictées, dernière visite, jours restants), « À rappeler », « Bientôt la fin », « C'est fait ».
- B3. « À aider » du modérateur et tableau de l'administrateur : les mêmes signaux, sur tous les artisans.

### C. Démo, côté prospect
- C1. Premier écran d'une démo : un seul message, « dictez votre première visite », avec une phrase d'exemple propre à son métier et le micro mis en avant. Les offres d'exemple sont présentées comme telles.
- C2. Première dictée guidée : avant d'enregistrer, trois conseils (écran allumé, pièce par pièce, prix si on les dit d'habitude) ; pendant l'attente, ce qui se passe ; à l'ouverture de la première offre, au plus trois repères montrés une fois (« À vérifier », « Prix dicté », corbeille).
- C3. Fin de démo : fenêtre à l'ouverture deux jours avant la fin (« passez à l'essai ou continuez », maquette v3 du 2026-11-19), une fois par jour au plus, jamais pendant une dictée ; écran de fin ; dictée et import refusés après `demo_fin`.

### D. Passage à l'essai, côté artisan
- D1. Après le choix du mot de passe, l'assistant de bienvenue s'ouvre sur les seules étapes nécessaires à une vraie offre (entreprise, puis TVA et IBAN), avec « Plus tard ».
- D2. Une liste « Avant votre première vraie offre » dans « Mes offres » : coordonnées, prix vérifiés (lien vers « Vos prix »), logo ; elle disparaît quand tout est fait ou fermée.
- D3. Page d'aide dans « Plus » : les questions fréquentes de la fiche d'essai, le contact, l'import d'un enregistrement du téléphone.

### E. Mesure
- E1. Quatre événements datés par compte, lisibles dans le suivi : première dictée, première offre générée, première offre envoyée, passage à l'essai. Ils servent B2 et la tâche Origin 464.
