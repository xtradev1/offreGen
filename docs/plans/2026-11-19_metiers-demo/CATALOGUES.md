# Catalogues de démonstration, lot 1

Sept catalogues à écrire ou à refaire : plâtrier, carreleur, maçon, poseur de sols, paysagiste, menuisier, général. Peintre et sanitaire restent tels quels (`src/Catalogue/CatalogueFictif.php`). Le catalogue de l'électricien, retiré du lot 1, est gardé plus bas pour le lot 2.

## Comment lire les prix

- Les prix sont inventés. Chacun est placé dans une fourchette publiée en Suisse quand il en existe une (colonne « Repère », sources en bas de page).
- « Estimation » veut dire : aucune fourchette suisse trouvée, prix posé par Claude. Ces lignes sont les premières à faire corriger par un artisan du métier.
- Les fourchettes viennent de guides pour particuliers (Houzy, MagicHeidi, Beobachter), de listes de prix (Hornbach, SIG) et d'un canton (Valais). Aucun devis d'artisan détaillé n'a été trouvé, comme le 2026-11-06.
- **Pages ouvertes le 2026-11-19 (lot 0 du plan) :** 14 pages lues, citations dans `ETAT.md`. Sept prix corrigés après lecture : lissage des murs, lissage des plafonds, crépi de finition, ouverture de porte, moquette, taille de haie, déchets verts. Une source ne porte aucun montant (S10) : son repère est retiré.
- Les guides visent toute la Suisse, Zurich compris. Les prix du Jura peuvent être plus bas.
- Chaque catalogue a 12 produits et 2 offres d'exemple, nombres imposés par `tests/Compte/DemoTest.php`.
- Unités admises : pce, m², m, m³, h, forfait. Prix en francs entiers (le code multiplie par 100).

## Plâtrier (nouveau)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Préparation | Protection des sols et des accès | forfait | 180 | bâcher, protéger | même prix que le peintre |
| Cloisons | Cloison en plaques de plâtre, posée | m² | 95 | cloison, placo, alba | 40 à 100 (S1) |
| Cloisons | Doublage isolant collé | m² | 75 | doublage | 40 à 100 (S1) |
| Plafonds | Faux plafond en plaques de plâtre | m² | 110 | faux plafond, plafond suspendu | estimation, au-dessus de la cloison |
| Enduits | Lissage des murs, 2 passes | m² | 40 | lisser, ratisser, glacis | 40 à 120 (S1), 25 à 40 (S2) |
| Enduits | Lissage des plafonds, 2 passes | m² | 45 | plafond, lisser | 40 à 120 (S1) |
| Enduits | Crépi de finition intérieur | m² | 40 | crépi, ribé | 40 à 120 (S1), 25 à 40 (S2) |
| Enduits | Rhabillage de saignées | m | 18 | rhabiller, saignée, reboucher | estimation |
| Finitions | Baguette d'angle | m | 12 | angle, cornière | estimation |
| Divers | Heure de régie | h | 88 | régie, heure | 70 à 120 (S1) |
| Divers | Déplacement | forfait | 60 | déplacement | comme les autres métiers |
| Divers | Évacuation des déchets | forfait | 160 | déchets, benne | comme les autres métiers |

Offres d'exemple :
- « Cloison et plafond du bureau », Famille Exemple, il y a 2 jours : protection 1, cloison 14, faux plafond 12, lissage des murs 28, baguette d'angle 6, évacuation 1.
- « Lissage du séjour », Monsieur Modèle, il y a 6 jours : lissage des murs 52, lissage des plafonds 26, rhabillage 8, déplacement 1.

## Carreleur (nouveau)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Démontage | Dépose de l'ancien carrelage | m² | 35 | dépose, piquer, démolir | estimation |
| Préparation | Primaire d'accrochage | m² | 10 | primaire, fond | 8 à 12 (S3) |
| Préparation | Ragréage du sol | m² | 28 | ragréage, égaliser | estimation |
| Préparation | Étanchéité sous carrelage | m² | 50 | étanchéité, natte | 40 à 60 (S3) |
| Pose | Carrelage de sol, pose seule | m² | 85 | carrelage, sol, catelles | 70 à 100 (S3) |
| Pose | Faïence murale, pose seule | m² | 80 | faïence, murs, catelles | 50 à 100 (S3) |
| Pose | Supplément grand format | m² | 25 | grand format | estimation |
| Fournitures | Carrelage grès cérame, fourniture | m² | 55 | carreaux, fourniture | 30 à 150 (S3) |
| Finitions | Plinthes carrelées | m | 28 | plinthes | 20 à 50 (S3) |
| Finitions | Joints silicone | m | 14 | silicone, joints | estimation |
| Divers | Heure de régie | h | 88 | régie, heure | 75 à 100 (S3) |
| Divers | Déplacement | forfait | 60 | déplacement | comme les autres métiers |

Offres d'exemple :
- « Salle de bains, sol et murs », Famille Exemple, il y a 2 jours : dépose 6, primaire 24, étanchéité 9, carrelage de sol 6, faïence 18, joints silicone 12, déplacement 1.
- « Carrelage de la cuisine », Madame Essai, il y a 6 jours : ragréage 14, carrelage de sol 14, fourniture 15, plinthes 16, déplacement 1.

## Maçon (nouveau, remplace l'électricien dans le lot 1)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Démolition | Démolition de cloison non porteuse | m² | 45 | démolir, abattre, cloison | estimation |
| Démolition | Ouverture de porte dans un mur porteur, linteau compris | pce | 5500 | ouverture, percement, linteau | estimation ; S2 donne 7 500 à 15 000 pour une ouverture de 5 m², et davantage dans un mur porteur |
| Maçonnerie | Cloison en briques, montée | m² | 95 | cloison, briques, galandage | estimation ; S2 donne 20 à 25 pour le matériel d'une cloison légère, enduit en plus |
| Maçonnerie | Mur porteur en maçonnerie | m³ | 580 | mur porteur, plots | 550 à 600 (S2), mur brut jusqu'à 30 cm |
| Maçonnerie | Mur de jardin en béton | m² | 500 | mur de jardin, muret | environ 500 (S2) |
| Maçonnerie | Mur en pierre naturelle | m² | 230 | pierre naturelle, mur en pierre | 200 à 250 (S2) |
| Béton | Chape ciment | m² | 48 | chape | estimation |
| Béton | Béton coulé, coffrage compris | m³ | 420 | béton, coffrage, dalle | estimation |
| Enduits | Crépi de mur | m² | 32 | crépi, enduit | 25 à 40 (S2) |
| Divers | Heure de maçon | h | 85 | heure, régie | 70 à 90 (S2) |
| Divers | Benne à gravats, pose et enlèvement | pce | 480 | benne, gravats | estimation |
| Divers | Déplacement | forfait | 60 | déplacement | comme les autres métiers |

Offres d'exemple :
- « Ouverture entre cuisine et séjour », Famille Exemple, il y a 2 jours : démolition de cloison 9, ouverture de porte 1, crépi de mur 12, benne à gravats 1, déplacement 1.
- « Mur de jardin et chape du garage », Monsieur Modèle, il y a 6 jours : mur de jardin en béton 8, chape ciment 22, heure de maçon 6, déplacement 1.

## Électricien (lot 2, retiré du lot 1 le 2026-11-19)

Retiré sur le doute de Xavier (« trop compliqué à mettre en place ») et l'avis de Claude : un catalogue réel de milliers d'articles, 7 prix sur 12 sans repère, vocabulaire risqué à la dictée. Gardé ici pour le jour où un électricien le demande.

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Appareillage | Prise triple, fournie et posée | pce | 95 | prise, T13 | pose seule 30 à 50 (S4), matériel et câblage estimés |
| Appareillage | Interrupteur, fourni et posé | pce | 85 | interrupteur | pose seule 50 à 75 (S4) |
| Éclairage | Point lumineux au plafond | pce | 140 | point lumineux, lampe, plafonnier | estimation |
| Éclairage | Spot LED encastré, fourni et posé | pce | 85 | spot, encastré | estimation |
| Câblage | Tirage de câble sous tube | m | 14 | câble, tirer, fil | estimation |
| Câblage | Saignée et rhabillage | m | 45 | saignée | estimation |
| Tableau | Disjoncteur différentiel, fourni et posé | pce | 220 | différentiel, FI, disjoncteur | estimation |
| Tableau | Remplacement du tableau d'appartement | pce | 1900 | tableau, coffret | estimation (Allemagne : 800 à 2 500 euros) |
| Mobilité | Borne de recharge 11 kW, fournie et posée | pce | 2400 | borne, wallbox | 2 380 installée (S5) |
| Divers | Contrôle de l'installation et rapport de sécurité | forfait | 280 | contrôle, OIBT, rapport | estimation |
| Divers | Heure de monteur | h | 105 | heure, monteur, régie | 90 à 135 (S4) |
| Divers | Déplacement | forfait | 70 | déplacement | même prix que le sanitaire |

Offres d'exemple :
- « Cuisine, prises et éclairage », Famille Exemple, il y a 2 jours : prise triple 6, interrupteur 2, spot 8, tirage de câble 35, saignée 12, contrôle 1, déplacement 1.
- « Borne de recharge au garage », Monsieur Modèle, il y a 6 jours : borne 1, disjoncteur différentiel 1, tirage de câble 18, contrôle 1, déplacement 1.

## Poseur de sols (nouveau, reprend les lignes de parquet du menuisier actuel)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Démontage | Dépose de l'ancien revêtement | m² | 15 | dépose, arracher | prix actuel du menuisier |
| Préparation | Ragréage du sol | m² | 28 | ragréage, égaliser | préparation 25 à 35 (S6) |
| Préparation | Sous-couche isolante | m² | 8 | sous-couche | prix actuel du menuisier |
| Parquet | Parquet chêne contrecollé, pose collée | m² | 95 | parquet, chêne | prix actuel ; pose seule 45 (S8) |
| Parquet | Stratifié, pose flottante | m² | 48 | stratifié, laminé | prix actuel ; pose seule 29 à 33 (S8) |
| Parquet | Ponçage et vitrification | m² | 38 | poncer, vitrifier | 30 à 50 (S6) |
| Sols souples | Vinyle clipsé, fourni et posé | m² | 98 | vinyle, PVC | matériel 60 à 100, pose 30 à 40 (S7) |
| Sols souples | Moquette, fournie et posée | m² | 150 | moquette, tapis | matériel 80 à 160, pose 25 à 35 (S6) |
| Finitions | Plinthes, fournies et posées | m | 22 | plinthes | prix actuel ; 10 à 20 (S7), pose seule 11 (S8) |
| Finitions | Barre de seuil | pce | 45 | seuil | prix actuel du menuisier |
| Divers | Évacuation des déchets | forfait | 150 | déchets, benne | prix actuel du menuisier |
| Divers | Déplacement | forfait | 60 | déplacement | prix actuel du menuisier |

Offres d'exemple :
- « Parquet de la chambre », Famille Exemple, il y a 2 jours (offre actuelle du menuisier, déplacée) : dépose 16, sous-couche 16, parquet chêne 16, plinthes 18, barre de seuil 1, évacuation 1.
- « Vinyle du couloir et de la cuisine », Madame Essai, il y a 6 jours : dépose 22, ragréage 22, vinyle 22, plinthes 24, barre de seuil 2, déplacement 1.

## Paysagiste (nouveau)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Entretien | Tonte du gazon, jusqu'à 300 m² | forfait | 90 | tonte, tondre, gazon | 0.25 à 0.40 par m² (S9), soit 75 à 120 pour 300 m² |
| Entretien | Taille de haie, jusqu'à 2 m de haut | m | 15 | haie, tailler | 8 à 22 (S9) |
| Entretien | Taille d'arbuste | pce | 45 | arbuste, tailler | estimation |
| Plantation | Gazon en rouleau, fourni et posé | m² | 30 | gazon, rouleau, pelouse | 20 à 40 (S9) |
| Plantation | Semis de gazon, préparation comprise | m² | 10 | semis, semer | 5 à 15 (S9) |
| Plantation | Arbuste, fourni et planté | pce | 65 | planter, arbuste | estimation |
| Aménagement | Allée en pavés, fournie et posée | m² | 180 | allée, pavés, dallage | 100 à 250 (S9) |
| Aménagement | Surface en gravier, fournie et posée | m² | 85 | gravier | 60 à 120 (S9) |
| Aménagement | Bordure, fournie et posée | m | 48 | bordure | estimation |
| Divers | Heure de jardinier | h | 85 | heure, régie | 80 à 110 (S9), 80 à 100 (S11) |
| Divers | Évacuation des déchets verts | forfait | 80 | déchets verts, déchetterie | 30 à 100 par chargement (S9) |
| Divers | Déplacement | forfait | 60 | déplacement | comme les autres métiers |

La tonte est au forfait : le repère au mètre carré (0.25 à 0.40 franc, S9) demanderait des centimes, que le format actuel du catalogue fictif n'accepte pas.

Offres d'exemple :
- « Entretien d'automne », Famille Exemple, il y a 2 jours : tonte 1, taille de haie 38, taille d'arbuste 6, déchets verts 1, déplacement 1.
- « Allée et gazon », Monsieur Modèle, il y a 6 jours : allée 18, bordure 24, gazon en rouleau 60, heure de jardinier 4, déplacement 1.

## Menuisier (refait)

| Catégorie | Désignation | Unité | Prix | Synonymes | Repère |
|---|---|---|---|---|---|
| Portes | Porte intérieure complète, posée | pce | 850 | porte | prix actuel ; pose seule 149 à 189 (S14) |
| Portes | Ajustage de porte | pce | 70 | raboter, ajuster | prix actuel |
| Portes | Dépose d'une ancienne porte | pce | 60 | dépose, démonter | 60 (S14, liste de 2022) |
| Fenêtres | Fenêtre bois, double vitrage, posée | pce | 1200 | fenêtre | environ 1 200 (S13) |
| Fenêtres | Porte-fenêtre bois, double vitrage, posée | pce | 1800 | porte-fenêtre, baie | environ 1 800 (S13) |
| Fenêtres | Dépose et évacuation d'une ancienne fenêtre | pce | 120 | dépose, ancienne fenêtre | estimation |
| Agencement | Armoire sur mesure, toute hauteur | m | 2200 | armoire, penderie, placard | 2 000 à 2 500 (S12) |
| Agencement | Étagère sur mesure | m | 180 | étagère, rayonnage | estimation |
| Cuisine | Pose de cuisine | m | 350 | cuisine, montage | estimation (IKEA Suisse : dès 500 la petite cuisine, prix de 2018, S15) |
| Cuisine | Plan de travail stratifié, fourni et posé | m | 320 | plan de travail | estimation |
| Divers | Heure de menuisier | h | 100 | régie, heure | 101 pour un menuisier qualifié, 115 au montage (S12, tarifs de régie VSSM) |
| Divers | Déplacement | forfait | 60 | déplacement | prix actuel |

Offres d'exemple :
- « Portes de l'appartement », Madame Essai, il y a 6 jours (offre actuelle, complétée) : dépose d'une ancienne porte 3, porte intérieure complète 3, ajustage 2, déplacement 1.
- « Fenêtres du séjour et armoire », Famille Exemple, il y a 2 jours : fenêtre 2, porte-fenêtre 1, dépose d'une ancienne fenêtre 3, armoire sur mesure 3, déplacement 1.

## Général (pour « Autre métier »)

Tous les prix sont des estimations : le métier est inconnu, aucun repère ne s'applique.

| Catégorie | Désignation | Unité | Prix | Synonymes |
|---|---|---|---|---|
| Main-d'œuvre | Heure de travail | h | 95 | heure, régie |
| Main-d'œuvre | Heure d'aide | h | 65 | aide, apprenti, manœuvre |
| Préparation | Visite et relevé sur place | forfait | 90 | visite, relevé, métré |
| Préparation | Installation de chantier | forfait | 250 | installation |
| Préparation | Protection des sols et des accès | forfait | 150 | bâcher, protéger |
| Fournitures | Petit matériel et fournitures | forfait | 80 | petit matériel, fournitures |
| Machines | Location de machine, par jour | pce | 120 | location, machine |
| Machines | Échafaudage roulant, par jour | pce | 90 | échafaudage |
| Déchets | Évacuation des déchets | forfait | 150 | déchets, déchetterie |
| Déchets | Benne, pose et enlèvement | pce | 480 | benne |
| Finitions | Nettoyage de fin de chantier | forfait | 220 | nettoyage |
| Divers | Déplacement | forfait | 60 | déplacement |

Offres d'exemple :
- « Petits travaux à la cave », Famille Exemple, il y a 2 jours : heure de travail 6, heure d'aide 6, petit matériel 1, évacuation 1, déplacement 1.
- « Débarras et remise en état », Monsieur Modèle, il y a 6 jours : installation de chantier 1, benne 1, heure de travail 12, location de machine 2, nettoyage 1, déplacement 1.

## Sources

Pages ouvertes le 2026-11-19. Les deux listes Hornbach (S8, S14) sont lues dans le PDF par Claude ; les autres par un agent de lecture, citations dans `ETAT.md`.

| | Source | Ce qu'elle donne |
|---|---|---|
| S1 | https://www.houzy.ch/handwerker/gipser | plâtrier 70 à 120 CHF/h ; enduit 40 à 120 CHF/m² ; plaques de plâtre 40 à 100 CHF/m² |
| S2 | https://www.houzy.ch/post/kosten-maurer | maçon 70 à 90 CHF/h ; enduire un mur intérieur 25 à 40 CHF/m² (contredit S1) ; cloison jusqu'à 12,5 cm 20 à 25 CHF/m² ; mur porteur 550 à 600 CHF/m³ ; mur de jardin en béton environ 500 CHF/m² ; pierre naturelle 200 à 250 CHF/m² ; ouverture de 5 m² 7 500 à 15 000 CHF, davantage dans un mur porteur. Page du 12.11.2023, prix de 2023 |
| S3 | https://en.houzy.ch/post/costs-tilers | carreleur 75 à 100 CHF/h ; pose au sol 70 à 100 CHF/m² ; murs 50 à 100 ; étanchéité 40 à 60 ; primaire 8 à 12 ; plinthes 20 à 50 CHF/m ; grès cérame 30 à 150 CHF/m². Prix de 2023. Aucun prix pour la dépose, le ragréage, les joints ni le grand format |
| S4 | https://www.houzy.ch/post/kosten-elektriker | électricien 90 à 135 CHF/h ; prise 30 à 50 CHF, interrupteur 50 à 75 CHF, sans matériel ni frais fixes |
| S5 | https://media.sig-ge.ch/documents/particuliers/nos_offres/mobilite_electrique/mobilite_tableau_prix_particuliers.pdf | borne installée 2 380 CHF TTC, mise en service 360 CHF (offre d'un fournisseur, Genève) |
| S6 | https://www.houzy.ch/post/parkett-verlegen-kosten et https://fr.houzy.ch/post/couts-poseurs-de-sol | préparation 25 à 35 et pose 25 à 35 CHF/m² ; ponçage et vitrification 30 à 50 ; poseur 80 à 120 CHF/h (page allemande), 80 à 100 (page française) ; moquette 80 à 160 CHF/m² de matériel (page française) |
| S7 | https://en.houzy.ch/post/laying-vinyl-flooring-cost | vinyle 60 à 100 CHF/m² de matériel, pose 30 à 40 ; plinthes 10 à 20 CHF/m |
| S8 | https://media.hornbach.ch/cmsm/ch/fr-ch/parkett.pdf | pose clipsée 29 à 33 CHF/m², collée 45, plinthes 11 CHF/m, sans matériel (liste du 13.10.2022) |
| S9 | https://www.houzy.ch/post/kosten-garten | jardinier 80 à 110 CHF/h ; tonte 0.25 à 0.40 CHF/m² ; gazon en rouleau 20 à 40 ; semis 5 à 15 ; chemin de jardin 100 à 250 ; gravier 60 à 120 CHF/m² ; taille de haie 8 à 22 CHF/m ; déchets verts 30 à 100 CHF par chargement. Article du 16.11.2026 |
| S10 | https://magicheidi.ch/fr/wie-viel-kosten/gaertner | aucun montant sur la page (« Ce guide ne mentionne donc volontairement aucun montant en francs ») : repère retiré |
| S11 | https://www.beobachter.ch/wohnen/garten-balkon/hilfe-bei-der-gartenpflege-der-gartner-im-abo | jardinier 80 à 100 CHF/h (article du 3.6.2022) |
| S12 | https://www.houzy.ch/post/schreiner-kosten | menuisier 80 à 140 CHF/h ; régie VSSM 101, montage 115 ; armoire toute hauteur 2 000 à 2 500 CHF par mètre linéaire. Page du 23.3.2023 |
| S13 | https://www.vs.ch/de/web/energie/die-fenster-ersetzen | fenêtre bois double vitrage environ 1 200 CHF, porte-fenêtre environ 1 800 CHF, sans dimension donnée |
| S14 | https://media.hornbach.ch/cmsm/ch/fr-ch/innentueren.pdf | pose d'une porte intérieure 149 à 189 CHF, démontage 60 CHF (liste du 13.10.2022) |
| S15 | https://www.ikea.com/ch/en/customer-service/services/kitchen-installation/ | pose d'une petite cuisine (moins de 2,5 m) dès 500 CHF, prix moyens de 2018 d'un partenaire |

## Prestations (activités, décision de Xavier du 2026-11-22)

Trois métiers et un catalogue général, pour l'activité « Prestations » (`docs/plans/2026-11-22_familles-activite/`). Les prix sont inventés, placés dans les fourchettes des sources S16 à S18 quand elles en donnent une. Ces sources sont des sites de vendeurs et des blogs : aucune statistique officielle n'existe sur ces tarifs. Les forfaits (site vitrine, logo, état des lieux) sont des estimations de Claude sans repère publié : Xavier les corrige à la recette.

### Informatique

Repères : développeur confirmé 100 à 150 CHF/h (S16) ; ingénierie en indépendant 100 à 250 CHF/h (S17).

| Catégorie | Désignation | Unité | Prix | Synonymes |
|---|---|---|---|---|
| Conseil | Analyse des besoins et cahier des charges | forfait | 900 | analyse, cahier des charges, besoins |
| Conseil | Heure de conseil | h | 160 | conseil, heure de conseil |
| Développement | Heure de développement | h | 140 | développement, programmation |
| Développement | Journée de développement | jour | 1100 | journée, jour de développement |
| Développement | Site vitrine, cinq pages | forfait | 3500 | site vitrine, site web, site internet |
| Installation | Installation d'un poste de travail | pce | 250 | poste, ordinateur, PC |
| Installation | Migration de la messagerie, par boîte | pce | 90 | messagerie, boîte, mail |
| Maintenance | Maintenance et mises à jour | mois | 180 | maintenance, mises à jour, contrat |
| Maintenance | Hébergement et nom de domaine | mois | 35 | hébergement, domaine |
| Maintenance | Sauvegarde externalisée | mois | 45 | sauvegarde, backup |
| Formation | Formation des utilisateurs, demi-journée | forfait | 650 | formation |
| Divers | Déplacement | forfait | 80 | déplacement |

Offres d'exemple :
- « Site vitrine de la boutique », Madame Essai, il y a 2 jours : analyse 1, site vitrine 1, hébergement 12 mois, formation 1.
- « Trois postes et la messagerie », Monsieur Modèle, il y a 6 jours : postes 3, boîtes 3, sauvegarde 12 mois, heure de conseil 2, déplacement 1.

### Conseil

Repères : journée d'un généraliste 960 à 1 120 CHF, d'un spécialiste 1 440 à 1 760 CHF, heure à 100 CHF au moins (S17) ; journée de 800 à 2 800 CHF selon l'expérience (S16).

| Catégorie | Désignation | Unité | Prix | Synonymes |
|---|---|---|---|---|
| Diagnostic | Entretien de cadrage | forfait | 450 | cadrage, premier entretien |
| Diagnostic | État des lieux et rapport | forfait | 2400 | état des lieux, diagnostic, audit |
| Accompagnement | Heure de conseil | h | 180 | heure, conseil |
| Accompagnement | Journée de conseil | jour | 1500 | journée, jour |
| Accompagnement | Demi-journée de conseil | pce | 800 | demi-journée |
| Accompagnement | Suivi mensuel | mois | 900 | suivi, accompagnement |
| Ateliers | Animation d'un atelier, demi-journée | forfait | 1200 | atelier, animation |
| Ateliers | Préparation d'un atelier | forfait | 600 | préparation |
| Livrables | Rédaction du rapport final | forfait | 1200 | rapport final, rédaction |
| Livrables | Présentation à la direction | forfait | 600 | présentation, restitution |
| Formation | Journée de formation | jour | 1800 | formation |
| Divers | Déplacement | forfait | 80 | déplacement, frais |

Offres d'exemple :
- « État des lieux de l'organisation », Monsieur Modèle, il y a 2 jours : cadrage 1, état des lieux 1, présentation 1, déplacement 2.
- « Accompagnement de six mois », Madame Essai, il y a 6 jours : journée de conseil 4, animation d'atelier 2, préparation 2, suivi mensuel 6.

### Marketing

Repères : petite agence 120 à 160 CHF/h (S17) ; gestion des réseaux sociaux 1 500 à 4 000 CHF par mois, gestion des campagnes 1 200 à 4 000 CHF par mois hors budget publicitaire (S18). Aucun repère en francs pour un logo, un flyer ou un article.

| Catégorie | Désignation | Unité | Prix | Synonymes |
|---|---|---|---|---|
| Stratégie | Plan de communication | forfait | 2400 | plan de communication, stratégie |
| Stratégie | Heure de conseil | h | 140 | conseil, heure |
| Création | Logo et identité visuelle | forfait | 2800 | logo, identité, charte graphique |
| Création | Flyer ou affiche, création | pce | 450 | flyer, affiche, dépliant |
| Création | Heure de graphisme | h | 120 | graphisme, mise en page |
| Contenus | Rédaction d'un article | pce | 350 | article, rédaction, texte |
| Contenus | Reportage photo, demi-journée | forfait | 900 | photo, reportage |
| Contenus | Lettre d'information, création et envoi | pce | 400 | newsletter, lettre d'information |
| Réseaux sociaux | Gestion des réseaux sociaux | mois | 1500 | réseaux sociaux, publications |
| Réseaux sociaux | Publication, texte et visuel | pce | 120 | publication, post |
| Publicité | Gestion des campagnes en ligne | mois | 1200 | campagne, publicité en ligne |
| Divers | Déplacement | forfait | 80 | déplacement |

Offres d'exemple :
- « Identité visuelle et flyer », Madame Essai, il y a 2 jours : logo 1, flyer 2, heure de graphisme 3.
- « Communication du trimestre », Monsieur Modèle, il y a 6 jours : plan de communication 1, réseaux sociaux 3 mois, article 3, reportage photo 1.

### Général des prestations (pour « Autre prestation »)

Tous les prix sont des estimations : le métier est inconnu, aucun repère ne s'applique.

| Catégorie | Désignation | Unité | Prix | Synonymes |
|---|---|---|---|---|
| Prestations | Heure de travail | h | 120 | heure |
| Prestations | Demi-journée | pce | 480 | demi-journée |
| Prestations | Journée | jour | 950 | journée, jour |
| Préparation | Premier entretien | forfait | 150 | entretien, rencontre |
| Préparation | Analyse de la demande | forfait | 400 | analyse, étude |
| Suivi | Suivi mensuel | mois | 300 | suivi, abonnement |
| Suivi | Séance de suivi | pce | 150 | séance, point |
| Livrables | Rapport écrit | forfait | 500 | rapport, document |
| Livrables | Présentation | forfait | 350 | présentation |
| Formation | Formation, demi-journée | forfait | 600 | formation, cours |
| Frais | Frais et fournitures | forfait | 80 | frais, fournitures |
| Frais | Déplacement | forfait | 80 | déplacement |

Offres d'exemple :
- « Mission de deux jours », Monsieur Modèle, il y a 2 jours : premier entretien 1, journée 2, rapport écrit 1, déplacement 2.
- « Suivi du semestre », Madame Essai, il y a 6 jours : analyse de la demande 1, suivi mensuel 6, séance de suivi 2, présentation 1.

### Sources des prestations

Résultats d'une recherche du 2026-11-22, lus par Claude dans le résumé de la recherche : les pages n'ont pas été ouvertes une à une. À revérifier avant de citer un chiffre hors de ce dossier.

| | Source | Ce qu'elle donne |
|---|---|---|
| S16 | https://www.jobbers.io/?p=122021 | développeur web junior 70 à 100 CHF/h, confirmé 100 à 150 CHF/h ; consultant en stratégie junior 800 à 1 280 CHF par jour, senior 1 440 à 2 800 CHF par jour |
| S17 | https://magicheidi.ch/fr/hourly-rates | ingénierie en indépendant 100 à 250 CHF/h ; conseil, généraliste 960 à 1 120 CHF par jour, spécialiste 1 440 à 1 760 CHF par jour ; heure à 100 CHF au moins ; petites agences 120 à 160 CHF/h, grandes 160 à 200 CHF/h |
| S18 | https://ewm.swiss/en/blog/digital-agencies-prices-switzerland-complete-guide | gestion des réseaux sociaux en agence 1 500 à 4 000 CHF par mois ; référencement 1 000 à 5 000 CHF par mois ; gestion publicitaire 1 200 à 4 000 CHF par mois, hors budget média |
