# Plan du sous-chantier 25 : facturer en trois touchers, petits écrans sur PC

Source : `MAQUETTE-facturer.html`, réponses de Xavier du 2026-11-15 (E1 à E8, dans `ETAT.md`), `CODING_RULES.md`.
Feu vert de Xavier : « oui tu peux tout faire je te valide les ajouts et modifs ».

## Contrat technique

### Routes

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/offres/{id}/facturer` | GET | artisan de l'offre | `genre`, `pourcent`, `montant` (facultatifs) | la feuille Facturer (page seule sans JavaScript) |
| `/offres/{id}/facturer/apercu` | GET | idem | `genre`, `pourcent`, `montant`, `numero`, `date`, `echeance` | **à créer** : le document de la facture qui serait créée, marqué « Aperçu », et `data-a-payer` ; rien n'est écrit |
| `/offres/{id}/facturer` | POST | idem, CSRF | `genre`, `pourcent` ou `montant` (CHF TTC), `numero`, `date`, `echeance`, `suite=creer\|corriger` | `creer` : 303 vers `/factures/{id}/apercu` (facture émise) ; `corriger` (finale seulement) : 303 vers `/factures/{id}` (brouillon sans numéro) ; 422 : la feuille et ses erreurs, **rien en base** |
| `/factures/{id}` | GET | artisan | | brouillon : lignes, aperçu, « Créer la facture » ; émise ou annulée : **303 vers `/apercu`** |
| `/factures/{id}/document` | GET | artisan | | brouillon : le document (aperçu à côté) ; émise ou annulée : **303 vers `/apercu`** |
| `/factures/{id}/emettre` | GET | | | **retirée** (E3 : pas de page de confirmation) |
| `/factures/{id}/emettre` | POST | artisan, CSRF | `numero` (facultatif) | numéro attribué s'il manque, facture émise, 303 vers `/factures/{id}/apercu` |
| `/factures/{id}/apercu` | GET | artisan | | émise ou annulée : PDF.js, « Envoyer », panneau (montant, échéance, client), « Annuler la facture » |
| `/offres/{id}/client` | POST | artisan, CSRF | `client` ou `creer=1`, `suite=apercu` (facultatif) | `suite=apercu` : 303 vers `/offres/{id}/apercu` (E6) |

### Structures

- `facture.numero` : `VARCHAR NULL` (migration). NULL = brouillon pas encore créé ; l'index unique
  `(artisan_id, numero)` laisse passer plusieurs NULL (MariaDB).
- Acompte (E4) : `acompte_pourcent int|null` (null quand l'artisan tape un montant) ; `acompte_rang` = acomptes
  non annulés + 1 ; `acompte_nombre` = nombre de tranches des conditions, ou null.
- Montant tapé : centimes TTC (`int`), lu par `Saisie::centimes`.

### Flux, de bout en bout

1. Offre → « Facturer » (`data-en-feuille`) → `fichesEnFenetre` charge `GET /facturer` dans la feuille.
2. Toucher 20/30/40/50 % ou taper un montant → `app.js` (à créer : `feuilleFacturer`) recharge
   `GET /facturer/apercu?…` → remplace la miniature et le texte du bouton (« Créer la facture · CHF 932.05 »).
3. « Créer la facture » → `POST /facturer` (`suite=creer`) → `FactureControleur::creer` : `PlanAcomptes` calcule les
   lignes → `DepotFactures::creer` (numéro tapé ou proposé) → `emettreFacture` (PDF gardé) → 303 `/apercu`.
   Échec d'émission : le brouillon est effacé, la feuille revient en 422 avec la raison.
4. La feuille suit la redirection hors de l'offre : le formulaire porte `data-sortir`, `app.js` navigue vers
   `r.url` au lieu d'afficher la page dans la feuille (**à créer** dans `fichesEnFenetre`).
5. `/apercu` → `apercu_pdf.html.twig` → `apercu-pdf.js` (PDF.js) ; « Envoyer » → partage (existant, D8).

### Existant réutilisé

`FactureControleur::creer`, `emettreFacture`, `donnees`, `vueDocument` ; `DepotFactures::creer`, `emettre`,
`numeroPris`, `dernierNumero` ; `PlanAcomptes::parts`, `designation` ; `Totaux::calculer`, `parTaux` ;
`Numerotation::suivant` ; `templates/_document.html.twig` ; `_feuille.html.twig` et `fichesEnFenetre` (app.js) ;
`apercu_pdf.html.twig` et `public/js/apercu-pdf.js` ; `ClientControleur::choisirPourOffre`.

## Signatures nouvelles ou changées

```php
// src/Facture/PlanAcomptes.php
/** @return list<array{taux: int|null, ht: int}> parts HT dont le TTC calculé tombe sur $ttcVoulu, au centime près si possible */
public static function partsPourMontant(array $parTaux, int $ttcVoulu, int $tauxEntreprise, bool $arrondiCinq): array;
public static function designation(int $rang, ?int $nombre, ?int $pourcent, int $numeroOffre): string;
/** Les quatre pourcentages proposés, plus celui des conditions s'il n'y est pas. @return list<int> */
public static function pourcentages(?int $deConditions): array;

// src/Donnees/DepotFactures.php
/** @param Entete $e avec numero: string|null */
public function creer(array $e, array $lignes, \DateTimeImmutable $quand): int;
/** Donne son numéro à un brouillon qui n'en a pas ; faux si pris ou si ce n'est plus un brouillon. */
public function numeroter(int $id, string $numero): bool;
public function dernierNumero(): ?string; // ignore les NULL, ordonne par COALESCE(emise_le, cree_le)

// src/Http/Controleur/FactureControleur.php
public function apercuNouvelle(Request $requete, array $parametres): Response;
/** Le genre, le pourcentage ou le montant lus, et les lignes à copier ; erreurs par champ. */
private function lireFacturer(Request $requete, array $offre, array $champs): array;
```

Colonne changée : `facture.numero VARCHAR(20) NULL`.

## Décisions fragiles

- J'ai supposé que l'aperçu avant création peut être le **document HTML** (même gabarit `_document.html.twig` que le
  PDF) marqué « Aperçu ». Si Xavier veut aussi le PDF à ce moment-là, le lot 3 est à reprendre (rendu dompdf à
  chaque changement : une seconde d'attente par toucher).
- J'ai supposé qu'un montant tapé en CHF TTC se répartit entre les taux de TVA de l'offre **au prorata de leur HT**.
  Sur une offre à un seul taux (le cas courant) le problème n'existe pas ; sur une offre à deux taux, si un
  comptable veut une autre répartition, `partsPourMontant` est à refaire.
- J'ai supposé que le numéro d'un brouillon se décide **à la création** (`numeroter`, au moment de « Créer la
  facture ») et que la date reste celle du brouillon. Si une facture créée trois jours après son brouillon doit
  prendre la date du jour, il faut l'écrire dans `emettreFacture` (une ligne, mais un test de plus).

## Lots

Chaque lot : tests écrits avant et vus rouges, code, suite complète (`env APP_ENV=test vendor/bin/phpunit`),
`vendor/bin/phpstan analyse --no-progress`, parcours cliqué à 390 et 1440 px, captures relues, commit sur
`chantier-voix-offre`. Déploiement sur le test et sonde verte après les lots 2, 5 et 7.

| # | Objectif | Fichiers | Test écrit dans le lot | Vérification | Dépend |
|---|---|---|---|---|---|
| 1 | **Tablette (416)** : la version PC demande une souris (`(hover: hover) and (pointer: fine)`) en plus de 1024 px | `public/css/app.css`, `public/js/app.js` (deux `matchMedia`) | `tests/Http/VersionPcTest.php` : les deux requêtes média de app.css et les deux de app.js portent la condition de pointeur | phpunit vert ; Playwright 1180×820 `hasTouch` sans souris : barre du bas visible ; 1440 avec souris : menu à gauche | |
| 2 | **Numéro à la création (418, données)** : migration `numero NULL` ; `creer` accepte null, `numeroter`, `dernierNumero` ignore les NULL | `migrations/Version20261115000003.php`, `src/Donnees/DepotFactures.php` | `tests/Donnees/DepotFacturesTest.php` : brouillon sans numéro, deux brouillons sans numéro, `numeroter` refuse un pris, suivant après un brouillon sans numéro | phpunit vert ; `bin/console migrations:migrate` sur la base locale, `SHOW COLUMNS FROM facture LIKE 'numero'` → `YES` | |
| 3 | **Acompte libre (417, calcul)** : 20/30/40/50 % ou montant TTC ; désignation sans « sur N » quand le pourcentage sort des conditions | `src/Facture/PlanAcomptes.php` | `tests/Facture/PlanAcomptesTest.php` : 1000.00 TTC à 8,1 % donne 1000.00 ; 932.05 ; deux taux ; montant plus grand que l'offre refusé en amont ; désignations | phpunit vert | |
| 4 | **Facturer en une feuille (417)** : `GET /facturer` redessiné (puces %, CHF, miniature, ligne numéro · date · échéance « Modifier », bouton « Créer la facture · CHF … », phrase dessous) ; `GET /facturer/apercu` ; `POST` `suite=creer\|corriger` ; rien en base sur 422 ni sur échec d'émission ; « Vérifier avant d'émettre » retiré | `FactureControleur.php`, `config/routes.php`, `templates/facturer.html.twig`, `templates/facture_apercu_nouvelle.html.twig` (à créer), `public/js/app.js` (`feuilleFacturer`, `data-sortir`), `app.css` | `tests/Http/EnvoyerFacturerTest.php` réécrit : 3 requêtes (GET feuille, POST creer, GET apercu) donnent une facture émise ; montant CHF ; aperçu n'écrit rien (`COUNT(*)` inchangé) ; 422 n'écrit rien ; « Vérifier » absent ; `corriger` refusé sur un acompte | phpunit vert ; Playwright : offre → Facturer → Créer la facture → Envoyer = 3 clics comptés | 2, 3 |
| 5 | **Facture toujours en PDF (418, écran)** : `/factures/{id}` et `/document` d'une facture émise → `/apercu` ; page de confirmation retirée ; brouillon : « Créer la facture » (POST `/emettre`), numéro « à la création » ; PDF : panneau sur PC, « Annuler la facture » ; liste des factures et étiquettes de l'offre pointent vers `/apercu` | `FactureControleur.php`, `routes.php`, `facture.html.twig`, `apercu_pdf.html.twig`, `factures.html.twig`, `offre.html.twig`, `facture_annuler.html.twig`, `app.css` ; `facture_emettre.html.twig` supprimé | `tests/Http/FacturesEcranTest.php`, `FactureQrTest.php` mis à jour : 303 vers `/apercu`, GET `/emettre` 404, émettre un brouillon sans numéro lui en donne un, annuler depuis le PDF | phpunit vert ; captures du brouillon et du PDF à 390 et 1440 | 2, 4 |
| 6 | **Client confirmé avant l'offre (419)** : étiquette orange « à confirmer » et loupe sur la carte ; « Générer l'offre » sans client confirmé ouvre « Pour qui est cette offre ? » dans la feuille, le choix mène au PDF | `offre.html.twig`, `offre_client.html.twig`, `ClientControleur.php`, `app.css` | `tests/Http/EcransTest.php` (ou `ClientsEcranTest.php`) : bouton « Générer l'offre » vers `/client?suite=apercu` avec `data-en-feuille` quand `client_id` est null ; POST `suite=apercu` → 303 `/offres/{id}/apercu` | phpunit vert ; Playwright cas 2 de la maquette : 3 touchers jusqu'au PDF | 4 (`data-sortir`) |
| 7 | **Ajouter et petits écrans sur PC (420)** : « + » ouvre Ajouter dans la feuille, qui reste ouverte après un ajout (la ligne apparaît derrière) ; version sobre (E7) : un champ, une liste nue, « Ajouter « texte » » en dernière proposition ; sur PC, une ligne de recherche au bas du tableau, Entrée ajoute, le focus va sur la quantité ; menu de gauche sur tous les écrans (ligne, objet, client, nouveau client, facture, brouillon) ; petites fenêtres au milieu | `ajout.html.twig`, `LigneControleur.php`, `_lignes.html.twig`, `offre.html.twig`, `facture.html.twig`, `ligne.html.twig`, `offre_entete.html.twig`, `client.html.twig`, `base.html.twig`, `_onglets.html.twig`, `app.js`, `app.css` | `tests/Http/VersionPcTest.php` : chaque petit écran rend le menu (`nav class="tabs`) ; `EcransTest.php` : ajout d'un produit avec `reste=1` revient à `/ajouter` ; ligne libre préremplie | phpunit vert ; Playwright PC : taper « regie », Entrée, focus sur la quantité ; téléphone : deux ajouts sans fermer la feuille | 1 |
| 8 | **Revue et recette (421)** : revue du diff (une lentille correction, une sécurité : routes et CSRF), démo remise à zéro, `RECETTE.md` avec captures | `REVUE.md`, `RECETTE.md` | | sonde verte sur le test, parcours de la recette joué à 390 et 1440 | 1 à 7 |

## Hors plan

- Rendre la version PC plus belle : noté pour plus tard (Xavier, 2026-11-15).
- Lot 9 du sous-chantier 24 : après celui-ci.
