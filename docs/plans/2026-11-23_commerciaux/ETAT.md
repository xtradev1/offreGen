# Commerciaux : codes, compte personnel, espace de démonstration

Journal du chantier ouvert le 2026-11-23, à la question de Xavier : « un commercial peut faire des offres pour son petit business à côté, ça lui fait un cadeau de 45 francs par mois ». Ce fichier fait foi pour la suite ; les règles elles-mêmes sont dans `docs/commerciaux/PROJET-regles-commission.md` (version 0.3, C1, C9, C13 à C17).

## Suivi Origin

- Projet 44 (offreGen), tâche 555 « Commerciaux : espace de démonstration, compte personnel, réserve de démos » (17 h, estimation de Claude).
- Sous-tâches : 556 décisions sur la réserve de démos et SPEC (en cours) ; 557 espace de démonstration marqué, envoi bloqué ; 558 quota mensuel, remise à zéro, alerte et ajout ; 559 compte personnel, commission à zéro, remise ; 560 repérage d'un échange de codes ; 561 revue et recette sur le test.
- Liée à la tâche 461 (formules et plafonds, lot B1) et à la sous-tâche 536 (démo d'entraînement).

## État

- Rien n'est codé. Les règles sont écrites et poussées (commit `9cf9b49`), les roadmaps aussi (`2d70436` sur la branche ; celui de `~/offreGen` non commité, par convention).
- Prochain pas : réponses de Xavier aux points ouverts ci-dessous, puis SPEC (sous-tâche 556). Le code suit le lot B (quota mensuel), qui n'existe pas encore.

## Décisions de Xavier

2026-11-23, premier tour (Q1 à Q4) :
- Q1 : l'espace de démonstration du commercial est marqué « Démonstration » sur chaque PDF et ne peut rien envoyer à un client.
- Q2 : son business va sur un compte d'artisan séparé, payant.
- Q3 : remise gagnée, « c'est bien » : 50 % dès 5 artisans payants, offert dès 10 (lu par Claude comme une règle à écrire tout de suite).
- Q4 : deuxième adresse de courriel pour le compte personnel ; la remise vaut pour un seul compte et ne donne aucun pourcentage, même quand deux commerciaux échangent leurs codes.

2026-11-23, second tour :
- Deux codes par commercial. Le code de parrainage donne un mois gratuit à l'artisan et la commission au commercial ; seul un commercial touche un pourcentage. Le code personnel ouvre un seul compte (« compte CASA », compris comme son compte personnel) : prix plein sous 5 artisans payants, moitié dès 5, offert dès 10, aucune commission pour personne.
- Deux commerciaux qui échangent leurs liens commettent une fraude tous les deux. On ne peut pas empêcher l'échange, seulement le repérer au passage au paiement (C17 validé : IBAN, numéro IDE, adresse, téléphone comparés à ceux de l'équipe, signalé à l'administrateur, rien bloqué automatiquement).
- Plafond de démonstration : 200 dictées par mois et par commercial, son espace et les démos de ses prospects ensemble, « on va pas se compliquer la vie avec l'augmentation du plafond ». À 80 % (160), Xavier est prévenu (« un message, un mail, un pop-up ») ; un clic pour ajouter des dictées, un clic pour bloquer le compte « en 2 secondes ». Xavier : « si la personne en 2 jours a utilisé 160, c'est soit un vendeur hors pair, soit un autre souci ».
- Plus tard : statistiques par mois (démos créées, passages à l'essai, taux de succès) pour donner davantage à un bon commercial (« ça ne me dérange pas qu'il ait 1000 par mois »). Pas de hausse automatique pour l'instant.
- Questions 12 et 14 pour le juriste : « je ne suis pas sûr », reposées, sans réponse.

## Constats (code et documents lus le 2026-11-23)

- Aujourd'hui, l'espace artisan d'un membre de l'équipe a 10 offres au total, une seule fois : `Equipe::OFFRES_PAR_DEFAUT = 10` remplit `offres_max`, un plafond total (`src/Compte/Equipe.php`). Le quota par mois n'existe pas (tâche 461, lot B1). Le « cadeau de 45 francs » n'existe donc pas encore ; à 10 offres par mois il vaudrait un cinquième de Solo (50 offres, CHF 44.90).
- Démo d'un prospect (plan, lot 5 non construit) : 10 offres, plus 10 offertes par le commercial, 7 jours actifs, 10 démos par jour et par commercial, réponse 429 au-delà (`docs/plans/2026-11-19_admin-comptes/PLAN-lot-A.md`, lignes 44 et 148).
- `artisan.courriel` est `NOT NULL UNIQUE` : un même commercial a besoin de deux adresses (d'où Q4).
- Coût d'une dictée de démonstration : 1 à 1,5 ct (profils « très léger » et « léger » de `docs/plans/2026-11-07_voix-offre/26-couts-ia/PROFILS-USAGE.md`, estimé, jamais mesuré sur une démo). 200 dictées : 2 à 3 francs par mois ; Xavier avait dit 2 francs.
- Commission à 10 % sur Solo : 4.49 par artisan et par mois. Offrir Solo revaut la commission de 10 artisans.
- L'ancienne C9 classait « inscription faite par le commercial lui-même » en fraude : corrigé en v0.3 (compte personnel ouvert avec un autre code que le code personnel).
- Le site de test n'envoie aucun courriel (`.eml` dans `partage/var/courriels`) : l'alerte à 80 % doit aussi se voir dans l'administration.
- Le mois gratuit du code de parrainage double le mois gratuit de tout essai (lot B) : sans décision, le code n'apporte rien à l'artisan.

## Proposé par Claude, en attente de Xavier

- Alerte : bandeau dans l'administration avec « Ajouter » et « Bloquer », plus courriel à l'adresse d'alerte en production ; le message donne la vitesse (« Nina : 160 sur 200, en 2 jours »).
- Au plafond : l'espace du commercial refuse les dictées et il ne crée plus de démo ; les démos déjà ouvertes continuent jusqu'à leurs 20 offres, pour qu'aucune ne s'arrête devant un prospect.
- Compteur au mois civil.
- R3 : le commercial remet lui-même son espace à zéro (catalogue et offres d'exemple neufs, activité au choix, compteur inchangé ; `Comptes::reinitialiserDemo` existe). Ouvrir une offre d'exemple ne consomme rien.
- R4 : 30 démos par jour au lieu de 10 (une démo créée ne coûte rien tant que personne ne dicte).

## Ce qui reste ouvert

1. Questions 12 (remise et rémunération en nature) et 14 (espace de démonstration hors convention) pour le juriste : garder ou retirer.
2. Bloquer un commercial : ses démos ouvertes s'arrêtent-elles aussi ?
3. R3 et R4, et les propositions ci-dessus.
4. Dans les règles : [À choisir] C1 (mois gratuit du code ajouté ou confondu), C15 (remise sur le prix de Solo ou sur la formule choisie) ; [À valider] C14 (compte au nom du commercial, départ), C17 dans l'autre sens, C13 à C17 pour les modérateurs et la responsable commerciale.
5. `~/offreGen/docs/commerciaux/` : copie non suivie, identique au commit `9cf9b49` ; elle fera échouer un `git pull` dans cette copie. Suppression proposée à Xavier, sans réponse.
