# PLAN : options et variantes dans l'offre

Entrées : `SPEC.md` (validée le 2026-11-22), `MAQUETTE-options-variantes.html` (E1 à E5 validés). Code lu à la tête
`06e0690` de `chantier-voix-offre`.

## Contrat technique

### Données (table `ligne`, migration à créer)

| Colonne | Type | Sens |
|---|---|---|
| `nature` | `VARCHAR(10) NOT NULL DEFAULT 'ligne'` | `ligne`, `option` ou `variante` |
| `remplace_id` | `INT UNSIGNED NULL` | pour une variante : l'id de la ligne qu'elle remplace (même offre, même artisan) |
| `retenue` | `TINYINT(1) NOT NULL DEFAULT 0` | option ou variante retenue par le client (case cochée) |

Additive : une ligne existante vaut `ligne`, non retenue. `facture_ligne` ne change pas : une facture ne reçoit que des
lignes du total, copiées en lignes ordinaires.

### Règle unique du total (`src/Offre/Options.php`, à créer)

Une ligne est **dans le total** si :
- `nature = ligne` et aucune variante active **retenue** ne la remplace ;
- `nature = option` et `retenue = 1` ;
- `nature = variante`, `retenue = 1`, et sa ligne de base est active.

Une variante dont la ligne de base est supprimée (ou absente de la liste) **se comporte comme une option** (O3). Rien
n'est réécrit en base : « Annuler » sur la ligne de base remet ses variantes à leur place.

« Total avec toutes les options » (point 4) : le total plus toutes les options non retenues, variantes exclues.

### Où passe la règle

`Totaux::calculer` et `Totaux::parTaux` appliquent `Options::masque()` eux-mêmes : tout appelant d'aujourd'hui (écran,
liste des offres, fiche client, rabais, facturer, acomptes, montants) compte juste sans être touché. Une ligne hors du
total a un montant 0 dans `lignes` (même indice qu'en entrée), comme une ligne offerte. `calculer` rend en plus
`avec_options` : `null` s'il n'y a aucune option non retenue, sinon `{rabais, ht, tva, arrondi, ttc}` calculés avec
elles.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/offres/{id}/lignes/{ligne}` (existante, `ligne_corriger`) | POST | `Route::ARTISAN` | + `nature` (`ligne`, `option`, `variante`), + `remplace` (id) | 303 vers l'offre ; 422 si la variante vise une ligne invalide |
| `/offres/{id}/lignes` (existante, `ligne_ajouter`) | POST | `Route::ARTISAN` | + `nature`, + `remplace` ; quantité vide pour une variante : celle de la base | 303 |
| `/offres/{id}/ajouter` (existante) | GET | `Route::ARTISAN` | + `?variante={ligne}` : la feuille s'ouvre réglée sur « Variante » de cette ligne | 200 |
| `/offres/{id}/lignes/{ligne}/retenue` (à créer, `ligne_retenue`) | POST | `Route::ARTISAN` | `retenue` (0 ou 1), `retour` (`offre` ou `facturer`) | 303 vers l'offre, ou vers `/offres/{id}/facturer` |

Les routes de facture (`/factures/{id}/lignes…`) refusent `nature` et `remplace` (ignorés) : un brouillon de facture n'a
pas d'options.

### Structures échangées

- `Ligne` (`DepotOffres`, type phpstan) : + `nature: string`, `remplace_id: int|null`, `retenue: int`.
- `LigneExtraite` : + `nature: string` (défaut `ligne`), `remplace: int|null` (rang 0-based dans la liste extraite).
- Outil du second appel `classer_lignes` : `{lignes: list<{ligne: int (1-based), nature: "option"|"variante", remplace: int|null}>}` ; une ligne absente de la liste reste `ligne`.
- Vue de l'écran (`OffreControleur::grouper`) : chaque rangée d'un groupe porte en plus `variantes: list<rangée>` et
  `remplacee: bool` ; les options sortent de `grouper` et passent dans `options: list<rangée>` de `vueDocument`.

### Flux, de bout en bout

1. **Dictée** : `Audio\Traiteur::traiter` (ligne 171) appelle `Extracteur::extraire` ; `ExtracteurApi::extraire`
   rend les lignes (appel principal inchangé), puis `rangerParPiece`, puis **à créer** `classerOptions` (second appel,
   seulement si la transcription contient un mot déclencheur). `valider` et `validerOptions` produisent des
   `LigneExtraite` avec `nature` et `remplace`.
2. **Création** : `DepotOffres::creer` insère `nature`, puis, dans la même transaction, pose `remplace_id` d'après le
   rang (deuxième passe, ids connus). Une variante sans base valable devient `option`, `a_verifier = 1`.
3. **Lecture** : `DepotOffres::COLONNES_LIGNE` lit les trois colonnes ; `lignes()`, `ligne()`, `lignesDe()` les rendent.
4. **Calcul** : `Totaux::calculer` (règle unique ci-dessus).
5. **Écran** : `OffreControleur::vueDocument` → `grouper` (variantes rattachées, options à part) → `_lignes.html.twig`
   (variantes sous leur ligne, case « Retenue par le client ») et `offre.html.twig` (groupe « Options » sous `.totals`,
   « Total avec toutes les options »).
6. **Gestes** : `ligne.html.twig` et `ajout.html.twig` (choix « Dans le total · Option · Variante », « Elle remplace »,
   « + Variante ») → `LigneControleur::corriger` / `ajouter` → `DepotOffres::changerNature`. Case cochée →
   `LigneControleur::retenir` → `DepotOffres::retenir` → `marquerModifiee` (le bandeau « Renvoyer » suit, déjà en service).
7. **JS** : `public/js/app.js`, `corrections()` : une case à cocher part quand `checked !== defaultChecked` ; le
   remplacement après réponse couvre aussi `[data-options]`.
8. **Document** : `_blocs.html.twig` : `lignes` et `lignes_marge` montrent les variantes non retenues sous la ligne du
   total (case vide, italique, écart) et la variante retenue à la place de sa base ; macro `options` (à créer), appelée
   par `totaux` pour une offre seulement : bloc « Options », case vide, montant avant rabais (E4), « Total avec toutes
   les options ». Les douze gabarits n'appellent que ces macros.
9. **Facturer** : `FactureControleur::lireFacturer` copie `Options::lignesDuTotal()` (variante retenue au rang de sa
   base) ; `facturer.html.twig` reprend les cases (E1), qui postent sur `ligne_retenue` avec `retour=facturer`.

### Existant réutilisé (vérifié)

`src/Offre/Totaux.php` (`calculer`, `parTaux`, `brut`), `src/Offre/Offerte.php` (même logique d'exclusion du total),
`src/Donnees/DepotOffres.php` (`COLONNES_LIGNE` l. 38, `creer` l. 70, `ajouterLigne` l. 460, `supprimerLigne` l. 443),
`src/Http/Controleur/LigneControleur.php` (`corriger` l. 64, `ajouter` l. 180, `pageAjout` l. 166),
`src/Http/Controleur/OffreControleur.php` (`vueDocument` l. 221, `grouper` l. 259),
`src/Http/Controleur/FactureControleur.php` (`lireFacturer` l. 248, copie de la finale l. 327),
`src/Offre/ExtracteurApi.php` (`extraire`, `rangerParPiece`, `valider`), `public/js/app.js` (`corrections` l. 11),
`templates/_lignes.html.twig`, `templates/offre.html.twig` (`.totals` l. 50), `templates/document/_blocs.html.twig`
(`lignes` l. 54, `lignes_marge` l. 97, `totaux` l. 124), `config/routes.php` (l. 149 à 154),
`tests/Outils/OffreDEssai.php`, `src/Catalogue/CatalogueFictif.php` (`produits`, `offres`).

## Signatures

```php
// src/Offre/Options.php (à créer)
final class Options {
    public const LIGNE = 'ligne'; public const OPTION = 'option'; public const VARIANTE = 'variante';
    public const NATURES = [self::LIGNE, self::OPTION, self::VARIANTE];
    /** @param list<array{id?: int, nature?: string, remplace_id?: int|null, retenue?: int|bool}> $lignes @return list<bool> */
    public static function masque(array $lignes): array;                // dans le total, même ordre
    /** @return list<bool> */
    public static function masqueAvecOptions(array $lignes): array;     // + options non retenues
    public static function aDesOptionsLibres(array $lignes): bool;
    /** @template T of array @param list<T> $lignes @return list<T> */
    public static function lignesDuTotal(array $lignes): array;         // variante retenue au rang de sa base
    /** @return 'ligne'|'option'|'variante' */
    public static function natureEffective(array $l, array $lignes): string; // variante orpheline : option
}

// src/Offre/Totaux.php : signature inchangée, retour + 'avec_options' => array{rabais: int, ht: int, tva: int, arrondi: int, ttc: int}|null

// src/Donnees/DepotOffres.php
public function changerNature(int $offreId, int $ligneId, string $nature, ?int $remplaceId): bool; // faux : ligne, base ou nature invalide
public function retenir(int $offreId, int $ligneId, bool $retenue): bool; // variante : décoche les autres variantes de sa base

// src/Http/Controleur/LigneControleur.php
public function retenir(Request $requete, array $parametres): Response;

// src/Http/Controleur/OffreControleur.php
public static function grouper(array $lignes, array $totauxLignes): array; // + 'variantes', 'remplacee' ; sans les options
/** @return list<array{l: Ligne, total: int}> */
public static function options(array $lignes): array;

// src/Offre/LigneExtraite.php : constructeur + string $nature = Options::LIGNE, ?int $remplace = null

// src/Offre/ExtracteurApi.php
public static function aDesMotsDOption(string $transcription): bool;
/** @param array<string, mixed> $arguments @param list<LigneExtraite> $lignes @return list<LigneExtraite> */
public function validerOptions(array $arguments, array $lignes): array;
private function classerOptions(string $transcription, ResultatExtraction $resultat): ResultatExtraction;

// outils/banc/NoteExtraction.php (namespace OffreGen\Outils\Banc, chargé par require ; testé par tests/Outils/NoteExtractionTest.php)
final class NoteExtraction {
    /** @param list<array<string, mixed>> $attendues @param list<LigneExtraite> $proposees
     *  @return array{justes: int, fausses: list<array{ligne: int, raison: string}>, en_trop: int, taux: float} */
    public static function noter(array $attendues, array $proposees): array;
}
```

## Décisions fragiles

1. **`Totaux` filtre lui-même.** J'ai supposé qu'aucun appelant n'a besoin d'additionner une option non retenue. Si la
   relecture, la liste des offres ou les statistiques de coût en ont besoin, le lot 2 est à refaire en filtre explicite
   chez chaque appelant.
2. **Second appel `classer_lignes`, déclenché par des mots.** J'ai supposé que l'appel principal crée déjà une ligne
   pour « en option, laquage des portes » et pour « ou alors du vinyle » (« une ligne par travail dicté »), et qu'un
   mot déclencheur (option, variante, ou alors, à la place, au choix, à voir avec le client, éventuel) précède chaque
   cas. Si le banc montre des lignes fusionnées, perdues ou des options sans mot déclencheur, il faut le champ dans
   l'appel principal : consigne changée, empreinte de `ExtracteurActiviteTest` à refaire, non-régression complète.
3. **Variante orpheline lue comme option, sans écriture.** J'ai supposé qu'« Annuler » sur une ligne de base doit
   rendre ses variantes. Si Xavier veut que la suppression de la base efface aussi ses variantes, le lot 5 change
   (suppression en cascade, et `restaurer` à la même date).

## Lots

### Lot 1 : migration (à jouer à la main sur le test et en production)

- Objectif : les trois colonnes existent, l'ancienne version les ignore.
- Fichiers : `migrations/Version20261122200001.php` (à créer).
- Test : `tests/Donnees/ColonnesOptionsTest.php` (colonnes, défauts, `SchemaTest` reste vert).
- Vérification : sur `offregen_options_test` (à créer, droits copiés de `offregen_test`) : `migrations:migrate` (montée,
  descente, remontée), `migrations:up-to-date` « Up-to-date! », `phpunit --filter 'ColonnesOptionsTest|SchemaTest'` OK.
- Dépend de : rien.

### Lot 2 : la règle du total

- Objectif : une offre avec options et variantes compte ce qui est dans le total ; « Total avec toutes les options » juste
  au centime, rabais en % compris, rabais en francs inchangé ; TVA par taux juste.
- Fichiers : `src/Offre/Options.php` (à créer), `src/Offre/Totaux.php`.
- Tests : `tests/Offre/OptionsTest.php` (masques, variante exclusive, orpheline, `lignesDuTotal` dans l'ordre) ;
  `tests/Offre/TotauxTest.php` (cas de la maquette : 2'964.00, 3'043.90, 3'882.90 ; après choix 3'636.00, 3'734.00 ;
  sans les portes 3'302.65 ; rabais en francs constant ; deux taux de TVA).
- Vérification : tests rouges avant le code, `phpunit` complet vert, `phpstan` sans erreur.
- Dépend de : rien (tableaux en mémoire).

### Lot 3 : lecture et écran (traceur)

- Objectif : une offre semée avec une option et une variante les montre à l'écran à leur place, avec la case, et le
  total suit la règle.
- Fichiers : `src/Donnees/DepotOffres.php` (colonnes, `retenir`, `changerNature`, `creer`), `src/Http/Controleur/OffreControleur.php`,
  `templates/_lignes.html.twig`, `templates/offre.html.twig`, `public/css/app.css`.
- Test : `tests/Http/OptionsEcranTest.php` (rendu : variante sous sa base, groupe « Options » sous le total, « Total avec
  toutes les options », base barrée quand une variante est retenue (E5), isolation : ligne d'un autre artisan).
- Vérification : test rouge puis vert ; parcours Playwright local à 390 et 1280 px, captures regardées.
- Dépend de : 1, 2.

### Lot 4 : les gestes

- Objectif : passer une ligne en option ou en variante, en ajouter, cocher le choix du client sans recharger la page.
- Fichiers : `src/Http/Controleur/LigneControleur.php`, `config/routes.php`, `templates/ligne.html.twig`,
  `templates/ajout.html.twig`, `public/js/app.js`, `src/Donnees/DepotOffres.php`.
- Test : `tests/Http/OptionsGestesTest.php` (nature changée, base invalide 422, base d'un autre artisan refusée, ligne
  qui a des variantes refuse « Option », variante exclusive, `retour=facturer`, CSRF, brouillon de facture ignore
  `nature`) ; `IsolationRoutesTest` couvre la route neuve.
- Vérification : tests verts ; parcours cliqué local des étapes 1 à 3 et 6 de la maquette, sans rechargement (réseau
  relevé), captures regardées.
- Dépend de : 3.

### Lot 5 : le document, douze modèles

- Objectif : variantes sous leur ligne et bloc « Options » sous le total dans les douze modèles ; après le choix, le
  document suit le point 2 ; une facture n'en montre rien.
- Fichiers : `templates/document/_blocs.html.twig`, `public/css/document.css`, `tests/Outils/OffreDEssai.php`.
- Test : `tests/Offre/GabaritsOptionsTest.php` (douze modèles : textes attendus, bloc qui ne touche pas le pied,
  `TextePdf`) ; `ReglesDuDocumentTest` reste vert.
- Vérification : tests verts, PDF des douze lus (PyMuPDF) et pages regardées.
- Dépend de : 3.

### Lot 6 : facturer

- Objectif : l'acompte et la finale prennent ce qui est coché ; les cases dans « Facturer » (E1).
- Fichiers : `src/Http/Controleur/FactureControleur.php`, `templates/facturer.html.twig`.
- Test : `tests/Http/FacturerOptionsTest.php` (acompte de 30 % sur 3'734.00 = 1'120.20, finale sans les portes
  3'302.65 dont 2'182.45 à payer ; une option non retenue absente du PDF de la facture).
- Vérification : tests verts, PDF des factures lus.
- Dépend de : 4.

### Lot 7 : le banc du sous-chantier 29

- Objectif : `php outils/banc-corpus.php <corpus> [--passes=3] [--moitie=reglage|controle] [--reglage=nom]` rend, par
  dictée et au total : lignes justes, fausses avec raison, en trop, nature et base justes, jetons, coût en centimes,
  durée.
- Fichiers : `outils/banc-corpus.php`, `outils/banc/NoteExtraction.php` (à créer) ; corpus hors dépôt :
  `OffreGen/corpus/NN/` (Synology), `transcription.txt`, `catalogue.json` (tiré de `CatalogueFictif::produits`),
  `attendu.json`, `moitie` fixée à la création.
- Test : `tests/Outils/NoteExtractionTest.php` (appariement, ligne en trop, mauvaise nature, mauvaise base).
- Vérification : test vert ; banc lancé sur le corpus écrit avec la consigne en service, rapport collé dans `ETAT.md`
  (c'est la référence de non-régression).
- Corpus écrit : quinze dictées inventées (peintre, menuisier, plâtrier), dont six sans option ; tarif des jetons relevé
  sur la page d'Infomaniak et cité dans le rapport.
- Dépend de : rien (peut tourner avant les lots 3 à 6).

### Lot 8 : l'extraction

- Objectif : les options et variantes dictées arrivent dans l'offre ; les dictées sans option ne reculent pas.
- Fichiers : `src/Offre/LigneExtraite.php`, `src/Offre/ExtracteurApi.php`, `src/Donnees/DepotOffres.php` (`creer`).
- Tests : `tests/Offre/ExtracteurApiTest.php` (`validerOptions` : variante sans base valable devenue option à vérifier,
  numéro hors liste ignoré, nature inconnue ignorée ; aucun second appel sans mot déclencheur) ; `TraiteurTest`
  (variante créée avec sa base).
- Vérification : tests verts ; banc : nature et base justes sur les dictées d'options, taux des six dictées sans option
  au moins égal à la référence du lot 7, rapport collé.
- Dépend de : 7, 3.

### Phases suivantes

Revue (phase 7 : surfaces sensibles touchées : appels au modèle, couche d'accès aux données, droits par route, donc
revue lourde), puis recette sur le site de test (phase 8), avec les vraies dictées de Xavier passées au banc avant la
mise en service.
