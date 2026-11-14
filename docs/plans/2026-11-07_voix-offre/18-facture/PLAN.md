# Plan du sous-chantier 18 : la facture

Réponses de Xavier à la maquette, 2026-11-13 : facture et section de paiement « Valider » ; A, une facture
par acompte ; B, figée une fois émise (« si son offre est envoyée il doit s'y tenir donc la facture devrait
être figée ? ») ; C, un onglet Factures, « mais un lien depuis l'offre (voir la facture) » ; D, 2026-001 ;
points réglés « D'accord ». bcmath existe en production (Xavier, 2026-11-13).

## Hors de ce sous-chantier

- Envoi par courriel (19), suivi des paiements (« payée »), rappels, note de crédit.
- Factures sur la fiche du client.
- Figer l'offre une fois envoyée : soulevé par Xavier en répondant à B, à trancher à part.

## Contrat technique

### Données (migration 9)

| Table | Colonnes | Règle |
|---|---|---|
| `artisan` | `iban VARCHAR(21) NULL` | forme compacte, majuscules ; IBAN suisse ou liechtensteinois seulement (la QR-facture n'en accepte pas d'autre) |
| `facture` | `artisan_id`, `offre_id`, `numero VARCHAR(20)`, `genre` (`acompte` ou `finale`), `acompte_rang`, `acompte_nombre`, `acompte_pourcent`, `statut` (`brouillon`, `emise`, `annulee`), `date_facture`, `echeance`, `titre`, `client_nom`, `client_adresse`, puis, remplis à l'émission : `tva_pour_dix_mille`, `arrondi_cinq`, `iban`, `reference`, `ht`, `tva`, `arrondi`, `ttc`, `deja_facture`, `a_payer` ; `cree_le`, `emise_le`, `annulee_le`, `refaite_depuis` | index unique (`artisan_id`, `numero`) ; clé étrangère vers `offre` |
| `facture_ligne` | mêmes colonnes que `ligne` sans `a_verifier` ni `entendu` | copiées de l'offre à la création, corrigeables en brouillon seulement |
| `facture_pdf` | `facture_id` (clé), `artisan_id`, `contenu MEDIUMBLOB`, `cree_le` | le PDF émis, servi tel quel ensuite |

### Règles

- **Brouillon** : lignes, numéro, date et échéance modifiables ; TVA et arrondi lus dans les réglages, comme
  l'offre. Aucun PDF de brouillon : on ne paie pas un brouillon.
- **Émettre** (confirmation) : fige TVA, arrondi, IBAN, référence et montants dans `facture`, rend le PDF
  et le garde dans `facture_pdf`. Le PDF d'une facture émise ou annulée vient toujours de là : changer
  de logo, d'adresse ou de taux ensuite ne le change pas.
- **Annuler** une facture émise : elle reste dans la liste, barrée, son numéro n'est jamais repris.
  « Annuler et refaire » crée aussitôt un brouillon copié, au numéro suivant (`refaite_depuis`).
- **Supprimer** un brouillon : effacé pour de bon, son numéro se libère.
- Toute écriture sur une facture émise ou annulée répond 409, sauf « Annuler ».
- **Numéro** : proposé à la création, modifiable en brouillon, 1 à 20 caractères `A-Z a-z 0-9 - / . _`,
  unique chez l'artisan. Le suivant part du dernier créé : `2026-007` donne `2026-008` ; un numéro d'une
  autre année repart à `AAAA-001` ; un numéro libre qui finit par des chiffres les augmente (`F-0099`
  donne `F-0100`) ; sinon `AAAA-001`. On saute un numéro déjà pris.
- **Acomptes** (réglage « acomptes » du compte, par exemple 30 / 30 / 40) : « Facturer » propose
  l'acompte suivant tant qu'il en reste avant la dernière tranche, puis la facture finale. Un acompte
  porte une ligne « Acompte 1 sur 3 (30 %), selon offre n° 2 » au prix de 30 % du HT de l'offre ce jour-là.
  La finale reprend toutes les lignes de l'offre, puis déduit le TTC des acomptes émis de la même offre :
  « Déjà facturé », puis « À payer ». Sans réglage d'acomptes : seulement la finale. Une seule finale non
  annulée par offre.
- **Échéance** : date de facture plus N jours (« net à N jours » et « à N jours ») ; 30 jours pour un
  acompte ; modifiable en brouillon.
- **Client** : nom et adresse copiés de l'offre à la création ; une offre sans client ne se facture pas
  (lien vers « Client de l'offre »).
- **QR-facture** : charge utile et validation par sprain/swiss-qr-bill 5.3, dessin par bacon (sans GD),
  niveau M, croix suisse en SVG, 174 unités pour 46 mm (décision du 2026-11-13). **Adresses structurées
  (type S) seulement** : le type K n'est plus permis à l'émission depuis le 21.11.2025 (SIX). L'adresse
  de l'artisan se découpe en rue, numéro, NPA, localité ; si elle ne se découpe pas, pas de section de
  paiement et un message dans les réglages. L'adresse du client qui ne se découpe pas est omise
  (« Payable par » reste un cadre vide, permis par la norme). Référence : QR-IBAN donne une référence QR
  de 27 chiffres tirée de l'identifiant de la facture ; IBAN ordinaire donne une référence RF tirée du
  numéro (lettres et chiffres). Informations supplémentaires : « Facture n° … ».
- **Mise en page du PDF** : un premier rendu réserve 105 mm en bas ; s'il tient sur une page, la section
  de paiement va au bas de la page 1, sinon sur une page à part (décision de Xavier du 2026-11-12).
- **Isolation** : chaque route à paramètre a son cas dans `IsolationRoutesTest`, lecture et écriture.

### Écrans et routes

| Écran | Routes |
|---|---|
| Onglet Factures (troisième), liste avec recherche | `GET /factures` |
| Aperçu de l'offre : « Facturer » à côté de « PDF » ; offre : liens « Facture 2026-001 » | existantes |
| Nouvelle facture (numéro, date, échéance, genre proposé) | `GET /offres/{id}/facturer`, `POST /offres/{id}/facturer` |
| Facture : lignes corrigeables en brouillon, totaux, déjà facturé, à payer | `GET /factures/{id}` ; lignes : `/factures/{id}/lignes/...` et `/factures/{id}/ajouter`, mêmes gestes que l'offre |
| En-tête (numéro, date, échéance) | `GET` et `POST /factures/{id}/modifier` |
| Aperçu, puis « Émettre et PDF » | `GET /factures/{id}/document`, `POST /factures/{id}/emettre`, `GET /factures/{id}/pdf` |
| Annuler, annuler et refaire, supprimer le brouillon | `POST /factures/{id}/annuler`, `POST /factures/{id}/supprimer` |
| IBAN dans « Mon entreprise » | `POST /reglages` (existante) |

`LigneControleur` sert les deux : il reçoit sa cible (offre ou facture brouillon), qui donne le dépôt,
le préfixe d'adresse et le parent. Les gabarits `ligne` et `ajout` prennent le préfixe au lieu de
`/offres/{{ offre.id }}`.

## Lots

1. **Données** : migration 9, `DepotFactures`, `Numerotation` (pure), `PlanAcomptes` (pure : genre
   proposé, montant d'un acompte, déjà facturé), échéance. Tests unitaires et de dépôt, isolation.
2. **IBAN** : `Iban` (pure : nettoyage, CH/LI, clé modulo 97 sans bcmath, QR-IBAN), champ dans
   « Mon entreprise », résumé de la rubrique. Tests.
3. **Écrans du brouillon** : « Facturer », Nouvelle facture, écran de la facture, lignes par
   `LigneControleur` généralisé, en-tête, suppression du brouillon, onglet et liste, liens depuis l'offre.
   Tests d'écran et d'isolation ; non-régression des lignes de l'offre.
4. **Document de la facture** : `_document` généralisé (titre, « selon offre n° … du … », pas de
   validité ni de signature, « Payable jusqu'au … », déjà facturé, à payer), aperçu à l'écran.
5. **Émission** : émettre, PDF gardé et servi, annuler, annuler et refaire, 409 sur une facture figée.
   Tests : PDF identique après un changement de réglages ; aucune écriture sur une facture émise.
6. **Adresses structurées et QR-facture**, en deux temps : 6a, adresses (fait le 2026-11-13, variante B de Xavier :
   champs séparés, NPA au répertoire officiel, suggestions swisstopo) ; 6b, QR-facture (fait le 2026-11-14).
   Contenu d'origine : rue, numéro, NPA, localité et pays en champs séparés pour l'entreprise et les fiches clients
   (Xavier, 2026-11-13 : « il faut absolument que ça corresponde au standard »), reprise des adresses en texte
   libre par un découpage proposé, à vérifier ; dépendances verrouillées, `QrFacture`, section de paiement à
   l'écran et au PDF, placement en deux rendus. Preuve : charge utile sans
   violation, code relu par OpenCV sur le PDF, mesuré à 46 mm.
7. **Démo et recette** : remise à zéro de la démo (factures effacées avant les offres), parcours cliqué
   (Playwright), captures relues, `DEPLOIEMENT.md` (bcmath, dépendances), `RECETTE.md`.

## Vérification par lot

`vendor/bin/phpunit`, `composer analyse`, `composer audit` dans WSL, puis captures ouvertes pour tout
lot qui touche un écran. Aucun « fait » sans la sortie collée dans `ETAT.md`.
