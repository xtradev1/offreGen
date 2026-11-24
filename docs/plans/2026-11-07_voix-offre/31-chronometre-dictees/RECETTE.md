# Recette du chronomètre des dictées (sous-chantier 31)

Site de test : https://easy-offres.xtradev.ch, version `0ec9fff` (déployée le 2026-11-24, sonde verte).

## Ce que Claude a déjà joué par l'interface (2026-11-24)

Playwright, Chromium, téléphone 390 px puis PC 1366 px. Parcours : `recette.js` à côté de ce fichier.

- Une démo à soi, `chrono-claude@exemple.ch` (peintre, fichier `~/.offregen-test-chrono-claude-compte` sous WSL), dicte 29 s au micro de la barre depuis « Clients ». Offre 103 ouverte 17 s après « Terminer ».
- Le téléphone envoie la durée de son envoi : `POST /enregistrements/…/envoi` rend 204.
- L'administrateur `recette-e1@exemple.ch` ouvre « Plus », puis « Dictées » : statistiques, liste, CSV (BOM UTF-8), rapport de la dictée 41, « Rejouer ». Le rejeu finit en 20 s environ, 6 lignes comme l'original, offre intacte.
- Aucune erreur JavaScript, aucun débordement horizontal au PC.

Trois défauts trouvés par cette recette, corrigés dans `0ec9fff` et vérifiés sur le test :

| Défaut | Correction | Preuve |
|---|---|---|
| Premier octet de chaque appel à l'IA à 0 ms | le curl 7.74 du serveur met dans `starttransfer_time`, pour un POST, le début de l'envoi ; l'étape retient l'arrivée des en-têtes vue par `on_progress` | sur le test : extraction 4,8 s, premier octet 4,8 s ; envoi à Whisper 102 ms, premier octet 97 ms |
| Statuts affichés en code (« pret », « en_cours ») | libellés : prête, en cours, fini, en échec | `RejeuDicteeTest`, rouge sans le gabarit corrigé |
| Tableau des rejeux coupé au PC (colonnes Écriture et Total) | il prend la largeur de l'écran, comme les autres tableaux | capture `10-pc-rapport` |

## À toi (15 minutes)

Avec ton iPhone, sur ton compte d'essai habituel.

- [ ] Dicte une offre d'au moins 30 s au micro. Elle s'ouvre comme d'habitude.
- [ ] Au PC, connecté en administrateur : « Plus », puis « Dictées ». Ta dictée est en tête, avec « Safari, iPhone », la durée de l'audio et une durée d'envoi en ms.
- [ ] Ouvre son rapport. La frise montre l'envoi, la réception, la transcription, l'extraction, les pièces et l'écriture ; le tableau donne les jetons et le premier octet de chaque appel à l'IA.
- [ ] Clique « Rejouer », puis recharge la page au bout d'une minute. La ligne « Rejeu du … » passe à « fini » ; ton offre n'a pas changé.
- [ ] Clique « Exporter en CSV » et ouvre le fichier dans Excel : une ligne par dictée, accents lisibles, une colonne par étape.
- [ ] Facultatif, la panne : pendant qu'une dictée est en traitement, rien à faire de ton côté ; si un traitement meurt, l'écran d'attente affiche « Le traitement s'est arrêté en route » après 2 min 30 et la dictée repart seule en 3 min 30 au plus. Non provoqué sur le test (il faudrait tuer un processus du serveur).

## Lecture des chiffres

- Les appels à l'IA ne sont pas envoyés morceau par morceau : le premier octet arrive quand la réponse est calculée, il vaut donc presque la durée de l'appel. Il sert à séparer le réseau du calcul quand un appel est lent.
- Les dictées d'avant le chronomètre s'affichent « inconnu, - d'audio », sans durées.
- Déjà visible sur le test : la dictée de l'offre 96 (Safari, iPhone, 8,7 s d'audio) a mis 21,6 s en transcription, marquée ▲ : c'est le maximum de la période.

## Reste ouvert

- S1 de `REVUE.md` : le texte des dictées dans le rapport, à trancher.
- Non mesuré : la limite de durée des processus PHP chez Infomaniak et un traitement mort réel sur le test.
