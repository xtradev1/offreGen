# Recette : une application qui ne recharge plus

Sur le site de test, avec ton téléphone, en une passe d'une vingtaine de minutes. Coche au fur et à mesure. Les chiffres et les captures du banc sont dans `COMPARATIF-sans-rechargement.html`.

## Préalables

- [x] Migration `Version20261123230001` (table `envoi`, clés d'envoi) : jouée sur le test par le déploiement du 2026-11-23, 21 h 29.
- [x] Déployé : commit `93a61d1` sur https://easy-offres.xtradev.ch (branche `chantier-voix-offre`), sonde verte, `/sante` au bon commit.
- [ ] Compte : ton compte d'essai habituel, ou `fluidite-test@exemple.ch` (démo peintre, deux offres d'exemple ; mot de passe dans `~/.offregen-test-fluidite-compte`, sous WSL). Ce compte ouvre l'assistant de bienvenue : ses offres se trouvent par « Clients », puis la fiche du client.
- [ ] Téléphone de tous les jours, réseau mobile si possible (la différence se voit surtout là).

## Scénario 1 : les gestes de l'offre, sans rechargement

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/offres/<id>` (une offre avec des lignes « À vérifier ») |
| Compte | artisan |

Gestes :
1. Fais défiler jusqu'à une ligne « À vérifier », touche « C'est juste ».
2. Touche la corbeille de deux lignes, vite, l'une après l'autre.
3. Touche « Annuler » sur le premier bandeau.
4. Change une quantité, touche Entrée.

Attendu : 1. l'étiquette part au toucher, l'écran ne saute pas. 2. Les deux lignes partent, deux bandeaux « Annuler ». 3. La première ligne revient à sa place, le total remonte. 4. Le montant de la ligne et le total changent tout de suite (gris : provisoires), puis passent en noir quand le serveur confirme.

Si ça casse : console du navigateur (Chrome : `chrome://inspect` depuis le PC) ; `public/js/fluide.js` (`gestePourFormulaire`) ; la réponse du POST doit porter l'en-tête `X-Morceaux-Adresse`.

## Scénario 2 : ajouter des produits

| | |
|---|---|
| URL | la même offre |
| Compte | artisan |

Gestes :
1. Touche « + Ligne ».
2. Touche trois produits du catalogue.
3. Touche « Terminé ».

Attendu : la fenêtre s'ouvre tout de suite ; chaque produit affiche « … ajoutée » et la fenêtre reste ouverte ; à « Terminé », les trois lignes sont dans l'offre, sans rechargement, total à jour.

Si ça casse : `public/js/app.js` (`fichesEnFenetre`, `prechargerAjout`) ; la requête `GET /offres/<id>/ajouter` doit être partie à l'ouverture de l'offre.

## Scénario 3 : sans réseau

| | |
|---|---|
| URL | la liste, puis une offre déjà ouverte une fois |
| Compte | artisan |

Gestes :
1. Ouvre la liste, puis une offre (avec du réseau). Reviens à la liste, attends dix secondes (le préchargement passe).
2. Mode avion. Dans l'offre : « C'est juste » sur une ligne, une quantité changée, un produit ajouté par « + Ligne ».
3. Reviens à la liste, ouvre une autre offre modifiée ces 30 derniers jours.
4. Quitte le mode avion. Attends quelques secondes, puis recharge l'offre du point 2.

Attendu : 2. l'écran change à chaque geste ; bandeau jaune « Hors réseau : 3 changements attendent (« … » ajoutée), ils partiront au retour du réseau » ; montants en gris. 3. L'autre offre s'ouvre (copie gardée), le bandeau rappelle ce qui attend. 4. Le bandeau part ; après rechargement, les trois changements sont là, une seule fois chacun, total recalculé par le serveur (quelques centimes d'écart possibles avec le provisoire).

Si ça casse : console ; `public/sw.js` (copies gardées) ; dans Chrome, Application > Cache Storage (`offregen-pages-<compte>`) et IndexedDB (`offregen-fluide`, la file).

## Scénario 4 : se connecter, perdre le réseau (ta question D5)

Gestes :
1. Mode avion, ouvre l'application déconnectée : l'écran de connexion ne passe pas (il faut le serveur pour vérifier le mot de passe).
2. Avec du réseau, connecte-toi, ouvre la liste et une offre ; mode avion ; rouvre la liste et l'offre.

Attendu : 1. refus, normal. 2. Liste et offre lisibles et modifiables, les changements attendent (scénario 3).

## Scénario 5 : déconnexion avec un changement en attente

Gestes : en mode avion, change une quantité ; toujours en mode avion, va dans « Plus », touche « Se déconnecter », puis « Annuler » dans la question.

Attendu : la question « Un changement attend encore le réseau. Se déconnecter les perd. Se déconnecter quand même ? » ; « Annuler » garde le changement, il part au retour du réseau. Ne confirme pas en mode avion : la déconnexion a besoin du serveur et Chrome afficherait sa page d'erreur, après avoir effacé les copies du téléphone (défaut connu, noté plus bas ; ta session reste ouverte au serveur). Avec du réseau, « Se déconnecter » efface ce que le téléphone gardait pour ton compte (pages, PDF, file) ; les dictées à l'abri restent.

## Scénario 6 : le PDF

Gestes : depuis une offre prête, « Générer l'offre » ; reviens à l'offre ; « Générer » encore sans rien changer ; puis « Envoyer ».

Attendu : la deuxième ouverture est plus rapide (le PDF n'est pas retéléchargé) ; « Envoyer » ouvre le partage du téléphone sans recharger l'aperçu, « Offre n envoyée » s'affiche. Sur iPhone, ce dernier point n'a jamais été essayé : dis-moi ce qui se passe.

## Ce qui n'est PAS couvert

- « Envoyer » sur iPhone (le banc tourne sous Chrome) ; l'effacement par Safari de ce qu'un site garde après 7 jours sans l'ouvrir.
- Un vrai réseau de chantier qui perd des paquets : le banc simule la latence et le débit, pas les pertes.
- Les fenêtres de l'offre (client, représentant, rabais, conditions, rattacher, créer un produit, objet, enregistrer une ligne) et « Prendre 30.00 » attendent la réponse du serveur (environ 0,7 s sur le chantier) : écart à D2, ta décision est demandée dans la page du comparatif.
- Les fiches du client et des réglages ne passent pas par la file : sans réseau, l'écran dit « Pas enregistré ».
- Défaut ancien, inchangé : sans JavaScript, « Archiver » de la liste est recouvert par la carte.
- Défaut du chantier, connu et non corrigé : « Se déconnecter » sans réseau efface les copies du téléphone puis tombe sur la page d'erreur de Chrome (la session reste ouverte au serveur). Correction simple à prévoir : refuser la déconnexion tant que le serveur ne répond pas, et le dire.
- La mention dans la politique de confidentialité des copies gardées 14 jours (ta décision, page du comparatif).

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Analyse statique (PHPStan) | sans erreur | `deploiement.log` du 2026-11-23, 21 h 25 |
| Suite de tests | 1440 tests, 12 015 assertions, verts | idem, base `offregen_deploiement_test` reconstruite de zéro |
| Revue | 4 lentilles, 4 bloquantes et 19 importantes corrigées (une en partie) | `REVUE.md` |
| Banc avant / après | 47 gestes, 4 réseaux, médiane à l'écran sur le chantier 2,1 s → 632 ms | `COMPARATIF-sans-rechargement.html`, `banc/comparatif.json` |
| Site de test | sonde verte, en-têtes de cache relus, corbeille et « Annuler » par l'interface | `banc/deploiement.json`, `banc/recette-test.json` |
