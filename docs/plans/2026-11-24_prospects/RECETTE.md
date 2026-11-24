# Recette : prospects, le catalogue des commerciaux

Tout ce qui suit se teste à la main sur le site de test, en une passe. Coche au fur et à mesure. Site : `https://easy-offres.xtradev.ch`. Données inventées seulement.

## Préalables

- [x] Migration `Version20261124210001` (tables `prospect` et `prospect_note`) : jouée sur le test par le déploiement, `/sante` dit « migrations ok ».
- [x] Code déployé sur le test : voir le commit dans `ETAT.md`, sonde verte.
- [x] Comptes de départ, créés par Claude le 2026-11-24 :
  - `prospects-nina@exemple.ch` et `prospects-leo@exemple.ch`, commerciaux. Mots de passe dans WSL : `~/.offregen-test-prospects-nina-compte` et `~/.offregen-test-prospects-leo-compte` (ligne « Mot de passe »).
  - ton compte administrateur habituel.
- [x] Fiches déjà là, laissées par la recette cliquée de Claude : Peinture Roux Sàrl et Menuiserie des Tilleuls (Nina, la seconde avec une démo), Plâtrerie Girardin et Sols Berbier (Léo).
- [ ] Rien à faire en production : le site de production n'existe pas.

## Scénario 1 : noter un artisan en moins de 30 secondes

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/prospects` |
| Compte | Nina, sur ton téléphone |

Gestes :
1. Touche « Prospects » dans la barre du bas, puis « Ajouter un prospect ».
2. Tape « chapuis », touche « Chercher », puis « Créer « chapuis » ».
3. Complète le nom en « Façades Chapuis », la localité « 2854 Bassecourt », touche « Enregistrer ».

Attendu : la fiche « Façades Chapuis » s'ouvre, avec le message « Prospect enregistré. » et l'état « À contacter ». Aucune date de libération n'est annoncée. « Les miens » compte une fiche de plus.

Si ça casse : `src/Http/Controleur/ProspectControleur.php` (`creer`), table `prospect`.

## Scénario 2 : le prochain pas et « À faire »

| | |
|---|---|
| URL | la fiche créée au scénario 1 |
| Compte | Nina |

Gestes :
1. Sous « Prochain pas », tape « Rappeler », choisis la date d'aujourd'hui, touche « Enregistrer le pas ».
2. Reviens à « Prospects ».
3. Touche « C'est fait » sur la carte de Façades Chapuis.

Attendu : après 2, la carte « Façades Chapuis » est sous « À faire » avec l'étiquette « Aujourd'hui ». Après 3, la fiche s'ouvre, le pas a disparu et une note « Fait : Rappeler » est en tête des notes. Le jour de la semaine d'un pas daté plus loin est le bon (un jeudi s'appelle jeudi).

Si ça casse : `Prospects::poserPas`, `Prospects::pasFait`, `ProspectControleur::jour`.

## Scénario 3 : un collègue voit qui suit, sans lire la fiche

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/prospects?vue=tous` |
| Compte | Léo |

Gestes :
1. Ouvre « Tous », puis touche « Peinture Roux Sàrl ».
2. Cherche « roux » dans le champ de la liste.

Attendu : la liste dit « suivi par Nina Rey depuis le … ». La fiche montre le nom, « Delémont · Peintre », « Suivi par Nina Rey » et la date de sa dernière action, sans date de libération. Elle ne montre ni le 079 555 01 42, ni « Marc Roux », ni la note sur Word, ni aucun bouton.

Si ça casse : `Prospects::vue` (fiche réduite), `templates/admin/prospect.html.twig`.

## Scénario 4 : le doublon

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/prospects/chercher` |
| Compte | Léo |

Gestes :
1. Cherche « roux pein ». Regarde le résultat, puis touche « Créer « roux pein » ».
2. Change le nom en « Roux Rénovation », localité « 2800 Delémont », téléphone « +41 79 555 01 42 », « Enregistrer ».
3. Touche « C'est une autre entreprise ».

Attendu : après 1, la recherche montre « Peinture Roux Sàrl », suivi par Nina Rey (les deux mots, dans le désordre, sans « Sàrl »). Après 2, l'écran « Ce prospect est peut-être déjà noté » montre « Peinture Roux Sàrl · Même téléphone · suivi par Nina Rey ». Après 3, la fiche « Roux Rénovation » est créée et suivie par Léo.

Si ça casse : `DepotProspects::ressemblants`, `templates/admin/prospect_doublon.html.twig`.

## Scénario 5 : prendre et lâcher

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/prospects?vue=libres` |
| Compte | Léo, puis Nina |

Gestes :
1. Léo ouvre « Roux Rénovation », touche « Je lâche ce prospect ».
2. Nina ouvre « Libres », touche « Je prends » sur « Roux Rénovation ».

Attendu : après 1, Léo revient à sa liste, la fiche n'y est plus. Après 2, « Libres » disait « libre depuis le …, suivi avant par Léo Favre » ; la fiche s'ouvre chez Nina avec « Ce prospect est à vous. » et les notes « Prospect lâché. » puis « Prospect pris. ».

Si ça casse : `DepotProspects::prendre`, `DepotProspects::lacher`.

## Scénario 6 : la démo depuis la fiche

| | |
|---|---|
| URL | la fiche « Peinture Roux Sàrl » |
| Compte | Nina |

Gestes :
1. Touche « Créer une démo ».
2. Reviens à la fiche par « Prospects ».

Attendu : après 1, le code QR de la démo « Peinture Roux Sàrl », métier Peintre. Après 2, la fiche dit « Démo en cours », avec « Voir le compte » ; les boutons d'état et « Je lâche ce prospect » ont disparu. Chez Léo, la même fiche dit « Démo chez Dicta », sans lien vers le compte.

Si ça casse : `Prospects::creerDemo`, `DepotProspects::lierCompte`.

## Scénario 7 : la responsable lit tout, la fusion

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/prospects?vue=tous` |
| Compte | toi, administrateur, sur ordinateur |

Gestes :
1. Clique « Plâtrerie Girardin » dans le tableau.
2. Ouvre « Peinture Roux Sàrl », clique « Fusionner avec une autre fiche », cherche « roux », clique « Fusionner » sur « Roux Rénovation » (les deux fiches sont à Nina après le scénario 5).

Attendu : après 1, la fiche s'ouvre à droite du tableau avec le téléphone, le prochain pas et la note de Léo, sans aucun bouton d'écriture. Après 2, il reste une seule fiche « Peinture Roux Sàrl », qui porte les notes des deux. Si les deux fiches étaient suivies par deux personnes, la page répond « Ces deux fiches sont suivies par deux personnes… » et ne change rien.

Si ça casse : `Prospects::fusionner`, `DepotProspects::fusionner`.

## Ce qui n'est PAS couvert

- **Une fiche gardée longtemps** : depuis le 2026-11-24 (décision de Xavier), une fiche reste suivie tant qu'on ne la lâche pas, sans plafond. Quatre mois sans action et 42 fiches pour une seule personne sont prouvés par test seulement (`ProspectsTest::testUneFicheResteSuivieTantQuOnNeLaLachePas`, `ProspectsTest::testAucunPlafondDeFichesSuivies`).
- **Un prochain pas daté loin** : accepté jusqu'à un an, refusé au-delà ; prouvé par test seulement.
- **Deux gestes au même instant** (deux « Je prends », deux « Créer une démo ») : les garde-fous sont dans le SQL, testés un à la fois, jamais en vraie course.
- **Un vrai téléphone** : Claude a joué le parcours dans Chromium à 390 px. iPhone, Safari et Firefox non essayés.
- **La production** : rien n'y est. Les vrais prospects attendent le site de production.
- **Hors périmètre, acté dans `SPEC.md`** : recherche dans le registre du commerce ou le registre IDE, import en masse, attribution par un tiers, envoi de messages aux prospects, agenda.
- **Données personnelles** : mention dans la politique de confidentialité et durée de garde des fiches, à traiter avec le lot juridique (Origin 463). Rien n'efface une fiche aujourd'hui.
- **Quand une démo finie est supprimée** (30 jours après sa fin), la fiche reste suivie et perd la trace de la démo (REVUE, C5 et R2).

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Lint | vert | PHPStan niveau 8 : « No errors », dans `ETAT.md` |
| Suite de tests | verte | 1608 tests, 13 144 assertions après le retrait de la libération et du plafond ; détail dans `ETAT.md` |
| Revue | faite | `REVUE.md` : trois relecteurs, onze trouvailles retenues et corrigées, sept écartées avec leur raison |
| Recette cliquée par Claude | passée sur le test | parcours Playwright, douze captures regardées ; un défaut trouvé (jour de la semaine) et corrigé |
