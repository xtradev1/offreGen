# Recette : options et variantes

Sur le site de test, `https://easy-offres.xtradev.ch`, au téléphone puis sur PC. Tout se fait à la main, en une passe ;
coche au fur et à mesure. Les montants attendus sont recalculés à la main.

## Préalables

- [ ] Accord de Xavier pour déployer sur le test (données toujours inventées ?).
- [ ] Déploiement de la tête de `chantier-voix-offre` par `outils/deployer-test.sh` (Claude, sur accord). Il joue la migration `Version20261122200001` (trois colonnes sur `ligne` : `nature`, `remplace_id`, `retenue`, additives, avec valeurs par défaut).
- [ ] Contrôle de compatibilité (`docs/DEPLOIEMENT.md`, Migrations) : l'ancienne version passe sa sonde sur la base migrée (Claude, au déploiement).
- [ ] Compte de recette : `options-recette@exemple.ch`, démo peintre (Claude le crée au déploiement ; mot de passe dans `~/.offregen-test-options-recette-compte`, à te donner).
- [ ] Pour le scénario 6 : deux ou trois des fiches 21 à 28 enregistrées (`OffreGen/corpus/FICHES.md`, section « Options et variantes »).

## Scénario 1 : une option et une variante, sans la voix

| | |
|---|---|
| URL | `https://easy-offres.xtradev.ch/clients`, puis la fiche d'un client et une de ses offres |
| Compte | `options-recette@exemple.ch` |

Gestes :
1. Ouvre une offre d'exemple qui a une ligne « Peinture des murs, 2 couches ».
2. Touche « Ligne » en bas. Dans « Elle va », touche « Option ». Touche un produit du catalogue (par exemple « Laquage de porte, 2 faces »).
3. Touche « Terminé ». Corrige la quantité de l'option à 3.
4. Touche la ligne « Peinture des murs ». Dans sa fenêtre, touche « + Variante de cette ligne ».
5. Ouvre « Ligne libre avec unité et prix » : « Peinture lessivable », quantité vide, unité m², prix 34. Touche « Ajouter la ligne ».

Attendu :
- l'option est dans un groupe « Options, hors du total », sous les totaux, avec la case « Retenue par le client » ;
- la variante est sous « Peinture des murs », en italique, avec le mot « Variante », la même quantité, et l'écart (« +6.00 » fois la quantité) sous son montant ;
- le total en bas ne change pas ; « Total avec toutes les options » paraît sous le groupe des options.

Si ça casse : `templates/_rangee.html.twig`, `templates/ajout.html.twig`, `LigneControleur::ajouter`, table `ligne` (colonnes `nature`, `remplace_id`).

## Scénario 2 : le choix du client, sans recharger

Gestes :
1. Sur la même offre, coche « Retenue par le client » sous la variante.
2. Puis sous l'option.
3. Envoie l'offre (« Générer l'offre », « Envoyer »), puis décoche l'option.

Attendu :
- 1 : le total en bas augmente de l'écart de la variante (TVA comprise) sans que la page se recharge ; « Peinture des murs » reste visible, grisée, montant barré, « Remplacée par la variante retenue » ;
- 2 : l'option passe « Retenue, dans le total » ; s'il n'y a plus d'option libre, « Total avec toutes les options » disparaît ;
- 3 : le bandeau « Modifiée depuis l'envoi · Renvoyer » paraît.

Si ça casse : `public/js/app.js` (`corrections`), route `/offres/{id}/lignes/{ligne}/retenue`, console du navigateur.

## Scénario 3 : facturer le choix

Gestes :
1. Touche « Facturer ». En haut de la feuille, « Choix du client » montre les mêmes cases.
2. Coche l'option dans la feuille : l'aperçu et le montant du bouton suivent.
3. Crée un acompte de 30 %, puis « Facturer » encore, « Facture finale », « Créer la facture ».

Attendu : l'acompte vaut 30 % du total avec le choix ; la facture finale porte la variante à la place de « Peinture des murs », l'option retenue, aucune option libre, et déduit l'acompte.

Si ça casse : `FactureControleur::lireFacturer`, `templates/facturer.html.twig`, table `facture_ligne`.

## Scénario 4 : le document, dans plusieurs modèles

Gestes :
1. Sur une offre avec une option libre et une variante non retenue, ouvre le document (« Document à côté » sur PC, ou l'aperçu).
2. Dans « Réglages », « Ma marque », change de modèle (Sobre, Lettre, Technique, Marge, Récapitulatif) et regarde le document à chaque fois.
3. Retiens la variante et l'option, regarde le document.

Attendu :
- 1 et 2 : la variante sous sa ligne, case vide, « Variante : … », prix unitaire, écart ; un bloc « Options » sous le total, case vide, montant, puis « Total avec toutes les options » ; dans Technique, la variante est droite (pas d'italique, voir REVUE) ;
- 3 : la variante a pris la place de sa ligne, l'option retenue est dans les lignes ; le bloc ne garde que les options libres.

Si ça casse : `templates/document/_blocs.html.twig` (macros `lignes`, `lignes_marge`, `options`), `public/css/document.css`.

## Scénario 5 : les gestes de rattrapage

Gestes :
1. Touche une ligne qui a une variante : « Option » et « Variante » sont grisés (« Elle a des variantes : elle reste dans le total »).
2. Supprime « Peinture des murs » (corbeille) : sa variante passe dans le groupe « Options ». Touche « Annuler » dans le message.
3. Touche une option, passe-la « Dans le total », « Enregistrer ».

Attendu : 2, après « Annuler », la variante est revenue sous sa ligne, avec son choix ; 3, la ligne rejoint son groupe et compte dans le total.

Si ça casse : `DepotOffres::changerNature`, `LigneControleur::corriger`, `Options::natureEffective`.

## Scénario 6 : la dictée

Gestes :
1. Dicte la fiche 21 (option avec prix), la 23 (variante avec prix) et la 27 (le mot « option » sans option), avec le micro de l'application, sur le compte de recette.
2. Ouvre chaque offre créée.

Attendu : 21, « Laquage de porte » en option, à vérifier ; 23, la lessivable en variante des murs, même quantité, à vérifier ; 27, aucune option. Puis dépose les fichiers dans `corpus/audio/` : je les passe au banc avant la mise en service.

Si ça casse : le journal du test (`offreGen, options :`), `ExtracteurApi::classerOptions`, `validerOptions`.

## Ce qui n'est PAS couvert

- **Les vraies dictées n'ont pas encore passé le banc.** Le corpus est écrit par Claude, connu pour être optimiste. La règle ajoutée à la consigne principale vaut pour toutes les offres et toutes les activités. Le banc sur tes enregistrements (fiches 21 à 28) est la condition de la mise en service.
- **Activités Extérieur et Prestations :** la règle de la consigne leur vaut aussi, jamais mesurée.
- **Hors périmètre de la SPEC :**
  - les commandes vocales sur une offre existante (sous-chantier 06) ;
  - le choix en ligne par le client ;
  - les options sur une facture ;
  - les variantes d'options.
- **Constats d'avant le chantier :** sur une facture, une ligne libre est rangée sous « Hors catalogue », que le client lit ; plus visible avec les variantes, souvent libres.
- **À trancher par toi (REVUE, C5) :** un rabais en francs reste plafonné au sous-total. Plus grand que le total sans options, il compte moins que dans le total avec les options.
- **Mise en page :** avec un bloc d'options, la plupart des modèles laissent les conditions et les signatures seules en page 2 sur une offre de six lignes.
- **Le banc ne couvre pas la 12** (« ou alors » sur un travail absent de l'offre) : elle reste dans le total au lieu d'une option, trois passes sur trois.

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Analyse statique | sans erreur | `ETAT.md`, Preuves |
| Suite de tests | 1254 tests, 10589 assertions, verts | `ETAT.md`, Preuves |
| Revue | trois lentilles, 13 trouvailles retenues et corrigées, 2 écartées, 2 hors périmètre | `REVUE.md` |
| Banc (corpus écrit) | voir ci-dessous | `corpus/ecrit/rapports/` |

```
Corpus écrit, quinze dictées, trois passes, Qwen/Qwen3.8-27B-FP8, après les corrections de la revue :
Dictées sans option : taux 93.3 % (84 justes sur 90), 0.33 corrections par offre   (référence d'avant le chantier : 93.3 %)
Dictées avec options : taux 96.3 % (130 justes sur 135), 0.19 corrections par offre, natures 39/42   (référence : 58.3 %, natures 0/42)
Coût moyen 0.24 ct par offre, durée moyenne 3.8 s (45 passes)
Fautes restantes : 01 « reboucher et poncer » rangé en couche d'impression (d'avant le chantier) ; 12 « ou alors » sur un travail absent de l'offre ; 13 « murs » rangés en plafond, une passe sur trois (d'avant le chantier).
```
