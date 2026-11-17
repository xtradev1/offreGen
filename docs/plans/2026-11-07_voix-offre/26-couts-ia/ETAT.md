---
chantier: De la voix à l'offre, 26 coût de l'IA
slug: 2026-11-07_voix-offre/26-couts-ia
phase: 3
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Attendre les réponses de Xavier à `MAQUETTE-couts.html` (F2 et l'écran), puis `PLAN.md` et implémentation.

## En attente de toi

- Ouvrir `MAQUETTE-couts.html`, répondre sous F2 et sous l'écran, puis « Copier le texte » et le coller dans la conversation.

## Suivi Origin

- Projet 44 offreGen, tâche 422 « Coût de l'IA : statistiques d'exploitation (sous-chantier 26) ».
- Sous-tâches : 423 spec et maquette, 424 jetons et coût de chaque extraction, 425 relevé quotidien Infomaniak, 426 écran de l'exploitant, 427 revue et recette.
- Conversation rattachée à 423 le 2026-11-17.

## Décisions

- 2026-11-17, Xavier : « c'est important pour les statistiques que j'aie le coût global des requêtes API » ; « juste pour moi, comme statistiques d'exploitation » (aucun écran pour l'artisan).
- 2026-11-17, Xavier : Infomaniak facture Whisper au temps de calcul du serveur, pas à la durée de l'audio.
- 2026-11-17, Xavier (F1) : « accessible avec des accès admin, pour que si un jour j'ai des collaborateurs ils y aient aussi accès, enfin si je le décide ». Droit administrateur sur le compte, donné et retiré par commande.
- 2026-11-17, Claude : F3 relevé chaque nuit, 7 jours réécrits ; F4 tarifs dans le code, datés. Restent pour Xavier : F2 et l'écran.
- 2026-11-17 : phase 1 (spec) et phase 3 (maquette) présentées ensemble, une seule attente : le chantier est petit et les décisions ouvertes tiennent dans la maquette.

## Journal

- 2026-11-17 : relevé `GET /1/ai/357/consumptions` lu, coût de Qwen vérifié égal à jetons × tarif ; projection donnée à Xavier (pire offre observée CHF 0.0020 avec le 27B, 50 offres par mois au prix × 10 : CHF 1.00). Il n'existe que deux droits d'accès (public, artisan) : la manière de réserver l'écran à l'exploitant est la décision F1.

## Preuves

```
$ php conso.php : GET /1/ai/357/consumptions HTTP 200 ; 16.11 Qwen3.8-27B 1208 / 192 jetons, cost 0.0010592 ; whisper 00:00:01, 0.0001
$ calcul : 1208 × 0.40 / 1e6 + 192 × 3.00 / 1e6 = 0.0010592
```
