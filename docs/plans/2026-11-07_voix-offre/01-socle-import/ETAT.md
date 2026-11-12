---
chantier: De la voix à l'offre, 01 socle et import
slug: 2026-11-07_voix-offre/01-socle-import
phase: 8
statut: gate_ouvert
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Recette de Xavier en local (`En attente de toi`), puis commit et PR sur son accord. Ensuite, sous-chantier 02 (site de test).

## En attente de toi

- Recette en local selon `RECETTE.md` (huit gestes, un vrai enregistrement de ton téléphone, sans client). Dis-moi ce qui ne va pas, et le format et la taille du fichier.
- Accord pour commiter la branche `chantier-voix-offre` (cadrage et sous-chantier 01), la pousser et ouvrir la PR.
- Accord pour une revue indépendante du diff avant la PR (surfaces sensibles : connexion, isolation, import, secrets), environ 4 avis Opus courts à moyens, moins de 10 $.

## Journal

- 2026-11-07 : sous-chantier ouvert. Spec parente : `../SPEC.md`.
- 2026-11-07 : phase 3 sautée. Les vues liste, nouvelle, traitement et offre existent dans la maquette v4 validée ; seule la page de connexion est neuve, deux champs et un bouton en bas, dans le même style. Elle sera regardée en capture au lot 7.
- 2026-11-07 : phase 4, `PLAN.md` écrit au format du skill `chantier` (contrat, signatures sans corps, décisions fragiles, 8 lots). Le code complet par étape, que demande `writing-plans`, n'est pas écrit : l'exécution reste dans la session principale.
- 2026-11-07 : lot 1 fait. `security-csrf` 7.4 tirait `security-core` et `password-hasher` 8.1 : les deux sont figés en 7.4 dans `composer.json` (règle « branche 7.4 LTS »).
- 2026-11-07 : écarts au plan, lot 1 : `Route` porte une `\Closure` au lieu d'un nom de contrôleur (typage vérifiable par PHPStan) ; `Noyau` reçoit un journal injectable (le test vérifie que l'erreur est journalisée sans être montrée).
- 2026-11-07 : Xavier : « enchaîne jusqu'au dernier lot, je t'arrête pas ». Lots 2 à 8 enchaînés sans gate.
- 2026-11-07 : lot 2 fait. Bases et utilisateurs créés par un script root hors dépôt (`wsl -u root`) ; mots de passe générés dans `.env`, jamais affichés. `doctrine/migrations` 3.9 et DBAL 4 installés en moins d'une heure : la décision fragile 3 tient. `symfony/string` arrivait en 8.1, figé en 7.4.
- 2026-11-07 : ffmpeg 6.1 installé dans Ubuntu (`apt`, en root) pour fabriquer de vrais fichiers audio de test au lot 5.
- 2026-11-07 : lot 3 fait. Écarts au plan : la colonne `enregistrement.offre_id` est supprimée (doublon de `offre.enregistrement_id`, deux vérités possibles) ; le plafond démo compte les imports reçus du jour (`DepotEnregistrements::recusDepuis`), parce que le coût naît à l'import, pas à l'offre ; `symfony/clock` 7.4 ajouté pour une horloge que les tests figent.
- 2026-11-07 : `FileTraitement` : chaque écriture du traitement exige le jeton du verrou ; un traiteur qui a perdu son verrou lève `VerrouPerdu` et sa transaction est annulée.
- 2026-11-07 : lot 4 fait. Le noyau contrôle, dans l'ordre : droit, session, connexion (routes `artisan`), taille du corps (413 quand PHP a jeté un envoi trop gros, au lieu d'un 403 trompeur), jeton CSRF. Un courriel inconnu est vérifié contre un hachage factice aux paramètres des vrais : même durée de réponse. Session de 14 jours, cookie `HttpOnly`, `SameSite=lax`, `secure` automatique en HTTPS.
- 2026-11-07 : compte de développement fictif `dev@exemple.ch` créé dans `offregen_dev` ; son mot de passe est dans `~/.offregen-dev-compte` (WSL, hors dépôt).
- 2026-11-07 : lot 5 fait. Types relevés avec `finfo` sur des fichiers produits par ffmpeg : `audio/x-m4a`, `video/mp4` (m4a Android de marque isom), `audio/mpeg`, `audio/ogg` (vorbis et opus), `audio/x-wav`, `audio/AMR` (en majuscules), `video/3gpp`. L'AMR de test est écrit à la main (ffmpeg d'Ubuntu n'a pas d'encodeur AMR), ffprobe le lit : 1,03 s. Le format 3gp est ajouté à la liste de la SPEC.
- 2026-11-07 : lot 6 fait. `Totaux` arrondit chaque ligne et la TVA au centime, le TTC aux 5 centimes, l'écart affiché à part ; la maquette n'arrondissait que le TTC, ses montants affichés ne s'additionnaient pas toujours. Titre de l'offre factice : « Visite du JJ.MM.AAAA ».
- 2026-11-07 : défaut trouvé au lot 6 par l'essai sur le vrai serveur, pas par les tests : avec `variables_order = "GPCS"` (Ubuntu), une variable posée par le système n'arrive jamais dans `$_ENV` ; sous `php -S`, elle n'est que dans `getenv()`. `TRAITEMENT_IMMEDIAT=0` était ignoré sans bruit, et le serait chez l'hébergeur pour toute variable posée par `SetEnv`. Corrigé (`Conteneur::lireEnvironnement` lit `$_ENV`, `getenv()`, `$_SERVER` hors `HTTP_*`), test ajouté (`ConteneurTest`).
- 2026-11-07 : non vérifié au lot 5 : l'import d'un vrai m4a de téléphone par le navigateur. Il faut un enregistrement de Xavier (fictif, sans client) : à faire à la recette.
- 2026-11-07 : lot 7 fait. Écart à l'ordre du plan : les captures du lot 7 sont prises après le lot 8, pour montrer le catalogue fictif d'un compte démo. Défauts vus seulement à l'image et corrigés : titre de l'offre affiché deux fois, icônes des étapes de largeurs différentes (texte décalé), nom d'entreprise de démo doublé « (fictive) (compte de démonstration) » (donnée de développement, renommée).
- 2026-11-07 : lot 8 fait. Défaut trouvé en écrivant la purge : un enregistrement en `echec` ne recevait jamais de date de suppression, son audio serait resté pour toujours. Il reçoit maintenant 30 jours, comme une offre prête.
- 2026-11-07 : défaut introduit par moi et rattrapé par le parcours : le script de typographie a collé une espace insécable après `$uuid` dans `bin/traiter-file` ; PHP l'a lue comme une partie du nom de variable. Corrigé, `tests/Regles/CommandesTest.php` ajouté (aucune variable collée à un caractère non ASCII, commandes sans avertissement).
- 2026-11-07 : sonde d'exposition en local : rouge d'abord. Les en-têtes de sécurité n'existaient que dans `.htaccess`, et `php -S` servait `/.htaccess` comme la route « / » (303). Corrigé : `Noyau` pose les mêmes en-têtes, `tests/Http/EntetesTest.php` les compare au `.htaccess` ; sous `php -S`, un fichier de `public/` hors liste blanche répond 404. Sonde verte en local, témoins et HTTPS sautés (sans objet sur `php -S`).
- 2026-11-07 : le frein de connexion a bloqué mon propre parcours Playwright (cinq échecs de 127.0.0.1 en un quart d'heure, sixième essai refusé et noté) : preuve sur le vrai serveur. Échecs fictifs effacés de `offregen_dev` pour rejouer le parcours.
- 2026-11-07 : phases 6 et 7 : vérification faite lot par lot (sorties collées), revue indépendante pas lancée. Le diff touche quatre surfaces sensibles du profil (connexion et CSRF, isolation, import audio, secrets) : la revue lourde est à lancer avant la production pilote, au plus tard au sous-chantier 09, et à proposer à Xavier avant la PR.
- 2026-11-07 : phase 8 : gate « prod » ouvert. Ici, il veut dire « recette de Xavier en local puis commit » ; aucune mise en ligne n'est prévue avant le sous-chantier 02.

## Décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-11-07 | Sans `.env`, l'application se croit en production. | Un oubli coupe les erreurs détaillées au lieu de les montrer. |
| 2026-11-07 | PHPStan niveau 8 sur `src`, `config`, `public/index.php`, `tests`. | Niveau le plus haut qui reste lisible sur un code neuf. |
| 2026-11-07 | Le serveur local sert les fichiers statiques par `public/index.php` (mode `cli-server`), en imitant la liste blanche du `.htaccess`. | Même comportement qu'Apache pour les essais locaux. |
| 2026-11-07 | Les dates sont calculées en PHP (horloge injectable) et stockées en UTC ; la session MySQL est en `+00:00`. | Un `NOW()` dans le SQL ne se fige pas en test, et le fuseau du serveur de l'hébergeur est inconnu. |
| 2026-11-07 | Les tests refusent toute base dont le nom ne finit pas par `_test`. | Ils vident les tables : une erreur de `.env` ne doit pas effacer la base de développement. |
| 2026-11-07 | Une ligne d'offre ne garde un `produit_id` que s'il appartient à l'artisan. | L'extraction (un modèle) peut se tromper d'identifiant ; l'isolation ne dépend pas de lui. |
| 2026-11-07 | Le contrôle du plafond démo et de la connexion précède le contrôle CSRF. | Une session expirée mène à la page de connexion plutôt qu'à « formulaire expiré ». |
| 2026-11-07 | Un essai bloqué (429) est noté au journal comme un échec. | Le journal dit tout ce qui a été tenté ; la fenêtre de 15 minutes glisse tant que les essais continuent. |
| 2026-11-07 | Le courriel « offre prête » part après la validation de l'offre ; son échec est journalisé, il ne compte pas comme tentative. | Une offre prête ne se défait pas pour un serveur de courriel en panne. |
| 2026-11-07 | `public/index.php` pose `ignore_user_abort(true)` et 300 s de temps limite avant le travail d'après réponse. | L'artisan qui ferme son navigateur ne doit pas couper l'envoi de l'audio à la transcription. |
| 2026-11-07 | Un prix à 0 veut dire « prix manquant » (écran : « Prix ? »). | La colonne n'accepte pas de vide et le catalogue de la maquette a un produit sans prix. À revoir au sous-chantier 05 (prix nullable). |
| 2026-11-07 | La purge ne supprime que l'audio ; l'offre et la transcription restent. | La décision de Xavier vise l'audio. La durée de la transcription reste à décider (roadmap, points à clarifier). |
| 2026-11-07 | La remise à zéro d'un compte démo efface les fichiers après la validation en base. | Une erreur en base ne laisse pas de lignes privées de leur audio. |

## Artefacts

| Chemin | Phase | État |
|---|---|---|
| `PLAN.md` | 4 | écrit |
| `composer.json`, `composer.lock`, `phpunit.xml`, `phpstan.neon`, `.env.example` | 5, lot 1 | fait |
| `public/index.php`, `config/routes.php`, `src/Http/{Noyau,Route,Conteneur,ApresReponse}.php` | 5, lot 1 | fait |
| `templates/{base,connexion,erreur}.html.twig`, `public/css/app.css` | 5, lot 1 | fait, pas encore regardé en capture |
| `tests/Http/NoyauTest.php` | 5, lot 1 | fait |
| `migrations.php`, `migrations-db.php`, `migrations/Version20261107000001.php` | 5, lot 2 | fait, joué sur `offregen_dev` et `offregen_test` |
| `src/Donnees/*.php` | 5, lot 3 | fait |
| `tests/Donnees/{SchemaTest,IsolationDepotsTest}.php`, `tests/Regles/SqlHorsDonneesTest.php`, `tests/Outils/{BaseDeTest,Jeu}.php` | 5, lots 2 et 3 | fait |
| `src/Compte/*`, `src/Http/{Garde,Formats}.php`, `src/Http/Controleur/{Connexion,Accueil}Controleur.php`, `bin/creer-compte` | 5, lot 4 | fait |
| `src/Audio/{ValidateurAudio,AudioValide,AudioRefuse,StockageAudio,Uuid}.php`, `src/Http/Controleur/ImportControleur.php`, `templates/import.html.twig`, `public/js/app.js`, `tests/fixtures/audio/*` | 5, lot 5 | fait |
| `src/Audio/{Transcripteur,TranscripteurFactice,Traiteur}.php`, `src/Offre/*`, `bin/traiter-file` | 5, lot 6 | fait |
| `src/Http/Controleur/{Accueil,Enregistrement,Offre}Controleur.php`, `src/Http/PageIntrouvable.php`, gabarits, `public/css/app.css` | 5, lot 7 | fait, regardé en capture |
| `src/Catalogue/CatalogueFictif.php`, `src/Audio/Purge.php`, `bin/reinitialiser-demo`, `bin/purger-audio` | 5, lot 8 | fait |
| `tests/Http/{IsolationRoutes,Pages,Entetes,Conteneur}Test.php`, `tests/Compte/DemoTest.php`, `tests/Audio/PurgeTest.php`, `tests/Regles/CommandesTest.php` | 5, lots 7 et 8 | fait |
| `captures/*.jpg` | 6 | 12 captures du parcours cliqué, CSP active |

## Preuves

### Lot 1

```
$ vendor/bin/phpunit --colors=never
........                                                            8 / 8 (100%)
OK (8 tests, 12 assertions)

$ sed (contrôle du droit remplacé par if (false)) ; vendor/bin/phpunit
1) OffreGen\Tests\Http\NoyauTest::testRouteSansDroitRend403
2) OffreGen\Tests\Http\NoyauTest::testDroitInconnuRend403
Tests: 8, Assertions: 12, Failures: 2.
(fichier rétabli, OK (8 tests, 12 assertions))

$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors

$ composer audit
No security vulnerability advisories found.

$ php -S 127.0.0.1:8000 -t public public/index.php ; curl -w '%{http_code} %{content_type}'
/connexion 200 text/html; charset=UTF-8
/css/app.css 200 text/css; charset=UTF-8
/nulle-part 404 text/html; charset=UTF-8
/sonde-temoin.php 404 text/html; charset=UTF-8
/.env 404 text/html; charset=UTF-8
```

### Lot 2

```
$ wsl -u root sh lot2-bases.sh   (extrait, mots de passe absents de la sortie)
GRANT SELECT, INSERT, UPDATE, DELETE ON `offregen_dev`.* TO `offregen_app`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE ON `offregen_test`.* TO `offregen_app`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER ON `offregen_dev`.* TO `offregen_structure`@`localhost`
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER ON `offregen_test`.* TO `offregen_structure`@`localhost`

$ APP_ENV=dev vendor/bin/doctrine-migrations migrate --no-interaction
[notice] finished in 178.4ms, used 8M memory, 1 migrations executed, 6 sql queries
 [OK] Successfully migrated to version: OffreGen\Migrations\Version20261107000001
$ APP_ENV=dev vendor/bin/doctrine-migrations migrations:up-to-date
 [OK] Up-to-date! No migrations to execute.
$ APP_ENV=test vendor/bin/doctrine-migrations migrate --no-interaction
[notice] finished in 184.9ms, used 8M memory, 1 migrations executed, 6 sql queries
$ APP_ENV=test vendor/bin/doctrine-migrations migrations:up-to-date
 [OK] Up-to-date! No migrations to execute.

$ vendor/bin/phpunit
OK (11 tests, 21 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
```

### Lot 3

```
$ vendor/bin/phpunit
OK (19 tests, 37 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors

== mutation 1 : requête dans src/Http (fichier src/Http/Intrus.php)
+    0 => 'src/Http/Intrus.php : « SELECT id FROM »',
Tests: 1, Assertions: 2, Failures: 1.
== mutation 2 : parUuid sans filtre artisan_id
1) OffreGen\Tests\Donnees\IsolationDepotsTest::testEnregistrementDeBInvisiblePourA
Tests: 7, Assertions: 13, Failures: 1.
== rétabli
OK (19 tests, 37 assertions)
```

### Lot 4

```
$ vendor/bin/phpunit
OK (31 tests, 69 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors

Serveur réel (php -S), curl avec fichier de cookies, compte dev@exemple.ch :
== doublon refusé
Un compte existe déjà pour dev@exemple.ch.
code=1
== GET / sans session
303 -> http://127.0.0.1:8000/connexion
jeton lu : 123 caractères
== mauvais mot de passe
200
Courriel ou mot de passe incorrect
== sans jeton
403
== bon mot de passe
303 -> http://127.0.0.1:8000/
== GET / connecté
200
Peinture Démo Sàrl
== déconnexion
303 -> http://127.0.0.1:8000/connexion
== GET / après
303
Set-Cookie: offregen=<id>; expires=Sat, 21 Nov 2026 15:36:56 GMT; Max-Age=1209600; path=/; HttpOnly; SameSite=lax

== mutation : contrôle CSRF retiré
1) OffreGen\Tests\Compte\ConnexionTest::testPostSansJetonRend403
2) OffreGen\Tests\Compte\ConnexionTest::testPostAvecFauxJetonRend403
Tests: 31, Assertions: 68, Failures: 2.
== mutation : session non régénérée
1) OffreGen\Tests\Compte\ConnexionTest::testBonMotDePasseRedirigeEtRegenereLaSession
Tests: 31, Assertions: 67, Failures: 1.
OK (31 tests, 69 assertions)
```

### Lots 5 et 6

```
$ vendor/bin/phpunit
OK (67 tests, 175 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors

== mutation : type inconnu accepté
1) OffreGen\Tests\Audio\ValidateurAudioTest::testTexteRenommeEnM4aRefuse
2) OffreGen\Tests\Http\ImportTest::testFauxAudioRefuse422SansRienGarder
== mutation : verrou pris même s il est tenu
1) OffreGen\Tests\Audio\TraiteurTest::testUnSeulTraiteurTientLeVerrou
== mutation : demi arrondi vers le bas
1) OffreGen\Tests\Offre\TotauxTest::testDemiCentimeDeLigneArrondiVersLeHaut
2) OffreGen\Tests\Offre\TotauxTest::testDemiCentimeDeTvaArrondiVersLeHaut
3) OffreGen\Tests\Offre\TotauxTest::testMontantNegatifArrondiEnSEloignantDeZero
== mutation : six tentatives au lieu de cinq
1) OffreGen\Tests\Audio\TraiteurTest::testCinqEchecsDonnentEchec
== mutation : plafond démo ignoré
1) OffreGen\Tests\Http\ImportTest::testCompteDemoAuPlafond429
== rétabli
OK (64 tests, 172 assertions)

== serveur réel, traitement immédiat
import m4a : 303 http://127.0.0.1:8000/enregistrements/290ef0b0-7e69-4fe6-af3b-4d964eca0651
pret	0	audio/x-m4a	1
Visite du 07.11.2026	2
20261107-153840-b3661ea3.eml
import texte : 422
== serveur réel, traitement immédiat coupé (TRAITEMENT_IMMEDIAT=0)
import amr : 303 http://127.0.0.1:8000/enregistrements/c0599e71-7635-4efd-89bd-ff25c6538401
recu
$ bin/traiter-file
c0599e71-7635-4efd-89bd-ff25c6538401 : pret
1 enregistrement(s) repris.
$ bin/traiter-file (deuxième passage)
0 enregistrement(s) repris.
```

### Lots 7 et 8

```
$ vendor/bin/phpunit
OK (89 tests, 277 assertions)
$ vendor/bin/phpstan analyse
 [OK] No errors
$ composer audit
No security vulnerability advisories found.

== mutation : parId sans filtre artisan
1) OffreGen\Tests\Http\IsolationRoutesTest::testLaRessourceDeBRepond404EtCelleDeA200
== mutation : route à paramètre sans cas
1) OffreGen\Tests\Http\IsolationRoutesTest::testChaqueRouteAParametreAUnCas
== mutation : compte réel remis à zéro
1) OffreGen\Tests\Compte\DemoTest::testCompteQuiNEstPasDemoRefuseSansRienEffacer
== mutation : purge un audio pas encore échu
1) OffreGen\Tests\Audio\PurgeTest::testSeulLAudioEchuEstSupprimeLOffreReste
== rétabli
OK (84 tests, 245 assertions)

$ bin/reinitialiser-demo demo-peintre@exemple.ch peintre
Compte demo-peintre@exemple.ch remis à zéro : catalogue peintre (12 produits), 1 fichier(s) audio supprimé(s).
$ bin/reinitialiser-demo dev@exemple.ch peintre
dev@exemple.ch n'est pas un compte de démonstration : rien n'est effacé.
code=1
$ bin/reinitialiser-demo demo-peintre@exemple.ch boulanger
Métier inconnu : boulanger. Choix : peintre, menuisier, sanitaire.
code=1
$ bin/purger-audio
0 fichier(s) audio supprimé(s), 0 erreur(s).

$ vendor/bin/phpunit --filter CommandesTest   (avant correction de bin/traiter-file)
1) OffreGen\Tests\Regles\CommandesTest::testAucuneVariableColleeAUnCaractereNonAscii
+    0 => 'bin/traiter-file:27',
(après correction) OK (2 tests, 8 assertions)

Parcours cliqué Playwright (Chromium, 390 px puis 1280 px sombre, CSP active, traitement immédiat coupé) :
01-connexion : /connexion largeur 390/390
02-connexion-refusee : /connexion largeur 390/390
03-liste-vide : / largeur 390/390
04-import : /import largeur 390/390
import du texte : 422, « Ce fichier n'est pas un enregistrement audio reconnu. Formats acceptés : m4a, mp3, ogg, opus, wav, amr, 3gp. »
06-etat-en-cours : /enregistrements/f78c75a1-4760-4bab-9f99-f0bd3a6ac999 largeur 390/390
bin/traiter-file : f78c75a1-4760-4bab-9f99-f0bd3a6ac999 : pret | 1 enregistrement(s) repris.
07-etat-pret : (rafraîchissement automatique, bouton « Voir l'offre »)
08-offre : /offres/9 largeur 390/390
09-liste : / largeur 390/390
10-introuvable : /offres/999999999 largeur 390/390
11-offre-bureau-sombre : /offres/9 largeur 1280/1280
12-liste-bureau-sombre : / largeur 1280/1280
déconnexion : /connexion
erreurs JavaScript : trois ressources refusées, toutes attendues (favicon.ico 404, import du texte 422, page 404 demandée)

$ sonde-exposition.sh http://127.0.0.1:8000   (SONDE_LOCAL=1, SONDE_TEMOINS=aucun)
SAUTE  témoins de refus : règles de refus non prouvées
SAUTE  http vers https et HSTS (banc local)
SONDE VERTE
```
