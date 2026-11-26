# REVUE : options et variantes

Revue lourde du 2026-11-22 sur le diff `06e0690..5de3b67` (code seul) : il touche trois surfaces sensibles du profil
(appels au modèle de langue, couche d'accès aux données, droits par route). Trois relecteurs Opus indépendants, en
lecture seule : correction, sécurité (skill `owasp-security`), régression. Rapports complets : scratchpad de la session,
`revue-correction.md`, `revue-securite.md`, `revue-regression.md`. Chaque trouvaille a été relue dans le code avant le
verdict ; les retenues ont un test rouge avant la correction (`tests/Offre/RevueOptionsTest.php`,
`tests/Http/RevueGestesTest.php`), sauf les deux du script, vérifiées au clic.

| Trouvaille | Lentille | Gravité | Verdict | Suite |
|---|---|---|---|---|
| S1, R3 : trois appels au modèle, 600 + 180 + 180 = 960 s pour un verrou de traitement de 900 s ; une dictée lente se rejouerait, payée plusieurs fois | sécurité, régression | majeur | retenu | troisième appel borné à 90 s (870 s en tout) ; test de la somme des durées |
| C1 : la fenêtre d'une variante dont la ligne est supprimée l'enregistrait comme variante d'une autre ligne, décochée ; « Annuler » ne la remettait plus | correction | majeur | retenu | la fenêtre montre la nature effective (option) ; corriger ne change la nature que sur un vrai changement |
| C2 : une variante libre au forfait (« 800 francs le tout ») prenait la quantité de sa ligne : 33'600.- au lieu de 800.- | correction | majeur | retenu | quantité de la ligne seulement à unité égale, jamais au forfait |
| R1, S4 : une ligne que le troisième appel classe à tort en option sort du total sans passer « à vérifier » | régression, sécurité | majeur | retenu | toute ligne sortie du total par le modèle passe à vérifier : « Relire » la montre |
| R2 : la consigne principale change pour toutes les offres, mesurée sur des dictées écrites seulement ; le commentaire disait le contraire | régression | majeur | retenu en partie | commentaire corrigé ; la mesure sur de vraies dictées (fiches 21 à 28) est une condition de la mise en service, voir RECETTE |
| C3 : deux variantes d'une ligne supprimée, lues comme deux options, se décochaient l'une l'autre | correction | mineur | retenu | `retenir` ne décoche les sœurs que si la ligne de base est une ligne du total |
| C4 : une option retenue qui a une pièce faisait passer le document par pièce, l'écran restant par catégorie | correction | mineur | retenu | par pièce ou par catégorie : décidé par les lignes du total seules |
| C6 : une variante du catalogue d'une autre unité prenait la quantité de sa ligne (42 m² devenaient 42 rouleaux) | correction | mineur | retenu | quantité de la ligne à unité égale seulement |
| C7 : la ligne à remplacer disparue entre l'affichage et l'envoi, la variante se rattachait en silence à une autre | correction | mineur | retenu | elle entre en option (O3) |
| C8 : après la case « Retenue », le focus allait au champ caché du même nom | correction | mineur | retenu | sélecteur sans les champs cachés ; vérifié au clic |
| C9 : une case refusée par le serveur (409) restait cochée à l'écran | correction | mineur | retenu | la case reprend son état ; vérifié au clic (409, décochée, rien en base) |
| R6 : chaque enregistrement de la fenêtre d'une ligne écrivait la nature sans changement | régression | mineur | retenu | réglé par la correction de C1 |
| S3 : dossier des rapports du banc créé en 0777, à côté du corpus | sécurité | mineur | retenu | 0700 |
| S2 : `changerNature` lit sans verrou ; deux requêtes simultanées peuvent rendre deux lignes variantes l'une de l'autre | sécurité | mineur | écarté | l'offre de l'artisan seulement ; la lecture les traite en options (O3), aucun montant faux |
| R5 : `Options::masque` suppose une liste | régression | mineur | écarté | tous les appelants passent une liste ; l'analyse statique (niveau 8) le tient |
| C5 : un rabais en francs plus grand que le total sans options compte moins que dans le total avec les options | correction | mineur | hors périmètre | le plafond du rabais au sous-total est la règle d'avant ce chantier ; question posée à Xavier |
| R4 : une base sans la migration fait tomber les écrans des lignes ; un retour arrière compterait options et variantes dans le total | régression | mineur | hors périmètre | migration additive jouée avant le code par `deployer-test.sh` ; contrôle de compatibilité (`DEPLOIEMENT.md`) à faire au déploiement, voir RECETTE |

Vérifié sans trouvaille par les relecteurs : isolation entre artisans (toutes les écritures filtrent par artisan et par
offre, un `remplace_id` étranger se lit comme une option), CSRF (contrôle global du noyau), SQL paramétré, échappement
Twig, paramètre `retour` comparé à une valeur fixe, sortie du modèle bornée, motif `MOTS_OPTIONS` sans retour arrière
explosif ; totaux, regroupement, relecture et facture identiques pour une offre sans option.
