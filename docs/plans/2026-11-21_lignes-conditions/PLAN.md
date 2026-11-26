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

## Lots 2 à 7 (codés le 2026-11-22)

| Lot | Objet | Code | Tests |
|---|---|---|---|
| 2 | Rabais sous le sous-total, % ou CHF, cinq au plus | `src/Offre/Rabais.php`, `Totaux` (`rabais`, `repartir`), `RabaisControleur`, `templates/rabais.html.twig`, migration `…0002` (`offre.rabais`, `facture.rabais`) | `RabaisTest`, `TotauxTest`, `RabaisEcranTest` |
| 3 | Conditions et textes propres à l'offre ; escompte du compte | `src/Compte/Conditions.php`, `Reglages::pourOffre`, `ConditionsControleur`, `conditions_offre.html.twig`, macro `texte` des 12 modèles, migration `…0003` | `ConditionsTest`, `ConditionsOffreTest` |
| 4 | Représenté par | `RepresentantControleur`, `representant.html.twig`, `_base.html.twig` (mention), migration `…0004` | `RepresentantTest` |
| 5 | Libellé d'acompte libre ; la finale liste chaque acompte | `FactureControleur::lireFacturer`, `DepotFactures::acomptesEmis`, `facturer.html.twig`, migration `…0005` | `LibelleAcompteTest`, `FacturesEcranTest` (mis à jour) |
| 6 | Escompte en mention sur chaque facture, QR au montant plein | `Totaux::escompte`, `FactureControleur::mentionEscompte`, migration `…0006` | `EscompteFactureTest` |
| 7 | Bandeau « Modifiée depuis l'envoi · Renvoyer » | `offre.html.twig`, `app.js` (corrections) | `RenvoiOffreTest` |
| 8 | Revue, recette sur le test | `REVUE.md` | |

### Décisions fragiles des lots 2 à 7

- Rabais et conditions en JSON sur l'offre (pas de table) : toujours lus et écrits avec leur offre, la portée de l'artisan est celle de l'offre. Un rabais se désigne par sa place (1 à 5) : deux fenêtres ouvertes en même temps sur la même offre peuvent modifier le mauvais.
- Chaque rabais se compte sur le même sous-total (pas en cascade), comme la maquette v3 ; réparti entre les taux de TVA au prorata, le reste d'arrondi au plus gros.
- Une offre sans conditions à elle suit le compte, comme avant (une offre envoyée change si le compte change). Enregistrées dans la fenêtre, elles sont figées pour l'offre.
- L'escompte n'existait nulle part : ajouté aussi aux réglages du compte (rubrique Conditions), pour « remplies avec les réglages » (D5 A).
- Libellé d'acompte : le champ est vide, celui des conditions paraît en indice ; vide, c'est lui. Écart à « prérempli » (D11 A) : un champ prérempli ne suivrait pas un changement de pourcentage.
- Factures d'une offre représentée : « p.a. » le représentant par défaut (D10 C laisse le choix).
- Escompte : « si payé jusqu'au » = date de la facture + N jours ; montant aux 5 centimes si le compte arrondit ainsi.
- D18 non tranchée par Xavier : une offre baissée (rabais, ligne offerte) sous les acomptes déjà facturés donne une finale à payer négative, que l'émission refuse déjà avec la raison (`FactureControleur::raisonsDeNePasEmettre`, code d'avant le chantier). C'est l'option « refus » du prototype ; la note de crédit n'est pas faite.
