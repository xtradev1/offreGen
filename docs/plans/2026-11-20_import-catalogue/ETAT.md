---
chantier: Import du catalogue depuis d'anciennes offres (PDF)
slug: 2026-11-20_import-catalogue
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Lire le retour de Xavier sur ses propres offres, essayées sur le site de test. Corriger ce qu'il relève ; puis, si le regroupement rate trop de formulations, mesurer le comparateur sur des paires tirées de ses offres.

## En attente de toi

- Sur https://easy-offres.xtradev.ch, depuis un compte d'essai : Catalogue, « Importer », choisir 3 à 5 de tes offres en PDF, « Lire », puis « Voir les produits trouvés ».
- Relever les lignes fausses (prix, unité, libellé), les produits qui auraient dû être réunis, et ceux réunis à tort.
- Décocher ce qui ne va pas, « Ajouter au catalogue », regarder le catalogue, puis « Annuler cet import » depuis le bilan.
- Dire si l'avertissement sous le choix des fichiers te convient (texte modifié, voir Décisions).

## Suivi Origin

Projet 44 (offreGen), tâche 458 « Passage à l'échelle », sous-tâche 467 « catalogue importé d'anciennes offres ».

## Journal

Append-only. Une ligne datée par événement.

- 2026-11-20 : chantier ouvert par Xavier (« un petit mini sous-chantier »). Étude sur papier rendue dans la conversation : trois voies (anciennes offres, CSV, dictée), coûts estimés, rien de mesuré.
- 2026-11-20 : phase 0 sautée, le profil existe. Phase 1 : `SPEC.md` écrit à partir des décisions de Xavier ; gate d'intention tenu pour franchi par son message, qui dicte le périmètre et commande la maquette. Phase 2 sautée : un seul lot.
- 2026-11-20 : phase 3, `MAQUETTE-import.html` v1 : parcours cliquable sur téléphone et sur PC, états vide, PDF scanné et « aucun produit », variante un produit à la fois, douze décisions. Gate visuel ouvert.
- 2026-11-20 : hors gate, sur ordre direct de Xavier, unités « ml », « kg » et « jour » ajoutées à `Saisie::UNITES`, test écrit avant.
- 2026-11-20 : recherches rendues dans la conversation (Qwen 3.8 face à Opus, PDF contre photo).
- 2026-11-20 : gate visuel franchi, réponses de Xavier à D1 à D12 (tableau des décisions). Consigne : corpus d'offres publiques et inventées, « un mélange », puis tester.
- 2026-11-20 : corpus de neuf PDF, lecteur et banc en local (Qwen dans Ollama) : 47 lignes justes sur 47.
- 2026-11-20 : demande de Xavier : 3 imports par mois, coût exact chez Infomaniak d'abord, puis développer et déployer sur le test, « tu places avec les liens sur Infomaniak ». Phase 4 (PLAN.md) sautée : le prototype tient en un lot, la maquette validée sert de plan d'écran ; écrit au journal plutôt qu'en document.
- 2026-11-20 : chez Infomaniak, la sortie par appel d'outil s'arrêtait parfois après une ligne (carreleur : 7 passes ratées sur 17, relance comprise). Champ « propre à un client » retiré, puis sortie en lignes de texte : 10 passes justes sur 10. Détail : `banc/stats/SYNTHESE-2026-11-20.md`.
- 2026-11-20 : copie de travail propre (`~/offreGen-deploiement-import-catalogue`, tête de `origin/chantier-voix-offre`) et base de test propre à la session (`offregen_import_test`), parce que trois autres suites tournaient sur `offregen_test`.
- 2026-11-20 : prototype écrit : regroupement, intégration, annulation, quota, écrans, script du navigateur. Tests écrits avant, vus rouges. Parcours cliqué sur PC et téléphone, serveur local branché sur Infomaniak ; cinq défauts vus à l'image et corrigés.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-20 | PDF seulement ; date et provenance des offres ignorées | Xavier, voir `SPEC.md` |
| 2026-11-20 | Texte du PDF lu dans le navigateur par PDF.js ; le fichier ne part jamais | Claude : déjà dans l'application, aucune dépendance PHP, chiffres exacts |
| 2026-11-20 | D1 : la carte, sobre (une ligne, sans couleur), dans le catalogue tant qu'aucun import n'a servi, et l'entrée dans « Plus » ; pas de bouton dans la barre du bas | Xavier : « la carte puis dans plus et la carte faut la faire plus sobre la c'est moche » (lecture de Claude) |
| 2026-11-20 | D2 : cinq PDF au plus par import | Xavier (A) |
| 2026-11-20 | Trois imports par mois et par compte, un import compte dès sa création, annulé compris | Xavier : « On peut mettre 3 imports par mois » ; le coût est engagé à la lecture |
| 2026-11-20 | D3 à D9, D11, D12 appliqués tels que validés | Xavier |
| 2026-11-20 | D10 : « m » et « ml » gardés tous les deux | Xavier : « si oui ont peut avoir les deux en fonction de l'envie » |
| 2026-11-20 | Pas de champ « propre à un client » ; les rabais, sous-totaux et acomptes ne sont pas relevés. La section « Lignes laissées de côté » n'existe plus : l'artisan décoche | Mesure chez Infomaniak (voir journal) ; Xavier : « Il faut juste qu'ils reconnaissent qu'il y ait un élément » |
| 2026-11-20 | Lecteur et comparateur répondent en lignes de texte, sans appel d'outil | Banc chez Infomaniak : 10 sur 10 contre 10 sur 17 ; 40 % de jetons reçus en moins |
| 2026-11-20 | Une seconde lecture quand le tableau compte nettement plus de lignes chiffrées que la réponse (moins de 60 %) | Panne observée chez Infomaniak ; ne coûte qu'en cas d'échec |
| 2026-11-20 | Le modèle cite le numéro de la ligne ; le code refuse un prix qu'il ne relit pas près de cette ligne, et un montant pris pour un prix unitaire | Aucun prix inventé ne doit entrer au catalogue |
| 2026-11-20 | Nouveaux produits dans la catégorie « Importés » | Le modèle ne propose pas de catégorie ; un appel de plus pour un prototype |
| 2026-11-20 | Avertissement sous le choix des fichiers changé : « Le fichier reste sur votre appareil : seul son texte est lu, par l'IA d'Infomaniak, en Suisse. Dicta garde les produits, les unités et les prix, jamais les noms ni les adresses de vos clients. » | La phrase validée (D9) disait « les fichiers sont supprimés après la lecture » : le fichier n'est jamais envoyé, et le texte, noms compris, part bien chez Infomaniak |
| 2026-11-20 | Le texte lu et les lignes relevées ne sont pas gardés après l'intégration ; seuls le bilan et de quoi annuler restent | D9 |
| 2026-11-20 | Préfixe `/import-catalogue` | `/catalogue/{id}` avale `/catalogue/import` : le routage n'a pas de contrainte sur `{id}` |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `SPEC.md` | 1 | écrit |
| `MAQUETTE-import.html` | 3 | validée le 2026-11-20 |
| `banc/` | 5 | banc de la lecture et du regroupement, local et Infomaniak |
| `src/Catalogue/` : `Lecteur`, `LecteurApi`, `LecteurFactice`, `LectureOffre`, `LigneLue`, `UniteLue`, `Comparateur`, `ComparateurApi`, `ComparateurFactice`, `Ressemblance`, `Regroupement`, `Integration` | 5 | écrits, testés |
| `src/Donnees/DepotImports.php`, `migrations/Version20261120210001.php` | 5 | écrits, migration jouée sur la base de test de la session |
| `src/Http/Controleur/ImportCatalogueControleur.php`, `ImportDejaIntegre.php`, routes, conteneur | 5 | écrits |
| `templates/import_catalogue.html.twig`, `import_relecture.html.twig`, `import_bilan.html.twig` ; carte du catalogue ; entrée de « Plus » | 5 | écrits, vus à l'image |
| `public/js/texte-pdf.js`, `public/js/import-catalogue.js`, `public/css/import-catalogue.css` | 5 | écrits, vus à l'image |
| `tests/Catalogue/LecteurApiTest.php`, `ImportCatalogueTest.php`, `tests/Http/ImportCatalogueEcranTest.php`, cas d'isolation | 5 | verts |

## Preuves

### Phase 3

```
$ node check.js MAQUETTE-import.html captures
bureau : largeur 1440/1440, images 0, cassées 0, kit oui
telephone : largeur 390/390, images 0, cassées 0, kit oui
OK.
```

### Phase 5, tests écrits avant

```
$ vendor/bin/phpunit tests/Catalogue/ImportCatalogueTest.php        (avant le code)
Tests: 14, Assertions: 0, Errors: 14.
Error: Class "OffreGen\Catalogue\Ressemblance" not found

$ vendor/bin/phpunit tests/Http/ImportCatalogueEcranTest.php        (avant le code)
Tests: 8, Assertions: 2, Errors: 6, Failures: 2.
Error: Class "OffreGen\Http\Controleur\ImportCatalogueControleur" not found

$ vendor/bin/phpunit tests/Http/IsolationRoutesTest.php            (routes ajoutées, cas absents)
1) OffreGen\Tests\Http\IsolationRoutesTest::testChaqueRouteAParametreAUnCas
+    0 => 'import_catalogue_voir',  ... 4 => 'import_catalogue_annuler',
```

### Phase 6, vérification

```
$ env APP_ENV=test vendor/bin/phpunit          (base offregen_import_test)
OK (965 tests, 8326 assertions)
$ vendor/bin/phpstan analyse
[OK] No errors
```

Parcours cliqué (Playwright, serveur local, IA d'Infomaniak), PC 1440 px et téléphone 390 px : catalogue, Plus, choix de deux offres et d'un scan, lecture, relecture, envoi sans réponse, réponses, crayon, intégration, catalogue, annulation. Sondes de disposition et de contraste muettes sur les écrans de l'import ; un texte indicatif à 3,73:1 dans la recherche du catalogue, existant avant ce chantier. Aucune erreur JavaScript. Lecture et regroupement de deux offres : 6 à 25 s.

Défauts vus à l'image et corrigés : PDF scanné effacé de la liste au lancement de la lecture (contre D12) ; question répondue restée rouge ; ligne décochée à peine distincte (barrée depuis) ; champ du prix sur toute la largeur sur PC ; bouton « Annuler cet import » hors de son formulaire (sonde).

Non vérifié : un vrai iPhone, un PDF sorti de Word ou d'Excel, les offres de Xavier.
