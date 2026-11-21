# Recette : douze modèles d'offre pour le bâtiment

Tout ce qui suit se teste à la main, en une passe, sur le site de test (`https://easy-offres.xtradev.ch`). Coche au fur et à mesure. Les scénarios 1 à 9 se font sur un téléphone ou un PC ; le 10 demande une imprimante et deux enveloppes.

## Préalables

- [ ] Code sur le site de test : la tête de `chantier-voix-offre` (commit noté dans `ETAT.md`), déployée quand tu le dis. Deux migrations partent avec : `Version20261120220001` (cinq colonnes facultatives, les phrases de Lettre) et celle d'une autre session, `Version20261120210001`. Contrôle : `GET /sante` annonce ce commit, `"migrations":"ok"`.
- [x] Migration `migrations/Version20261120120001.php` jouée par le déploiement (huit colonnes facultatives) : `GET /sante` rend `"migrations":"ok"`.
- [x] Le dossier `var/cache/pdf` du site de test accepte l'écriture : le PDF d'une offre de `demo-macon` porte Roboto et Archivo, aucune police de repli (contrôle du 2026-11-20).
- [ ] Recharger sans le cache du navigateur (Ctrl+F5, ou fermer l'onglet sur téléphone) : `app.css`, `app.js` et `document.css` sont servis sans numéro de version.
- [ ] Comptes : `demo-platrier@exemple.ch` et `demo-macon@exemple.ch` (mots de passe dans `~/.offregen-test-demo-platrier-compte` et `~/.offregen-test-demo-macon-compte`, WSL). Données inventées seulement. À la connexion, l'assistant du premier lancement s'ouvre : le finir ou le passer, sinon l'accueil y renvoie.
- [ ] Pour le scénario 10 : une imprimante, une enveloppe C5 à fenêtre à droite, une à fenêtre à gauche.

## Scénario 1 : douze modèles en quatre familles

| | |
|---|---|
| URL | `/reglages/marque` |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Ouvrir « Plus », « Réglages », « Ma marque ».
2. Faire défiler la partie « 3. Modèle ».

Attendu : quatre titres, « Classiques » (Sobre, Lettre, Atelier), « Couleur » (Bandeau, Moderne, Latéral, Enseigne), « Détaillés » (Technique, Récapitulatif, Chantier), « Simples » (Épuré, Marge). Chaque case montre le vrai document en petit, dans la couleur et avec le logo du compte, le logo à ses proportions ; le bas de Lettre, plus longue qu'une page, est coupé. Si le compte a pour métier « plâtrier », « Chantier » porte l'étiquette « Proposé pour votre métier » ; les comptes créés avant ce chantier gardent le modèle qu'ils avaient.

Si ça casse : `templates/_reglages_marque.html.twig`, `Marque::FAMILLES`, colonne `artisan.metier`, console du navigateur (fonctions `vignettesVivantes` et `papiers` d'`app.js`).

## Scénario 2 : voir un modèle en grand, le choisir, changer de couleur

| | |
|---|---|
| URL | `/reglages/marque` |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Toucher « Voir en grand » sous « Latéral ».
2. Passer d'un modèle à l'autre avec les flèches.
3. Toucher « Choisir ce modèle » sur « Récapitulatif ».
4. Toucher une autre pastille de couleur, puis rouvrir « Voir en grand » sur « Bandeau ».
5. Dans la vue en grand, glisser le doigt vers la gauche, puis vers la droite.

Attendu : le modèle s'ouvre en grand dans la page, entier en largeur, sans barre de défilement horizontale, dans la couleur du compte. Après « Choisir ce modèle », la case « Récapitulatif » est cochée. Au toucher d'une couleur, les douze vignettes changent de couleur aussitôt, et le modèle rouvert en grand est dans cette couleur (ta demande du 2026-11-20). Au 5, le doigt vers la gauche montre le modèle suivant, vers la droite le précédent ; un geste vertical fait défiler la page sans changer de modèle.

Si ça casse : `modelesEnGrand` et `vignettesVivantes` dans `public/js/app.js`, `ReglagesControleur::css` (feuille `?apercu=`), `public/css/document-ecran.css`.

## Scénario 2 bis : une couleur trop claire

| | |
|---|---|
| URL | `/reglages/marque` |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Toucher « Autre » et choisir le blanc, puis un jaune ou un orange clair.
2. Toucher « Bien visible ».
3. Toucher une des six couleurs proposées.

Attendu : au 1, un encadré jaune sous la couleur dit « Cette couleur se lit mal sur le papier blanc », avec son contraste, et propose « Bien visible » et « Visible », deux versions plus foncées de la même teinte (pour le blanc, deux gris). Au 2, la couleur prise s'enregistre, l'encadré disparaît, les vignettes la prennent. Au 3, pas d'encadré.

Si ça casse : `avisCouleur` dans `public/js/app.js`, `Marque::propositions`, `ReglagesControleur::avisCouleur`.

## Scénario 3 : le document à l'écran

| | |
|---|---|
| URL | une offre du compte, puis « Générer l'offre », et sur PC le bouton « Document à côté » |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Sur téléphone : ouvrir une offre, puis son document (`/offres/<numéro interne>/document`).
2. Sur PC (fenêtre de 1300 px ou plus) : ouvrir la même offre, cliquer « Document à côté », corriger une quantité.

Attendu : sur téléphone, la feuille entière tient dans la largeur de l'écran, sans défilement horizontal ; on zoome pour lire. Sur PC, le document à droite se met à jour après la correction, à la largeur de sa colonne. C'est la deuxième hypothèse fragile du plan : si la feuille réduite te paraît illisible sur ton téléphone, dis-le, l'écran montrera le PDF seul.

Si ça casse : `papiers()` dans `public/js/app.js`, `.paper > [data-feuille]` dans `public/css/document-ecran.css`.

## Scénario 4 : le PDF d'une offre

| | |
|---|---|
| URL | une offre, « Générer l'offre » |
| Compte | `demo-platrier@exemple.ch`, modèle « Latéral » |

Gestes :
1. Générer le PDF d'une offre courte.
2. Générer le PDF d'une offre d'une trentaine de lignes (ou en dicter une).

Attendu : texte en Roboto, titre en Archivo (pas de police à empattements de repli). En bas à droite, « Page 1 / 1 ». Sur l'offre longue : la colonne de couleur sur chaque page, les titres de colonnes répétés en haut de chaque page, « Page 1 / 3 », « Page 2 / 3 », « Page 3 / 3 », les totaux entiers sur une page, conditions et signatures ensemble.

Si ça casse : `src/Offre/DocumentPdf.php` (dossier des polices), `OffreControleur::rendrePdf`, `var/log` du site.

## Scénario 5 : le chantier, le début, la durée, ce qui n'est pas compris

| | |
|---|---|
| URL | une offre, son titre, puis `/offres/<numéro interne>/modifier` |
| Compte | `demo-platrier@exemple.ch`, modèle « Chantier » |

Gestes :
1. Remplir « Adresse du chantier », « Contact sur place », « Accès », « Début des travaux », « Durée », deux lignes dans « Non compris dans l'offre », « Date de la visite ». Enregistrer.
2. Générer le PDF.
3. Revenir à l'en-tête, vider tous ces champs, enregistrer, générer le PDF.
4. Taper 90 caractères dans « Durée », enregistrer.

Attendu : au 1, une ligne sous « Contact sur place », « Accès » et « Date de la visite » dit dans quel modèle le champ paraît. Au 2, la fiche « Chantier » à gauche de l'adresse porte les cinq rangées ; sous le total, une colonne « Non compris » à droite des conditions (le début et la durée sont dans la fiche ; dans les autres modèles, ils sont dans les conditions). Au 3, la fiche s'intitule « Références » (numéro, date, validité), plus aucun libellé « Chantier », « Non compris », « Début des travaux ». Au 4, le formulaire revient avec « 80 caractères au plus. » sous le champ, rien n'est enregistré.

Si ça casse : `src/Offre/Chantier.php`, `DepotOffres::modifierChantier`, colonnes `offre.chantier_*`.

## Scénario 6 : la personne de contact

| | |
|---|---|
| URL | `/reglages/entreprise` |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Remplir « Personne de contact », attendre l'enregistrement.
2. Générer le PDF d'une offre en modèle « Sobre ».

Attendu : une case « Votre contact » dans la ligne d'informations, et le même nom en gras au-dessus du trait de signature de l'entreprise.

Si ça casse : colonne `artisan.contact_nom`, `Reglages::depuisSaisie`.

## Scénario 7 : le lieu dicté devient l'adresse du chantier

| | |
|---|---|
| URL | l'accueil, le micro |
| Compte | `demo-platrier@exemple.ch` |

Gestes :
1. Dicter une offre en disant un nom et un lieu inventés (« pour Madame Exemple, rue du Test 4 à Porrentruy »).
2. Ouvrir l'en-tête de l'offre créée.

Attendu : « Adresse du chantier » porte le lieu dicté. C'est la troisième hypothèse fragile du plan : si, dans tes dictées, le lieu est le plus souvent l'adresse du client et pas celle du chantier, dis-le, je retire ce report.

Si ça casse : `DepotOffres::creer`.

## Scénario 8 : les douze modèles, un par un

| | |
|---|---|
| URL | `/reglages/marque`, puis le PDF d'une même offre |
| Compte | `demo-macon@exemple.ch`, chantier et contact remplis |

Gestes : pour chaque modèle, le choisir, générer le PDF, regarder.

| Modèle | À voir |
|---|---|
| Sobre | filet de couleur, titres de colonnes en couleur, total en anthracite |
| Lettre | adresse à gauche avec la ligne d'expéditeur, date en toutes lettres, « Madame, Monsieur, », conditions en phrases |
| Atelier | empattements, logo centré, double filet, « Offre » en italique |
| Bandeau | bande de couleur en haut, logo sur un cartouche blanc, bande au bas de chaque page avec le numéro de page |
| Moderne | grand « Offre », une ligne sur deux teintée, total sur fond teinté et texte anthracite |
| Latéral | colonne de couleur à gauche avec les références |
| Enseigne | nom de l'entreprise en grandes capitales, sans logo |
| Technique | cartouche encadré, positions 1.1, 1.2, montants en chasse fixe, total encadré de foncé sur ses quatre côtés |
| Récapitulatif | postes A, B, C et leurs totaux à côté du total, lettres centrées dans leur carré, puis le détail |
| Chantier | fiche du chantier à gauche de l'adresse, postes sur bandes teintées |
| Épuré | larges marges, petit trait de couleur |
| Marge | nom du poste et son total dans la marge de gauche |

Attendu, pour tous : l'adresse du client au même endroit, rien qui se chevauche, conditions et signatures au-dessus du pied. Dis-moi ceux qui ne te plaisent pas : c'est un jugement que mes tests ne portent pas.

Si ça casse : `templates/document/<modèle>.html.twig`, le bloc du modèle dans `public/css/document.css`.

## Scénario 8 bis : les phrases de Lettre

| | |
|---|---|
| URL | `/reglages/marque`, puis le document d'une offre |
| Compte | `demo-macon@exemple.ch` |

Gestes :
1. Cocher le modèle « Lettre » : le groupe « 4. Les phrases de la lettre » paraît.
2. Choisir « Selon le client », toucher « Tournure 3 » sous le début de l'offre, taper une fin à soi qui contient « Madame, Monsieur ».
3. Ouvrir le document d'une offre dont le client commence par « Madame » ou « Monsieur », puis d'une offre à une famille.
4. Créer une facture de cette offre, ouvrir son PDF.
5. Vider un champ, revenir : il montre la phrase d'avant.

Attendu : au 1, le groupe paraît sous les modèles, et disparaît si un autre modèle est coché. Au 3, « Madame, » ou « Monsieur, » en tête, « Madame, Monsieur, » pour la famille ; ta fin reprend la même formule ; après le début, « Visite du … » et « Chantier : … » quand l'offre les a. Au 4, le début et la fin de la facture, puis « Selon notre offre n° … du … ».

Si ça casse : `Compte\PhrasesLettre`, `templates/document/lettre.html.twig`, colonnes `artisan.lettre_*`, `tournuresLettre` dans `public/js/app.js`.

## Scénario 9 : les factures

| | |
|---|---|
| URL | une offre, « Facturer » |
| Compte | `demo-macon@exemple.ch`, IBAN rempli |

Gestes :
1. Modèle « Sobre » : créer une facture d'acompte (une ligne), ouvrir son PDF.
2. Créer la facture finale d'une offre de dix lignes ou plus, ouvrir son PDF.
3. Modèle « Lettre » : créer une facture d'acompte, ouvrir son PDF.

Attendu : au 1, une seule page, la section de paiement au bas de la page, pas de pied de page ; sous les mentions, « nom de l'entreprise · numéro IDE TVA ». Au 2, la facture puis la section de paiement seule sur la dernière page. Au 3, deux pages : la lettre, puis la section. Une facture émise avant ce chantier garde son ancien PDF.

Si ça casse : `FactureControleur::rendrePdf`, règles `.qr-bas` et `.qr-apart` de `public/css/document.css`.

## Scénario 10 : l'enveloppe à fenêtre

Gestes :
1. Imprimer une offre en « Sobre » et une en « Latéral », sans mise à l'échelle (100 %).
2. Les plier en deux et les glisser dans l'enveloppe C5 à fenêtre à droite.
3. Imprimer une offre en « Lettre », la glisser dans l'enveloppe à fenêtre à gauche.

Attendu : le nom et l'adresse du client entiers dans la fenêtre, rien d'autre que l'adresse (et, pour Lettre, la ligne d'expéditeur). L'adresse est posée à 48 mm du haut et à 118 mm du bord gauche (22 mm pour Lettre).

Si ça casse : dis-moi de combien de millimètres l'adresse est décalée, et dans quel sens ; la hauteur se règle dans `public/css/document.css` (commentaire « 182 px du haut »).

## Ce qui n'est PAS couvert

- Les options et les variantes : chantier à part, après celui-ci (tâche Origin 499).
- Marge : quand un poste passe d'une page à l'autre, son nom reste sur la première page.
- La couleur de l'artisan en texte tient un contraste de 4,5 sur le blanc (règle du 2026-11-12), pas 7 : à trancher par toi.
- L'avis d'un artisan sur les douze modèles (question 4 de la fiche d'essai).
- Un vrai iPhone et un vrai Android : je n'ai vu que Chromium, à 390 px et à 1280 px.
- Le verrou posé sur le premier rendu après une mise en ligne (deux PDF demandés à la même seconde) : écrit, pas essayé à deux processus.
- Le PDF d'une facture annulée : il reste celui de l'émission ; le PDF marqué « Annulée » est au chantier responsive, pas encore commité.
- La production : aucun accès de ma part, elle suit ton protocole de mise en ligne.

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Lint | vert | `vendor/bin/phpstan analyse` : `[OK] No errors` |
| Suite de tests | verte | dans la copie propre du commit, sur une base de test à part : `OK (912 tests, 8080 assertions)` |
| Sondes d'écran | vertes | copie propre du commit, disposition : 0 débordement, 0 chevauchement, 0 texte coupé sur cinq écrans ; cinq captures regardées |
| Revue | faite | `REVUE.md` : trois relecteurs, aucun bloquant, trois majeurs corrigés, dix mineurs corrigés |
