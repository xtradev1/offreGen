"""Construit la page de relecture des textes de Dicta à partir des captures (captures/captures.json).

Deux sorties, même contenu :
- le site : relecture/index.html (servi par la route /relecture) et public/relecture-textes/ (styles, scripts,
  images). Le dossier ne s'appelle pas « relecture » : Apache redirigerait /relecture vers /relecture/ (404).
- la maquette locale du chantier : docs/plans/2026-11-24_relecture-textes/MAQUETTE-textes.html, kit en ligne.
Les textes de l'application vont dans un bloc JSON : aucun outil de mise en forme ne les retouche.
"""
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

ICI = Path(__file__).parent
CAPTURES = ICI.parent / 'captures'
DEPOT = Path(sys.argv[1])          # la copie de travail, vue de Windows
KIT = Path.home() / '.claude/skills/maquette'
NB = ' '

PARTIES = [
    ('site', 'Le site dicta.ch', 'La page qu’un artisan découvre avant d’essayer Dicta.', [
        ('site-haut', 'Haut de la page', 'Le premier écran : le menu, la promesse, l’animation « Dicter, vérifier, envoyer ».'),
        ('site-produit', 'Ce que Dicta fait pour vous', ''),
        ('site-image', 'Exemple de dictée et d’offre', ''),
        ('site-suisse', 'Fait en Suisse', ''),
        ('site-essai', 'Demander un essai', 'Le visiteur laisse son courriel pour être recontacté.'),
        ('site-demande-recue', 'Demande d’essai reçue', 'Après l’envoi du courriel.'),
        ('site-demande-invalide', 'Courriel refusé', 'Le courriel tapé n’a pas la forme d’une adresse.'),
        ('site-demande-trop', 'Trop de demandes', 'Le même visiteur a envoyé trop de demandes dans la journée.'),
        ('site-pied', 'Pied de page', ''),
    ]),
    ('demo', 'La démo et son guide', 'Le commercial crée une démo pendant le rendez-vous. L’artisan l’ouvre sur son téléphone, puis un guide lui montre quoi toucher.', [
        ('demo-entrer', 'Entrer dans la démo', 'L’artisan a scanné le code QR du commercial.'),
        ('guide-1-micro', 'Guide, étape 1 : le micro', 'Première ouverture de la démo. La bulle désigne le micro.'),
        ('guide-2-conseils', 'Guide, étape 2 : avant de dicter', 'Après le premier toucher sur le micro.'),
        ('guide-3-terminer', 'Guide, étape 3 : pendant la dictée', ''),
        ('guide-4-attente', 'Guide, étape 4 : l’offre se prépare', 'Après « Terminer et créer l’offre ».'),
        ('guide-5-reperes', 'Guide, étape 5 : relire l’offre', 'L’offre dictée est prête et l’artisan l’ouvre.'),
        ('avant-envoi', 'Avant l’envoi', 'L’artisan envoie une offre alors que le nom de son entreprise est encore celui de la démo.'),
        ('demo-fenetre-2-jours', 'La démo se termine dans 2 jours', 'Une fenêtre à l’ouverture de l’application.'),
        ('demo-fenetre-demain', 'La démo se termine demain', ''),
        ('demo-terminee', 'Démo terminée', 'À la première ouverture après la fin de la démo.'),
        ('demo-perimee', 'Lien de démo périmé', 'Le lien du code QR a déjà servi ou a plus d’une heure.'),
    ]),
    ('essai', 'Passage à l’essai et mise en route', 'L’artisan garde son compte et ses offres, et commence un mois gratuit. Quatre écrans l’aident à régler son entreprise.', [
        ('essai-passer', 'Passer à l’essai', 'Ouvert par le bouton « Passer à l’essai » du bandeau de la démo.'),
        ('essai-mot-de-passe', 'Choisir son mot de passe', 'Juste après le passage à l’essai.'),
        ('mise-en-route-entreprise', 'Mise en route, étape 1 : votre entreprise', ''),
        ('mise-en-route-tva', 'Mise en route, étape 2 : la TVA', ''),
        ('mise-en-route-logo', 'Mise en route, étape 3 : votre logo', ''),
        ('mise-en-route-marque', 'Mise en route, étape 4 : modèle et couleur', 'Les noms des douze modèles de document font partie des textes.'),
        ('mise-en-route-prix', 'Mise en route, étape 5 : vos prix', ''),
    ]),
    ('courriels', 'Les courriels', 'Les courriels que l’artisan reçoit. Sous chaque capture : l’objet, puis chaque partie du courriel.', [
        ('courriel-acces', 'Compte ouvert : choisir son mot de passe', ''),
        ('courriel-oubli', 'Mot de passe oublié', ''),
        ('courriel-mot-de-passe-change', 'Mot de passe changé', ''),
        ('courriel-demo-retour', 'Le lien pour revenir dans la démo', 'Envoyé à la première entrée dans la démo.'),
        ('courriel-rappel-2-jours', 'Rappel : la démo se termine dans 2 jours', ''),
        ('courriel-rappel-dernier', 'Dernier rappel, 12 heures avant la fin', ''),
        ('courriel-offre-prete', 'L’offre dictée est prête', 'Quand l’artisan a quitté l’écran pendant la préparation.'),
        ('courriel-dictee-longue', 'Dictée trop longue', ''),
        ('courriel-dictee-echec', 'Dictée qui n’a pas pu être traitée', ''),
    ]),
]
LARGES = {'site-haut', 'site-produit', 'site-image', 'site-suisse', 'site-essai', 'site-pied'}


def typo(t: str) -> str:
    """Typographie de mes propres phrases (titres et contextes), jamais des textes de l'application."""
    t = t.replace('« ', '«' + NB).replace(' »', NB + '»')
    return re.sub(r' ([:;!?])', NB + r'\1', t)


def utile(t: str) -> bool:
    """Un texte à relire porte au moins deux lettres hors des chiffres, des montants et des dates."""
    reste = re.sub(r'CHF|\d|[.,’\'%:×=+/\-\u2013·()]', ' ', t)
    return len(re.findall(r'[A-Za-zÀ-ÿ]', reste)) >= 2


captures = {c['id']: c for c in json.load(open(CAPTURES / 'captures.json', encoding='utf-8'))}
numero = {}   # texte -> son numéro, au premier écran qui le montre
marques = {}  # « capture:rang » (marque data-rl des copies figées) -> numéro du texte
n = 0
parties = []
manquantes = []
for pid, titre, intro, ecrans in PARTIES:
    sortie = []
    for eid, etitre, contexte in ecrans:
        c = captures.get(eid)
        if c is None:
            manquantes.append(eid)
            continue
        textes, deja = [], 0
        for rang, t in enumerate(c['textes']):
            if t is None:
                continue
            t = ' '.join(t.split(' ')).strip()
            if not utile(t):
                continue
            cle = ' '.join(t.split())
            if cle in numero:
                # Déjà numéroté plus haut : l'aperçu de cet écran suit quand même la correction.
                deja += 1
                marques[f'{eid}:{rang}'] = numero[cle]
                continue
            n += 1
            numero[cle] = marques[f'{eid}:{rang}'] = f'T{n:03d}'
            textes.append({'n': numero[cle], 't': t})
        sortie.append({'id': eid, 'titre': typo(etitre), 'contexte': typo(contexte), 'note': typo(c.get('note', '')),
                       'image': c['image'], 'apercu': c.get('apercu'), 'textes': textes, 'deja': deja, 'large': eid in LARGES})
    parties.append({'id': pid, 'titre': typo(titre), 'intro': typo(intro), 'ecrans': sortie})
if manquantes:
    sys.exit('Captures manquantes : ' + ', '.join(manquantes))
print(f'{n} textes numérotés, {sum(len(p["ecrans"]) for p in parties)} écrans, {len(marques)} marques')


def donnees(images: str, ecrans) -> str:
    """ecrans : adresse des copies figées, ou None (maquette locale : un fichier ouvert du disque ne peut pas les lire)."""
    return json.dumps({'images': images, 'ecrans': ecrans, 'parties': parties}, ensure_ascii=False).replace('</', '<\\/')


def relier(html: str) -> str:
    """Les marques de capture deviennent les numéros des textes ; une marque sans texte retenu disparaît."""
    html = re.sub(r' data-rl="([^"]+)"', lambda m: f' data-t="{marques[m.group(1)]}"' if m.group(1) in marques else '', html)
    # Un attribut style vide reste un style en ligne pour la CSP : chaque cadre le signalerait.
    # Un lien blob: ou data: (enregistrement gardé dans un fichier) ne mène nulle part dans la copie.
    html = re.sub(r' (?:href|src)="(?:blob|data):[^"]*"', '', html)
    return html.replace(' style=""', '')


gabarit = (ICI / 'gabarit.html').read_text(encoding='utf-8')
css = (ICI / 'relecture.css').read_text(encoding='utf-8')
js = (ICI / 'relecture.js').read_text(encoding='utf-8')

# 1. Le site : fichiers à part, aucune ligne de style ni de script dans la page (politique de sécurité du site).
pub = DEPOT / 'public/relecture-textes'
if pub.exists():
    shutil.rmtree(pub)
(pub / 'img').mkdir(parents=True)
for f in (CAPTURES / 'img').glob('*.jpg'):
    shutil.copy(f, pub / 'img' / f.name)
# Les copies figées des écrans, pour l'aperçu en direct : HTML en .txt (le site ne sert pas de .html statique).
(pub / 'ecrans').mkdir()
for f in (CAPTURES / 'ecrans').iterdir():
    if f.suffix == '.txt':
        (pub / 'ecrans' / f.name).write_text(relier(f.read_text(encoding='utf-8')), encoding='utf-8', newline='\n')
    else:
        shutil.copy(f, pub / 'ecrans' / f.name)
shutil.copy(ICI / 'ecrans-commun.css', pub / 'ecrans' / 'commun.css')
(pub / 'relecture.css').write_text(css.replace('%%POLICES%%', '/fonts'), encoding='utf-8', newline='\n')
(pub / 'relecture.js').write_text(js, encoding='utf-8', newline='\n')
shutil.copy(KIT / 'assets/maquette-kit.css', pub / 'kit.css')
shutil.copy(KIT / 'assets/maquette-kit.js', pub / 'kit.js')
site = (gabarit
        .replace('%%CSS%%', '<link rel="stylesheet" href="/relecture-textes/kit.css">\n<link rel="stylesheet" href="/relecture-textes/relecture.css">')
        .replace('%%JS%%', '<script src="/relecture-textes/relecture.js"></script>\n<script src="/relecture-textes/kit.js"></script>')
        .replace('%%DONNEES%%', donnees('/relecture-textes/img/', '/relecture-textes/ecrans/')))
(DEPOT / 'relecture').mkdir(exist_ok=True)
(DEPOT / 'relecture/index.html').write_text(site, encoding='utf-8', newline='\n')

# 2. La maquette locale du chantier : un seul fichier, images lues dans public/relecture/img.
chantier = DEPOT / 'docs/plans/2026-11-24_relecture-textes'
chantier.mkdir(parents=True, exist_ok=True)
locale = (gabarit
          .replace('%%CSS%%', '<style>\n' + css.replace('%%POLICES%%', '../../../public/fonts') + '</style>')
          .replace('%%JS%%', '<script>\n' + js + '</script>')
          .replace('%%DONNEES%%', donnees('../../../public/relecture-textes/img/', None)))
cible = chantier / 'MAQUETTE-textes.html'
cible.write_text(locale, encoding='utf-8', newline='\n')
r = subprocess.run([sys.executable, str(KIT / 'scripts/inject_kit.py'), str(cible)], capture_output=True, text=True, encoding='utf-8')
print(r.stdout.strip(), r.stderr.strip())
if r.returncode:
    sys.exit(1)
for f in [DEPOT / 'relecture/index.html', pub / 'relecture.css', pub / 'relecture.js', cible]:
    s = f.read_text(encoding='utf-8')
    print(f.name, 'tirets longs :', len(re.findall('[\u2014\u2013]|&' + 'mdash;', s)), '| BOM :', s.startswith('\ufeff'))
