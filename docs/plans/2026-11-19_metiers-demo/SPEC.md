# SPEC : métiers de la démo et de l'essai

Demande de Xavier, 2026-11-19 : ajouter des métiers au-delà de peintre, menuisier, sanitaire, et pouvoir écrire un métier absent de la liste. Réponses du même jour : liste du lot 1 validée (M1), menuisier refait et poseur de sols séparé (M2), catalogue général pour « Autre métier » (M3), essai aux prix à 0 (M4), tâche Origin créée (M5). Demande ajoutée : des prix de démonstration « les plus réalistes possibles ».

## Problème

Constaté dans le dépôt le 2026-11-19 :
- `src/Catalogue/CatalogueFictif.php` connaît trois métiers. Un commercial qui rencontre un carreleur ou un électricien n'a aucun catalogue à lui montrer.
- Le catalogue « menuisier » est celui d'un poseur de sols : sept produits sur douze concernent le parquet et ses finitions, aucun les fenêtres, les armoires ou la cuisine.
- Le compte ignore son métier : aucune colonne en base. La maquette des comptes (`docs/plans/2026-11-19_admin-comptes/`) affiche pourtant « Démo · peintre ».
- Le métier se choisit seulement en ligne de commande (`bin/reinitialiser-demo`).
- Un compte d'essai démarre avec un catalogue vide : l'artisan doit tout saisir avant sa première dictée utile.

## Utilisateurs

- **L'exploitant et le commercial** créent une démo ou un essai et choisissent le métier. Droits : ceux du chantier des comptes.
- **L'artisan en démonstration** dicte une visite et voit une offre chiffrée tout de suite.
- **L'artisan à l'essai** part des désignations de son métier et y met ses prix.

## Comment se passe une démo

1. Le commercial choisit le métier. Le compte reçoit le catalogue du métier, 12 produits avec leurs prix, et 2 offres d'exemple déjà chiffrées.
2. L'artisan ouvre l'application : il voit deux offres complètes, avec des totaux crédibles.
3. Il dicte une visite. Chaque ligne reconnue prend le prix du catalogue. L'offre sort avec un total.
4. Il corrige un prix d'un toucher s'il veut voir « son » chiffre. Le prix dicté s'affiche déjà à côté du prix du catalogue (livré le 17.11).
5. À la fin, la démo est remise à zéro ou devient un essai.

Les prix de démonstration sont inventés et placés dans les fourchettes publiées en Suisse : voir `CATALOGUES.md`.

## Passage de la démo à l'essai

Question de Xavier, 2026-11-19 : « comment on passe de j'ai un compte démo avec 12 lignes démo et 2 offres bidons à je fais un essai et je veux plus avoir ces lignes bidons ».

Constaté dans le code le même jour :
- Aucune commande et aucun écran ne sortent un compte du mode démo (`grep` de `SET demo` et `changerDemo` dans `src` et `bin` : aucune sortie).
- Rien ne marque une donnée d'exemple. La table `produit` n'a aucune colonne pour cela. Une offre d'exemple se reconnaît seulement à l'absence d'enregistrement, comme une offre créée à la main.
- La maquette des comptes v2 annonce l'inverse de la demande : « ses offres, son catalogue et ses réglages restent ».

Validé par Xavier le 2026-11-19 (« ok pour le reste ») :
1. À la pose d'un catalogue de démonstration, chaque produit, chaque offre d'exemple et chaque client d'exemple reçoit une marque « exemple ».
2. Un produit perd sa marque dès que l'artisan le modifie : il devient le sien.
3. « Transformer en essai » (bouton du chantier des comptes) appelle un nettoyage, dans une seule transaction :
   - les 2 offres d'exemple et leurs clients inventés sont supprimés ;
   - les offres dictées pendant la démo restent, avec leurs lignes ;
   - les produits que l'artisan a modifiés ou créés restent tels quels ;
   - les produits d'exemple jamais touchés gardent leur désignation et leur unité, prix remis à 0 (choix A, validé par Xavier le 2026-11-19) ;
   - les réglages de l'artisan (entreprise, logo, TVA) restent ;
   - le compte quitte le mode démo, reçoit son plafond d'offres et son courriel d'accès.
4. Garantie testée : après le passage, aucun produit ne porte à la fois la marque « exemple » et un prix.

### Écran « Vos prix »

Idée de Xavier, 2026-11-19 : « on fait passer les lignes qu'il n'a pas changées par une petite interface où il swipe et met le prix des 6-9 produits ». Retenue en complément du choix A : A dit ce que deviennent les données, l'écran dit comment l'artisan les complète.

- À la première ouverture de l'essai, un bandeau annonce « N produits attendent votre prix » et ouvre l'écran. « Plus tard » le ferme ; il reste accessible depuis le catalogue tant qu'un produit est sans prix.
- Un produit à la fois : désignation, unité, un seul champ, le prix, clavier numérique ouvert.
- Trois gestes, boutons dans le tiers bas : « Suivant » enregistre le prix, « Passer » laisse à 0, « Je ne fais pas ça » supprime le produit (avec « Annuler » quelques secondes).
- Même patron que la relecture au pouce d'une offre (`RelectureControleur`, décision D5 du 2026-11-15) : la carte suivante vient d'elle-même, aucun appel au modèle.
- Sert aussi à l'essai créé directement avec un métier (point 5 du périmètre) : même catalogue aux prix à 0.

Réponses de Xavier à la maquette `MAQUETTE-vos-prix.html`, 2026-11-19 :

| Question | Réponse | Ce qui est retenu |
|---|---|---|
| V1, le parcours | Valider, « quand je clique reprendre, ça doit automatiquement faire suivant minimum de clique » | « Reprendre » enregistre le prix de la démo et ouvre le produit suivant, en un toucher |
| V2, la saisie | A : clavier du téléphone | Un champ de chiffres ; les boutons sont sous le champ, au-dessus du clavier |
| V3, le glisser | A : glisser à gauche pour passer, en plus des boutons | Le glisser fait « Passer » et rien d'autre |
| V6, le prix de la démo | A : le proposer | Le prix de la démo se garde à côté du produit d'exemple et se propose |
| V4, retirer | Valider | « Je ne fais pas ça » retire, « Annuler » reste quelques secondes |
| V5, bilan et rappels | Valider | Bilan, rappel dans le catalogue, écran sans produit à compléter |

Ajout de Claude, à défaire si Xavier le refuse : après « Reprendre », un message « Prix repris » avec « Annuler », comme après « Je ne fais pas ça ». Raison : un seul toucher écrit un prix inventé dans un vrai catalogue.

Écart avec le choix de Xavier, dit une fois : le clavier de chiffres de l'iPhone n'a aucune touche de validation et recouvre le bas de l'écran. Les boutons quittent donc le tiers bas de l'écran, contre la règle du pouce de `CODING_RULES.md`. Entorse assumée par la réponse V2 = A, à noter dans les décisions du roadmap à la clôture.

Condition, confirmée par Xavier le 2026-11-19 (« démo et essai, même chose ») : la démo et l'essai vivent sur le même site.

Précisions apportées par le code (lots 3 à 5), décidées par Claude :
- Une fiche client d'exemple que l'artisan modifie devient la sienne, comme un produit.
- Une fiche client d'exemple qu'une offre dictée désigne reste et perd sa marque.
- Le plafond de l'essai vaut le nombre d'offres accordé plus les visites déjà dictées pendant la démo : elles ne sont pas décomptées.

## Périmètre

1. **Neuf catalogues** dans le code, huit métiers plus le général (« 8 plus 1 », Xavier, 2026-11-19) : peintre et sanitaire inchangés, menuisier refait, plâtrier, carreleur, maçon, poseur de sols, paysagiste, général. Contenu : `CATALOGUES.md`. Chacun a 12 produits et 2 offres d'exemple. L'électricien est retiré du lot 1 et remplacé par le maçon.
2. **Un libellé par métier** (« Poseur de sols »), fourni par le code avec la liste des métiers, dans l'ordre d'affichage : peintre, plâtrier, carreleur, maçon, sanitaire, menuisier, poseur de sols, paysagiste, autre.
3. **Le métier enregistré sur le compte**, par une migration additive : la clé du métier, et le texte saisi quand le métier est « autre » (60 caractères au plus).
4. **Démo :** `bin/reinitialiser-demo` accepte les nouveaux métiers et écrit le métier sur le compte. « Autre » charge le catalogue général.
5. **Essai :** à la création d'un compte d'essai avec un métier, le catalogue reçoit les désignations, les unités et les synonymes du métier, tous les prix à 0. Sans métier, le catalogue reste vide comme aujourd'hui.
6. **Compte des métiers saisis :** une commande liste les textes saisis sous « autre » et leur nombre.
7. **Une dictée d'essai par nouveau métier**, passée par l'interface du site de test, résultat noté dans `ETAT.md` : lignes justes sur lignes dictées.
8. **Chaque repère de prix vérifié** en ouvrant sa page avant d'écrire le prix dans le code.
9. **La marque « exemple » et le nettoyage** décrits dans « Passage de la démo à l'essai » : migration additive sur `produit`, `offre` et `client`, une méthode de nettoyage, ses tests. Le bouton reste au chantier des comptes.
10. **L'écran « Vos prix »** décrit plus haut : maquette d'abord (gate visuel), puis le bandeau, l'écran, ses tests et sa recette par l'interface.

## Hors périmètre

- **L'écran de choix du métier** (tuiles, champ « Autre métier ») : il appartient au chantier des comptes, qui appelle la liste des métiers de ce chantier. Raison : un seul chantier par écran.
- **Le lot 2** (électricien, chauffagiste, ferblantier-couvreur, nettoyage) : ajouté à la demande. L'électricien et le chauffagiste chiffrent surtout du matériel pris dans le catalogue d'un grossiste : ils attendent l'import de catalogue (tâche Origin 467).
- **Un catalogue généré par le modèle** à partir du métier saisi : prix inventés sans mesure, à évaluer avec la tâche Origin 466.
- **Le métier transmis au modèle d'extraction** : aucun banc ne dit si cela aide.
- **Les prix en centimes dans le catalogue fictif** : la tonte reste au forfait.
- **Les comptes de démonstration existants** : ils gardent leur catalogue jusqu'à leur prochaine remise à zéro.

## Critères de succès

- `vendor/bin/phpunit` passe, avec un test par règle : 12 produits et 2 offres pour chacun des neuf métiers, unités dans `Saisie::UNITES`, chaque ligne d'offre présente au catalogue.
- Une démo créée pour chacun des neuf métiers montre son catalogue chiffré et ses deux offres, vérifié par l'interface.
- Un essai créé avec un métier montre 12 produits « à compléter », aucun prix.
- La fiche d'un compte peut lire le métier en base.
- Six dictées d'essai notées (plâtrier, carreleur, maçon, poseur de sols, paysagiste, menuisier).

## Contraintes

- `CODING_RULES.md` : SQL dans `src/Donnees/` seulement, métier nommé en français, un test qui échoue avant et passe après.
- `docs/DEPLOIEMENT.md` : migration additive.
- `docs/SECURITE.md` : le texte du métier saisi est une entrée utilisateur, validée côté serveur et échappée à l'affichage.
- Ne casse pas : `tests/Compte/DemoTest.php`, `tests/Bout/IaLocaleTest.php` (catalogue du peintre), les recettes qui appellent `bin/reinitialiser-demo ... peintre`.
- Le code attend le commit de la session « responsive », qui modifie `Conteneur.php` et les gabarits.

## Risques

- **Prix issus de guides pour particuliers**, pas de devis d'artisans. Sur 84 prix, 32 sont des estimations sans repère (compte du script de contrôle après lecture des pages, 2026-11-19), dont les 12 du catalogue général et 6 chez le maçon. Un artisan du métier les jugera en dix secondes. Parade proposée : faire relire chaque catalogue par le premier artisan du métier rencontré, et noter ses corrections.
- **Guides nationaux :** les prix du Jura peuvent être plus bas. À trancher : garder le milieu de la fourchette, ou viser le bas.
- **Extraction jamais essayée hors du peintre** dans le dépôt. Une démo de maçon peut mal reconnaître « galandage » ou « plots ». La dictée d'essai du point 7 le dira avant la première démo.
- **Maçon :** la page de repères date de 2023, et son prix de cloison (20 à 25 CHF/m²) couvre le matériel d'une cloison légère. Un maçon de gros œuvre chiffre avec un catalogue de branche ; ce catalogue vise la petite maçonnerie de rénovation.
- **M4 réglé le 2026-11-19 :** l'essai démarre aux prix à 0 (« va pour A »), et le prix de la démo se propose en un toucher (V6 = A). Reste le risque : un prix inventé repris sans réfléchir part dans une vraie offre.
- **Offres dictées pendant la démo :** elles gardent les prix inventés du moment de la dictée. Elles restent des brouillons ; l'artisan les corrige ou les supprime.
- **Maquette des comptes v2 à aligner :** son texte « ses offres, son catalogue et ses réglages restent » contredit le nettoyage. À corriger dans ce chantier-là après la réponse de Xavier.
- **Estimation :** le point 9 ajoute environ 2 heures et le point 10 environ 4 heures (maquette comprise) aux 8 heures de la tâche Origin 483. Total : 14 heures.
- **Menuisier refait :** la prochaine remise à zéro d'une démo « menuisier » change son contenu.
