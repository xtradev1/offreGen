# Recette : onboarding du commercial et de l'artisan

Tout ce qui suit se teste à la main, sur le site de test (`https://easy-offres.xtradev.ch`), au téléphone puis sur PC, en une passe. Coche au fur et à mesure. Les lots 9 (part du commercial) et 10 (lien vers l'aide) ne sont pas construits : ils attendent des chantiers voisins.

## Préalables

- [ ] Commit de la branche `chantier-onboarding`, intégration des deux commits arrivés depuis sur `origin/chantier-voix-offre`, push, déploiement sur le test : à ton accord (aucun accord permanent pour ce chantier).
- [ ] Migration à jouer (le script de déploiement la joue) : `migrations/Version20261122300001.php` (trois colonnes sur `artisan` ; les comptes existants sont notés « guide vu »).
- [ ] Un commercial neuf sur le test, créé par toi dans `/admin/equipe` (« Nouveau membre », rôle Commercial), avec son lien d'accès (sur le test, le courriel est un fichier dans `partage/var/courriels` : je peux te le lire).
- [ ] Ton compte administrateur sur le test.

## Scénario 1 : l'arrivée d'un commercial, chronométrée

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/connexion` |
| Compte | le commercial neuf, au téléphone |

Gestes :
1. Lancer un chronomètre. Se connecter, toucher « Plus », puis l'entrée de l'espace commercial.
2. Lire les trois écrans de bienvenue (« Suivant »), toucher « Faire ma démo d'entraînement ».
3. Suivre l'anneau : « Créer une démo », « Peintre », « Créer la démo », le bouton « Ouvrir la démo sur cet appareil ».
4. Dans la démo : le micro, « Commencer la dictée », dicter une pièce, « Terminer », « Suivant ».
5. Attendre l'offre (elle s'ouvre seule), « Suivant », puis « Revenir à mon espace ».
6. Créer ensuite une vraie démo pour un prospect inventé ; arrêter le chronomètre au code QR.

Attendu : moins de dix minutes en tout ; la carte « Le guide est terminé » au retour, sans retaper de mot de passe ; la démo d'entraînement absente de « Démos en cours » et des chiffres ; la bienvenue ne revient pas à la connexion suivante.

Si ça casse : `public/js/guide.js` (console du navigateur), `templates/admin/_guide_equipe.html.twig`, `src/Compte/RetourEquipe.php`, colonne `artisan.guide_equipe_le`.

## Scénario 2 : le prospect ouvre sa démo et dicte

| | |
|---|---|
| URL | le code QR du scénario 1, scanné avec un autre téléphone |
| Compte | aucun (prospect) |

Gestes : scanner, taper un courriel inventé, « Commencer » ; suivre l'anneau (micro, conseils, « Terminer », attente) ; à l'ouverture de l'offre, lire les repères 1, 2, 3 ; « Compris ».

Attendu : première dictée lancée en moins de soixante secondes après le scan ; l'attente ne promet aucun courriel ; le guide ne revient pas à la réouverture.

Si ça casse : `templates/_guide_artisan.html.twig`, colonne `artisan.guide_artisan_le`.

## Scénario 3 : la fin de la démo

| | |
|---|---|
| Compte | la démo du scénario 2 |

Gestes : je recule la date de fin de cette démo sur le test (`demo_fin`), d'abord à demain, puis à hier ; tu rouvres l'application à chaque fois.

Attendu : à demain, la fenêtre « Votre démo se termine demain », deux boutons de même taille, « Continuer » la ferme pour la journée ; à hier, l'écran « Votre démo est terminée », le micro refuse avec le même message, les offres restent lisibles.

Si ça casse : `Demos::terminee`, `ImportControleur::envoyer`, `templates/demo_terminee.html.twig`.

## Scénario 4 : le passage à l'essai et la mise en route

Gestes : dans la démo, « Passer à l'essai », courriel, mot de passe ; suivre les écrans : entreprise et IBAN, TVA (quatre boutons), logo, modèle et couleur, puis « Vos prix ».

Attendu : « Étape 1 sur 5 » ; « Passer le guide » en haut ; l'IBAN tapé pendant la démo est encore là ; le guide de la démo ne se rejoue pas ; « Ma marque » des réglages est inchangée.

Si ça casse : `ReglagesControleur::ETAPES`, `Comptes::passerEnEssai`, `templates/reglages_rubrique.html.twig`.

## Scénario 5 : l'avertissement avant l'envoi

Gestes : avec le compte du scénario 4, sans avoir changé le nom « Démo Peintre », ouvrir une offre, « Générer l'offre », « Envoyer ».

Attendu : l'avertissement « Votre offre porte encore « Démo Peintre » » ; « Compléter mon entreprise » mène aux réglages ; « Envoyer quand même » ouvre le partage du téléphone et l'offre est notée envoyée.

## Scénario 6 : l'aide à la vente et le suivi

Gestes : commercial, « Plus », « Aide à la vente » (sur PC : « Imprimer ») ; « Mon suivi ».

Attendu : la démo au rendez-vous, les objections, le message de démo ; une rubrique ouverte à la fois ; à l'impression, toutes ouvertes ; dans le suivi, la démo du prospect avec son courriel, « ouverte le », ses dictées, ses jours restants ; un autre commercial ne la voit pas.

## Ce qui n'est PAS couvert

- La part du commercial et le taux par commercial (lot 9) : il faut d'abord les paiements (lot B3 de l'administration).
- « Demander de l'aide » vers « Aide et questions » (lot 10) : la route du chantier support n'existe pas encore.
- « Les questions à poser » dans l'aide à la vente : contenu jamais montré dans une maquette.
- « Ce qu'on ne dit pas » (O31) : en attente de toi.
- Les phrases k.o3.r (99 %) et k.o4.r (import depuis des factures) sont tes mots, non vérifiés.
- Un vrai iPhone et un vrai Android : je n'en ai pas (profil).

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Lint (phpstan) | vert | `[OK] No errors`, `ETAT.md`, Preuves |
| Suite de tests | verte, 1114 tests | `OK (1114 tests, 9826 assertions)`, `ETAT.md`, Preuves |
| Revue | lourde, 4 lentilles ; 14 trouvailles retenues et corrigées, les autres écartées avec raison | `REVUE.md` |
| Parcours cliqués en local | lots 0 à 8 joués au navigateur, rejoués après la revue | `ETAT.md`, Preuves |
