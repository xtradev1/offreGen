# SPEC : fluidité, suite de la recette du 24.11

Chantier `2026-11-24_fluidite-suite`, suite de `2026-11-23_sans-rechargement` (SPEC, PLAN et décisions D1 à D10 de ce chantier restent valables). Points de la recette générale (`docs/plans/2026-11-17_mise-en-production/MAQUETTE-recette-generale.html`, copie non commitée de `~/offreGen-recette`), consigne de Xavier du 2026-11-24, mots exacts :

- SR-1 : « tout geste sur l'offre (« C'est juste », corbeille, quantité) répond à l'instant à l'écran. Si le serveur refuse, il revient en arrière avec un message. Au début d'une offre, les éléments ne sont pas encore en mémoire et la latence se sent. »
- MP-A19 : « après « Créer la fiche », ouvrir la fiche pour corriger le nom mal écrit par l'IA, sans clic de plus. »
- MP-A14 : « autocomplétion des pièces existantes dans la fiche d'une ligne. »
- SR-6, à vérifier : « « Générer » deux fois sans rien changer, puis « Envoyer ». L'application annonce des changements en attente, alors qu'il n'y en a aucun. »
- « Maquette avant le code. »

## Ce qui doit être vrai à la fin

1. SR-1 : un toucher sur « C'est juste », la corbeille ou une quantité ne recharge jamais la page, même fait dès que l'offre paraît, y compris juste après un déploiement sur un réseau faible. Refus du serveur : l'écran revient et le dit (en place depuis le lot 2 du chantier précédent, revérifié). Mécanisme : choix D1 de la maquette.
2. SR-6 : « Envoyer » ne tient compte que des changements en attente de l'offre affichée ; le bandeau « Hors réseau » et le message nomment l'offre et le changement. Changement de cette offre en attente : choix D4.
3. MP-A19 : le nom entendu se corrige avant que la fiche soit créée, sans aller dans « Clients ». Forme : choix D2.
4. MP-A14 : la fiche d'une ligne montre les pièces de l'offre et en choisit une en un toucher ; une nouvelle pièce se tape. Forme : choix D3.
5. Sans JavaScript, chaque écran marche comme avant.

## Hors périmètre

- Les choix restés ouverts du chantier précédent (mention dans la politique de confidentialité, durée de garde, « Se déconnecter » sans réseau).
- Toute autre ligne de la recette générale.
- Le déploiement sur le test : seulement avec l'accord de Xavier (ordre du 2026-11-24 : ne plus déployer).

## Critères de preuve

- Tests PHPUnit pour toute logique serveur changée (MP-A19, MP-A14), qui échouent avant.
- Banc par l'interface : `sr1-debut.js` et `sr1-fenetre.js` (scripts absents) sans « RECHARGÉE » ; `sr5-sr6.js chromium aucun 1 2` : « Envoyer » de l'offre 1 part ; WebKit en plus de Chromium pour les gestes.
