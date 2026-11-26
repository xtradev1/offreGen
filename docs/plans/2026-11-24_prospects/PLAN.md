# PLAN : prospects, le catalogue des commerciaux

Entrées : `SPEC.md`, `MAQUETTE-prospects.html` (réponses P1 à P9 de Xavier du 2026-11-24, dans `ETAT.md`).

## Contrat technique

### Endpoints

Toutes les routes portent `Route::EQUIPE`, sauf la fusion (`Route::ADMIN`). Chaque POST porte `_csrf`.

| Route | Verbe | Entrée | Sortie |
|---|---|---|---|
| `/admin/prospects` | GET | `vue` (miens, libres, tous, retard), `q` | liste |
| `/admin/prospects/chercher` | GET | `q` | prospects et comptes qui ressemblent, bouton « Créer » |
| `/admin/prospects/nouveau` | GET | `nom` | formulaire |
| `/admin/prospects` | POST | champs de la fiche, `confirme` | 303 vers la fiche ; 200 avertissement de doublon ; 422 saisie refusée |
| `/admin/prospects/{id}` | GET | | fiche entière ou réduite selon le droit ; 404 si inconnue |
| `/admin/prospects/{id}/modifier` | GET | | formulaire ; 404 hors droit d'écriture |
| `/admin/prospects/{id}` | POST | champs de la fiche, `confirme` | 303 ; 404 hors droit d'écriture |
| `/admin/prospects/{id}/prendre` | POST | | 303 ; 422 plafond atteint ; 404 si la fiche n'est pas libre |
| `/admin/prospects/{id}/lacher` | POST | | 303 vers la liste ; 404 hors droit d'écriture |
| `/admin/prospects/{id}/etat` | POST | `etat` | 303 ; 404 |
| `/admin/prospects/{id}/pas` | POST | `texte`, `date` | 303 ; 422 ; 404 |
| `/admin/prospects/{id}/pas-fait` | POST | | 303 ; 404 |
| `/admin/prospects/{id}/note` | POST | `texte` | 303 ; 422 ; 404 |
| `/admin/prospects/{id}/demo` | POST | | 303 vers `/admin/artisans/{compte}/qr` ; 422 ; 404 |
| `/admin/prospects/{id}/fusion` | GET | `q` | fiches candidates (administrateur) |
| `/admin/prospects/{id}/fusion` | POST | `avec` | 303 vers la fiche gardée (administrateur) |

« Droit d'écriture » : être le suiveur en cours de la fiche. L'administrateur et le modérateur lisent tout et n'écrivent que sur leurs fiches.

### Structures

Table `prospect` (à créer) : `id`, `entreprise` VARCHAR(120), `nom_cle` VARCHAR(140), `npa` CHAR(4) NULL, `localite` VARCHAR(80), `metier` VARCHAR(20) NULL, `metier_saisi` VARCHAR(60) NULL, `telephone` VARCHAR(32) NULL, `telephone_cle` VARCHAR(16) NULL, `courriel` VARCHAR(190) NULL, `contact` VARCHAR(120) NULL, `origine` VARCHAR(16) NULL, `ide` CHAR(15) NULL UNIQUE, `etat` VARCHAR(16), `suivi_par` INT NULL, `suivi_depuis` DATETIME NULL, `suivi_avant` INT NULL, `libre_depuis` DATETIME NULL, `pas_texte` VARCHAR(120) NULL, `pas_le` DATE NULL, `derniere_action_le` DATETIME, `compte_id` INT NULL UNIQUE, `cree_par` INT NULL, `cree_le` DATETIME. Les quatre colonnes qui désignent un compte ont une clé étrangère vers `artisan`, `ON DELETE SET NULL`. Aucune colonne ne s'appelle `artisan_id` : la suppression d'une démo finie détache la fiche, elle ne l'efface pas.

Table `prospect_note` (à créer) : `id`, `prospect_id` (clé vers `prospect`, `ON DELETE CASCADE`), `auteur_id` INT NULL (clé vers `artisan`, `SET NULL`), `texte` VARCHAR(1000), `cree_le` DATETIME.

`Fiche` (tableau PHP rendu par le dépôt) : les colonnes ci-dessus, plus `suiveur` (nom du compte `suivi_par`), `avant` (nom de `suivi_avant`), `createur`, `echeance` (DATE : `pas_le` + 7 jours, à défaut jour de `derniere_action_le` + 7 jours), `tenue` (0 ou 1), et l'état du compte lié : `c_demo`, `c_demo_fin`, `c_offres_max`, `c_dictees`, `c_derniere`.

`tenue` = `suivi_par` non nul, et (un compte est lié, ou `echeance` >= aujourd'hui). Une fiche non tenue est libre. Rien n'écrit la libération : elle se calcule à la lecture, et « Je prends » la constate dans son `UPDATE`.

`FicheReduite` : `id`, `entreprise`, `localite`, `metier`, `metier_saisi`, `suiveur`, `suivi_depuis`, `derniere_action_le`, `echeance`, `compte` (démo, essai, client ou null). Aucun autre champ ne sort du service pour un lecteur sans droit.

### Flux

1. Saisie : `prospect_formulaire.html.twig` → `POST /admin/prospects` → `ProspectControleur::creer` → `Prospects::creer` (validation, clés, doublons, plafond) → `DepotProspects::inserer` → 303 vers la fiche.
2. Lecture : `GET /admin/prospects` → `ProspectControleur::liste` → `Prospects::liste(acteur, toutVoir, vue, q, aujourd'hui)` → `DepotProspects::lister` → pour chaque fiche, `Prospects::vue` rend la fiche entière ou réduite → `prospects.html.twig`.
3. Démo : `POST /admin/prospects/{id}/demo` → `Prospects::creerDemo` → `Compte\Demos::creer` (existant) → `DepotProspects::lierCompte` → 303 vers `/admin/artisans/{compte}/qr` (existant). L'état de la fiche se lit ensuite par la jointure sur `artisan`.

### Existant réutilisé

- `src/Donnees/Base.php`, `src/Donnees/Dates.php`, `src/Donnees/JournalAdmin.php` (gestes `prospect_fusionne`).
- `src/Compte/Demos.php::creer` et `Demos::courriel`, `src/Compte/DemandesAppel.php::telephone`, `src/Catalogue/CatalogueFictif.php::libelles`, `src/Catalogue/Recherche.php::normaliser`, `src/Compte/Role.php`.
- `src/Http/Garde.php` (`roleCourant`, `artisanCourant`), `src/Http/Flash.php`, `src/Http/PageIntrouvable.php`, `src/Http/Conteneur.php` (deux accesseurs à créer).
- `templates/admin/base.html.twig`, `templates/admin/_menu.html.twig` (entrée « Prospects » à ajouter pour les trois rôles), composants de `public/css/app.css`, `admin.css`, `suivi.css`.
- Tests : `tests/Outils/Jeu.php`, `Monde.php`, `Navigateur.php`, `BaseDeTest.php` (`vider` : deux tables à ajouter avant `artisan`).

## Signatures

```php
// src/Donnees/DepotProspects.php (à créer)
public function inserer(array $champs, ?int $suiviPar, int $creePar, \DateTimeImmutable $quand): int;
public function modifier(int $id, array $champs, \DateTimeImmutable $quand): void;
public function fiche(int $id, string $aujourdhui): ?array;                                   // Fiche|null
public function lister(string $vue, int $acteur, string $recherche, string $aujourdhui): array; // list<Fiche>, 200 au plus
public function compter(int $acteur, string $aujourdhui): array;                              // array{miens: int, libres: int, tous: int, retard: int, tenues: int}
public function ressemblants(array $cles, ?int $sauf): array;                                 // list<array{id, entreprise, localite, motif}>
public function comptesRessemblants(string $nom, ?string $courriel): array;                   // list<array{id, entreprise, sorte, apporteur}>
public function chercherComptes(string $recherche): array;                                    // list<array{id, entreprise, sorte, apporteur}>, 20 au plus
public function prendre(int $id, int $acteur, string $aujourdhui, \DateTimeImmutable $quand): bool;
public function lacher(int $id, int $acteur, \DateTimeImmutable $quand): bool;
public function changerEtat(int $id, string $etat, \DateTimeImmutable $quand): void;
public function poserPas(int $id, ?string $texte, ?string $date, \DateTimeImmutable $quand): void;
public function noter(int $id, int $auteur, string $texte, \DateTimeImmutable $quand): void;
public function notes(int $id): array;                                                        // list<array{texte, cree_le, auteur}>
public function lierCompte(int $id, int $compteId, \DateTimeImmutable $quand): void;
public function fusionner(int $garde, int $absorbee): void;

// src/Compte/Prospects.php (à créer)
public const ETATS, ORIGINES, PLAFOND = 30, JOURS_LIBRE = 7, JOURS_PAS = 30;
public static function cleNom(string $nom): string;
public static function cleTelephone(string $tel): string;
public static function ide(string $saisi): ?string;                    // @throws \InvalidArgumentException
public function creer(int $acteur, array $saisie, bool $confirme): int; // @throws DoublonProspect, \InvalidArgumentException, \OverflowException
public function modifier(int $acteur, int $id, array $saisie, bool $confirme): void;
public function vue(int $acteur, bool $toutVoir, array $fiche): array;  // array{entiere: bool, ecrit: bool, fiche: Fiche|FicheReduite}
public function prendre(int $acteur, int $id): void;                    // @throws \OverflowException, \DomainException
public function lacher(int $acteur, int $id): void;                     // @throws \DomainException
public function changerEtat(int $acteur, int $id, string $etat): void;
public function poserPas(int $acteur, int $id, string $texte, string $date): void;
public function pasFait(int $acteur, int $id): void;
public function noter(int $acteur, int $id, string $texte): void;
public function creerDemo(int $acteur, Role $role, int $id): int;
public function fusionner(int $acteur, int $garde, int $absorbee): void;

// src/Compte/DoublonProspect.php (à créer) : exception qui porte les fiches et comptes ressemblants, et dit si le refus est ferme (même IDE).
```

## Décisions fragiles

1. J'ai supposé que la libération peut rester un calcul à la lecture (aucune écriture, aucun cron). Si Xavier veut un courriel « votre prospect est libre », il faudra une tâche de nuit et le lot 2 gagne une colonne.
2. J'ai supposé qu'un commercial peut voir, en cherchant un nom, qu'un compte d'un collègue existe (nom, sorte, qui l'a amené). La règle R1 du 19.11 dit qu'il ne voit que ses artisans. Si Xavier refuse cette exception, la recherche et l'avertissement de doublon ne couvrent plus que ses comptes, et un client de Dicta peut être démarché. La liste « Tous » ne montre que des fiches de prospects, jamais la liste des comptes.
3. J'ai supposé que le numéro IDE se contrôle par sa forme seule (CHE et neuf chiffres). La clé de contrôle n'est pas vérifiée : je n'ai pas de numéro réel prouvé pour l'essayer.

## Lots

| Lot | Objectif | Fichiers | Test | Vérification |
|---|---|---|---|---|
| 0 Traceur | « Prospects » est au menu des trois rôles et ouvre une liste vide | `config/routes.php`, `src/Http/Controleur/ProspectControleur.php`, `templates/admin/_menu.html.twig`, `templates/admin/prospects.html.twig` | `tests/Http/DroitsEquipeTest.php` (existant : matrice des rôles, menu sans refus) | `vendor/bin/phpunit tests/Http/DroitsEquipeTest.php` vert |
| 1 Schéma | Les deux tables existent, additives | `migrations/Version20261124210001.php`, `tests/Outils/BaseDeTest.php` | `tests/Donnees/ColonnesProspectsTest.php` | migration jouée, test vert, `SuppressionDemosTest` vert |
| 2 Règles | Créer, doublons, prendre, plafond, libération, lâcher, état, pas, notes, droits | `src/Donnees/DepotProspects.php`, `src/Compte/Prospects.php`, `src/Compte/DoublonProspect.php`, `src/Http/Conteneur.php` | `tests/Compte/ProspectsTest.php` | test rouge avant, vert après |
| 3 Écrans | Liste, recherche, formulaire, doublon, fiche entière et réduite, gestes | contrôleur, routes, six gabarits, `public/css/prospects.css` | `tests/Http/ProspectsEcransTest.php` (dont : aucun champ masqué ne sort pour un collègue, par aucune route) | test vert |
| 4 Démo | « Créer une démo » lie le compte, l'état vient du compte | service, contrôleur, gabarit de la fiche | `ProspectsTest`, `ProspectsEcransTest` | tests verts |
| 5 Fusion | L'administrateur fusionne deux fiches | service, dépôt, contrôleur, `prospect_fusion.html.twig` | `ProspectsTest`, `ProspectsEcransTest` | tests verts |
| 6 Ordinateur et recette locale | Tableau, fiche à côté, parcours cliqué sur PC et téléphone | `prospects.css`, gabarits | parcours Playwright, captures regardées | suite complète et PHPStan verts |

Dépendances : chaque lot dépend du précédent. La migration (lot 1) alimente les préalables de `RECETTE.md`.
