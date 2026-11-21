# Plan : douze modèles d'offre pour le bâtiment

Entrées : `MAQUETTE-modeles-v2.html` et les réponses de Xavier des 19 et 20 novembre 2026 (dans `ETAT.md`), `generateur.html` (source des douze feuilles), `essai-pdf/` (essai par dompdf, huit constats). Pas de `SPEC.md` : Xavier a demandé l'analyse et la maquette d'emblée, la maquette validée tient lieu d'intention. Exécution dans la session principale, lot par lot, test d'abord. Aucun commit sans demande.

Hors de ce plan : les options et les variantes (décision O6 : après les modèles, tâche Origin 499).

## Préalable : le document est tenu par ce chantier

Lu ainsi dans la réponse de Xavier du 2026-11-20 (« c'est bien le 1 ») : ce chantier tient le document PDF, et les montants passent sur une police à chiffres de même largeur. À confirmer avant le lot 0.

Le lot « 9c » d'une autre session a modifié sans commit `templates/_document.html.twig`, `public/css/document.css`, `src/Offre/DocumentPdf.php` et `src/Http/Conteneur.php` (Inter et Archivo dans le PDF, `public/fonts/inter-400.ttf`, `inter-700.ttf`, `archivo-700.ttf`). Le lot 0 part de ces fichiers une fois commités par leur session ; il garde le troisième paramètre de `DocumentPdf` (dossier des polices) et remplace Inter dans le document. L'écran de l'application garde Inter.

## Ce que les réponses imposent

| Réponse | Conséquence dans le code |
|---|---|
| Douze modèles, Métré et En bloc écartés | `Marque::MODELES` passe de six à douze clés : `sobre`, `lettre`, `atelier`, `bandeau`, `moderne`, `lateral`, `enseigne`, `technique`, `recap`, `chantier`, `epure`, `marge`. Toutes tiennent dans `artisan.modele VARCHAR(12)` : aucune migration pour le modèle. |
| C1, polices intégrées | Polices du document en TTF, une face déclarée par graisse employée, montants en Roboto, IBM Plex ou Source Serif 4 (lot 1). |
| C2, chantier, début, durée, contact | Colonnes sur `offre` et `artisan` (lot 3), champs à l'écran et ligne d'informations du document (lot 4). |
| C3, « non compris » | Colonne `offre.non_compris`, une entrée par ligne ; bloc à droite des conditions. |
| C4, deux signatures | Bloc de fin commun : l'entreprise avec le nom du contact, le client avec la place pour signer. |
| C5, pied avec IBAN et page | Pied commun, « Page n / N » posé après le rendu. |
| C6, adresse à la même place | Adresse à 446 px du bord gauche dans onze modèles (118 mm), à 83 px dans Lettre ; la hauteur se contrôle par test. |
| Sobre « total en noir/anthracite », Technique « le haut soit aussi foncé », Moderne et Épuré retouchés | Déjà dans `generateur.html` : le CSS des lots 2, 5 et 6 en est repris. |
| Enseigne : « on peut utiliser le nom officiel de l'entreprise et on regarde dans un premier temps si ça joue » | Pas de champ nouveau. Le corps du nom se réduit selon sa longueur (lot 6) ; test avec un nom de 40 caractères. |
| Modèle proposé selon le métier, quatre familles | `Marque::FAMILLES`, `Marque::pourMetier()`, écran « Ma marque » (lot 7). |

## Contrat technique

### Structures (colonnes ajoutées, migration additive, lot 3)

| Table | Colonne | Type | Sens |
|---|---|---|---|
| `offre` | `chantier_adresse` | `VARCHAR(255) NULL` | adresse du chantier ; à la création depuis une dictée, reçoit le lieu dicté |
| `offre` | `chantier_contact` | `VARCHAR(190) NULL` | contact sur place (nom, téléphone) ; montré par Chantier |
| `offre` | `chantier_acces` | `VARCHAR(255) NULL` | accès (clé, parcage) ; montré par Chantier |
| `offre` | `debut_travaux` | `VARCHAR(80) NULL` | texte libre : « semaine 3, 2027 » |
| `offre` | `duree_travaux` | `VARCHAR(80) NULL` | texte libre : « 6 jours ouvrables » |
| `offre` | `non_compris` | `VARCHAR(1000) NULL` | une entrée par ligne, dix lignes au plus |
| `offre` | `visite_le` | `DATE NULL` | date de la visite ; montrée par Lettre |
| `artisan` | `contact_nom` | `VARCHAR(120) NULL` | nom de la personne de contact, sur le document et sous la signature |

L'ancienne version du code n'écrit aucune de ces colonnes : toutes acceptent NULL. Un champ vide n'est pas montré sur le document : ni libellé seul, ni ligne vide.

### Endpoints

Aucune route nouvelle. Trois routes existantes reçoivent des champs, six routes d'aperçu naissent de la boucle sur `Marque::MODELES`.

| Route | Verbe | Droit | Entrée ajoutée | Sortie |
|---|---|---|---|---|
| `/offres/{id}/modifier` | POST | `Route::ARTISAN` | `chantier_adresse`, `chantier_contact`, `chantier_acces`, `debut_travaux`, `duree_travaux`, `non_compris`, `visite_le` | 303 vers l'offre ; 422 et le formulaire si une longueur est dépassée ou la date illisible ; 404 pour l'offre d'un autre artisan |
| `/reglages` | POST | `Route::ARTISAN` | `contact_nom` (rubrique « Mon entreprise ») ; `modele` accepte douze clés | inchangée |
| `/reglages/modeles/{clé}` | GET | `Route::ARTISAN` | six clés de plus (`config/routes.php`, boucle existante) | 200, le document d'exemple dans le modèle |
| `/offres/{id}/pdf`, `/factures/{id}/pdf` | GET | `Route::ARTISAN` | rien | PDF avec les polices du document et « Page n / N » |

### Flux

1. **Du réglage au PDF.** `POST /reglages` → `ReglagesControleur::sauver` (existe) → `DepotReglages::enregistrerMarque` (existe) → `artisan.modele`. `GET /offres/{id}/pdf` → `OffreControleur::pdf` (existe) → `donnees` → `vueDocument` (existe, reçoit les champs du chantier par `DepotOffres::parId`) → `htmlPdf` (existe) → `templates/document_pdf.html.twig` (existe, change) → `templates/_document.html.twig` (existe, devient un aiguillage) → `templates/document/<modèle>.html.twig` (à créer, douze fichiers) → `DocumentPdf::rendre` (existe, change : pose « Page n / N »).
2. **Éléments fixes.** Chaque gabarit de modèle remplit deux blocs Twig, `fixes` (pied, bande du bas, colonne) et `corps`. Dans le PDF, `templates/document/_base.html.twig` (à créer) sort `fixes` en enfant direct de `body`, dont la classe porte le modèle (`pdf m-lateral`) ; à l'écran, il les garde dans la feuille. Constat 5 de l'essai.
3. **À l'écran.** `document.html.twig`, `modele_apercu.html.twig`, `_vignette_modele.html.twig` et `facture_document.html.twig` (existent) incluent le même `_document.html.twig`. La feuille a une largeur fixe de 794 px et se réduit à la largeur de sa case par le mécanisme des vignettes (`vignettesVivantes()` de `public/js/app.js`, `.vignette > .paper` de `public/css/app.css`), étendu aux trois autres écrans.
4. **Champs du chantier.** `GET /offres/{id}/modifier` → `OffreControleur::entete` (existe) → `templates/offre_entete.html.twig` (existe, reçoit les champs) → `POST` → `OffreControleur::modifierEntete` (existe) → `Chantier::depuisSaisie` (à créer) → `DepotOffres::modifierChantier` (à créer).
5. **Lieu dicté.** `DepotOffres::creer` (existe, privée) écrit `chantier_adresse` avec le lieu dicté qu'elle reçoit déjà.
6. **Modèle selon le métier.** `Comptes::creer` (existe) → `Marque::pourMetier` (à créer) → `DepotReglages::enregistrerMarque` (existe). L'écran « Ma marque » marque ce modèle « Proposé pour votre métier ».
7. **Facture.** `FactureControleur::vueDocument` (existe) → même `_document.html.twig`, avec `f` et `montants`. Une facture émise garde son PDF (`facture_pdf`) : un changement de modèle ne la touche pas.

### Existant réutilisé

| Besoin | Où |
|---|---|
| Couleur assombrie, teintes, classes `c-*` | `src/Compte/Marque.php` (`principale`, `teinte`, `css`) |
| Groupes par pièce ou par catégorie, totaux | `OffreControleur::grouper`, `src/Offre/Totaux.php` |
| Logo en JPEG, intégré en `data:` | `OffreControleur::htmlPdf`, `src/Compte/TraitementLogo.php` |
| Section de paiement QR, bas de page ou page à part | `templates/_paiement.html.twig`, `FactureControleur::rendrePdf`, `DocumentPdf::pages` |
| Feuille réduite à sa case | `vignettesVivantes()` (`public/js/app.js:1267`), `.vignette > .paper` (`public/css/app.css:425`) |
| Saisie validée, longueurs | `src/Offre/Saisie.php`, `SaisieRefusee` |
| Jeu à deux artisans, navigateur de test | `tests/Outils/Jeu.php`, `tests/Outils/Navigateur.php` |
| Isolation par route | `tests/Http/IsolationRoutesTest.php` |
| Le CSS des douze feuilles et leurs données d'exemple | `docs/plans/2026-11-19_modeles-offre/generateur.html` |
| Les règles propres au PDF trouvées à l'essai | `docs/plans/2026-11-19_modeles-offre/exporter.js` (constantes `PDF` et `LONG`) |

## Signatures

```php
// src/Compte/Marque.php
/** Les douze modèles, dans l'ordre de l'écran. */
public const MODELES = ['sobre' => 'Sobre', 'lettre' => 'Lettre', 'atelier' => 'Atelier', 'bandeau' => 'Bandeau', 'moderne' => 'Moderne', 'lateral' => 'Latéral',
    'enseigne' => 'Enseigne', 'technique' => 'Technique', 'recap' => 'Récapitulatif', 'chantier' => 'Chantier', 'epure' => 'Épuré', 'marge' => 'Marge'];
/** @var array<string, array{nom: string, modeles: list<string>}> */
public const FAMILLES = ['classique' => ['nom' => 'Classiques', 'modeles' => ['sobre', 'lettre', 'atelier']],
    'couleur' => ['nom' => 'Couleur', 'modeles' => ['bandeau', 'moderne', 'lateral', 'enseigne']],
    'detail' => ['nom' => 'Détaillés', 'modeles' => ['technique', 'recap', 'chantier']],
    'simple' => ['nom' => 'Simples', 'modeles' => ['epure', 'marge']]];
/** peintre : marge ; platrier : chantier ; carreleur : sobre ; macon : bandeau ; sanitaire : technique ; menuisier : atelier ; sols : moderne ; paysagiste : epure ; autre et null : sobre. */
public static function pourMetier(?string $metier): string
/** Corps du nom dans Enseigne : 58 px jusqu'à 18 caractères, 46 jusqu'à 24, 36 jusqu'à 32, 28 au-delà. */
public static function corpsEnseigne(string $entreprise): int
/** teinte(0.9), teinte(0.82) et principale() en classes ; reçoit en plus .c-bord-gauche et .c-fond-fort. */
public function css(): string

// src/Offre/Chantier.php (à créer)
final readonly class Chantier
{
    /** @param list<string> $nonCompris */
    public function __construct(public string $adresse = '', public string $contact = '', public string $acces = '', public string $debut = '',
        public string $duree = '', public array $nonCompris = [], public ?\DateTimeImmutable $visite = null)
    /** @param array<string, string> $champs @return array{0: ?self, 1: array<string, string>} le chantier, ou les erreurs par champ */
    public static function depuisSaisie(array $champs): array
    /** @param array<string, mixed> $offre une ligne de DepotOffres::parId */
    public static function depuisOffre(array $offre): self
    public function estVide(): bool
}

// src/Donnees/DepotOffres.php
// SELECT_OFFRE reçoit o.chantier_adresse, o.chantier_contact, o.chantier_acces, o.debut_travaux, o.duree_travaux, o.non_compris, o.visite_le
public function modifierChantier(int $id, Chantier $c, \DateTimeImmutable $quand): bool

// src/Compte/Reglages.php : paramètre de plus, en dernier
public string $contactNom = '',

// src/Offre/DocumentPdf.php
/** Toutes les faces du document : une par graisse employée, sinon dompdf retombe sur DejaVu. */
public function policesCss(): string
/** Rend le PDF et pose « Page n / N » en bas à droite de chaque page. */
public function rendre(string $html): string
/** Position des blocs après le rendu, pour les tests : classe CSS => liste de [page, haut, bas] en points. */
public function blocs(string $html, array $classes): array
```

Gabarits : `templates/document/_base.html.twig` (blocs `fixes` et `corps`), `templates/document/_blocs.html.twig` (macros `lignes`, `totaux`, `conditions`, `signatures`, `pied`, `infos`), puis un fichier par modèle.

## Décisions fragiles

1. J'ai supposé que les familles complètes des six polices se trouvent en TTF statiques chez leurs éditeurs (Roboto, Archivo, Barlow Condensed, IBM Plex, Source Serif 4). Les fichiers de l'essai sont des sous-ensembles latins : il leur manque š, č, ć, đ, ž, ł, ř, ğ, ş (sonde du 2026-11-20), et Inter du lot « 9c » aussi. Si une famille n'existe qu'en police variable, le lot 1 fusionne ses sous-ensembles par un outil, et son test de couverture tranche.
2. J'ai supposé que la feuille à largeur fixe, réduite par le mécanisme des vignettes, remplace partout la mise en page étroite de `.paper`. Si la lecture sur téléphone en souffre à la recette, le lot 0 est à reprendre : l'écran de l'offre montrerait le PDF seul.
3. J'ai supposé que le lieu dicté est l'adresse du chantier. S'il est le plus souvent l'adresse du client, le point 5 du flux est à retirer et `chantier_adresse` se saisit à la main.

## Lots

Commandes : `vendor/bin/phpunit` et `vendor/bin/phpstan analyse` dans `~/offreGen` (WSL). Chaque lot finit par les deux, toutes deux vertes ; seule la vérification propre au lot est écrite ci-dessous.

### Lot 0 : traceur, Latéral de bout en bout

| | |
|---|---|
| Objectif | Un compte réglé sur « Latéral » obtient un PDF avec la colonne répétée sur chaque page, données d'exemple en dur dans la colonne, et le même document réduit à l'écran. |
| Fichiers | `src/Compte/Marque.php` (clé `lateral`), `templates/_document.html.twig` (aiguillage : les six modèles actuels restent dans `templates/document/_six.html.twig`, copie de l'existant), `templates/document/_base.html.twig`, `templates/document/lateral.html.twig`, `templates/document_pdf.html.twig` (classe du modèle sur `body`), `public/css/document.css`, `public/css/app.css` et `public/js/app.js` (feuille réduite sur `document.html.twig` et `modele_apercu.html.twig`), `src/Offre/DocumentPdf.php` (`blocs`) |
| Test | `tests/Offre/GabaritsPdfTest.php` (à créer) : `testLateralRepeteSaColonneSurChaquePage` (offre de 40 lignes : `blocs()` rend un bloc `doc-cote` par page) ; `testLesSixModelesActuelsNeChangentPas` (le HTML de `sobre` contient toujours `class="doc m-sobre"`). `tests/Http/MarqueEcranTest.php` : `testLateralSeChoisitEtSeVoitEnGrand`. |
| Vérification | `vendor/bin/phpunit --filter 'GabaritsPdfTest|MarqueEcranTest'` : OK. PDF de l'offre de 40 lignes ouvert, pages 1 à 3 regardées (colonne présente). |
| Dépend de | le préalable |

### Lot 1 : polices du document

| | |
|---|---|
| Objectif | Le PDF sort dans les polices de la maquette, avec les caractères des noms d'ici (Kovačević, Çelik, Dvořák), et des montants alignés. |
| Fichiers | `public/fonts/` (TTF statiques complets : Roboto 400, 500, 700, 400 italique ; Archivo 600, 700, 800 ; Barlow Condensed 500, 600, 700 ; IBM Plex Sans 400, 500, 600, 700 ; IBM Plex Mono 400, 500, 600 ; Source Serif 4 400, 600, 700, 400 et 600 italiques ; une licence OFL par famille), `public/fonts/SOURCES.md` (adresse, version et empreinte SHA-256 de chaque fichier), `src/Offre/DocumentPdf.php` (`policesCss`), `PROJECT_ROADMAP.md` (une ligne de décision : polices du document, leur source) |
| Test | `tests/Offre/PolicesDocumentTest.php` (à créer) : `testChaqueFaceDeclareeAUnFichier` ; `testChaqueGraisseEmployeeParLaFeuilleEstDeclaree` (lit les `font-weight` et les raccourcis `font` de `document.css`, par famille) ; `testLesPolicesCouvrentLesNomsDIci` (é œ ü ß ã ë š č ć đ ž ł ř ğ ş ı ’ − × ² € dans chaque fichier) ; `testLesChiffresOntLaMemeLargeur` (Roboto, IBM Plex Sans, IBM Plex Mono, Source Serif 4 : « 1111 » et « 0000 » de même largeur par `FontMetrics::getTextWidth`). |
| Vérification | `vendor/bin/phpunit --filter PolicesDocumentTest` : OK. Le PDF de Latéral ne cite plus Inter : `grep -c Inter` sur le PDF rend 0. |
| Dépend de | 0 |

### Lot 2 : socle commun et Sobre

| | |
|---|---|
| Objectif | Sobre sort comme sur la maquette : ligne d'informations, postes, totaux en anthracite, conditions, deux signatures, pied avec IBAN et « Page n / N » ; sur 40 lignes, l'en-tête du tableau se répète et les totaux restent entiers. |
| Fichiers | `templates/document/_blocs.html.twig`, `templates/document/sobre.html.twig`, `templates/document/_six.html.twig` (Sobre en sort), `public/css/document.css` (socle `.doc` repris de `generateur.html` ; règles `.pdf` : interligne 1.22, hauteurs hors marges intérieures, `page-break-inside: avoid` sur `.doc-totaux` et `.doc-fin`), `src/Offre/DocumentPdf.php` (`rendre` pose la pagination), `tests/Http/MarqueEcranTest.php` et `tests/Http/EcransTest.php` (attentes sur le HTML de Sobre) |
| Test | `tests/Offre/GabaritsPdfTest.php` : `testSobreTientSurUnePage` (offre de 8 lignes : 1 page) ; `testLeBlocDeFinResteAuDessusDuPied` (`blocs()` : bas de `doc-fin` au moins 6 pt au-dessus du haut de `doc-pied`) ; `testLesTotauxNeSeCoupentPas` (40 lignes : `doc-totaux` sur une seule page) ; `testChaquePagePorteSonNumero` (3 pages : « Page 2 / 3 » dans le texte du PDF non compressé). |
| Vérification | `vendor/bin/phpunit --filter GabaritsPdfTest` : OK. PDF de 8 lignes et de 40 lignes regardés. |
| Dépend de | 1 |

### Lot 3 : migration

| | |
|---|---|
| Objectif | Les huit colonnes du contrat existent, en dev et en test. |
| Fichiers | `migrations/Version20261121000001.php` |
| Test | `tests/Donnees/ColonnesChantierTest.php` (à créer, sur le modèle de `tests/Donnees/ColonnesMetiersTest.php`) : `testLesColonnesDuChantierExistentEtAcceptentNull`. |
| Vérification | `vendor/bin/doctrine-migrations migrations:migrate --no-interaction` puis `migrations:up-to-date` : « Up-to-date! ». `SHOW COLUMNS FROM offre LIKE 'chantier_%'` : 3 lignes. Descente puis remontée jouées une fois en dev. |
| Dépend de | rien |

### Lot 4 : chantier, début, durée, non compris, contact

| | |
|---|---|
| Objectif | L'artisan remplit le chantier sur l'en-tête de l'offre et son nom de contact dans « Mon entreprise » ; le document les montre, et se passe des champs vides. |
| Fichiers | `src/Offre/Chantier.php`, `src/Donnees/DepotOffres.php`, `src/Http/Controleur/OffreControleur.php` (`modifierEntete`, `vueDocument`), `templates/offre_entete.html.twig`, `src/Compte/Reglages.php`, `src/Donnees/DepotReglages.php`, `src/Http/Controleur/ReglagesControleur.php`, `templates/_reglages_entreprise.html.twig`, `templates/document/_blocs.html.twig` |
| Test | `tests/Offre/ChantierTest.php` (à créer) : longueurs refusées, onze lignes de « non compris » refusées, date illisible refusée, lignes vides retirées. `tests/Http/EnTeteChantierTest.php` (à créer) : `testLeChantierSaisiParaitSurLeDocument`, `testUnChampVideNeLaisseAucunLibelle`, `testLeLieuDicteDevientLAdresseDuChantier`, `testLeContactSigneLOffre`. `tests/Http/IsolationRoutesTest.php` : l'en-tête de l'offre de B refusé à A avec les champs nouveaux. |
| Vérification | `vendor/bin/phpunit --filter 'ChantierTest|EnTeteChantierTest|IsolationRoutesTest'` : OK. Parcours à la souris sur le compte de démo : en-tête rempli, document relu. |
| Dépend de | 2, 3 |

### Lot 5 : Bandeau, Atelier, Technique, Moderne, Épuré

| | |
|---|---|
| Objectif | Les cinq modèles refaits sortent comme sur la maquette v2. |
| Fichiers | `templates/document/{bandeau,atelier,technique,moderne,epure}.html.twig`, `templates/document/_six.html.twig` (supprimé à la fin du lot), `public/css/document.css` (les six blocs `.m-*` actuels remplacés), `src/Compte/Marque.php` (`css` : classes en plus) |
| Test | `tests/Offre/GabaritsPdfTest.php`, un jeu de données par modèle (`#[DataProvider('modeles')]`) : `testTientSurUnePage`, `testLeBlocDeFinResteAuDessusDuPied`, `testLAdresseEstDansLaFenetre` (`blocs()` : haut de `doc-adresse` entre 125 et 170 pt, page 1), `testQuaranteLignesSansBlocCoupe`. Épuré : `testEpureGardeLesPieces` (offre par pièce : chaque nom de pièce dans le HTML). |
| Vérification | `vendor/bin/phpunit --filter GabaritsPdfTest` : OK. Les cinq PDF regardés, à côté des images de `MAQUETTE-modeles-v2-img/`. |
| Dépend de | 4 |

### Lot 6 : Lettre, Enseigne, Récapitulatif, Chantier, Marge, et Latéral complété

| | |
|---|---|
| Objectif | Les cinq modèles nouveaux sortent comme sur la maquette v2 ; Latéral quitte ses données en dur. |
| Fichiers | `templates/document/{lettre,enseigne,recap,chantier,marge,lateral}.html.twig`, `public/css/document.css`, `src/Compte/Marque.php` (cinq clés, `corpsEnseigne`), `config/routes.php` (rien à écrire : la boucle sur `MODELES` crée les routes) |
| Test | `tests/Offre/GabaritsPdfTest.php`, mêmes quatre tests par modèle. En plus : `testEnseigneReduitUnNomLong` (40 caractères : le nom tient sur une ligne, `blocs()` : hauteur de `doc-enseigne` sous 50 pt) ; `testMargeGardeSesColonnesEnPageDeux` (40 lignes : aucune cellule fusionnée dans le HTML) ; `testLettreSansVisiteNeCiteAucuneDate` ; `testRecapitulatifListeChaquePosteAvantLeDetail` ; `testLeLogoLargeTientDansLaColonneDeLateral` (logo de 600 x 120 : largeur de l'image sous 170 px dans le HTML). `tests/Compte/MarqueTest.php` : `testCorpsEnseigne`. |
| Vérification | `vendor/bin/phpunit --filter 'GabaritsPdfTest|MarqueTest'` : OK. Les six PDF regardés, 8 lignes et 40 lignes. |
| Dépend de | 5 |

### Lot 7 : écran « Ma marque »

| | |
|---|---|
| Objectif | L'artisan choisit parmi douze modèles rangés en quatre familles ; le modèle de son métier est marqué « Proposé pour votre métier » et réglé d'office à la création du compte. |
| Fichiers | `src/Compte/Marque.php` (`FAMILLES`, `pourMetier`), `templates/_reglages_marque.html.twig`, `src/Http/Controleur/ReglagesControleur.php` (vignettes par famille, métier du compte), `src/Compte/Comptes.php` (`creer`), `public/css/app.css`, `public/img/modeles/*.jpg` et `public/img/modeles/grand/*.jpg` (douze de chaque, capturés sur l'aperçu du compte de démo) |
| Test | `tests/Compte/MarqueTest.php` : `testChaqueModeleAUneFamilleEtUneSeule`, `testPourMetier` (les neuf métiers et null). `tests/Http/MarqueEcranTest.php` : `testLesDouzeModelesSontRangesEnQuatreFamilles`, `testLeModeleDuMetierEstPropose`, `testVoirEnGrandDesigneUneGrandeImageParModele` (existe : passe pour douze clés). `tests/Compte/CreationCompteTest.php` : `testUnCompteDePlatrierOuvreSurChantier`. |
| Vérification | `vendor/bin/phpunit --filter 'MarqueTest|MarqueEcranTest|CreationCompteTest'` : OK. Écran regardé à 390 px et à 1280 px, clair et sombre. |
| Dépend de | 6 |

### Lot 8 : les douze en facture

| | |
|---|---|
| Objectif | Une facture sort dans chacun des douze modèles, avec la section de paiement en bas de page ou sur une page à part, et la mention d'une facture annulée. |
| Fichiers | `templates/document/*.html.twig` (titre, références et conditions de la facture), `templates/document/_blocs.html.twig`, `public/css/document.css` (éléments fixes au-dessus de la section de paiement), `src/Http/Controleur/FactureControleur.php` (si la place de la section change) |
| Test | `tests/Http/FactureQrTest.php` et `tests/Http/FacturesEcranTest.php`, par modèle : `testLaSectionDePaiementTientEnBasDeLaPage` (`blocs()` : aucun bloc `doc-*` sous 545 pt en page 1 d'une facture d'une page), `testUneFactureLongueMetLaSectionAPart`, `testLaFactureAnnuleeLeDit`. |
| Vérification | `vendor/bin/phpunit --filter 'FactureQrTest|FacturesEcranTest'` : OK. Douze factures regardées, une courte et une longue par modèle. |
| Dépend de | 6 |

### Lot 9 : cas limites

| | |
|---|---|
| Objectif | Les douze tiennent avec un vrai logo, une couleur claire, un nom long, une offre par pièce et une offre sans aucun champ facultatif. |
| Fichiers | `tests/Offre/GabaritsPdfTest.php`, corrections dans `public/css/document.css` et les gabarits |
| Test | `GabaritsPdfTest` par modèle : `testAvecUnLogoHaut` (240 x 240), `testAvecUnLogoLarge` (600 x 120), `testSansLogo`, `testCouleurClaire` (`#F5E663` : aucun texte dans la couleur brute), `testSansChampFacultatif` (aucun libellé vide), `testOffreParPiece`. |
| Vérification | `vendor/bin/phpunit` : OK sur toute la suite. `vendor/bin/phpstan analyse` : « No errors ». |
| Dépend de | 7, 8 |

## Ce que les tests ne couvrent pas (recette de Xavier)

- L'enveloppe à fenêtre : une impression de chaque modèle, pliée, sur le site de test.
- L'aspect : les douze PDF relus par un artisan (question 4 de la fiche d'essai).
- La lecture de la feuille réduite sur un vrai téléphone.

## Revue du plan

- Chaque réponse de Xavier a son lot : C1 (lot 1), C2 et C3 (lots 3 et 4), C4 et C5 (lot 2), C6 (lots 5 et 6), les douze modèles (lots 2, 5, 6), l'écran (lot 7), les factures (lot 8).
- Les noms concordent d'un lot à l'autre : `blocs()`, `Chantier`, `modifierChantier`, `pourMetier`, `corpsEnseigne`, classes `doc-cote`, `doc-fin`, `doc-pied`, `doc-totaux`, `doc-adresse`, `doc-enseigne`.
- Chemins vérifiés le 2026-11-20 : routes de l'en-tête (`config/routes.php`, lignes 87 et 88), gabarits des réglages, tests cités.

## Avancement et écarts au plan

Les dix lots sont faits le 2026-11-20, dans l'ordre, test d'abord. Rien n'est commité. Le détail et les preuves sont dans `ETAT.md`.

| Lot | État | Écart au plan |
|---|---|---|
| 0 | fait | Latéral part des vraies données. Les six modèles d'avant ont vécu jusqu'au lot 5 dans `document/_six.html.twig`, classes `anc-*`. La réduction à l'écran se fait dans le papier (`papiers()` d'`app.js`, `document-ecran.css`), pas par le mécanisme des vignettes. |
| 1 | fait | En plus : 46 WOFF2 par sous-ensemble pour l'écran. Le test des graisses lit le PDF rendu (aucune police de repli), pas la feuille de style. dompdf range ses polices sous `var/cache/pdf`. |
| 2 | fait | « Page n / N » par le compteur CSS de dompdf et un second rendu, pas par `page_text`. La géométrie de la section de paiement des factures est venue ici (Sobre est le modèle des tests de facture). Interlignes du PDF par famille de police. |
| 3 | fait | Migration `Version20261120120001`. |
| 4 | fait | Aucun. |
| 5 | fait | L'adresse du client à 182 px du haut dans tous les modèles (C6), à 6 points près ; le plan tolérait 125 à 170 points. |
| 6 | fait | Lettre est jugée sur six lignes par page. Le logo large dans Latéral se teste au lot 9. |
| 7 | fait | Un compte de métier « autre » n'a pas de modèle marqué. Images de repli tirées du PDF. |
| 8 | fait | Tests dans `tests/Offre/GabaritsFactureTest.php`. Une facture d'une page n'a pas de pied : la section de paiement en prend la place. Lettre porte toujours la section sur une page à part. |
| 9 | fait | En plus : nom d'entreprise de 42 caractères. |

Signatures : `Marque::css()` n'a pas reçu `.c-fond-fort` (aucun modèle n'en a eu besoin) ; elle a reçu `.c-bord-gauche` et `.c-teinte.c-texte`. `DocumentPdf` a `rendreEtCompter()` en plus.

Décisions fragiles : la première est levée (les six familles existent en TTF statiques complets chez leurs éditeurs). Les deux autres attendent la recette : la feuille réduite sur un vrai téléphone, et le lieu dicté pris pour l'adresse du chantier.
