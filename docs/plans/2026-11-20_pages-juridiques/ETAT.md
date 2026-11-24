---
chantier: Pages juridiques de Dicta (mentions légales, protection des données, conditions)
slug: 2026-11-20_pages-juridiques
statut: en attente des réponses de Xavier (MAQUETTE-pages-juridiques.html)
---

## Prochaine action

Xavier répond aux questions P1 et D1 à D11 de `MAQUETTE-pages-juridiques.html` (bouton « Copier le texte »). Ensuite : remplir les passages surlignés (`class="a-completer"`), repasser les textes à l'agent relecteur XtraDev, retirer le bandeau « Projet de texte » de `templates/juridique/_page.html.twig` quand il ne reste plus aucun passage surligné.

## Suivi Origin

- Projet 44 offreGen, tâche 458 « Passage à l'échelle », sous-tâche 463 « Porte 1 : juridique » (conversation rattachée le 2026-11-20).

## Demande

- 2026-11-20, Xavier : « Est-ce que tu peux me créer la page des mentions légales [...] page de protection des données et des conditions. Il faut que tu me fasses ça. » Ce sont les trois liens vides (`href="#"`) du pied de page de la présentation. Aucune quatrième page.
- 2026-11-21, Xavier : « fais moi une maquette pour me montrer le tout ».

## Livré (copie `~/offreGen-juridique`)

- Trois pages publiques et statiques : `/mentions-legales`, `/protection-des-donnees`, `/conditions` (routes `mentions_legales`, `protection_donnees`, `conditions` dans `config/routes.php`, droit `Route::PUBLIC`). Gabarits dans `templates/juridique/`, feuille `public/css/juridique.css` posée sur `site.css`. Pied de page commun, page lue marquée `aria-current`.
- Pied de page de `templates/presentation.html.twig` : les trois liens pointent sur ces pages (seul changement dans ce fichier).
- `tests/Http/PagesJuridiquesTest.php` : 200 sans session et avec session, CSP fermée (ni style, ni script, ni ressource externe), aucun tiret long, liens depuis la présentation, ancre `#sous-traitance`.
- Conditions avec une annexe « traitement des données de vos clients » (art. 9 LPD), au lieu d'une convention séparée : choix de Claude pour un logiciel accepté en ligne. Le skill `conformite-mandats-clients` recommande un document autonome ; à reconsidérer si un client le demande.
- Maquette `MAQUETTE-pages-juridiques.html` et ses captures dans `MAQUETTE-pages-juridiques-img/`.

## Preuves (2026-11-20 et 21)

- Suite complète sur la base à part `offregen_juridique_test` : 929 tests verts, PHPStan sans erreur, sur la tête 4b99cba.
- 2026-11-24 : commit rebasé sur `62f5c85` (65 commits plus loin). Conflits dans `config/routes.php` et `templates/presentation.html.twig` résolus en gardant la version amont, plus les 5 lignes de routes et les 3 liens. Suite complète : 1485 tests verts, PHPStan sans erreur.
- Captures Playwright à 390 et 1280 px : aucun débordement, contrastes 7,95 à 15:1, liens du pied de page à 44 px, logo chargé.
- Relecteur XtraDev : « À retoucher », puis « À retoucher », puis « Prêt » au troisième tour. Le verdict exclut les passages surlignés.
- Maquette : `check.js` vert aux deux largeurs, 18 images, copie des réponses testée.

## Faits vérifiés, sur lesquels reposent les textes

- Infomaniak Network SA, Rue Eugène-Marziano 25, 1227 Genève, CHE-103.167.648. Conditions particulières « LLM API », révision du 07/10/2025, art. 6 : données stockées temporairement le temps du traitement ; prompts et réponses jamais utilisés pour entraîner ses IA ; gardés : numéro client, nombre et temps de traitement des requêtes, heure d'appel, métadonnées (taille, format, durée, nombre de pages). Le lieu de traitement en Suisse vient de la page commerciale « AI Services », pas des conditions.
- Code : cookie de session `offregen`, 14 jours, HttpOnly, SameSite Lax, seul cookie ; `localStorage` (disposition PC) et `sessionStorage` (« plus tard » de Vos prix) ; audio dans IndexedDB jusqu'à l'offre prête ; mots de passe Argon2id (Symfony PasswordHasher) ; 5 échecs en 15 minutes ; liens d'accès 1 h et 7 jours ; swisstopo appelé par le serveur, sans nom ni IP ; demande d'essai envoyée par courriel, pas en base.
- Purge de l'audio écrite mais éteinte (`PURGE_AUDIO`, décision Q1 du 2026-11-18). Journal des connexions jamais purgé. Offre supprimée : seulement marquée (`supprimee_le`), reste en base.
- Adresse « Rue de la Fenaison 37, 2800 Delémont » reprise des contrats de Xavier (`Entreprise/Stratégie/Claude/generate_contract.py`, `entree-de-secours/contrat-support-informatique-projet.md`). IDE introuvable dans ses fichiers (« [numéro IDE à compléter] »).

## Constats à transmettre

- La présentation dit « Vos dictées ne sont ni conservées ni utilisées pour entraîner ses modèles. » Vrai chez Infomaniak, faux chez Dicta (audio gardé, purge éteinte ; texte gardé). Contredit la page Protection des données. Question D2 de la maquette.
- Présentation au téléphone : la page fait 458 px de large pendant l'entrée animée du tampon (environ 0,8 s), puis 390 px. Mesuré dans Chromium, trois essais identiques. Le « 68 px » annoncé à Xavier le 2026-11-20 était cet état passager, mesuré trop tôt.
- Présentation : liens du pied de page à 23 px de haut, sous les 44 px de `CODING_RULES.md`. Corrigé dans `juridique.css` pour les pages juridiques seulement.
- Aucun écran ne fait accepter les conditions : l'engagement de l'art. 179ter CP (accord des personnes enregistrées), seul rempart depuis le retrait du rappel à l'écran, n'est accepté par personne. Question D8.
- Rôles `EQUIPE` et `MODERATION` existent : la clause « Seul Xavier Freléchoz accède aux données de vos clients » tombe dès qu'une autre personne voit le contenu des offres (décision R2 du chantier des comptes).

## Reste à faire

- Réponses de Xavier : P1, D1a à D1d (forme juridique, IDE, TVA, courriel, adresse), D2 (audio et phrase de l'accueil), D3a à D3d (durées), D4a à D4e (essai, prix, facturation, résiliation, préavis de hausse), D5 (plafond de responsabilité), D6 (réutilisation des dictées), D7 (serveur d'envoi), D8 et D9 (acceptation, rappel 179ter), D10 (préavis de 30 jours), D11 (corrections de l'accueil).
- Chaque durée validée demande une suppression automatique codée et testée.
- Si D8 = A : case à cocher sur l'écran du premier accès, date d'acceptation enregistrée (migration additive).
- Durée de garde du journal du serveur web chez Infomaniak : à lire dans le Manager.
- Lien vers les trois pages depuis l'application (menu « Plus ») : non fait.
- Base `offregen_juridique_test` et copie `~/offreGen-juridique` à supprimer à la clôture.
- Dossier parasite `C:\wsl.localhost\` créé par erreur par Claude le 2026-11-21 (32 captures, recopiées dans `MAQUETTE-pages-juridiques-img/`) : suppression refusée par la sécurité de l'outil, à effacer par Xavier.
