# RECETTE : fluidité, suite de la recette du 24.11

Sur l'iPhone, sur https://easy-offres.xtradev.ch, une fois le commit `7bb8dd8` (ou plus récent) déployé. Compte : celui de ta recette du 24.11 (`fluidite-test@exemple.ch`, ou le tien).

| N° | À faire | Attendu |
|---|---|---|
| F-1 | Juste après le déploiement, ouvre une offre avec des lignes « À vérifier » et touche aussitôt « C'est juste », sans attendre. | L'offre peut paraître un peu plus tard que d'habitude (scripts neufs), mais le toucher marche : l'étiquette part, la page ne se recharge pas et ne remonte pas. |
| F-2 | Ouvre une autre offre, et dès qu'elle paraît : une corbeille, puis une quantité changée et Entrée, puis le nom d'une ligne. | Chaque geste répond au toucher, rien ne se recharge ; la fiche de la ligne s'ouvre en fenêtre (au pire avec une demi-seconde de retard). |
| F-3 | Mode avion. Change une quantité sur l'offre A. Ouvre l'offre B, « Générer l'offre », « Envoyer ». | Le bandeau jaune dit « (offre A, quantité …) » ; le partage de l'offre B s'ouvre ; « PDF partagé. L'envoi sera noté au retour du réseau. » |
| F-4 | Toujours en mode avion, sur l'offre A : « Générer l'offre », « Envoyer ». Puis quitte le mode avion et attends. | « Ce PDF n'a pas encore votre changement (quantité …, « … »). » ; le réseau revenu, l'aperçu se refait seul avec le PDF à jour ; « Envoyer » part. |
| F-5 | Touche le nom d'une ligne d'une offre rangée par pièce ; touche la puce « Chambre », « Enregistrer ». | Les pièces de l'offre en puces, celle de la ligne choisie ; la ligne passe dans la Chambre, les sous-totaux suivent. |
| F-6 | Même fiche : tape « Bureau » dans « Autre pièce », « Enregistrer ». | Les puces se décochent en tapant ; la ligne arrive dans une pièce « Bureau ». |
| F-7 | Sur une offre dictée sans client : « Générer l'offre », « Créer la fiche « … » ». Corrige le nom, « Enregistrer et voir le PDF ». | La fiche s'ouvre dans la même fenêtre, nom et adresse remplis d'après la dictée ; le PDF s'ouvre avec le nom corrigé. |

Points ouverts, à ton choix (détail dans `REVUE.md`) :
- M7 : une offre gardée dans le téléphone avant un déploiement, rouverte sans réseau, s'ouvre sans JavaScript (gestes en page entière, donc perdus hors réseau). Défaut ancien du service worker, que chaque déploiement déclenche.
- M10 : « Envoyer » touché dans les toutes premières dizaines de millisecondes de l'aperçu ne voit pas encore les changements rangés.
