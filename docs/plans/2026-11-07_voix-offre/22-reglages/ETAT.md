---
chantier: De la voix à l'offre, 22 réglages mieux rangés
slug: 2026-11-07_voix-offre/22-reglages
phase: 8
statut: livre_en_code
gate_attendu: prod
profil: .claude/atoz-profile.md
---

## Prochaine action

Xavier essaie sur `http://localhost:8000` avec le compte de démonstration (remis à zéro : l'assistant s'ouvre à la connexion). Ensuite : reprise du sous-chantier 17 (clients).

## En attente de toi

- Te connecter avec le compte de démonstration : l'assistant doit s'ouvrir, quatre étapes, « Plus tard » ou « Terminer » mènent aux offres.
- Dans Réglages, changer un champ de chaque rubrique : « Enregistré » doit s'afficher en haut à droite, sans bouton.
- Dire si le résumé de chaque rubrique du sommaire te suffit.

## Décisions

- 2026-11-12 : réponses de Xavier à la maquette : A (sommaire par rubriques, résumés, « à compléter ») validé ; B (assistant au premier lancement, puis sommaire) validé ; enregistrement automatique (choix 2).
- 2026-11-12 : enregistrement partiel : un champ absent du formulaire garde sa valeur. Conséquence : la case « arrondi » envoie un champ caché 0 avant la case 1, sinon la décocher ne changerait rien.
- 2026-11-12 : l'enregistrement automatique part au changement (choix, case) et 0,9 s après la dernière frappe ; une saisie en attente part quand on quitte l'écran (fetch keepalive). En cas d'échec autre qu'une erreur de champ (session expirée, réseau), le bouton « Enregistrer » revient.
- 2026-11-12 : sans JavaScript, le bouton « Enregistrer » reste et revient sur la rubrique (liste blanche des retours).
- 2026-11-12 : l'assistant s'ouvre tant que `artisan.reglages_faits_le` est vide (migration Version20261112000007) ; « Plus tard » le marque fait aussi. La remise à zéro d'un compte de démonstration le vide.
- 2026-11-12 : une rubrique est « à compléter » quand l'adresse manque, ou l'IDE pour un assujetti. Marque, conditions et TVA ont toujours une valeur par défaut, donc sont complètes.
- 2026-11-12 : résumé du sommaire en quelques mots (« acomptes 25 / 25 / 50 % », « net à 10 jours ») : la phrase entière du document était coupée à 390 px.

## Journal

- 2026-11-12 : demande de Xavier : « j'ai besoin que tu me fasses une meilleure proposition pour noter les infos dans les réglages ».
- 2026-11-12 : constat : une seule longue page de quatre cartes, un bouton « Enregistrer » en bas, rien ne dit ce qui manque.
- 2026-11-12 : maquette écrite : A, sommaire de quatre rubriques avec résumé, repère « à compléter » et compteur ; B, assistant de quatre écrans au premier lancement ; enregistrement par bouton ou automatique. Contrôlée au clic, aucune erreur.
- 2026-11-12 : sous-chantier 17 (clients) mis en pause pour cette maquette. Sa migration, renommée Version20261112000008, attend hors du dépôt dans `~/offregen-attente/`.
- 2026-11-12 : « il faut que ce soit plus professionnel, enlève les emoji » : icônes SVG au trait dans la maquette, puis dans l'application.
- 2026-11-12 : code : `ReglagesControleur` (sommaire, rubriques `/reglages/{nom}`, assistant `/bienvenue/{nom}`, `/bienvenue/passer`), un gabarit partiel par rubrique, `AccueilControleur` renvoie vers l'assistant tant qu'il n'est pas fait, réponse JSON pour l'enregistrement automatique, CSS et `reglagesAutomatiques()` dans `app.js`.
- 2026-11-12 : tests adaptés aux nouvelles pages (EcransTest, MarqueEcranTest) ; nouveau `ReglagesRangesTest` (7 tests) ; `SqlHorsDonneesTest` a attrapé du SQL dans `Comptes`, déplacé dans `DepotReglages::assistantARefaire()`.
- 2026-11-12 : défauts trouvés sur les captures et corrigés : résumé des conditions coupé, lien « Se déconnecter » décalé de 8 px.
- 2026-11-12 : défaut connu, hors de ce sous-chantier : la puce « 30 / 30 / 40 » touche presque son bord à 390 px.

## Preuves

Suite et analyse statique (WSL, `~/offreGen`) :

```
$ vendor/bin/phpunit
OK (235 tests, 1125 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
```

Parcours cliqué (Playwright, 390 px, compte de démonstration remis à zéro, script `scratchpad/parcours-22.js`) :

```
  après connexion : http://localhost:8000/bienvenue/entreprise
  Suivant : /bienvenue/marque ; Étape 2 sur 4
  Suivant : /bienvenue/tva ; Étape 3 sur 4
  Suivant : /bienvenue/conditions ; Étape 4 sur 4
  dernier bouton : Terminer
  Terminer : http://localhost:8000/ ; message « C'est prêt : vos offres porteront votre en-tête, votre marque et vos conditions. »
  sommaire : 4 rubriques sur 4 complètes | Mon entreprise Peinture Morel Sàrl · Delémont › / Ma marque Logo · modèle Épuré › / ...
  barre Enregistrer visible : false
  courriel faux : « Pas enregistré : corrigez le champ signalé. » ; message du champ « Une adresse de courriel, par exemple info@exemple.ch. » ; aria-invalid true
  courriel juste : « Enregistré » ; message caché true ; aria-invalid null
  téléphone tapé 032 432 70 00 puis retour immédiat, relu : 032 432 70 00
  validité 60 relue : 60
  « Autre » touché (champ prérempli 60) : état « Enregistré »
  validité 45 relue : choix autre, valeur 45
erreurs : favicon.ico 404 (préexistant) / 422 sur /reglages (attendu : le courriel faux)
```

Captures regardées : assistant étape 1, sommaire avant et après correction du résumé, rubrique Entreprise en erreur, Conditions avec « Autre », Marque, TVA.

Non vérifié : le comportement sans JavaScript dans un vrai navigateur (couvert seulement par `ReglagesRangesTest`, qui poste sans JSON) ; Safari iOS (keepalive au départ de la page).
