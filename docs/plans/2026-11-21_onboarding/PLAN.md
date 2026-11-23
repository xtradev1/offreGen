# PLAN : onboarding du commercial et de l'artisan

Écrit le 2026-11-22, phase 4, après le gate visuel (maquettes v2 à v6 validées, réponses dans `ETAT.md`). Base : `origin/chantier-voix-offre`, tête `aa5ea49`. Chaque maillon cité ci-dessous a été lu à ce commit, ou porte « à créer ».

Ce que le plan construit, en une phrase par partie :
- **Commercial :** trois écrans de bienvenue, puis un guide à anneau jusqu'à sa démo d'entraînement, avec retour direct dans son espace ; l'aide à la vente ; son suivi (démos en cours et leur activité, chiffres), puis sa part quand les paiements existeront.
- **Artisan en démo :** le guide à anneau (micro, conseils, « Terminer », l'attente, les repères 1, 2, 3), une attente sans promesse de courriel, la fin de démo appliquée (dictée et import refusés après `demo_fin`, écran de fin, fenêtre des deux derniers jours).
- **Artisan qui passe à l'essai :** la mise en route en cinq étapes (entreprise et IBAN, TVA en quatre boutons, logo, modèle et couleur en blocs, prix), sans rejouer le guide ; l'avertissement avant l'envoi d'une offre au nom provisoire.

Ce que le plan ne construit pas : l'aide et les questions (chantier support, décision T11), « Ce qu'on ne dit pas » (O31, en attente), les paiements (lot B3 de l'administration), la règle complète de commission (Xavier l'écrit ailleurs).

## Contrat technique

### Endpoints

| Route | Verbe | Droit | Entrée | Sortie | Lot |
|---|---|---|---|---|---|
| `/guide/fini` | POST | ARTISAN | `_csrf`, `etat` ∈ {`fini`, `quitte`} | 204 ; 422 si `etat` inconnu | 3 |
| `/guide/revoir` | POST | ARTISAN | `_csrf` | 303 vers `/` | 3 |
| `/import` (existant) | POST | ARTISAN | inchangée | 410 + `demo_terminee.html.twig` si démo et `demo_fin` passée | 2 |
| `/fin-de-demo` | GET | ARTISAN | rien | 200 `demo_terminee.html.twig` pour une démo finie, 404 sinon | 2 |
| `/bienvenue/{etape}` (existant) | GET, POST | ARTISAN | étapes `entreprise`, `tva`, `logo`, `marque`, puis renvoi vers `/vos-prix` | inchangée | 4 |
| `/admin/aide-vente` | GET | EQUIPE | rien | 200 `admin/aide_vente.html.twig` | 6 |
| `/admin/suivi` (existant, ossature) | GET | EQUIPE | rien | 200 `admin/suivi.html.twig`, portée de `PorteeEquipe` | 7 |
| `/admin/bienvenue` | GET | EQUIPE | `?ecran=1..3` | 200 `admin/bienvenue.html.twig` | 8 |
| `/admin/bienvenue/vu` | POST | EQUIPE | `_csrf` | 303 vers `Role::accueil()` | 8 |
| `/admin/demos/entrainement` | POST | EQUIPE | `_csrf`, `metier` | 303 vers `/` dans la session de la démo d'entraînement | 8 |
| `/retour-espace` | POST | ARTISAN, et seulement si la session porte un retour d'équipe valide | `_csrf` | 303 vers `/admin/suivi` ; 404 sans retour valide | 8 |
| `/admin/equipe/{id}/part` | POST | ADMIN | `_csrf`, `taux` entier 0 à 50 | 303 vers `/admin/equipe/{id}` ; 422 hors bornes | 9 |

Toute route neuve entre dans `tests/Http/DroitsEquipeTest.php` et `tests/Http/IsolationRoutesTest.php` (les tests qui parcourent toutes les routes, `CODING_RULES.md`, Données et sécurité).

### Structures

Colonnes ajoutées à `artisan` (lot 1, additives, toutes nulles ou avec défaut) :

| Colonne | Type | Sens |
|---|---|---|
| `guide_artisan_le` | `DATETIME NULL` | guide de l'artisan fini ou quitté ; null = à montrer |
| `guide_equipe_le` | `DATETIME NULL` | bienvenue du commercial vue ; null = à montrer à l'entrée dans `/admin` |
| `entrainement` | `TINYINT(1) NOT NULL DEFAULT 0` | démo d'entraînement d'un membre : hors limite de 10 par jour, `apporte_par` vide |

Colonne ajoutée au lot 9 seulement (pas de colonne morte avant) : `artisan.part_pour_cent SMALLINT UNSIGNED NULL` (null = 10 %).

Session (lot 8) : `retour_equipe` = `{membre: int, version: int, demo: int}`, posée par `/admin/demos/entrainement`, lue et effacée par `/retour-espace`.

Ligne du suivi (lot 7), produite par `DepotEquipe::suivi` (à créer) :

```
array{id: int, entreprise: string, metier: string|null, ouverte_le: string|null, demo_fin: string,
      dictees: int, derniere_dictee: string|null, entrainement: int}
```

Chiffres du suivi : `array{amenes: int, essai: int, abonnes: int}` ; « abonné » = compte hors démo sans `offres_max` jusqu'au lot B1 de l'administration (formule), qui redéfinira ce compte.

Part du commercial (lot 9, après B3) : `array{taux: list<int>, mensuels_centimes: int, annuels: list<array{entreprise: string, centimes: int, arrive_le: string, taux: int}>, detail: list<array{entreprise: string, sorte: 'mensuel'|'annuel', centimes: int, arrive_le: string, taux: int}>}`. Montants en centimes, hors TVA.

Textes (lot 1bis) : `config/textes-onboarding.json`, liste `{id, ecran, lecteur, texte}`, versée depuis `textes.json` de la maquette, insécables posées dans le fichier. Lue par la fonction Twig `texte(id)` (à créer) ; un identifiant inconnu lève une exception en test et en développement.

### Flux

1. **Guide de l'artisan.** `DemoControleur::ouvrir` ouvre la session de la démo (existant) → `AccueilControleur::liste` rend « Offres » → la fonction Twig `guide_artisan()` (à créer, `Conteneur`) lit `guide_artisan_le` par `DepotReglages` → si null et que le compte est une démo ou un essai sans démo, le gabarit inclut `_guide_artisan.html.twig` (étapes en `data-guide-*`) → `public/js/guide.js` (à créer) pose l'anneau et la bulle, avance au toucher de la cible, passe d'un écran à l'autre (liste, feuille des conseils, enregistrement, attente, première offre) en gardant l'étape dans `sessionStorage` → au dernier « Compris » ou à « Quitter le guide », `fetch` POST `/guide/fini` → `DepotReglages::guideFini` (à créer) écrit la date. Le passage d'une démo à l'essai ne remet pas la colonne à null : le guide ne se rejoue pas (O1).
2. **Fin de démo.** `ImportControleur::envoyer` lit l'artisan (existant, ligne 46) → si `demo = 1` et `demo_fin` passée selon `Horloge`, 410 et `demo_terminee.html.twig` → le micro et « Importer » de la barre du bas mènent à `/fin-de-demo` quand la fonction Twig `demo()` (existante, `Conteneur.php` ligne 397) rend `jours = 0`.
3. **Passage à l'essai.** `DemoControleur::passer` (existant) → `Comptes::passerEnEssai` (existant) appelle `DepotReglages::rouvrirAssistant` (à créer : `reglages_faits_le = NULL`, IBAN gardé, contrairement à `assistantARefaire`) → `AccueilControleur::liste` renvoie vers `/bienvenue/entreprise` (existant, ligne 37) → étapes `entreprise`, `tva`, `logo`, `marque` → la dernière renvoie vers `/vos-prix` (existant) au lieu de `/`.
4. **Avertissement avant l'envoi.** `apercu_pdf.html.twig` (bouton `data-partager`, lignes 47 et 60) reçoit `nom_provisoire: bool` de son contrôleur, calculé par `Demos::estNomProvisoire(entreprise, metier, metierSaisi)` (à créer, même formule que `Demos::creer` ligne 60) → si vrai, le bouton ouvre la feuille d'avertissement ; « Envoyer quand même » continue le partage existant et le POST `/offres/{id}/envoyee`.
5. **Suivi.** `/admin/suivi` → `AdminControleur::suivi` (à créer, remplace `ossature('suivi')`) → `DepotEquipe::suivi(PorteeEquipe)` et `DepotEquipe::chiffres(PorteeEquipe)` (à créer, même filtre de portée que `COMPTES`, `DepotEquipe.php` ligne 28) → `admin/suivi.html.twig`. « Ouverte le » vient de la date d'usage du lien de démo (`LiensAcces::ouvrirDemo` → `marquerUtilise`, à relire au lot 7) ; « Pas encore dicté » = aucune dictée 48 heures après l'ouverture.
6. **Démo d'entraînement et retour.** `/admin/demos/entrainement` → `Demos::creerEntrainement` (à créer : `Demos::creer` sans la limite, `entrainement = 1`, `apporte_par` null) → `retour_equipe` en session, puis `Authentification::ouvrirSession` sur la démo → le ruban porte « Revenir à mon espace » → POST `/retour-espace` → contrôle : la session porte `retour_equipe`, la démo de la session est `retour_equipe.demo`, le membre existe, a un rôle, et sa `version_session` est inchangée → `ouvrirSession` sur le membre, `retour_equipe` effacé, geste noté par `JournalAdmin::noter`.
7. **Part du commercial (après B3).** La table des paiements du lot B3 porte le taux du commercial au moment où le paiement arrive (exigence de ce chantier sur B3, à écrire dans son plan) → `DepotEquipe::part(PorteeEquipe, mois)` (à créer) somme 10 % ou le taux noté, par paiement → `admin/suivi.html.twig`, encadré « Votre part ».

### Existant réutilisé

| Quoi | Chemin |
|---|---|
| Routes et droits | `config/routes.php`, `Route::ARTISAN`, `Route::EQUIPE`, `Route::ADMIN` |
| Rôles et page d'accueil par rôle | `src/Compte/Role.php` (`accueil()`) |
| Démo : création, limite, nom provisoire | `src/Compte/Demos.php` (`creer`, `PAR_JOUR`, ligne 60) |
| Démo : entrée, passage | `src/Http/Controleur/DemoControleur.php` (`ouvrir`, `passer`) |
| Passage à l'essai | `src/Compte/Comptes.php` (`passerEnEssai`) |
| Session | `src/Compte/Authentification.php` (`ouvrirSession`, `artisanConnecte`) |
| Assistant | `src/Http/Controleur/ReglagesControleur.php` (`ETAPES`, `etape`, `enregistrerEtape`, `passer`), `src/Donnees/DepotReglages.php` (`assistantFait`) |
| Marque | `templates/_reglages_marque.html.twig`, `src/Compte/Marque.php` |
| Prix un par un | `src/Http/Controleur/VosPrixControleur.php`, `templates/vos_prix.html.twig` |
| Ruban de démo | `templates/_ruban_demo.html.twig`, fonction Twig `demo()` |
| Comptes de l'équipe, portée | `src/Donnees/DepotEquipe.php` (`COMPTES`, `evenements`), `PorteeEquipe` |
| Journal des gestes | `src/Donnees/JournalAdmin.php` (`noter`) |
| Import et plafond | `src/Http/Controleur/ImportControleur.php` (ligne 46) |
| Envoi d'une offre | `templates/apercu_pdf.html.twig`, route `offre_envoyee` |

## Signatures

```php
// src/Compte/Demos.php
public static function estNomProvisoire(string $entreprise, ?string $metier, ?string $metierSaisi): bool;
public function creerEntrainement(PorteeEquipe $p, string $metier): int;

// src/Donnees/DepotReglages.php
public function rouvrirAssistant(): void;
public function guideFini(\DateTimeImmutable $quand): void;
public function guideAMontrer(): bool;
public function revoirGuide(): void;

// src/Donnees/DepotEquipe.php
/** @return list<array{id: int, entreprise: string, metier: string|null, ouverte_le: string|null, demo_fin: string, dictees: int, derniere_dictee: string|null, entrainement: int}> */
public function suivi(PorteeEquipe $p): array;
/** @return array{amenes: int, essai: int, abonnes: int} */
public function chiffres(PorteeEquipe $p): array;
public function bienvenueVue(int $membreId, \DateTimeImmutable $quand): void;
public function bienvenueAMontrer(int $membreId): bool;

// src/Compte/RetourEquipe.php (à créer)
public function poser(Request $requete, int $membreId, int $version, int $demoId): void;
/** Le membre à rouvrir, ou null : session sans retour, autre démo, membre sans rôle ou session du membre changée. */
public function membreARouvrir(Request $requete): ?array;
public function effacer(Request $requete): void;

// src/Http/Textes.php (à créer)
public function texte(string $id): string;

// Contrôleurs (à créer)
// GuideControleur::fini(Request): Response ; GuideControleur::revoir(Request): RedirectResponse
// AdminControleur::aideVente(): Response ; AdminControleur::suivi(): Response
// AdminControleur::bienvenue(Request): Response ; AdminControleur::bienvenueVue(Request): RedirectResponse
// AdminControleur::demoEntrainement(Request): RedirectResponse ; DemoControleur::retourEspace(Request): RedirectResponse
// DemoControleur::fin(Request): Response
```

## Décisions fragiles

1. J'ai supposé que « Demander un appel » du lot 6 de l'administration disparaît avec T11 : l'écran de fin et la fenêtre des deux derniers jours mènent vers « Aide et questions » du chantier support, et la table `demande_appel` reste inutilisée. Si Xavier veut garder un « Être rappelé » propre à la démo, le lot 2 gagne un formulaire et son courriel (environ 4 heures).
2. J'ai supposé que l'assistant change pour tous les comptes : `ETAPES` devient entreprise, TVA, logo, marque, puis « Vos prix » ; « Vos conditions » sort de l'assistant et reste dans les réglages. Si un essai sans démo doit garder ses conditions dans l'assistant, le lot 4 garde deux listes d'étapes.
3. J'ai supposé que le retour direct du commercial (O12) se fait par une marque en session, contrôlée par la `version_session` du membre, sans jeton à part. Si la revue lourde du lot 11 le refuse, le retour repasse par la page de connexion (variante A de la v2), et le lot 8 perd `RetourEquipe`.

## Lots

Estimations de Claude, en heures, à revoir en fin de lot. Base de test : `offregen_onboarding_test` (décision du 2026-11-21). Commande de base : `vendor/bin/phpunit --filter '<motif>'` dans `~/offreGen-onboarding`, sous WSL.

| # | Objectif | Fichiers touchés | Test écrit dans ce lot | Vérification | Dépend de | Estimation |
|---|---|---|---|---|---|---|
| 0 | Traceur : une étape de guide en dur sur « Offres », l'anneau et la bulle s'affichent, « Compris » appelle `/guide/fini` qui répond 204 sans rien écrire | `public/js/guide.js`, `public/css/guide.css` (à créer), `templates/base.html.twig`, `templates/accueil.html.twig`, `config/routes.php`, `src/Http/Controleur/GuideControleur.php` (à créer) | `tests/Http/GuideTest.php` : la page porte `data-guide`, POST sans CSRF 403, avec CSRF 204 | `--filter Guide` vert ; capture Playwright au téléphone, anneau sur le micro, image ouverte | rien | 3 |
| 1 | Migration : `guide_artisan_le`, `guide_equipe_le`, `entrainement` ; une démo existante n'est pas touchée | `migrations/Version20261122xxxxxx.php` (à créer) | `tests/Donnees/` : migration jouée, colonnes présentes, valeurs par défaut | `migrations:migrate` puis `migrations:up-to-date` sur la base du chantier, sorties collées | rien | 1 |
| 1bis | Textes : `config/textes-onboarding.json` et `texte(id)` ; insécables dans le fichier | `config/textes-onboarding.json`, `src/Http/Textes.php`, `src/Http/Conteneur.php` | `tests/Regles/TextesOnboardingTest.php` : chaque `texte('…')` des gabarits existe dans le fichier ; aucun tiret long ; insécable avant `: ; ! ?` et dans « » | `--filter TextesOnboarding` vert | rien | 2 |
| 2 | Fin de démo : import et dictée refusés après `demo_fin` (410), écran de fin, micro qui y mène, fenêtre des deux derniers jours une fois par jour et jamais pendant une dictée ; l'attente d'une démo ne promet plus de courriel | `src/Http/Controleur/ImportControleur.php`, `src/Http/Controleur/DemoControleur.php`, `templates/demo_terminee.html.twig` (à créer), `templates/enregistrement.html.twig`, `templates/_ruban_demo.html.twig`, `config/routes.php` | `tests/Http/FinDeDemoTest.php` : horloge avant et après `demo_fin` ; essai et compte jamais refusés ; texte de l'attente sans « courriel » pour une démo | `--filter 'FinDeDemo\|Import'` vert | 1bis | 5 |
| 3 | Guide de l'artisan : micro, conseils, « Terminer », l'attente, repères 1, 2, 3 et « Compris » ; à l'ouverture d'une démo et d'un essai sans démo, jamais après un passage ; « Quitter le guide » ; « Revoir le guide » dans « Plus » | `public/js/guide.js`, `public/css/guide.css`, `templates/_guide_artisan.html.twig` (à créer), `templates/accueil.html.twig`, `templates/enregistrement.html.twig`, `templates/offre.html.twig`, `templates/plus.html.twig`, `src/Donnees/DepotReglages.php`, `src/Http/Controleur/GuideControleur.php`, `src/Http/Conteneur.php` | `tests/Http/GuideTest.php` : montré à une démo neuve, pas après `/guide/fini`, pas après un passage à l'essai, montré à un essai sans démo ; `/guide/revoir` le remet | `--filter Guide` vert ; parcours Playwright au téléphone de bout en bout, captures ouvertes | 0, 1, 1bis | 8 |
| 4 | Mise en route en cinq étapes : entreprise et IBAN, TVA en quatre boutons, logo, modèle et couleur en blocs, puis « Vos prix » ; rouverte au passage à l'essai, IBAN gardé ; « Passer le guide » = « Plus tard » existant | `src/Http/Controleur/ReglagesControleur.php`, `templates/reglages_rubrique.html.twig`, `templates/_reglages_marque.html.twig` (partagé en `_reglages_logo` et `_reglages_modele_couleur`), `templates/_reglages_tva.html.twig`, `src/Compte/Comptes.php`, `src/Donnees/DepotReglages.php` | `tests/Compte/PassageEssaiTest.php` (étendu) : assistant rouvert, IBAN gardé ; `tests/Http/ReglagesRangesTest.php` (étendu) : ordre des étapes, dernière vers `/vos-prix` ; « Ma marque » des réglages inchangée | `--filter 'PassageEssai\|Reglages\|Marque'` vert ; parcours Playwright démo → essai, captures ouvertes | 1bis | 6 |
| 5 | Avertissement avant l'envoi d'une offre au nom provisoire, « Envoyer quand même » | `src/Compte/Demos.php`, `templates/apercu_pdf.html.twig`, contrôleur de l'aperçu, `public/js/guide.js` ou fichier propre | `tests/Compte/DemosTest.php` : `estNomProvisoire` ; `tests/Http/RenvoiOffreTest.php` (étendu) : feuille présente si nom provisoire, absente sinon | `--filter 'Demos\|RenvoiOffre'` vert ; capture | 1bis | 2 |
| 6 | Aide à la vente dans `/admin`, imprimable, sans « Ce qu'on ne dit pas » | `templates/admin/aide_vente.html.twig` (à créer), `templates/admin/_menu.html.twig`, `templates/admin/plus.html.twig`, `src/Http/Controleur/AdminControleur.php`, `config/routes.php`, `public/css/admin.css` | `tests/Http/DroitsEquipeTest.php` (étendu) : les trois rôles l'ouvrent, un artisan reçoit 404 | `--filter DroitsEquipe` vert ; capture téléphone et PC, aperçu d'impression | 1bis | 4 |
| 7 | Suivi : démos en cours et leur activité (ouverte le, dictées, jours restants, « Pas encore dicté » après 48 heures), amenés, à l'essai, abonnés ; l'administrateur voit tout, le commercial les siens ; démos d'entraînement à part | `src/Donnees/DepotEquipe.php`, `src/Http/Controleur/AdminControleur.php`, `templates/admin/suivi.html.twig` (à créer), `config/routes.php` | `tests/Http/SuiviTest.php` : chiffres sur un jeu connu, « Pas encore dicté » à 47 et 49 heures (horloge), isolation : le commercial A ne voit aucune démo du commercial B | `--filter 'Suivi\|IsolationRoutes'` vert | 1 | 6 |
| 8 | Arrivée du commercial : trois écrans de bienvenue au premier passage, guide à anneau jusqu'à la démo d'entraînement, démo d'entraînement hors limite et sans `apporte_par`, retour direct dans l'espace | `templates/admin/bienvenue.html.twig` (à créer), `src/Compte/RetourEquipe.php` (à créer), `src/Compte/Demos.php`, `src/Http/Controleur/AdminControleur.php`, `src/Http/Controleur/DemoControleur.php`, `templates/_ruban_demo.html.twig`, `config/routes.php` | `tests/Http/ArriveeCommercialTest.php` : bienvenue une seule fois ; démo d'entraînement hors limite, `apporte_par` vide ; `/retour-espace` 404 sans retour, 404 depuis une autre démo, 404 après changement de mot de passe du membre, 303 dans le cas normal | `--filter 'ArriveeCommercial\|DroitsEquipe\|IsolationRoutes'` vert ; parcours Playwright chronométré (critère : moins de dix minutes) | 0, 1, 3, 7 | 10 |
| 9 | Part du commercial et taux par commercial | migration `part_pour_cent`, `src/Donnees/DepotEquipe.php`, `templates/admin/suivi.html.twig`, `templates/admin/equipe.html.twig`, `src/Http/Controleur/AdminControleur.php` | `tests/Http/PartCommercialTest.php` : 10 % par défaut ; annuel compté une fois, le mois de son arrivée ; changement de taux sans effet sur le passé | `--filter PartCommercial` vert | lot B3 de l'administration | 5 |
| 10 | « Demander de l'aide » et l'écran de fin mènent à « Aide et questions » | `templates/plus.html.twig`, `templates/demo_terminee.html.twig`, `templates/_ruban_demo.html.twig` | `tests/Http/PagesTest.php` (étendu) : lien présent | `--filter Pages` vert | route du chantier support | 1 |
| 11 | Vérification, revue lourde (droits, session, retour d'équipe, isolation), recette | `REVUE.md`, `RECETTE.md` | suite complète | `vendor/bin/phpunit` et `vendor/bin/phpstan analyse`, sorties collées | 0 à 8 (9 et 10 s'ils sont débloqués) | 6 |

Ordre : 0, 1, 1bis, puis 2, 3, 4, 5, 6, 7, 8. Les lots 9 et 10 attendent des chantiers voisins ; la recette des lots 0 à 8 ne les attend pas. Total des lots 0 à 8 et 11 : 53 heures, estimation de Claude.

## Migrations et préalables de la recette

- Lot 1 : une migration additive, à jouer sur la base du chantier, puis sur le test au déploiement (`docs/DEPLOIEMENT.md`).
- Lot 9 : une migration additive, après celle du lot B3.
- Préalable de recette : un compte commercial sur le site de test, créé par Xavier depuis `/admin/equipe`, et une démo neuve.

## Coordination avec les chantiers voisins

- Administration (`2026-11-19_admin-comptes`) : ligne de reprise des lots 6 et 7 dans son `ETAT.md`, écrite au premier commit de code de ce chantier. Exigence pour B3 : la table des paiements note le taux du commercial à l'arrivée du paiement.
- Support (`2026-11-21_support`) : la route de « Aide et questions » débloque le lot 10. Le passage de `role` à `equipe_membre` (T5) touche `Role` et `PorteeEquipe`, que les lots 6 à 8 lisent : relire au début du lot 6 si son code est arrivé sur la branche.
- `public/js/app.js` et `public/css/app.css` restent intouchés (modifiés par d'autres sessions) : le guide vit dans `guide.js` et `guide.css`.
