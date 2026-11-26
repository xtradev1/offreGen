"""Sous-chantier 30 (question de Xavier du 2026-11-24) : les fautes de transcription font-elles rater le catalogue ?

Pour chaque ligne extraite d'une dictée (texte entendu gardé), on cherche dans le texte entendu un mot qui SONNE comme
un mot du catalogue de l'artisan sans s'ECRIRE pareil (« plaintes » pour « plinthes »).
- ligne hors catalogue avec un tel mot : raté possible, à juger à la main ;
- ligne au catalogue avec un tel mot et sans le mot écrit juste : le modèle a déjà fait le rapprochement.
Clé phonétique française simplifiée, écrite pour cette mesure (pas une bibliothèque) : voir cle().
"""
import csv, re, sys, unicodedata
from collections import defaultdict

VIDES = set("""de des du la le les et en un une au aux pour par sur dans avec sans à a deux trois quatre cinq
mètre mètres carré carrés couche couches face faces pièce pièces forfait heure heures prix francs franc compris
mur murs ou on il y est ce ça qui que tout tous toute""".split())


def sans_accents(t):
    return ''.join(c for c in unicodedata.normalize('NFD', t) if unicodedata.category(c) != 'Mn')


def cle(mot):
    """Prononciation approchée : voyelles proches confondues, lettres muettes de fin retirées, doubles réduites."""
    m = sans_accents(mot.lower())
    m = re.sub(r'[^a-z]', '', m)
    if not m:
        return ''
    for a, b in [('eau', 'o'), ('au', 'o'), ('ph', 'f'), ('qu', 'k'), ('gu', 'g'), ('ch', 'S'), ('gn', 'N'),
                 ('ce', 'se'), ('ci', 'si'), ('cy', 'si'), ('ge', 'je'), ('gi', 'ji'), ('c', 'k'), ('ck', 'k'),
                 ('y', 'i'), ('w', 'v'), ('h', ''), ('th', 't')]:
        m = m.replace(a, b)
    m = re.sub(r'(ain|ein|in|im|un|um)(?![aeiou])', '1', m)
    m = re.sub(r'(an|am|en|em)(?![aeiou])', '2', m)
    m = re.sub(r'(on|om)(?![aeiou])', '3', m)
    m = re.sub(r'(ai|ei|er$|ez$|et$|e(?=[^aeiou]{2}))', 'E', m)
    m = re.sub(r'(?<=[aeiouE123])s(?=[aeiouE123])', 'z', m)
    m = re.sub(r'([a-zSN])\1+', r'\1', m)
    m = re.sub(r'(e|es|s|t|ts|x|d|ds|nt)$', '', m) or m
    m = re.sub(r'([a-zSN])\1+', r'\1', m)
    return m


def distance(a, b):
    if abs(len(a) - len(b)) > 1:
        return 9
    p = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        c = [i]
        for j, cb in enumerate(b, 1):
            c.append(min(p[j] + 1, c[j - 1] + 1, p[j - 1] + (ca != cb)))
        p = c
    return p[-1]


def mots(texte):
    return re.findall(r"[\w']+", texte.lower())


def significatifs(texte):
    return {m for m in mots(texte) if len(sans_accents(m)) >= 4 and m not in VIDES and not m.isdigit()}


def morceaux(texte, n=3):
    """Mots seuls et suites de 2 ou 3 mots collés : « à la quai » doit pouvoir sonner comme « laquer »."""
    ms = mots(texte)
    for i in range(len(ms)):
        for k in range(1, n + 1):
            if i + k <= len(ms):
                yield ' '.join(ms[i:i + k]), cle(''.join(ms[i:i + k]))


def main(dossier):
    produits = defaultdict(dict)
    for p in csv.DictReader(open(f'{dossier}/phon_produits.tsv', encoding='utf-8'), delimiter='\t'):
        produits[p['artisan_id']][p['id']] = p['designation'] + ' ' + p['synonymes']
    lignes = list(csv.DictReader(open(f'{dossier}/phon_lignes.tsv', encoding='utf-8'), delimiter='\t'))
    hors, rattrapees, sures = [], [], 0
    for l in lignes:
        catalogue = produits.get(l['artisan_id'], {})
        ecrits = set(mots(l['entendu']))
        trouves = []
        for pid, texte in catalogue.items():
            for mot in significatifs(texte):
                k = cle(mot)
                if len(k) < 4 or mot in ecrits:
                    continue
                for surface, kk in morceaux(l['entendu']):
                    if surface.replace(' ', '') == mot:
                        continue
                    if kk and (kk == k or (len(k) >= 6 and distance(kk, k) <= 1)):
                        trouves.append((pid, mot, surface))
        if not trouves:
            if l['produit_id']:
                sures += 1
            continue
        if l['produit_id']:
            juste = any(pid == l['produit_id'] for pid, _, _ in trouves)
            rattrapees.append((l, trouves, juste))
        else:
            hors.append((l, trouves))
    print(f"lignes lues (texte entendu gardé) : {len(lignes)}, dont hors catalogue : {sum(1 for l in lignes if not l['produit_id'])}")
    print(f"artisans : {len({l['artisan_id'] for l in lignes})}, dictées : {len({l['enregistrement_id'] for l in lignes})}")
    print(f"\n=== HORS CATALOGUE, un mot y sonne comme le catalogue ({len(hors)}) : ratés possibles, à juger")
    for l, t in hors:
        cands = sorted({f"{mot} <- « {surf} » (produit {pid} : {produits[l['artisan_id']][pid].strip()[:50]})" for pid, mot, surf in t})
        print(f"- ligne {l['id']} (offre {l['offre_id']}) « {l['designation']} » | entendu : {l['entendu'][:110]}")
        for c in cands[:4]:
            print(f"    {c}")
    print(f"\n=== AU CATALOGUE, mot mal écrit dans l'entendu ({len(rattrapees)}) : le modèle a-t-il fait le rapprochement ?")
    for l, t, juste in rattrapees:
        cands = sorted({f"{mot} <- « {surf} »" for pid, mot, surf in t if pid == l['produit_id']}) or sorted({f"{mot} <- « {surf} » (produit {pid})" for pid, mot, surf in t})
        print(f"- ligne {l['id']} (offre {l['offre_id']}) « {l['designation']} » {'[même produit]' if juste else '[autre produit]'} | entendu : {l['entendu'][:100]}")
        for c in cands[:3]:
            print(f"    {c}")


if __name__ == '__main__':
    main(sys.argv[1])
