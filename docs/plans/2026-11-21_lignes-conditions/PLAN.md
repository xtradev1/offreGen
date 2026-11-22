# PLAN : lignes et conditions

Entrées : `SPEC.md`, maquette `MAQUETTE-lignes-conditions-v3.html`. Base de code : `origin/chantier-voix-offre` 77954ce.
Tests sur la base à soi `offregen_lignes_test` (`.env.test.local` de `~/offreGen-lignes`).

## Lot 1 : ligne offerte, ouverture au toucher, plus de « ? »

### Contrat technique

| Maillon | Chemin | État |
|---|---|---|
| Colonne `offerte TINYINT(1) NOT NULL DEFAULT 0` sur `ligne` et `facture_ligne` | `migrations/Version20261122000001.php` | à créer |
| Lecture : `l.offerte` dans les colonnes des deux dépôts | `src/Donnees/DepotOffres.php` (`COLONNES_LIGNE`), `src/Donnees/DepotFactures.php` (`COLONNES_LIGNE`) | à modifier |
| Écriture : `changerOfferte` | `src/Donnees/SupportLignes.php`, les deux dépôts | à créer |
| Copie vers la facture finale | `FactureControleur::lireFacturer` (ligne 300 env.) puis `DepotFactures::creer` (`INSERT INTO facture_ligne`) | à modifier |
| Règle « offerte après une saisie », « prix manquant » | `src/Offre/Offerte.php` | à créer |
| Calcul : une ligne offerte compte 0 | `src/Offre/Totaux.php` (`calculer`, `parTaux`) | à modifier |
| Saisie : liste (champ `prix` seul), fenêtre de la ligne (`prix` + `offerte`) | `LigneControleur::corriger` | à modifier |
| Relecture : 0 accepté, rend la ligne offerte | `RelectureControleur::valider`, `aRelire`, `champs` | à modifier |
| Compte « à relire » et « manque » | `OffreControleur` (lignes 235-236), `FactureControleur` (752), `templates/offre.html.twig` (2 boucles) | à modifier |
| Écran des lignes : tag Offerte, « Offert », prix barré, placeholder « Prix » | `templates/_lignes.html.twig`, `public/css/app.css` | à modifier |
| Fenêtre de la ligne : « Facturée / Offerte », aide | `templates/ligne.html.twig` | à modifier |
| Toucher la ligne l'ouvre | `public/js/app.js` (clic délégué sur `[data-glisse-ligne]`, à côté de `glisserLignes`) | à créer |
| Document : prix barré + « Offert », « Offert », case vide | `templates/document/_blocs.html.twig` (deux tableaux) | à modifier |

Flux : l'artisan tape 0 (liste) → `app.js` envoie `prix=0` → `LigneControleur::corriger` → `Offerte::apresSaisie`
→ `corrigerLigne` + `changerOfferte` → réponse HTML de l'offre → `.rows` et `.totals` remplacés → `Totaux`
compte 0 → le document lit `l.offerte`.

### Signatures

```php
// src/Offre/Offerte.php
public static function prixManquant(array $ligne): bool;           // prix 0 et pas offerte
public static function apresSaisie(bool $avant, int $prixAvant, ?string $prixTape, int $prixApres, ?string $choix): bool;
// src/Donnees/SupportLignes.php
public function changerOfferte(int $parentId, int $ligneId, bool $offerte): bool;
```

### Décisions fragiles

- J'ai supposé qu'une colonne `offerte` vaut mieux qu'un prix NULL : prix 0 sans `offerte` reste « manquant ».
  Si Xavier veut distinguer « prix inconnu » de « prix 0 facturé », il faudra le NULL et vingt fichiers de plus.
- J'ai supposé qu'un vrai prix tapé sur une ligne offerte à 0 la refacture, et qu'un prix changé sur une ligne
  offerte barrée la laisse offerte.
- J'ai supposé que toucher la quantité, le prix, une note ou la corbeille ne doit pas ouvrir la ligne (zones de
  saisie) ; seul le reste de la ligne l'ouvre.

### Tests (écrits dans ce lot)

- `tests/Offre/OfferteTest.php` : `apresSaisie` (liste et fenêtre), `prixManquant`.
- `tests/Offre/TotauxTest.php` : ligne offerte comptée 0, TVA par taux sans elle.
- Test HTTP de la fenêtre de la ligne : « Offerte » enregistrée, prix gardé ; 0 dans la liste rend offerte ; vide
  rend manquant ; facture finale copiée avec `offerte`.
- Vérification : `vendor/bin/phpunit` vert sur `offregen_lignes_test`, puis parcours Playwright sur le test.

## Lots suivants (à détailler à leur tour)

| Lot | Objet | Dépend de |
|---|---|---|
| 2 | Rabais sous le sous-total (table `offre_rabais`), calcul, document, facture finale | 1 |
| 3 | Textes avant et après, validité, paiement, escompte propres à l'offre ; Lettre (D6, D16) | 1 |
| 4 | Représenté par : fiche, offre et factures adressées | 1 |
| 5 | Libellé d'acompte libre ; acomptes listés sur la facture finale (vérifier l'existant) | 1 |
| 6 | Escompte en mention sur chaque facture, QR au montant plein | 3, 5 |
| 7 | Bandeau « Modifiée depuis l'envoi · Renvoyer » | 1 |
| 8 | Revue, recette sur le test | tous |
