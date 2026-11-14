# Protocole de déploiement

La même version passe par le test puis par la production. Un seul geste humain : après la recette,
lancer la mise en production du commit recetté.

## Environnements

| | Développement | Test | Production |
|---|---|---|---|
| Source | Branche `feature/*` | `main` | Commit recetté en test |
| Déclencheur | Poste de travail | Fusion dans `main` | Lancement manuel par Xavier |
| Données | Fictives | Fictives | Réelles |
| Base, compte SSH, secrets | Propres | Propres | Propres |
| Accès de Claude | Autorisé | À confirmer par Xavier | Aucun accès direct, workflow lancé par Xavier |

## Parcours

1. **PR vers `main` :** tests, audit des dépendances, test d'isolation entre clients. Un rouge bloque la fusion.
2. **Fusion dans `main` :** déploiement en test, migrations, contrôle de compatibilité, sonde d'exposition, tests de bout en bout. Si tout est vert, le commit reçoit le statut `controles-test-verts`, et le résumé du workflow affiche le commit à mettre en production.
3. **Recette sur le test :** Xavier vérifie à l'écran. Le commit affiché sur le test est celui qu'il recette.
4. **Lancement du workflow « Mettre en production » :** Xavier donne le commit recetté. Ce lancement vaut validation. Le workflow refuse tout commit sans `controles-test-verts`.

## Déroulé automatique de la mise en production

| # | Étape | En cas d'échec |
|---|---|---|
| 1 | Vérifier que le commit porte `controles-test-verts` | Arrêt, production intacte |
| 2 | Construire la version et l'envoyer dans `releases/<commit>/` | Arrêt, production intacte |
| 3 | Passer en maintenance (`(à préciser)` : mécanisme de la pile) | Arrêt, ancienne version |
| 4 | Sauvegarder la base (chiffrée, hors hébergement) : plus aucune écriture ne peut lui échapper | Fin de maintenance, ancienne version, alerte |
| 5 | Jouer les migrations avec l'utilisateur de structure | Fin de maintenance, ancienne version, alerte. Une migration partielle reste une décision humaine |
| 6 | Basculer `current` vers la nouvelle version | Retour à l'ancienne version, fin de maintenance, alerte |
| 7 | Sortir de maintenance | Nouvelle tentative, puis alerte critique : site indisponible |
| 8 | Lancer la sonde d'exposition avec le commit attendu | Retour à l'ancienne version, nouvelle sonde sur celle-ci, alerte. Si elle est rouge : alerte critique |
| 9 | Marquer la production `prod-<date>` et notifier Xavier | Alerte |

Entre les étapes 7 et 8, de vrais utilisateurs reçoivent la nouvelle version pendant quelques secondes. C'est le prix de contrôles menés sur le site réel.

## Migrations

- **Additives dans une mise en production ordinaire :** nouvelle table, nouvelle colonne nullable ou avec valeur par défaut. L'ancienne version doit continuer à fonctionner sur la nouvelle structure : c'est ce qui rend le retour arrière automatique sûr.
- **Contrôle de compatibilité, en test :** après les migrations et avant la bascule, l'ancienne version doit encore passer sa sonde et ses tests de fumée. Un rouge bloque le commit. C'est ce contrôle qui attrape un `NOT NULL` sans valeur par défaut.
- **Suppression ou renommage :** en deux versions. La première cesse d'utiliser la colonne, la suivante la supprime après une sauvegarde vérifiée.
- **Retour arrière :** il concerne le code. La base n'est jamais restaurée automatiquement, car cela effacerait les données écrites depuis la sauvegarde. Une restauration est une décision humaine.

## Adresses (sous-chantier 18)

- `.env` de production : `ADRESSES=swisstopo`. Sans cette ligne, le registre factice répond : aucune suggestion, aucune vérification, la saisie marche quand même.
- Le serveur doit pouvoir joindre `https://api3.geo.admin.ch` en HTTPS sortant (délai de 3 secondes ; au-delà, l'écran continue sans suggestions).
- Le répertoire des NPA (`src/Adresse/localites.php`) est copié de swisstopo. Le refaire avant une mise en production, il change quelques fois par an : la marche à suivre est en tête de `outils/localites.php`.
- La migration 10 (`Version20261113000002`) est additive. Contrôle de compatibilité fait en local le 2026-11-13 : la version 5de18b4 passe ses 345 tests sur la base migrée.

## QR-facture (sous-chantier 18, lot 6b)

- Extension PHP **bcmath** exigée par sprain/swiss-qr-bill (références RF et QR). En local : `apt install php8.5-bcmath`. Sur le serveur Cloud d'Infomaniak : à activer et à prouver (`php -m | grep bcmath`) avant la première mise en production. Sans elle, `composer install` refuse de s'installer.
- GD n'est pas exigée : le code est dessiné en SVG par bacon/bacon-qr-code.
- Dépendances ajoutées : sprain/swiss-qr-bill 5.3.1, qui tire symfony/validator et symfony/intl, retenus en 7.4 comme le reste de Symfony (composer prenait la 8.1).
- La section de paiement du PDF est en Helvetica, police de base du PDF, sans fichier à installer. Elle ne connaît que les caractères d'Europe de l'Ouest (Windows-1252) : un nom avec un caractère hors de cette table s'y affiche mal, le code QR reste juste.

## Contrôles en production

Aucun envoi de courriel, aucune écriture de données métier, aucun script à effet de bord.

- **`GET /sante`** rend `{"commit":"<sha>","base":"ok","migrations":"ok"}`, sans autre information. Il prouve la bonne version, la base joignable et les migrations connues de cette version jouées. Une base plus récente que le code reste `ok`, sinon le retour arrière serait toujours rouge.
- **Sonde d'exposition avec ses témoins :** chemins interdits, règles de refus actives, HTTPS, en-têtes.
- **Page témoin propre au projet :** `(à préciser)`.

## À prouver sur le vrai hébergement

Ce protocole n'est réputé en place qu'après ces essais sur l'environnement de test :

- [ ] Le journal du job de test montre qu'aucun secret de production ne lui est fourni.
- [ ] La commande forcée de la clé SSH de production refuse toute autre commande.
- [ ] La racine web suit le lien `current/public`. Sinon, adapter l'étape 6.
- [ ] GitHub Actions joint le serveur en SSH par clé.
- [ ] L'utilisateur de structure se connecte depuis le serveur pendant le déploiement.
- [ ] La sonde est verte avec ses témoins, puis rouge après retrait volontaire du `.htaccess`.
- [ ] Une mise en production volontairement cassée déclenche bien le retour arrière.
- [ ] Une migration volontairement incompatible est bloquée par le contrôle de compatibilité.
- [ ] Une sauvegarde est restaurée et chronométrée sur un environnement temporaire.
