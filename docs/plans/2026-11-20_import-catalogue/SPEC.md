# SPEC : import du catalogue depuis d'anciennes offres

Demande de Xavier, 2026-11-20. Sous-chantier de la porte 2 (tâche Origin 467). Cette spec fixe le périmètre du prototype ; les détails d'écran se tranchent sur `MAQUETTE-import.html` (D1 à D12).

## Problème

- Un compte d'essai démarre avec les douze désignations de son métier, prix à 0, ou avec un catalogue vide. L'artisan doit saisir ses prix avant sa première dictée utile.
- Ses prix existent déjà : ils sont dans ses anciennes offres.
- Les concurrents importent un tableur (Chiffr, Opus) ou tirent le catalogue des anciennes offres (Offerto, PDF et Word). Relevé du 18.11, `docs/strategie/concurrents.md`.

## Utilisateurs

- **L'artisan** importe quelques offres et valide ce qui entre dans son catalogue. Droits : son propre catalogue seulement.
- **Xavier** importe ses offres de test sur le prototype.

## Parcours

1. Dans le catalogue, « Importer des offres ».
2. Il choisit des PDF (cinq au plus dans la maquette).
3. L'application lit chaque PDF, puis regroupe les lignes qui disent la même chose.
4. Elle montre ce qu'elle propose d'intégrer : prix à choisir, prix trouvés pour des produits sans prix, nouveaux produits, lignes laissées de côté.
5. L'artisan coche, choisit, corrige, puis ajoute. Rien n'entre au catalogue avant ce geste.
6. Un bilan, avec « Annuler cet import ».

## Décisions de Xavier, 2026-11-20

| Sujet | Décision | Ses mots |
|---|---|---|
| Format | PDF seulement, pas de photo | « un import PDF, pas de photo, juste du PDF » |
| Date de l'offre | ignorée | « Le fait que l'offre elle date de 2023, on s'en fout » |
| Provenance des offres | l'affaire de l'artisan | « S'il veut mettre les offres de son voisin, il met les offres de son voisin » |
| Unités | mètre linéaire, kilo, jour ajoutés | « il faut l'intégrer au système » |
| Ordre | maquette, validation, puis prototype | « une fois que tu as fait la maquette, que je puisse valider la maquette, tu me dev un petit prototype » |
| Modèle du prototype | Qwen en local | « pour faire une estimation du nombre de tokens et peaufiner l'algo » |
| Données du prototype | offres de Xavier, données de clients admises en test | « on est en test, elles vont être supprimées » |

## Périmètre du prototype

1. Lecture du texte du PDF dans le navigateur, avec PDF.js (déjà dans `public/js/vendor/pdfjs-6.5.438/`). Un PDF sans texte est refusé sur place.
2. Un appel au modèle par offre : désignation, unité, prix unitaire de chaque ligne chiffrée ; les rabais, sous-totaux, acomptes et travaux propres à un client sont signalés.
3. Le code regroupe les lignes proches, les compare au catalogue et prépare les questions de prix. Le prix écrit est toujours un prix lu dans une offre.
4. L'écran de relecture de la maquette validée.
5. Une mesure gardée dans le dossier : jetons envoyés et reçus par offre, durée, lignes justes sur lignes attendues.

## Hors périmètre

- Photo et PDF scanné (décision de Xavier). Word et Excel : l'artisan enregistre en PDF.
- CSV et catalogue de grossiste : autre problème, la taille du catalogue (point M16 de la revue du 18.11).
- Dicter son catalogue : proposé par Claude le 20.11, sans réponse.
- Import des clients des anciennes offres.

## Critères de succès (proposés par Claude, à valider)

- 95 % des prix lus sont exacts, sur les offres de test de Xavier.
- Aucun prix proposé qui ne figure dans aucune offre.
- Un PDF scanné est refusé avec sa raison, et la lecture des autres continue.
- `vendor/bin/phpunit` et `vendor/bin/phpstan analyse` passent.

## Contraintes

- `CODING_RULES.md` : SQL dans `src/Donnees/`, cibles de 48 px, 64 px pour la barre d'action, un test rouge avant chaque changement de logique.
- `docs/SECURITE.md` : l'envoi de fichiers et l'appel au modèle sont des surfaces sensibles. Le texte venu du navigateur et la sortie du modèle sont des entrées non fiables, validées côté serveur. Isolation par artisan.
- Unités : la liste de `Saisie::UNITES`. Une unité lue hors de la liste donne une ligne à corriger.

## Risques

- **Colonnes d'un tableau PDF :** PDF.js rend le texte dans l'ordre du fichier. Un tableau peut sortir colonne par colonne. À mesurer sur les offres de Xavier.
- **Formulations proches :** « Dispersion murs 2 couches » et « Peinture murs » se regroupent mal par le code seul. Le modèle devra peut-être y aider, avec le raisonnement activé.
- **Qwen 3.8 27B sans raisonnement :** 10 à l'indice d'Artificial Analysis v5.0.1, 20 avec raisonnement au maximum (lu le 20.11, non recontrôlé par Claude). La tâche d'import demande plus de jugement que l'extraction d'une dictée.
- **Données de clients dans les offres de test :** si Claude lit ces PDF, leur contenu passe par Anthropic. Question posée à Xavier le 20.11.
- **Un prix faux validé sans regarder** part dans de vraies offres. La relecture reste obligatoire.
