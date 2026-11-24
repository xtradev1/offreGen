# Recette : documents et réglages

Sur le site de test, `https://easy-offres.xtradev.ch`, au téléphone. Tout se fait à la main, en une passe ; coche au fur et à mesure. Environ 15 minutes.

## Préalables

- [x] Accord de Xavier, 2026-11-24 : « oui déploie, pas de vraies données ».
- [x] Déployé : 2ddda06 (tête de `chantier-voix-offre`, contient ce510c5), sonde verte. Aucune migration de ce chantier.
- [x] Compte de recette : `documents-recette@exemple.ch`, démo peintre neuve ; mot de passe dans `~/.offregen-test-documents-recette-compte` (WSL). Son offre « Rafraîchissement du séjour » : `https://easy-offres.xtradev.ch/offres/91`, ligne « Rebouchage et ponçage des murs », 42 m² à 9.00.
- [ ] La connexion ouvre l'assistant du premier lancement (`/bienvenue/entreprise`) : le scénario 2 se joue là ; les autres URL s'ouvrent directement.

## Scénario 1 : la rubrique « Phrases du document » (LC-3)

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/reglages` (onglet « Plus », puis « Réglages ») |
| Compte | `documents-recette@exemple.ch` |

Gestes :
1. Regarde le sommaire des réglages.
2. Touche « Ma marque », fais défiler jusqu'en bas, reviens.
3. Touche « Phrases du document ». Touche « Validité », ajoute « Bon pour un mois. » à la fin de la phrase, puis touche ailleurs.
4. Reviens au sommaire.
5. Retourne dans « Phrases du document ».

Attendu :
- 1 : cinq rubriques ; « Phrases du document » sous « Ma marque », résumé « Dans Lettre seulement » ; « 4 rubriques sur 5 complètes » si l'entreprise est à compléter.
- 2 : Ma marque s'arrête après les modèles (logo, couleur, modèle), environ deux écrans de moins qu'avant.
- 3 : « Enregistré » en haut ; « Validité » passe à « Réécrite ».
- 4 : le résumé dit « Dans Lettre seulement · 1 phrase réécrite ».
- 5 : la phrase garde « Bon pour un mois. ».

Si ça casse : `templates/_reglages_phrases.html.twig`, `ReglagesControleur::RUBRIQUES` et `resumePhrases`, colonne `phrases_document` de la table `artisan`, console du navigateur.

## Scénario 2 : l'assistant du premier lancement (L2)

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/bienvenue/marque` |
| Compte | `documents-recette@exemple.ch` |

Gestes : ouvre l'étape « Modèle et couleur ».

Attendu : logo, couleur et modèle seulement ; pas de « Formule d'appel », pas de phrases.

Si ça casse : `templates/_reglages_marque.html.twig`.

## Scénario 3 : « Facturer » sans filigrane (OV-3)

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/clients`, puis la fiche d'un client et une de ses offres |
| Compte | `documents-recette@exemple.ch` |

Gestes :
1. Sur une offre, touche « Facturer ».
2. Regarde la vignette de la facture ; touche-la pour la voir en grand.
3. Ferme. Touche « Facture finale », puis « Corriger des lignes d'abord » : la page du brouillon s'ouvre ; ouvre son aperçu.

Attendu : nulle part le mot « APERÇU » en travers de la facture ; sous la vignette, « Aperçu : rien n'est encore créé » ; le titre de la page du brouillon dit « Aperçu du brouillon ».

Si ça casse : `templates/_apercu_facture.html.twig`, `templates/facture_document.html.twig`, cache du navigateur (`public/css/app.css`).

## Scénario 4 : variantes, quantité et unité reprises (V1, V2, V3)

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/offres/91` (« Rafraîchissement du séjour ») |
| Compte | `documents-recette@exemple.ch` |

Gestes :
1. Touche la ligne « Rebouchage et ponçage des murs » (42 m²), puis « + Variante de cette ligne ».
2. Regarde sous « Elle remplace ».
3. Sans rien changer, touche « Peinture anti-moisissure » dans la liste.
4. Dans « Quantité », tape 30. Regarde « Laquage de porte, 2 faces », puis touche-le.
5. Tape « Peinture silicate » dans la recherche, touche « Ajouter « Peinture silicate » ».
6. Ouvre « Ligne libre avec unité et prix » : désignation « Peinture lessivable », prix 34, « Ajouter la ligne ».
7. « Terminé ». Regarde les variantes sous « Rebouchage ». Puis touche « Relire » (ou le bouton principal s'il y mène).

Attendu :
- 2 : « Quantité 42 m² », « reprise de la ligne remplacée, à changer si besoin ».
- 3 : variante « Peinture anti-moisissure », 42 m².
- 4 : sous les produits d'une autre unité (Laquage, Déplacement, Heure de régie…), « 30 m², à relire » ; après le toucher, variante « Laquage de porte », 30 m² à 160.00, marquée « À vérifier ».
- 5 : variante « Peinture silicate », 30 m², prix à compléter.
- 6 : la quantité est déjà à 30 et l'unité à m² ; variante 30 m² × 34 = 1'020.00.
- 7 : « Laquage de porte » est dans « Relire » ; « C'est juste » l'en sort.

Si ça casse : `LigneControleur::ajouter` et `pageAjout`, `templates/ajout.html.twig`, colonnes `quantite_milliemes`, `unite`, `a_verifier` de la table `ligne`, console du navigateur (`app.js`, `optionsEtVariantes`).

## Ce qui n'est PAS couvert

- La ligne provisoire d'un ajout sans réseau (quantité reprise, hors du total pour une option ou une variante) : codée, non vérifiée, ni par un test ni par l'interface.
- Le bouton « OK » de la quantité sans JavaScript : vérifié par un test serveur (`?quantite=30`), pas cliqué.
- « Envoyer » une offre dont une variante est « À vérifier » : je n'ai pas vérifié qu'il passe par « Relire » (décision fragile n° 2 du plan).
- La marque « Démonstration » des PDF d'une démo : hors chantier (O2, Origin 557 et 509). Une démo sort toujours des factures sans marque.
- Le texte des phrases et des tournures : inchangé.
- La dictée des variantes : l'extraction n'a pas été touchée.

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Analyse statique | sans erreur | `ETAT.md`, Preuves |
| Suite de tests | 1506 tests, 12437 assertions, verts | `ETAT.md`, Preuves |
| Recette locale par l'interface | les quatre scénarios joués en local, sans erreur JS | `ETAT.md`, Preuves |
| Revue | 6 trouvailles mineures, corrigées et testées ; 3 points laissés à ta connaissance | `REVUE.md` |
