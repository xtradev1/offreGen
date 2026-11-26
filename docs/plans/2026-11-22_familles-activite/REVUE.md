# REVUE : activités (bâtiment, extérieur, prestations)

Revue du 2026-11-22, sur le diff non commité de `~/offreGen-familles` (46 fichiers suivis modifiés, 8 fichiers neufs et ce dossier). Revue lourde : le diff touche l'appel au modèle de langue et une écriture sur le compte, deux surfaces sensibles du profil. Trois relecteurs indépendants sur Opus (`route-heavy`), une lentille chacun, sans connaître les conclusions des autres. Leurs rapports complets sont dans le scratchpad de la session (`revue-correction.md`, `revue-securite.md`, `revue-regression.md`) : ils ne sont pas versionnés.

Les verdicts sont ceux de la session principale. Chaque trouvaille retenue a été relue dans le code avant correction.

| Trouvaille | Lentille | Gravité | Verdict | Suite |
|---|---|---|---|---|
| Changer d'activité dans les réglages enregistrait les phrases de Lettre de l'ancienne activité comme tapées : « travaux » restait sur les documents d'un prestataire (`ReglagesControleur::sauver`) | Correction, Sécurité, Régression (les trois) | haute | retenu | Reproduit par deux assertions rouges, puis corrigé : la comparaison se fait avec l'activité d'avant l'envoi (`$activiteAvant`). Test `testLesPhrasesDeLettreSuiventLeChangementDActivite` |
| Le test « rien d'autre ne change » ne lisait pas les colonnes `lettre_*` | Correction | moyenne | retenu | `ActiviteReglageTest::reste()` les lit |
| Aucune facture rendue en Prestations dans les tests | Correction | moyenne | retenu | `testLaFactureDUnePrestationNeParlePasDeTravaux`, sur les douze modèles |
| L'activité des tests de gabarits est posée par SQL, pas par le réglage | Correction | moyenne | écarté | Le réglage a ses propres tests (`ActiviteReglageTest`) ; les tests de gabarits lisent la colonne, comme l'application |
| Le message à envoyer au prospect disait « première visite » pour tout métier (`AdminControleur::demoMessage`) | Correction | basse | retenu | Corrigé par `Activite::duMetier` ; sans test dédié |
| Un libellé du modèle « Chantier » choisi en comparant un autre libellé (`chantier.html.twig`) | Correction | basse | retenu | Mot `doc_adresse` dans `Activite::mots` |
| Le champ « Quel métier ? » s'affichait après le dernier groupe, loin d'« Autre métier » | Régression | basse | retenu | Placé sous le groupe qui porte « Autre métier », assertion ajoutée |
| L'aide de « Ma marque » dit « la date de la visite et l'adresse du chantier » à un prestataire | Correction | basse | hors périmètre | Phrase validée le 2026-11-20 avec les phrases de Lettre ; listée dans la recette, « ce qui n'est pas couvert » |
| L'aperçu de « Ma marque » montre une offre de peintre à un compte sans offre | Correction | basse | hors périmètre | Exemple en dur dans `ReglagesControleur::exemple`, d'avant ce chantier ; à traiter à part |
| Le fichier d'un enregistrement gardé s'appelle `visite.webm` | Correction | basse | hors périmètre | Nom de fichier, listé dans la recette |
| Trois écritures sans transaction dans `ReglagesControleur::sauver` | Sécurité | basse | écarté | Deux existaient déjà ; l'activité s'écrit en dernier, un échec avant elle laisse des réglages valides et l'activité d'avant |
| L'annulation de la migration efface les activités choisies | Sécurité | basse | écarté | Propre à l'annulation d'une colonne : elle ne se joue que sur décision |
| `Conteneur::activiteCourante()` relit le compte à chaque appel, une quinzaine de fois sur « Ma marque » | Régression | basse | écarté | Lecture par clé primaire, aucune boucle ; un cache par conteneur rendrait l'activité fausse après un changement dans la même requête |
| Les comptes paysagistes existants passent en Extérieur et changent de libellé | Régression | basse | retenu, pour information | Voulu par la SPEC ; porté aux préalables de la recette |
| `app.js` servi sans numéro de version : un fichier gardé en cache remettrait « Parlez, pièce par pièce. » à un prestataire | Régression | supposition | non vérifié | À regarder à la recette sur le site de test, après un rechargement forcé |
| Une démo partagée garderait l'activité choisie par un visiteur | Sécurité | supposition | écarté | `Comptes::reinitialiserDemo` remet le métier et son activité |

## Vérifié par les relecteurs, sans trouvaille

- La consigne du Bâtiment et les deux outils envoyés au modèle sont identiques au commit `8385e78`.
- `changerActivite` reçoit l'identifiant de la session ; aucune route d'administration n'écrit l'activité d'un autre compte ; le champ est borné à la liste fermée avant toute écriture.
- Aucun texte saisi n'entre dans la consigne ni dans la description de l'outil. `Activite::dire` n'est jamais appliquée à un texte tapé.
- Aucune route nouvelle (`config/routes.php` absent du diff). Aucun `|raw` ajouté ; `app.js` n'écrit que du texte.
- Les sept chemins de rendu d'un document, PDF en deux passes compris, passent par `templates/_document.html.twig`.
- Tous les appelants des signatures changées sont à jour ; le déploiement joue la migration avant la bascule du code.

## Compteur de délégation

Trois agents sur Opus : hors compteur (`model-routing`, section 6). Leurs trouvailles retenues ont été vérifiées dans le code ; aucune ne s'est révélée fausse.
