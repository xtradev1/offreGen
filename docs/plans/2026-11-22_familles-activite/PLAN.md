# PLAN : activités (bâtiment, extérieur, prestations)

Entrée : `SPEC.md`, gate intention passé le 2026-11-22. Relevés faits sur le commit `8385e78`, dans `~/offreGen-familles`.

## Contrat technique

### Endpoints

Aucune route nouvelle. Deux routes existantes reçoivent un champ de plus.

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/reglages` | POST | `Route::ARTISAN`, CSRF du noyau | champ `activite` (`batiment`, `exterieur`, `prestations`), facultatif comme les autres champs | 303 vers la rubrique, ou 422 avec `erreurs.activite` ; JSON `{ok}` pour l'enregistrement automatique |
| `/bienvenue/entreprise` | POST | idem | idem (même formulaire, assistant du premier lancement) | 303 vers l'étape suivante, ou 422 |
| `/admin/demos` | POST | inchangé | `metier` accepte quatre clés de plus | inchangé |

### Structures

- Base : `artisan.activite VARCHAR(20) NOT NULL DEFAULT 'batiment'` (à créer). Valeurs : celles de l'énumération.
- `OffreGen\Compte\Activite` (à créer) : énumération `Batiment = 'batiment'`, `Exterieur = 'exterieur'`, `Prestations = 'prestations'`.
- Mots d'une activité : `array<string, string>`, mêmes clés pour les trois : `dictee`, `dictee_nom`, `dictee_objet`, `titre_defaut`, `groupe`, `bloc_titre`, `bloc_adresse`, `bloc_contact`, `bloc_acces`, `debut`, `date`, `doc_lieu`, `doc_fiche`, `doc_detail`, `lettre_date`, `travaux`, `premiere`. Une valeur vide veut dire « ne rien afficher » (`bloc_titre`, `bloc_acces` en Prestations).
- Type `Artisan` de `DepotArtisans` : gagne `activite: string`.
- Données d'un document (`OffreControleur::vueDocument`) : gagnent `activite: Activite` et `mots: array<string, string>`.

### Flux

1. **L'activité naît** : `Comptes::creer` et `Comptes::reinitialiserDemo` (`src/Compte/Comptes.php`) appellent `DepotArtisans::changerMetier`, qui écrit le métier et l'activité tirée de `Activite::duMetier` (à créer). Un compte sans métier garde la valeur par défaut de la colonne. Le titulaire la change par `/reglages` : `ReglagesControleur::sauver` appelle `DepotArtisans::changerActivite` (à créer) avec l'identifiant de la session.
2. **L'activité règle l'IA** : `Traiteur::creerOffre` (`src/Audio/Traiteur.php:171`) lit le compte par `DepotArtisans::parId` (déjà injecté : `$this->artisans`), passe l'activité à `Extracteur::extraire`. `ExtracteurApi` choisit la consigne (`consigne()`, à créer), saute `rangerParPiece` si `rangeParGroupe()` est faux, et donne le mot « zone » au second appel en Extérieur.
3. **L'activité règle les écrans** : `Conteneur::twig()` expose `mots()` et `activite()` (à créer), tirées du compte de la session par `Conteneur::activiteCourante()` (à créer) ; sans session, Bâtiment. Les gabarits d'écran lisent `mots()`.
4. **L'activité règle les documents** : `OffreControleur::vueDocument` ajoute `activite` et `mots` du compte de la portée. `templates/_document.html.twig`, seul point d'entrée des douze modèles, prend ceux de la session quand la vue ne les donne pas (factures). Les gabarits lisent `mots.x`, les macros de `_blocs.html.twig` lisent `d.mots.x`.

### Existant réutilisé

- `DepotArtisans::parId`, `::changerMetier` (`src/Donnees/DepotArtisans.php`), `Comptes::creer`, `::reinitialiserDemo`, `Demos::creer`.
- `CatalogueFictif::libelles`, `::produits`, `::offres` ; `Marque::pourMetier`.
- `ExtracteurApi::rangerParPiece`, et son repli : une offre sans pièce sort rangée par catégorie (`OffreControleur::grouper`).
- `Piece::lire` pour une zone comme pour une pièce ; colonnes `offre.chantier_*`, `debut_travaux`, `duree_travaux`, `visite_le`, `ligne.piece`, inchangées.
- `ReglagesControleur::sauver`, `::champsActuels`, gabarit `_reglages_entreprise.html.twig`, classes `.groupe` et `.radio`.
- Tests : `Jeu`, `Monde`, `Navigateur`, `BaseDeTest`, `OffreDEssai`, `MockHttpClient`.

## Signatures

```php
// src/Compte/Activite.php (à créer)
enum Activite: string
{
    public static function lire(?string $cle): self;            // valeur inconnue ou null : Batiment
    public static function duMetier(?string $metier): self;     // métier inconnu ou null : Batiment
    public function libelle(): string;                          // « Bâtiment », « Extérieur », « Prestations »
    public function exemples(): string;                         // sous le libellé, dans le réglage
    public function rangeParGroupe(): bool;                     // faux pour Prestations
    /** @return array<string, string> */ public function mots(): array;
    /** @param array<string, string> $libelles  @return list<array{nom: string, metiers: array<string, string>}> */
    public static function grouper(array $libelles): array;
}

// src/Donnees/DepotArtisans.php
public function changerMetier(int $id, ?string $metier, ?string $metierSaisi, Activite $activite): void;
public function changerActivite(int $id, Activite $activite): void;

// src/Offre/Extracteur.php, ExtracteurApi.php, ExtracteurFactice.php
public function extraire(string $transcription, array $catalogue, Activite $activite = Activite::Batiment): ResultatExtraction;
public static function consigne(Activite $activite): string;          // ExtracteurApi
public static function consigneGroupes(Activite $activite): string;   // ExtracteurApi

// src/Compte/PhrasesLettre.php
public static function actuelles(Activite $activite): array;           // array<string, string>
public static function tournures(Activite $activite): array;           // array<string, list<string>>
public function texte(string $cle, ?string $client, Activite $activite = Activite::Batiment): string;
public function versChamps(Activite $activite = Activite::Batiment): array;
public static function depuisSaisie(array $champs, Activite $activite = Activite::Batiment): array;

// src/Http/Conteneur.php
public function activiteCourante(): Activite;
```

Colonne : `ALTER TABLE artisan ADD activite VARCHAR(20) NOT NULL DEFAULT 'batiment'`.

## Décisions fragiles

1. J'ai supposé que les factures et les aperçus se rendent toujours dans la session du titulaire : le repli de `_document.html.twig` sur `mots()` suffit donc pour eux. Si un document se rend ailleurs pour un autre compte (cron, courriel), il sortira avec les mots du Bâtiment, et le lot 4 est à compléter.
2. J'ai supposé que remplacer deux phrases de la consigne suffit pour les prestations, et que les règles communes écrites avec le mot « travail » ne gênent pas le modèle. Si les dictées de la recette disent le contraire, la consigne des Prestations devient un texte à part (lot 3).
3. J'ai supposé qu'aucun écran ne permet de créer la première pièce d'une offre à la main. Si c'est faux, un compte Prestations peut ranger par « pièce » avec ce mot à l'écran, et le lot 2 doit donner un mot aux Prestations.

## Lots

Vérification commune : dans `~/offreGen-familles`, base `offregen_familles_test`. Les tests se lancent par `vendor/bin/phpunit <chemin>`, l'analyse par `vendor/bin/phpstan analyse --memory-limit=512M`.

### Lot 1 : la colonne et le traceur

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | Un compte porte une activité ; l'écran de dictée dit la phrase de son activité (chaîne complète : base, dépôt, conteneur, gabarit) |
| Fichiers touchés | `migrations/Version20261122100001.php` (à créer), `src/Compte/Activite.php` (à créer), `src/Donnees/DepotArtisans.php`, `src/Http/Conteneur.php`, `templates/_nouvelle_offre.html.twig` |
| Test | `tests/Compte/ActiviteTest.php` (mêmes clés de mots pour les trois, `lire`, `duMetier`, `grouper`) ; `tests/Http/ActiviteEcransTest.php::testLEcranDeDicteeParleLaLangueDeLActivite` |
| Vérification | `vendor/bin/phpunit tests/Compte/ActiviteTest.php tests/Http/ActiviteEcransTest.php` : OK ; migration jouée et annulée sur la base du chantier |
| Dépend de | rien |

### Lot 2 : les mots des écrans

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | Liste des dictées, pièce ou zone, en-tête de l'offre, aides de la facture et de la TVA, entrée d'une démo : chaque écran prend ses mots dans l'activité |
| Fichiers touchés | `templates/accueil.html.twig`, `ajout.html.twig`, `ligne.html.twig`, `piece.html.twig`, `offre_entete.html.twig`, `facture.html.twig`, `facturer.html.twig`, `_reglages_tva.html.twig`, `demo_entrer.html.twig`, `src/Http/Controleur/PieceControleur.php`, `src/Http/Controleur/DemoControleur.php` |
| Test | `tests/Http/ActiviteEcransTest.php` : en-tête sans titre de bloc ni « Accès » en Prestations, « Accès » gardé s'il porte déjà un texte, « Zone » en Extérieur, écrans du Bâtiment inchangés |
| Vérification | `vendor/bin/phpunit tests/Http` : OK |
| Dépend de | 1 |

### Lot 3 : l'extraction

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | La consigne et le rangement suivent l'activité ; celle du Bâtiment reste identique au caractère près ; le titre par défaut suit l'activité |
| Fichiers touchés | `src/Offre/Extracteur.php`, `src/Offre/ExtracteurApi.php`, `src/Offre/ExtracteurFactice.php`, `src/Audio/Traiteur.php`, doublures d'`Extracteur` dans `tests/` |
| Test | `tests/Offre/ExtracteurActiviteTest.php` : empreinte SHA-256 des deux consignes du Bâtiment (`a3cdb155…`, `f83d5c17…`, relevées sur `8385e78`) ; Prestations : un seul appel HTTP, consigne sans « artisan » ni « visite » ; Extérieur : deux appels, le second dit « zone ». `tests/Audio` : le traitement passe l'activité du compte |
| Vérification | `vendor/bin/phpunit tests/Offre tests/Audio` : OK |
| Dépend de | 1 |

### Lot 4 : les documents

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | Les douze modèles et les phrases du modèle Lettre prennent leurs mots dans l'activité |
| Fichiers touchés | `src/Http/Controleur/OffreControleur.php`, `src/Http/Controleur/ReglagesControleur.php`, `src/Compte/PhrasesLettre.php`, `src/Compte/Reglages.php`, `templates/_document.html.twig`, `templates/offre.html.twig`, `templates/document/_blocs.html.twig`, `chantier.html.twig`, `enseigne.html.twig`, `lateral.html.twig`, `technique.html.twig`, `recap.html.twig`, `lettre.html.twig` |
| Test | `tests/Offre/GabaritsActiviteTest.php` : pour chacun des douze modèles, une offre de Prestations aux champs remplis se rend sans « chantier », « travaux » ni « visite » (hors nom de classe CSS) ; la même offre en Bâtiment garde « Chantier » et « Début des travaux » |
| Vérification | `vendor/bin/phpunit tests/Offre tests/Compte` : OK |
| Dépend de | 1 |

### Lot 5 : unités, métiers, catalogues

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | « mois » et « passage » ; Informatique, Conseil, Marketing, Autre prestation avec douze produits et deux offres ; « Jardinier, paysagiste » ; un compte créé avec un métier reçoit son activité ; la création d'une démo groupe les métiers |
| Fichiers touchés | `src/Offre/Saisie.php`, `src/Catalogue/CatalogueFictif.php`, `src/Compte/Marque.php`, `src/Compte/Comptes.php`, `src/Http/Controleur/AdminControleur.php`, `templates/admin/demo_nouvelle.html.twig`, `docs/plans/2026-11-19_metiers-demo/CATALOGUES.md`, tests qui figent les listes (`SaisieTest`, `CatalogueFictifTest`, `MarqueTest`) |
| Test | `CatalogueFictifTest` étendu aux treize clés ; `tests/Compte/ActiviteComptesTest.php` : démo « informatique » = douze produits, deux offres, activité Prestations ; démo « paysagiste » = Extérieur ; écran de création : trois intertitres |
| Vérification | `vendor/bin/phpunit tests/Catalogue tests/Compte tests/Http` : OK |
| Dépend de | 1 |

### Lot 6 : le réglage « Votre activité »

- [x] Fait le 2026-11-22

| Champ | Contenu |
|---|---|
| Objectif | Le titulaire choisit son activité dans « Mon entreprise » (et à la première étape de l'assistant) ; rien d'autre ne change sur son compte |
| Fichiers touchés | `src/Http/Controleur/ReglagesControleur.php`, `templates/_reglages_entreprise.html.twig` |
| Test | `tests/Http/ActiviteReglageTest.php` : le choix s'enregistre et change l'écran de dictée ; valeur inconnue = 422 et rien d'écrit ; catalogue, offres et modèle inchangés ; le compte B n'est pas touché |
| Vérification | `vendor/bin/phpunit tests/Http` : OK |
| Dépend de | 1, 2 |

### Lot 7 : retours de Xavier du 2026-11-23

- [x] Fait le 2026-11-23

| Champ | Contenu |
|---|---|
| Objectif | Accès replié en Prestations ; second appel des Prestations sur les parties annoncées, mot « catégorie » ; plus de titre « Hors catalogue » |
| Fichiers touchés | `src/Compte/Activite.php` (`rangeParGroupe` retirée, `groupe` des Prestations), `src/Offre/ExtracteurApi.php` (`CONSIGNE_CATEGORIES`, `consigneGroupes`, `outilPieces`), `templates/offre_entete.html.twig`, `src/Http/Controleur/OffreControleur.php` (`grouper`, `HORS_CATALOGUE` retirée), `src/Http/Controleur/FactureControleur.php`, `templates/document/_blocs.html.twig`, `templates/document/recap.html.twig` |
| Test | `ExtracteurActiviteTest` (deux tests de Prestations), `ActiviteTest`, `ActiviteEcransTest` (accès replié, accès ouvert, catégorie, sans titre), `GabaritsActiviteTest::testLesLignesSansCategorieNOntPasDeTitre` (douze modèles), `EcransTest` (deux assertions « Hors catalogue » remplacées) |
| Vérification | `vendor/bin/phpunit` : suite complète verte ; `vendor/bin/phpstan analyse` : aucune erreur |
| Dépend de | 1 à 6 |

## Migrations et données

- `Version20261122100001` : additive, `activite` à « batiment », puis « exterieur » pour les comptes dont le métier est « paysagiste ». Annulation : suppression de la colonne. Elle alimente les préalables de la recette : à jouer sur le site de test par le déploiement.
- Données de référence : quatre catalogues de démonstration dans le code (`CatalogueFictif`), sans écriture en base.
