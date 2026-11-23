#!/usr/bin/env python3
"""Typographie française d'un fichier Markdown : espace insécable avant les deux-points, le point-virgule, les points
d'exclamation et d'interrogation, et à l'intérieur des guillemets. Hors bloc de code, hors code en ligne, hors en-tête
YAML. Contrôles : ponctuation intacte, aucun tiret long. Usage : python3 typo.py fichier.md [...]
Les caractères spéciaux sont écrits par chr() : un échappement dans le texte du script peut être transformé à
l'écriture du fichier (constaté le 2026-11-23)."""
import re
import sys

NB = chr(0xA0)
FINE = chr(0x202F)
OUVRANT, FERMANT = chr(0xAB), chr(0xBB)
TIRETS = chr(0x2014) + chr(0x2013)
ENTITE = '&' + 'mdash;'


def corriger_texte(t):
    t = re.sub('[ ' + FINE + ']+([:;!?' + FERMANT + '])', lambda m: NB + m.group(1), t)
    t = re.sub(OUVRANT + '[ ' + FINE + ']+', lambda m: OUVRANT + NB, t)
    return t


def corriger_ligne(ligne):
    morceaux = re.split(r'(`[^`]*`|\]\([^)]*\)|https?://\S+)', ligne)
    return ''.join(m if (m.startswith('`') or m.startswith('](') or m.startswith('http')) else corriger_texte(m) for m in morceaux)


for chemin in sys.argv[1:]:
    with open(chemin, encoding='utf-8') as f:
        lignes = f.read().split('\n')
    avant = '\n'.join(lignes)
    sortie, code, yaml = [], False, False
    for i, l in enumerate(lignes):
        if i == 0 and l.strip() == '---':
            yaml = True
            sortie.append(l)
            continue
        if yaml:
            sortie.append(l)
            if l.strip() == '---':
                yaml = False
            continue
        if l.strip().startswith('```'):
            code = not code
            sortie.append(l)
            continue
        sortie.append(l if code else corriger_ligne(l))
    apres = '\n'.join(sortie)
    ponct = lambda s: [c for c in s if c in ':;!?' + OUVRANT + FERMANT]
    assert ponct(avant) == ponct(apres), 'ponctuation modifiée'
    assert not any(c in apres for c in TIRETS) and ENTITE not in apres, 'tiret long trouvé'
    with open(chemin, 'w', encoding='utf-8', newline='\n') as f:
        f.write(apres)
    print(chemin, 'insécables :', apres.count(NB), '(avant :', avant.count(NB), ')')
