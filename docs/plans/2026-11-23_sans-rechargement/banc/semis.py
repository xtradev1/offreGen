#!/usr/bin/env python3
"""Jeu de mesure inventé : catalogue de 300 produits de peintre et 40 clients pour le compte 1 de la base
offregen_fluidite_vue (chantier « sans rechargement », 2026-11-23). Écrit du SQL sur la sortie standard."""
import random

random.seed(20261123)
A = 1


def q(s):
    return "'" + s.replace("\\", "\\\\").replace("'", "''") + "'"


produits = []
supports_prep = ['des murs', 'des plafonds', 'des boiseries', 'des radiateurs', 'des fenêtres', 'des volets', 'des portes', 'des escaliers']
for op, syn in [('Lessivage', 'laver'), ('Ponçage', 'poncer'), ('Rebouchage', 'reboucher'), ('Décapage', 'décaper'), ('Grattage', 'gratter'),
                ('Dépoussiérage', 'dépoussiérer'), ('Masquage', 'masquer'), ('Égrenage', 'égrener'), ('Ratissage', 'ratisser')]:
    for s in supports_prep:
        u = 'pce' if s in ('des radiateurs', 'des fenêtres', 'des volets', 'des portes') else 'm²'
        produits.append(('Préparation', f'{op} {s}', u, syn))
for op, syn in [('Peinture dispersion mate', 'dispersion'), ('Peinture dispersion satinée', 'satiné'), ('Peinture acrylique', 'acrylique'),
                ('Peinture minérale', 'minérale'), ('Peinture à la chaux', 'chaux'), ('Laque glycéro', 'glycéro'), ('Laque acrylique', 'laque')]:
    for s in ['murs', 'plafonds', 'boiseries', 'radiateurs', 'portes', 'plinthes', 'cadres de fenêtre', 'escaliers']:
        u = 'pce' if s in ('radiateurs', 'portes') else ('m' if s in ('plinthes', 'cadres de fenêtre') else 'm²')
        produits.append(('Peinture intérieure', f'{op}, {s}, 2 couches', u, syn))
for op, syn in [('Peinture silicate', 'silicate'), ('Peinture de façade acrylique', 'façade'), ('Lasure', 'lasure'), ('Vernis', 'vernis'),
                ('Hydrofuge', 'hydrofuge'), ('Peinture anti-graffiti', 'graffiti')]:
    for s in ['façade', 'volets', 'avant-toit', 'balcon', 'barrière', 'portail', 'chéneaux', 'garage']:
        u = 'pce' if s in ('volets', 'portail') else ('m' if s in ('barrière', 'chéneaux') else 'm²')
        produits.append(('Peinture extérieure', f'{op}, {s}', u, syn))
for op, syn in [('Pose de papier peint', 'tapisserie'), ('Pose de toile de verre', 'toile'), ('Pose de crépi', 'crépi'), ('Pose de fibre', 'fibre'),
                ('Dépose de papier peint', 'décoller'), ('Enduit décoratif', 'stuc')]:
    for s in ['murs', 'plafonds', "cage d'escalier", 'salle de bain', 'cuisine', 'chambre']:
        produits.append(('Revêtements', f'{op}, {s}', 'm²', syn))
for op, syn in [('Vitrification', 'vitrifier'), ('Huilage', 'huile'), ('Peinture de sol époxy', 'époxy'), ('Ponçage de parquet', 'parquet'), ('Résine', 'résine')]:
    for s in ['parquet', 'escalier', 'garage', 'cave', 'terrasse', 'balcon']:
        produits.append(('Sols', f'{op}, {s}', 'm²', syn))
divers = ["Location d'échafaudage roulant", 'Benne de chantier', 'Nettoyage de fin de chantier', 'Petites fournitures', 'Supplément urgence',
          'Supplément week-end', 'Protection des vitrages', 'Évacuation des déchets', 'Bâche de protection', 'Ruban de masquage',
          'Location de nacelle', 'Déplacement hors canton', 'Heure de compagnon', "Heure d'apprenti", 'Mise en teinte',
          'Échantillon de couleur', 'Conseil couleur', 'Métré sur place', 'Rapport photo', 'Garantie prolongée']
for d in divers:
    u = 'h' if d.startswith('Heure') else 'forfait'
    produits.append(('Divers', d, u, d.split()[0].lower()))
produits = produits[:288]  # 12 produits de démo déjà là : 300 en tout

lignes = ['DELETE FROM produit WHERE artisan_id = %d AND id > 12;' % A]
for cat, des, u, syn in produits:
    prix = random.choice([5, 8, 9, 12, 15, 18, 22, 25, 28, 32, 38, 45, 55, 65, 80, 95, 120, 160, 220]) * 100 + random.choice([0, 0, 50])
    lignes.append('INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes, synonymes) VALUES '
                  f'({A}, {q(cat)}, {q(des)}, {q(u)}, {prix}, {q(syn)});')

noms = ['Dupont', 'Rochat', 'Favre', 'Bonvin', 'Cuenot', 'Girard', 'Monnier', 'Perrin', 'Schmid', 'Meier', 'Rossier', 'Jaquet', 'Pittet',
        'Chevalley', 'Berset', 'Clément', 'Python', 'Rey', 'Délèze', 'Fournier']
rues = ['Rue du Marché', 'Avenue de la Gare', 'Chemin des Vignes', 'Route de Berne', 'Rue des Alpes', 'Chemin du Lac', 'Grand-Rue', 'Rue du Temple']
villes = [('1700', 'Fribourg'), ('2000', 'Neuchâtel'), ('1003', 'Lausanne'), ('1950', 'Sion'), ('2300', 'La Chaux-de-Fonds'), ('1201', 'Genève'), ('2800', 'Delémont')]
lignes.append('DELETE FROM client WHERE artisan_id = %d AND exemple = 0;' % A)
for i in range(40):
    nom = ('Famille ' if i % 3 == 0 else ('Madame ' if i % 3 == 1 else 'Monsieur ')) + noms[i % len(noms)] + ('' if i < 20 else ' ' + chr(65 + i % 26) + '.')
    rue = rues[i % len(rues)]
    num = str(1 + (i * 7) % 60)
    npa, loc = villes[i % len(villes)]
    lignes.append('INSERT INTO client (artisan_id, nom, adresse, rue, numero, npa, localite, pays, cree_le) VALUES '
                  f"({A}, {q(nom)}, {q(rue + ' ' + num + chr(10) + npa + ' ' + loc)}, {q(rue)}, {q(num)}, {q(npa)}, {q(loc)}, 'CH', NOW() - INTERVAL {i} DAY);")
print('\n'.join(lignes))
