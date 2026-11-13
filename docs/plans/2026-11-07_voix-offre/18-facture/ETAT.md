---
chantier: De la voix à l'offre, 18 facture
slug: 2026-11-07_voix-offre/18-facture
phase: 3
statut: gate_ouvert
gate_attendu: visuel
profil: .claude/atoz-profile.md
---

## Prochaine action

Xavier répond dans `MAQUETTE-facture.html` et colle « Copier le texte ». Ensuite : plan, puis code.

## En attente de toi

- Répondre aux quatre choix de la maquette (acomptes, facture émise, où sont les factures, forme du numéro) et valider la facture et les points réglés.
- Scanner le code QR de la maquette avec l'application de ta banque, sans payer : elle doit afficher Peinture Morel Sàrl et CHF 3 106.80.
- Installer l'extension bcmath dans WSL : `sudo apt install php8.5-bcmath` (Claude ne manipule pas ton mot de passe).
- Vérifier chez Infomaniak que bcmath est active sur l'hébergement visé (Claude n'a pas accès à la production).

## Suivi Origin

- Projet 44 offreGen, tâche 400 « De la voix à l'offre, suite : clients et facture » (la tâche 315 ne prend pas de sous-tâche de plus par l'outil).
- Sous-tâches : 401 Clients (sous-chantier 17), 402 Facture (ce sous-chantier). Conversation rattachée à 402 le 2026-11-13.

## Décisions

- 2026-11-12 : décisions de Xavier à la maquette 14 : « Facturer » depuis l'offre (« avec la possibilité de mettre soi-même le numéro ») ; QR-facture validée ; même modèle et même couleur que l'offre ; facture plus longue qu'une page : section de paiement sur une page à part.
- 2026-11-13 : bibliothèque retenue pour la QR-facture : sprain/swiss-qr-bill 5.3.1 (seule version qui accepte PHP 8.5 et Symfony 7), pour la charge utile et sa validation. Elle exige l'extension bcmath. Le dessin du code ne passe pas par elle : son écrivain SVG appelle GD pour la croix suisse (`imagecreatefromstring`), et GD manque. Le code est dessiné par bacon/bacon-qr-code (déjà dans ses dépendances, sans GD), niveau de correction M, croix suisse ajoutée en SVG.
- 2026-11-13 : dompdf lit le `viewBox` d'un SVG en pixels CSS et ignore la taille demandée en mm : le code se dessine sur 174 unités (46 mm à 96 dpi). Avec `viewBox="0 0 46 46"`, il sortait à 12 mm, illisible.
- 2026-11-13 : proposé à Xavier comme réglé : IBAN dans « Mon entreprise » ; référence RF bâtie sur le numéro (IBAN ordinaire) ou référence QR (QR-IBAN) ; client copié sur la facture le jour de sa création ; la facture garde le lien avec son offre.

## Journal

- 2026-11-13 : sous-chantier ouvert (Xavier : « oui vas-y »). Relu ce que la maquette 14 avait validé pour la facture.
- 2026-11-13 : essai technique hors dépôt (`~/essai-qr`) avant la maquette, pour lever le risque de la QR-facture :
  - sprain/swiss-qr-bill 4.10 s'installe, mais ne connaît que Symfony 6 ; la 5.3.1 accepte PHP 8.5 et Symfony 7 et exige bcmath ;
  - la référence RF appelle `bcmod` même en 4.10 : bcmath est nécessaire dans tous les cas ;
  - charge utile sans référence : aucune violation ;
  - premier rendu dompdf : code de 12 mm, non lu ; après passage en pixels : 46,1 mm, relu par OpenCV, accents intacts.
- 2026-11-13 : maquette écrite : parcours en trois écrans, facture A4 avec la vraie section de paiement (code de l'essai, IBAN d'exemple de la norme), quatre choix, points réglés. Défauts trouvés sur les captures et corrigés avant de la montrer : montants faux (ligne Déplacement manquante, TVA et arrondi recalculés), facture rangée dans la colonne des téléphones et recouverte par la section de paiement (tailles passées à l'échelle de la page).

## Preuves

Essai QR (`scratchpad/qr-essai2.php`, `scratchpad/decoder-qr.py`) :

```
violations : 0
version : 9, modules : 53
pdf : 12385 octets
taille : 46.1 mm
décodé : oui
SPC / 0200 / 1 / CH9300762011623852957 / K / Peinture Morel Sàrl / Rue de l'Exemple 12 / 2800 Delémont / CH / 3106.80 / CHF / NON / Facture n° 2026-001 / EPD
```

Maquette : `check.js` bureau 1440/1440 et téléphone 390/390, image 1, cassée 0 ; capture de la facture relue par OpenCV : « décodé : oui », même contenu ; aucune erreur JavaScript.

Non vérifié : lecture du code par une vraie application bancaire (à faire par Xavier) ; présence de bcmath chez Infomaniak.
