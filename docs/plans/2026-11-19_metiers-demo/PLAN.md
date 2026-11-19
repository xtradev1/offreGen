# Plan : métiers de la démo et de l'essai

Entrées : `SPEC.md` (validée le 2026-11-19), `CATALOGUES.md`, `MAQUETTE-vos-prix.html` et les réponses de Xavier du 2026-11-19 (V1 à V6). Exécution dans la session principale, lot par lot, test d'abord. Aucun commit sans demande.

## Ce que les réponses à la maquette imposent

| Réponse | Conséquence dans le code |
|---|---|
| V1 validé, « quand je clique reprendre, ça doit automatiquement faire suivant » | « Reprendre » enregistre le prix de la démo et ouvre le produit suivant, en un toucher. Un message « Prix repris » avec « Annuler » suit, comme pour un produit retiré (décision de Claude : un toucher de travers ne doit pas laisser un prix inventé dans un vrai catalogue). |
| V2 = A, clavier du téléphone | Un champ `inputmode="decimal"`. « Reprendre », « Passer » et « Suivant » sont dans le flux, sous le champ : la barre d'action du bas serait cachée par le clavier. Le lot 10 garde le clavier ouvert d'un produit à l'autre. |
| V3 = A | Glisser la carte à gauche fait « Passer » et rien d'autre (lot 10). |
| V4 validé | « Je ne fais pas ça » retire le produit, « Annuler » reste visible quelques secondes. |
| V5 validé | Bilan, rappel dans le catalogue, écran sans produit à compléter. |
| V6 = A | Le prix de la démo se garde à côté du produit et se propose. Il entre au catalogue par « Reprendre » seulement. |

## Contrat technique

### Structures (colonnes ajoutées, migration additive)

| Table | Colonne | Type | Sens |
|---|---|---|---|
| `artisan` | `metier` | `VARCHAR(20) NULL` | clé du métier (`peintre`, `platrier`, `carreleur`, `macon`, `sanitaire`, `menuisier`, `sols`, `paysagiste`, `autre`) ; NULL pour un compte sans métier |
| `artisan` | `metier_saisi` | `VARCHAR(60) NULL` | texte tapé quand le métier est `autre` |
| `produit` | `prix_exemple_centimes` | `INT UNSIGNED NULL` | NULL : produit de l'artisan. Une valeur : produit d'exemple jamais modifié, avec son prix de démonstration. Sert de marque « exemple » et de prix proposé. |
| `offre` | `exemple` | `TINYINT(1) NOT NULL DEFAULT 0` | 1 : offre d'exemple posée par la démo |
| `client` | `exemple` | `TINYINT(1) NOT NULL DEFAULT 0` | 1 : client inventé d'une offre d'exemple |

L'ancienne version du code n'écrit aucune de ces colonnes : toutes ont une valeur par défaut.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/vos-prix` | GET | `Route::ARTISAN` | `p` (identifiant du produit à montrer, facultatif), `suite` (1 : garde le bilan en cours) | 200 : un produit sans prix, ou le bilan, ou l'écran « rien à compléter » |
| `/vos-prix/{id}` | POST | `Route::ARTISAN` | `_csrf`, `action` (`suivant`, `reprendre`, `passer`, `retirer`), `prix` | 303 vers `/vos-prix?p=<suivant>&suite=1` ; 422 et la même carte si `suivant` sans prix valable ; 404 si le produit n'est pas à l'artisan |
| `/vos-prix/annuler` | POST | `Route::ARTISAN` | `_csrf` | 303 vers `/vos-prix?p=<produit remis>&suite=1` ; sans rien à annuler, 303 vers `/vos-prix` |
| `/vos-prix/plus-tard` | POST | `Route::ARTISAN` | `_csrf` | 303 vers `/` ; le bandeau de l'accueil devient un rappel d'une ligne pour la session |

Commandes : `bin/reinitialiser-demo <courriel> <métier> [texte du métier]`, `bin/creer-compte … [--metier=clé] [--metier-saisi=texte]`, `bin/passer-en-essai <courriel> [--offres=N] [--acces]`, `bin/metiers-saisis`.

### Flux

1. **Pose d'une démo.** `bin/reinitialiser-demo` → `Comptes::reinitialiserDemo` (existe, `src/Compte/Comptes.php:92`) → `CatalogueFictif::produits` (existe) → `DepotProduits::remplacerTout` (existe, reçoit `prix_exemple_centimes`) → `DepotClients::creer` et `DepotOffres::creerExemple` (existent, posent `exemple = 1`) → `DepotArtisans::changerMetier` (à créer).
2. **Un produit modifié devient celui de l'artisan.** `CatalogueControleur::modifier` (existe) → `DepotProduits::modifier` (existe) qui remet `prix_exemple_centimes` à NULL.
3. **Passage à l'essai.** `bin/passer-en-essai` → `Comptes::passerEnEssai` (à créer), une transaction : `DepotFactures::effacerDesOffresExemple`, `DepotOffres::effacerExemples`, `DepotClients::effacerExemplesSansOffre`, `DepotProduits::remettreExemplesAZero`, `DepotArtisans::sortirDeDemo` (tous à créer). Le bouton « Transformer en essai » du chantier des comptes appellera `Comptes::passerEnEssai`.
4. **Essai créé avec un métier.** `bin/creer-compte --metier=…` → `Comptes::creer` (existe, deux paramètres de plus) → `DepotProduits::remplacerTout` avec `prix_centimes = 0` et `prix_exemple_centimes` = prix du catalogue.
5. **Écran « Vos prix ».** `AccueilControleur::liste` (existe) compte par `DepotProduits::sansPrix` (à créer) et montre le bandeau → `GET /vos-prix` → `VosPrixControleur::afficher` (à créer) → `templates/vos_prix.html.twig` (à créer) → `POST /vos-prix/{id}` → `VosPrixControleur::agir` → `DepotProduits::fixerPrix` ou `supprimer` → message `Flash` de type `annuler` (existe, `src/Http/Flash.php`, affiché par `templates/base.html.twig:27`) → carte suivante.
6. **Rappel du catalogue.** `CatalogueControleur::liste` (existe) passe le nombre de produits sans prix à `templates/catalogue.html.twig`.

### Existant réutilisé

| Besoin | Où |
|---|---|
| Un produit à la fois, formulaire, redirection | `src/Http/Controleur/RelectureControleur.php`, `templates/relecture.html.twig`, styles `relu-*` de `public/css/app.css` |
| Message avec « Annuler » qui s'efface seul | `Flash::dire($requete, 'annuler', …)`, `templates/base.html.twig`, `public/js/app.js` |
| Lecture d'un prix saisi | `Saisie::centimes` (`src/Offre/Saisie.php:27`), `Formats::saisiePrix` |
| Glisser une carte à gauche | `balayage()` et `glisserLignes()` de `public/js/app.js` |
| Courriel d'accès d'un compte d'essai | `LiensAcces::envoyerAcces` (`src/Compte/LiensAcces.php`) |
| Jeu de test à deux artisans, navigateur de test | `tests/Outils/Jeu.php`, `tests/Outils/Navigateur.php` |
| Une route à paramètre exige un cas d'isolation | `tests/Http/IsolationRoutesTest.php` (`cas()`, `casEcriture()`) |

## Signatures

```php
// src/Catalogue/CatalogueFictif.php
/** @return array<string, string> clé => libellé, dans l'ordre d'affichage */
public static function libelles(): array
public static function libelle(string $metier): string

// src/Donnees/DepotArtisans.php (COLONNES reçoit metier et metier_saisi)
public function changerMetier(int $id, ?string $metier, ?string $metierSaisi): void
public function sortirDeDemo(int $id, int $offresMax): void
/** @return list<array{texte: string, nombre: int}> */
public function metiersSaisis(): array

// src/Donnees/DepotProduits.php (Produit et ProduitNeuf reçoivent prix_exemple_centimes: int|null)
/** @return list<Produit> les produits au prix 0, dans l'ordre du catalogue */
public function sansPrix(): array
public function fixerPrix(int $id, int $prixCentimes): bool
/** Annule « Reprendre » : prix à 0, prix d'exemple remis. */
public function remettreExemple(int $id, int $prixExempleCentimes): bool
/** @return int nombre de produits d'exemple remis à 0 */
public function remettreExemplesAZero(): int

// src/Donnees/DepotOffres.php, DepotClients.php, DepotFactures.php
public function effacerExemples(): int               // DepotOffres
public function effacerExemplesSansOffre(): int      // DepotClients ; un client d'exemple qui garde une offre perd sa marque
public function effacerDesOffresExemple(): void      // DepotFactures

// src/Compte/Comptes.php
public function creer(string $courriel, string $entreprise, bool $demo, ?int $plafond, ?int $offresMax = null, ?string $metier = null, ?string $metierSaisi = null): string
public function reinitialiserDemo(string $courriel, string $metier, ?string $metierSaisi = null): int
/** @return array{offres: int, clients: int, produits: int} ce qui a été retiré ou remis à 0 */
public function passerEnEssai(string $courriel, int $offresMax = 10): array

// src/Http/Controleur/VosPrixControleur.php (à créer)
public function afficher(Request $requete): Response
public function agir(Request $requete, array $parametres): Response
public function annuler(Request $requete): Response
public function plusTard(Request $requete): Response
```

## Décisions fragiles

1. J'ai supposé qu'un champ gardé actif pendant que la carte change autour de lui garde le clavier de l'iPhone ouvert, et que la hauteur visible au-dessus du clavier (environ 400 px) contient le nom, le champ, « Reprendre » et les deux boutons. Si c'est faux, l'artisan touche le champ à chaque produit et le lot 10 est à refaire. Aucun iPhone ici pour le vérifier.
2. J'ai supposé qu'une seule colonne, `prix_exemple_centimes`, suffit comme marque et comme prix proposé. Si Xavier revient à « prix gardés » (réponse P2 = B du chantier des comptes), `remettreExemplesAZero` sort du lot 5 ; la colonne reste.
3. J'ai supposé que « Annuler » après « Je ne fais pas ça » peut recréer le produit depuis la session, avec un nouvel identifiant. Les lignes des offres dictées pendant la démo qui le citaient gardent leur texte et leur prix, et perdent leur lien au catalogue. Si ce lien compte, le lot 8 passe à une suppression différée.

Confirmé par Xavier le 2026-11-19 (« démo et essai, même chose ») : la démo et l'essai vivent sur le même site.

## Fichiers partagés avec d'autres sessions

Constaté par `git status` le 2026-11-19 : `public/css/app.css`, `public/js/app.js` et `CODING_RULES.md` portent des modifications non commitées d'autres sessions. Le chantier des comptes (`PLAN-lot-A.md`) prévoit de toucher `src/Compte/Comptes.php`, `src/Donnees/DepotArtisans.php`, `config/routes.php` et une migration `Version20261119000001`, et attend la réponse de Xavier sur l'ordre des deux chantiers.

- Les lots 0 et 1 ne touchent aucun fichier partagé : ils démarrent tout de suite.
- Les lots 2 à 6 touchent `Comptes.php` et `DepotArtisans.php`. Ordre donné par Xavier le 2026-11-19 : « Admin, métiers, puis démo ». Le chantier des comptes a commité ses lots 1 à 4 (7a7956d, migration `Version20261119100001`) ; ce chantier a suivi, avec `Version20261119000002`.
- Les lots 7 à 10 touchent `app.css` et `app.js`, encore modifiés par d'autres sessions au `git status` du 2026-11-19 au soir : n'y commiter que ses propres morceaux.

## Lots

Ordre : données et commandes d'abord (lots 1 à 6, une seule couche, chacun vérifié par PHPUnit), puis l'écran, qui traverse la base et l'interface et commence par un traceur (lot 7).

| Lot | Objectif | Fichiers | Test écrit dans le lot | Vérification | Dépend de |
|---|---|---|---|---|---|
| 0 | Chaque repère de prix de `CATALOGUES.md` est lu sur sa page ; un prix hors de la fourchette lue est corrigé ou passe en « sans repère » | `CATALOGUES.md`, `ETAT.md` | aucun (document) | tableau dans `ETAT.md` : source, page ouverte oui ou non, fourchette lue, prix retenu ; `controle.py` sans défaut | rien |
| 1 | Neuf métiers dans le code, avec libellé et ordre ; le menuisier est refait, ses lignes de parquet passent au poseur de sols | `src/Catalogue/CatalogueFictif.php`, `tests/Compte/DemoTest.php` (l'essai du parquet passe sur `sols`) | `tests/Catalogue/CatalogueFictifTest.php` : neuf clés dans l'ordre, un libellé chacune, 12 produits uniques, 2 offres, unités dans `Saisie::UNITES`, lignes d'offre présentes au catalogue, quantités plus grandes que 0 | `vendor/bin/phpunit tests/Catalogue tests/Compte/DemoTest.php tests/Bout` vert ; `vendor/bin/phpstan analyse` sans erreur | 0 |
| 2 | Migration additive : les cinq colonnes du contrat | `migrations/Version20261119000002.php` | `tests/Donnees/ColonnesMetiersTest.php` : les cinq colonnes existent, avec leur type et leur valeur par défaut | `vendor/bin/doctrine-migrations migrations:migrate` puis `migrations:up-to-date` ; PHPUnit vert | accord sur l'ordre |
| 3 | Une démo remise à zéro écrit son métier sur le compte et marque ses produits, ses 2 offres et leurs clients ; un produit modifié perd sa marque | `src/Compte/Comptes.php`, `src/Donnees/DepotArtisans.php`, `DepotProduits.php`, `DepotOffres.php`, `DepotClients.php`, `bin/reinitialiser-demo` | `tests/Compte/DemoTest.php` : métier lu en base, 12 produits marqués avec leur prix, 2 offres et 2 clients marqués, « autre » avec son texte de 60 caractères au plus, texte trop long refusé ; `tests/Http/` : modifier un produit met sa marque à NULL | PHPUnit vert sur ces fichiers et sur `tests/Donnees/IsolationDepotsTest.php` | 1, 2 |
| 4 | Un essai créé avec un métier reçoit 12 produits au prix 0, chacun avec son prix proposé ; sans métier, catalogue vide comme aujourd'hui | `src/Compte/Comptes.php`, `bin/creer-compte` | `tests/Compte/CreationCompteTest.php` : 12 produits, tous à 0, tous marqués ; sans métier 0 produit ; métier inconnu refusé avant la création du compte | PHPUnit vert ; `tests/Regles/CommandesTest.php` vert | 3 |
| 5 | Une démo devient un essai : offres et clients d'exemple retirés, offres dictées gardées, produits d'exemple à 0, produits de l'artisan intacts, compte hors du mode démo avec son plafond | `src/Compte/Comptes.php`, les quatre dépôts, `bin/passer-en-essai` | `tests/Compte/PassageEssaiTest.php` : chaque règle de la spec, la garantie (aucun produit marqué avec un prix), une facture d'une offre d'exemple ne bloque rien, le compte B intact, un compte qui n'est pas une démo refusé sans rien changer, retour complet si une étape échoue | PHPUnit vert | 3 |
| 6 | Une commande liste les métiers tapés sous « autre » et leur nombre | `src/Donnees/DepotArtisans.php`, `bin/metiers-saisis` | `tests/Compte/` : deux comptes « ébéniste », un « ramoneur », sortie triée par nombre | PHPUnit vert, `CommandesTest` vert | 3 |
| 7 | Traceur : `/vos-prix` montre le premier produit sans prix de l'artisan, dans la mise en page de la maquette, et « Suivant » enregistre un prix | `config/routes.php`, `src/Http/Controleur/VosPrixControleur.php`, `templates/vos_prix.html.twig`, `src/Donnees/DepotProduits.php`, `public/css/app.css` | `tests/Http/VosPrixTest.php` : la carte, le prix enregistré, 422 sans prix ; `IsolationRoutesTest` : un cas pour `/vos-prix/{id}` | PHPUnit vert ; capture Playwright à 390 px regardée | 4, fichiers partagés commités |
| 8 | « Reprendre » (enregistre et avance), « Passer », « Je ne fais pas ça », « Annuler » pour les deux, bilan, écran sans produit à compléter | les fichiers du lot 7 | `VosPrixTest` : chaque action, l'annulation de chacune, l'ordre des cartes, le bilan, un produit de B refusé (404) | PHPUnit vert | 7 |
| 9 | Bandeau sur « Mes offres » pour un compte qui a des produits d'exemple sans prix, « Plus tard », rappel dans le catalogue | `src/Http/Controleur/AccueilControleur.php`, `CatalogueControleur.php`, `templates/accueil.html.twig`, `templates/catalogue.html.twig`, `public/css/app.css` | `VosPrixTest` : bandeau présent, absent pour une démo et pour un compte sans produit d'exemple, réduit après « Plus tard », rappel du catalogue | PHPUnit vert ; captures regardées | 8 |
| 10 | Sur téléphone, le produit suivant arrive sans rechargement et le clavier reste ouvert ; glisser à gauche fait « Passer » ; le message s'efface après 5 secondes | `public/js/app.js`, `templates/vos_prix.html.twig` | parcours Playwright au clic et au glisser, à 390 px, clair et sombre | sortie du parcours : aucune erreur, aucune cible sous 48 px, aucun débordement ; captures regardées | 9 |
| 11 | Une dictée d'essai par nouveau métier (plâtrier, carreleur, maçon, poseur de sols, paysagiste, menuisier), par l'interface | `ETAT.md` | aucun | tableau : lignes justes sur lignes dictées, par métier | 3, site de test à jour (déploiement à demander) |

## Avancement

- [x] Lot 0, 2026-11-19 : 14 pages ouvertes, sept prix corrigés, S10 retirée. Preuve dans `ETAT.md`.
- [x] Lot 1, 2026-11-19 : neuf catalogues dans `CatalogueFictif`, `libelles()` et `libelle()`, tests verts. Ajout au plan : `DemoTest::testChaqueMetierDonneUneDemoComplete` pose une démo par métier.
- [x] Lot 2, 2026-11-19 : migration `Version20261119000002`, jouée sur les bases locales de test et de développement.
- [x] Lot 3, 2026-11-19 : métier écrit sur le compte, produits, offres et clients d'exemple marqués, marque retirée à la modification d'un produit. Ajout au plan : une fiche client d'exemple modifiée perd aussi sa marque.
- [x] Lot 4, 2026-11-19 : `Comptes::creer` et `bin/creer-compte --metier`. Ajout au plan : une démo créée avec un métier est posée tout de suite.
- [x] Lot 5, 2026-11-19 : `Comptes::passerEnEssai` et `bin/passer-en-essai`. Ajout au plan : le plafond vaut N plus les imports déjà reçus.
- [x] Lot 6, 2026-11-19 : `DepotArtisans::metiersSaisis` et `bin/metiers-saisis`.
- [ ] Lots 7 à 10 : `public/css/app.css` et `public/js/app.js` portent encore les modifications d'autres sessions ; n'y commiter que ses propres morceaux. `sansPrix`, `fixerPrix` et `remettreExemple` de `DepotProduits` s'écrivent avec ces lots.
- [ ] Lot 11 : attend le lot 3 et un site de test à jour.

## Préalables de la recette

- Jouer la migration `Version20261119000002` sur le site visé.
- Remettre à zéro les démos existantes après la migration : une démo posée avant n'a aucune marque, son passage à l'essai garderait ses offres d'exemple.
- Aligner le texte de la maquette des comptes (« ses offres, son catalogue et ses réglages restent ») : chantier des comptes.

## Hors plan

- L'écran de choix du métier et le bouton « Transformer en essai » : chantier des comptes.
- La correction de la description de la tâche Origin 483 (elle cite l'électricien) : aucun outil ne le permet.
