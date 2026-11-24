---
chantier: De la voix à l'offre, 27 enregistrement à l'abri
slug: 2026-11-07_voix-offre/27-enregistrement-abri
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette AB-1 bis sur l'iPhone de Xavier : le test porte le correctif depuis le 2026-11-24. Puis rhabiller la fenêtre avec la marque.

## En attente de toi

- Fait le 2026-11-24 (reprise immédiate validée). À refaire après le prochain déploiement : dicter 10 s, fermer Safari de force, le rouvrir aussitôt. La fenêtre « Une dictée n'est pas partie » doit être là dès l'ouverture.
- Toucher « Continuer la dictée », parler 5 s, « Abandonner » : la dictée disparaît, rien ne revient. Puis une autre dictée coupée, « Continuer la dictée », parler 10 s, « Terminer » : une seule offre, avec ce qui a été dit avant et après la coupure.
- Refaire une coupure pendant la suite, rouvrir : la dictée revient avec sa durée totale.

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
- 2026-11-24, Xavier, recette AB-1 bis sur iPhone : « La dictée, elle est reprise directement quand je relance le truc, ça c'est bien. Le problème, c'est que j'y ai fait continuer, j'y ai continué à parler, et après j'ai cliqué sur annuler la dictée, donc supprimer, et là ça m'a refait apparaître l'élément. Et il a fallu que je clique deuxième fois sur supprimer. Donc ça, il faut que, si on relance la dictée, c'est simple, ça enlève le point, et puis on reprend. Et ça ne doit plus remettre le système comme ça. »
- 2026-11-24, Claude, en conséquence : remplace « Abandonner pendant la suite n'efface que la suite ». Continuée, la dictée est une dictée en cours comme une autre : « Abandonner » l'efface entière, début compris (« Abandonner la dictée ? Elle ne sera pas gardée, début compris. »), et rien ne se repropose. Une coupure pendant la suite la fait toujours revenir à la réouverture.
- 2026-11-24, Xavier, même recette : Safari redemande le micro à chaque rechargement. Réponse de Claude : un site ne peut pas prolonger cette permission, c'est un réglage de Safari (par site : aA, Réglages du site web, Micro, Autoriser ; pour tous les sites : Réglages, Safari, Micro).

## Journal

- 2026-11-17 : constat dans `public/js/app.js` (`nouvelleOffre`) : l'enregistrement n'existe que dans la mémoire de la page (`morceaux`, une tranche par seconde) jusqu'à l'envoi par `POST /import`. Après l'envoi, la page suit `/enregistrements/<uuid>/etat` et reçoit `offre` quand l'offre est prête : c'est le signal de suppression. La fenêtre peut se fermer après l'envoi ; le suivi doit donc reprendre à la réouverture.
- Proposition de Claude, à mettre en spec : chaque tranche rangée dans le stockage du navigateur pendant l'enregistrement ; numéro de l'enregistrement noté après l'envoi ; suppression quand l'état rend `offre` ; à la réouverture, un bandeau pour ce qui n'est pas parti (« Envoyer », « Garder dans un fichier », « Supprimer »), visible du seul compte qui l'a enregistré.
- Risques à lever : lisibilité d'un enregistrement coupé au milieu (format de Safari surtout) ; Safari peut effacer ce stockage après 7 jours sans visite ; audio d'une visite en clair dans le navigateur, à écrire dans `docs/SECURITE.md`.

- 2026-11-18 : livré en code. `public/js/app.js` : module `abri` (IndexedDB, une tranche par seconde, rangée sous le compte de la page), reprise dans la fenêtre du bas (Envoyer, Garder dans un fichier, Supprimer, Fermer) ; `templates/_nouvelle_offre.html.twig` : état « reprise » ; `base.html.twig` : `data-compte`. Parcours cliqué dans Chromium avec micro simulé, sur le serveur local (IA factice) : tout passe. Non vérifié : Safari sur iPhone (format mp4 coupé, effacement du stockage).

- 2026-11-24 : recette AB-1 de Xavier (voir Décisions). Livré en code, commits sur `chantier-voix-offre` : verrou du navigateur par dictée (Web Locks), tenu pendant l'enregistrement et l'envoi seulement ; repli des 20 s qui refait le tour à l'échéance ; « Continuer la dictée » (dictée coupée avant « Terminer » seulement) ; envoi en parties `suite[]`, rangement `<uuid>-<n>`, colonne `fichiers_suite`, une transcription par partie, textes joints ; migration `Version20261124200001` (additive, `transcription_ref` élargie à 512). Revue en trois lentilles : `REVUE.md`, onze points retenus et corrigés, deux laissés en limite connue.
- Limites connues, à porter à la recette : un lot de transcription perdu chez le prestataire renvoie toutes les parties (rare, refacturé, rien de perdu) ; après un retour à l'ancienne version du code, une dictée en parties déjà en transcription donnerait une offre de la seule première partie.
- Base de test du chantier : `offregen_abri_test` (phpunit), `offregen_abri_vue` (recette locale, compte `abri-recette@exemple.ch`, mot de passe dans `~/.offregen-abri-vue-compte`), copie `~/offreGen-abri`. À supprimer à la clôture.
- 2026-11-24, Xavier : « oui déploie, pas de vraies données ». Déployé : `0b20d96` (tête de `chantier-voix-offre`, qui contient `fb63939`) sur easy-offres.xtradev.ch, sonde verte, `/sante` {"commit":"0b20d96…","base":"ok","migrations":"ok"} ; `js/app.js` servi contient `data-reprise-continuer`.

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

```
Recette AB-1 bis dans Chromium (micro simulé, serveur local, IA factice), scripts dans le scratchpad de la session :
- Ancien app.js : dictée 8 s, Chromium tué (taskkill /F), relancé 763 ms après : « reprise affichée JAMAIS en 30 s ».
- Nouveau : relancé 700 ms après la coupure, « reprise affichée 15 ms après le chargement de /clients ».
  texte : La dictée du 24 novembre à 14 h 11 (8 s) s’est arrêtée avant « Terminer » : elle est gardée sur ce téléphone.
  Continuez-la, ou envoyez-la telle quelle pour en faire une offre. ; boutons : continuer principal, envoyer secondaire
- Suite abandonnée : « Abandonner la suite ? Le début de la dictée reste sur ce téléphone. », reprise de nouveau
  proposée, abri {tranches: 8} (la suite effacée, le début intact).
- Suite gardée : minuteur au départ 00:08, offre ouverte /offres/3, abri vide après l'offre.
- Base : cle_client 1795525000743-8d71hxump2, fichiers_suite ["2/<uuid>-2.webm"],
  transcription_ref « factice-353273799be217c3 factice-0a82250245c92c9f », transcription = deux textes joints.
  ffprobe : début coupé 8,04 s (« File ended prematurely », décodé, code 0), suite 6,96 s.
- Double coupure : rouvert 802 ms après, reprise en 12 ms, « (14 s) », parties [0,8] ; « Garder dans un fichier » :
  enregistrement-2026-11-24-14h11.webm (30319 o) et enregistrement-2026-11-24-14h11-partie-2.webm (23157 o) ;
  envoi : offre /offres/4.
- Sans navigator.locks (Safari avant 15.4 simulé) : reprise affichée 19674 ms après le chargement, sans recharger.
- Deux onglets : celui qui ne dicte pas ne propose rien (reprise visible = false).
- Envoi en échec (réseau coupé) : second onglet, reprise visible, « n’est pas arrivée au serveur », continuer caché,
  envoyer principal.
- Fenêtre à 390x844, 375x667, 320x568 : aucune cible couverte, rien ne défile, boutons 56 px, liens 48 px
  (captures ab1-reprise-*.png ouvertes).
erreurs JS : [] dans chaque parcours
$ vendor/bin/phpunit (base offregen_abri_test)
OK (1511 tests, 12395 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
```
