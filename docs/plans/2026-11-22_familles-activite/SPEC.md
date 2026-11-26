# SPEC : activités (bâtiment, extérieur, prestations)

Demande de Xavier, 2026-11-22 : « l'app a été construite pour les artisans, mais elle a la possibilité de faire des offres pour bien plus de monde, comment faire pour que les différents corps de métier aient un outil adapté à leur besoin ». Réponses du même jour : familles validées, « un socle commun et quelques changements en fonction des familles, changement minime » ; intéressés dans l'informatique, le conseil, le marketing, et un jardinier ; « on n'est pas obligé d'avoir forcément un titre dans le bloc, on peut aussi ne rien mettre » ; « on met en place et on fera les tests après » ; des testeurs du site de test s'en servent déjà pour des prestations et du conseil ; la publicité et le site restent adressés aux artisans.

Dans le code, la famille s'appelle **activité** : « famille » y désigne déjà les groupes de modèles de l'écran « Ma marque » (`Marque::FAMILLES`).

## Problème

Constaté le 2026-11-22 sur la tête de `chantier-voix-offre` (commit `8385e78`) :

- La consigne de l'IA suppose un artisan en visite : « une offre pour un artisan suisse, à partir de la dictée de sa visite chez le client » (`src/Offre/ExtracteurApi.php:31`), titre fait de « la nature des travaux et le lieu » (`:42`).
- Toute offre non vide passe par un second appel qui range les lignes « dans les pièces où se font les travaux » (`ExtracteurApi.php:53` et `:109`). Un prestataire paie cet appel pour un rangement sans objet.
- Les écrans disent « Parlez, pièce par pièce. » (`templates/_nouvelle_offre.html.twig:14`), « Visite du … » (`templates/accueil.html.twig:34`), et l'en-tête de l'offre porte un bloc « Chantier » : adresse du chantier, contact sur place, accès, début des travaux, date de la visite (`templates/offre_entete.html.twig:22`).
- Les douze modèles impriment « Chantier », « Début des travaux », « Détail des travaux », « Suite à notre visite du … », « pour les travaux suivants » (`templates/document/`).
- Les neuf catalogues de départ sont du bâtiment, « Autre métier » compris (benne, échafaudage, installation de chantier) (`src/Catalogue/CatalogueFictif.php`). Rien pour l'informatique, le conseil ou le marketing.
- Le compte connaît son métier (`artisan.metier`, `artisan.metier_saisi`), qui ne règle que le catalogue de départ et le modèle proposé. Rien ne dit à l'application dans quel genre d'activité elle travaille.

Déjà en place, à garder : l'unité « jour » existe (`Saisie::UNITES`) ; une offre sans pièce sort déjà rangée par catégorie du catalogue (repli du second appel) ; les écrans d'ajout et de ligne cachent déjà la pièce quand l'offre n'en a aucune (`pieces is not empty`).

## Utilisateurs

- **Le titulaire d'un compte** (artisan, jardinier, prestataire) : choisit ou change son activité dans les réglages de son compte. Droits : ceux des réglages actuels, son compte seulement.
- **L'exploitant et le commercial** : choisissent le métier à la création d'une démo ; l'activité en découle. Droits inchangés.
- **Le client final** : lit un document sans mot du bâtiment quand l'offre vient d'un prestataire.

## Périmètre

1. **Trois activités** : Bâtiment, Extérieur, Prestations. Chaque compte en a une. Migration additive : `artisan.activite`, « batiment » par défaut, « exterieur » pour les comptes dont le métier est paysagiste.
2. **Chaque métier appartient à une activité.** Créer un compte ou une démo avec un métier fixe l'activité. Le titulaire peut la changer dans ses réglages (« Votre activité », trois choix) : c'est le chemin des comptes existants, dont ceux des testeurs. Le changement ne touche ni le catalogue, ni les offres, ni le modèle choisi.
3. **Consigne de l'IA** : deux phrases varient selon l'activité (qui dicte et dans quelle situation, de quoi est fait le titre). Le reste est commun. La consigne du Bâtiment reste identique au caractère près.
4. **Rangement** : Bâtiment, par pièce, inchangé. Extérieur, même second appel avec le mot « zone ». Prestations, aucun second appel : l'offre sort rangée par catégorie du catalogue.
5. **Mots des écrans**, tous tenus dans une seule classe :

| Endroit | Bâtiment | Extérieur | Prestations |
|---|---|---|---|
| Écran de dictée | Parlez, pièce par pièce. | Parlez, zone par zone. | Parlez, prestation par prestation. |
| Groupe d'une ligne | Pièce | Zone | (absent) |
| Liste des dictées | Visite du … | Visite du … | Dictée du … |
| Titre du bloc de l'en-tête | Chantier | Lieu d'intervention | (aucun) |
| Champs du bloc | Adresse du chantier, Contact sur place, Accès, Début des travaux, Durée, Date de la visite | Adresse, Contact sur place, Accès, Début des travaux, Durée, Date de la visite | Lieu, Contact, Début, Durée, Date de l'échange |
| Document, case du lieu | Chantier | Lieu | Lieu |
| Document, délais | Début des travaux | Début des travaux | Début |
| Document, détail | Détail des travaux | Détail des travaux | Détail des prestations |
| Lettre | Suite à notre visite du … ; pour les travaux suivants | idem | Suite à notre échange du … ; pour les prestations suivantes |

   « Non compris dans l'offre » reste tel quel partout. Le champ « Accès » disparaît de l'écran en Prestations. Les colonnes en base ne changent pas.
6. **Unités** : « mois » et « passage » s'ajoutent à la liste commune. Une seule liste pour tous.
7. **Catalogues de départ** : trois métiers en Prestations (Informatique, Conseil, Marketing) et « Autre prestation » avec un catalogue général de services ; douze produits et deux offres d'exemple chacun, comme les métiers existants. Prix inventés dans des fourchettes publiées en Suisse, sources ajoutées à `docs/plans/2026-11-19_metiers-demo/CATALOGUES.md`. « Paysagiste » devient « Jardinier, paysagiste » et passe en Extérieur avec son catalogue actuel.
8. **Création d'une démo** (`templates/admin/demo_nouvelle.html.twig`) : la liste des métiers s'affiche sous trois intertitres, un par activité. `bin/creer-compte` et `bin/reinitialiser-demo` acceptent les nouveaux métiers.

## Hors périmètre

- **L'activité « Événement »** : aucun demandeur.
- **Le site, la publicité, la page de connexion, les courriels** : ils restent adressés aux artisans (décision de Xavier).
- **L'administration** (`/admin`) : elle continue de dire « artisans ». La table `artisan` garde son nom.
- **Un rangement par phase ou par poste, des champs nouveaux** (nombre de personnes, livrables) : chacun demande un comportement d'IA jamais mesuré ou une colonne en base.
- **Des unités propres à chaque activité, le dessin des douze modèles, le nom du modèle « Chantier ».**
- **Les langues** (tâche 521) : les mots sont réunis dans une classe pour qu'elle les trouve, rien n'est traduit ici.
- **Le guide de l'onboarding** (étape « Le métier », phrase d'exemple) : il appartient au chantier `2026-11-21_onboarding`, en cours dans une autre copie.
- **Le banc de justesse hors bâtiment** : reporté après la mise en place (décision de Xavier, voir R2).

## Critères de succès

- Compte Bâtiment : la consigne envoyée au modèle est, au caractère près, celle du commit `8385e78` (test) ; la suite de tests existante reste verte, écrans et documents inchangés.
- Compte Prestations : l'écran de dictée dit « Parlez, prestation par prestation. » ; une dictée produit un seul appel d'extraction, sans appel « pièces » (test sur le client HTTP) ; l'offre est rangée par catégorie ; l'en-tête n'a ni titre de bloc ni champ « Accès ».
- Pour chacun des douze modèles, une offre de Prestations dont tous les champs sont remplis se rend sans les mots « chantier », « travaux » ni « visite » (test qui parcourt les douze).
- Compte Extérieur : les écrans disent « Zone », le second appel reçoit le mot « zone ».
- Une démo créée avec le métier « Informatique » a douze produits, deux offres d'exemple et l'activité Prestations.
- Changer « Votre activité » dans les réglages change les mots à l'écran suivant, sans toucher au catalogue ni aux offres (test).
- Migration jouée et annulée sur la base de test du chantier ; un compte paysagiste devient Extérieur, les autres Bâtiment.
- Isolation : le réglage n'écrit que sur le compte de la session (test de la suite d'isolation).

## Contraintes

- `CODING_RULES.md` : PHP sans cadre applicatif, métier en français, accès aux données par `src/Donnees/` seulement, droits refusés par défaut, CSRF sur tout ce qui écrit, un test qui échoue avant et passe après, migrations additives (`docs/DEPLOIEMENT.md`).
- `docs/SECURITE.md` : l'appel au modèle est une surface sensible. La consigne se compose à partir d'une liste fermée (les trois activités), jamais d'un texte saisi ; `metier_saisi` n'entre pas dans la consigne.
- Ergonomie au pouce : le réglage « Votre activité » reprend les boutons radio existants (56 px), aucun composant nouveau.
- Existant réutilisé : `CatalogueFictif`, `Marque::pourMetier`, `Comptes::creer`, `Comptes::reinitialiserDemo`, le repli « rangée par catégorie », `Piece` pour les zones.
- La copie `~/offreGen` porte les fichiers d'autres sessions : tout le code se fait dans `~/offreGen-familles`.

## Risques

- **R1. Accès au site de test.** Xavier, 2026-11-22 : « j'ai des clients en test qui détournent déjà le test ». La règle du projet coupe l'accès de Claude au site de test dès que de vraies données de clients y sont dictées. Tant que Xavier n'a pas répondu, Claude ne contacte pas le site de test, déploiement compris. Le code et les tests locaux n'en dépendent pas ; la recette, si.
- **R2. Aucune mesure hors bâtiment.** La justesse (94,6 % au banc) vaut pour le bâtiment. L'essai de Xavier en informatique et en conseil (« ça fonctionne tip top ») n'est pas chiffré. Décision de Xavier : mise en place d'abord, dictées ensuite. Entorse à « prouver, pas supposer », assumée par lui ; cinq dictées par activité à la recette.
- **R3. « Pas de titre dans le bloc » : lecture de Claude.** En Prestations, l'en-tête de l'offre n'a pas de titre de bloc à l'écran ; sur les documents, la case qui porte l'adresse s'appelle « Lieu », car une case sans libellé ne se lit pas. À confirmer.
- **R4. « Visite » ou « Dictée ».** Question posée deux fois, sans réponse. Choix par défaut : « Visite » reste en Bâtiment et en Extérieur, « Dictée » en Prestations.
- **R5. Pas de maquette.** Aucun écran ni composant nouveau : des libellés, trois intertitres, un groupe de boutons radio existant. La phase 3 est sautée ; les écrans seront montrés en captures à la recette. À refuser ici si Xavier veut une maquette.
- **R6. Prix des trois catalogues.** Inventés dans des fourchettes publiées, comme les neuf existants. Xavier connaît les tarifs de l'informatique : il les corrige à la recette.
- **R7. Second appel de l'Extérieur.** Trois phrases changent (« zone » pour « pièce ») : jamais mesuré.
- **R8. Guide de l'onboarding.** Son étape « Le métier » et sa phrase d'exemple supposent le bâtiment. Le chantier de l'onboarding devra lire l'activité ; rien n'est modifié chez lui d'ici.
- **R9. Revue lourde.** Le diff touche l'appel au modèle : la phase 7 lance trois lentilles et la revue de sécurité. Coût estimé sous le seuil de 30 $, annoncé avant le lancement.

## Réponses de Xavier au gate intention (2026-11-22)

- R1 : « non pas de vrai client tkt ». L'accès de Claude au site de test reste ouvert.
- R2 : « si avec quelque chose prévu pour des artisans ça fonctionne pour des prestations, alors si on améliore le prompt ça ne peut qu'être mieux. Mais passons au développement. » Les dictées restent après la mise en place.
- R3 : « ok ça va pour le lieu ». Aucun titre de bloc à l'écran en Prestations, case « Lieu » sur les documents.
- R4, R5, tableau des mots, périmètre et hors périmètre : « ok ».
- Question de Xavier sur « Dictée du … » : « c'est quoi l'intérêt, c'est pour afficher sur l'offre pour le client ? ». Réponse : le mot sert à trois endroits. Dans l'application, il nomme une dictée en cours de traitement. Sur le document, il donne le titre par défaut de l'offre quand l'IA n'en trouve pas, et la phrase du modèle Lettre tirée du champ « Date de la visite ». En Prestations, le titre par défaut devient « Offre du … » et la phrase de Lettre « Échange du … ».

## Précisions du plan (2026-11-22)

- Le réglage « Votre activité » est un groupe de la rubrique « Mon entreprise » et de la première étape de l'assistant : aucune rubrique ni route nouvelle.
- « Autre prestation » n'a pas de texte libre, à la différence d'« Autre métier ».
- En Prestations, une offre faite avant le changement d'activité garde ses pièces, sous le mot « Pièce ».
- En Prestations, le champ « Accès » reste affiché sur une offre qui en porte déjà un : l'enregistrement de l'en-tête ne l'efface pas.

## Retours de Xavier après la maquette (2026-11-23)

Réponses copiées de la maquette `MAQUETTE-activites.html` :

- Dictée et accueil, en-tête, document, facturer, réglages, catalogue : « Valider ».
- Accès : « y a-t-il une situation où on a un besoin d'accès ? dans les prestations ? si oui alors laisse mais facultatif, il apparaît seulement si besoin. » Oui : une installation chez le client, un atelier dans ses locaux (badge, réception, place de parc). Le champ « Accès » des Prestations est replié sous « Ajouter un accès », ouvert d'un toucher ou quand l'offre en porte un.
- Offre et rangement : « Valider. je pense qu'on peut laisser renommer et ajouter mais on appelle cela catégorie si on veut séparer. » Le groupe dicté d'une prestation s'appelle « catégorie » : renommer, choisir à l'ajout d'une ligne.
- Ce que lit l'IA : « Valider. ajouter le second appel qui range mais seulement si des catégories sont utiles. » Le second appel tourne aussi en Prestations et ne range que les parties que le prestataire annonce ; rien d'annoncé, l'offre sort par catégorie du catalogue. Remplace la décision du 2026-11-22 « Prestations : aucun second appel ».
- Ce qui parle encore de bâtiment : « À laisser pour l'instant. il faut absolument sur les offres enlever la catégorie ou zone hors catalogue c'est moche et sert à rien. » Les lignes sans catégorie n'ont plus de titre « Hors catalogue », dans les trois modes, à l'écran et sur les douze modèles ; leur groupe passe en dernier. Le modèle Récapitulatif, qui cite chaque groupe par sa lettre, les nomme « Autres postes » (proposé par Claude, validé par Xavier le 2026-11-23 : « ok pour autres postes »).

