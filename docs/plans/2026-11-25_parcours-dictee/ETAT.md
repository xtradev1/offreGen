---
chantier: Parcours de la dictée (micro immédiat, enregistrement, attente, arrivée sur le PDF)
slug: 2026-11-25_parcours-dictee
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 626 « Parcours de la dictée : micro immédiat et interface plus agréable ».
- Sous-tâches : 627 micro immédiat (fait, sur le test), 628 maquette (gate visuel franchi le 2026-11-26), 629 implémentation de la piste retenue (codée le 2026-11-26, pas commitée, pas déployée).
- Tâche 630 « Analyse UX des parcours de l'artisan (rapport avec captures) » : demande de Xavier du 2026-11-26, rapport dans ce dossier.
- Tâche 631 « Corrections de l'analyse des parcours (8 constats retenus le 26.11) » : codées le 2026-11-26 avec les lots 3, 5 et 7 à 11.

## Prochaine action

Gate prod ouvert : Xavier déroule `RECETTE.md` sur son iPhone, sur le site de test. Ses retours renvoient en phase 5.
Copie de travail : `~/offreGen-micro` (détachée, à la tête de `origin/chantier-voix-offre`). Serveur local :
`BANC_DB=offregen_micro_vue sh preparer.sh` (scratchpad, port 8097).

## En attente de toi

- Dérouler `RECETTE.md` sur ton iPhone et dire ce qui casse : la pause, l'onde, l'alerte de silence et le clavier ouvert par le curseur n'ont été vus que dans Chromium.
- Trancher trois textes et un réglage : les conseils des activités « extérieur » et « prestations » (écrits par Claude), « en quelques minutes » dans les bulles du guide alors que l'anneau dit « environ 10 s », et la pièce d'une ligne repliée sous « Plus de réglages ».

## Journal

- 2026-11-25 : demande de Xavier : « analyse le bouton de clic du micro […] ça doit aller plus vite et être plus joli ». Mesure dans Chromium (10 essais par profil) : le code demande le micro 1 ms après le toucher ; l'attente est celle du navigateur, sans aucun signe à l'écran, puis 330 ms de fenêtre. Correction : fenêtre dès le toucher (état « Le micro s'ouvre… »), annulation possible, bouton enfoncé, écran d'enregistrement refait. Commit `f2c1aad`, poussé et déployé sur le test avec l'accord de Xavier (« oui déploie, pas de vraies données »), sonde verte, recette Playwright sur le test. Xavier : « ça marche ».
- 2026-11-25 : demande de Xavier : « prépare-moi maintenant une maquette pour rendre l'interface plus sympa en termes d'UI/UX ». Périmètre choisi par lui : parcours de la dictée, deux pistes côte à côte. `MAQUETTE-parcours-dictee.html` (pistes A et B, démo jouable, captures du parcours actuel sur le banc local, envoi simulé).
- 2026-11-26 : réponses de Xavier à la maquette v1 (mots exacts sous Décisions), plus : « re faire en sorte qu'on arrive directement sur l'offre pdf à condition qu'il n'y ait pas d'erreur […] simplifier encore ». Faits relevés : l'offre naît avec `client_id` seulement si le nom dicté ressemble à plus de 80 % à une fiche (`Traiteur::creerOffre`) ; sinon « Pour qui est cette offre ? » (E6) avant le PDF. Sur le test, en lecture seule : 43 offres dictées, 28 sans ligne à vérifier, 22 avec client rattaché (les 22 sans ligne à vérifier), état actuel et non état à la naissance.
- 2026-11-26 : `MAQUETTE-parcours-dictee-v2.html` (ce qui restait ouvert : attente entre A et B, règle d'arrivée, trois cas, captures réelles du PDF, de la relecture et du choix du client). Contrôle du kit vert après correction d'un identifiant en double (`demo`).
- 2026-11-26 : réponses de Xavier à la v2 : tout validé (mots exacts sous Décisions). Gate visuel franchi. Phases 1 et 3 réunies dans les maquettes, comme aux chantiers précédents ; phase 2 sautée (un seul chantier, trois lots).
- 2026-11-26 : demande de Xavier : « tu peux m'analyser l'ui/l'ux pour regarder ce qui est mieux en termes de parcours utilisateur ». Interprétation de Claude : analyse des parcours de l'artisan dans toute l'application, au téléphone, parcours joués sur une copie de la base du banc. Tâche Origin 630.
- 2026-11-26 : parcours joués au téléphone (Chromium, 390 x 844) sur une base à soi, `offregen_micro_vue` (copie de `offregen_fluidite_vue`, droits recopiés, offre 5 mise en « client à confirmer »). `PARCOURS-artisan.html` : touchers par parcours, huit constats avec captures, choix sous chacun. Contrôle du kit vert, captures relues.
- 2026-11-26, constat 1 (défaut reproduit) : en relecture, sur une ligne sans prix, « C'est juste » reste actif ; chaque toucher empile un message « Pas enregistré […] L'envoi a échoué » ; les messages restent sur l'écran « Toutes les lignes sont relues ».
- 2026-11-26, constat 2 (cause lue dans le code) : la liste compte `a_verifier` (`AccueilControleur.php:108`), la relecture compte `a_verifier` ou prix manquant (`RelectureControleur::aRelire`). D'où « 3 à vérifier » et « Relire 5 lignes » pour l'offre 3 du banc.
- 2026-11-26 : autres constats : page de l'offre (première ligne à 60 % du premier écran, 6,3 écrans pour 33 lignes), ajout d'une ligne (retour en haut de page, ligne ajoutée sans repère, texte du champ coupé), pas de recherche dans Mes offres, fiche d'une ligne sans hiérarchie, bouton principal inchangé après l'envoi, « Se déconnecter » en double.
- 2026-11-26 : réponses de Xavier au rapport des parcours : les huit constats « À faire » (mots exacts sous Décisions). Tâche Origin 631 créée pour eux.
- 2026-11-26 : mesure sur le test, en lecture seule : 14 dictées chronométrées ; hors panne du 25.11 (3 dictées de plus d'une heure), 11 dictées prêtes en 4 à 25 s, médiane 8 s, durée dictée de 8 à 214 s. La maquette v2 annonçait « 30 à 50 s » : faux.
- 2026-11-26 : `PLAN.md` écrit (phase 4) : contrat de l'arrivée, onze lots. Phase 5 ouverte.
- 2026-11-26, lot 1 (attente) codé : fenêtre en plein écran sombre après « Terminer », anneau (envoi réel sur le premier huitième, puis temps écoulé sur temps attendu, plafond 95 %, « encore un instant » au-delà), trois étapes (« Offre prête » retirée, `faites` vaut 3 quand l'offre est prête), croix pendant la préparation, « En préparation » rafraîchi à la fermeture, « Échec » sans phrase de départ. Tests : `AttenteDicteeTest` (vu rouge sans le code), suite `tests/Http tests/Regles tests/Offre` : OK (1088 tests, 9971 assertions). Playwright sur le banc local, envoi et état simulés : téléphone clair et sombre, 320 px, PC, page Import ; sonde de contraste à 0 défaut, prouvée rouge sur un défaut injecté ; le seul « débordement » relevé est le rond qui tourne (0 à l'arrêt).
- 2026-11-26, lot 2 (traceur) : `/etat` porte `suite` et `a_relire`, le navigateur ouvre `suite` (Playwright : `/offres/2/apercu` ouvert quand l'état le dit, `/offres/2` sans le champ).
- 2026-11-26, lot 3 (règle d'arrivée, constat 2) : `Offre\Arrivee` (une ligne est à relire si elle est incertaine ou sans prix ; relecture, puis client, puis PDF). `/etat` dit `suite`, `a_relire`, `lignes`, `titre`, `client`. La liste, la colonne du PC et la relecture comptent pareil ; la liste dit « N à relire ». Tests `ArriveeTest`, `ArriveeDicteeTest` vus rouges puis verts.
- 2026-11-26, lot 4 (« Offre prête », fin de relecture) : écran « Offre prête » sans le prix (33 lignes, 5 à relire sur l'offre 3 du banc), la dernière ligne relue redirige vers le PDF ou vers le choix du client. Playwright avec le vrai état du serveur local, seul l'envoi simulé : offre propre et client connu, 0 toucher jusqu'au PDF ; 5 lignes à relire, 6 touchers ; client à confirmer, 2 touchers. Sondes à 0 défaut en clair, sombre, 320 px et PC. Le retour du téléphone depuis le PDF ramène à la liste.
- 2026-11-26, lot 5 (constat 1) : défaut rejoué avant correction (2 à 4 messages empilés, « L'envoi a échoué. » collé à chaque refus, « C'est juste » sur une ligne sans prix). Cause du texte parasite : `messageDe` (fluide.js) lisait aussi les alertes cachées de la fenêtre de dictée, présente sur chaque écran. Après : un seul message, sans ce texte, parti à la ligne suivante ; sans prix, le bouton dit « Indiquez le prix », le curseur est dans le prix, deux touchers ne donnent aucun message ; fin sur le PDF, 0 message.
- 2026-11-26 : suite `tests/Http tests/Regles tests/Offre` après les lots 2 à 5 : OK (1102 tests, 10029 assertions) ; `vendor/bin/phpstan analyse` : No errors.
- 2026-11-26, lot 6 (enregistrement plein écran, silence, pause) codé : écran sombre (état, minuteur, onde de 34 barres, carte du conseil, Abandonner, Terminer, Pause), trois conseils par activité (`Activite::mots`, clés `conseil_1` à `conseil_3`, la clé `dictee` retirée), alerte après 5 s sans voix, pause par `MediaRecorder.pause()` avec la durée retranchée (champ `pause` de la copie du téléphone). Playwright, faux micro de Chromium : onde levée (21 barres sur 34), conseil 2 à 8 s, alerte absente à 4,3 s et présente à 5,7 s de voix coupée, partie au retour de la voix ; pause de 3,5 s : minuteur figé, durée envoyée 17832 ms pour 17808 ms attendus (écart 24 ms) ; 2 s de dictée et 5 s de pause : refusée (« 5 secondes au minimum ») ; coupée pendant une pause : reproposée à « 7 s » (3 s et 4 s dictées, 8 s de pause exclues), « Continuer » repart à 00:07. Sondes à 0 défaut : téléphone clair et sombre, 320 px (après correction d'un débordement de l'onde), couché, PC, page Import.
- 2026-11-26, lot 6, deux erreurs de Claude corrigées en recette : l'attribut `data-pause` de l'état masquait le bouton Pause (renommé `data-en-pause`) ; la classe `alerte` de l'écran prenait le style des messages d'alerte et décalait les commandes (renommée `urgent`).
- 2026-11-26, lot 6 : `non_verifie` sur iPhone. La pause, le niveau du micro (contexte audio créé dans le toucher) et le clavier ouvert par le curseur ne se prouvent que sur l'appareil de Xavier.
- 2026-11-26 : suite `tests/Http tests/Regles tests/Offre tests/Compte` après le lot 6 : OK (1298 tests, 11461 assertions) ; `phpstan analyse` : No errors.
- 2026-11-26, lot 7 (constat 3) : sans représentant ni texte avant les lignes, deux liens sur une ligne (« + Représentant », « + Texte avant les lignes », qui se déplie sans JavaScript). Mesure Playwright sur l'offre 3 du banc, téléphone 390 x 844 : première ligne à 389 px (0,46 écran) au lieu de 506 px (0,60), deux lignes entières au premier écran au lieu d'une ; le rapport annonçait 170 px de gain, la mesure donne 117. Texte saisi : enregistré, il reprend son champ au rechargement ; représentant choisi : sa carte se pose sous le client.
- 2026-11-26, lot 8 (constat 4) : avant, la ligne ajoutée était bien éclairée mais hors écran (défilement 0, ligne à 1174 px) : le retour de l'historique, à la fermeture de la fenêtre, remettait la page en haut après le défilement. Après : ligne à l'écran (339 px), éclairée, curseur dans sa quantité, dès « Terminé ». Recherche avant « Elle va », texte du champ « Chercher ou taper une ligne ». Le rapport disait le texte du champ coupé : la mesure donne 293 px pour 298 px de place, il tenait de justesse.
- 2026-11-26, lot 9 (constat 5) : recherche à la frappe dans Mes offres (objet, client, numéro, sans accents ni majuscules), par le filtre existant (`brancherFiltre`) : coût réel bien sous les 2 h annoncées. Playwright : « dupont » 1 carte, « FAÇADE » 1, « facade modele » 1, « 4 » 1, « zzz » le message « Aucune offre ne correspond. » ; le tri garde le filtre.
- 2026-11-26, lot 10 (constat 6) : fiche d'une ligne, réglages rares sous « Plus de réglages », ouverts d'office pour une ligne offerte, une option ou variante, un taux particulier, des variantes ou une erreur. La pièce d'une ligne ne les ouvre pas (décision de Claude : presque toutes les lignes d'une offre par pièce en ont une).
- 2026-11-26, lot 11 (constats 7 et 8) : aperçu d'une offre envoyée et inchangée depuis : « Facturer », « Renvoyer », « Retour aux offres » en bouton principal ; modifiée depuis l'envoi, elle garde « Envoyer ». Playwright : la barre change sans rechargement après le partage simulé, « Renvoyer » partage de nouveau, « Facturer » ouvre sa fenêtre. Premier rendu fautif corrigé : « Retour aux offres » se cassait sur trois lignes à 390 px, il a sa ligne au téléphone. « Se déconnecter » : lien discret avec l'adresse du compte au bas de « Plus » ; dans les Réglages, seulement sur PC, où « Plus » n'existe pas. Erreur de Claude corrigée : la variable `demo` des réglages retirée à tort (douze pages en erreur 500 aux tests).
- 2026-11-26, phase 7 (revue) : un relecteur adversarial sur Opus, aucune surface sensible touchée. Cinq trouvailles importantes, toutes confirmées dans le code et corrigées (filtre sur une liste détachée, guide de la démo court-circuité par l'arrivée directe, offre vide menée à un PDF vide, texte avant les lignes qui disparaissait, durée fausse une seconde après « Reprendre »), plus huit retouches mineures. Une sixième trouvée en rejouant le guide : sa bulle de l'attente sortait de l'écran. Détail et verdicts : `REVUE.md`.
- 2026-11-26, phase 8 : `RECETTE.md` écrit, onze scénarios. Gate prod ouvert.
- 2026-11-26 : Xavier, après le rapport de fin de phase 7 : « oui commite, pousse et déploie, pas de vraies données ». Accord pour ce commit, ce push sur `chantier-voix-offre` et ce déploiement sur le test.
- 2026-11-26 : commit `6d7d2ec` poussé sur `chantier-voix-offre`, déployé sur le test (`bash outils/deployer-test.sh`) : sonde verte, `/sante` annonce `6d7d2ec`.
- 2026-11-26 : demande de Xavier pendant le déploiement : « Il faut pouvoir fermer la préparation de l'offre. Un bouton avec écrit mettre en arrière-plan pour travailler sur autre chose ou quelque chose dans ce style-là. » Puis : « oui déploie aussi le bouton arrière-plan ».
- 2026-11-26 : bouton « Mettre en arrière-plan » sur l'écran de préparation, une fois l'audio au serveur (il remplace la croix). La fenêtre fermée, le suivi continue tant que la page vit ; l'offre prête s'annonce par un bandeau « Offre prête » avec « Ouvrir » (ou « Relire »), l'échec par un bandeau rouge ; la liste des offres se met à jour. Après un échec, le bouton dit « Fermer ». Playwright : bouton de 56 px en clair, sombre, 320 px, couché et PC ; fenêtre fermée, bandeau « Offre prête Ouvrir », lien de 48 px, offre ouverte.

## Décisions

Réponses de Xavier, mots exacts.

| Date | Décision | Raison |
|---|---|---|
| 2026-11-26 | Étape 1, enregistrement : B | Maquette v1 |
| 2026-11-26 | Alerte après 5 s de silence : Oui | Maquette v1 |
| 2026-11-26 | Pause pendant la dictée : Oui | Maquette v1. À prouver sur l'iPhone de Xavier (Claude ne peut pas). |
| 2026-11-26 | Étape 2, attente : « une version entre A et B » | Maquette v1 ; version proposée en v2 : « Valider » |
| 2026-11-26 | Texte entendu pendant l'attente : Non, « pas obliger » | Maquette v1 |
| 2026-11-26 | Étape 3, offre prête : B, « il faut qu'ensuite ça ouvre l'offre directement avec un petit menu et ne pas afficher le prix » | Maquette v1 |
| 2026-11-26 | Règle « offre propre, PDF » : Valider | Maquette v2 : dès qu'il n'y a aucune ligne à vérifier et qu'un client est rattaché, le PDF s'ouvre |
| 2026-11-26 | Cas 3, client à confirmer : Choix du client tout de suite | Maquette v2 |
| 2026-11-26 | Après la dernière ligne relue : PDF directement | Maquette v2 |
| 2026-11-26 | « Ne pas afficher le prix » : Oui, seulement cet écran | Maquette v2 : l'écran « Offre prête » |
| 2026-11-26 | Constats 1 à 8 du rapport des parcours : « À faire » pour chacun | `PARCOURS-artisan.html` : relecture d'une ligne sans prix, un seul compte, page de l'offre, ajout d'une ligne, recherche dans Mes offres, fiche d'une ligne, après Envoyer, Se déconnecter |
| 2026-11-26 | « Il faut pouvoir fermer la préparation de l'offre. Un bouton avec écrit mettre en arrière-plan pour travailler sur autre chose ou quelque chose dans ce style-là. » | Demande de Xavier pendant le déploiement |

Tranché par Claude :

| Date | Décision | Raison |
|---|---|---|
| 2026-11-25 | La demande du micro part avant l'affichage de la fenêtre | 11 ms de retard mesurées sur un processeur ralenti quatre fois quand la fenêtre passait devant |
| 2026-11-25 | Pas d'ouverture du micro à l'appui du doigt | Safari demande l'autorisation dans un toucher relâché ; gain d'environ 100 ms contre un risque sur iPhone |
| 2026-11-26 | Ordre des lots : attente, arrivée, enregistrement | Du moins risqué au plus risqué ; la pause touche la durée, la limite des 30 minutes et la reprise |
| 2026-11-26 | « Offre propre » = aucune ligne à relire au sens de la relecture (incertaine ou sans prix) et client rattaché | Avec le compte de la liste (`a_verifier` seul), une offre dont une ligne n'a pas de prix arriverait sur le PDF |
| 2026-11-26 | Ordre : les trois lots validés, puis les constats ; les constats 1 et 2 dans l'arrivée | Ils touchent le même code (relecture, compte des lignes) |
| 2026-11-26 | Durée attendue de la préparation : 10 s plus 3 % de la durée dictée | Mesure du test (médiane 8 s) ; au-delà, l'anneau reste à 95 % et dit « encore un instant » |
| 2026-11-26 | Le client changé depuis la carte de l'offre ramène à l'offre ; seul `suite=apercu` mène au PDF | Sauter au PDF pendant qu'on corrige l'offre surprendrait |
| 2026-11-26 | Tant que le guide de la démo ou de l'essai se montre, la dictée mène à la page de l'offre, comme avant | Le guide y finit (repères, « Revenir à mon espace ») ; revue, I2 |
| 2026-11-26 | Une offre sans ligne s'ouvre elle-même, jamais sur son PDF | Le PDF serait vide ; revue, I3 |
| 2026-11-26 | Aperçu d'une offre envoyée : « Retour aux offres » a sa ligne au téléphone, « Modifier » sort de la barre | Les trois mots se cassaient sur trois lignes à côté de deux boutons ; le retour « Offre » du haut mène à la page à modifier |
| 2026-11-26 | Réglages : « Se déconnecter » reste sur PC | « Plus » n'existe pas sur PC ; au téléphone, il n'est plus que dans « Plus » |
| 2026-11-26 | Deux tests existants alignés : `RepresentantTest` (carte vide du représentant) et les tests de la consigne « Parlez, pièce par pièce. » | Le constat 3 remplace la carte vide par un lien ; la maquette validée remplace la consigne par trois conseils |
| 2026-11-26 | Hors périmètre, à traiter ailleurs : captures de `public/relecture-textes/ecrans/` périmées ; repli vers une page 404 si l'offre est supprimée pendant l'attente | Revue, M13 et M5 |
| 2026-11-26 | En arrière-plan, le suivi continue et annonce l'offre prête par un bandeau | Sans cela, rien ne dirait à l'artisan resté sur la page que son offre est là ; le serveur, qui le voit encore, n'envoie pas de courriel |
| 2026-11-26 | Toute fermeture pendant la préparation (bouton ou retour du téléphone) vaut mise en arrière-plan | Un seul comportement ; la croix n'est plus montrée pendant la préparation |
| 2026-11-26 | Phrase du bas : « Vous pouvez travailler sur autre chose ou quitter l'application : l'offre vous attendra dans « Offres ». » | Elle dit ce que fait le bouton ; texte de Claude, à relire par Xavier |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `MAQUETTE-parcours-dictee.html` | 3 | répondue le 2026-11-26 |
| `MAQUETTE-parcours-dictee-v2.html` | 3 | validée le 2026-11-26 |
| `PLAN.md` | 4 | écrit le 2026-11-26, onze lots |
| `REVUE.md` | 7 | écrit le 2026-11-26 |
| `RECETTE.md` | 8 | écrit le 2026-11-26, à dérouler par Xavier |
| `PARCOURS-artisan.html` | hors phases (tâche 630) | répondu le 2026-11-26 : huit « À faire », lots 3, 5 et 7 à 11 de `PLAN.md` |

## Preuves

### Micro immédiat (commit f2c1aad)

```
Chromium, 10 essais par profil, ms après le toucher, médiane avant -> après
micro immédiat        premier signe 22 -> 2    fenêtre posée 365 -> 341    enregistreur parti 72 -> 66
micro +300 ms simulé  premier signe 328 -> 2   fenêtre posée 658 -> 343    enregistreur parti 386 -> 384
micro +700 ms simulé  premier signe 727 -> 2   fenêtre posée 1056 -> 340   enregistreur parti 781 -> 776
+300 ms, CPU x4       premier signe 371 -> 16  fenêtre posée 698 -> 354    enregistreur parti 419 -> 398

$ vendor/bin/phpunit tests/Http tests/Regles tests/Offre/ReglesDuDocumentTest.php
OK (579 tests, 6725 assertions)

$ bash outils/deployer-test.sh
/sante annonce f2c1aad5422c6d032b5c24a72f70d0072bab95e2 après 0 s
SONDE VERTE
DÉPLOYÉ : f2c1aad5422c6d032b5c24a72f70d0072bab95e2 sur https://easy-offres.xtradev.ch
```

### Parcours de la dictée et constats (2026-11-26, non commité)

```
$ vendor/bin/phpunit            (toute la suite, après les corrections de la revue)
OK (1655 tests, 13520 assertions)

$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors

Playwright, serveur local du banc (port 8097, base offregen_micro_vue), Chromium, faux micro ; envoi simulé
attente          anneau 0 % « envoi », 23 % « environ 10 s », 95 % « encore un instant », « Échec » ; clair, sombre, 320 px, PC, page Import
arrivée          offre propre et client connu : /offres/2/apercu, 0 toucher ; 5 lignes à relire : « Offre prête » puis PDF, 6 touchers ; client à confirmer : PDF en 2 touchers
relecture        avant : 2 à 4 messages empilés, « L'envoi a échoué. » ; après : 1 message, 0 sur une ligne sans prix, bouton « Indiquez le prix », curseur dans le prix
enregistrement   onde 21 barres sur 34 ; alerte absente à 4,3 s de silence, présente à 5,7 s ; pause 3,5 s : minuteur figé, durée envoyée 17842 ms pour 17821 attendus
                 2 s dictées et 5 s de pause : refusée (5 secondes au minimum) ; coupée en pause : reproposée à « 7 s », « Continuer » repart à 00:07
page de l'offre  première ligne à 389 px (0,46 écran) au lieu de 506 px (0,60) ; 2 lignes entières au premier écran au lieu d'une
ajout            avant : défilement 0, ligne à 1174 px ; après : ligne à 339 px, éclairée, curseur dans sa quantité
recherche        « dupont » 1 carte sur 4, « facade modele » 1, « zzz » : « Aucune offre ne correspond. »
après l'envoi    Modifier | Facturer | Envoyer*  devient  Facturer | Renvoyer | Retour aux offres* ; « Renvoyer » : 2 partages
guide            étapes 1 à 5 jouées, bulles dans l'écran à 390 et à 320 px, arrivée sur /offres/3
sondes           contraste 7:1 : 0 défaut sur chaque écran ; sonde prouvée rouge sur un défaut injecté (2.27 pour 7)
                 disposition : 0 débordement, 0 texte coupé ; seul relevé, le rond qui tourne (0 à l'arrêt)

non_verifie : iPhone (pause, onde, alerte de silence, clavier), lecteur d'écran, dictées de plus de 4 minutes
```

### Déploiement de 6d7d2ec sur le test (2026-11-26)

```
$ bash outils/deployer-test.sh
LU     /sante : {"commit":"6d7d2ec746c39cf2661f3bc579d9b8389b36473e","base":"ok","migrations":"ok"}
OK     commit déployé : 6d7d2ec746c39cf2661f3bc579d9b8389b36473e
OK     base joignable
OK     migrations à jour
SONDE VERTE
DÉPLOYÉ : 6d7d2ec746c39cf2661f3bc579d9b8389b36473e sur https://easy-offres.xtradev.ch
```
