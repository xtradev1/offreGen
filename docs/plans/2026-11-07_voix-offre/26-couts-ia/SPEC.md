# Sous-chantier 26 : coût de l'IA, statistiques d'exploitation

Demande de Xavier du 2026-11-17 : « c'est important pour les statistiques que j'aie le coût global des requêtes API »,
« juste pour moi, comme statistiques d'exploitation ».

## Ce qu'on sait (vérifié le 2026-11-17)

- **Qwen (extraction)** : chaque réponse rend ses jetons (`usage`). Le coût facturé par Infomaniak égale exactement
  jetons × tarif publié (16.11 : 1208 et 192 jetons, CHF 0.0010592 des deux côtés ; banc du 17.11, idem).
- **Whisper (transcription)** : la réponse ne rend ni durée ni coût. Infomaniak facture le **temps de calcul** du
  serveur (Xavier), pas la durée de l'audio : la dictée du 16.11, 19,2 s d'audio, compte 1 s, CHF 0.0001. Le coût d'une
  transcription ne se calcule donc pas chez nous ; il ne se connaît que par le relevé d'Infomaniak.
- **Relevé d'Infomaniak** : `GET /1/ai/{produit}/consumptions` rend, **par jour et par modèle**, le nombre de requêtes,
  le coût facturé, les jetons (modèle de langage) ou le temps de calcul (Whisper). Rien par requête. Tout le produit
  357 y est mêlé : développement, test, bancs, anciens usages.

## Ce que l'outil fait

1. **Chaque extraction note son coût exact** : modèle, jetons entrants et sortants, coût au tarif du jour, sur
   l'enregistrement qui l'a demandée. Plus la durée de l'audio, mesurée par nous (utile pour estimer, pas pour payer).
2. **Un relevé quotidien de la consommation Infomaniak** (tâche planifiée, comme `traiter-file`) : les jours récents
   réécrits à chaque passage, au cas où Infomaniak corrigerait un chiffre.
3. **Un écran « Coût de l'IA »**, pour l'exploitant seulement :
   - le mois : offres créées, coût facturé par Infomaniak, coût moyen par offre, projection à 50 offres ;
   - par modèle (relevé Infomaniak) : requêtes, jetons ou temps de calcul, coût facturé ;
   - par artisan (nos chiffres) : offres, minutes d'audio, jetons, coût d'extraction, coût par offre ;
   - l'écart entre nos chiffres et la facture, et pourquoi (Whisper, essais, autres usages du produit).

## Hors périmètre

- Aucun écran pour l'artisan (décision de Xavier).
- Ni facturation aux artisans, ni plafond de dépense (le plafond quotidien d'enregistrements existe déjà).
- Séparer la production du test chez Infomaniak : un second produit IA, à créer par Xavier dans son Manager.

## Décisions

- **F1, accès (Xavier, 2026-11-17)** : « accessible avec des accès admin, pour que si un jour j'ai des collaborateurs
  ils y aient aussi accès, enfin si je le décide ». Un droit administrateur sur le compte, refusé par défaut ; l'écran
  répond 404 à tout autre compte et n'apparaît dans aucun de ses menus. Le droit se donne et se retire par une commande
  (`bin/droit-admin <courriel> oui|non`), lancée par Xavier ; un écran pour le donner viendra avec le premier
  collaborateur.
- **F3, relevé (Claude)** : chaque nuit, les 7 derniers jours réécrits ; deux passages le même jour ne doublent rien.
- **F4, tarifs (Claude)** : dans le code, datés (un changement de tarif = un commit) ; le relevé d'Infomaniak reste la
  vérité, l'écart se voit.
- **F2, à trancher sur la maquette** : le coût de Whisper par artisan (réparti au prorata des minutes d'audio du jour)
  ou au total seulement.

## Critères de succès

- Une extraction faite ce jour apparaît avec ses jetons et un coût égal au relevé d'Infomaniak du même jour, au
  dix-millième de franc près, quand elle est seule ce jour-là.
- Un compte sans droit administrateur qui demande l'écran reçoit 404 ; la page n'apparaît dans aucun de ses menus.
  Un compte administrateur la voit dans « Plus » (téléphone) et dans le menu (PC).
- Le relevé passé deux fois le même jour ne double rien.
