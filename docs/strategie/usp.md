# Dicta : l'argument unique

Note de travail du 19 novembre 2026, seconde version. Elle complète `concurrents.md`. Demande de Xavier : « je veux
vraiment développer mon USP », puis : « essaye de vraiment trouver quelque chose qui est unique […] ça doit vraiment
être très simple. Une action maximum en 4, allez, disons 5 clics ».

## Trois conditions

1. **Vrai et prouvable** : une fonction en place, une mesure, une page à montrer.
2. **Absent chez les autres** : aucun concurrent ne le dit, et aucun ne peut le dire demain matin.
3. **Important pour l'artisan** : il change sa décision d'acheter.

## Ce que les concurrents disent déjà

Deux relevés du 19 novembre sur onze sites (pages téléchargées le 18, scripts `revendications.py`,
`revendications-2.py`, `moment.py`). Le premier cherchait des tournures françaises sur la page allemande d'Offerto et
concluait à tort que personne ne parlait de prix fiables : corrigé ici, sur la remarque de Xavier.

| Promesse | Qui la fait | Citation |
|---|---|---|
| Libérer les soirées | SecretarIA, Batappli, Chiffr, Offerto | « Fini les soirées à ressaisir les chiffres » ; « Abends keine Offerten mehr schreiben » |
| Vos prix, sans invention | tous ; Offerto le plus net | « Nichts wird erfunden, jeder Preis zeigt, aus welcher Offerte er stammt » |
| Rien ne part sans vous | Chiffr, Offerto | « rien ne part sans vous » ; « Prüfen, senden » |
| En un clic, en 30 secondes | Chiffr, SecretarIA, Opus, Renalto, Obat, Costructor, Vertuoza, Offerto | « Envoyé en un clic » ; « Offerte in 2 Minuten » ; « en 30 secondes, sans clics » |
| Simple, sans formation | Obat (65 fois « simple »), Tolteck, Vertuoza, SecretarIA | « simple et intuitif » |
| Pendant la visite, sur place | Vocabat, Vertuoza, Chiffr, SecretarIA | « Pendant la visite, aucun formulaire » ; « bouclé avant de rentrer » |
| Sans réseau | Tolteck, Vocabat | « sans réseau, dans une cave » |
| Rangé par pièce | Renalto | « Des lignes classées par pièce » |
| **L'IA et la voix hébergées en Suisse** | **personne** | Chiffr, SecretarIA, Opus et Offerto envoient l'IA aux États-Unis (`concurrents.md`) |

Conclusion : toutes les promesses de fonction sont déjà prises, y compris la vitesse, la simplicité et le prix
fiable. Un seul fait reste sans concurrent : l'hébergement suisse de la voix et de l'IA.

## Ce qui se dit quand même

Un argument déjà pris se dit encore s'il compte pour l'artisan. Il ne porte pas la différence.

- **Le prix dicté est contrôlé contre le catalogue**, et l'écart s'affiche (« Prix dicté 26.00, catalogue 28.00 »).
  Xavier : « ça a le plus de valeur ». Offerto promet la même confiance par un autre chemin (l'origine de chaque prix).
- **Toute la visite en une dictée**, jusqu'à 30 minutes, rangée par pièce : 89 à 98 % de lignes justes au banc selon la passe
  (766 sur 810 dans la limite des 30 minutes, soit 94,6 % ; corrigé le 19 novembre, le premier relevé avait manqué la passe à 89 %).

## Trois façons d'être unique quand les mots sont pris

Les concurrents promettent tous la même chose. On se distingue par ce qu'ils ne peuvent ou n'osent pas faire.

| Levier | Ce que Dicta dirait | Pourquoi eux ne le diront pas | Ce qu'il manque |
|---|---|---|---|
| **Un fait** | « Ce qui se dit chez votre client reste en Suisse » : la voix, l'IA, les données | ils devraient changer de fournisseur d'IA | la page d'Infomaniak à l'appui ; savoir si l'artisan y tient |
| **Un chiffre mesuré** | « Deux touchers et votre voix », puis la justesse et le temps relevés chez de vrais artisans | ils promettent (« en un clic », « 30 secondes ») sans rien publier | les mesures des essais en cours |
| **Une garantie** | « Première offre sans mode d'emploi, ou le mois est offert » | une suite de gestion ne peut pas le promettre | la preuve que c'est vrai : la fiche d'essai annonce 30 à 45 minutes |

Le chiffre des deux touchers est lu dans le code : le micro lance l'enregistrement, « Terminer » envoie la dictée et
crée l'offre, qui s'ouvre seule (`public/js/app.js`, « Un seul Terminer compte »). La première fois, le téléphone
demande en plus l'accès au micro. À confirmer par un essai filmé.

## Recommandation

**La position : la simplicité, prouvée par un chiffre.** C'est la valeur que Xavier donne à son produit, et le seul
terrain où une promesse devient vérifiable en dix secondes de vidéo.

- En tête : « Deux touchers et votre voix. » Le reste se fait seul.
- En preuve : les prix contrôlés, la visite entière, la justesse mesurée.
- En signature : tout reste en Suisse, la voix comprise. C'est le seul point que personne ne peut copier vite.

Formulation de travail, à faire relire avant tout usage : « Deux touchers et votre voix : l'offre est faite. Vos
prix sont contrôlés, et rien ne quitte la Suisse. »

## Ce que les essais en cours doivent mesurer

Les comptes d'essai tournent. Trois mesures par artisan donnent les chiffres à publier :

1. Le temps entre l'ouverture du compte et la première offre prête.
2. Le nombre de touchers de la visite à l'offre envoyée.
3. Le nombre de lignes corrigées par offre.

Et une question : « Est-ce que ça compte pour vous que l'enregistrement reste en Suisse ? »

## Avant d'écrire « le seul »

1. Passer la dictée de Luigi dans les essais gratuits de SecretarIA et de Chiffr, compter leurs touchers, noter ce
   qu'ils font d'un prix dicté. Ouvrir ces comptes est la décision de Xavier.
2. Appuyer l'hébergement sur la page d'Infomaniak qui dit où tournent les modèles.

## Décision de Xavier (19 novembre 2026)

« Mon unique selling proposition, c'est que je fais la même chose que les autres, mais suisse. Concrètement, vos
données restent vraiment suisses. » Les arguments partagés avec les concurrents (simplicité, prix contrôlés) se
disent quand même. Sous-titre proposé par Xavier : « Enregistrer, vérifier, envoyer », écrit « Dicter, vérifier,
envoyer » parce que « enregistrer » veut dire sauvegarder dans l'application.

Vérifié le même jour :

- Sur l'installation : le code n'appelle que `api.infomaniak.com` (transcription et rédaction) et le géoservice de
  la Confédération (`api.geo.admin.ch`). La page ne charge rien d'un autre site (CSP `default-src 'self'`, polices
  hébergées par l'application).
- Chez le fournisseur (infomaniak.com, page des services d'IA) : « dans nos data centers en Suisse, sans dépendance
  à des acteurs étrangers » ; « Vos requêtes ne sont ni enregistrées ni utilisées pour entraîner les modèles ».

Ce qu'on écrit, relu par l'agent XtraDev :

- Accroche : « Dictez la visite de chantier. L'offre est rédigée en Suisse. » (ou « Dictez vos offres. Votre voix
  reste en Suisse. »)
- Sous-titre : « Dicter, vérifier, envoyer. »
- Preuve : « Votre voix est transcrite et l'offre rédigée par l'IA d'Infomaniak, dans ses centres de données en
  Suisse. Selon Infomaniak, vos dictées ne sont ni conservées ni utilisées pour entraîner ses modèles. »

Ce qu'on n'écrit pas :

- « Suisse » tout court : Chiffr, Opus et Offerto hébergent aussi leurs données en Suisse. La différence est la
  voix et l'IA.
- « IA suisse » : les modèles sont Whisper (conçu par OpenAI) et Qwen (Alibaba). Ils tournent en Suisse, ils n'y
  ont pas été conçus.
- « Garanti », « sécurisé », « 100 % » employés seuls : on écrit le fait et où le vérifier.
- « En un instant » : l'offre arrive quelques minutes après la dictée.

À fermer avant de l'afficher : le fournisseur de courriels de la production (suisse), la liste publique des
sous-traitants, la convention de traitement d'Infomaniak rangée au dossier. Un paiement par carte, plus tard,
passerait par un prestataire étranger : la promesse porte sur ce que l'artisan dicte et sur les données de ses
clients, pas sur son propre abonnement.
