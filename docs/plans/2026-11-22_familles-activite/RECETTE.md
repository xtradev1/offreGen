# Recette : activités (bâtiment, extérieur, prestations)

Tout ce qui suit se teste à la main sur le site de test, en une passe. Coche au fur et à mesure.

## Préalables

- [ ] Accord de Xavier pour commiter, pousser sur `chantier-voix-offre` et déployer sur le site de test. Rien n'est commité : le code vit dans la copie `~/offreGen-familles`.
- [ ] Migration à jouer : `migrations/Version20261122100001.php` (colonne `artisan.activite`). Le déploiement sur le test la joue (`outils/deployer-test.sh`). Elle est additive : l'ancienne version du code tourne avec la colonne.
- [ ] Fichiers à déployer : la tête de `chantier-voix-offre` après le commit de ce chantier.
- [ ] Données de départ : aucune. Les démos des scénarios se créent à l'écran. Un compte d'équipe (commercial ou administrateur) est nécessaire pour `/admin`.
- [ ] Les comptes existants du test restent en Bâtiment, sauf ceux dont le métier est paysagiste, qui passent en Extérieur.

## Scénario 1 : créer une démo d'informatique

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/demos/nouvelle` |
| Compte | équipe (commercial ou administrateur) |

Gestes :
1. Ouvrir la page.
2. Toucher « Informatique », puis « Créer la démo ».
3. Ouvrir le lien de la démo dans une fenêtre privée, taper un courriel inventé.
4. Passer l'assistant (« Plus tard »).

Attendu : la page de création montre trois intertitres, « Bâtiment », « Extérieur », « Prestations », avec « Jardinier, paysagiste » seul sous « Extérieur » et quatre métiers sous « Prestations ». La page d'entrée de la démo dit « dictez votre première offre ». L'accueil montre deux offres, « Site vitrine de la boutique » et « Trois postes et la messagerie ».

Si ça casse : `templates/admin/demo_nouvelle.html.twig`, `src/Compte/Activite.php` (`duMetier`, `grouper`), table `artisan` (colonnes `metier`, `activite`).

## Scénario 2 : dicter une prestation

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/` |
| Compte | la démo d'informatique du scénario 1 |

Gestes :
1. Toucher le micro.
2. Dicter une vraie offre d'informatique ou de conseil, avec deux ou trois prix.
3. Toucher « Terminer et créer l'offre », attendre l'offre.

Attendu : l'écran d'enregistrement dit « Parlez, prestation par prestation. ». L'offre sort rangée par catégorie du catalogue, sans pièce. Si la dictée ne donne pas de titre, l'offre s'appelle « Offre du » suivi de la date.

À relever, pour cinq dictées (risque R2 de la SPEC) : le nombre de lignes justes et le nombre de lignes à corriger. Ces chiffres n'existent pas encore hors du bâtiment.

Si ça casse : `src/Offre/ExtracteurApi.php` (`consigne`, `PHRASES`), `src/Audio/Traiteur.php` (`creerOffre`), le journal du traitement.

## Scénario 3 : l'en-tête et le document d'une prestation

| | |
|---|---|
| URL | une offre de la démo d'informatique, bouton « Modifier » de son en-tête, puis l'aperçu du document |
| Compte | la démo d'informatique |

Gestes :
1. Ouvrir l'en-tête de l'offre : remplir « Lieu », « Début », « Durée », « Date de l'échange », enregistrer.
2. Ouvrir l'aperçu du document.
3. Dans Réglages, « Ma marque », choisir le modèle « Lettre », revenir à l'aperçu. Essayer deux autres modèles.

Attendu : l'en-tête n'a ni titre de bloc ni champ « Accès ». Le document dit « Lieu », « Début », « Paiement : À 30 jours dès la fin de la prestation. ». Le modèle Lettre écrit « pour les prestations suivantes » et « Échange du » suivi de la date. Aucun modèle ne dit « chantier », « travaux » ni « visite ».

Si ça casse : `templates/offre_entete.html.twig`, `templates/document/`, `templates/_document.html.twig`, `Activite::mots` et `Activite::dire`.

## Scénario 4 : changer l'activité d'un compte existant

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/reglages/entreprise` |
| Compte | un compte de test en Bâtiment (un des testeurs qui s'en servent pour des prestations) |

Gestes :
1. Noter le nombre de produits du catalogue et le nombre d'offres.
2. Dans « Votre activité », toucher « Prestations ».
3. Revenir à l'accueil, toucher le micro.
4. Ouvrir « Ma marque », lire la première phrase du modèle Lettre.

Attendu : le choix s'enregistre seul. L'écran de dictée dit « Parlez, prestation par prestation. ». Le catalogue, les offres et le modèle choisi sont les mêmes. La phrase de Lettre dit « prestations » si le titulaire ne l'avait pas réécrite.

Si ça casse : `ReglagesControleur::sauver`, `DepotArtisans::changerActivite`, `templates/_reglages_entreprise.html.twig`.

## Scénario 5 : un jardinier

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/admin/demos/nouvelle`, puis la démo créée |
| Compte | équipe, puis la démo |

Gestes :
1. Créer une démo « Jardinier, paysagiste », y entrer.
2. Dicter un entretien en nommant deux endroits (« devant la maison », « la haie côté route »).
3. Sur l'offre, toucher « Renommer » à côté d'une zone.

Attendu : l'écran de dictée dit « Parlez, zone par zone. ». L'offre est rangée par zone. L'écran dit « Renommer la zone », « Nom de la zone ». L'en-tête de l'offre s'intitule « Lieu d'intervention ».

À relever : les zones sont-elles justes ? Le second appel de l'Extérieur n'a jamais été mesuré (risque R7).

Si ça casse : `ExtracteurApi::CONSIGNE_ZONES`, `templates/piece.html.twig`, `templates/ajout.html.twig`.

## Scénario 6 : rien ne change au bâtiment

| | |
|---|---|
| URL | une démo « Peintre » créée par `/admin/demos/nouvelle` |
| Compte | la démo |

Gestes :
1. Dicter une visite pièce par pièce.
2. Ouvrir l'en-tête de l'offre et l'aperçu du document.

Attendu : tout est comme avant ce chantier. « Parlez, pièce par pièce. », offre rangée par pièce, bloc « Chantier », « Début des travaux », « dès la fin des travaux ».

Si ça casse : `tests/Offre/ExtracteurActiviteTest.php` garde l'empreinte de la consigne ; `Activite::mots` pour le Bâtiment.

## Scénario 7 : les unités

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/catalogue`, « Nouveau produit » |
| Compte | n'importe lequel |

Gestes :
1. Ouvrir la liste des unités.

Attendu : « mois » et « passage » y sont, avant « forfait ».

Si ça casse : `src/Offre/Saisie.php` (`UNITES`).

## Ce qui n'est PAS couvert

- **La justesse de l'extraction hors du bâtiment.** Aucune mesure : ni pour les prestations (deux phrases de consigne changées), ni pour les zones de l'Extérieur. Décision de Xavier du 2026-11-22 : mise en place d'abord, dictées ensuite. Les scénarios 2 et 5 sont ces dictées.
- **L'activité « Événement »** : écartée, aucun demandeur.
- **Le site, la publicité, la page de connexion** (« Connectez-vous pour importer une visite »), **les courriels, l'administration** (« Artisans ») : hors périmètre, ils restent adressés aux artisans.
- **Le guide de l'onboarding** (étape « Le métier », phrase d'exemple) : chantier `2026-11-21_onboarding`, dans une autre copie. Il devra lire l'activité du compte.
- **Les langues** (tâche 521) : rien n'est traduit. Les mots sont réunis dans `src/Compte/Activite.php`.
- **Mots du bâtiment laissés en place pour un prestataire**, connus : le nom du modèle « Chantier » (écran « Ma marque » et une aide de l'en-tête) ; l'aide de « Ma marque » sous les phrases de Lettre (« la date de la visite et l'adresse du chantier ») ; l'aperçu de « Ma marque » d'un compte sans offre, qui montre une offre de peintre ; le nom du fichier d'un enregistrement gardé (`visite.webm`) ; les règles communes de la consigne de l'IA, écrites avec « travail » et « travaux ».
- **Le cache du navigateur** : `app.js` change. Un téléphone qui garde l'ancien fichier remet « Parlez, pièce par pièce. » au début d'un enregistrement, même pour un prestataire. À regarder au scénario 2 ; recharger la page si c'est le cas, et le signaler.
- **L'écran d'une offre, à 390 px** : la sonde de disposition y relève quatre boutons de suppression qui dépassent de leur formulaire (`button.del`). Ni `templates/_lignes.html.twig` ni les feuilles de style ne sont touchés par ce chantier : le relevé existait avant. À trancher à part (geste « glisser pour retirer », ou défaut).
- **Les prix des quatre catalogues de prestations** : inventés, sources faibles (sites de vendeurs). À corriger par Xavier.
- **Un iPhone et un Android réels** : les écrans ont été regardés dans Chromium, à 390 px et à 1280 px, en local.
- **Le site de test** : rien n'y est déployé tant que Xavier n'a pas donné son accord.

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Analyse statique | aucune erreur | `ETAT.md`, Preuves, phase 6 |
| Suite de tests | 1159 tests, 10 086 assertions, aucun échec (1075 tests avant le chantier) | `ETAT.md`, Preuves, phase 6 |
| Migration, aller et retour | jouée, annulée, rejouée sur la base du chantier | `ETAT.md`, Preuves, phase 6 |
| Recette visuelle locale | douze captures regardées, à 390 px et à 1280 px ; sondes de contraste et de disposition muettes sur les onze écrans du chantier | `ETAT.md`, Preuves, phase 6 |
| Revue | trois lentilles ; un défaut haut corrigé, cinq corrections mineures | `REVUE.md` |
| Site de test | rien de déployé | en attente de l'accord de Xavier |
