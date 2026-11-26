"""Fabrique l'offre « colonnes » : le texte est écrit dans le fichier colonne par colonne (toutes les positions,
puis toutes les désignations, puis les quantités...), comme le font certains exports de tableur. Une lecture dans
l'ordre du fichier mélange tout ; seule une lecture par coordonnées retrouve les lignes."""
import io, json, os
import pymupdf

ici = os.path.dirname(os.path.abspath(__file__))
offres = json.load(io.open(os.path.join(ici, 'offres.json'), encoding='utf-8'))['offres']
sortie = os.path.join(ici, '..', 'corpus', 'inventees')
os.makedirs(sortie, exist_ok=True)


def chf(n):
    s = '%.2f' % n
    ent, dec = s.split('.')
    groupes = []
    while len(ent) > 3:
        groupes.insert(0, ent[-3:]); ent = ent[:-3]
    return "'".join([ent] + groupes) + '.' + dec


def couper(texte, largeur):
    mots, lignes, cour = texte.split(), [], ''
    for m in mots:
        if len(cour) + len(m) + 1 > largeur:
            lignes.append(cour); cour = m
        else:
            cour = (cour + ' ' + m).strip()
    return lignes + [cour]


for o in offres:
    if o['mise_en_page'] != 'colonnes':
        continue
    doc = pymupdf.open()
    page = doc.new_page()  # A4, 595 x 842 points
    f, g = 'helv', 'hebo'
    page.insert_text((50, 60), o['entreprise'].split(',')[0], fontname=g, fontsize=11)
    page.insert_text((50, 75), ','.join(o['entreprise'].split(',')[1:]).strip(), fontname=f, fontsize=9.5)
    for i, l in enumerate(o['client'].split(', ')):
        page.insert_text((360, 120 + 13 * i), l, fontname=f, fontsize=10)
    page.insert_text((50, 190), o['titre'], fontname=g, fontsize=13)
    page.insert_text((50, 207), o['date'], fontname=f, fontsize=10)
    x = {'pos': 50, 'des': 85, 'qte': 330, 'u': 375, 'pu': 425, 'tot': 490}
    y0 = 245
    for cle, titre in [('pos', 'Pos.'), ('des', 'Désignation'), ('qte', 'Quantité'), ('u', 'Unité'), ('pu', 'Prix unit.'), ('tot', 'Montant')]:
        page.insert_text((x[cle], y0), titre, fontname=g, fontsize=9.5)
    lignes, y = [], y0 + 20
    for l in o['sections'][0]['lignes']:
        morceaux = couper(l['des'], 44)
        lignes.append((y, l, morceaux))
        y += 13 * len(morceaux) + 7
    # Colonne par colonne : c'est l'ordre d'écriture dans le fichier.
    for (yy, l, _) in lignes:
        page.insert_text((x['pos'], yy), l['pos'], fontname=f, fontsize=10)
    for (yy, l, morceaux) in lignes:
        for k, m in enumerate(morceaux):
            page.insert_text((x['des'], yy + 13 * k), m, fontname=f, fontsize=10)
    for (yy, l, _) in lignes:
        page.insert_text((x['qte'], yy), str(l['qte']).replace('.', ','), fontname=f, fontsize=10)
    for (yy, l, _) in lignes:
        page.insert_text((x['u'], yy), l['u'], fontname=f, fontsize=10)
    for (yy, l, _) in lignes:
        page.insert_text((x['pu'], yy), chf(l['pu']), fontname=f, fontsize=10)
    for (yy, l, _) in lignes:
        page.insert_text((x['tot'], yy), chf(l['qte'] * l['pu']), fontname=f, fontsize=10)
    brut = sum(l['qte'] * l['pu'] for l in o['sections'][0]['lignes'])
    tva = brut * o['tva'] / 100
    for k, (lib, val) in enumerate([('Total hors taxe', brut), ('TVA %s %%' % str(o['tva']).replace('.', ','), tva), ('Total TTC, CHF', brut + tva)]):
        page.insert_text((x['qte'], y + 20 + 15 * k), lib, fontname=g if k == 2 else f, fontsize=10)
        page.insert_text((x['tot'], y + 20 + 15 * k), chf(val), fontname=g if k == 2 else f, fontsize=10)
    page.insert_text((50, y + 90), o['pied'], fontname=f, fontsize=9.5)
    cible = os.path.join(sortie, o['id'] + '.pdf')
    doc.save(cible)
    print('fabriqué :', os.path.basename(cible), os.path.getsize(cible), 'octets')
