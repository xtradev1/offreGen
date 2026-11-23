---
chantier: Onboarding du commercial et de l'artisan
slug: 2026-11-21_onboarding
phase: 6
statut: en_cours
gate_attendu: null
profil: .claude/atoz-profile.md
---

## Suivi Origin

- Projet 44 (offreGen), tâche 530 « Onboarding du commercial et de l'artisan » : 531 SPEC et maquette, 532 aide à la vente et page Aide, 533 suivi (lots 6 et 7 repris), 534 démo côté prospect, 535 passage à l'essai, 536 arrivée du commercial, 537 revue et recette. Reprend la sous-tâche 468 de la tâche 458. Conversation rattachée à 531 le 2026-11-21.

## Prochaine action

Retours du 2026-11-23 (E2, E3, E5, E7a, E8) commités et déployés sur le test. Ensuite : E1 dans ce chantier (texte K1 remis ; suppression des démos finies depuis 30 jours ; mois gratuit à la place des 10 offres de l'essai ; « Demander un appel »), d'abord relire ce que les maquettes de l'administration ont validé pour ces trois fonctions (`MAQUETTE-parcours-v4.html`, `MAQUETTE-mois-gratuit-v5.html`, lot 8), maquette pour ce qui n'y est pas ; E6 en maquette (« Les questions à poser », tirées de `docs/terrain/2026-11-18_questions-adequation.md`, en parlant de « client »). Puis ligne de reprise dans l'`ETAT.md` de l'administration et exigence sur B3. Lots 9 et 10 : à reprendre quand B3 et la route « Aide et questions » du chantier support existeront.

## En attente de toi

- Dérouler `RECETTE.md` sur le test, au téléphone puis sur PC, et dire ce qui casse (le guide du commercial a maintenant onze gestes, par l'onglet « Clients »).
- Dire à la conversation du support que la commission est de 10 %.

## Journal

- 2026-11-21 : chantier ouvert. Cadre de Xavier : onboarding commercial = arrivée du commercial et parcours de vente ; artisan = démo lancée par un commercial et passage à l'essai ; maquette puis code ; le chantier reprend le reste des lots 6 et 7 de `2026-11-19_admin-comptes` et la tâche 468.
- 2026-11-21 : phase 0 sautée, le profil `.claude/atoz-profile.md` existe (daté du 2026-11-07 : ses commandes « à créer » existent depuis, `vendor/bin/phpunit` et `vendor/bin/phpstan analyse`).
- 2026-11-21 : copie de travail `~/offreGen-onboarding` (`git worktree add --detach`, tête `77954ce` de `origin/chantier-voix-offre`). `~/offreGen` porte une trentaine de fichiers non commités d'autres sessions.
- 2026-11-21 : constats dans le code, avant la SPEC : `Comptes::passerEnEssai` ne relance pas l'assistant ; `ImportControleur` ne lit pas `demo_fin` ; aucun gabarit d'aide. Lots 5 et 6 (en partie) de l'administration déjà livrés : démo, QR, message, `/d/{jeton}`, ruban, passage à l'essai, courriel de retour.
- 2026-11-21 : `SPEC.md` écrite, gate intention ouvert.

- 2026-11-21 : gate intention franchi. Xavier : « ok pour tes recommandations, fais la maquette je valide que sur maquettes ». Points 1 à 6 de la section 7 de `SPEC.md` réglés selon les recommandations ; point 7 sans réponse.
- 2026-11-21 : phase 2 sautée comme découpage en sous-chantiers : un seul chantier, découpé en lots au plan (phase 4). Ordre retenu (point 1) : kit et suivi, démo, passage à l'essai, arrivée du commercial en dernier.

- 2026-11-21 : phase 3, `MAQUETTE-onboarding.html` : CSS de la maquette v4 de l'administration (charte Dicta) repris par script, douze décisions O1 à O12, variantes A et B pour O1, O3, O11 et O12. N1, K1 et S1 (maquette v4 du 19.11) affichés comme réglés, sans boutons.
- 2026-11-21 : défaut trouvé en préparant la maquette : `templates/enregistrement.html.twig` promet un courriel « offre prête » à une démo, que `Traiteur::prevenir` n'envoie jamais (`demo = 1`). Corrigé dans l'écran d'attente de la maquette (O2).
- 2026-11-21 : textes d'écran relus par `relecteur-xtradev`, « À corriger » aux tours 1 et 2, « Prêt » au tour 3. Écartés avec raison : « 50 offres ce mois-ci » (l'essai du code donne 10 offres, `OFFRES_ESSAI`), l'import de liste (existe, `import_catalogue.html.twig`). Prix de CHF 44.90 retiré de la réponse du commercial tant que Xavier ne l'a pas confirmé. Au choix de Xavier : « enregistrez, enregistreur, enregistrement » dans la page Aide.
- 2026-11-21 : gate visuel ouvert.

- 2026-11-21 : réponse de Xavier à la v1, collée telle quelle : « Okay, l'idée est pas trop mal dans les grandes lignes. En revanche, ça correspond pas à ce que je pensais. Moi, ce que j'aimerais, que tu aies 2 ou 3 petites bienvenue dans l'équipe, qui présentent un petit peu le truc, et après, quand on dit faire ma démo de démonstration, tu cliques, et là, je veux qu'il y ait un suivi, au sens, cliquer sur le truc rond, boum, puis ça entoure d'une couleur, tu vois, genre, boum, boum, que ça indique, cliquez ici, comme ça, elle clique, et on apprend à cliquer aux gens. »
- 2026-11-21 : maquette v2 : section de l'arrivée refaite en prototype jouable (trois écrans de bienvenue, puis guide de dix gestes : anneau terracotta qui pulse sur la cible, bulle, un pas par toucher, de « Faire ma démo d'entraînement » à « Revenir à mon espace »). O11 devient la validation du guide ; O12 porte sur la sortie de l'entraînement (reconnexion ou retour direct) ; O13 neuf : le même anneau pour l'artisan, qui ferait tomber O1 et O3. Autres sections inchangées.
- 2026-11-21 : textes neufs du guide relus par `relecteur-xtradev` : « À corriger » (phrase sur l'accord du client perdue dans l'écran des conseils, compteur des bulles faux, « garde son catalogue et ses réglages » non établi), « Prêt » au tour 2. Au choix de Xavier : « Faites-la d'abord vous-même ».

- 2026-11-22 : réponses de Xavier à la v2, collées telles quelles :

  > Réponses, maquette de l'onboarding (21.11) :
  > - O11 : Valider · oui bien a tester dans des condtions réel et les textes sont pas top. il faut juste voir que ça s'intègre vraiment bien la vue que c'est une maquette reste à valider les éléments par exemple y'a pas la partie qr code ou on a la possiblité de scanner donc faut l'expliquer dans le text.
  > - O12 : B : retour direct, sans mot de passe · ça doit etre le plus fluide et la demo artisan peut etre une fake qui est pas une vraie demo au sens elle simule comme ça on reste dans la meme app et compte si tu penses que c'est bien.
  > - O13 : Oui, le même anneau pour l'artisan
  > - O9 :  · le prix de l'abonnement est annoncé directement en expliquanrt que le premier mois est gratuit...
  > pour si elle se trompe, dans tous les tests effectué, 99% des lignes redigés sont corrects, par rapport a ce qui aété dit une information absente vous est transmise
  > vous pouvez facilement importer depuis vos dernières facture le cataloge de vos produits usuels.
  > il faudra valider les text... tu devraus me faire un endroit pour les valider et ecrire
  > - O10 : Valider · et aussi passés payants avec l'abonnement et une ligne revenu estimés pourle mois .... si annuel alors c'est en une fois...
  > - O1 :  · plutot B mais c'est moche, je veux un guide simple et efficace et aussi une démo qui passe essai ne refait pas le guide alors qu'un essai sans demo si.
  > - O4 :  · je pense que je vais enlever les métiers et mettre soit des catégories soit pas de métier pour ouvrir l'application a plus de monde car capable de faire plein de chose
  > - O2 : Valider · text a revoir
  > - O3 : A : pastilles et légende · juste il faut vérifier que les point 1,2,3 soit bien a l'écran sinon pas bon pour l'experience utilisateur
  > - O5 : Valider · ensuite configurer la marque jolie, tva mets des bouton pour les taux existants..
  > - O6 : Valider · plutot un guide simple qu'on peut passer
  > - O7 : Pas valider · c'est l'utilisateur qui gpre ces envoie. mais un warning est le bienvenue
  > - O8 : Pas valider · demander un support

- 2026-11-22 : lecture de Claude, pour la v3 : un seul mécanisme de guide (anneau, bulle, « Passer ») pour le commercial, la démo de l'artisan (O13, O1 et O3 s'y fondent : liste des offres dessous, pastilles 1, 2, 3 toutes visibles) et la mise en route de l'essai (O5, O6 : entreprise, TVA avec boutons de taux, marque, prix ; passable). Le guide de découverte s'ouvre sur une démo et sur un essai sans démo, jamais au passage d'une démo à l'essai. O7 : avertissement, l'envoi reste possible. O8 : « Demander de l'aide » (message ou rappel) remplace la page de questions. O10 : payants et revenu estimé du mois, l'annuel compté une fois, le mois du paiement (dépend du lot B). O4 : hors de ce chantier, tâche Origin 538 ; le guide garde l'étape du métier tant que Xavier n'a pas décidé. Textes : une page de validation où Xavier lit, réécrit et valide chaque texte.
- 2026-11-22 : deux chiffres de O9 vérifiés dans le dépôt avant usage. « 99 % des lignes » : aucune mesure ne le donne (94 à 98 % au banc inventé, `29-qualite-extraction/ETAT.md` ; 96 % au roadmap ; la seule vraie dictée, Luigi : 5 à 7 lignes justes sur 7). « Importer depuis vos dernières factures » : l'import existe pour d'anciennes offres en PDF créées par ordinateur (`import_catalogue.html.twig`) ; des factures n'y ont pas été essayées. Signalés à Xavier, ses mots gardés dans la page des textes avec la mesure à côté.

- 2026-11-22 : maquette v3. Textes dans une seule source (`textes.json` du scratchpad, 102 textes, à verser au dépôt avec le plan) qui produit la maquette et `TEXTES-onboarding.html`, la page où Xavier valide ou réécrit chaque texte. Trois guides jouables (commercial, découverte de l'artisan, mise en route de l'essai), moteur commun. Questions O14 à O20, et le point 7 reposé. La v2 est gardée dans `MAQUETTE-onboarding-v2.html`.
- 2026-11-22 : textes de la v3 relus par `relecteur-xtradev`, « À corriger » puis « Prêt ». Écartés avec raison : blocage de l'envoi (remplacé par O7 du 22.11), « sans montant » (remplacé par O10 du 22.11), formule annuelle (décidée au lot B). « 10 offres » non ajouté au mois gratuit (P4 du 19.11 : plafond de la formule). Restent bloquants selon le relecteur, à trancher par Xavier : k.o3.r (99 % sans source) et k.o4.r (factures jamais essayées) ; ses mots gardés, propositions dans les notes.
- 2026-11-22 : gate visuel rouvert sur la v3.

- 2026-11-22 : réponses de Xavier à la v3, collées telles quelles :

  > Réponses, maquette de l'onboarding v3 (22.11) :
  > - O14 : Valider · les conseils sont pas très bien visible en grisé et cachl derrière le bouton
  > - O15 :  · je veux que tu me fasse une version ou ont peux par exemple swipe les type de modèle et une ou on peut zoomer ça doit aller vite.
  > pareil pour la couleur. et le rendre sympa
  > - O16 : Valider
  > - O17 : Valider · ont peut intégrer le commercial si y'a pour que ce soit plus simple ?
  > - O18 : Valider · le ce qu'on dit pas je sais pas si c'est utile
  > - O19 : Valider · ceux ou un abonnement est en cours ont pourrais imaginer avoir revenu reccurent estimé, revenu du mois
  > - O20 : A : page des textes, puis le code
  > - P7 :  · je sais pas regarde

- 2026-11-22 : point 7 vérifié par Claude : aucun commit sur les fichiers de la démo et de l'administration depuis le 2026-11-20 à 16:15 (`79abbd6`), `~/offreGen-admin` propre, aucun processus PHP, quatre sessions offreGen au repos (sujet non visible). Ce chantier reprend les lots 6 et 7 ; une ligne sera écrite dans l'`ETAT.md` de l'administration au premier commit de code.
- 2026-11-22 : « Ma marque » existe (logo, pastilles de couleur et avis de contraste, douze modèles en vignettes, page « Voir en grand » à onglets). O15 la refait en carrousel : balayer les modèles, zoomer, balayer les couleurs, aperçu aussitôt.

- 2026-11-22 : maquette v4, réduite à ce qui reste ouvert (O21 à O26) ; v3 gardée dans `MAQUETTE-onboarding-v3.html`. Conseils sans voile (O14), « Votre marque » en carrousel avec agrandissement et couleur d'un toucher (O15, couleur approchée par teinte de l'image dans la maquette), carte du commercial dans « Demander de l'aide » (O17), « Ce qu'on ne dit pas » en trois lignes avec l'avis de Claude (O18), revenu récurrent estimé et revenu du mois (O19). 113 textes dans `textes.json`.
- 2026-11-22 : textes neufs relus par `relecteur-xtradev` : « À corriger » aux tours 1 et 2 (« sécurisé » perdu de la liste, boutons à l'infinitif, accords), « Prêt » au tour 3. Restent à trancher par Xavier : k.o3.r et k.o4.r.
- 2026-11-22 : gate visuel rouvert sur la v4.

- 2026-11-22 : réponses de Xavier à la v4, collées telles quelles :

  > Réponses, maquette de l'onboarding v4 (22.11) :
  > - O21 : Valider
  > - O22 : Valider · il manque le fait que l'on doit pouvoir mettre la couleur de notre choix
  > - O23 : Oui, le même écran dans les réglages · verifie qu'il est agréable a utiliser ui/ux ...
  > - O24 : Pas valider · juste écrire un message et etre rappelé
  > - O25 : Garder, en court et en dernier · text a verifier
  > - O26 : Valider · fais une petite analyse de si cela te semble assez simple car ça doit etre clair pour éviter problème et litige avec les commerciaux

- 2026-11-22 : O22 : l'application a déjà « Autre » (sélecteur de couleur) et l'avis de contraste avec deux versions plus foncées (`_reglages_marque.html.twig`, `avisCouleur` de `app.js`) ; la v4 les avait perdus. Autres écarts de la v4 trouvés à la revue O23 : six modèles sur douze, sans familles ; couleurs qui n'étaient pas les six teintes de `Marque::TEINTES` ; « Celle du logo » avant tout logo ; flèches de 40 px et pastilles de 44 px ; balayer aurait changé le modèle dans des réglages enregistrés seuls ; phrases du modèle Lettre perdues.
- 2026-11-22 : O26 analysé : la v4 ouvrait cinq litiges (« revenu » lu comme gain ; facturé compté comme payé ; mois tenu par une date tapée à la main au lot B3 ; total sans détail ; récurrent présenté comme un fait). Aucun état « payé » ni montant d'abonnement n'existe dans le code : les montants dépendent du lot B3. Aucune règle de commission n'est écrite dans le dépôt.
- 2026-11-22 : O25 vérifié contre le dépôt : k.n1 juste (Whisper et Qwen chez Infomaniak, `.env.example`) ; fin de k.n2 fausse (« quelques minutes », jamais mesuré de bout en bout), réécrite ; k.n4 juste ; contradiction relevée entre la rubrique et k.o3.r (99 %).
- 2026-11-22 : maquette v5, réduite à O27 à O33 ; v4 gardée dans `MAQUETTE-onboarding-v4.html`. « Votre marque » à douze modèles en quatre familles, modèle du métier proposé d'abord, couleur libre avec l'avis de contraste de l'application (même calcul) ; « Ma marque » des réglages (balayer compare, « Prendre ce modèle », couleur enregistrée au toucher, « Votre modèle ») ; « Demander de l'aide » en un écran (question, numéro prérempli, rappel) ; suivi « Payé par vos artisans » avec détail par paiement et estimation en second. 124 textes dans `textes.json`.
- 2026-11-22 : défauts trouvés en jouant la v5 et corrigés : un redimensionnement de la fenêtre (barre d'adresse d'un téléphone) relançait l'étape du guide et effaçait le choix (`guide3.js` relance seulement si la largeur change) ; pastille « Autre » et famille « Simples » hors de l'écran à droite ; `[hidden]` sans effet sous `display:grid` ; image du zoom vide comptée cassée ; numéro de téléphone coupé en fin de ligne.

- 2026-11-22 : textes v5 relus par `relecteur-xtradev` : « À corriger » au tour 1 (« L'équipe Dicta » alors que Xavier est seul, remplacée par son nom, qui viendra du compte de l'administrateur ; « l'administrateur » de k.n4 nommé ; suivi : factures d'abonnement seulement, une seule date, celle où le paiement arrive ; « Abonnement mensuel », « Abonnement annuel » ; estimation « payants », « hors TVA » ; doublon « Modèle de vos documents » retiré), « À corriger » au tour 2 (« réglée » contre « paiement arrivé »), « Prêt » au tour 3. Écartés avec raison : un seul nom pour le groupe d'artisans (la case « Amenés par vous » et la note le définissent), « lettre d'accompagnement » (Lettre est le nom du modèle).
- 2026-11-22 : gate visuel rouvert sur la v5.

- 2026-11-22 : décision T11 du chantier support, transmise par Xavier : le chantier support construit « Aide et questions » dans « Plus » et le circuit des questions ; « Demander de l'aide » (O8) y mène, sans mécanisme propre ; les questions fréquentes de la fiche d'essai deviennent les premières entrées publiées de la base d'aide ; le rôle de modérateur disparaît, il devient membre de l'équipe Support (T5). Détail : `docs/plans/2026-11-21_support/ETAT.md` (copie `~/offreGen`). Vérifié dans ce fichier : sa règle de destination (le commercial qui suit l'artisan, `cree_par`, d'abord ; sans commercial, le support) est déjà la réponse O30.
- 2026-11-22 : réponses de Xavier à la v5, collées telles quelles :

  > Réponses, maquette de l'onboarding v5 (22.11) :
  > - O27 : Valider
  > - O28 : Valider · remet les modèle en bloc quitte a mettre une etape en plus, ajouter logo puis choisir mquette et couleur  comme dans l'ancien format
  > - O29 : Valider
  > - O30 : Le commercial qui suit l'artisan, visible dans /admin
  > - O31 : Pas valider · je gere pas cela maintenant
  > - O32 :  · non il faut seulement qu'il voit leur par car sinon c'est pas clair et ça confond, je voulais juste que ce soit clair pour eux que le tarif par exemple annuel soit pas ajoutés au mensuel mais un appoint par exemple ecrire 50chf de plus abo annuel en janvier..
  > - O33 :  · je l'écris autre par, mais c'est 10% et avec la pssiblité de changer pour certains vraiment bon

- 2026-11-22 : O28 ambigu (O27 venait de valider le carrousel) : question posée, réponse « Réglages seuls en blocs », corrigée aussitôt par Xavier : « non partout oops ». Lecture retenue : blocs partout, la mise en route gagne une étape.
- 2026-11-22 : maquette v6, réduite à O34 à O36 ; v5 gardée dans `MAQUETTE-onboarding-v5.html`. Mise en route en cinq étapes (« Votre logo », puis « Modèle et couleur » : couleur en haut avec « Autre » et l'avis de contraste, douze modèles en blocs par famille, Marge proposé et coché, « Voir en grand ») ; suivi réduit à la part du commercial (mensuel en tête, annuel sur une ligne « En plus », détail par paiement) ; taux par commercial dans `/admin`. Textes h.*, r.* et e.marque.* retirés ; 112 textes.

- 2026-11-22 : textes v6 relus par `relecteur-xtradev` : « À corriger » au tour 1 (« ont payé » au lieu de « paiements arrivés », note d'administration contradictoire, mois d'un changement de taux : texte s.part.taux2 et taux sur chaque ligne du détail), « Prêt » au tour 2. Écartés avec raison : « Sa couleur devient celle de vos documents » (l'envoi du logo pose la couleur, `DepotReglages`), « vos offres et vos factures » (Dicta fait des factures). 112 textes.
- 2026-11-22 : gate visuel rouvert sur la v6.

- 2026-11-22 : réponses de Xavier à la v6 : « O34 : Valider, O35 : Valider, O36 : Valider ». Gate visuel franchi.
- 2026-11-22 : copie de travail avancée de `77954ce` à `aa5ea49` (`origin/chantier-voix-offre`, cinq commits d'autres sessions ; seul le dossier du chantier était hors suivi, rien d'écrasé).
- 2026-11-22 : phase 4 : `PLAN.md` écrit (contrat technique vérifié dans le code à `aa5ea49`, signatures, trois décisions fragiles, lots 0 à 11, 53 heures pour les lots 0 à 8 et 11, estimation de Claude). Le format est celui de la phase 4 du skill `chantier` ; le skill `writing-plans` n'a pas été chargé. `SPEC.md` mise à jour : section 3 réécrite, modérateur remplacé par l'équipe Support, exclusions T11, O31 et B3 ; l'ancien périmètre est gardé en annexe.

- 2026-11-22 : phase 5 ouverte. Branche `chantier-onboarding` (depuis `aa5ea49`), `.env` recopié de `~/offreGen`, `.env.test.local` sur `offregen_onboarding_test` (créée, droits recopiés de `offregen_test`, migrations jouées), `composer install`. Base de recette locale `offregen_onboarding_vue` (sans suffixe `_test`), serveur `php -S` sur 8077, comptes xavier@vue.test (administrateur) et nina@vue.test (commercial) ; mots de passe hors dépôt. Suite complète avant tout code : 1075 tests verts. Conversation Origin rattachée à la sous-tâche 534.
- 2026-11-22 : lot 0 (traceur) : `public/js/guide.js`, `public/css/guide.css`, `GuideControleur`, route `POST /guide/fini`. Défauts vus sur les captures et corrigés : anneau en ellipse blanche autour de la case du micro (cible : le rond lui-même), bulle centrée loin de sa cible sur PC, cible PC propre (`data-cible-pc`), anneau rond seulement autour d'une cible carrée.
- 2026-11-22 : lot 1 : migration `Version20261122300001` (`guide_artisan_le`, `guide_equipe_le`, `entrainement` ; comptes existants marqués « guide vu »), jouée sur les deux bases. Méthodes de `DepotReglages` : `guideAMontrer`, `guideFini`, `revoirGuide`, `rouvrirAssistant`. Défaut à moi trouvé par le test : `Base::valeur` rend `false` sans ligne, pas `null` ; le guide se montrait à tous.
- 2026-11-22 : lot 1bis : 93 textes versés dans `config/textes-onboarding.json` (insécables posées dans le fichier), fonction Twig `texte()`. Variables à la place des exemples : `{prenom}`, `{jours}`, `{offres}`, `{entreprise}`. Restent hors du code : « Ce qu'on ne dit pas » (O31) et les textes de la part (lot 9). Texte changé sans passer par Xavier, à lui signaler : c.b1.p2 (« les demandes d'appel » n'existent plus avec T11) devient « Vous suivez les démos et les essais de vos artisans. »
- 2026-11-22 : lot 2 : fin de démo (`Demos::terminee`, `Demos::joursAvantLaFin` au calendrier de Zurich), import refusé (410), feuille de dictée refusée d'avance (`data-epuise`, mécanisme existant), écran de fin à la première ouverture de la session, ruban « terminée », fenêtre des deux derniers jours (`_fenetre_demo.html.twig`, `demo-fin.js`, une fois par jour), attente d'une démo sans promesse de courriel (même règle que `Traiteur::prevenir`). Écarts au texte K1 validé, à signaler à Xavier : « un mois gratuit » retiré (lot B non livré, l'essai compte des offres) et la date de suppression des offres retirée (lot 8 de l'administration non livré).
- 2026-11-22 : lot 3 : guide de l'artisan en cinq étapes. `app.js` n'est pas touché : le guide intercepte le premier toucher du micro pour montrer les conseils, puis rejoue le micro. Le passage à l'essai marque le guide vu (O1) et rouvre la mise en route (lot 4, IBAN gardé). Défaut vu sur la capture et corrigé : repères affichés un mot par ligne (grille CSS sur un texte en gras).

- 2026-11-22 : lot 4 : mise en route. `ReglagesControleur::ETAPES` devient entreprise, TVA, logo, modèle et couleur ; la dernière mène à « Vos prix » s'il reste des produits sans prix, compté comme cinquième étape. Étape « Votre logo » (`_reglages_logo.html.twig`), logo retiré de l'étape modèle et couleur (les réglages gardent « Ma marque » telle quelle, O28), TVA en quatre boutons (`mise-en-route.css`), « Passer le guide » en haut, un seul bouton en bas. `ReglagesRangesTest` mis au nouvel ordre (décision 2 du plan). Défauts vus sur les captures et corrigés : deux boutons principaux à l'étape du logo ; compteur « sur 4 » contre « sur 5 » à la maquette.
- 2026-11-22 : lot 5 : avertissement avant l'envoi d'une offre au nom provisoire (`Demos::estNomProvisoire`, `avertissement-envoi.js` devant `apercu-pdf.js`, qui n'est pas touché). « Envoyer quand même » partage et note l'envoi.
- 2026-11-22 : lot 6 : aide à la vente, `/admin/aide-vente`, depuis « Plus » de l'administration. Écart au plan, à signaler à Xavier : « Les questions à poser » reste hors de la page. Son contenu n'a jamais été montré dans une maquette, et la seule source (`acquisition.md`, section 12) est une note interne qui annonce 49 francs. k.o3.r et k.o4.r sont dans la page avec les mots de Xavier, non vérifiés (99 %, import depuis des factures) : signalés une fois, pas corrigés.
- 2026-11-22 : lot 7 : suivi, `/admin/suivi` (démos ouvertes et leur activité, « Pas encore dicté » 48 heures après l'ouverture, amenés, à l'essai, abonnés). Défaut vu sur la capture et corrigé : démos sans nom d'entreprise indiscernables (« Démo Peintre ») ; le courriel tapé par le prospect s'affiche sur la ligne.

- 2026-11-22 : lot 8 : arrivée du commercial. Bienvenue en trois écrans au premier passage d'un commercial dans `/admin` (pas pour l'administrateur ni le modérateur) ; « Faire ma démo d'entraînement » lance le guide de l'équipe, dix gestes : créer la démo, le métier, « Créer la démo », l'ouvrir sur cet appareil, puis les six gestes de l'artisan avec les bulles du commercial, jusqu'à « Revenir à mon espace » ; carte « Le guide est terminé » au retour. Démo d'entraînement : `entrainement = 1`, sans `apporte_par`, hors limite de dix, trois en 24 heures au plus (décision prise par Claude, à signaler), courriel du membre déjà posé. Retour direct : `RetourEquipe` (marque en session posée après `ouvrirSession`, contrôlée au retour : même démo, entraînement créé par lui, toujours membre, numéro de session inchangé). Écart au plan : le lien de la démo d'entraînement s'ouvre en GET pour son créateur connecté, sans la page d'entrée (un geste de moins ; le jeton reste à usage unique).
- 2026-11-22 : lot 8, textes validés changés pour coller à l'écran, à signaler à Xavier : g.rond et g.fin.texte parlaient du « rond + », qui n'existe pas dans l'administration (« Créer une démo »).
- 2026-11-22 : lot 8, défauts vus en jouant le parcours et corrigés : carte de fin absente (la session neuve du retour perdait la marque du guide) ; à l'étape 4, la cible cachée sous la barre du bas, l'anneau semblait entourer « Terminé » (le guide fait défiler la cible au-dessus des barres fixes) ; « Suivant » à l'étape 8 sautait la ligne à vérifier (étape 10 marquée `data-exacte`). Test d'accueil par rôle mis à jour : la première arrivée d'un commercial passe par la bienvenue.
- 2026-11-22 : lots 9 et 10 non commencés, bloqués : aucun paiement (lot B3 de l'administration) ni route « Aide et questions » (le chantier support est en maquette) sur `origin/chantier-voix-offre` (vérifié par `git grep`). La branche distante a avancé de deux commits d'autres chantiers depuis `aa5ea49` : à intégrer au commit.
- 2026-11-22 : lot 11 : revue lourde (le diff touche droits, session, SQL et routes d'administration, surfaces sensibles du profil) : trois lentilles `route-heavy` (correction, sécurité, régression) et une revue OWASP, sans phpunit ; Fable écarté (le CLAUDE.md global le réserve à une demande de Xavier, il l'emporte sur le profil). Relecture XtraDev des textes écrits pendant le code.

- 2026-11-22 : lot 11 : revue lourde rendue (`REVUE.md`). Trouvaille haute, vérifiée et corrigée : la marque « guide en cours » restait en session, et la démo suivante d'un vrai prospect devenait une démo d'entraînement. Autres corrections retenues : retour direct limité à deux heures ; ouverture de la démo d'entraînement par un POST (plus de GET qui écrit) ; import du catalogue et relance d'une dictée fermés après la fin d'une démo ; ruban et suivi au calendrier de Zurich ; « Revoir le guide » dans « Plus » ; impression de l'aide à la vente ; touche D pendant les conseils ; libellés et trace des gestes d'entraînement ; test des textes élargi au PHP et aux ternaires. Relecture XtraDev des textes écrits au code : « À corriger » au tour 1, corrections appliquées.
- 2026-11-22 : après corrections : phpstan vert, suite complète verte (1114 tests), parcours 2, 3, 4 et 8 rejoués au navigateur sans erreur. `RECETTE.md` écrite ; gate prod ouvert. Rien n'est commité : Xavier n'a pas donné d'accord de commit pour ce chantier.

- 2026-11-22 : relecture XtraDev des textes écrits au code : « À corriger » aux tours 1, 2 et 3 (limite des trois tours atteinte). Tour 2 : le fichier relu n'était pas à jour, Claude l'avait affirmé à tort ; corrigé, et le suivi compte maintenant « 2 offres sur 10 », même unité que les dix offres annoncées. Tour 3 : seul point obligatoire, le texte de la page du code d'une démo d'entraînement, collé avec la demi-phrase « ensuite, reconnectez-vous » vérifiée dans le code. Au choix de Xavier : « Suivant », « Plus tard » ; le ruban à 10 offres sur 10 ; une phrase sur ce que l'essai engage.

- 2026-11-23 : `ECARTS-code.html` (images dans `ECARTS-code-img/`) : le code des lots 0 à 8 face aux maquettes validées, neuf écarts E1 à E9 à trancher par Xavier, lots 9 et 10 montrés comme bloqués. Captures refaites sur la base locale (parcours du lot 8 rejoué : dix gestes, erreurs [], démo entrainement=1 apporte_par=NULL). Correction d'une affirmation du 22.11 (lot 6) : « Les questions à poser » a une source, les six questions de `docs/terrain/2026-11-18_questions-adequation.md` (entretiens de découverte de Xavier), en plus de la note interne à 49 francs.

- 2026-11-23 : lots 0 à 8 commités, intégrés sur 15 commits arrivés sur `origin/chantier-voix-offre` (conflits résolus avec le chantier des activités : message de démo dans le mot de l'activité, groupes de métiers sous la démo d'entraînement), poussés (`c8afd28`) et déployés sur le test, sonde verte, migrations à jour. Accord de Xavier : « tu peux commit sur le test » ; pas de vrais clients sur le test (réponse du jour).
- 2026-11-23 : réponses de Xavier aux écarts E1 à E9 (`ECARTS-code.html`). Codé : E2 (« client » à la place d'« artisan » dans l'administration et les textes du commercial ; onglet « Clients », « Mes clients »), E3 (guide en onze gestes : onglet « Clients », puis « Créer une démo »), E5 (trois démos d'entraînement, puis une par mois civil, message avec la date de la suivante), E7a (95 %), E8 (« Vos prix » en « Étape 5 sur 5 » avec « Passer le guide » depuis la mise en route). Défaut vu sur capture et corrigé : la liste des clients montrait les démos d'entraînement du membre (test rouge sans la correction, vert avec). Suite complète verte (1300 tests), phpstan vert, parcours 4 et 8 rejoués sans erreur. Restent : E1 (texte K1 remis et ses trois fonctions construites ici : suppression des démos finies depuis 30 jours, mois gratuit à la place des 10 offres de l'essai, « Demander un appel ») et E6 (« Les questions à poser » tirées des six questions, en maquette, en parlant de « client »).

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-21 | Styles neufs dans un fichier à part (comme `demo.css`, `admin.css`) | `public/css/app.css` modifié sans commit par d'autres sessions |
| 2026-11-21 | Base de test du chantier : `offregen_onboarding_test` (à créer en phase 5) | la base `offregen_test` est partagée entre sessions |
| 2026-11-21 | Ordre des lots : kit de vente et suivi d'abord, arrivée du commercial (A1, A2) en dernier, à couper si aucun commercial n'arrive | point 1, recommandation acceptée par Xavier |
| 2026-11-21 | Démo d'entraînement marquée « entraînement » : hors limite des 10 démos par jour, sans commission (`apporte_par` vide), supprimée avec les autres démos | point 2, idem |
| 2026-11-21 | Trois repères sur la première offre, montrés une fois, un seul bouton « Compris » | point 3, idem |
| 2026-11-21 | Passage à l'essai : « Plus tard » gardé dans l'assistant ; envoi d'une offre bloqué tant que l'entreprise porte le nom provisoire « Démo <métier> » | point 4, idem |
| 2026-11-21 | Kit de vente : une page dans `/admin`, imprimable par le navigateur, sans PDF à part | point 5, idem |
| 2026-11-21 | Le commercial voit ses chiffres (amenés, passés à l'essai), sans montant | point 6, idem |
| 2026-11-21 | Hors périmètre de la section 4 de `SPEC.md` confirmé | « ok pour tes recommandations » |
| 2026-11-22 | O12 : une vraie démo d'entraînement, avec retour direct sans mot de passe (B) ; pas de fausse démo simulée | Une simulation recopie les écrans de l'artisan et diverge à chaque changement de l'application ; le commercial apprendrait un écran qui n'existe pas. Le retour direct donne la même fluidité. Coût d'une dictée d'entraînement : quelques centimes. Xavier laissait le choix (« si tu penses que c'est bien ») |
| 2026-11-22 | Guide de découverte de l'artisan : à l'ouverture d'une démo, et d'un essai sans démo ; jamais au passage d'une démo à l'essai | O1 et O13, réponse de Xavier |
| 2026-11-22 | O7 : avertissement avant l'envoi d'une offre au nom provisoire, envoi possible (remplace l'envoi bloqué du point 4) | « c'est l'utilisateur qui gère ses envois, mais un warning est le bienvenue » |
| 2026-11-22 | O4 (métiers ou catégories) hors de ce chantier, tâche Origin 538 ; l'étape du métier reste dans le guide jusqu'à sa décision | décision produit qui touche le chantier des métiers en service sur le test |
| 2026-11-22 | Point 7 : ce chantier reprend les lots 6 et 7 de l'administration | vérifié : aucune activité depuis le 20.11, Xavier : « je sais pas regarde » |
| 2026-11-22 | O20 : les textes passent par la page des textes, puis par le code | réponse A de Xavier |
| 2026-11-22 | O23 : « Ma marque » des réglages devient le même carrousel ; balayer compare, le modèle change au bouton « Prendre ce modèle », la couleur s'enregistre au toucher | réponse de Xavier ; dans des réglages enregistrés seuls, balayer ne doit rien changer |
| 2026-11-22 | O24 : « Demander de l'aide » = la question et le numéro, puis un rappel ; plus de carte du commercial ni d'appel direct | réponse de Xavier : « juste écrire un message et être rappelé » |
| 2026-11-22 | O25 : « Ce qu'on ne dit pas » gardé, trois lignes, en dernière rubrique | réponse de Xavier |
| 2026-11-22 | O26 : suivi en « payé » hors TVA, détail par paiement, estimation en second ; montants seulement après le lot B3 ; ils suivent `apporte_par` | analyse demandée par Xavier, à confirmer en O32 |
| 2026-11-22 | O28 (corrigé « non partout ») : modèles en blocs partout ; la mise en route passe à cinq étapes, « Votre logo » puis « Modèle et couleur » ; les réglages gardent « Ma marque » telle qu'elle est ; le carrousel est abandonné. Remplace O23 | réponse de Xavier |
| 2026-11-22 | T11 du chantier support : « Demander de l'aide » mène à « Aide et questions » du chantier support ; ce chantier ne construit ni écran d'aide ni circuit. O29 et O30 y passent ; le modérateur de la SPEC (B3) devient l'équipe Support | décision T11, transmise par Xavier |
| 2026-11-22 | O31 : « Ce qu'on ne dit pas » en attente, hors du code de ce chantier tant que Xavier ne l'a pas traitée | « je gère pas cela maintenant » |
| 2026-11-22 | O32 : le commercial voit seulement sa part ; l'annuel sur une ligne « En plus », le mois où son paiement arrive, jamais ajouté au mensuel ; paiements arrivés seulement, hors TVA, détail par paiement. Remplace la décision O26 | réponse de Xavier, à confirmer en O35 |
| 2026-11-22 | O33 : 10 %, taux propre possible pour un commercial, fixé par l'administrateur ; proposition de Claude : un changement vaut pour les paiements à venir et reste noté. La règle complète (durée, départ, remboursement), Xavier l'écrit ailleurs | réponse de Xavier, à confirmer en O36 |
| 2026-11-22 | « Demander un appel » du lot 6 de l'administration n'est pas construit : l'écran de fin et la fenêtre mènent vers « Aide et questions » (T11) ; `demande_appel` reste inutilisée | décision fragile 1 de `PLAN.md` |
| 2026-11-22 | L'assistant de tous les comptes devient : entreprise, TVA, logo, marque, puis « Vos prix » ; « Vos conditions » reste dans les réglages | décision fragile 2 de `PLAN.md` |
| 2026-11-22 | Retour direct du commercial par une marque en session contrôlée par la `version_session` du membre | décision fragile 3 de `PLAN.md` ; revue lourde au lot 11 |
| 2026-11-22 | E1 lu dans les tables existantes, sans table d'événements | les quatre dates existent déjà (`enregistrement`, `offre.envoyee_le`, `journal_admin`) |
| 2026-11-22 | Démo d'entraînement : trois en 24 heures au plus, seulement sous la marque à usage unique posée par « Faire ma démo d'entraînement » | Claude, revue du 2026-11-22 : sans cela, une démo de prospect pouvait devenir d'entraînement |
| 2026-11-22 | Retour direct : marque valable deux heures ; ouverture de la démo d'entraînement par un POST depuis la page du code | revue du 2026-11-22 (sécurité, OWASP) ; décision fragile 3 du plan précisée |
| 2026-11-22 | Après la fin d'une démo : dictée, import, import du catalogue et relance d'une dictée refusés | revue du 2026-11-22 ; ce qui coûte de l'IA s'arrête avec la démo |
| 2026-11-22 | Ruban, fenêtre et suivi comptent les jours au calendrier de Zurich | revue du 2026-11-22 : le ruban (heures) contredisait la fenêtre (jours) |
| 2026-11-23 | E1 : le texte K1 de l'écran de fin revient tel que validé ; ses trois promesses (suppression à 30 jours, mois gratuit, « Demander un appel ») se construisent dans ce chantier | réponse de Xavier (« il faut pas retirer, on laisse et on note que c'est à faire et on fait » ; « Texte maintenant, ici ») ; remplace T11 pour « Demander un appel » sur l'écran de fin |
| 2026-11-23 | E2 : dans l'administration, la personne à qui le commercial vend s'appelle « client » | réponse de Xavier (« démo du client », « Tout l'espace commercial ») |
| 2026-11-23 | E3 : le guide du commercial passe par l'onglet « Clients », puis « Créer une démo » ; pas de rond + | réponse de Xavier |
| 2026-11-23 | E5 : trois démos d'entraînement au total, puis une par mois civil (Zurich) | réponse de Xavier ; remplace « trois en 24 heures » |
| 2026-11-23 | E7a : 95 % dans k.o3.r ; E7b, E9 : gardés tels quels ; E4 validé | réponses de Xavier ; 95 % est l'objectif du sous-chantier 29, pas une mesure (signalé une fois) |
| 2026-11-23 | E8 : « Vos prix » est la cinquième étape de la mise en route quand on y arrive depuis l'assistant | réponse de Xavier |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | validée le 2026-11-21 ; section 3 mise à jour le 2026-11-22 |
| `PLAN.md` | 4 | écrit le 2026-11-22 ; lots 0 à 8 et 11 faits, 9 et 10 bloqués |
| `REVUE.md` | 7 | 2026-11-22, revue lourde, corrections faites |
| `RECETTE.md` | 8 | 2026-11-22, en attente de Xavier |
| `MAQUETTE-onboarding.html` | 3 | v6, validée le 2026-11-22 (gate visuel franchi) |
| `MAQUETTE-onboarding-v5.html` | 3 | v5, répondue le 2026-11-22 |
| `MAQUETTE-onboarding-v4.html` | 3 | v4, répondue le 2026-11-22 |
| `MAQUETTE-onboarding-v3.html` | 3 | v3 entière, répondue le 2026-11-22 |
| `TEXTES-onboarding.html` | 3 | page de validation des 112 textes |
| `MAQUETTE-onboarding-v2.html` | 3 | v2, répondue le 2026-11-22, gardée pour l'historique |

## Preuves

### Phase 1

```
$ git show origin/chantier-voix-offre:src/Compte/Comptes.php | grep -A40 "function passerEnEssai" | grep -i -E "assistant|reglages"
(aucune ligne)
$ git show origin/chantier-voix-offre:src/Http/Controleur/ImportControleur.php | grep -n -i "demo"
46:        if ($artisan !== null && $artisan['demo'] === 1 && $artisan['plafond_jour'] !== null
48:            return $this->page("Ce compte de démonstration a atteint ses {$artisan['plafond_jour']} imports du jour. Réessayez demain.", 429);
```

### Phase 3

```
$ node check.js MAQUETTE-onboarding.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ node captures.js (14 blocs par vue, bureau clair, bureau sombre, téléphone ; images regardées)
bureau blocs 14 défauts [] erreurs JS []
sombre blocs 14 défauts [] erreurs JS []
tel blocs 14 défauts [] erreurs JS []
$ comptage sur la page injectée
nbsp 230, espaces ordinaires avant : ; ? ! 0, tirets longs 0, BOM non, décisions 12
```

Défauts vus sur les captures et corrigés (les sondes ne les relevaient pas) : grand micro décentré, pastille 3 cachée sous la légende, titre d'une feuille sous la croix, étiquettes « Exemple » étirées, jours restants coupés en deux lignes, lien décentré.

### Phase 3, version 2

```
$ node parcours-guide.js (le guide joué en touchant chaque cible, téléphone clair et PC sombre)
14 étapes, anneau et bulle dans le téléphone à chaque étape, aucun chevauchement anneau et bulle, erreurs JS []
$ node check.js MAQUETTE-onboarding.html
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
$ comptage
tirets 0, nbsp 251, décisions 13
```

Vu sur les captures et corrigé : bulle de l'étape 9 posée sur les trois conseils (remontée en haut), anneau statique de O13 décalé (calculé sur la position du micro). Faux positif de la sonde « texte coupé » sur l'anneau, élément vide.

### Phase 3, version 3

```
$ node parcours3.js (trois guides joués en touchant chaque cible, téléphone clair et PC sombre)
étapes jouées 50, anomalies 0 (anneau et bulle dans l'écran, sans chevauchement ; pastilles 1, 2, 3 et légende toutes visibles, 6 sur 6)
tel erreurs JS [] ; sombre erreurs JS []
$ node textes-test.js TEXTES-onboarding.html (Valider, Réécrire, frappe, filtre)
largeur 390 et 1440, filtre artisan 40 textes tous artisan, copie : « - k.o1.r : Valider / - k.o3.r : Réécrire : « … » », erreurs JS []
$ node check.js (les deux pages)
bureau : largeur 1440/1440, kit oui ; telephone : largeur 390/390, kit oui
$ comptage
MAQUETTE tirets 0, nbsp 177, espaces avant ponctuation 0 ; TEXTES tirets 0, nbsp 110, espaces avant ponctuation 0 ; BOM non
```

Vu sur les captures et corrigé : bulles posées sur leur cible (règle CSS restée sur l'ancien `#guide`), bulle qui cachait le code QR, chiffres du suivi sur trois colonnes.

### Phase 3, version 4

```
$ node parcours3.js (guides de l'artisan et de la mise en route)
étapes 18, anomalies 0, erreurs JS [] (téléphone clair, PC sombre)
$ node marque-test.js (au doigt et à la souris)
flèche : Sobre -> Atelier ; glisser : -> Bandeau ; couleur : #2E7D32 ; agrandir : zoom ouvert sur grand/bandeau.jpg ; fermer ; erreurs JS []
$ node textes-test.js ; node check.js (les deux pages)
largeur 390 et 1440, filtre artisan 51 textes, erreurs JS [] ; kit oui
$ comptage
MAQUETTE tirets 0, nbsp 76, espaces avant ponctuation 0, décisions 6 ; TEXTES tirets 0, nbsp 112 ; BOM non
```

Vu sur les captures et corrigé : le guide de l'artisan s'arrêtait sur « Terminer » (étape d'attente ajoutée).

### Phase 3, version 5

```
$ node marque5-test.js (téléphone au doigt, PC sombre à la souris)
mise en route : départ Marge (Simples), famille Couleur -> Bandeau, flèche -> Moderne, glisser -> Latéral, Autre #F5E663 -> avis « se lit mal » 1,2:1, Bien visible -> #5A5525, agrandir ; cibles sous 48 px : 0
réglages : départ Lettre (« Modèle de vos documents », phrases de la lettre visibles), flèche -> Atelier (« Prendre ce modèle »), prendre -> Atelier « Votre modèle », « Enregistré », phrases masquées ; Celle du logo #E0A526 -> avis 2,1:1 ; erreurs JS []
$ node parcours3.js : 5 étapes, erreurs JS [] (téléphone, PC sombre)
$ node textes-test.js : filtre artisan 55, copie juste, erreurs JS []
$ node check.js (les deux pages) : images cassées 0, kit oui
$ node captures.js : 4 blocs par vue, seuls débords : les pages voisines du carrousel, voulus
$ comptage : MAQUETTE tirets 0, BOM non ; TEXTES tirets 0, BOM non
```

### Phase 3, version 6

```
$ node bloc6-test.js (téléphone au doigt, PC sombre à la souris)
logo : étape 3 ; Suivant -> « Modèle et couleur » : 12 blocs, 4 familles, Marge coché, couleur du logo #7A2E1C
toucher Bandeau -> coché ; Autre #F5E663 -> avis « se lit mal » 1,2:1 ; Bien visible -> #5A5525 ; Voir en grand Technique -> technique.jpg ; fermer ; Suivant -> « Les prix »
cibles sous 48 px : 0 ; débord horizontal : 0 ; erreurs JS []
$ node parcours3.js : 6 étapes jouées, erreurs JS [] (téléphone, PC sombre)
$ node captures.js : défauts [], erreurs JS [] (clair, sombre, téléphone)
$ node textes-test.js : filtre artisan 39, erreurs JS [] ; node check.js (les deux pages) : OK
$ comptage : MAQUETTE tirets 0, BOM non, décisions 3 ; TEXTES tirets 0, BOM non
```

Vu sur les captures et corrigé : « Voir en grand » à 44 px, lien « Changer » du logo sous 48 px.

### Phase 5, lots 0 à 3

```
$ php vendor/bin/phpunit (avant tout code)
OK (1075 tests, 9241 assertions)
$ php vendor/bin/phpunit --filter "GuideTest|PassageDemo|PassageEssai|FinDeDemo|DemoTest|DemosTest"
OK (52 tests, 519 assertions)
$ php vendor/bin/phpunit --filter "FinDeDemo|TextesOnboarding|GuideTest|DroitsEquipe|IsolationRoutes|PagesTest|EcransTest"
OK (80 tests, 2091 assertions)
$ php vendor/bin/phpstan analyse
[OK] No errors
$ node lot3.js (micro simulé, téléphone clair et PC sombre, démo créée par Nina dans l'interface)
tel 1-micro GUIDE · 1 SUR 5 ; 2-conseils 2 SUR 5, conseils visibles true ; 3-terminer ; 4-attente ; 5-reperes ; Compris 204 ; guide_artisan_le posé ; guide absent ensuite ; erreurs []
pc  idem, erreurs []
$ node lot2.js
fenêtre visible true « Votre démo se termine demain » ; après Continuer, le même jour false ; ouverture après la fin /fin-de-demo ; micro « Votre démo est terminée. Pour dicter de nouveau, passez à l'essai. » ; erreurs []
```

Captures ouvertes et regardées : anneau du micro (téléphone et PC), conseils, « Terminer », attente, repères, fenêtre, écran de fin.

### Phase 5, lots 4 à 7

```
$ php vendor/bin/phpunit --filter "ReglagesRangesTest|GuideTest|MarqueEcran"
OK (27 tests, 294 assertions)
$ php vendor/bin/phpunit --filter "AvertissementEnvoi|RenvoiOffre|ApercuPdf"
OK (9 tests, 42 assertions)
$ php vendor/bin/phpunit --filter "AideVenteTest" ; --filter "TextesOnboarding|DroitsEquipe" ; --filter "SuiviTest"
OK (1 test, 9 assertions) ; OK (12 tests, 430 assertions) ; OK (5 tests, 19 assertions)
$ php vendor/bin/phpstan analyse
[OK] No errors
$ node lot4.js (démo, passage à l'essai, mot de passe, mise en route ; téléphone et PC sombre)
entreprise > tva > logo > marque puis /vos-prix, message « Votre compte est prêt. … », un seul bouton en bas, aucun débord, erreurs []
$ node lot5.js
avertissement visible true « Votre offre porte encore « Démo Peintre » » ; POST envoyee 303 ; offre notée envoyée 1 ; erreurs []
$ node lot67.js (Nina, téléphone et PC sombre)
suivi : 16 démos, chiffres 19 / 2 / 0, aucun débord ; kit : une rubrique ouverte à la fois, aucun débord, erreurs []
```

### Phase 5, lot 8

```
$ php vendor/bin/phpunit --filter ArriveeCommercialTest
OK (6 tests, 56 assertions)
$ php vendor/bin/phpunit (suite complète, avant correction du test d'accueil par rôle)
Tests: 1111, Failures: 1 (DroitsEquipeTest::testLAccueilRenvoieChaqueRoleASaPage : commercial neuf vers /admin/bienvenue, attendu ; test mis à jour)
$ php vendor/bin/phpunit --filter "DroitsEquipeTest" ; --filter "TextesOnboarding|ArriveeCommercial|GuideTest|SuiviTest"
OK (9 tests, 129 assertions) ; OK (23 tests, 447 assertions)
$ php vendor/bin/phpstan analyse
[OK] No errors
$ node lot8.js (commerciale neuve, téléphone, micro simulé)
bienvenue 1, 2, 3 ; guide 1 à 10 sur 10, anneau présent, aucun chevauchement bulle-anneau ; carte « Le guide est terminé » ; démo entrainement=1 apporte_par=NULL ; erreurs [] ; 26 s en automatique (critère de la SPEC : moins de dix minutes, à chronométrer par Xavier)
```

### Phase 6 et 7, après la revue

```
$ php vendor/bin/phpstan analyse
[OK] No errors
$ php vendor/bin/phpunit
OK (1114 tests, 9826 assertions)
$ php vendor/bin/phpunit --filter ArriveeCommercialTest ; --filter FinDeDemoTest ; --filter TextesOnboarding
OK (8 tests, 82 assertions) ; OK (9 tests, 39 assertions) ; OK (3 tests, 348 assertions)
$ node lot8.js ; lot2.js ; lot3.js ; lot4.js
guide de l'équipe 1 à 10 sur 10, carte « Le guide est terminé », démo entrainement=1 apporte_par=NULL ; fenêtre « Votre démo se termine demain », micro « … Pour dicter ou importer de nouveau … » ; guide de l'artisan Compris 204 ; mise en route jusqu'à /vos-prix ; erreurs [] partout
```
