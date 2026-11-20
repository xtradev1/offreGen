---
chantier: Responsive du site de test (téléphone, tablette, PC)
slug: 2026-11-19_responsive
phase: 3
statut: gate_ouvert
gate_attendu: accord de Xavier pour déployer sur le test, puis commit
---

## Prochaine action

Xavier regarde le site de test (commit 257d800) et répond dans `MAQUETTE-test.html` (7a seulement : « Valider » ou « À reprendre »), avec ce qui cloche ailleurs. Ensuite : corrections éventuelles depuis la copie propre `~/offreGen-responsive` (branche locale `responsive-tour5`, recalée sur la tête avant chaque poussée), puis `/roadmap-sync`.

À ranger à la clôture : base `offregen_responsive_test` (en root : `DROP DATABASE offregen_responsive_test`), copie `~/offreGen-responsive` (`git worktree remove`), serveur local du port 8001. Dans `~/offreGen`, mes fichiers des tours 1 à 4 restent modifiés en double de ce qui est commité (mêlés au travail d'autres sessions) : ne pas les commiter depuis là.

## Réponses de Xavier au 5e tour (MAQUETTE-tour5.html, 2026-11-20, texte collé tel quel)

```
2b Couleur tapée : Valider
3 Bouton plus : Actuel
4a Ligne libre : B : bande blanche. Commentaire : et enlève le jaune jsutre l'icone suffit
4b Prix gardé au rattachement : Valider. Commentaire : a mettre en jaune pour vérifier
5 Icônes dans la carte : Valider
7a Bandeau intégré : Valider. Commentaire : mais c'est pas assez lisible le blanc ne suffit pas
```

Consigne jointe : « Tu peux mettre des corrections que je t'ai mis et pousser directement ça en test. Je regarderai depuis le test. Tu pourras me refaire une petite maquette juste pour le point 7a. […] tu fais une maquette qui met ce que tout a mis en test. »

À coder (lecture de Claude) :
- 3 : décision A du 4e tour (total dans « Générer l'offre ») avec le bouton « + Ligne » actuel. Au téléphone et sur tablette seulement : au PC, la ligne du total reste (elle porte le bandeau de 7a).
- 2b : sous la couleur, quand la couleur tapée ou celle du logo est sous 7:1, un encadré avertit et propose « Bien visible » (7:1) et « Visible » (4,5:1, la couleur elle-même si elle y est déjà) ; sans choix, le document la fonce à 4,5:1 comme aujourd'hui.
- 4a : bande blanche à bord gris sous la ligne libre, icône sans fond jaune.
- 4b : fiche « Mettre au catalogue » (Nouveau produit avec catégorie, Déjà au catalogue) ; au rattachement, la ligne prend le produit et garde son prix en note « Garder 45.00 », la ligne passe à vérifier (en jaune) ; avertir quand l'unité change.
- 5 : icônes Archiver et Supprimer dans la carte au survol, au PC, sans place réservée.
- 7a : le bandeau « Annuler » prend la ligne du total au PC, avec un fond plus marqué que le blanc (« le blanc ne suffit pas »), contraste mesuré.

## Réponses de Xavier au 4e tour (MAQUETTE-tour4.html, 2026-11-19, texte collé tel quel)

```
1 Facture annulée : C : Envoyer seulement si elle était partie
2a Aperçu en direct : Valider
2b Couleur tapée entre 4,5 et 7 : (pas de réponse). Commentaire : aucun des 2, l'app l'informe que le contrast peu etre faible et lui propose une couleur bien visible (7:1), visible (4.5:1)
3 Barre du bas : A : total dans le bouton. Commentaire : et le plus dans le bouton doit etre mieux
4a Pastille et mot : Pas valider. Commentaire : fais mieux ça doit etre bien en ui/ux
4b Fiche intégrée : Valider. Commentaire : top ma question c'est quand j'ai par exemple des radiateurs qui demande plus de travail possible d'avoir un prix un peu plus cher sans changer le catalogue ?
5 Archiver au PC : A : icônes au survol. Commentaire : oui mais il faut faire mieux car la y'a trop d'espace ça fait vide la ou rien ne survol
7a Bandeau au PC : A : à la place du total. Commentaire : mais a faire mieux car la super mal intégré pas les bons espace et tout
7c Encore un mot du téléphone : Corrige
```

Lecture :
- 1 C, 2a, 4b, 7c : à coder.
- 2b : nouvelle consigne de Xavier (avertir, proposer une couleur à 7:1 et une à 4,5:1) ; maquette du 5e tour.
- 3 A validé ; « le plus dans le bouton doit être mieux » : maquette du 5e tour pour le bouton « + Ligne ».
- 4a refusé (« fais mieux ») ; 5 A et 7a A validés sur le principe, à mieux dessiner : maquette du 5e tour.
- 4b : question de Xavier (prix plus cher sur une ligne sans changer le catalogue), réponse à vérifier dans le code.

## Réponses de Xavier au 3e tour (MAQUETTE-points.html, 2026-11-19, texte collé tel quel)

```
1 Facture annulée : A : PDF annulé, Envoyer en secondaire. Commentaire : quel est l'interet de prevenir le client ? car sinon B suffit
2 Couleur de marque : A : foncer d'office jusqu'à 7:1. Commentaire : deja actuellement le changement de couleur ne fonctionne pas, du moins pas en live, 2 il faudrait que les couleur proposé satisfasse une bonne lisiblité si possible 7, mais 4.5 peut aller pour avoir des couleurs.
3 Barre du bas : (pas de réponse). Commentaire : le problème c'est que le bouton ajouter doit etre dispo a tout moment pour cliquer ou alors on transforme le micro en + ou alors on permet d'utiliser le micro pour modifier, ce qui engendre un cout a revoir
4 Lignes libres : A : lien « Ranger » sur la ligne. Commentaire : ranger me va pas de plus je sais pas si c'est assez grand pour cliquer, de plus il faut absolument faire le chemin de quand on clique ranger ou créer le produit ça doit ouvrir une fiche de création intégré qui permet de créer ou ranger le produit facilement.
5 Archiver au PC : Pas valider. Commentaire : il faut des icones c'est plus estetic et plus parlent
6a Confirmer l'annulation : Valider. Commentaire : un peu vide un mon gout mais ok
6b Annulation impossible : Valider. Commentaire : il faut un meilleur message trop de blanc ça fais très vide
7a Bandeau au PC : Pas valider. Commentaire : bof, c'est trop caché dans ta proposition, je comprends ton besoin trouve mieux
7b Mots au PC : Valider
8 Facturer : Valider. Commentaire : tu peux meme ajouter un bouton pour mettre la facutre en plus grand et reduire les éléments de gauche
9a Total TTC deux fois : Corriger
9b Bouton plus : Corriger
9c Police du PDF : Corriger
9d Logo de la connexion : Corriger
9e Pastille anthracite : Corriger
9f Voir en grand : Corriger
9g Erreurs de l'assistant : Corriger
9h Facture sur tablette debout : Corriger
```

Lecture :
- 1 : question en marge, reposée à Xavier avant de coder (rien de codé).
- 2 : constat vérifié le 2026-11-19 sur le compte `recette-pc` : l'enregistrement de la couleur marche (« Enregistré »), les vignettes des modèles sont des JPEG figés en bleu (`/img/modeles/<cle>.jpg`), elles ne suivent pas la couleur choisie. Maquette du 4e tour.
- 3, 4, 5, 7a : maquette du 4e tour.

## À trancher (recette complète du 2026-11-19, trois agents)

1. Facture annulée : son PDF (figé à l'émission) ne dit pas qu'elle est annulée et garde un QR payable ; « Envoyer » reste le bouton principal. Proposition : refaire le PDF à l'annulation avec un filigrane « ANNULÉE », sans section de paiement, « Envoyer » en secondaire.
2. Couleur de marque : l'application accepte 4,5:1 (`Marque::CONTRASTE_MIN`), le document du client descend à 5,13:1 (vert de la démo). Monter à 7:1 restreint les teintes possibles.
3. Barre du bas de l'offre : 212 px au téléphone (25 % à 390, 29 % à 360), 215 px sur tablette couchée. Conséquence des décisions D3, B4 et R1 A ; une piste : le total dans la barre des boutons.
4. Lignes libres : deux boutons pleine largeur sous chacune (« Créer le produit », « Rattacher ») ; 18 boutons sur l'offre 910. Piste : un seul « Ranger » qui ouvre le choix.
5. Archiver et supprimer une offre : invisibles à la souris (geste de glisser) ; au clavier, les deux premières tabulations tombent sur ces boutons cachés.
6. Annuler une facture : aucune couleur de danger, « Annuler seulement » isolé au milieu ; l'erreur 409 n'offre que « Retour à l'accueil ».
7. PC : bandeau centré qui couvre des champs ; mots du téléphone (« pincer pour agrandir », « touchez ») ; page « Plus » qui double le menu ; bouton de connexion à 700 px du formulaire à 1920.
8. Facturer : vignette de la facture illisible et coupée, filigrane « APERÇU » sur l'adresse du client.
9. Cosmétique, relevé non corrigé : « Total TTC » deux fois en bas de l'offre ; « + » sans libellé ; police DejaVu du PDF contre Inter à l'écran ; logo de la connexion hors de la colonne ; pastille anthracite invisible en sombre (1,5:1) ; « Voir en grand » détaché de sa vignette ; erreurs de l'assistant hors de vue après l'envoi ; facture en portrait avec le panneau sous deux pages de PDF.

## Suivi Origin

- Projet 44 offreGen, tâche 478 « Responsive du site de test : téléphone, tablette et PC ».
- Sous-tâches : 479 audit (fait le 2026-11-19), 480 proposition et décisions, 481 corrections, 482 revue et recette.

## Demande

Xavier, 2026-11-19 : « regarde si le site de test est bien responsive créer une nouvelle tache origin et et fais moi une proposition de tout ce qui ne fonctionne pas ça doit etre utilisable sur mobile, pc, et tablette ».

## Méthode de l'audit

- Site de test `https://easy-offres.xtradev.ch`, code `475ef57`. Compte créé pour l'audit : `audit-responsive@exemple.ch` (démonstration, catalogue de peintre, deux offres d'exemple), mot de passe dans `~/.offregen-audit-compte`. La démo et le compte de recette n'ont pas été touchés.
- Chromium 1243 piloté par Playwright, lecture seule (GET, connexion, « Plus tard » de l'assistant sur le compte d'audit). 30 écrans sur 12 tailles : téléphones 360, 375, 390 et 430 px, téléphone couché 844 x 390, tablettes 744, 820, 1180 et 1366 px (tactile), PC 900, 1024, 1366 et 1920 px (souris).
- Par écran : défilement horizontal, éléments hors de l'écran, cibles de moins de 44 px, champs sous 16 px, part de la hauteur prise par le bas collé, sonde de disposition de la skill `recette-visuelle`, capture regardée.
- Limites : pas de Safari ni de vrai appareil (WebKit absent du poste). Le zoom d'iOS sur les champs de moins de 16 px est un comportement connu de Safari, non reproduit ici. Contrastes non mesurés : la palette actuelle (sous 7:1, déjà relevé le 2026-11-18) sera remplacée par la marque Dicta.

## Ce qui tient

- Aucun défilement horizontal de la page sur les 30 écrans fermés, à toutes les tailles.
- Téléphone de 360 à 412 px : liste, offre, catalogue, clients, factures, réglages lisibles et utilisables au pouce.
- PC à partir de 1024 px : menu à gauche, colonne des offres et document à côté dès 1280 px, Facturer en deux volets.
- Tablette portrait et paysage : vue du téléphone élargie, colonne des offres en paysage, fenêtres au milieu.
- Aucune erreur JavaScript relevée.

## Constats, par gravité

### Graves (l'écran se trompe d'action ou devient inutilisable)

1. **Le micro central recouvre le bouton principal.** Mesuré : 72 x 13 px de « Générer l'offre », « Nouveau produit » et « Nouveau client » sont sous le micro, sur tous les téléphones, toutes les tablettes et la fenêtre PC étroite (sur tablette paysage, aussi 10 px du bouton « + »). Un toucher en bas au milieu du bouton ouvre la dictée. Cause : le rond de 72 px remonte de 24 px au-dessus des onglets (`.tabs .micro-central > span { top: -24px }`), la barre d'actions ne garde que 10 px de marge.
2. **Téléphone couché : il reste 104 px pour l'offre.** Sur 390 px de haut, l'en-tête prend 56 px et le bas collé 230 px (total, « + », « Générer l'offre », onglets, micro) : 59 % de l'écran. On voit une carte et demie, aucune ligne. Catalogue et clients : 37 %. Cause : la règle de la tablette (`min-width: 700px` et `pointer: coarse`) s'applique au téléphone couché, sans règle sur la hauteur.
3. **Grand téléphone (430 px, iPhone Plus et Pro Max) : la quantité recouvre la désignation.** « Protection des sols e » est coupé par le champ. C'est le défaut vu par Luigi (décision L1 du 2026-11-17), corrigé seulement sous 420 px (`@media (max-width: 419px)`). Les lignes débordent de 4 px aussi dans la fenêtre PC étroite (452 px de contenu pour 448).
4. **Facturer à 360 px : le champ « Échéance » sort de sa carte** une fois « Modifier » ouvert, et la page s'élargit de 3 px (défilement horizontal sur un Android courant). Cause probable : deux colonnes de grille sans `minmax(0, 1fr)`, le champ date garde sa largeur minimale. Même boîte coupée de 17 px sur PC 1024 (`div.duo`).

### Moyens (utilisable, mais pénible ou laid)

5. **Champs quantité et prix de l'offre en 14 px** (`.saisie`). Sur iPhone, Safari zoome la page à chaque toucher d'un champ sous 16 px : l'écran le plus utilisé saute à chaque correction. Non reproduit ici (Chromium), à vérifier sur un iPhone.
6. **Tablette : les formulaires prennent toute la largeur.** À 1180 px, le champ « Rue » fait 870 px de large, « Nom » 960 px : page vide et lecture difficile. Le PC limite déjà ces écrans à 640 px, la tablette n'a pas cette limite.
7. **« Mon entreprise » et l'assistant, au téléphone : « Téléphone » et « Numéro IDE » décalés de 21 px.** L'étiquette « à compléter » passe à la ligne sous « Numéro IDE » et pousse son champ plus bas que le voisin.
8. **Filtres du catalogue au téléphone : la rangée défile sans le montrer.** « Peinture intéri » est coupé net au bord, aucune ombre ni flèche : rien ne dit qu'il reste « Préparation » à droite (494 px de contenu pour 398 visibles).
9. **Désignation d'une ligne : cible de 29 px de haut** (lien `.nm`), alors que `CODING_RULES.md` demande 48 px pour toute cible. C'est le toucher qui ouvre la ligne entière.

### Faibles (choix de mise en page à confirmer)

10. **Fenêtre PC entre 700 et 1023 px** (écran partagé en deux, petit portable) : colonne de téléphone de 480 px au milieu, bandes grises, Catalogue et Réglages rangés sous « Plus », micro qui recouvre le bouton. Conforme à la décision du 2026-11-15 (« la fenêtre étroite d'un PC garde la colonne du téléphone »), mais c'est le cas courant d'un artisan au bureau avec le PDF ouvert à côté.
11. **PC 1920 px : la liste des offres s'arrête à 1272 px**, collée au menu, avec 650 px de gris à droite.

## Proposition

Chaque point demande « oui », « non » ou une autre consigne.

- **R1 (constat 1) :** réserver la place du micro. Barre d'actions : 24 px de marge en bas quand le micro suit, plus les 8 px d'écart de la règle D10. Coût : 24 px de hauteur en moins pour le contenu. Autre voie : rond de 64 px qui ne remonte que de 12 px.
- **R2 (constat 2) :** téléphone couché, mode compact (`max-height: 500px` en paysage) : onglets de 48 px sans libellé, micro dans la barre sans dépasser, total et boutons sur une seule rangée, en-tête de 44 px. Objectif : au moins 200 px de contenu sur 390. Autre voie : ne pas soutenir le paysage et afficher « Tourner le téléphone ».
- **R3 (constat 3) :** monter le seuil de la grille compacte des lignes de 419 à 480 px (couvre 430 px et la colonne de 480 px des fenêtres étroites). À vérifier sur la capture à 412 et 430 px.
- **R4 (constat 4) :** grilles à deux colonnes en `minmax(0, 1fr)`, champs en `min-width: 0` ; contrôle sur Facturer et sur tous les `.duo`.
- **R5 (constat 5) :** champs de saisie à 16 px partout. Les colonnes de 60 et 68 px doivent alors contenir « 1'176.00 » : à mesurer, sinon élargir le prix.
- **R6 (constat 6) :** tablette, écrans sans liste (fiches, formulaires, réglages) centrés à 720 px au plus, comme le PC.
- **R7 (constat 7) :** étiquette « à compléter » posée dans le champ (texte indicatif) ou sous lui, hauteur de l'étiquette réservée sur la rangée.
- **R8 (constat 8) :** filtres du catalogue : fondu sur le bord droit au téléphone ; retour à la ligne sur tablette et PC.
- **R9 (constat 9) :** toute la ligne (désignation et zone vide) devient la cible, 48 px de haut au moins.
- **R10 (constats 10 et 11) :** à décider par Xavier. (a) Fenêtre PC étroite : garder la colonne du téléphone, ou passer à un menu de gauche replié en icônes dès 700 px à la souris. (b) PC 1920 : centrer le contenu, ou laisser la liste à gauche.

Ordre conseillé : R1, R3, R4, R2, R5, puis le reste. R1 à R5 touchent seulement la mise en page : ils se font avant la marque Dicta sans conflit.

## Décisions

Premier tour, réponses de Xavier collées depuis `MAQUETTE-responsive.html` (2026-11-19), telles quelles :

```
R1 : A : garder 72 px, réserver 24 px
R3 : Valider
R9 : Valider
R4 : Valider
R2 : A : onglets à gauche. Commentaire : mets le micro doit etre facilement accessible depuis le pouce donc plutot au millieu ou en bas
R5 : Valider
R6 : Valider
R7 : Valider
R8 : Valider le fondu
R10a : B : onglets à gauche. Commentaire : avec les remarques que j'ai faites plus haut
R10b : A : laisser. Commentaire : t'as pas une autre solutoion quelque chose de mieux ?
```

Sur R3 et R9, Xavier, 2026-11-19 : « Pour R3 et R9, j'ai plusieurs points quand même. Le premier, j'aimerais que la poubelle elle soit affichée plus ou moins au milieu de la ligne, ou je sais pas, quelque chose où on se dit ah bah tiens, j'efface ma ligne, ou alors en haut de la ligne, je sais pas. Ça me convient dans la grande idée, sauf que les blocs ils sont trop gros. [...] On peut le faire moins haut, cet élément-là. On peut vraiment le rendre plus fin, plus agréable. [...] juste l'encadrer sans forcément mettre un fond, juste l'encadrer légèrement et puis il a une taille fixe qui est extensible [...] par défaut je mettrai sur 2 digits [...] Pareil pour le prix, on peut le prix lui en revanche le mettre là sur 3 digits. Il peut monter à 4, voire plus, mais que ça s'agrandit qu'à la fin [...] Le forfait, là, un forfait, ça doit être léger, on ne doit pas avoir un gros pavé gris [...] C'est trop lourd, ça. »

Conséquences tirées (deuxième tour) : R10 a B rend la fenêtre PC étroite large, donc la vue compacte des lignes s'arrête sous 700 px (et non plus jusqu'à 1023 px à la souris). R10 b : variante C proposée (trois volets dès 1800 px).

Deuxième tour, réponses de Xavier (2026-11-19), telles quelles :

```
Lignes : B : poubelle en face du nom
Micro : Au milieu
R10b : C : trois volets dès 1800 px
```

Avec, dans le même message : « Ajoute à tes correctifs les décisions boutons et contrastes du 19.11 : docs/plans/2026-11-19_contrastes-boutons/DECISIONS.md (B2 bord gris des boutons secondaires, B3 bouton d'envoi plein avec rond qui tourne, B4 plancher 48 px, barre de l'offre gardée à 64). Applique-les avec R1 à R10 dans app.css et app.js, recette par l'interface en clair et en sombre à 390 px. La partie charte de ce fichier va à la session Dicta. »

Ajouts de Claude, à confirmer par Xavier : textes indicatifs (`::placeholder`) en `--muted` (ils étaient à la couleur du navigateur, 3,2 à 4,6:1) ; plancher de 48 px étendu à toutes les cibles encore à 36, 40, 44 ou 46 px (B4 dit « plancher de toute cible », DECISIONS ne listait que la corbeille et les segments).

## Journal

- 2026-11-19 : tâche Origin 478 créée. Compte `audit-responsive@exemple.ch` créé sur le test (`bin/creer-compte --demo`, `bin/reinitialiser-demo peintre`). Parcours sur 12 tailles, 30 écrans, captures regardées. Constats 1 à 11 mesurés ; artefacts de sonde écartés (corbeille dans un formulaire en `display: contents`, chevauchement avec la barre collée, bas collé au milieu des captures pleine page). Captures de référence dans `captures/`. La géométrie du micro (72 px, `top: -24px`) est la même dans le travail de marque en cours : le constat 1 reste vrai après la marque.
- 2026-11-19, après-midi : la marque Dicta est commitée et déployée (`dabc3d9`). Captures « avant » refaites sur cette version, correctifs injectés dans le navigateur (`page.addStyleTag`, CSP contournée côté navigateur seulement), rien d'écrit sur le serveur. Mesures sur `dabc3d9` : micro sur le bouton 72 x 13 px (tablette 72 x 11) ; paysage 137 px de contenu sur 390 ; 430 px et PC 900 : 2 désignations recouvertes de 15 px ; Facturer 360 : Échéance dépasse de 33 px, page de 17 px ; Mon entreprise : décalage 22 px. Constat nouveau : R3 seul donne aux grands téléphones la cible de 29 px de R9, les deux vont ensemble. Filtres du catalogue : ils tiennent déjà sur tablette 1180. Maquette `MAQUETTE-responsive.html` (kit injecté, `check.js` vert à 1440 et 390 px, boutons de choix essayés). Scripts copiés dans `audit/` (`planche.js` relit le mot de passe du compte d'audit dans `~/.offregen-audit-compte`, il ne le contient pas).
- 2026-11-19, deuxième tour : lignes redessinées (cadre fin sans fond, cadre visible 34 px dans une zone de 48 px, largeur au contenu par `field-sizing`, colonnes partagées par `subgrid`, poubelle au milieu ou en face du nom), micro au milieu ou en bas de la colonne de gauche, trois volets à 1920 px. Maquette `MAQUETTE-responsive-2.html`, kit injecté, `check.js` vert, boutons essayés.
- 2026-11-19, incident : en tapant « 125.5 » dans une quantité de l'offre 2 du compte d'audit pour montrer l'élargissement du champ, la valeur a été enregistrée sur le test. Cause : `app.js` envoie au `change` par `fetch(..., { keepalive: true })`, que `context.route` de Playwright n'intercepte pas ; le blocage des POST ne servait à rien. Rétabli par l'interface (42, Entrée), vérifié après rechargement : quantités 1, 42, 42, 24, 26, 1, prix 180, 9, 28, 32, 12, 60, total CHF 3'106.80. Trace restante : « Enregistrée le 19.11.2026 11:17 » au lieu de « Créée le 17.11.2026 » ; `bin/reinitialiser-demo` l'effacerait, pas lancé sans l'accord de Xavier. Parade dans `audit/planche2.js` : retirer `data-envoi-change` avant toute frappe.
- 2026-11-19, code : `app.css` (R1 A dans le bloc téléphone, R3 et R9 sous 700 px avec la poubelle en face du nom, R4, R5 et les champs légers, R6, R7, R8, colonne d'onglets de R2 A et R10 a B avec le micro au milieu, R10 b C dans le bloc PC, B2, B3, B4) et `app.js` (B3 sur les envois de formulaire et les fiches en fenêtre, ouverture d'office des trois volets dès 1800 px sans garder ce choix, libellé « Fermer le document », largeur des champs en `ch` sans `field-sizing`). Les colonnes des lignes passent en sous-grille partagée. `.cache` ancré à gauche (il dépassait de 1 px dans le bas en ligne). Tests : 588 sur 588 (`VersionPcTest` impose un seul bloc téléphone et un seul bloc PC, respecté).
- 2026-11-19, recette locale (`php -S`, `offregen_dev`, compte de démonstration, `audit/recette.js`), en clair et en sombre à 390 px. Offre, feuille d'ajout et fiche client : 0 texte sous 7:1, 0 cible sous 48 px, bords des boutons secondaires et cadres des champs 3,83:1 en clair, 4,77:1 en sombre, 0 défilement de côté, 0 erreur JavaScript. B3 vu sur « Créer la facture » (envoi annulé dans la page) et sur la fiche produit en fenêtre (envoi retenu puis coupé) : couleur pleine, rond, mot d'attente, bouton rendu après. R1 : micro sur aucun bouton (390, 820). R2 : 257 px de contenu couché (137 avant), boutons à 64 px. R3 : 0 chevauchement à 360 et 430. R4 : Échéance dans sa carte. R6 : 720 px. R7 : 0 px. R8 : fondu au téléphone. R10 a : 820 px utiles à 900. R10 b : trois volets à 1920, fermés ils le restent après rechargement, rien d'office à 1366. Champs sans `field-sizing` (simulation de Safari) : 48 et 56 px au repos, 76 px à la frappe. Base locale inchangée : 1 facture avant et après, mêmes quantités et prix. Faux positifs écartés : le micro « sur » le bouton Terminé de la feuille d'ajout (caché sous la feuille, vu sur la capture) ; la croix de la fiche qui dépasse de 12 px de son en-tête (marge négative voulue, 10 px avant).
- Écrans non regardés après le plancher de 48 px : réglages de la marque (teintes de 48 px), import, relecture, facture émise, PC en dessous de 1920 hors offre.
- 2026-11-19, recette complète par trois agents Opus (téléphone 360 à 430 et couché, tablette 744 à 1366, PC 900 à 1920 ; clair et sombre ; comptes jetables `recette-tel`, `recette-tab`, `recette-pc` (administrateur) créés dans `offregen_dev`, compte de démonstration en lecture seule). Rapports : dossier temporaire de la session, `recette-tel`, `recette-tab`, `recette-pc`. Aucune page d'administration n'existe dans le code : l'administrateur voit les écrans d'un artisan, sans défaut propre. Compte de démonstration vérifié intact après coup (quantités, prix, 1 facture).
- 2026-11-19, défauts vérifiés sur capture puis corrigés et remesurés : `.duo` en sous-grille de rangées (l'alignement par le bas de R7 faisait descendre le voisin d'un champ en erreur de 25 à 44 px ; mesuré à 0 au téléphone, sur tablette et PC) ; texte indicatif en italique ; corbeille de la colonne du document (52 px) ; filet des lignes signalées passé dans la marge (colonnes alignées) ; « Qté » au-dessus de son champ sur une rangée ; tablette : tout écran sans lignes d'offre sur 720 px (document et aperçu PDF étaient à 1124 px) ; fenêtre du micro refusé et téléphone couché ; titres de la liste latérale sur deux lignes ; chevrons des volets ; liens de factures à 48 px ; compteur des clients masqué sans résultat ; bords des puces au gris de B2 ; bandeau au-dessus du micro ; PC : survol, relecture, bande blanche de 10 px ; document : totaux sur une ligne à 390, pied à 7,9:1 ; PDF de l'offre : conditions, signature et pied ensemble (la signature partait seule en page 2), vérifié dans le fichier ; double point des erreurs. Une facture créée après la correction : 2 pages (contenu et pied, puis paiement). Les factures déjà émises gardent leur PDF figé.
- 2026-11-19, 3e tour codé : 6a (fiche de la facture, conséquences, deux boutons rouges empilés), 6b (page « Annulation impossible » en 409 au lieu de l'erreur nue, lien « Annuler la facture » gardé sur l'acompte déduit ; `FactureControleur::emiseSeule`, `finaleQuiDeduit` ; test ajouté), 7b (mots du PC : aperçu PDF, Facturer, objet de l'offre), 8 (facture posée sur 820 px, échelle `--echelle` posée par app.js, « Agrandir la facture » réduit les réglages à 260 px, choix gardé dans `localStorage`), 9a (« Total TTC » une fois ; détail masqué sans TVA ni arrondi), 9b (« + Ligne », classe `btn ajout`), 9c (PDF en Inter et Archivo : TTF statiques tirés des WOFF2 du dépôt par fontTools, `DocumentPdf::policesCss`, dossier des polices ajouté à l'enclos de dompdf ; pas d'italique d'Inter ; U+202F absente du sous-ensemble latin), 9d (`picture` en bloc), 9e (liseré `--bord`), 9f (cadre sur la case du modèle), 9g (`premiereErreur` dans app.js), 9h (panneau au-dessus du PDF sur tablette debout). En passant : petit texte de la carte ouverte de la liste PC à 6,99:1, mis à l'encre.
- 2026-11-19, recette du 3e tour par l'interface (téléphone 390 clair et sombre, tablette 820 et 1180, PC 1366 et 1920) : sondes de contraste et de disposition à zéro sur 6a, 6b, 9a, 9b ; Facturer : seuls le filigrane décoratif et la couleur de marque du compte (point 2) sous 7:1. Corbeilles signalées « hors de leur formulaire » par la sonde : formulaire en `display: contents`, corbeille mesurée dans sa ligne (faux signal de la sonde). 657 tests, PHPStan sans erreur.
- 2026-11-19, écritures en base locale pendant ce tour : couleur de `recette-pc` passée en rouge par un clic de diagnostic puis remise en bleu par l'interface (vérifiée) ; « Plus tard » de l'assistant touché par erreur sur `recette-tab` (date « assistant fait » posée, compte jetable) ; une ligne de l'offre 175 supprimée puis restaurée deux fois (captures du point 7a, nombre de lignes vérifié).
- 2026-11-19, réponse à la question du 4b, vérifiée dans le code : un prix tapé sur une ligne ne change que la ligne (`DepotOffres::corrigerLigne`, table `ligne` seule). Piège relevé : `LigneCatalogueControleur::rattacher` remplace nom, unité et prix de la ligne par ceux du produit. Proposition au 5e tour : garder le prix de la ligne en note (mécanisme du prix dicté).
- 2026-11-19, incident pendant les captures du 5e tour : un `input.value = '9'` posé par script sans événement sur le prix de la ligne 771 (offre 171, `recette-tel`) est parti au `pagehide` (filet d'app.js, keepalive), malgré la coupure des envois. Prix rétabli à 45 par l'écran, vérifié après rechargement (total CHF 3'252.75, comme avant). Script corrigé (`data-envoi-change` retiré avant toute modification), mémoire `reference_playwright` complétée.
- 2026-11-19, 4e tour codé : 7c (« Cliquez sur Enregistrer » au PC) ; 2a (vignettes de « Ma marque » = vrai document de chaque modèle, `_vignette_modele.html.twig`, `ReglagesControleur::vignettes`, échelle par app.js `vignettesVivantes`, couleur suivie au toucher par `/reglages/marque.css?apercu=` sans écriture ; « Voir en grand » montre aussi le document vivant) ; 1 C (migration additive `Version20261119180001` : `facture.envoyee_le`, `facture_pdf.contenu_annule` ; partage réussi d'une facture émise noté par `POST /factures/{id}/envoyee` ; l'annulation fabrique un PDF « Annulée le … » sans section de paiement, celui de l'émission reste ; facture annulée : « Envoyer la facture annulée » secondaire si elle était partie, sinon « Télécharger le PDF »). Migration jouée sur `offregen_dev` et `offregen_test`. Tests ajoutés (vignettes, aperçu sans écriture, PDF annulé et boutons, isolation de la nouvelle route) : 659 tests, PHPStan sans erreur.
- 2026-11-19, recette par l'écran : 2a au téléphone et au PC (titre de la vignette bleu puis rouge au toucher, agrandi en rouge, sondes de disposition à zéro, couleur de `recette-tel` remise en bleu) ; 1 C sur `recette-tel` : facture 2026-005 annulée par « Annuler seulement » (écriture définitive, compte jetable), PDF d'une page vérifié à l'œil. Limites : au PC, « Envoyer » télécharge (pas de partage de fichier) et rien n'est noté ; le PDF annulé est rendu avec les réglages du jour d'annulation (une adresse ou une couleur changée depuis l'émission y paraîtrait).
- 2026-11-20 : la session des modèles a commité (7bd9348) sans mon travail des tours 1 à 4, resté non commité dans `~/offreGen`. Report dans une copie propre `~/offreGen-responsive` (tête de la branche) par fusion à trois voies, commit 825cf40 puis recalé ; 2a (vignettes vivantes) et 2b (avis de couleur) avaient été faits entre-temps par cette session (70eca7a, 112c063) à partir de mes fichiers : gardés les siens, retirés les miens. Désaccord tranché par la décision de Xavier au 5e tour : les six pastilles à 7:1 (son test adapté au pétrole #0C616D).
- 2026-11-20 : 5e tour codé (3 A, 4a B, 4b avec le prix gardé en note et la ligne à vérifier, 5, 7a fond jaune, pastilles à 7:1). Base de test privée `offregen_responsive_test` (droits copiés de `offregen_test`) : la suite partagée était percutée par une autre session. 925 tests, PHPStan sans erreur. Recette locale par l'écran (port 8001) : les six points tiennent, sondes de contraste à zéro ; écriture : produit « Ponçage des radiateurs anciens » créé en catégorie « Radiateurs » depuis la ligne 771 (`recette-tel`, `offregen_dev`).
- 2026-11-20 : poussé sur `chantier-voix-offre` (257d800, recalé sur 79abbd6) et déployé sur le test par `outils/deployer-test.sh` depuis la copie propre : sonde verte, `/sante` sur 257d800. Captures sur le test avec `audit-responsive` : une connexion refusée d'abord (ma lecture du fichier du mot de passe, ligne 1 au lieu de 2), puis deux envois bloqués par le navigateur (courriel mal extrait, rien n'est parti), puis connecté. Écritures par l'écran : une ligne de l'offre 17 supprimée puis restaurée (6 lignes avant et après) ; couleur orange puis bleu remis (vérifié). Maquette `MAQUETTE-test.html`.
