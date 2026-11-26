# SPEC : options et variantes dans l'offre

Rassemble les décisions de Xavier : O1 à O6 des 19 et 20 novembre 2026 (`docs/plans/2026-11-19_modeles-offre/ETAT.md`,
`MAQUETTE-modeles-v2.html`, section « Options et variantes ») et ses réponses du 22 novembre (journal de
`docs/plans/2026-11-21_lignes-conditions/ETAT.md` dans `~/offreGen-lignes`). Ce qui reste ouvert est sous « Points à
trancher ».

## Problème (constaté dans le code, 8385e78)

- Une ligne d'offre est toujours dans le total : la table `ligne` n'a ni nature (ligne, option, variante) ni lien
  vers une ligne remplacée (`migrations/Version20261107000001.php` et suivantes).
- `src/Offre/Totaux.php` additionne toutes les lignes non supprimées ; l'acompte et la facture finale en dépendent
  (`src/Facture/PlanAcomptes.php`, `FactureControleur::lireFacturer`).
- La consigne d'extraction (`src/Offre/ExtracteurApi.php`, `CONSIGNE` et l'outil `proposer_offre`) ne connaît ni
  « en option » ni « variante » : un travail proposé en option devient une ligne du total, une variante devient une
  seconde ligne qui double le poste.
- Le banc du sous-chantier 29 n'existe pas : ni `outils/banc-corpus.php`, ni corpus (`OffreGen/corpus/audio/` est
  vide). Les mesures du 17 novembre ont tourné avec des scripts du scratchpad, aujourd'hui perdus.

## Utilisateurs

L'artisan, au téléphone et sur PC. Le client lit le document, coche à la main sur le papier signé, ou répond à
l'artisan, qui reporte son choix dans l'application.

## Périmètre

1. **Option** (O1) : un travail en plus que le client prend ou laisse. Hors du total tant qu'elle n'est pas
   retenue. Sur le document : un bloc sous le total, une case à cocher et le montant de chaque option, puis une
   ligne « Total avec toutes les options ».
2. **Variante** (O2) : une autre façon de faire une ligne de l'offre. Sur le document : sous la ligne qu'elle
   remplace, en italique, avec une case, son prix unitaire et l'écart (« +252.00 » ou « -80.00 »). Le total compte
   la ligne de base tant que la variante n'est pas retenue. Même quantité que la ligne de base, modifiable.
3. **Rabais** (réponse du 22.11) : un rabais en pourcentage vaut sur tout, options comprises : le « Total avec
   toutes les options » le déduit aussi sur elles. Un rabais en francs reste le même montant, avec ou sans options.
4. **Dictée** (O3) : « en option », « option », « variante », « en variante », « ou alors », « à la place de » sont
   reconnus. Une variante dont la ligne de base n'est pas retrouvée devient une option « À vérifier ». Une option
   ou une variante sans prix dit prend le prix du catalogue, ou reste sans prix (« À vérifier »), comme une ligne.
5. **Écran, sans la voix** (O4) : à l'ajout d'un produit, un toucher « Option » ou « Variante ». La variante
   remplace par défaut la dernière ligne du même groupe (pièce ou catégorie), modifiable. Sur une ligne existante :
   un toucher la passe en option ou la remet dans le total ; « Ajouter une variante » depuis la ligne.
6. **Choix du client** (O5) : l'artisan coche les options et variantes retenues. Le total se recalcule ; l'offre
   mise à jour se renvoie (bandeau « Modifiée depuis l'envoi · Renvoyer », déjà en service) ; la facture ou
   l'acompte se crée en un toucher et reprend ce qui est coché : une option retenue devient une ligne de la
   facture, une variante retenue prend la place de sa ligne de base, le reste n'y paraît pas.
7. **Les douze modèles** : les deux blocs en offre, dans chacun (Récapitulatif et Marge compris, offre par pièce
   comprise). Une facture ne montre jamais d'option ni de variante non retenue.
8. **Banc** (sous-chantier 29, tâche Origin 450) : `outils/banc-corpus.php` écrit dans ce chantier, selon la SPEC
   du 29 (note par ligne, lignes en trop, trois passes, moitié de réglage et moitié de contrôle, jetons, coût en
   centimes, durée). La note d'une ligne compte en plus sa nature (ligne, option, variante) et, pour une variante,
   sa ligne de base. Aucune consigne nouvelle ne part sur le test sans être passée par ce banc.

## Hors périmètre

- Commandes vocales sur une offre existante (« passe les plinthes en option ») : aucun code de commande vocale
  n'existe, c'est le sous-chantier 06.
- Signature ou choix en ligne par le client (lien, page de réponse) : le client répond à l'artisan, qui coche.
- Options sur une facture : une facture ne porte que ce qui est retenu.
- Variante d'une option, option d'une variante : un seul niveau.
- Le réglage de la consigne vers 95 % sur toutes les dictées (sous-tâche 451) : ce chantier ne mesure que ce qu'il
  change, et refuse de faire reculer le reste.

## Critères de succès

- Une offre avec deux options et une variante : le total, la TVA par taux, le rabais et l'arrondi ne comptent que
  les lignes et ce qui est retenu ; « Total avec toutes les options » est juste au centime (tests de `Totaux`).
- Cocher une option sur l'écran de l'offre met le total à jour sans recharger la page, et le bandeau « Renvoyer »
  paraît (parcours cliqué sur le test, à 390 et 1280 px).
- La facture finale et l'acompte créés après le choix reprennent exactement ce qui est coché (PDF lu).
- Les douze modèles rendent les deux blocs en offre sur une page lisible, sans bloc qui touche le pied (tests de
  gabarits, images regardées).
- Le banc, sur les dictées d'options et de variantes, donne la bonne nature et la bonne ligne de base ; sur les
  dictées sans option, le taux ne recule pas par rapport à la consigne en service (rapport du banc collé dans
  `ETAT.md`).

## Contraintes

- `CODING_RULES.md` : un toucher pour une action fréquente, cibles de 48 px au moins, toute correction vocale a
  son équivalent à l'écran, test rouge avant le code, recette à la souris sur le site de test.
- Leçon du sous-chantier 28 : ajouter un champ à l'appel principal a fait perdre des prix dictés (4 à 6 justes
  sur 7 au lieu de 6 à 7). La façon d'extraire (champ dans l'appel principal, ou second appel comme pour les
  pièces) se choisit au banc, pas à l'intuition.
- Réutiliser : `Totaux`, `Rabais`, `SupportLignes` (partagé avec le brouillon de facture, où les options n'ont
  pas cours), le bandeau de renvoi, les macros `lignes`, `lignes_marge` et `totaux` de `_blocs.html.twig`.
- Le chantier des lignes et conditions (`~/offreGen-lignes`) attend la recette de Xavier sur les mêmes fichiers
  (`Totaux.php`, `_blocs.html.twig`, `FactureControleur.php`, `app.js`) : rebaser sur la tête de la branche avant
  chaque commit.

## Points à trancher (gate intention)

Tranchés le 2026-11-22 par Xavier : « 1 oui, 2 ok, 3 oui, 4 oui, 5 crée-les ». Les propositions ci-dessous valent décision.

1. **Corpus du banc.** Aucune vraie dictée n'existe. Proposition : je fabrique d'abord des dictées écrites
   (transcriptions inventées, une quinzaine, dont six sans option pour la non-régression), qui servent à
   développer ; puis tu enregistres cinq à huit vraies dictées avec options et variantes avant la mise en service
   sur le test. Le banc inventé est connu pour être optimiste (94 à 98 % inventé contre 5 à 6 sur 7 sur la dictée
   de Luigi).
2. **Document après le choix du client.** Proposition : une option retenue entre dans les lignes, au rang où
   elle était dictée, sans mention ; une variante retenue prend la place de sa ligne de base, qui disparaît ; ce
   qui n'est pas retenu reste dans les blocs, case vide. Autre lecture : tout reste affiché, cases cochées.
3. **Plusieurs variantes d'une même ligne** (peinture lessivable ou vinyle). Proposition : permis, une seule
   retenue à la fois.
4. **« Total avec toutes les options »** : proposition, les options seulement ; les variantes n'y entrent pas
   (plusieurs variantes d'une ligne s'excluent, un total « avec toutes » n'aurait pas de sens).
5. **Suivi Origin** : la tâche 499 n'a pas de sous-tâches. Proposition : Spec et maquette (2 h) ; banc et corpus
   écrit (4 h) ; données, totaux, facturation (5 h) ; écran (5 h) ; douze modèles (3 h) ; extraction mesurée au
   banc (4 h) ; revue et recette (3 h). 26 h au lieu de 24.

Les choix d'écran (où se cochent les options, comment se dit « Option » et « Variante » à l'ajout) se tranchent sur
la maquette, après ce gate.

## Risques

- Extraction : une consigne qui cherche les options peut en voir partout (« peut-être » dit pour une quantité)
  ou en rater ; le banc le dira, mais sur des dictées inventées tant que les vraies manquent.
- Les douze modèles : un bloc d'options allonge l'offre ; Lettre tient six lignes sur une page, le bloc la fera
  passer sur deux plus souvent.
- Acompte facturé avant le choix du client : la facture finale doit déduire l'acompte d'un total qui a changé ;
  le code actuel le fait déjà (finale négative refusée à l'émission), à couvrir par un test.
- Conflits de fichiers avec le chantier des lignes et conditions tant qu'il n'est pas clos.
