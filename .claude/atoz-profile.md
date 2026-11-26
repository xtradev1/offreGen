# Profil de chantier

Décrit l'environnement du projet pour le skill `chantier`. Généré en phase 0 le 2026-11-07, à
valider par Xavier, mis à jour explicitement quand l'environnement change.

**Ce fichier est versionné : aucun identifiant, aucun mot de passe, aucun jeton.** Ils vont dans
`atoz-profile.local.md`, ignoré par git (`*.local.*` dans `.gitignore`).

## Règles opposables

| Fichier | Portée |
|---|---|
| `CODING_RULES.md` | pile, organisation, style, ergonomie, tests, à lire avant toute tâche touchant au code |
| `docs/SECURITE.md` | accès, données, dépendances, déploiement, modèles de langue |
| `docs/DEPLOIEMENT.md` | parcours développement, test, production ; migrations |
| `docs/plans/2026-11-05_mesures-ia/cadrage-produit.md` | architecture de la section 5 (deux appels au modèle, le code calcule) |

## Commandes

Aucune n'existe encore : elles naissent avec le squelette (sous-tâche Origin 318).

| Usage | Commande | Où ça tourne |
|---|---|---|
| Lint / parse | `vendor/bin/phpstan analyse` (à créer) | local, WSL, `~/offreGen` |
| Suite de tests | `vendor/bin/phpunit` (à créer) | local, WSL ; en CI GitHub une fois les workflows écrits |
| Build | `composer install --no-dev` (déploiement) | CI, plus tard |
| Serveur local | `php -S localhost:8000 -t public` | local, WSL, ouvert depuis le navigateur Windows |

Depuis Windows, une commande WSL passe par un script `.sh` lancé par
`wsl -d Ubuntu -e sh /mnt/c/...` (les guillemets cassent par PowerShell).

## Emplacements

| Quoi | Où |
|---|---|
| Copie de travail | `~/offreGen` dans WSL (`\\wsl.localhost\Ubuntu\home\xavie\offreGen`) |
| Dossiers de chantier | `docs/plans/AAAA-MM-JJ_<slug>/` |
| Feuille de route | `PROJECT_ROADMAP.md` |
| Tests | `tests/` (à créer) |
| CSS / design existant à réutiliser | `docs/plans/2026-11-06_maquette-ux/MAQUETTE-offre-vocale.html` (maquette v4 validée) |

## Ce que Claude ne peut pas faire lui-même

- Toucher la production : aucun accès, aucune requête, aucun déploiement (mémoire « Production »).
- Fusionner une PR dans `main` ni pousser sur `main` : Xavier fusionne.
- Créer le site de test, la base, les comptes chez Infomaniak, ni le jeton d'API.
- Accéder au site de test : à décider par Xavier.
- Fournir de vrais enregistrements de visite.
- Tester sur un vrai iPhone ou un vrai Android.

## Recette

| | |
|---|---|
| URL de base | `http://localhost:8000` en local ; site de test à créer |
| Comptes | voir `atoz-profile.local.md` |

## Surfaces sensibles

Un diff qui touche une de ces zones déclenche la revue lourde en phase 7 (trois lentilles plus
`owasp-security`, et Fable pour connexion, droits, filtre par client et appels de fonctions).

- Connexion, sessions, CSRF, droits par route.
- Couche d'accès aux données et filtre par client (isolation).
- Import de fichiers audio : type, taille, stockage hors de `public/`, conservation.
- Appels au modèle de langue : sortie traitée comme non fiable, appels de fonctions en liste blanche.
- Secrets, `.env`, configuration serveur, `.htaccess`, workflows GitHub, déploiement.
- Envoi de courriels.
