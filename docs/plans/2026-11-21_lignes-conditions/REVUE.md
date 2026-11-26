# REVUE : lots 2 à 7 (relecteur indépendant, Opus, 2026-11-22)

<!-- Rapport du relecteur, tel quel ; verdicts de la session principale à la fin. -->

# Revue du diff « lignes-conditions » (copie WSL ~/offreGen-lignes, non commité)

Périmètre lu : `git diff HEAD` de src/, config/, templates/ (offre, facture, facturer, document/_base, _blocs, lettre, sobre, recap), public/js/app.js, les 5 migrations, les fichiers nouveaux (Rabais, Conditions, 3 contrôleurs, 3 gabarits).
Tests lancés (base `offregen_lignes_test` de `.env.test.local`), tous verts : TotauxTest (21), RabaisTest (7), ConditionsTest (6), RabaisEcranTest (10), EscompteFactureTest (5), LibelleAcompteTest (7), RepresentantTest (8), ConditionsOffreTest (9), RenvoiOffreTest (4), IsolationRoutesTest (11, 1296 assertions), FacturesEcranTest (25).
Sonde de calcul hors dépôt : `scratchpad/sonde.php` (Totaux, Rabais, Conditions, escompte).
Rien de bloquant trouvé. Pas de fuite entre artisans, pas de XSS, migrations additives.

## Important

### I1. Un rabais posé après des acomptes émis ne prévient pas et casse la finale plus tard
- Où : `src/Http/Controleur/RabaisControleur.php:50-72` (aucune vérification des factures de l'offre) ; le blocage n'arrive qu'à `FactureControleur::raisonsDeNePasEmettre` (l. 660, « montant à payer négatif »).
- Scénario : offre 10 000 TTC, plan 30/30/40. Acomptes 1 et 2 émis (6 000). L'artisan ajoute un rabais CHF 5 000 HT. L'écran l'accepte (« Rabais ajouté »), le total tombe à environ 5 400. « Facturer » refuse la finale (a_payer négatif). Seule sortie : annuler un acompte émis. Variante sans blocage : rabais 10 % après l'acompte 1 ; l'acompte 2 « (30 %) » se compte sur la nouvelle base, les deux 30 % ne portent plus sur le même montant.
- Statut : vérifié en lisant (pas de test écrit, lecture seule). Le défaut existait déjà pour une baisse de lignes ; le rabais en fait un geste d'un toucher.
- Correction : dans `RabaisControleur::enregistrer` (et `retirer`, sens inverse sans risque), calculer le TTC après rabais et refuser (422) s'il passe sous `dejaFacture()` ; afficher un avertissement dans `rabais.html.twig` quand l'offre a des acomptes émis ou une finale émise.

### I2. Rabais plafonnés : le document affiche un pourcentage faux
- Où : `src/Offre/Totaux.php:58-64` (plafond silencieux) et l'affichage `templates/document/_blocs.html.twig:128`, `templates/offre.html.twig:52`, `templates/facture.html.twig:28`.
- Scénario (sonde, cas 1) : sous-total 1 000.00, rabais A 60 %, rabais B 60 %. Montants appliqués 600.00 et 400.00. Le PDF imprime « B 60 % − 400.00 » et un total 0.00. Même chose avec un rabais CHF plus grand que le sous-total. Aucune validation à la saisie (`Rabais::depuisSaisie` ne connaît pas le sous-total, `RabaisControleur` non plus).
- Statut : vérifié par la sonde.
- Correction : refuser à l'enregistrement quand la somme des rabais dépasse le sous-total (message avec le maximum possible) ; à défaut, ne pas imprimer le pourcentage d'un rabais rogné.

## Mineur

- M1. `templates/document/_base.html.twig:26` : offre représentée sans nom de client (dictée sans client) donne « Pour le compte de » suivi de rien. Vérifié en lisant. Correction : n'écrire la mention que si la liste jointe est non vide.
- M2. `FactureControleur.php:670, 773` + `_base.html.twig:21-22` : `representant_nom` est copié même quand `factures_adressees = 'client'` ; la facture « chez lui » porte encore « Représenté par X », et en « p.a. » le nom figure deux fois (adresse et mention). À trancher avec Xavier (D10). Vérifié en lisant.
- M3. `ConditionsControleur.php:46-60` : « Enregistrer » sans rien changer fige les conditions du compte sur l'offre (elle ne suivra plus les réglages) et repousse `modifiee_le` (date du document, validité, bandeau « Modifiée depuis l'envoi »). `changerConditions(null)` n'a aucun appelant (grep) : pas de retour aux conditions du compte. Correction : ne rien écrire si les conditions lues égalent celles en vigueur ; ajouter « Reprendre mes réglages ».
- M4. `ConditionsControleur::texte` (l. 67-81) : texte refusé (plus de 1000) renvoie 303 comme un succès ; `fetch` suit la redirection, `r.ok` vaut vrai, `app.js` affiche « Enregistrée ». Le `maxlength` du textarea limite le cas. Vérifié en lisant. Correction : 422 en JSON pour l'appel JS.
- M5. `Conditions::depuisSaisie` : aucun contrôle escompte à J jours plus long que l'échéance (« net 10 jours », « escompte 2 % à 30 jours ») ; la facture dit « Payable jusqu'au J+10 » puis « Escompte si payé jusqu'au J+30 ». Vérifié en lisant.
- M6. `DepotOffres::rattacherClient` (l. 261) ne vérifie pas `representant_id` : le client peut devenir sa propre fiche représentante après coup (`changerRepresentant` l'interdit seulement à la pose). Document « X, pour le compte de X ». Vérifié en lisant.
- M7. Finale émise avant le chantier : l'écran HTML (`donneesDe`, l. 752-757) liste maintenant chaque acompte, le PDF figé montre « Acomptes déjà facturés ». Montants identiques, présentation différente écran/PDF. Vérifié en lisant.
- M8. Brouillon de finale (« Corriger des lignes ») : il copie les rabais à sa création ; changer ensuite les rabais de l'offre ne le touche pas et aucune route ne modifie les rabais d'une facture. L'artisan voit deux totaux. Plausible (même logique que les lignes copiées).
- M9. `FactureControleur::adresseDeFacture` + `DepotFactures::creer` l. 74 : « p.a. » + nom (190) + adresse du représentant, coupé à 255 caractères sans avertir. Plausible, rare.
- M10. `RabaisControleur` désigne un rabais par sa place : deux onglets, un retrait dans l'un, « Retirer » dans l'autre retire le suivant. Plausible.
- M11. Migration `Version20261122000004` : `representant_id` sans index ni clé étrangère (choix cohérent avec la jointure filtrée), mais `effacerExemplesSansOffre` fait maintenant un `OR` sur deux colonnes. Coût seulement.
- M12. `Totaux::repartir` avec une ligne négative (avoir) sur un autre taux : le taux de l'avoir reçoit une part négative du rabais (sonde, cas 3). Prorata exact, total juste ; noté pour mémoire.

## Vérifié sans défaut
- Calcul : sous-total, rabais, HT, TVA par taux, arrondi. Somme des HT par taux égale au HT après rabais (sonde, cas 2, trois taux et deux rabais) ; reste d'arrondi au plus gros taux. Acompte 30 % puis finale sur offre rabaissée : 52 500 + 122 500 = 175 000 (sonde, cas 4).
- Isolation : toutes les nouvelles requêtes filtrent `artisan_id` ; jointure `rp` filtrée ; fiche d'un autre artisan refusée par `clientDeLArtisan`. IsolationRoutesTest couvre les 9 nouvelles routes (vert).
- XSS : `nl2br` de Twig échappe avant (essai direct : `<script>` sort en `&lt;script&gt;`) ; les deux `|raw` ajoutés portent des textes fixes. `strict_variables` actif : tous les tableaux d'offre viennent de `SELECT_OFFRE`, de `ReglagesControleur::exemple` ou d'`OffreDEssai`, qui ont les nouvelles clés.
- CSRF : jeton contrôlé pour tout POST dans `Noyau.php:126-130` ; les formulaires et le `fetch` des textes l'envoient.
- Facture émise : rabais, libellé, escompte, représentant copiés à la création ; PDF figé à l'émission ; `Montants::de` relit les rabais de la facture. Annuler et refaire recopie ces champs et, en plus, `offerte` (correctif au passage).
- Factures d'avant le chantier : colonnes NULL ou 0, `Rabais::liste(null)` vide, libellé absent remplacé par « Acompte N », escompte nul sans mention.
- Migrations 0002 à 0006 : uniquement des `ADD` de colonnes NULL ou à défaut ; aucun `MODIFY`, `DROP` ni `UPDATE` dans `up()`.
- 12 modèles : `b.texte` avant et après dans les 11 modèles à blocs (2 appels chacun), Lettre en ligne ; escompte de facture dans `_blocs` et dans Lettre.

## Verdicts (session principale, 2026-11-22)

| Point | Verdict | Suite |
|---|---|---|
| I1 | Confirmé en relisant `RabaisControleur` | Corrigé : refus (422) si l'offre passerait sous les acomptes émis ; avertissement dans la fenêtre. Test `testUnRabaisNeFaitPasPasserLOffreSousLesAcomptesEmis`. |
| I2 | Confirmé en relisant `Totaux::rabais` | Corrigé : refus si la somme des rabais dépasse le sous-total. Test `testDesRabaisQuiDepassentLeSousTotalSontRefuses`. |
| M1 | Confirmé | Corrigé (`_base.html.twig`), test `testSansNomDeClientPasDePourLeCompteDeVide`. |
| M2 | Confirmé | Question à Xavier : « Représenté par » sur une facture adressée au client chez lui. |
| M3 | Confirmé | Corrigé en partie : rien n'est écrit si rien ne change (test `testDesConditionsInchangeesNeSEcriventPas`). Pas de « Reprendre mes réglages » (bouton retiré comme superflu par Xavier dans la v3). |
| M4 | Confirmé | Corrigé : 422, l'envoi automatique affiche « Non enregistré ». |
| M5 | Confirmé | Laissé : à signaler à Xavier. |
| M6 | Confirmé | Corrigé (`rattacherClient`), test `testLeRepresentantDevenuClientNeSeRepresentePasLuiMeme`. |
| M7 à M12 | Notés | Laissés : présentation seulement (M7), même logique que les lignes copiées (M8), cas rares (M9, M10), coût (M11), calcul juste (M12). |
