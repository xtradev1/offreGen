# Plan : parcours de la dictée et constats des parcours

Entrées : `MAQUETTE-parcours-dictee-v2.html` (validée le 2026-11-26), `PARCOURS-artisan.html` (huit constats, tous
« À faire » le 2026-11-26), décisions de `ETAT.md`. Pas de `SPEC.md` : les deux maquettes en tiennent lieu.

Aucune migration, aucune dépendance nouvelle, aucune route nouvelle.

## Contrat technique

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/enregistrements/{uuid}/etat` | GET | artisan connecté, sa portée | rien | JSON d'aujourd'hui, plus `suite`, `a_relire`, `lignes`, `titre`, `client` (voir Structures) |
| `/offres/{id}/relire/{ligne}` | POST | artisan connecté, sa portée | `quantite`, `prix`, `_csrf` (inchangé) | 303 vers `/offres/{id}/relire` s'il reste des lignes, sinon vers `Arrivee::adresse` |
| `/offres/{id}/relire` | GET | idem | rien | la page ; sans ligne à relire, l'écran de fin garde ses deux boutons, le principal mène à `Arrivee::adresse` |
| `/` | GET | idem | `tri` (inchangé) | la liste, chaque carte avec `data-texte` (titre, client, numéro, normalisés) et le compte unique |
| `/offres/{id}/client?suite=apercu` | GET, POST | idem | inchangé | inchangé : le client choisi mène au PDF (existant, `ClientControleur::apresChoix`) |

### Structures

Réponse de `/enregistrements/{uuid}/etat` (les cinq premiers champs existent) :

| Champ | Type | Sens |
|---|---|---|
| `statut` | string | inchangé |
| `faites` | int | étapes faites sur trois (« Offre prête » sort de la liste) : reçu 1, transcrit 2, prêt 3 |
| `echec`, `interrompu` | bool | inchangés |
| `essais` | int | inchangé |
| `offre` | int ou null | inchangé (les pages gardées dans les téléphones le lisent encore) |
| `suite` | string ou null | où aller quand l'offre est prête : `/offres/N/relire`, `/offres/N/client?suite=apercu` ou `/offres/N/apercu` |
| `a_relire` | int ou null | lignes à relire (incertaines ou sans prix) ; plus de 0 : l'écran « Offre prête » se montre avant `suite` |
| `lignes` | int ou null | nombre de lignes de l'offre, pour l'écran « Offre prête » |
| `titre`, `client` | string ou null | objet de l'offre et nom du client (fiche ou nom dicté), pour le même écran |

Copie d'une dictée dans le navigateur (IndexedDB `offregen-abri`, magasin `dictees`) : un champ de plus, `pause`
(millisecondes de pause dans la partie en cours, 0 par défaut). `dureeMs(d)` le retranche.

### Flux de l'arrivée

1. `Traiteur::creerOffre` (`src/Audio/Traiteur.php`) crée l'offre et ses lignes, `client_id` posé au-delà de 80 % de
   ressemblance (existant).
2. `EnregistrementControleur::etat` (`src/Http/Controleur/EnregistrementControleur.php`) lit l'offre
   (`DepotOffres::parId`) et ses lignes (`DepotOffres::lignes`), compte par `Arrivee::compter`, choisit par
   `Arrivee::adresse` (à créer, `src/Offre/Arrivee.php`).
3. `suivreEtapes` (`public/js/app.js`) passe la réponse entière à `fini(e)`.
4. Fenêtre de dictée (`nouvelleOffre`) : `a_relire > 0` montre l'état `prete` (à créer dans
   `templates/_nouvelle_offre.html.twig`), dont le bouton mène à `suite` ; sinon `partir(suite)`.
5. Page seule `/enregistrements/{uuid}` (`preparation`) : `a_relire > 0` recharge la page, que le serveur rend avec
   « Relire N lignes » ; sinon `location.replace(suite)`.
6. Dernière ligne relue : `RelectureControleur::valider` redirige vers `Arrivee::adresse` ; `fluide.js` suit déjà une
   redirection vers un autre écran (`Morceaux::ailleurs`, en-tête `X-Morceaux-Aller`).

### Existant réutilisé

- `Offerte::prixManquant` (`src/Offre/Offerte.php`), `OffreControleur::grouper` et `::options` pour l'ordre de relecture.
- `ClientControleur::suite` et `::apresChoix` : `suite=apercu` mène au PDF.
- `Morceaux::ailleurs` et `Morceaux::aller` (`src/Http/Morceaux.php`) : navigation après un geste parti par `fetch`.
- `brancherFiltre` (`public/js/app.js`) et `Recherche::normaliser` : recherche à la frappe, déjà en place pour Clients,
  Catalogue, Factures et la colonne des offres au PC.
- `calques`, `montrer(etat)`, `abri`, `marquerEtapes` (`public/js/app.js`).

## Signatures

```php
namespace OffreGen\Offre;

final class Arrivee
{
    /** @param array{prix_centimes: int, a_verifier: int, offerte?: int|bool} $ligne */
    public static function aRelire(array $ligne): bool;

    /** @param list<array{prix_centimes: int, a_verifier: int, offerte?: int|bool}> $lignes */
    public static function compter(array $lignes): int;

    /** @param array{id: int, client_id: int|null} $offre */
    public static function adresse(array $offre, int $aRelire): string;
}
```

`AccueilControleur::totauxEtARelire` garde sa signature ; son second tableau devient le compte de `Arrivee::compter`.
`EnregistrementControleur::ETAPES` passe à trois libellés ; `FAITES[PRET]` passe à 3.

JavaScript, dans `nouvelleOffre` (`public/js/app.js`) :

```js
const attente = (dureeAudioMs) => ({ envoi(part), partir(), arreter() }) // l'anneau : envoi réel, puis temps écoulé sur temps attendu
const ecoute = (flux, contexte) => ({ niveau(), arreter() })             // niveau du micro (AnalyserNode), null si le contexte ne tourne pas
const mettreEnPause = () => void ; const reprendreDictee = () => void    // MediaRecorder.pause() et resume(), durée de pause retranchée
```

## Décisions fragiles

1. **Durée attendue de la préparation : 10 s plus 3 % de la durée dictée.** Mesure du 2026-11-26 sur le test, 11 dictées
   hors panne du 25.11 : de 4 à 25 s, médiane 8 s, aucune dictée de plus de 214 s. La maquette annonçait « 30 à 50 s » :
   c'était faux. Si les vraies dictées de dix minutes prennent bien plus, l'anneau reste à 95 % avec « encore un
   instant » et la constante du lot 1 est à refaire.
2. **La pause par `MediaRecorder.pause()` marche sur l'iPhone.** Vérifiable dans Chromium seulement. Si Safari coupe
   l'enregistrement ou rend un fichier illisible après une pause, le lot 6 est à refaire en parties séparées, comme la
   reprise d'une dictée coupée.
3. **Le niveau du micro se lit par un `AudioContext` créé dans le toucher.** Si Safari le laisse suspendu, il n'y a ni
   onde ni alerte de silence (jamais d'alerte sur un contexte qui ne tourne pas) : le lot 5 livre alors un écran sans
   son principal apport.

## Lots

Ordre : les trois lots validés (attente, arrivée, enregistrement), puis les constats. Les constats 1 et 2 entrent dans
l'arrivée : ils touchent le même code. Suite de contrôle de chaque lot, sauf mention :
`vendor/bin/phpunit tests/Http tests/Regles tests/Offre` (dans `~/offreGen-micro`, base `offregen_fluidite_test`).

### Lot 1 : attente entre A et B

- [x] fait

| | |
|---|---|
| Objectif | Après « Terminer », le plein écran sombre reste : anneau qui se remplit avec la durée restante, trois étapes nommées, phrase « vous pouvez quitter ». |
| Fichiers | `templates/_nouvelle_offre.html.twig`, `templates/_etapes.html.twig`, `src/Http/Controleur/EnregistrementControleur.php`, `public/js/app.js`, `public/css/app.css` |
| Test | `tests/Http/EcransTest.php::testLaPreparationAvanceEtapeParEtape` (trois étapes) ; `tests/Http/AttenteDicteeTest.php` (à créer : la fenêtre porte l'anneau et trois étapes) |
| Vérification | suite de contrôle verte ; Playwright sur le banc local : captures de l'envoi, de la transcription, de la fin, sondes de contraste et de disposition |
| Dépend de | rien |

### Lot 2 : traceur de l'arrivée

- [x] fait

| | |
|---|---|
| Objectif | `/etat` porte `suite` et `a_relire` (en dur : `/offres/N`, 0) et le navigateur suit `suite`. |
| Fichiers | `src/Http/Controleur/EnregistrementControleur.php`, `public/js/app.js` |
| Test | `tests/Http/ArriveeDicteeTest.php` (à créer) : la réponse porte les deux champs |
| Vérification | Playwright : fin d'une dictée simulée, l'adresse ouverte est celle de `suite` |
| Dépend de | lot 1 |

### Lot 3 : règle « offre propre, PDF » et compte unique (constat 2)

- [x] fait

| | |
|---|---|
| Objectif | Le serveur dit où arriver : relecture, choix du client ou PDF. La liste, la colonne du PC et la relecture comptent pareil, et la liste dit « N à relire ». |
| Fichiers | `src/Offre/Arrivee.php` (à créer), `EnregistrementControleur.php`, `AccueilControleur.php`, `RelectureControleur.php`, `templates/accueil.html.twig`, `templates/_liste_offres_pc.html.twig`, `templates/enregistrement.html.twig` |
| Test | `tests/Offre/ArriveeTest.php` (à créer : ligne sans prix, ligne offerte à 0, les trois adresses) ; `ArriveeDicteeTest.php` (trois cas par la route) ; test de la liste : une ligne sans prix compte |
| Vérification | suite de contrôle verte |
| Dépend de | lot 2 |

### Lot 4 : écran « Offre prête » et fin de relecture vers le PDF

- [x] fait

| | |
|---|---|
| Objectif | Lignes à relire : écran « Offre prête » sans le prix, un toucher mène à la relecture. Dernière ligne relue : le PDF s'ouvre, ou le choix du client s'il manque. |
| Fichiers | `templates/_nouvelle_offre.html.twig`, `templates/relecture.html.twig`, `RelectureControleur.php`, `public/js/app.js`, `public/js/fluide.js`, `public/css/app.css` |
| Test | `tests/Http/RelectureTest.php` : la dernière ligne validée redirige vers `/apercu`, ou vers `/client?suite=apercu` sans client |
| Vérification | suite verte ; Playwright sur le banc : les trois cas de la maquette, de « Terminer » au PDF |
| Dépend de | lot 3 |

### Lot 5 : relecture d'une ligne sans prix (constat 1)

- [x] fait

| | |
|---|---|
| Objectif | Sur une ligne sans prix, le curseur est dans le prix et le bouton dit « Indiquez le prix » tant que le champ est vide. Un message remplace le précédent, part à la ligne suivante, et ne dit plus « L'envoi a échoué ». |
| Fichiers | `templates/relecture.html.twig`, `public/js/fluide.js`, `public/css/app.css` |
| Test | `RelectureTest.php` : la carte d'une ligne sans prix porte le libellé de remplacement ; Playwright `parcours-3.js` rejoué (0 message après deux touchers, 0 sur l'écran de fin) |
| Vérification | suite verte ; sortie du script collée |
| Dépend de | lot 4 |

### Lot 6 : enregistrement plein écran, alerte de silence, pause

- [x] fait

| | |
|---|---|
| Objectif | Écran sombre : état, minuteur, onde de la voix, conseils tournants, arrêt au centre, Abandonner à gauche, Pause à droite. Cinq secondes sans voix : la carte du conseil devient l'alerte. La pause arrête le minuteur et l'enregistrement, la durée dictée l'ignore (limite des 30 minutes, minimum de 5 s, reprise d'une dictée coupée). |
| Fichiers | `templates/_nouvelle_offre.html.twig`, `public/js/app.js`, `public/css/app.css`, `src/Compte/Activite.php` si les conseils suivent l'activité |
| Test | `tests/Http/EnregistrementAbriTest.php` ou voisin : la fenêtre porte les trois commandes et les conseils ; Playwright avec faux micro : onde, pause de 3 s (minuteur figé, durée envoyée sans la pause), silence simulé, reprise d'une dictée coupée après une pause |
| Vérification | suite verte ; sorties Playwright ; sondes de contraste et de disposition ; `non_verifie` sur iPhone (Xavier) |
| Dépend de | lot 1 (même écran sombre) |

### Lot 7 : page de l'offre, lignes plus haut (constat 3)

- [x] fait

| | |
|---|---|
| Objectif | Vides, « Représenté par » et « Texte avant les lignes » tiennent sur une ligne de deux liens ; remplis, ils reprennent leur place. |
| Fichiers | `templates/offre.html.twig`, `public/css/app.css`, `public/js/fluide.js` ou `app.js` (ouvrir le champ au toucher) |
| Test | `EcransTest.php` ou `EnTeteChantierTest.php` : offre sans représentant ni texte, les deux liens ; avec, les blocs |
| Vérification | suite verte ; `parcours-1.js` : la première ligne plus haut qu'à 0,60 écran |
| Dépend de | rien |

### Lot 8 : ajouter une ligne (constat 4)

- [x] fait

| | |
|---|---|
| Objectif | À « Terminé », la page défile jusqu'à la dernière ligne ajoutée, l'éclaire et pose le curseur dans sa quantité. La recherche passe avant « Elle va ». Texte du champ : « Chercher ou taper une ligne ». |
| Fichiers | `templates/ajout.html.twig`, `public/js/app.js` (`fichesEnFenetre`), `public/css/app.css` |
| Test | `EcransTest::testAjouterGardeLaFeuilleOuverteEtProposeLeTexteTape` (ordre et texte du champ) ; Playwright `parcours-3.js` : élément actif après « Terminé » |
| Vérification | suite verte ; sortie du script |
| Dépend de | rien |

### Lot 9 : recherche dans Mes offres (constat 5)

- [x] fait

| | |
|---|---|
| Objectif | Un champ « Chercher une offre, un client » en tête de liste, filtre à la frappe sur le titre, le client et le numéro. |
| Fichiers | `AccueilControleur.php`, `templates/accueil.html.twig`, `public/js/fluide.js` (le tri garde le message « aucune offre ») |
| Test | `EcransTest.php` : chaque carte porte son `data-texte` normalisé, le champ pointe `#liste-offres` |
| Vérification | suite verte ; Playwright : taper un nom, compter les cartes visibles |
| Dépend de | lot 3 (même gabarit) |

### Lot 10 : fiche d'une ligne (constat 6)

- [x] fait

| | |
|---|---|
| Objectif | Désignation, quantité, unité et prix d'abord ; le reste sous « Plus de réglages », ouvert quand un de ces réglages sert déjà sur la ligne ou porte une erreur. |
| Fichiers | `templates/ligne.html.twig`, `public/css/app.css` |
| Test | `EcransTest.php` : ligne simple, réglages repliés ; ligne offerte, option ou à taux particulier, ouverts |
| Vérification | suite verte ; capture |
| Dépend de | rien |

### Lot 11 : après « Envoyer » (constat 7) et « Se déconnecter » (constat 8)

- [x] fait

| | |
|---|---|
| Objectif | Offre envoyée : le bouton principal du PDF devient « Retour aux offres », « Renvoyer » et « Facturer » à côté. « Se déconnecter » une seule fois, en lien discret au bas de « Plus », avec l'adresse du compte. |
| Fichiers | `templates/apercu_pdf.html.twig`, `OffreControleur.php` (`apercu` : l'offre est-elle envoyée), `public/js/apercu-pdf.js`, `templates/plus.html.twig`, `templates/reglages.html.twig`, `PlusControleur` ou équivalent |
| Test | `ApercuPdfTest.php` (offre envoyée, bouton principal) ; `EcransTest::testPlusRegroupeCatalogueReglagesImportEtDeconnexion` |
| Vérification | suite verte ; Playwright `parcours-2.js`, parcours G |
| Dépend de | rien |

## Hors périmètre

Le PC et la tablette (aucune maquette), la recherche des offres côté serveur, le texte entendu pendant l'attente
(« pas obliger »), la preuve de la pause sur iPhone (Xavier).
