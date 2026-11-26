# REVUE : onboarding du commercial et de l'artisan

2026-11-22, phase 7. Revue lourde : le diff touche droits, session, SQL et routes d'administration (surfaces sensibles du profil). Quatre relecteurs `route-heavy` indépendants, en lecture seule, sans phpunit : correction, sécurité, régression, OWASP. Fable écarté : le CLAUDE.md global le réserve à une demande de Xavier et l'emporte sur le profil. Relecture XtraDev des textes écrits pendant le code. Chaque trouvaille retenue a été vérifiée dans le code avant d'être corrigée ; les verdicts sont de Claude. Détail des relecteurs : `revue-correction.md`, `revue-securite.md`, `revue-regression.md`, `revue-owasp.md` (scratchpad de la session, non versés).

| Trouvaille | Lentille | Gravité | Verdict | Suite |
|---|---|---|---|---|
| La marque « guide en cours » restait en session : sans ouvrir la démo d'entraînement sur le même appareil, la démo suivante, pour un vrai prospect, devenait une démo d'entraînement (sans « amené par », hors du suivi, courriel du membre) | correction | haute | retenu | marque à usage unique `ENTRAINEMENT_A_CREER`, consommée à la création ; test `testLeChampEntrainementPosteSeulNeFaitPasUneDemoDEntrainement` |
| Le champ `entrainement=1` était accepté dans tout `POST /admin/demos` : 10 + 3 démos par jour, hors du suivi | sécurité, OWASP | moyenne | retenu | même correction : le champ seul ne suffit plus |
| Le retour dans l'espace du membre n'avait pas de durée : un téléphone tendu à un prospect lui ouvrait l'espace du commercial | sécurité, correction | moyenne | retenu | marque valable deux heures (`RetourEquipe::DUREE`) ; test `testLeRetourExpireApresDeuxHeures` |
| Un GET du lien consommait le jeton, écrivait et changeait de session (contre `CODING_RULES.md`) | sécurité, OWASP, régression | moyenne par la règle | retenu | ouverture de la démo d'entraînement par un formulaire POST avec jeton sur la page du code ; le GET montre la page d'entrée |
| Après la fin d'une démo, l'import du catalogue (IA) et la relance d'une dictée en échec restaient ouverts | sécurité, OWASP, correction | moyenne à basse | retenu | 410 sur `POST /import-catalogue` et `/import-catalogue/{id}/offres`, renvoi à l'écran de fin pour `reessayer` ; test |
| Ruban (« 2 jours restants ») et fenêtre (« se termine demain ») se contredisaient : heures contre jours de calendrier | correction | moyenne | retenu | ruban et suivi au calendrier de Zurich, comme la fenêtre ; « dernier jour » le jour même |
| « Revoir le guide » sans entrée dans l'application | correction, régression | moyenne | retenu | ligne « Revoir le guide » dans « Plus » pour une démo ou un essai |
| Impression de l'aide à la vente : une seule rubrique ouverte (l'exclusivité refermait les autres) | correction | basse, supposée | retenu | exclusivité suspendue pendant l'impression |
| « Quitter le guide » dans la démo d'entraînement laissait la carte de fin au retour | correction | basse | retenu | `/guide/fini` avec « quitte » efface aussi le guide de l'équipe |
| Touche D du PC pendant les conseils : la dictée part sans « Commencer la dictée » | régression | basse, supposée | retenu | la touche est arrêtée tant que les conseils sont ouverts |
| `demo-fin.js` comptait le jour en UTC | correction | basse | retenu | jour de l'appareil |
| Gestes `demo_entrainement`, `retour_entrainement` sans libellé sur la fiche d'un artisan | régression | basse | retenu | libellés ajoutés, plus `entree_entrainement` |
| Aucune trace de l'entrée d'un membre dans sa démo d'entraînement | OWASP | basse | retenu | geste `entree_entrainement` au journal |
| Le test des textes ne lisait ni les ternaires ni le PHP : une faute de frappe donnerait une page en erreur | correction | basse | retenu | le test lit tout identifiant dans un appel à `texte()`, gabarits et `src/` |
| Les chiffres du suivi de l'administrateur ne comptent que ses propres apports | correction | moyenne | écarté | le libellé validé est « Amenés par vous » ; l'administrateur voit toutes les démos ouvertes, ses chiffres restent les siens |
| L'assistant finit sur « Vos prix » pour tout compte qui a des produits à 0, pas seulement après une démo | régression | moyenne | écarté | voulu : un essai neuf reçoit les douze produits de son métier à 0, « Vos prix » est fait pour eux |
| `bin/passer-en-essai` rouvre aussi l'assistant | régression | basse | écarté | voulu, comme pour le passage par l'écran |
| `/bienvenue/conditions` disparaît : un artisan sur cette étape au déploiement reçoit 404 | régression | basse | écarté | fenêtre de quelques secondes, l'étape se refait depuis les réglages |
| Le courriel du membre est prérempli pour qui tient le lien de sa démo d'entraînement | sécurité | basse | écarté | le lien n'est montré qu'au membre, il vaut une heure et une fois |
| Compteurs de limites lus hors transaction : des POST simultanés dépassent la limite | OWASP, sécurité | basse | hors périmètre | défaut déjà présent pour la limite de dix (`Demos::creer`) ; à reprendre avec le chantier de l'administration |
| Le guide s'arrête sans rien dire après 30 secondes sans cible (micro refusé, dictée trop courte) | correction | basse | écarté | « Revoir le guide » le relance ; un message de plus n'a pas été maquetté |
| « Vos prix », comptée comme cinquième étape, n'a ni progression ni « Passer le guide » | correction | basse | hors périmètre | écran existant d'un autre chantier |
| Écarts au contrat technique : `/admin/bienvenue/vue` au lieu de `/vu`, pas de `/admin/demos/entrainement` (champ du formulaire sous marque de session), 303 au lieu de 404 pour `/fin-de-demo` avant la fin | correction | basse | écarté | décisions de code, notées dans `ETAT.md` |

## Relecture XtraDev des textes écrits pendant le code

« À corriger » au tour 1. Appliqué : « Continuer la démo » ; « Réessayez dans 24 heures » ; refus « Pour dicter ou importer de nouveau » ; « l'offre y apparaît » ; « Changer le logo » ; g.fin.texte réécrit ; le message « Votre compte est prêt » seulement sur « Offres », plus sur « Vos prix ». Écartés : « Compris » à l'infinitif (validé par Xavier, O3 et O14) ; « votre marque » dans e.fin.texte (texte validé). Au choix de Xavier : « Suivant », « Plus tard » ; une demi-phrase sur l'essai sans engagement. g.rond, g.fin.texte et c.b1.p2, changés après validation, sont à lui montrer.

## Preuve après corrections

```
$ php vendor/bin/phpstan analyse
[OK] No errors
$ php vendor/bin/phpunit
OK (1114 tests, 9826 assertions)
$ node lot8.js ; lot2.js ; lot3.js ; lot4.js (navigateur, base de recette locale)
guide de l'équipe 1 à 10, carte de fin, démo entrainement=1 apporte_par=NULL ; fenêtre « demain », micro refusé ; guide de l'artisan, Compris 204 ; mise en route jusqu'à /vos-prix ; erreurs [] partout
```
