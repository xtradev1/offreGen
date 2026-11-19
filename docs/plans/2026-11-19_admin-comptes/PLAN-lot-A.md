# Plan du lot A : administration, rôles, démos

Écrit le 2026-11-19. Entrées : maquettes v2 à v5 de ce dossier et réponses de Xavier (`ETAT.md`, section Décisions). Le lot B (formules, mois gratuit, facture, rappels) attend les réponses à la v5 et les tâches Origin 461 et 462 : il aura son propre plan.

Périmètre du lot A, tout validé :
- le champ d'adresse des pages de mot de passe (défaut Bitwarden) ;
- la partie `/admin`, ses trois rôles (administrateur, modérateur, commercial), l'équipe ;
- la liste et la fiche des artisans, « offrir des offres », le fil des événements ;
- la démo : métier, QR, lien par message, ruban, fenêtre des deux derniers jours, fin, « Demander un appel » ;
- le suivi du commercial, « À aider » du modérateur, le courriel de demande d'appel.

Hors du lot A :
- « Passer à l'essai », formules, mois gratuit (lot B). Tant que le lot B n'est pas livré, le ruban et la fenêtre de la démo ne portent que « Demander un appel ».
- La transformation d'une démo en compte payant ou invité (question G4 de la v5).
- Le quota par mois de l'espace artisan d'un membre de l'équipe (R4) : le quota mensuel n'existe pas encore (tâche 461). En attendant, son espace a un plafond total (`offres_max`) qu'il ne peut pas changer lui-même.
- Le second facteur pour l'équipe : avant la production, pas dans ce lot. À inscrire au roadmap.
- Le contenu d'une offre ouvert 48 h par l'artisan (R2) : le lot A ne montre jamais de contenu, seulement le suivi.

## Contrat technique

### Droits

Trois droits de route s'ajoutent à `Route::PUBLIC`, `Route::ARTISAN`, `Route::ADMIN` (`src/Http/Route.php`) :

| Droit | Qui passe | Les autres |
|---|---|---|
| `Route::EQUIPE` (à créer) | administrateur, modérateur, commercial | 404, avec ou sans session |
| `Route::MODERATION` (à créer) | administrateur, modérateur | 404 |
| `Route::ADMIN` (existe) | administrateur | 404 |

Le rôle se lit en base à chaque requête (`artisan.administrateur`, `artisan.role`), comme `estAdministrateur` aujourd'hui (`src/Compte/Authentification.php:142`). Un droit de route ne suffit pas pour un commercial : chaque lecture et chaque écriture sur un artisan passe par `PorteeEquipe`, qui restreint à `cree_par = lui`. Un artisan hors portée répond 404.

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie |
|---|---|---|---|---|
| `/admin` | GET | EQUIPE | | renvoi 303 vers l'accueil du rôle : `/admin/tableau`, `/admin/a-aider`, `/admin/suivi` |
| `/admin/artisans` | GET | EQUIPE | `type` (tous, demos, clients), `q` | liste dans la portée |
| `/admin/artisans/{id}` | GET | EQUIPE | | fiche : jauge, fil, actions du rôle |
| `/admin/artisans/{id}/offres` | POST | EQUIPE | `ajout` (2, 5 ou 10) | 303 vers la fiche ; 422 au-delà de la limite du rôle |
| `/admin/artisans/{id}/zero` | POST | EQUIPE | `metier` | 303 ; démo seulement |
| `/admin/artisans/{id}/fermer` | POST | EQUIPE | | 303 ; démo seulement, `demo_fin` = maintenant |
| `/admin/demos/nouvelle` | GET | EQUIPE | | formulaire métier et nom |
| `/admin/demos` | POST | EQUIPE | `metier`, `entreprise` facultatif | 303 vers `/admin/artisans/{id}/qr` ; 429 après 10 démos du jour pour un commercial |
| `/admin/artisans/{id}/qr` | GET | EQUIPE | | page « Démo prête » : QR en SVG dans la page, lien de 1 h émis à l'affichage |
| `/admin/artisans/{id}/message` | POST | EQUIPE | `nom` facultatif | page « Envoyer la démo » : texte prêt, lien de 24 h émis à cet instant |
| `/d/{jeton}` | GET | PUBLIC | | page qui s'envoie seule en POST ; 410 si le lien ne vaut plus |
| `/d/{jeton}` | POST | PUBLIC | `_csrf` | session ouverte sur le compte de démo, 303 vers `/` ; 410 sinon |
| `/appel` | GET | ARTISAN | | formulaire « Demander un appel » |
| `/appel` | POST | ARTISAN | `telephone`, `moment` (matin, midi, soir), `question` facultatif | 303 vers la confirmation ; courriel au créateur après la réponse |
| `/admin/suivi` | GET | EQUIPE | | « À rappeler », « Bientôt la fin », compteurs, dans la portée |
| `/admin/appels/{id}/fait` | POST | EQUIPE | | 303 ; 404 hors portée |
| `/admin/a-aider` | GET | MODERATION | | échecs de traitement, plafonds atteints, accès jamais ouverts |
| `/admin/tableau` | GET | ADMIN | | « À rappeler » de toute l'équipe et compteurs ; le reste viendra du sous-chantier 26 |
| `/admin/equipe` | GET | ADMIN | | membres et leur rôle |
| `/admin/equipe` | POST | ADMIN | `nom`, `courriel`, `role` (commercial, moderateur), `offres` | compte créé, courriel d'accès, 303 |
| `/admin/equipe/{id}/role` | POST | ADMIN | `role` ou vide | 303 ; vide = retrait, ses artisans passent à l'administrateur qui agit |

Toutes les pages de `/admin` portent `Cache-Control: no-store`.

### Structures

Colonnes ajoutées à `artisan` (migration additive) :

| Colonne | Type | Sens |
|---|---|---|
| `role` | `VARCHAR(16) NULL` | `commercial`, `moderateur`, ou NULL. Le droit administrateur reste dans `administrateur`, donné par `bin/administrateur` seulement. |
| `cree_par` | `INT UNSIGNED NULL`, clé vers `artisan(id)`, `ON DELETE SET NULL` | qui a créé la démo ou le compte |
| `demo_fin` | `DATETIME NULL` | fin de la période active d'une démo (création + 7 jours) ; suppression 30 jours après la création |
| `offres_offertes` | `SMALLINT UNSIGNED NOT NULL DEFAULT 0` | total des offres ajoutées depuis une fiche, pour appliquer la limite du rôle |

Tables créées :

```
demande_appel(id, artisan_id → artisan ON DELETE CASCADE, telephone VARCHAR(32), moment VARCHAR(8),
              question VARCHAR(500) NULL, cree_le DATETIME, traite_le DATETIME NULL, traite_par INT UNSIGNED NULL)
journal_admin(id, acteur_id INT UNSIGNED, artisan_id INT UNSIGNED NULL, geste VARCHAR(32),
              detail VARCHAR(190) NULL, quand DATETIME, KEY (artisan_id, quand), KEY (acteur_id, quand))
```

`jeton_acces.motif` (existant, `migrations/Version20261118000003.php`) reçoit deux valeurs de plus : `demo_qr` (1 heure) et `demo_message` (24 heures).

Un compte de démo est une ligne `artisan` avec `demo = 1`, `offres_max = 10`, `plafond_jour = NULL`, `demo_fin` rempli, un courriel fabriqué `demo-<16 hexadécimaux>@demo.invalid` (la colonne est `NOT NULL UNIQUE`) et un mot de passe aléatoire jamais montré. On n'y entre que par un lien.

### Flux

1. **Créer une démo.** `POST /admin/demos` → `Demos::creer` (à créer) → `DepotArtisans::creer` (existe), puis le catalogue et les offres d'exemple par `Comptes::reinitialiserDemo` (existe, `src/Compte/Comptes.php:92`) → `journal_admin` → renvoi vers la page du QR.
2. **Afficher le QR.** `GET /admin/artisans/{id}/qr` → `LiensAcces::emettreDemo` (à créer, sur `LiensAcces::emettre`, `src/Compte/LiensAcces.php:122`) → `Endroid\QrCode` (6.2.0, installé par `sprain/swiss-qr-bill`, `SvgWriter` présent) → SVG écrit dans la page. La politique de sécurité du site (`default-src 'self'`) refuse une image en `data:` : le SVG est donc en ligne, sans style en ligne.
3. **Entrer dans la démo.** Le téléphone de l'artisan ouvre `GET /d/{jeton}` : la page ne consomme rien (les aperçus de WhatsApp et de Messages font ce GET) ; `public/js/app.js` envoie le formulaire ; `POST /d/{jeton}` → `LiensAcces::ouvrirDemo` (à créer) marque le jeton utilisé, sous transaction → `Authentification::ouvrirSession` (existe, ligne 71) → `/`.
4. **Ruban et fenêtre.** `templates/base.html.twig` lit la fonction Twig `demo()` (à créer dans `src/Http/Conteneur.php`, à côté de `compte()`) : jours restants, terminé ou non. La fenêtre vit dans `public/js/app.js` : une fois par jour, les deux derniers jours, jamais pendant une dictée.
5. **Fin de la démo.** `demo_fin` passé : `ImportControleur` refuse la dictée, comme il refuse un essai épuisé (`src/Http/Controleur/ImportControleur.php:50`) ; l'accueil affiche l'écran de fin.
6. **Demander un appel.** `POST /appel` → `DepotAppels::creer` (à créer) → après la réponse (`src/Http/ApresReponse.php`), courriel au créateur de la démo par `Messagerie` (`src/Systeme/Messagerie.php`), à défaut à `ALERTES_A`.
7. **Suivi.** `GET /admin/suivi` → `DepotEquipe` sous `PorteeEquipe` : demandes non traitées, démos à deux jours de la fin, comptes à 8 offres sur 10.

### Existant réutilisé

| Besoin | Existe | Chemin |
|---|---|---|
| Jetons à usage unique, empreinte seule en base | oui | `src/Compte/LiensAcces.php`, `src/Donnees/DepotLiensAcces.php` |
| Session et numéro de version | oui | `src/Compte/Authentification.php` |
| 404 pour qui n'a pas le droit, y compris sur une méthode non déclarée | oui | `src/Http/Noyau.php` (`existeHorsAdmin`) |
| Plafond total d'offres | oui | `src/Compte/PlafondEssai.php` |
| Catalogue et offres d'exemple par métier | oui, trois métiers | `src/Catalogue/CatalogueFictif.php` |
| Courriel d'accès d'un nouveau compte | oui | `LiensAcces::envoyerAcces` |
| Envoi après la réponse | oui | `src/Http/ApresReponse.php` |
| Navigateur de test, comptes de test | oui | `tests/Outils/Navigateur.php`, `tests/Outils/Jeu.php` |

## Signatures

```php
// src/Http/Route.php
public const EQUIPE = 'equipe';
public const MODERATION = 'moderation';

// src/Compte/Role.php (à créer)
enum Role: string { case Administrateur = 'administrateur'; case Moderateur = 'moderateur'; case Commercial = 'commercial'; }

// src/Compte/Authentification.php
public function role(int $artisanId): ?Role;

// src/Donnees/PorteeEquipe.php (à créer) : exigée par tout accès de l'équipe aux comptes
final readonly class PorteeEquipe { public function __construct(public int $acteurId, public Role $role) {} }

// src/Donnees/DepotEquipe.php (à créer)
/** @return list<Compte> */ public function artisans(PorteeEquipe $p, string $type, string $recherche): array;
/** @return Compte|null */  public function artisan(PorteeEquipe $p, int $id): ?array;
public function ajouterOffres(PorteeEquipe $p, int $id, int $ajout): bool;
public function reattribuer(int $ancienCreateur, int $nouveauCreateur): int;

// src/Compte/Demos.php (à créer)
public function creer(PorteeEquipe $p, string $metier, ?string $entreprise): int;
public function fermer(PorteeEquipe $p, int $id): void;
public function etat(int $artisanId): ?EtatDemo;   // joursRestants, terminee, supprimeeLe

// src/Compte/LiensAcces.php
public function emettreDemo(int $artisanId, string $motif): string;   // demo_qr ou demo_message
/** @return array{id: int, version_session: int}|null */ public function ouvrirDemo(string $jeton): ?array;

// src/Donnees/DepotAppels.php (à créer)
public function creer(int $artisanId, string $telephone, string $moment, ?string $question, \DateTimeImmutable $quand): int;
/** @return list<Appel> */ public function enAttente(PorteeEquipe $p): array;
public function marquerFait(PorteeEquipe $p, int $id, \DateTimeImmutable $quand): bool;

// src/Donnees/JournalAdmin.php (à créer)
public function noter(int $acteurId, ?int $artisanId, string $geste, ?string $detail, \DateTimeImmutable $quand): void;
```

Limites de « offrir des offres », par rôle, sur `offres_offertes` : modérateur 10, commercial 10 (pour atteindre 20 en tout sur une démo de 10), administrateur sans limite. Personne n'ajoute d'offres à son propre compte, administrateur compris.

## Décisions fragiles

1. J'ai supposé que les aperçus de lien de WhatsApp et de Messages n'exécutent pas le script de la page. S'ils l'exécutent, ils consomment le lien avant l'artisan : le lot 5 remplace alors l'envoi automatique par un bouton « Ouvrir ma démo ».
2. J'ai supposé qu'un courriel fabriqué en `@demo.invalid` ne gêne aucun écran ni aucun envoi. Si un écran l'affiche ou si un courriel part vers lui, le lot 5 doit le masquer partout où `demo = 1`.
3. J'ai supposé que la colonne du métier et la liste des huit métiers viennent du chantier `2026-11-19_metiers-demo`, livré avant le lot 5. Sinon, le formulaire ne propose que les trois métiers actuels et la liste n'affiche pas le métier.

Risque d'organisation : d'autres sessions modifient `public/css/app.css`, `public/js/app.js` et `CODING_RULES.md` sans les avoir commités (constaté le 2026-11-19, 12:25). Aucun lot qui touche ces fichiers ne démarre tant qu'elles n'ont pas commité.

## Lots

Vérification commune à chaque lot : `env APP_ENV=test vendor/bin/phpunit` entier (aucun test rouge) et `vendor/bin/phpstan analyse --no-progress` (« No errors »). La ligne « Vérification » donne le filtre propre au lot.

| Lot | Objectif | Fichiers | Test écrit dans le lot | Vérification | Dépend de |
|---|---|---|---|---|---|
| 0 | Les pages « Nouveau mot de passe » et « Mot de passe » des réglages portent l'adresse du compte dans un champ en lecture seule, `autocomplete="username"` | `templates/_mot_de_passe_champs.html.twig`, `templates/mot_de_passe_nouveau.html.twig`, `templates/reglages_mot_de_passe.html.twig`, `src/Http/Controleur/MotDePasseControleur.php` | `tests/Compte/LiensAccesTest.php` : le champ est présent sur les deux pages, avec la bonne adresse | `--filter LiensAcces` | rien |
| 1 | Migration additive : quatre colonnes sur `artisan`, tables `demande_appel` et `journal_admin` | `migrations/Version20261119000001.php`, `tests/Outils/BaseDeTest.php` (tables à vider) | `tests/Donnees/` : la migration se joue sur une base à jour et les colonnes ont leur défaut | `doctrine-migrations migrate` sur les bases de test et de développement, puis `--filter Sante` | rien |
| 2 | Traceur : `/admin` répond à chaque rôle par sa page d'accueil et sa barre du bas ; 404 pour tout autre compte ; « Espace commercial » (ou « Administration ») apparaît dans « Plus » | `src/Http/Route.php`, `src/Http/Noyau.php`, `src/Http/Garde.php`, `src/Compte/Role.php`, `src/Compte/Authentification.php`, `src/Http/Controleur/AdminControleur.php`, `templates/admin/base.html.twig`, `templates/admin/_menu.html.twig`, `templates/plus.html.twig`, `config/routes.php` | `tests/Http/DroitsEquipeTest.php` : la matrice rôle par route, pour toutes les routes `/admin` ; `tests/Http/NoyauTest.php` accepte les deux droits | `--filter 'DroitsEquipe\|Noyau\|DroitAdministrateur'` | 1 |
| 3 | L'administrateur crée un membre, change son rôle, le retire ; ses artisans passent alors à l'administrateur | `src/Compte/Comptes.php`, `src/Donnees/DepotArtisans.php`, `src/Donnees/DepotEquipe.php`, `src/Donnees/JournalAdmin.php`, `AdminControleur.php`, `templates/admin/equipe.html.twig`, `templates/admin/membre.html.twig` | `tests/Compte/EquipeTest.php` : création, courriel d'accès écrit, retrait et réattribution ; un membre ne change pas son propre plafond | `--filter Equipe` | 2 |
| 4 | Liste et fiche des artisans dans la portée de chacun ; « offrir des offres » avec la limite du rôle ; fil des événements sans contenu d'offre | `src/Donnees/PorteeEquipe.php`, `DepotEquipe.php`, `AdminControleur.php`, `templates/admin/artisans.html.twig`, `templates/admin/artisan.html.twig` | `tests/Http/IsolationEquipeTest.php` : le commercial A demande chaque route de `/admin/artisans/{id}` sur un artisan du commercial B, GET et POST, et reçoit 404 ; `tests/Compte/OffresOffertesTest.php` : limites par rôle | `--filter 'IsolationEquipe\|OffresOffertes'` | 3 |
| 5 | Créer une démo, afficher son QR, préparer le message ; l'artisan entre par le lien, une fois | `src/Compte/Demos.php`, `src/Compte/LiensAcces.php`, `src/Http/Controleur/DemoControleur.php`, `templates/admin/demo_nouvelle.html.twig`, `templates/admin/demo_prete.html.twig`, `templates/admin/demo_message.html.twig`, `templates/demo_entrer.html.twig`, `templates/demo_perimee.html.twig`, `public/js/app.js`, `config/routes.php` | `tests/Compte/DemosTest.php` : compte créé avec son plafond, sa fin et son créateur ; le GET ne consomme pas le lien ; le POST ouvre la session une fois, 410 ensuite et après expiration ; 429 à la onzième démo du jour d'un commercial ; aucun courriel ne part vers `@demo.invalid` | `--filter Demos` | 4, et le chantier des métiers pour les huit métiers |
| 6 | Côté artisan : ruban, fenêtre des deux derniers jours, écran de fin, dictée refusée après la fin, « Demander un appel » et son courriel | `src/Http/Conteneur.php`, `templates/base.html.twig`, `templates/_ruban_demo.html.twig`, `templates/demo_terminee.html.twig`, `templates/appel.html.twig`, `templates/appel_note.html.twig`, `src/Donnees/DepotAppels.php`, `src/Http/Controleur/AppelControleur.php`, `src/Http/Controleur/ImportControleur.php`, `src/Http/Controleur/AccueilControleur.php`, `public/js/app.js`, `public/css/app.css` | `tests/Http/DemoArtisanTest.php` : ruban présent sur chaque écran à onglets d'une démo, absent ailleurs ; import refusé après `demo_fin` ; demande enregistrée ; courriel au créateur, à `ALERTES_A` s'il n'y en a pas | `--filter 'DemoArtisan\|Import'` | 5 |
| 7 | Suivi du commercial, « À aider » du modérateur, tableau de l'administrateur : « À rappeler », « C'est fait », « Bientôt la fin », compteurs, nombre dans la barre | `DepotEquipe.php`, `DepotAppels.php`, `AdminControleur.php`, `templates/admin/suivi.html.twig`, `templates/admin/a_aider.html.twig`, `templates/admin/tableau.html.twig` | `tests/Http/SuiviEquipeTest.php` : une demande n'apparaît que chez son créateur, le modérateur et l'administrateur ; « C'est fait » hors portée rend 404 | `--filter SuiviEquipe` | 6 |
| 8 | Suppression d'une démo 30 jours après sa création : commande écrite, lancée par cron, éteinte tant que le `.env` ne porte pas `PURGE_DEMOS=1` | `bin/fermer-demos`, `src/Compte/Demos.php`, `outils/deployer-test.sh`, `.env.example`, `docs/DEPLOIEMENT.md` | `tests/Compte/DemosTest.php` : rien n'est supprimé commande éteinte ; allumée, la démo de 31 jours disparaît avec ses fichiers et celle de 29 jours reste | `--filter Demos` | 5 |
| 9 | Vérification, revue lourde (droits, jetons, isolation : surfaces sensibles) et recette | `REVUE.md`, `RECETTE.md`, `docs/SECURITE.md` | parcours cliqué au téléphone (Playwright) : créer une démo, ouvrir le lien dans un second navigateur, dicter, demander un appel, le voir dans le suivi ; sondes de disposition et de contraste sur chaque écran neuf | sorties collées dans `ETAT.md` | 0 à 8 |

Le lot 8 éteint la suppression par défaut, comme la purge des audios (Q1 du 18.11) : une suppression automatique de comptes ne s'allume pas sans un geste de Xavier sur le serveur.

## Préalables de la recette

- Jouer `Version20261119000001` (le déploiement du test le fait).
- Donner un rôle à un compte d'essai : `/admin/equipe`, depuis le compte de Xavier.
- Sur le test, aucun courriel ne part : le courriel d'accès d'un membre et le courriel de demande d'appel se lisent dans `partage/var/courriels`.
