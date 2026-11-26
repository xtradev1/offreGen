# Revue : documents et réglages

Un relecteur indépendant (Opus, lecture seule, phpunit interdit), lentille « correction ». Surfaces sensibles du profil touchées : aucune (pas de droit, de connexion, d'accès aux données d'un autre client, d'appel au modèle, de courriel) ; revue lourde non lancée.

## Trouvailles

| N° | Gravité | Constat (relu dans le code avant correction) | Verdict | Correction | Test |
|---|---|---|---|---|---|
| 1 | mineur | Variante d'une ligne en « rouleau », ligne libre détaillée avec « Garder aussi dans le catalogue » : 422 « Choisissez une unité », `SaisieProduit::lire` ne recevait pas l'unité de la ligne remplacée. | retenu | `SaisieProduit::lire($champs…, $base['unite'] ?? null)` | `testGarderAuCatalogueAvecLUniteDeLaLigne` |
| 2 | mineur | Après un refus (422), la quantité tapée en haut revenait à celle de la ligne (lue seulement dans l'adresse). | retenu | `pageAjoutAvec` relit `reprise` du formulaire envoyé | `testUnRefusGardeLaReprise` |
| 3 | mineur | Quantité tapée illisible (« abc », « 0 ») : le produit partait avec celle de la ligne, l'écran affichait « abc m², à relire ». | retenu | `app.js` montre et envoie celle de la ligne (`data-defaut`) quand la saisie ne se lit pas ; le serveur fait de même | parcours Playwright : « 42 m², à relire », caché « 42 » |
| 4 | mineur | La quantité propre d'une ligne libre détaillée (5) devenait celle des ajouts suivants. | retenu | la quantité de la variante voyage dans un champ à part, `reprise` ; la redirection ne reprend qu'elle | `testLaQuantiteDeLaLigneLibreNeRemplacePasLaReprise` |
| 5 | mineur | « OK » de la quantité (sans JavaScript) perdait la recherche en cours. | retenu | champ caché `q` | `testLaQuantiteGardeLaRecherche` |
| 6 | mineur | Ligne remplacée disparue entre l'affichage et l'envoi d'une ligne tapée : option à 42 m² au lieu de 1. | retenu en partie | quantité 1 quand la variante retombe en option ; l'unité envoyée reste celle de l'ancienne ligne (hors liste : refus 422) | `testLigneTapeeSansLigneRemplaceeDevientUneOptionDeQuantiteUn` |

Les cinq tests ajoutés ont été écrits après la correction ; prouvés rouges en remettant l'ancien code dans la copie (`Tests: 5, Assertions: 11, Failures: 5`), puis fichiers restaurés.

## Écarté par le relecteur après lecture

Variante sans ligne de base (repli en option) ; facture (même contrôleur, pas de variante) ; file hors réseau ancienne sans quantité (valeurs par défaut) ; écouteurs JS posés une fois sur le document, feuille dans le DOM ; `marquerAVerifier` après `poserNature`, dans la portée de l'artisan ; filigrane absent du JS et du CSS ; route `/reglages/phrases` générée par `RUBRIQUES`, 422 sans JavaScript revient à la rubrique, `sauver()` n'écrit que les champs reçus.

## Laissé, à la connaissance de Xavier

- Le résumé de la rubrique compte les 14 phrases réécrites, pas la formule d'appel, le début ni la fin. Au choix de Xavier.
- `LigneControleur::modifier` (fenêtre d'une ligne) a le même trou que le n° 1 quand on garde au catalogue une ligne d'unité hors liste : d'avant le chantier, non touché.
- N° 6 : une ligne tapée dont la ligne remplacée a disparu, d'unité hors liste, est refusée (422) au lieu d'entrer en option. Course rare (deux appareils), le message le dit.
