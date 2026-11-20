"""Second passage après la recette : plancher de 48 px pour toute cible (B4), textes indicatifs à 7:1, largeur des
champs sans field-sizing calculée en ch. Chaque remplacement doit trouver son texte exactement une fois."""
import sys

css_chemin, js_chemin = sys.argv[1], sys.argv[2]
css = open(css_chemin, encoding='utf-8').read()
js = open(js_chemin, encoding='utf-8').read()
C, J = [], []

# B4 : 48 px, plancher de toute cible.
for ancien, nouveau in [
    ('.plus summary { font-size: 14px; font-weight: 600; color: var(--accent); cursor: pointer; min-height: 44px;', '.plus summary { font-size: 14px; font-weight: 600; color: var(--accent); cursor: pointer; min-height: 48px;'),
    ('.x { width: 44px; height: 44px; display: grid; place-items: center; background: none; border: 0; color: var(--muted); cursor: pointer; margin-right: -10px; }', '.x { width: 48px; height: 48px; display: grid; place-items: center; background: none; border: 0; color: var(--muted); cursor: pointer; margin-right: -12px; }'),
    ('text-decoration: none; min-height: 44px; padding: 0 6px 0 2px; white-space: nowrap; }', 'text-decoration: none; min-height: 48px; padding: 0 6px 0 2px; white-space: nowrap; }'),
    ('text-decoration: underline; cursor: pointer; min-height: 44px; padding: 0 10px; }', 'text-decoration: underline; cursor: pointer; min-height: 48px; padding: 0 10px; }'),
    ('.ocard.echec .act button { font: inherit; font-size: 14px; font-weight: 600; min-height: 40px;', '.ocard.echec .act button { font: inherit; font-size: 14px; font-weight: 600; min-height: 48px;'),
    ('text-decoration: underline; min-height: 44px; padding: 0 8px; cursor: pointer; display: inline-flex; align-items: center; }', 'text-decoration: underline; min-height: 48px; padding: 0 8px; cursor: pointer; display: inline-flex; align-items: center; }'),
    ('.okbtn { font: inherit; background: none; border: 0; padding: 0; min-height: 44px; cursor: pointer; }', '.okbtn { font: inherit; background: none; border: 0; padding: 0; min-height: 48px; cursor: pointer; }'),
    ('color: var(--ink); outline: none; min-height: 46px; min-width: 0; }', 'color: var(--ink); outline: none; min-height: 48px; min-width: 0; }'),
    ('.fchips a { flex: none; min-height: 44px;', '.fchips a { flex: none; min-height: 48px;'),
    ('.libre-detail summary { list-style: none; display: flex; align-items: center; min-height: 44px;', '.libre-detail summary { list-style: none; display: flex; align-items: center; min-height: 48px;'),
    ('.ajout-pc input { flex: 1; min-height: 46px;', '.ajout-pc input { flex: 1; min-height: 48px;'),
    ('.radio, .check { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; min-height: 44px;', '.radio, .check { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; min-height: 48px;'),
    ('.puce-lien { display: inline-flex; align-items: center; min-height: 44px;', '.puce-lien { display: inline-flex; align-items: center; min-height: 48px;'),
    ('font-size: 16px; min-height: 44px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 12px;', 'font-size: 16px; min-height: 48px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 12px;'),
    ('border-radius: 999px; padding: 8px 14px; min-height: 44px; cursor: pointer; transition:', 'border-radius: 999px; padding: 8px 14px; min-height: 48px; cursor: pointer; transition:'),
    ('.teinte { position: relative; width: 44px; height: 44px;', '.teinte { position: relative; width: 48px; height: 48px;'),
    ('.teinte-autre { min-height: 44px; }', '.teinte-autre { min-height: 48px; }'),
    ('.voir { display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 36px;', '.voir { display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 48px;'),
    ('.suggestions button { font: inherit; font-size: 15px; width: 100%; min-height: 44px;', '.suggestions button { font: inherit; font-size: 15px; width: 100%; min-height: 48px;'),
    ('.lien-apercu { display: inline-flex; align-items: center; min-height: 44px;', '.lien-apercu { display: inline-flex; align-items: center; min-height: 48px;'),
    ('.apercu-grand-barre .btn { min-height: 44px; }', '.apercu-grand-barre .btn { min-height: 48px; }'),
    ('.lien-discret { display: inline-flex; align-items: center; min-height: 44px;', '.lien-discret { display: inline-flex; align-items: center; min-height: 48px;'),
    ('  .tabs a { flex: none; flex-direction: row; align-items: center; gap: 12px; min-height: 44px;', '  .tabs a { flex: none; flex-direction: row; align-items: center; gap: 12px; min-height: 48px;'),
    ('    .ohead .doc-cote { display: inline-flex; min-height: 40px;', '    .ohead .doc-cote { display: inline-flex; min-height: 48px;'),
    ('.cat h5 .renommer { display: inline-block; margin-left: 6px; padding: 12px 6px;', '.cat h5 .renommer { display: inline-flex; align-items: center; min-height: 48px; vertical-align: middle; margin-left: 6px; padding: 0 6px;'),
    # Quantité : le contenu de 2 chiffres donne 46 px, le plancher est 48.
    ('.q .saisie { min-width: calc(2ch + 26px);', '.q .saisie { min-width: max(48px, calc(2ch + 26px));'),
    # Textes indicatifs : la couleur du navigateur donnait 3,2 à 4,6:1 ; --muted tient 7:1 sur les fonds de l'application.
    ('.err { color: var(--bad); font-size: 13px; }', '::placeholder { color: var(--muted); opacity: 1; }\n.err { color: var(--bad); font-size: 13px; }'),
]:
    C.append((ancien, nouveau))

# Largeur des champs sans field-sizing : en ch (chiffres tabulaires, 1 ch chacun), comme app.css.
J.append(("  const ajuster = (c) => { c.size = Math.max(c.name === 'prix' ? 3 : 2, (c.value || c.placeholder || '').length); };",
          "  const ajuster = (c) => { c.style.width = 'calc(' + Math.max(c.name === 'prix' ? 3 : 2, (c.value || c.placeholder || '').length) + 'ch + 26px)'; };"))
J.append(("// un navigateur qui ne le connaît pas (Safari) reçoit l'attribut size, recalculé à la frappe et quand les lignes",
          "// un navigateur qui ne le connaît pas (Safari) reçoit la même largeur en ch, recalculée à la frappe et quand les lignes"))

for nom, texte, liste in [('css', css, C), ('js', js, J)]:
    for ancien, nouveau in liste:
        n = texte.count(ancien)
        if n != 1:
            sys.exit(f'ARRÊT {nom}, trouvé {n} fois : {ancien[:90]!r}')
        texte = texte.replace(ancien, nouveau)
    if nom == 'css':
        css = texte
    else:
        js = texte
open(css_chemin, 'w', encoding='utf-8', newline='\n').write(css)
open(js_chemin, 'w', encoding='utf-8', newline='\n').write(js)
print(len(C), 'css,', len(J), 'js')
