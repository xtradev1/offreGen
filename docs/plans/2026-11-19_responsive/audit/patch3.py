"""Corrections après la recette des trois agents (2026-11-19). Défauts vérifiés sur capture avant d'être corrigés.
Chaque remplacement doit trouver son texte exactement une fois, sinon rien n'est écrit."""
import re
import sys

racine = sys.argv[1]
fichiers = {k: f'{racine}/{v}' for k, v in {
    'css': 'public/css/app.css', 'doc': 'public/css/document.css', 'tdoc': 'templates/_document.html.twig',
    'err': 'templates/erreur.html.twig', 'fac': 'templates/facture.html.twig'}.items()}
textes = {k: open(v, encoding='utf-8').read() for k, v in fichiers.items()}
R = []


def rem(f, ancien, nouveau):
    R.append((f, ancien, nouveau))


# 1. Deux champs côte à côte : sous-grille de rangées (libellé, champ, erreur, aide). Les champs restent alignés quand
#    un libellé se replie (R7) ET quand un message d'erreur s'affiche (l'alignement par le bas le cassait, 25 à 44 px).
rem('css', '/* Deux champs côte à côte : les colonnes peuvent rétrécir sous la largeur d\'un champ date (R4) ; les champs s\'alignent\n'
           '   en bas, même quand un libellé se replie sur deux lignes (R7). */\n'
           '.duo { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; align-items: end; }',
    '/* Deux champs côte à côte : les colonnes peuvent rétrécir sous la largeur d\'un champ date (R4). Chaque champ occupe\n'
    '   quatre rangées partagées (libellé, champ, erreur, aide) : les champs restent alignés quand un libellé se replie (R7)\n'
    '   et quand un message d\'erreur s\'affiche sous l\'un d\'eux (recette du 2026-11-19). */\n'
    '.duo { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); grid-template-rows: repeat(4, auto); column-gap: 10px; align-items: start; }\n'
    '.duo > .champ { grid-row: span 4; grid-template-rows: subgrid; row-gap: 0; }\n'
    '.duo > .champ > * + * { margin-top: 6px; }')

# 2. Texte indicatif : en italique, il ne passe plus pour une valeur saisie (même teinte proche de l'encre).
rem('css', '::placeholder { color: var(--muted); opacity: 1; }', '::placeholder { color: var(--muted); opacity: 1; font-style: italic; }')

# 3. Colonne de la corbeille quand le document est à côté : 48 px pour une corbeille de 48 px écartée de 4 px.
rem('css', 'minmax(72px, max-content) 80px 48px; }', 'minmax(72px, max-content) 80px 52px; }')

# 4. Lignes signalées : le filet passe dans la marge, les colonnes ne se décalent plus de 10 px.
rem('css', '.row.flag { box-shadow: inset 3px 0 0 var(--warn); padding-left: 10px; }',
    '.row.flag { box-shadow: inset 3px 0 0 var(--warn); padding-left: 10px; margin-left: -10px; }')
rem('css', '.row.miss { box-shadow: inset 3px 0 0 var(--bad); padding-left: 10px; }',
    '.row.miss { box-shadow: inset 3px 0 0 var(--bad); padding-left: 10px; margin-left: -10px; }')

# 5. Tablette : tout écran sans lignes d'offre ni volets se lit sur 720 px (documents, aperçu PDF, listes, TVA, client
#    de l'offre, annulation), plus seulement ceux qui ont des champs.
rem('css', '  /* Formulaires et fiches (R6) : 720 px au plus, au milieu, les boutons alignés sur eux. Facturer garde ses deux volets. */\n'
           '  .screen:has(.champ):not(:has(.facturer)) > * { max-width: 720px; margin-left: auto; margin-right: auto; }\n'
           '  .app:has(.screen .champ):not(:has(.facturer)) .actionbar {',
    '  /* Écrans sans lignes d\'offre (R6, étendu après la recette du 2026-11-19) : 720 px au plus, au milieu, les boutons\n'
    '     alignés sur eux. L\'offre, Facturer et la facture avec son panneau gardent la largeur. */\n'
    '  .screen:not(:has(.rows, .facturer, .panneau-facture)) > * { max-width: 720px; margin-left: auto; margin-right: auto; }\n'
    '  .app:not(:has(.screen .rows, .screen .facturer, .screen .panneau-facture)) .actionbar {')

# 6. Fenêtre du micro refusé : le message descend sous la croix.
rem('css', '.feuille [data-etat="court"], .feuille [data-etat="echec"] { padding-top: 40px; }',
    '.feuille [data-etat="court"], .feuille [data-etat="echec"], .feuille [data-etat="refus"] { padding-top: 40px; }')

# 7. Liste des offres à côté de l'offre : le titre tient sur deux lignes au lieu d'être coupé.
rem('css', '.lcard b { font-size: 15px; font-weight: 650; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }',
    '.lcard b { font-size: 15px; font-weight: 650; min-width: 0; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }')
rem('css', '.lcard .l1 { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }',
    '.lcard .l1 { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }')

# 8. Volets dépliables : un chevron dit qu'ils s'ouvrent.
rem('css', '.plus[open] summary { margin-bottom: 8px; }',
    '.plus[open] summary { margin-bottom: 8px; }\n'
    '.plus summary, .libre-detail summary { gap: 8px; }\n'
    '.plus summary::-webkit-details-marker { display: none; }\n'
    '.plus summary { list-style: none; }\n'
    '.plus summary::after, .libre-detail summary::after { content: "›"; font-size: 20px; line-height: 1; transition: transform .2s; }\n'
    '.plus[open] summary::after, .libre-detail[open] summary::after { transform: rotate(90deg); }')

# 9. Liens de factures sous 48 px : cibles de 48 px, 8 px entre elles.
rem('css', '.factures-offre { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 6px; }',
    '.factures-offre { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 6px; }\n'
    '.factures-offre .etiquette { display: inline-flex; align-items: center; min-height: 48px; padding: 0 14px; font-size: 13px; }')
rem('css', '.liste-factures { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; font-size: 14px; }',
    '.liste-factures { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; font-size: 14px; }\n'
    '.liste-factures a { display: inline-flex; align-items: center; min-height: 48px; }')
rem('css', '.panneau-facture dd { margin: 0; overflow-wrap: anywhere; }',
    '.panneau-facture dd { margin: 0; overflow-wrap: anywhere; }\n'
    '.panneau-facture dd a { display: inline-flex; align-items: center; min-height: 48px; }\n'
    '.lien-offre { display: inline-flex; align-items: center; min-height: 48px; }')
rem('css', '.ohead a.t { color: var(--ink); text-decoration: none; }',
    '.ohead a.t { color: var(--ink); text-decoration: none; display: flex; align-items: center; min-height: 48px; }')

# 10. Recherche des clients sans résultat : le compteur se tait.
rem('css', '.compteur { margin: 0 2px 4px; font-size: 13.5px; color: var(--muted); }',
    '.compteur { margin: 0 2px 4px; font-size: 13.5px; color: var(--muted); }\n'
    '.screen:has([data-aucun]:not([hidden])) .compteur { display: none; }')

# 11. Puces de choix et de filtre : le bord des boutons secondaires (B2), 1,42:1 avant.
for sel in ('.puce-lien {', '.puce {', '.part span {', '.fchips a span {'):
    lignes = [l for l in textes['css'].split('\n') if l.startswith(sel)]
    if len(lignes) != 1 or 'border: 1px solid var(--line)' not in lignes[0]:
        sys.exit(f'ARRÊT puces : {sel} {len(lignes)}')
    rem('css', lignes[0], lignes[0].replace('border: 1px solid var(--line)', 'border: 1px solid var(--bord)'))

# 12. Téléphone, écran sans barre d'actions : le bandeau passe au-dessus du micro, qui dépasse de 24 px des onglets.
rem('css', '  .bas:has(.tabs:not(.pc-seul)) .actionbar { padding-bottom: 34px; }\n}',
    '  .bas:has(.tabs:not(.pc-seul)) .actionbar { padding-bottom: 34px; }\n'
    '  .app:has(.bas > .tabs:not(.pc-seul)):not(:has(.bas .actionbar)) .snack { bottom: calc(var(--bas, 120px) + 36px); }\n}')

# 13. Téléphone couché : la fenêtre d'enregistrement tient dans 390 px (le lien « Abandonner » était coupé).
rem('css', '  .bar .logo { height: 24px; }\n}',
    '  .bar .logo { height: 24px; }\n'
    '  .rec { padding: 8px 0; }\n'
    '  .rec .dot { width: 64px; height: 64px; margin: 6px auto 8px; }\n}')

# 14. Lignes sur une rangée : « Qté » au-dessus du champ, pas de l'unité.
rem('css', '.rows.une .row { min-height: 60px; }\n.del { margin-left: 4px; }',
    '.rows.une .row { min-height: 60px; }\n.del { margin-left: 4px; }\n'
    '@media (min-width: 700px) { .rows.une .cat .ch:nth-child(2) { padding-right: 43px; } }')

# 15. PC : survol des cartes, des lignes et des boutons ; relecture en haut de l'écran ; pas de bande blanche en bas.
rem('css', '  .totalbar { padding: 10px 28px 0; }\n\n  /* Les fenêtres s\'ouvrent au milieu',
    '  .totalbar { padding: 10px 28px 0; }\n'
    '  /* La fenêtre de dictée, cachée, ne prend plus la marge du bas de l\'écran : 10 px blancs sous chaque page. */\n'
    '  .bas > .nouvelle:last-child { padding-bottom: 0; }\n'
    '  /* À la souris, la ligne à relire suit son compteur : poussée en bas pour le pouce, elle laissait 310 à 620 px vides. */\n'
    '  .screen.relecture .relu-reste { margin-bottom: 16px; }\n'
    '  @media (hover: hover) {\n'
    '    .ocard:hover, .krow:hover, .frow:hover, .omini:hover { border-color: var(--bord); }\n'
    '    .crow:hover, .pick:hover { background: var(--chip); }\n'
    '    .btn:not(.primary):not(.danger):not(.ok):not([disabled]):hover { background: var(--chip); }\n'
    '    .btn.primary:not([disabled]):hover, .btn.danger:not([disabled]):hover { filter: brightness(1.18); }\n'
    '  }\n\n  /* Les fenêtres s\'ouvrent au milieu')

# Document du client
rem('doc', 'color: #69717c; text-align: center;', 'color: #4b525b; text-align: center;')
rem('doc', '.doc-totaux td { padding: 3px 4px; }',
    '.doc-totaux td { padding: 3px 4px; }\n'
    '/* Écran étroit : les totaux prennent la largeur, « Sous-total HT » ne se coupe plus en deux. Le PDF n\'a pas .paper. */\n'
    '@media (max-width: 560px) { .paper .doc-totaux { margin-left: 0; width: 100% !important; } }\n'
    '/* PDF : conditions, signature et pied restent ensemble ; la signature ne part plus seule sur une page. */\n'
    '.doc-fin { page-break-inside: avoid; }')
debut = textes['tdoc'].index('  {% if facture %}\n  <div class="doc-cond">')
fin_pied = '  <div class="doc-pied">{{ r.entreprise }}{% if r.ide %} · {{ r.ide }}{% if avec_tva %} TVA{% endif %}{% endif %}</div>\n'
fin = textes['tdoc'].index(fin_pied) + len(fin_pied)
bloc = textes['tdoc'][debut:fin]
rem('tdoc', bloc, '  {# Conditions, signature et pied d\'un seul tenant dans le PDF (document.css, .doc-fin). #}\n  <div class="doc-fin">\n' + bloc + '  </div>\n')
rem('err', '<p class="muted">{{ titre }}.</p>', '<p class="muted">{{ titre|trim(\'.\', \'right\') }}.</p>')
rem('fac', '<a href="/offres/{{ f.offre_id }}">selon offre', '<a class="lien-offre" href="/offres/{{ f.offre_id }}">selon offre')

for f, ancien, nouveau in R:
    n = textes[f].count(ancien)
    if n != 1:
        sys.exit(f'ARRÊT {f}, trouvé {n} fois : {ancien[:100]!r}')
    textes[f] = textes[f].replace(ancien, nouveau)
for k, v in textes.items():
    open(fichiers[k], 'w', encoding='utf-8', newline='\n').write(v)
print(len(R), 'remplacements')
