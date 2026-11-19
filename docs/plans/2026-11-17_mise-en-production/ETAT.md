---
chantier: Mise en production d'offreGen
slug: 2026-11-17_mise-en-production
phase: 4
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

En cours (demande de Xavier du 2026-11-18, `PLAN-completer.md`) : lots 1 à 7 livrés en code, testés, non déployés (le déploiement du test exige un commit poussé : accord de Xavier demandé). Lot 7 non vérifié sur iPhone (sous-chantier 27). Toujours en attente du choix de marque : appliquer la direction à `public/css/app.css`, refaire P1, P2, P4, A2, R1 et les pages du lot 3 dans la marque.

## En attente de toi

- Dans `MAQUETTE-site-v3.html` (elle remplace les questions des deux planches précédentes, B étant retenue) : le menu (Z1), le premier écran et le tampon (Z2), les six cartes (Z3), la facture (Z4), le modèle et la couleur au toucher (Z5), la carte (Z6), la promesse suisse écrite (Z7), l'essai (Z8), le pied de page (Z9), et toujours le premier mois offert ou payant (X5), la limite de l'essai (X6), le prix affiché (X7), l'état « avant l'ouverture » (X8). À faire vérifier : le prix barré (ordonnance sur l'indication des prix), le contact obligatoire d'un site qui vend en ligne, la croix suisse sur le tampon. Au choix aussi (relecteur) : « Transcrite et rédigée en Suisse » plutôt que « L'IA tourne sur des serveurs en Suisse » ; le titre « Aucun entraînement de l'IA sur vos dictées », qui affirme ce que le texte attribue à Infomaniak ; « Faite dans le Jura » plutôt que « Conçue dans le Jura ».
- Choisir l'argument unique à mettre en tête (`docs/strategie/usp.md`, ou U1 dans la partie 10 de la charte) : A le prix dicté contrôlé, B toute la visite en une dictée, C la voix en Suisse. La phrase d'accroche s'écrit ensuite.
- Dire si l'on passe la dictée de Luigi dans les essais de SecretarIA et de Chiffr, pour pouvoir écrire « le seul ».
- Dire si les sept phrases « à aligner » du verbatim partent dans l'application maintenant (A2 est réglée), avec « À relire » remplacé par « À vérifier » sur l'offre.
- Jouer la recette regroupée (`RECETTE.md`).
- Confirmer que la production ira sur `dicta.ch` et créer la boîte `notifications@`.

## Suivi Origin

- Projet 44 offreGen, tâche 458 « Passage à l'échelle », sous-tâche 459 « Porte 1 : production » (conversation rattachée le 2026-11-18). Sous-tâches voisines : 460 (compte créé par l'exploitant), 463 (juridique), 469 (paiement).

## Décisions

- 2026-11-17, Xavier : « il faut que tu prépares le test […] tu vas relister tout ce qui n'a pas été fait, tout ce qui pose problème. Et ensuite, on va travailler pour faire en sorte qu'on puisse passer en production le plus vite possible. En respectant et faisant les choses bien. »
- 2026-11-17, Xavier : formules d'abonnement, la recharge pour les abonnés est gardée, le pack sans abonnement est écarté (« ça casse le système »).
- 2026-11-18, Xavier : « va jusquau 4 avec une remarque quand meme c'est que je veux que tu respect le design de l'app du moins que y'ai une cohérence visuel sur tout le site ».
- 2026-11-18, Xavier : « oui va pour fable pour de la relecture ».
- 2026-11-18, Xavier : domaine envisagé « easy-offre.ch » (le DNS le dit inexistant ce jour, donc probablement libre) ; « j'aimerais bien que le paiement puisse être rapidement mis en place ».
- 2026-11-18, Xavier : « tu peux faire une tache production ou te coller a une tache qui convient dans offreGen » : sous-tâche 459 retenue.
- 2026-11-18, Xavier, réponses à `MAQUETTE-completer.html` (texte copié tel quel) :
  - P1 : Pas valider · je trouve qu'il manque un aspect de marque il gfaut définir la marque pour les couleurs et y'a trop de blanc
  - P2 : Pas valider · on peut faire plus propre et jolie non ?
  - P3 : B · et avec le petit oeil emoticione...
  - P4 : Pas valider
  - A1 : Valider
  - A2 : Pas valider · il faut plus travailler l'ui ça doit avoir une super experience
  - A3 : A
  - R1 : B · mais il faut que le tesxte soit plus clair
  - C1 : Valider
  - Q1 : A : audio effacé 30 jours après l'offre, texte gardé · mais pour l'instant désactivé sera activé en production quand je déciderai
  - Q2 : notifications@ sur le domaine de production
  - Q3 : mon adresse habituelle
- 2026-11-18, Xavier (questions posées après la maquette) : nom affiché « Easy Offre » ; « Identité propre » pour la marque (distincte de XtraDev) ; P4 refusé pour « Même remarque que P1 » (le parcours lui va, le design non).
- 2026-11-18, Xavier, sur `MAQUETTE-marque.html` : « C'est un peu trop basique ton truc, ça fait un peu moche », « 3 propositions hyper originales », « commencer par définir quelle valeur on est censé transmettre », puis les couleurs (théorie, Europe, Suisse, confiance). Les trois directions de `MAQUETTE-marque.html` sont écartées.
- 2026-11-18, Xavier, sur `MAQUETTE-identite.html` : V1 « Pas valider · enlève décontracté on est sur un produit, simple, sobre, efficace, qualité suisse et adapter au métier » ; I1 « aucun on est pas une agence de design plus sobre on est avec des artisants » ; I2 « neutre ». Carnet, Mètre et Raoul écartés ; ton neutre lu comme : ni tutoiement ni vouvoiement, formulations à l'infinitif ou nominales.
- 2026-11-18, Xavier, sur `MAQUETTE-sobre.html` : « alors pas B, mais j'hésite entre A et C, mais effectivement j'irais plus sur C le nom aussi pas est moyen est-ce que tu as d'autres idée ». B écarté, C pressenti, nom « Easy Offre » remis en question (domaine et boîte `notifications@` en attente du nom).
- 2026-11-18, Xavier, sur le nom : « je pars sur dicta.ch et dicta.com ». Vérifié le même jour : dicta.ch libre (registre suisse) ; dicta.com détenu depuis 2001, en vente par la place de marché de GoDaddy (prix non lu, lecture automatique refusée) ; au moins six applications de dictée nommées « Dicta » sur la boutique suisse d'Apple, plus DictaNet et DictaPlus ; Swissreg non vérifié (lecture automatique refusée). Nom à confirmer par Xavier après ces constats ; carpedevis.ch, carpe-devis.ch et carpedevis.com sont libres, calliope.ch et calliope.com pris.

- 2026-11-18, Xavier : « l'envoi de courriels et les jetons de mot de passe et d'accès (A3) ; le droit administrateur ; la purge écrite et désactivée (Q1) ; les alertes vers ton adresse (Q3) ; ensuite l'avertissement des postes coupés (A13) et l'enregistrement à l'abri (sous-chantier 27) ». Plan : `PLAN-completer.md`.
- 2026-11-18, Claude, décisions de mise en œuvre : pages du lot 3 faites avec les composants actuels (parcours P1, P3 B, P4 acceptés, aspect refusé : elles suivront la marque) ; vouvoiement gardé dans les courriels et les pages, comme le reste de l'application, en attendant le ton de la marque ; aucun courriel vers un compte de démonstration ; droit administrateur en 404 pour tout autre compte et sans session (A1) ; 10 caractères au moins pour un mot de passe, sans règle de composition ; `symfony/mailer` et `symfony/event-dispatcher` figés en 7.4 (le second arrivait en 8.1).
- 2026-11-18, courriels d'accès relus par l'agent XtraDev : « À retoucher » puis « Prêt » au second tour (objet du courriel d'oubli, adresse de connexion donnée, « avec votre adresse électronique », « vous connecter »).
- 2026-11-18, Xavier, charte « Dicta - Charte graphique.pdf » reçue : nom Dicta validé, `dicta.ch` réservé, bleu encre `#1d3557`, orange `#e76f51`, Archivo ou Barlow, Inter, trois logos retenus. Avis de Claude : slogan « Une phrase, un devis » contraire au produit, contrastes sous les seuils (gris 4,29, bouton 3,09, attention 2,20), orange et rouge d'erreur de même teinte, trois logos. Réponse de Xavier : « fais moi les 2 cote a cote en faisant en sorte que ça passe les rapport. propose des logo aussi je suis pas convaincu ».
- 2026-11-18, Xavier, sur `MAQUETTE-dicta.html` : « je suis pas convaicu de l'orange ça me convient pas parfaitement prends la couleur de vlc et fais moi des logos avec un cone de chantier qui dit dicta un truc comme ça fais moi 10 tests dew logo et donne des variante du orange et potentiellement test avec l'antracite a la place du bleu mais 2 version fais en plein ». Puis, à l'avertissement sur le logo de VLC : « oui je veux pas copier, m'inspirer ont peu prendre un autre cone ou un cone deformer etc.. ». Le jaune sort de la course ; P1 et L1 à L5 de `MAQUETTE-dicta.html` restent sans réponse.
- 2026-11-18, Xavier, sur `MAQUETTE-dicta-cone.html` : « il faut que le logo fasse cone qui parle donne des style vraiment différents en terme de logo. anthracite c'est bien mais l'orange est encore trop flachi, il me faut d'autre exemple faire des variantes d'orange 5-6 ». Retenu : en-tête anthracite `#23272D`. Écarté : orange VLC `#FF8800`. O1 et C1 à C10 de cette planche restent sans autre réponse.
- 2026-11-18, Xavier, sur `MAQUETTE-dicta-parle.html` : K6 (le porte-voix) « Garder », les neuf autres logos, O2 et M1 sans réponse. Puis : « fais des variantes k6 on doit bien voir que c'est un cone qui parle et dans ce style fais mois 10 autres exemples ».
- 2026-11-18, Xavier, sur `MAQUETTE-dicta-portevoix.html` : O2 « 5 · Terracotta » (`#C4552D`, en accent sur le logo et le micro, boutons anthracite) ; M1 « Offre » ; V5, V6 et V8 « Garder », les autres sans réponse. Puis : « fais moi une version avec les 3 plus 3 autres dans ce style » et « avec v9 aussi donc 8 variants au total ».
- 2026-11-18, Xavier, sur `MAQUETTE-dicta-huit.html` : « 6, B fais juste les que je puisse les envoyer donc tu me fais les maquettes avec ces 2 », « que je puisse envoyer pour départager ». En finale : le logo 6 (le cône et les barres) et le logo B (le cône en tranches et la bulle). Xavier les fait départager par des tiers.
- 2026-11-18, Xavier, sur la planche d'envoi : « go pour le 2 valide la marque avec les couleurs et genere moi la chartre graphique au propre pour qu'elle puisse etre reutiliser, rajoute aussi tous les élémetns ui/uxc qui te semble utile pour avoir un produit propre de obut en bout avec par exemple le verbatim ». Puis : « oui le logo peut encore ce changer c'est un détaille ». Validé : nom Dicta, anthracite `#23272D`, terracotta `#C4552D` en accent, mot « offre », logo « le cône et la bulle ».
- 2026-11-19, Xavier, sur la charte : A1 « Pas valider · il faut que ce soit plus punchy ça fais trop ia nulle et pas assez bien selon moi » ; A2 « Ni tu ni vous · c'est quoi le mieux comment font mes conccurrent ? » (question : reposée avec le relevé des concurrents, rien de réécrit) ; A3 « Français seul pour le lancement » ; A4 « Vrai partout ». Puis : « tu peux ensuite mettre l'âpp a jour dans le test avec tous ces ajouts et verifier la charte est respecté partout avevc les valeurs aussi ».
- 2026-11-19, Xavier : « non pas de vrais clients, tu peux déployer ». Puis, sur A2 : « On peut vous voyer ou être dans du neutre. Je pense qu'on va partir là-dessus. » (libellés neutres, vouvoiement dans les phrases). Sur A1 : « le fini les offres le soir, le problème c'est que j'ai l'impression que c'est déjà quelque chose qui est utilisé par tout le monde [...] je veux vraiment développer mon USP ». Vérifié : SecretarIA, Batappli et Chiffr le disent. Phrase écartée, accroche suspendue à l'argument unique (`docs/strategie/usp.md`).
- 2026-11-19, Xavier, argument unique : « mon unique selling proposition, c'est que je fais la même chose que les autres, mais suisse. Concrètement, les données, vos données restent vraiment suisses. » Sous-titre proposé : « enregistrer vérifier envoyer ». Sur les phrases à aligner : « tu peux faire les propositions pour les 7 phrases à aligner ». Essais : « tester avec les artisans que je connais […] imaginez que vous avez l'app, essayez de faire une offre […] voir s'ils y arrivent ». Détail et formulations relues : `docs/strategie/usp.md`.
- 2026-11-19, Xavier : « fais-moi une maquette pour présenter mon produit vraiment avec les valeurs et tout, l'idée c'est de faire la page d'accueil du site dicta.com. […] avec la possibilité de passer très vite sur l'app […] ou alors on va faire un sous-domaine qui redirige directement sur l'app […] pas trop IA […] quelque chose d'original qui reste dans les valeurs ». Noté : `dicta.com` appartient à un tiers, la maquette est faite pour `dicta.ch`.
- 2026-11-19, Xavier, page d'accueil : parti pris validé (W1) ; accroche « Dictez la visite de chantier. Dicta rédige l'offre, en Suisse. » (W2) ; essai présenté comme une offre, gardé (W3) ; une seule adresse (W4) : « quand tu vas sur dicta.ch, t'es connecté […] Il faut juste du coup un point pour tourner sur le menu ». Essai : « la démo de 7 jours, elle est valable uniquement pour des artisans qui se sont fait approcher par un commercial ou par moi […] l'essai, en fait, il ne doit pas être demandé. Ça doit être en 3 clics […] Premier mois sans engagement. Pas 20 trucs » ; « Pas besoin que ce soit ouvert par moi-même. L'idée c'est que ce soit automatisé » ; « aujourd'hui l'essai n'existe pas, j'attends qu'il y ait 20 artisans […] qui soient passés par moi ou par un commercial » ; « j'ai mis un budget pour ces offres, mais il faut limiter ». Contenu : ajouter « j'ai 3 murs de 10 mètres carrés et qu'ensuite il met 30 mètres carrés » ; vendre les modèles d'offre (« Personnalisez votre offre en 2 clics ») ; « Il faut faire une carte de la Suisse […] Vos données ne partent jamais » ; « Le service de la Confédération Suisse Topo, on s'en fout » ; fiche technique : un seul chiffre de justesse (« soit 95 %, soit 98 »), « Bouton, enlève. Lisibilité, enlève ».
- 2026-11-19, Xavier, sur la version 2 de la page d'accueil : « Je suis pas convaincu encore. […] tu me fais 3 variantes supplémentaires dans d'autres styles […] pas forcément des couleurs, mais juste d'autres idées […] pour vraiment vendre ce produit et que ce soit un côté waouh. » Mon cadrage, à lui de le confirmer : chaque variante vend d'abord un argument différent (la démonstration, les chiffres en grand, la Suisse) ; l'idée « libérez vos soirées » est écartée, quatre concurrents la portent déjà (`docs/strategie/usp.md`, ligne 21) et aucun temps par offre n'est mesuré.
- 2026-11-19, Xavier, sur les quatre idées : « On va partir sur B plutôt, mais on va améliorer concrètement. » Ses consignes : vendre le produit avec un tampon suisse (la dictée a rendu « Stempfeld », lu comme « tampon » : mon interprétation) ; répartir la fiche technique dans ce qu'on vend (« c'est vraiment informaticien ») ; le titre « Une offre à votre image » ; la carte de la Suisse avec le chantier qui se déplace (« on montre qu'on a toute la Suisse ») ; « en moyenne, 95 % » ; une dictée « jusqu'à 30 minutes » ; « 2 clics, votre voix » ; un menu refait (« 3 gestes, modèles, en Suisse, ça veut rien dire ») ; « Écrire à Xavier, je sais pas si c'est hyper intéressant » ; pour l'essai, « tu peux me proposer mieux ». Trois phrases ne sont pas écrites comme dictées, et la raison lui est donnée sur la planche : la facture « en un clic » (l'application en demande deux, `templates/offre.html.twig:60` puis `templates/facturer.html.twig`) ; « aucune donnée ne part à l'étranger » (le PDF part par la messagerie de l'artisan) ; « qualité suisse » (aucun fait ne le prouve).

## Journal

- 2026-11-17 : inventaire (`INVENTAIRE.md`) établi d'après les `ETAT.md` de chaque sous-chantier, `docs/DEPLOIEMENT.md`, `docs/SECURITE.md`, la crontab du site de test, les routes et la liste des PR. Plan en neuf étapes (`PLAN.md`).
- 2026-11-18 : étape 1 faite. `ETAT.md` des sous-chantiers 01, 10 à 18, 21, 22, 25, 28 remis à jour (accords de commit et de PR caducs, questions restées ouvertes gardées), 14 clos, index du chantier de la voix à l'offre complété (27 à 29). Roadmap resynchronisé (décisions et livrés anciens archivés tels quels). Recette regroupée écrite. Sur le test : démo remise à zéro, compte `essai-recette@exemple.ch` (2 offres) créé, mot de passe dans `~/.offregen-recette-compte` (600), jamais affiché. A41 vu au navigateur (assistant, catalogue vide, 0 erreur JS).
- 2026-11-18 : étape 3 du `PLAN.md`, vérifications automatiques : `.github/workflows/controles.yml` (tests, PHPStan, audit des dépendances, migrations sur une base créée par le job, aucun secret, actions épinglées par SHA). Première exécution verte sur la PR #3. La fusion dans `main` attend la recette et l'accord de Xavier.
- 2026-11-18 : étape 2, revue indépendante : six relecteurs (cinq Opus, un Fable), synthèse dans `REVUE.md`. Aucune fuite entre artisans, aucun accès sans connexion. Corrigés avec un test chacun : H1 à H5, M1 à M3, M5, M8 à M10, M12 à M15, B1 à B6. M4 écarté par la mesure (le test enregistre les vraies adresses). M7 reporté avec sa raison. M6, M11, B9 vont avec l'étape 5. Déployé sur le test (18d49a0), sonde verte, cookie `secure` vu sur le site.
- 2026-11-18 : étape 4, phase 3 : maquette `MAQUETTE-completer.html`, écrans dessinés avec la vraie `public/css/app.css` et la structure des pages rendues (demande de Xavier : cohérence visuelle sur tout le site). Défaut trouvé aux captures et corrigé : la liste d'offres copiée de la base de développement contenait le nom et une adresse de Xavier, remplacée par des offres inventées (contrôle : 0 occurrence). `check.js` vert (1440 et 390 px), captures par section relues. Gate visuel ouvert.
- 2026-11-18 : réponses de Xavier reçues. Validés : A1, A3 (courriel d'accès), C1, Q1 A (purge écrite mais désactivée jusqu'à sa décision), Q2, Q3. Refusés : P1, P2, P4, A2 ; P3 B avec un œil ; R1 B avec un texte plus clair. Constat commun de P1, P2 et A2 : l'application n'a pas d'identité de marque (couleurs, nom, trop de blanc) ; c'est un travail sur toute l'application, pas sur ces seuls écrans. Gate visuel toujours ouvert.
- 2026-11-18 : maquette `MAQUETTE-marque.html` : trois directions (A Atelier, B Signal, C Sapin) appliquées aux vrais écrans (connexion, liste des offres, une offre) et au courriel, même logo pour les trois. Défaut trouvé aux captures et corrigé : le jaune de B illisible en texte, remplacé par un jaune foncé (couleur calculée vérifiée). Offre de démonstration rendue en local : la colonne PC contenait des données réelles, écartée (contrôle : 0 occurrence). Gate visuel ouvert sur la marque ; les écrans refusés seront refaits dans la direction choisie.
- 2026-11-18 : `MAQUETTE-identite.html` : cinq valeurs (simple, décontracté, juste, du métier, suisse), ce que disent les sources sur les couleurs (Jonauskaite 2020, Labrecque et Milne 2012, Broeder 2018 et 2019, Poste et CFF ; aucune étude suisse trouvée sur couleur et confiance), six règles qui en découlent, trois identités prototypées (Carnet, Mètre, Raoul) sur connexion, offres, offre, dictée et courriel, contrastes mesurés. Défauts trouvés et corrigés : tirets demi-cadratins des prix ronds, polices non chargées pour les courriels, deux affirmations non vérifiées retirées. `check.js` vert, captures relues.
- 2026-11-18 : `MAQUETTE-sobre.html` : une seule direction (fond pierre, en-tête de couleur, listes à filets, IBM Plex Sans, marque carré et trois lignes), trois accents à choisir (bleu ardoise, vert sapin, jaune signal), mode sombre montré. Défaut trouvé et corrigé : texte secondaire et étiquettes d'état entre 5,4 et 6,9:1 alors que la page annonce 7:1, couleurs foncées jusqu'à 7:1 partout (mesuré). Chiffres à chasse fixe vérifiés (largeur de 1111.11 = 8888.88). `check.js` vert, captures des trois accents relues.

- 2026-11-18 : lots 1 à 6 de `PLAN-completer.md`. Messagerie dans `src/Systeme/` (fichier, SMTP, redirection pour le test) ; droit administrateur (`Route::ADMIN`, `bin/administrateur`) ; liens d'accès (table `jeton_acces`, empreinte seule, 1 h et 7 jours, 3 demandes par heure, session des autres appareils fermée au changement) ; purge éteinte sauf `PURGE_AUDIO=1`, ligne cron posée par `outils/deployer-test.sh` ; alertes du journal par courriel (`ALERTES_A`, une par heure pour la même erreur, 30 par jour) ; postes coupés : 5 tests existants verts. Trois migrations additives. Recette visuelle des pages du lot 3 (390, 1440, sombre) : 0 défaut de disposition après correction d'un lien décalé ; contrastes sous 7:1 tous dus à la palette actuelle (gris 4,94, bleu 6,47), remplacée avec la marque.
- 2026-11-18 : lot 7, enregistrement à l'abri (sous-chantier 27), livré en code ; parcours cliqué dans Chromium vert (détail et preuves dans l'`ETAT.md` du sous-chantier). `docs/SECURITE.md` : dictée en clair dans le navigateur, liens d'accès. Suite complète : 576 tests, PHPStan sans erreur. Fichier inconnu trouvé dans le dépôt, non créé par Claude, laissé tel quel : `docs/terrain/2026-11-18_essai-artisans.txt`.
- 2026-11-18, soir : revue des lots par trois relecteurs Opus (connexion, exploitation, dictée à l'abri). 2 défauts hauts et une dizaine de moyens, tous vérifiés puis corrigés avec un test chacun ; détail et restes dans `REVUE.md`. Migration additive `Version20261118000004` (clé de dictée). Parcours cliqué de la dictée à l'abri rejoué, six scénarios verts.
- 2026-11-18, nuit : accord de Xavier, commit `475ef57` poussé sur `chantier-voix-offre`, déployé sur le test (3 migrations jouées, sonde verte). Le `.env` du test ne règle ni messagerie ni alertes : courriels en fichiers sur le serveur, aucune alerte, purge éteinte. `MAQUETTE-dicta.html` et `docs/terrain/2026-11-18_essai-artisans.txt` laissés hors du commit (autre session).
- 2026-11-18 : `MAQUETTE-dicta.html` : la charte corrigée (A, bleu `#1D3557` et orange `#FF8419` à libellé foncé, Archivo et Inter) à côté d'anthracite et jaune (B, accent C de la maquette sobre), quatre écrans par paire, états à côté du bouton, cinq logos dans leurs quatre emplois. Le générateur refuse d'écrire la page si un texte est sous 7:1 ou une forme sous 3:1 (19 couples par palette, tous au-dessus). Chiffres à chasse fixe mesurés dans Inter et Plex. `check.js` vert, captures bureau et téléphone relues. Défauts trouvés et corrigés avant livraison : variables de police coupées par des guillemets dans un attribut, logo 1 illisible (redessiné en D tranché), point du i mal posé (recalé sur la mesure de l'encre).
- 2026-11-18 : `MAQUETTE-dicta-cone.html` : cinq oranges (VLC `#FF8800`, ambre, adouci, rouille à libellé blanc, précédent) sur bleu et sur anthracite ; quatre écrans en orange VLC, bleu contre anthracite ; dix logos au cône, chacun avec sa distance à VLC. Couleur de VLC lue dans le fichier de l'icône (dégradé de `#FFB900` à `#CC5F00`, moyen `#F48200` à 6,82:1 avec un libellé noir). 17 couples mesurés par version, tous au-dessus des seuils. `check.js` vert, captures relues. Corrigé avant livraison : deux logos trop proches de VLC remplacés (demi-cône en D, entonnoir), distance du logo 9 ramenée à « faible », une classe CSS en conflit.
- 2026-11-18 : `MAQUETTE-dicta-parle.html`, tout sur anthracite : six oranges moins vifs en deux familles (bouton orange désaturé à libellé noir : terre cuite claire, ocre, abricot ; orange profond en accent sur le logo et le micro, boutons anthracite : orange brûlé, terracotta, moutarde), montrés en petit puis sur deux écrans entiers ; dix cônes qui parlent en dix styles, avec un sélecteur qui les habille de chaque orange. 19 couples mesurés par essai (texte 7:1, forme 3:1), tous au-dessus. `check.js` vert, captures relues, parcours cliqué joué (sélecteur d'orange, choix, « Copier le texte »).
- 2026-11-18 : `MAQUETTE-dicta-portevoix.html` : dix variantes du logo K6 dans son style (formes pleines, cône couché qui émet du son), avec deux bandes blanches et un socle épais pour qu'on y lise un cône de chantier. Vue d'ensemble puis chaque variante dans cinq emplois (icône en grand, fond clair, en-tête, une couleur, 30 px), sélecteur des six oranges. `check.js` vert, captures relues, parcours cliqué joué. Corrigé avant livraison : variante « la mâchoire » ratée (flèche cassée) remplacée par « le D » (socle et première onde en D), dessins agrandis dans les tuiles.
- 2026-11-18 : `MAQUETTE-dicta-huit.html` : les quatre logos gardés (5 la bulle, 6 les barres, 8 les tranches, 9 la pastille) et quatre nouveaux dans leur style (A tranches et barres, B tranches et bulle, C la voix dedans, D la pastille à barres), en terracotta sur anthracite. Vue d'ensemble, écran de connexion avec chacun des huit, puis chaque logo dans cinq emplois. 13 couples mesurés (texte 7:1, forme 3:1), tous au-dessus ; terracotta sur anthracite à 3,35:1. `check.js` vert, captures relues, parcours cliqué joué.
- 2026-11-18 : `MAQUETTE-dicta-envoi.html` et ses exports dans `MAQUETTE-dicta-envoi-img/` (`dicta-deux-logos.png`, `dicta-deux-logos.pdf` d'une page, `dicta-logo-1.png`, `dicta-logo-2.png`) : les deux logos numérotés 1 et 2, chacun en grand, en icône, sur deux écrans, dans un courriel et en noir, avec la question « le 1 ou le 2 ? ». Aucun terme de travail. Texte relu par l'agent XtraDev : « À retoucher » puis « Prêt » au second tour (« l'application rédige l'offre chiffrée », légendes des icônes). `check.js` vert, exports et PDF relus, largeur au téléphone vérifiée.
- 2026-11-19 : identité livrée dans `docs/identite/` : `CHARTE.html` et `CHARTE.pdf` (20 pages : marque, logo, couleurs claires et sombres, typographie, composants, verbatim, règles d'ergonomie, écrans, jetons), `VERBATIM.md`, `dicta-tokens.css`, et `logo/` (logo, symbole, icône, version réduite, en SVG sans transformation ni police, plus quatre PNG d'icône). Le nom du logo est tracé en contours d'Archivo gras (OFL). Cône agrandi et bulle réduite par rapport à la planche d'envoi, pour les petites tailles. 21 couples de couleurs mesurés en clair et en sombre (texte 7:1, forme 3:1). Verbatim aligné sur les textes lus dans le code (vouvoiement, objets des courriels, format des montants) et relu par l'agent XtraDev : « À retoucher » deux fois, « Prêt » au troisième tour. `check.js` vert, captures et pages du PDF relues (une page vide corrigée). Les sources de génération sont dans le scratchpad de la session, pas dans le dépôt.
- 2026-11-19 : identité appliquée à l'application, en local. Polices Archivo et Inter hébergées par le site (`public/fonts/`, OFL), quinze variables de couleur de `app.css` remises aux valeurs de la charte en clair et en sombre, en-tête anthracite, micro terracotta, contour de saisie terracotta, liens soulignés, logo (connexion, menu PC, icônes), nom « Dicta » par défaut (`APP_NOM`) dans les titres et les courriels. Audit automatique sur 40 écrans (20 en clair, 20 en sombre, compte de démonstration) : 14 couples sous 7:1 au premier passage, 9 à la fin, tous dans l'aperçu du document de l'artisan (ses couleurs) ou sur le filigrane. Corrigés : boutons du glissement en sombre (1,97 et 2,04:1), filigrane et lueurs restés bleus, lien sans style, texte d'attente du PDF, textes de 11 px, état « à vérifier » en jaune, bord des champs (1,4:1 au lieu de 3:1), ombres du micro. Charte remise en accord avec `CODING_RULES.md` : elle disait 52 et 44 px, les règles disent 64, 56 et 48 px. 588 tests verts, phpstan sans erreur. **Non déployé** : la fiche d'essai envoie les artisans sur le site de test, question posée à Xavier.
- 2026-11-19 : identité déployée sur le test, commit `dabc3d9`, sonde verte (`/sante` rend ce commit, base et migrations à jour). Audit de la charte rejoué sur le test avec le compte d'audit, en lecture seule : 38 écrans, en-tête et micro conformes partout, Archivo et Inter chargées, aucun texte sous 12 px, deux couples sous 7:1 (le filigrane « Aperçu » et le pied du document de l'artisan). Incident : le script d'audit lisait mal le fichier du compte et se connectait trois fois par passage ; six refus en cinq minutes ont bloqué l'adresse IP de Xavier pendant 15 minutes (`Authentification::ESSAIS_MAX`). Script corrigé (une connexion, session reprise, arrêt au premier refus), piège noté en mémoire. `docs/strategie/usp.md` écrit : trois candidats d'argument unique, avec ce que les onze sites concurrents disent déjà.
- 2026-11-19 : `MAQUETTE-site-accueil.html` (la maquette avec les choix) et `MAQUETTE-site-accueil-page.html` (la page seule). Parti pris : le produit montré avec ses propres objets (ce qu'on dit et ce que Dicta écrit, trois gestes avec les vrais boutons, le trajet de la voix en ligne à cinq arrêts, une fiche technique, l'essai présenté comme une offre). Accès à l'application sous le pouce au téléphone, dans le bandeau à l'ordinateur. Texte relu par l'agent XtraDev : « À retoucher » puis « Prêt pour la maquette » ; six affirmations ramenées aux faits, quatre gardées avec preuve (mode sombre audité, TVA, facture depuis l'offre, géoservice lu dans le code). `check.js` vert après correction d'une apostrophe qui cassait le script des choix ; captures téléphone et ordinateur relues ; parcours cliqué joué.
- 2026-11-19 : seconde version de `MAQUETTE-site-accueil.html` et de `MAQUETTE-site-accueil-page.html`. Démonstration avec le calcul des trois murs et le prix dicté, repérés 1 et 2 ; les six modèles d'offre (vignettes de l'application) ; carte de la Suisse (contour Natural Earth, trois trajets contrôlés à l'intérieur du contour par le générateur) ; fiche technique à sept lignes ; inscription en libre-service au bas de l'offre d'essai ; deux états de la page, « inscriptions ouvertes » et « avant l'ouverture ». Écarts avec les mots de Xavier, signalés : l'application compte trois choix pour la marque (logo, couleur, modèle), la page n'écrit donc pas « 2 clics » pour les modèles ; la légende de la carte dit où va la dictée, sans promettre le trajet du réseau ; « ne partent jamais » est écrit « restent en Suisse ». Relecteur XtraDev, trois tours : « À retoucher » aux trois tours. Tour 1 : légende de la carte et deux tournures, appliquées. Tour 2 : « Vous envoyez l'offre en PDF […] depuis votre téléphone » (le bouton ouvre le partage du téléphone) et « hors TVA », appliqués. Tour 3 : un seul point bloquant, l'option « 96 % » de la maquette, retirée après le tour ; pas de quatrième relecture. `check.js` vert, parcours cliqué joué (bascule des deux états, choix, texte à copier), captures téléphone et ordinateur relues. L'inscription en libre-service, la gratuité du premier mois, la limite de l'essai et le prix ne sont pas décidés : la maquette les pose en questions X5 à X8. À construire si la maquette est validée : inscription en libre-service avec plafond de dépense, lien du menu de l'application vers la page.
- 2026-11-19 : `MAQUETTE-site-variantes.html` (planche) et trois pages, `MAQUETTE-site-variante-b-page.html`, `-c-page.html`, `-d-page.html`, images dans `MAQUETTE-site-variantes-img/` (les six modèles de l'application, 560 px). B, la démonstration : le premier écran joue Dicter, Vérifier, Envoyer tout seul (dictée mot à mot, lignes de l'offre, « 3 × 10 m² = 30 m² », bouton réel « Prendre 26.00 », volet de partage), puis les six modèles au toucher. C, grand format : une phrase par écran, « 30 m² » et « 26.00 / 28.00 » en très grand, les modèles en éventail. D, la carte : la Suisse ouvre la page, huit villes romandes à toucher, dix autres chantiers sans nom, tous les trajets contrôlés à l'intérieur du contour par le générateur. Les textes de la version 2 sont repris mot pour mot ; les textes nouveaux tiennent en une douzaine de libellés. Relecteur XtraDev : « À retoucher » au premier tour faute de sources (le « vingt » et le « premier mois » sont les mots de Xavier), « Prêt » au second après le point final de « Vous dites, Dicta écrit. ». Limites écrites sur la planche : les écrans de B sont dessinés (le vrai site demandera les écrans de l'application et le PDF de cette offre-là) ; D repose sur la promesse suisse, à prouver avant publication ; les maquettes appellent Google Fonts, la vraie page servira les polices de l'application. Scripts : `scratchpad/variantes/` (non versionnés).
- 2026-11-19 : `MAQUETTE-site-v3.html` (planche) et `MAQUETTE-site-v3-page.html`, la version 3 de la page d'accueil, bâtie sur B. Menu à trois liens en clair ; tampon « Conçue à Delémont, hébergée en Suisse » ; la fiche technique remplacée par six cartes (calcul, contrôle des prix, 30 minutes, 95 %, 2 clics, facture) ; « Une offre à votre image », modèle et couleur au toucher (la couleur est simulée dans le navigateur) ; la carte avec treize villes de tout le pays ; l'essai titré « Premier mois sans engagement. », tampon « Bon pour essai » dans la zone de signature ; pied de page sans « Écrire à Xavier ». Relecteur XtraDev : « À retoucher » aux tours 1 et 2, « Prêt » au tour 3. Défaut trouvé en chemin : les pages générées depuis le matin n'avaient aucune espace insécable (dans `scratchpad/site/maquette-site.py`, l'insécable collée dans le script s'était perdue à l'enregistrement). Corrigé à la source ; les quatre pages et les deux planches déjà validées (`MAQUETTE-site-accueil*.html`, `MAQUETTE-site-variante*.html`) sont régénérées, identiques hors insécables. Scripts : `scratchpad/v3/` (non versionnés).

## Preuves

```
$ ls .github/workflows : No such file or directory
$ git log --oneline main..chantier-voix-offre | wc -l : 77
$ gh pr list : #3 « De la voix à l'offre… » OPEN depuis le 2026-11-12
$ crontab -l (site de test) : seulement bin/traiter-file, pas de bin/purger-audio
$ grep routes : connexion, déconnexion ; ni mot de passe oublié ni changement de mot de passe
```

Étape 1 (2026-11-18) :

```
$ env APP_ENV=test vendor/bin/phpunit
OK (509 tests, 3121 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ git diff --stat 9fe1570 HEAD -- src templates public config migrations bin
(vide : le test a le dernier code)
$ php-8.5 bin/reinitialiser-demo demo-peintre@exemple.ch peintre (test)
Compte demo-peintre@exemple.ch remis à zéro : catalogue peintre (12 produits), 3 fichier(s) audio supprimé(s).
$ outils/compte-essai-test.sh creer essai-recette@exemple.ch "Peinture Recette (essai)" 2
Compte créé : <masqué> (essai, 2 offres)
$ node recette-a41.js (390 px, test)
après connexion /bienvenue/entreprise
catalogue Tout Le catalogue est vide. « Nouveau produit », en bas, ajoute le premier.
erreurs JS 0
```

Étapes 2 et 3 (2026-11-18) :

```
$ gh run view 44274735896 (Contrôles, PR #3)
No security vulnerability advisories found.
[OK] Successfully migrated to version
[OK] No errors
OK (509 tests, 3121 assertions)
$ env APP_ENV=test vendor/bin/phpunit (après les corrections)
OK (535 tests, 3626 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ bash outils/deployer-test.sh
SONDE VERTE
DÉPLOYÉ : 18d49a09443fa53c43516c0e2ba5e64fe93387ab sur https://easy-offres.xtradev.ch
$ curl -sI https://easy-offres.xtradev.ch/connexion | grep -i set-cookie
set-cookie: offregen=<masqué>; ...; path=/; secure; HttpOnly; SameSite=lax
```

Lots 1 à 6 de `PLAN-completer.md` (2026-11-18) :

```
$ env APP_ENV=test vendor/bin/phpunit
OK (576 tests, 3746 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ composer audit
No security vulnerability advisories found.
$ vendor/bin/doctrine-migrations migrate (test et dev)
[OK] Successfully migrated to version: OffreGen\Migrations\Version20261118000003
$ php bin/purger-audio
Purge éteinte (PURGE_AUDIO=1 dans le .env pour l'allumer) : aucun fichier supprimé.
$ node recette-mdp.js (30 pages : connexion, oubli, envoyé, nouveau, œil, erreur, périmé, réglages, rubrique, erreur ; 390, 1440, sombre)
débordements 0, chevauchements 0, textes coupés 0, désalignements 0, scroll horizontal 0 ; œil : text,text
```

Revue des lots et corrections (2026-11-18, soir) :

```
$ env APP_ENV=test vendor/bin/phpunit
OK (588 tests, 3785 assertions)
$ vendor/bin/phpstan analyse --no-progress
 [OK] No errors
$ node recette-abri2.js (Chromium, micro simulé, IA factice)
A. copie pendant l'enregistrement : 7 tranches, durée 7125 ms ; reprise proposée après 21 s ; fichier gardé 27355 octets
B. deux touchers : offre /offres/167 ; enregistrements sous la clé : 1 ; abri vide
C. premier envoi reçu par le serveur : 303 ; réponse coupée : « Pas de connexion au serveur » ; après « Réessayer » : 1 sous la clé
D. après « Supprimer » : abri vide, fenêtre fermée
E. après le geste retour, autre page : fenêtre fermée ; compte de démonstration : fenêtre fermée
F. stockage muet : offre /offres/169 en 1199 ms
erreurs JS : []
$ grep -qx 'PURGE_AUDIO=1' (garde cron) : lancée seulement pour la ligne exacte PURGE_AUDIO=1
```

Déploiement sur le test (2026-11-18, nuit) :

```
$ bash outils/deployer-test.sh
OK (588 tests, 3785 assertions) ; analyse statique : sans erreur
[notice] 3 migrations executed ; Successfully migrated to version: Version20261118000004
en service : releases/475ef579a2322e17317a13591003eac6dfcbf65c ; précédente : releases/18d49a09443fa53c43516c0e2ba5e64fe93387ab
LU     /sante : {"commit":"475ef579a2322e17317a13591003eac6dfcbf65c","base":"ok","migrations":"ok"}
SONDE VERTE
$ node recette-test.js (Chromium 390 px, site de test, lecture seule)
connexion 200 ; lien « Mot de passe oublié ? » visible ; oubli -> /mot-de-passe/oubli
lien inventé 410 Lien expiré ; réglages sans session -> /connexion ; erreurs JS []
$ crontab -l (serveur de test)
17 3 * * * grep -qx 'PURGE_AUDIO=1' $HOME/.../partage/.env && cd ... && php-8.5 bin/purger-audio ...
PURGE_AUDIO=1 dans partage/.env : 0
```
- 2026-11-19, calcul des surfaces (`scratchpad/site/essai-calcul.php`, catalogue et dictée inventés, extraction réelle) : « Salon. Trois murs de dix mètres carrés. Rebouchage et ponçage, puis peinture deux couches à vingt-six francs. Le plafond, vingt mètres carrés. » Six passes sur six : `Rebouchage et ponçage des murs 30.00 m² prix 9.00 sûre pièce Salon` ; `Peinture des murs, 2 couches 30.00 m² prix 28.00 dicté 26.00 à vérifier pièce Salon` ; `Peinture du plafond 20.00 m² prix 22.00 sûre pièce Salon`. Justesse du banc, dictées inventées : dans la limite des 30 minutes, 29/30, 88/90, 160/180, 287/300 (`26-couts-ia/ETAT.md`), 87/90, 28/30, 87/90 (`28-offre-par-piece/ETAT.md`), soit 766 sur 810 (94,6 %, de 89 à 98 % selon la passe). Mon premier relevé cherchait les nombres de 90 à 99 et avait manqué la passe à 89 % : le relecteur l'a trouvée, vérifié à la source.
- 2026-11-19, variantes de la page d'accueil. `check.js` sur la planche : `bureau : largeur 1440/1440, images 0, cassées 0, kit oui` ; `telephone : largeur 390/390, images 0, cassées 0, kit oui`. Captures des trois pages (`captures-variantes.js`, clics sur les onglets de B, un modèle, la ville de Sion) : pour chacune, au téléphone et à l'ordinateur, `largeur` égale à la fenêtre (390 et 1280), `archivo:true`, `inter:true`, `petites:[]` (aucune cible sous 48 px), `images:0` cassée, `erreurs JS 0`, dans les deux états. Parcours de la planche (`parcours-planche.js`) : 8 cadres chargés, bascule « avant l'ouverture » vraie dans les 8 puis retour, texte copié « B : Je la prends · essai de commentaire […] V1 : B + la carte de D », `choix 5 cadres 8 erreurs JS 0`. Captures ouvertes et relues ; trois défauts vus à l'œil et corrigés (bulle du calcul sur le total, éventail tassé, carte maigre). Tirets longs : 0 dans les cinq fichiers, pas de BOM.
- 2026-11-19, version 3 de la page d'accueil. `check.js` sur la planche : `bureau : largeur 1440/1440, images 0, cassées 0, kit oui` ; `telephone : largeur 390/390, images 0, cassées 0, kit oui`. Captures de la page (`captures-v3.js`, clics sur les onglets de la scène, le modèle Bandeau, trois couleurs, Zurich et Lugano), dans les deux états : `tel ouvert hauteur 6887 […] {"largeur":390,"fenetre":390,"archivo":true,"inter":true,"cachees":0,"petites":[],"images":0} erreurs JS 0` ; `pc ouvert hauteur 4753 […] {"largeur":1280,"fenetre":1280,…,"petites":[],"images":0} erreurs JS 0` ; mêmes mesures à l'état « avant ». Parcours de la planche (`parcours-v3.js`) : six cadres chargés chacun à sa partie (`#produit`, `#image`, `#suisse`, `#essai`), bascule « avant l'ouverture » vraie dans les six puis retour, `choix 13 cadres 6 erreurs JS 0`. Captures ouvertes et relues ; défauts vus à l'œil et corrigés : lien « Se connecter » cassé par une classe déjà prise, partie « à votre image » en deux colonnes au téléphone, carte « facture » qui débordait, tampon sur un titre. Insécables : pages déjà validées `identique hors insecables : True`, de 0 à 15, 16 ou 18 par page ; version 3 : 31. Tirets longs : 0, pas de BOM.
