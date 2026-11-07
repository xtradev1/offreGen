# CODING_RULES

Règles opposables pour ce projet. Toute entorse se justifie par un commentaire dans le code et une
entrée dans les décisions du `PROJECT_ROADMAP.md`. Les règles transverses (tirets longs, preuve par
la donnée, recette par l'interface, mots exacts) sont dans le CLAUDE.md global et ne sont pas
répétées ici.

## Pile
- Langage et version : PHP 8.5, disponible sur l'hébergement Infomaniak selon Xavier (2026-11-05). Le développement et le test tournent sur la même version.
- Framework : aucun. Le code de l'application reste du PHP lisible, assemblé dans ce dépôt.
- Bibliothèques retenues : `symfony/http-foundation`, `routing`, `validator`, `security-csrf`, `rate-limiter`, `http-client`, `mailer`, `dotenv` (branche 7.4 LTS), `twig/twig`, `doctrine/migrations`, `monolog/monolog`, `spomky-labs/otphp` ou `pragmarx/google2fa`. En développement : `phpunit/phpunit`, `phpstan/phpstan`.
- Toute autre dépendance se justifie en une ligne dans `PROJECT_ROADMAP.md`. Aucun paquet ne donne la structure de l'application.
- Base de données, accès : MySQL ou MariaDB chez Infomaniak (?), PDO et requêtes préparées, toujours par la couche d'accès aux données.
- Environnement d'exécution local : WSL, Ubuntu 24.04, copie de travail dans `~/offreGen`. PHP 8.5.12 (dépôt ppa:ondrej/php), Composer 2.11.1 (installeur officiel, signature vérifiée), MariaDB 10.11.14. Installé et vérifié le 2026-11-07. MariaDB se démarre par `sudo service mariadb start`.

## Organisation des fichiers
- (à préciser : arborescence, où vont les modèles, les contrôleurs, les vues, les scripts)
- Un fichier, une responsabilité. Pas de fonction dupliquée : chercher l'existant avant d'écrire (`grep`).

## Style
- Encodage UTF-8 sans BOM, fins de ligne LF.
- Nommage : (à préciser : langue des identifiants, casse)
- Pas de code mort, pas de commentaire qui paraphrase le code.
- Textes visibles en français, typographie française, pas de tiret long.

## Ergonomie
- L'application s'utilise sur un téléphone, sur un chantier : grandes zones de toucher, lisible en plein jour.
- Une action fréquente se fait en un clic.
- Utilisable au pouce, d'une main : les actions principales sont dans le tiers bas de l'écran, les confirmations s'ouvrent en bas, chaque cible mesure 44 px au moins. Le haut de l'écran ne porte que des informations et des actions secondaires.
- Supprimer une ligne d'offre : un clic, puis « Annuler » reste visible quelques secondes. Choix de Xavier sur la maquette du 2026-11-06, à confirmer par les tests avec des artisans.
- Une ligne incertaine s'affiche « À vérifier », sans pourcentage de confiance et sans rappel de la dictée (choix de Xavier, 2026-11-06).
- Le document envoyé au client ne montre ni la marge, ni le fournisseur d'une ligne libre, ni le motif ou le pourcentage d'une majoration : la ligne garde son nom, à son nouveau prix (Xavier, 2026-11-06).
- Une confirmation est toujours demandée pour ce qui ne se rattrape pas : envoyer une offre au client, supprimer une offre entière.
- L'audio arrive de deux façons : un fichier enregistré avec l'application du téléphone puis importé (prioritaire), ou un enregistrement dans l'application. Le traitement est asynchrone : l'artisan est prévenu quand l'offre est prête.
- Toute correction vocale a son équivalent à l'écran, qui ne coûte aucun appel au modèle.

## Données et sécurité
- `docs/SECURITE.md` est opposable : à lire avant toute tâche touchant aux accès, aux données, aux dépendances ou au déploiement.
- Aucun secret dans le code. Fichiers `*.local.*` et `.env` ignorés par git.
- Toute entrée utilisateur est validée côté serveur. Requêtes préparées.
- **Droits refusés par défaut :** une route sans droit déclaré répond 403, et un test parcourt toutes les routes.
- **Accès aux données en un seul endroit :** cette couche exige l'identifiant du client, et un contrôle automatique rejette tout SQL écrit ailleurs.
- **Isolation testée :** le client A tente d'atteindre chaque donnée du client B, route par route, fichiers et exports compris.
- Sessions et CSRF : (à préciser au moment d'écrire la connexion).

## Tests et preuve
- Un changement de logique s'accompagne d'un test qui échoue avant et passe après.
- « Ça marche » = commande exécutée, sortie collée. Pas de claim sans evidence.
- Recette d'interface à la souris (Playwright), pas par appel direct à l'API.

## Migrations et déploiement
- `docs/DEPLOIEMENT.md` est opposable : parcours, migrations additives, contrôles et retour arrière.
- Migrations : `vendor/bin/doctrine-migrations migrations:migrate`, vérification par `migrations:up-to-date`. Commandes à prouver à la première installation.
- Un `ADD COLUMN IF NOT EXISTS` n'élargit pas une colonne existante ; élargir = `MODIFY`.

## Documentation vivante
- `PROJECT_ROADMAP.md` : décisions et prochains pas. Mis à jour par `/roadmap-sync` après tout travail significatif.
- `docs/plans/<date>_<slug>/` : plans et ETAT.md des chantiers. Journaux de mémoire dans `docs/plans/_memoire-archivee/`.
- Mémoire de session : voir la section « Mémoire » du CLAUDE.md global.
