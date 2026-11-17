---
chantier: De la voix à l'offre, 27 enregistrement à l'abri
slug: 2026-11-07_voix-offre/27-enregistrement-abri
phase: 1
statut: en_cours
gate_attendu: aucun
profil: .claude/atoz-profile.md
---

## Prochaine action

Écrire `SPEC.md` à partir de la décision ci-dessous, puis la maquette du bandeau de reprise (phase 3).

## En attente de toi

Rien pour l'instant.

## Suivi Origin

- Projet 44 offreGen, tâche 428 « Enregistrement à l'abri dans le téléphone (sous-chantier 27) », 6 h.
- Sous-tâches : 429 maquette et décisions, 430 sauvegarde pendant l'enregistrement, 431 suppression et reprise, 432 essai sur iPhone, 433 revue et recette.

## Décisions

- 2026-11-17, Xavier : « le téléphone sauvegarde l'enregistrement jusqu'à qu'il soit envoyé et qu'il ait reçu une réponse du serveur […] Une fois que c'est traité par le serveur et que l'offre a été traitée, on peut considérer que l'enregistrement peut être supprimé. » « oui tu peux créer la tâche ».

## Journal

- 2026-11-17 : constat dans `public/js/app.js` (`nouvelleOffre`) : l'enregistrement n'existe que dans la mémoire de la page (`morceaux`, une tranche par seconde) jusqu'à l'envoi par `POST /import`. Après l'envoi, la page suit `/enregistrements/<uuid>/etat` et reçoit `offre` quand l'offre est prête : c'est le signal de suppression. La fenêtre peut se fermer après l'envoi ; le suivi doit donc reprendre à la réouverture.
- Proposition de Claude, à mettre en spec : chaque tranche rangée dans le stockage du navigateur pendant l'enregistrement ; numéro de l'enregistrement noté après l'envoi ; suppression quand l'état rend `offre` ; à la réouverture, un bandeau pour ce qui n'est pas parti (« Envoyer », « Garder dans un fichier », « Supprimer »), visible du seul compte qui l'a enregistré.
- Risques à lever : lisibilité d'un enregistrement coupé au milieu (format de Safari surtout) ; Safari peut effacer ce stockage après 7 jours sans visite ; audio d'une visite en clair dans le navigateur, à écrire dans `docs/SECURITE.md`.

## Preuves

Aucune encore.
