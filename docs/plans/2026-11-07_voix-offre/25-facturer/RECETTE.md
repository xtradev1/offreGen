# Recette du sous-chantier 25 : facturer en trois touchers

Sur le site de test, comme ton essai du 15.11 : https://easy-offres.xtradev.ch, compte `demo-peintre@exemple.ch`
(mot de passe : `cat ~/.offregen-demo-compte` dans Ubuntu). La démo y est remise à zéro avant la recette. Fais la
partie A au téléphone, la partie B sur le PC (souris), la partie C sur une tablette si tu en as une sous la main.

Préalables, faits par Claude avant de te passer la main :

- [ ] Migration `Version20261115000003` (numéro de facture facultatif) jouée sur le test : la sonde le vérifie.
- [ ] Commit déployé sur le test et sonde verte (voir « État des vérifications »).
- [ ] Démo remise à zéro sur le test.

## A. Au téléphone

| # | Geste | Attendu |
|---|---|---|
| 1 | Une offre quelconque | la carte du client porte une loupe (on y cherche une fiche) |
| 2 | « Offres », « Rafraîchissement du séjour », « Générer l'offre », puis « Facturer » en bas du PDF | une feuille monte : « Acompte 1 sur 3 » choisi, 30 % allumé, la facture dessinée en petit, le bouton « Créer la facture · CHF 932.05 » et la phrase dessous |
| 3 | Touche 40 % | la petite facture et le bouton suivent : CHF 1'242.70 |
| 4 | Touche « CHF », tape 1000 | la consigne, puis CHF 1'000.00 sur le bouton et la facture |
| 5 | Touche la petite facture | elle s'ouvre en grand, marquée « Aperçu » ; « Retour » revient à la feuille, rien n'est créé |
| 6 | Reviens à 30 %, « Créer la facture » | le PDF de la facture 2026-001 s'ouvre, « Envoyer » en bas, le panneau (montant, échéance, client) sous le PDF |
| 7 | « Envoyer » | le partage du téléphone, le PDF joint. Compte : Facturer, Créer la facture, Envoyer = **3 touchers** |
| 8 | Onglet « Factures », touche 2026-001 | le PDF, jamais la version HTML |
| 9 | Retour à l'offre, « Facturer », « Facture finale » | « Toutes les lignes de l'offre, moins les acomptes déjà facturés (CHF 932.05) » ; bouton « Créer la facture · CHF 2'174.75 » |
| 10 | « Corriger des lignes d'abord » | le brouillon : « N° 2026-002 à la création » ; change une quantité, touche « + » |
| 11 | Dans « Ajouter », tape « pein », touche un produit | la feuille reste ouverte et dit « « … » ajoutée » |
| 12 | Tape « Nettoyage final » | rien au catalogue : « Ajouter « Nettoyage final » » s'affiche |
| 13 | Touche-le, puis « Terminé » | le brouillon montre les deux lignes ajoutées ; « Nettoyage final » sans prix, à compléter |
| 14 | Complète le prix, « Créer la facture » | sans page de confirmation : le PDF de 2026-002, à payer = total moins CHF 932.05 |
| 15 | Dicte une courte offre pour un client nouveau (« pour Madame Test, à Porrentruy, … ») | sur l'offre, le nom entendu avec l'étiquette orange « à confirmer » |
| 16 | « Générer l'offre » | « Pour qui est cette offre ? » : « Créer la fiche « Madame Test » », les fiches qui ressemblent, la recherche ; un choix ouvre le PDF de l'offre, sans autre toucher |

## B. Sur le PC (souris), fenêtre large

| # | Geste | Attendu |
|---|---|---|
| 1 | Une offre | le menu à gauche ; sous le tableau, la ligne « Ajouter une ligne : tapez un produit, puis Entrée » |
| 2 | Tape « pein » dans cette ligne, flèche bas, Entrée | la ligne choisie est ajoutée, le curseur dans sa quantité |
| 3 | Tape « Nettoyage final », Entrée | ligne libre ajoutée, prix à compléter |
| 4 | Clique la désignation d'une ligne, puis l'objet de l'offre | chacun s'ouvre en fenêtre au milieu, le menu visible derrière ; Échap ferme |
| 5 | « Facturer » | la fenêtre en deux volets : réglages à gauche, facture à droite, qui suit chaque choix ; Entrée dans le montant recalcule, ne crée rien |
| 6 | « Créer la facture » | le PDF en grand, le panneau à droite (montant, état, client, offre, « Annuler la facture »), « Télécharger » et « Envoyer » en bas |
| 7 | Ouvre « Ajouter », « Nouveau client », « Facturer » en pages seules (barre d'adresse) | le menu de gauche reste partout |

## C. Sur une tablette, si tu en as une (ta demande : « un vrai responsive pour la tablette »)

| # | Geste | Attendu |
|---|---|---|
| 1 | Une offre, en portrait | toute la largeur, sans bandes vides ; la barre du bas et le micro au centre ; les cases des lignes plus larges |
| 2 | Tourne la tablette en paysage | la liste des offres à gauche, l'offre à droite, la barre du bas sous l'offre |
| 3 | « Facturer » | une fenêtre au milieu : en paysage, réglages à gauche et facture à droite ; en portrait, l'un sous l'autre |
| 4 | « Créer la facture » | le PDF sur la largeur ; en paysage, le panneau à droite ; en portrait, dessous |

Dis-moi ce qui gêne encore : un geste de trop, un mot, la sobriété d'Ajouter (ta remarque sur E7).

Si quelque chose casse : `var/log/app.log` du site, la console du navigateur (PC), et l'écran exact (capture).

## Ce qui n'est PAS couvert

- Le partage « Envoyer » sur PC dépend du navigateur : Chrome ouvre le partage de Windows quand il le peut, sinon le PDF se télécharge. Non vérifiable par Claude (partage sans affichage).
- L'aperçu avant création est le document HTML (même gabarit que le PDF), marqué « Aperçu » ; la facture créée, elle, se voit toujours en PDF. Si tu veux aussi le PDF à ce moment-là, c'est une seconde d'attente par choix (décision fragile du `PLAN.md`).
- Un montant tapé sur une offre à plusieurs taux de TVA se répartit au prorata du HT de chaque taux ; aucun comptable ne l'a encore relu.
- Avant la création (aperçu, brouillon sans numéro), la section de paiement montre un cadre « Code QR à la création » : rien à scanner tant que la référence n'est pas fixée (revue).
- Sans arrondi aux 5 ct, un montant tapé au centime peut tomber à 1 ct près (environ 7 % des cas) : le bouton annonce le montant exact avant la création.
- Tablette : vérifiée en émulation (iPad 820 × 1180, au doigt), pas sur un appareil réel ; la règle vise les écrans tactiles d'au moins 700 px.
- La vue PC plus belle : notée pour plus tard, à ta demande.
- Le lot 9 du sous-chantier 24 (revue et recette du 24) suit cette recette.

## État des vérifications automatiques

| Vérification | Résultat | Preuve |
|---|---|---|
| Analyse statique | PHPStan : aucune erreur | `ETAT.md`, Preuves |
| Suite de tests | 473 tests, 2929 assertions, verts | `ETAT.md`, Preuves |
| Parcours cliqués | 390, 1440 px et tablette 820 × 1180 / 1180 × 820, démo locale, aucune erreur JS, aucun débordement | `ETAT.md`, Preuves |
| Revue | correction et sécurité : 3 défauts à corriger, 7 mineurs ou bas, tous traités sauf S3 (sans effet hors de l'artisan) | `REVUE.md` |
| Déploiement test | voir `ETAT.md`, Preuves | sonde |
