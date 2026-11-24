# PLAN : documents et réglages

Entrées : `SPEC.md`, réponses de Xavier à `MAQUETTE-documents-reglages.html` (décisions L0 à V3 dans `ETAT.md`).
Code écrit dans la session principale, aucune délégation. Pas de migration.

## Contrat technique

Le chantier touche le serveur (contrôleurs, dépôt), les gabarits et `app.js` / `fluide.js` (lot 3). Pas de lot 0 traceur : le flux reste un formulaire HTML rendu par le serveur et envoyé en POST ; le JavaScript ne fait que recopier une valeur et dessiner la ligne provisoire hors réseau. Décision tracée dans `ETAT.md`.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/reglages/phrases` | GET | ARTISAN (boucle de `config/routes.php:269` sur `RUBRIQUES`) | rien | page `reglages_rubrique.html.twig`, section `phrases` |
| `/reglages` | POST | ARTISAN (existant) | champs `lettre_*`, `phrase_*`, `retour=/reglages/phrases` | 303 vers `/reglages/phrases`, ou JSON `{ok}` (existant, `ReglagesControleur::enregistrer`) |
| `/offres/{id}/ajouter` | GET | ARTISAN (existant) | `nature`, `remplace`, **`quantite`** (nouveau, facultatif, texte comme « 42 » ou « 12.5 ») | page d'ajout ; la quantité reprise préremplie |
| `/offres/{id}/lignes` | POST | ARTISAN (existant) | `nature=variante`, `remplace`, `produit` ou ligne libre, **`quantite`** (désormais lu aussi pour un produit en variante), `unite` (ligne libre) | 303 (existant) |

### Structures

- Champ `quantite` d'un ajout en variante : texte saisi, lu par `Saisie::milliemes` ; vide ou invalide pour un produit : la quantité de la ligne remplacée.
- Ligne ajoutée en variante d'un produit : `quantite_milliemes` = saisie ou base, `unite` = `base.unite`, `prix_centimes` = prix du produit, `a_verifier` = 1 si `produit.unite !== base.unite`.
- Provisoire hors réseau (`fluide.js`) : `{designation: string, unite: string, prix: int (centimes), quantite: int (millièmes, 1000 par défaut)}`.

### Flux

1. Phrases : `ReglagesControleur::rubrique('phrases')` → `page()` (déjà `tournures` et `phrases` pour toute section) → `reglages_rubrique.html.twig` inclut `_reglages_phrases.html.twig` (à créer) → `app.js` `tournuresLettre`, `pastillesPhrases`, enregistrement automatique (sélecteurs sur tout le document, vérifié `app.js:1564-1620`) → POST `/reglages` → `sauver()` n'écrit que les champs reçus (`ReglagesControleur.php:248-251`, vérifié).
2. Variante : `LigneControleur::pageAjout` (base par `natureDeLAjout`, ligne 216) → `ajout.html.twig` : champ « Quantité » dans le formulaire « Elle remplace » (GET), champ caché `quantite` dans chaque formulaire d'ajout → `app.js` recopie la saisie dans les champs cachés (attribut `data-quantite-reprise`) → POST `/offres/{id}/lignes` → `LigneControleur::ajouter` (ligne 227) → `DepotOffres::ajouterLigne` (ligne 478) → `poserNature` → `DepotOffres::marquerAVerifier` (à créer) si les unités diffèrent → `RelectureControleur` (ligne 96) la montre dans « Relire ».
3. Hors réseau : `app.js` `envoyerEnMorceaux` (ligne 975-980) lit `quantite` et `unite` du formulaire pour une variante → `fluide.ligneEnAttente` (ligne 551) les affiche.

### Existant réutilisé

`ReglagesControleur::RUBRIQUES`, `page()`, `sauver()` ; `PhrasesLettre::DOCUMENT`, `estReecrite()`, `partout` ; `LigneControleur::natureDeLAjout`, `bases`, `poserNature` ; `Saisie::milliemes`, `Saisie::unite`, `Formats::saisieQuantite` ; `DepotOffres::ajouterLigne`, `changerNature` ; `RelectureControleur`.

## Signatures

```php
// src/Donnees/DepotOffres.php : la ligne passe dans « Relire ». Faux si elle n'est pas à l'artisan.
public function marquerAVerifier(int $offreId, int $ligneId): bool

// src/Http/Controleur/ReglagesControleur.php
public const RUBRIQUES = ['entreprise' => …, 'marque' => …, 'phrases' => 'Phrases du document', 'conditions' => …, 'tva' => …];
// Résumé du sommaire : « Dans Lettre seulement » ou « Dans les douze modèles », puis « · N phrase(s) réécrite(s) » si N > 0.
private static function resumePhrases(PhrasesLettre $p): string
```

```js
// public/js/fluide.js
const ligneEnAttente = (designation, unite, prixCentimes, quantiteMilliemes = 1000) => { … }
```

## Décisions fragiles

1. J'ai supposé qu'une unité de ligne remplacée hors de la liste des unités (`unites` de la page d'ajout) peut arriver (ligne dictée). Je l'ajoute comme option du menu de la ligne libre plutôt que de retomber sur « forfait ». Si `Saisie::unite` la refuse à l'envoi, V3 casse pour ces lignes : le test du lot 3 doit le couvrir.
2. J'ai supposé que « Relire » suffit comme garde de V1 (la ligne au prix de la pièce sur 42 m² se relit avant l'envoi). Si « Envoyer » ne passe pas par « Relire » quand une ligne est à vérifier, le total faux peut partir : à vérifier au lot 3 par l'interface.
3. J'ai supposé que la rubrique « phrases » compte dans le compteur du sommaire (« 4 rubriques sur 5 complètes », toujours complète). Si Xavier trouve le compteur trompeur, la retirer du compte est une ligne.

## Lots

### Lot 1 : filigrane retiré (O1)

| | |
|---|---|
| Objectif | Ni la vignette de « Facturer » ni la page d'un brouillon ne portent le filigrane « Aperçu » ; la légende « Aperçu : rien n'est encore créé » reste. |
| Fichiers | `templates/_apercu_facture.html.twig`, `templates/facture_document.html.twig`, `public/css/app.css` (règle `.filigrane`), commentaires `src/Http/Controleur/FactureControleur.php:648`, `public/js/app.js:2195` |
| Test | `tests/Http/FiligraneTest.php` : aperçu de `/offres/{id}/facturer/apercu` et `/factures/{id}/document` d'un brouillon sans `class="filigrane"` ; la légende présente sur `/offres/{id}/facturer`. Rouge avant. |
| Vérification | `DB_NOM=offregen_documents_test vendor/bin/phpunit tests/Http/FiligraneTest.php tests/Http/EnvoyerFacturerTest.php` : OK |
| Dépend de | rien |

### Lot 2 : rubrique « Phrases du document » (L1, L2)

| | |
|---|---|
| Objectif | Ma marque montre logo, couleur, modèle ; la section des phrases vit dans `/reglages/phrases`, au sommaire sous Ma marque ; l'étape « marque » de l'assistant n'a plus les phrases. |
| Fichiers | `src/Http/Controleur/ReglagesControleur.php` (`RUBRIQUES`, `afficher`), `templates/_reglages_marque.html.twig` (section retirée), `templates/_reglages_phrases.html.twig` (à créer), `templates/reglages.html.twig` (icône), `src/Compte/PhrasesLettre.php` (commentaire « dans Ma marque »), tests touchés : `tests/Http/ReglagesRangesTest.php`, `tests/Http/PhrasesLettreEcranTest.php`, `tests/Http/MarqueEcranTest.php` |
| Test | `tests/Http/PhrasesRubriqueTest.php` : `/reglages/marque` sans `phrases-doc` ; `/reglages/phrases` avec `phrases-doc`, `lettre_offre_debut`, 14 `phrase_` ; sommaire avec `href="/reglages/phrases"` et son résumé ; `/bienvenue/marque` sans `phrases-doc` ; un POST de Ma marque sans champs de phrases garde une phrase réécrite. Rouge avant. |
| Vérification | suite complète `DB_NOM=offregen_documents_test vendor/bin/phpunit` OK, `vendor/bin/phpstan analyse` sans erreur ; parcours Playwright local : Ma marque et la rubrique au téléphone, capture regardée, hauteur de Ma marque mesurée. |
| Dépend de | rien |

### Lot 3 : variantes, quantité et unité reprises (V1, V2, V3)

| | |
|---|---|
| Objectif | Les quatre cas du tableau de la maquette donnent la colonne « Avec ta règle » ; la quantité se voit et se change avant l'ajout ; un produit d'une autre unité passe dans « Relire ». |
| Fichiers | `src/Http/Controleur/LigneControleur.php` (`pageAjout`, `ajouter`), `src/Donnees/DepotOffres.php` (`marquerAVerifier`), `templates/ajout.html.twig`, `public/js/app.js` (recopie, provisoire), `public/js/fluide.js` (`ligneEnAttente`), `public/css/app.css` si besoin |
| Test | `tests/Http/VarianteQuantiteTest.php` : produit de même unité → base ; produit d'une autre unité → quantité et unité de la base, prix du produit, `a_verifier = 1` ; quantité tapée (« 30 ») → 30 ; ligne libre rapide → unité de la base ; ligne libre détaillée sans quantité → base ; page d'ajout : champ `quantite` prérempli, unité de la base sélectionnée, mention « à relire » sur un produit d'une autre unité ; un ajout hors variante inchangé (quantité 1). Rouge avant. |
| Vérification | suite complète OK, phpstan OK, `node --check` sur `app.js` et `fluide.js` ; parcours Playwright local par l'interface : les quatre cas, puis « Relire » montre la ligne d'une autre unité. |
| Dépend de | rien |

## Après les lots

Revue (phase 7) : surfaces sensibles touchées = aucune (pas de droit, pas de donnée d'un autre client, pas d'appel au modèle) ; revue simple, une lentille correction. Puis commit par chemins, fetch, rebase, push et déploiement sur le test à l'accord de Xavier, recette (`RECETTE.md`).
