"""Combien de jetons Whisper pour un vocabulaire de 500 caractères ? Catalogues de démo réels du test, recopiés."""
import glob, os, sys
from tokenizers import Tokenizer

chemins = glob.glob(os.path.expanduser('~/offregen-ia/hf/hub/**/tokenizer.json'), recursive=True)
if not chemins:
    sys.exit('aucun tokenizer.json en cache')
print('tokeniseur :', chemins[0].split('/hub/')[1][:80])
tok = Tokenizer.from_file(chemins[0])

peintre = ["Protection des sols et des meubles", "Rebouchage et ponçage des murs", "Couche d'impression",
           "Peinture des murs, 2 couches", "Peinture des plafonds, 2 couches", "Laquage des plinthes",
           "Laquage de porte, 2 faces", "Peinture anti-moisissure", "Peinture de façade, 2 couches",
           "Échafaudage, montage et location", "Déplacement", "Heure de régie", "Sol travertin"]
lourd = [f"Crépi rustique à l'huile de lin numéro {i}, façade exposée, échafaudage compris" for i in range(30)]
for nom, liste in (('peintre (13 produits)', peintre), ('accents, long', lourd)):
    texte = 'Offre. '
    gardes = []
    for d in liste:
        essai = 'Offre. ' + ', '.join(gardes + [d]) + '.'
        if len(essai) > 500:
            break
        gardes.append(d)
    texte = 'Offre. ' + ', '.join(gardes) + '.'
    print(f'{nom} : {len(texte)} caractères, {len(tok.encode(texte, add_special_tokens=False).ids)} jetons')
