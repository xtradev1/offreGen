# Recette regroupée avant la production

Elle remplace les recettes des sous-chantiers 01, 10, 11, 12, 13, 15, 16, 17, 18, 21, 22, 25, 28, la note « prix dicté » et la recette finale du 24 (lot 9). Elle porte sur ce qui existe au 18 novembre : les morceaux de l'étape 5 du `PLAN.md` (mot de passe, administration, alertes…) auront chacun la leur.

Tout se passe sur le site de test, https://easy-offres.xtradev.ch, avec le compte de démonstration (`demo-peintre@exemple.ch`, ton mot de passe du test). Aucun vrai client : noms, adresses et chantiers inventés.

Deux séances : **A au téléphone** (environ 1 h), **B sur le PC** (environ 30 min). **C**, la tablette, si tu en as une sous la main.

Note chaque écart avec son numéro (A12, B3…) : ce qui casse, ce qui gêne, un mot qu'un artisan ne comprendrait pas, un geste de trop. Une capture d'écran vaut mieux qu'une description.

## Avant de commencer (fait par Claude)

- [x] Le dernier commit de code (9fe1570) déployé sur le test, sonde verte (les commits suivants ne touchent que la documentation).
- [x] Démo remise à zéro sur le test le 18 novembre (deux offres d'exemple, catalogue de peintre de 12 produits).
- [x] Compte d'essai `essai-recette@exemple.ch` créé le 18 novembre avec 2 offres ; mot de passe dans Ubuntu : `cat ~/.offregen-recette-compte`.

## A. Au téléphone

### Connexion et premiers pas

| # | Geste | Attendu |
|---|---|---|
| A1 | Ouvre le site, connecte-toi avec un mauvais mot de passe | « Courriel ou mot de passe incorrect. » |
| A2 | Connecte-toi avec le bon | l'assistant s'ouvre (démo remise à zéro) : quatre étapes ; « Plus tard » ou « Terminer » mènent aux offres |
| A3 | Regarde la barre du bas | Offres, Clients, le micro au centre, Factures, Plus |

### Dicter

| # | Geste | Attendu |
|---|---|---|
| A4 | Touche le micro, parle 3 secondes, termine | « Enregistrement non pris en compte : 5 secondes au minimum », bouton « Recommencer » |
| A5 | Dicte 10 secondes sans rien d'utile (« bon, on verra ça demain ») | une offre vide, pas d'échec |
| A6 | Dicte environ deux minutes, pièce par pièce, pour un client inventé et nouveau : « Offre pour Madame Test, à Porrentruy. Dans le salon, murs, environ trente mètres carrés, deux couches. Plafond vingt mètres carrés. Dans la chambre, murs vingt-cinq mètres carrés, et le cadre de porte à laquer, en bloc deux cents francs. Pour tout le chantier, un déplacement. » | les étapes avancent une à une, l'offre s'ouvre seule |
| A7 | Regarde l'offre | « Général » en tête (le déplacement), puis « Salon », « Chambre », chacune avec son sous-total ; « environ » marqué « À vérifier » ; Madame Test avec l'étiquette « à confirmer » |
| A8 | Sous la ligne du cadre de porte, la note « Prix dicté 200, catalogue … » : touche « Prendre », puis réponds « Non » à « Mettre aussi 200 au catalogue ? » | le prix de la ligne passe à 200, le catalogue ne change pas |
| A9 | Fais pareil sur une autre ligne à prix dicté, s'il y en a, et réponds « Oui » | le catalogue prend le nouveau prix (vérifie dans Plus, Catalogue) |
| A10 | Fais défiler l'offre, téléphone en portrait | rien ne déborde à droite, la page ne se zoome pas (le bug de Luigi) ; quantité, prix et total alignés à gauche sous la désignation |

### Corriger l'offre

| # | Geste | Attendu |
|---|---|---|
| A11 | Touche une quantité, change-la, touche ailleurs | le total suit, « À vérifier » disparaît |
| A12 | Sur une ligne « À vérifier », « C'est juste » | la marque orange disparaît |
| A13 | Touche une corbeille, puis « Annuler » dans le bandeau | la ligne part, puis revient |
| A14 | Touche le nom d'une ligne : dans sa fiche, change la pièce pour « Chambre », enregistre | la ligne passe dans la Chambre, les sous-totaux suivent |
| A15 | « Renommer » sur la pièce « Salon », tape « Séjour » | toutes ses lignes suivent |
| A16 | « + » (Ajouter) : touche la puce « Chambre », cherche « pein », touche un produit | il arrive dans la Chambre, sa quantité ouverte |
| A17 | « + », tape un travail absent du catalogue, mets un prix | la ligne arrive « hors catalogue » ; sur sa fiche, « Créer le produit » ou « Rattacher à un produit » |
| A18 | S'il reste des lignes à vérifier, « Générer l'offre » ouvre la relecture ligne par ligne | « C'est juste » ou « Corriger » pour chacune, puis « Toutes les lignes sont relues » |
| A19 | « Générer l'offre » | « Pour qui est cette offre ? » : « Créer la fiche « Madame Test » », les fiches qui ressemblent ; un choix ouvre directement le PDF |
| A20 | Le PDF dans l'application | rangé par pièce, sous-total par pièce sauf « Général » ; pas de « Total général » en double |
| A21 | « Envoyer » | le partage du téléphone, PDF joint (ne l'envoie à personne) |

### Importer un enregistrement

| # | Geste | Attendu |
|---|---|---|
| A22 | Enregistre une minute avec l'enregistreur habituel du téléphone (dictée inventée), puis Plus, « Importer un enregistrement » | l'offre se prépare comme une dictée ; note le format du fichier (m4a, 3gp…) |
| A23 | Importe un fichier qui n'est pas un audio (un PDF, une photo) | refusé : « pas un enregistrement audio reconnu » |

### La liste des offres

| # | Geste | Attendu |
|---|---|---|
| A24 | Offres : balaie une offre vers la gauche, « Archiver », puis « Annuler » | l'offre part puis revient |
| A25 | Archive-la pour de bon, ouvre « Archives », « Restaurer » | elle revient dans la liste |
| A26 | Trie les offres | l'ordre change ; « Supprimer » sur une offre d'exemple demande confirmation |

### Facturer

| # | Geste | Attendu |
|---|---|---|
| A27 | Plus, Réglages, « Mon entreprise » : IBAN `CH93 0076 2011 6238 5295 7` (exemple de SIX) | « Enregistré » sans bouton ; IBAN groupé par quatre |
| A28 | « Rafraîchissement du séjour », « Générer l'offre », « Facturer » | une feuille monte : « Acompte 1 sur 3 », 30 %, la facture en petit, « Créer la facture · CHF … » |
| A29 | Touche 40 %, puis « CHF » et tape 1000 | la facture et le bouton suivent ; reviens à 30 % |
| A30 | Touche la petite facture | elle s'ouvre en grand, marquée « Aperçu » ; « Retour » ne crée rien |
| A31 | « Créer la facture », puis « Envoyer » | le PDF de la facture ; Facturer, Créer, Envoyer = 3 touchers |
| A32 | Scanne le code du PDF avec ton application bancaire, **sans payer** | elle lit l'entreprise de la démo, l'IBAN, le montant, une référence RF, le numéro de facture |
| A33 | Retour à l'offre, « Facturer », « Facture finale », « Corriger des lignes d'abord » ; ajoute « Nettoyage final », complète son prix, « Créer la facture » | à payer = total moins l'acompte, sans page de confirmation |
| A34 | Onglet Factures, ouvre une facture émise, « Annuler la facture » | elle est barrée ; « Annuler et refaire » propose un nouveau brouillon |

### Clients, catalogue, réglages

| # | Geste | Attendu |
|---|---|---|
| A35 | Clients : touche une fiche, puis « Nouveau client » | la fiche monte par-dessus la liste ; l'adresse propose des rues pendant la frappe, le NPA remplit la localité |
| A36 | Sur une offre, touche le client en haut | « Client de l'offre » : choisir une fiche, en chercher une, en créer une |
| A37 | Plus, Catalogue : cherche « facade », ouvre un produit, change son prix ; crée un produit, puis supprime-le | la liste se réduit pendant la frappe, accents ou pas ; « enregistré » |
| A38 | Réglages : un champ de chaque rubrique | « Enregistré » en haut, sans bouton ; dis-moi si le résumé de chaque rubrique du sommaire te suffit |
| A39 | « Ma marque » : ton vrai logo (PNG ou JPEG), la couleur proposée, un modèle, « Voir en grand » | le modèle en grand avec ton logo ; le PDF suivant le reprend |
| A40 | TVA : taux de l'entreprise, puis sur une ligne d'offre un autre taux | le document détaille le total par taux |

### Le compte d'essai

| # | Geste | Attendu |
|---|---|---|
| A41 | Déconnecte-toi (Plus, « Se déconnecter »), connecte-toi avec `essai-recette@exemple.ch` | l'assistant de bienvenue s'ouvre ; après « Plus tard », la liste dit « Compte d'essai : 2 offres restantes » ; Plus, Catalogue : vide (vu par Claude le 18 novembre) |
| A42 | Fais deux courtes dictées (avec un ou deux produits créés au catalogue) | le compteur descend |
| A43 | Touche le micro une troisième fois | refusé : « Ce compte d'essai a utilisé ses 2 offres. Pour continuer, contactez-nous. » ; l'import est bloqué aussi |

## B. Sur le PC, souris et clavier, fenêtre large

| # | Geste | Attendu |
|---|---|---|
| B1 | Une offre | le menu à gauche ; la colonne des offres avec recherche ; sous le tableau, « Ajouter une ligne : tapez un produit, puis Entrée » |
| B2 | Tape « pein », flèche bas, Entrée | la ligne est ajoutée, le curseur dans sa quantité |
| B3 | Tape « Nettoyage final », Entrée | ligne libre, prix à compléter |
| B4 | Les touches de l'aide sous les lignes (Entrée, Tab, Suppr) | chacune fait ce qu'elle annonce |
| B5 | « Document à côté », puis corrige une quantité | le document suit sans recharger la page |
| B6 | Clique une désignation, puis l'objet de l'offre | une fenêtre au milieu, le menu visible derrière ; Échap ferme |
| B7 | Touche D, ou « Dicter une offre », avec le micro du portable ou d'un casque | la dictée part comme au téléphone |
| B8 | « Facturer » | deux volets : réglages à gauche, facture à droite qui suit chaque choix ; Entrée dans le montant recalcule sans rien créer |
| B9 | « Créer la facture » | le PDF en grand, le panneau à droite, « Télécharger » et « Envoyer » |
| B10 | Ouvre « Ajouter », « Nouveau client » et « Facturer » par la barre d'adresse | le menu de gauche reste partout |
| B11 | Regarde les écrans les uns après les autres | même style partout : boutons, couleurs, titres, espacements ; note ce qui détonne |

## C. Sur une tablette, si tu en as une

| # | Geste | Attendu |
|---|---|---|
| C1 | Une offre, en portrait | toute la largeur, la barre du bas et le micro au centre |
| C2 | En paysage | la liste des offres à gauche, l'offre à droite |
| C3 | « Facturer », puis « Créer la facture » | une fenêtre au milieu ; en paysage, réglages et facture côte à côte |

## Questions restées ouvertes dans les sous-chantiers

Réponds en même temps que la recette, d'un mot chacune :

1. Sous-chantier 13 : « il faut que je voie des offres » visait-il bien les offres d'exemple de la démo ?
2. Sous-chantier 18 : l'adresse de la démo (« Rue de la Gare 4, 2800 Delémont ») n'existe pas au registre et l'écran dit « introuvable ». La garder pour montrer le message, ou en prendre une vraie ?
3. Sous-chantier 21 : « la barre horizontale » à enlever était-elle bien la jauge du compteur ?
4. Sous-chantier 22 : le résumé de chaque rubrique du sommaire des réglages te suffit-il (A38) ?

## Ce que cette recette ne couvre pas

- Le mot de passe oublié, l'écran d'administration, les alertes, la purge, l'avertissement au-delà de 300 postes, l'enregistrement gardé dans le téléphone : à venir (étape 5), chacun avec sa recette.
- Le partage « Envoyer » sur PC dépend du navigateur : Chrome ouvre le partage de Windows quand il le peut, sinon le PDF se télécharge.
- Un échec de traitement ne se provoque pas depuis l'écran : « Réessayer » et « Retirer » sur une offre en échec ont été vérifiés par Claude seulement.
- La qualité de l'extraction sur de vraies dictées : c'est le corpus du sous-chantier 29, pas cette recette.

## Si quelque chose casse

L'écran exact (capture), l'heure, et le numéro du geste. Claude lit le journal du site de test de son côté.
