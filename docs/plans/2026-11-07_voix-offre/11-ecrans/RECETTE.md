# Recette du sous-chantier 11 : parler, corriger, catalogue, réglages

Tout tourne sur ton PC, comme pour le sous-chantier 10 (`10-ia-locale/RECETTE.md`, partie « Lancer »).
Ouvre http://localhost:8000 dans Chrome ou Edge sur le PC, connecte-toi avec `demo-peintre@exemple.ch`
(mot de passe : `cat ~/.offregen-demo-compte` dans Ubuntu).

| # | Geste | Attendu |
|---|---|---|
| 1 | En bas, touche « Catalogue », cherche « facade » | la liste se réduit à mesure que tu tapes, accents ou pas |
| 2 | Touche « Peinture anti-moisissure » (Prix ?), mets 24, enregistre | retour au catalogue, bandeau « enregistré », prix 24.00 |
| 3 | « Réglages » : regarde la TVA et l'arrondi, ne change rien | tes réglages actuels : 8,1 %, arrondi aux 5 centimes |
| 4 | « Offres », « Nouvelle offre », « Enregistrer » ; autorise le micro | le minuteur tourne, le rond rouge bat |
| 5 | Dicte une visite fictive d'une minute, puis « Terminer » | barre d'envoi, puis « Préparation de l'offre », puis « Voir l'offre » |
| 6 | Dans l'offre, touche une quantité, tape une autre valeur, puis touche ailleurs | la page revient sur la ligne, le total a changé, « À vérifier » a disparu |
| 7 | Sur une ligne « À vérifier », touche « C'est juste » | la marque orange disparaît |
| 8 | Touche une corbeille, puis « Annuler » dans le bandeau du bas | la ligne part, puis revient |
| 9 | « Ajouter », cherche un produit, touche-le | il arrive dans l'offre, sa quantité est déjà ouverte : tape-la, Entrée |
| 10 | « Ajouter », tape un travail absent du catalogue, mets un prix, « Ajouter la ligne libre » | la ligne arrive dans « Hors catalogue » |
| 11 | Touche le nom d'une ligne | sa fiche : désignation, quantité, unité, prix ; pour une ligne libre, « Garder aussi dans le catalogue » |

Dis-moi ce qui gêne : un geste qui demande trop de clics, un texte peu clair, une zone trop petite pour le pouce.

## Limites connues

- Depuis ton téléphone, le bouton « Enregistrer » ne s'affiche pas : le navigateur ne donne le micro qu'à un site
  en https, et `localhost` n'existe que sur le PC. Il viendra avec le site de test. L'import d'un fichier marche.
- Changer la TVA ou l'arrondi change aussi le total des offres déjà faites (il n'y a pas encore d'envoi au client).
- Pas encore : commandes vocales, majoration pour travail difficile, remise, aperçu, envoi.
