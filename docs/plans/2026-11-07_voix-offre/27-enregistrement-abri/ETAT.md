---
chantier: De la voix à l'offre, 27 enregistrement à l'abri
slug: 2026-11-07_voix-offre/27-enregistrement-abri
phase: 6
statut: non_verifie
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Essai sur un vrai iPhone (sous-tâche 432) : dictée coupée (application fermée pendant l'enregistrement), réouverture, fenêtre de reprise, envoi. Puis rhabiller la fenêtre avec la marque.

## En attente de toi

- Essayer sur ton iPhone, une fois le test déployé : enregistrer 10 secondes, fermer Safari pendant l'enregistrement, rouvrir l'application.

## Suivi Origin

- Projet 44 offreGen, tâche 428 « Enregistrement à l'abri dans le téléphone (sous-chantier 27) », 6 h.
- Sous-tâches : 429 maquette et décisions, 430 sauvegarde pendant l'enregistrement, 431 suppression et reprise, 432 essai sur iPhone, 433 revue et recette.

## Décisions

- 2026-11-17, Xavier : « le téléphone sauvegarde l'enregistrement jusqu'à qu'il soit envoyé et qu'il ait reçu une réponse du serveur […] Une fois que c'est traité par le serveur et que l'offre a été traitée, on peut considérer que l'enregistrement peut être supprimé. » « oui tu peux créer la tâche ».
- 2026-11-18, Xavier, maquette `MAQUETTE-completer.html` : R1 « B · mais il faut que le texte soit plus clair » (fenêtre qui monte).
- 2026-11-18, Claude : la spec tient dans la décision du 17.11, R1 B et la proposition du journal ; pas de `SPEC.md` à part. La fenêtre n'apparaît qu'aux écrans qui portent la barre du bas (tous les écrans à onglets et `/import`) ; « Fermer » la remet au prochain lancement de l'onglet ; une dictée de moins de 5 s est effacée sans rien demander ; une dictée arrivée est oubliée quand son offre est prête, quand le serveur ne la connaît plus, ou après 30 jours.
- 2026-11-24, Xavier, recette AB-1 sur iPhone : « Safari réduit, l'enregistrement continue ; Safari fermé de force, l'audio est gardé en entier jusqu'à la fermeture. Il n'est pourtant annoncé que 2 min après la réouverture. Il faut : l'annoncer dès la réouverture, et proposer de continuer la dictée à la suite. »
- 2026-11-24, Claude, cause du retard (lue dans le code, pas reproduite sur iPhone) : `reprendre()` ne tourne qu'au chargement de la page et écarte une dictée dont le dernier signe de vie (`vu`) a moins de 20 s, de peur qu'un autre onglet l'enregistre encore. Rouvert dans les 20 s, rien ; rien non plus ensuite jusqu'au chargement suivant.
- 2026-11-24, Claude, correction : l'onglet qui enregistre ou envoie une dictée tient un verrou du navigateur à son nom (Web Locks, `navigator.locks`, Safari 15.4 et plus). Le navigateur le lâche quand l'onglet meurt : à la réouverture, une dictée sans verrou est proposée tout de suite. Sans `navigator.locks`, la règle des 20 s reste, et la page refait le tour d'elle-même à leur échéance.
- 2026-11-24, Claude, « Continuer la dictée » : bouton de la fenêtre de reprise. Le micro se rouvre, le minuteur repart de la durée déjà dictée, la limite des 30 min vaut pour le tout. La suite est un fichier à part (deux enregistreurs ne se recollent pas en un fichier lisible, webm comme mp4) : l'envoi porte `audio` puis `suite[]`, 5 parties au plus. Le serveur transcrit chaque partie et joint les textes dans l'ordre : une seule offre, un seul import compté. La jonction par ffmpeg est écartée : le site web ne peut pas le lancer, l'offre attendrait le cron. « Abandonner » pendant la suite n'efface que la suite ; le début reste proposé. « Garder dans un fichier » rend une partie par fichier.
- 2026-11-24, Claude : pas de nouvelle maquette, un bouton de plus dans une fenêtre validée (R1 B) ; la recette sur iPhone tranche.

## Journal

- 2026-11-17 : constat dans `public/js/app.js` (`nouvelleOffre`) : l'enregistrement n'existe que dans la mémoire de la page (`morceaux`, une tranche par seconde) jusqu'à l'envoi par `POST /import`. Après l'envoi, la page suit `/enregistrements/<uuid>/etat` et reçoit `offre` quand l'offre est prête : c'est le signal de suppression. La fenêtre peut se fermer après l'envoi ; le suivi doit donc reprendre à la réouverture.
- Proposition de Claude, à mettre en spec : chaque tranche rangée dans le stockage du navigateur pendant l'enregistrement ; numéro de l'enregistrement noté après l'envoi ; suppression quand l'état rend `offre` ; à la réouverture, un bandeau pour ce qui n'est pas parti (« Envoyer », « Garder dans un fichier », « Supprimer »), visible du seul compte qui l'a enregistré.
- Risques à lever : lisibilité d'un enregistrement coupé au milieu (format de Safari surtout) ; Safari peut effacer ce stockage après 7 jours sans visite ; audio d'une visite en clair dans le navigateur, à écrire dans `docs/SECURITE.md`.

- 2026-11-18 : livré en code. `public/js/app.js` : module `abri` (IndexedDB, une tranche par seconde, rangée sous le compte de la page), reprise dans la fenêtre du bas (Envoyer, Garder dans un fichier, Supprimer, Fermer) ; `templates/_nouvelle_offre.html.twig` : état « reprise » ; `base.html.twig` : `data-compte`. Parcours cliqué dans Chromium avec micro simulé, sur le serveur local (IA factice) : tout passe. Non vérifié : Safari sur iPhone (format mp4 coupé, effacement du stockage).

## Preuves

```
$ node recette-abri.js (Chromium, 390 px, micro simulé, compte dev)
1. pendant l'enregistrement : {"dictees":[{"compte":"1","etat":"enregistrement","tranches":7,...,"type":"audio/webm;codecs=opus"}],"morceaux":7}
2. texte : La dictée du 18 novembre à 17 h 29 (7 s) est restée sur ce téléphone : elle n’est pas arrivée au serveur. Envoyez-la maintenant pour en faire une offre.
   sondes de la fenêtre : disposition {"deb":0,"chev":0,"coupes":0,"scroll":false}
3. fichier gardé : enregistrement-2026-11-18-17h29.webm 27355 octets
4. offre ouverte : http://localhost:8000/offres/163
   abri après l'offre : {"dictees":[],"morceaux":0}
5. après « Supprimer » : {"dictees":[],"morceaux":0} fenêtre visible : false
6. après « Fermer », autre page : fenêtre visible : false
   compte de démonstration : fenêtre visible : false ; dictées en tout : 1
erreurs JS : []
$ ffmpeg -v error -i enregistrement-2026-11-18-17h28.webm -f null -   (dictée coupée)
[matroska,webm] File ended prematurely at pos. 26121 ; code de sortie 0
$ env APP_ENV=test vendor/bin/phpunit --filter EnregistrementAbriTest
OK (3 tests, 13 assertions)
```
