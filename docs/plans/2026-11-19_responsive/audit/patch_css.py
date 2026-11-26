"""Responsive R1 à R10 et boutons B2 à B4 (2026-11-19) dans public/css/app.css.
Chaque remplacement doit trouver son texte exactement une fois, sinon rien n'est écrit."""
import sys

chemin = sys.argv[1]
css = open(chemin, encoding='utf-8').read()
R = []


def rem(ancien, nouveau):
    R.append((ancien, nouveau))


# B2 : bord gris des boutons secondaires (3,8:1 en clair, 4,8:1 en sombre).
rem('justify-content: center; gap: 6px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); border-radius: 12px; padding: 10px 14px; min-height: 48px;',
    'justify-content: center; gap: 6px; border: 1px solid var(--bord); background: var(--surface); color: var(--ink); border-radius: 12px; padding: 10px 14px; min-height: 48px;')
rem('.btn.danger-texte { width: 100%; color: var(--bad); }',
    '.btn.danger-texte { width: 100%; color: var(--bad); border-color: var(--bad); }')

# B3 : un bouton qui envoie garde sa couleur ; un rond tourne devant son mot d'attente (aria-busy, posé par app.js).
rem('.btn[disabled] { opacity: .45; cursor: not-allowed; }',
    '/* Bouton pendant un envoi (B3, 2026-11-19) : couleur pleine, un rond qui tourne devant le mot d\'attente, jamais délavé. */\n'
    '.btn[disabled] { cursor: progress; }\n'
    '.btn[aria-busy="true"]::before { content: ""; flex: none; width: 16px; height: 16px; box-sizing: border-box; border: 2.5px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: tourne .8s linear infinite; }\n'
    '@media (prefers-reduced-motion: reduce) { .btn[aria-busy="true"]::before { animation: none; } }')

# R4 et R7 : deux champs côte à côte.
rem('.duo { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; align-items: start; }',
    '/* Deux champs côte à côte : les colonnes peuvent rétrécir sous la largeur d\'un champ date (R4) ; les champs s\'alignent\n'
    '   en bas, même quand un libellé se replie sur deux lignes (R7). */\n'
    '.duo { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; align-items: end; }\n'
    '.duo > * { min-width: 0; }')
rem('border: 1px solid var(--bord); border-radius: 12px; background: var(--surface); color: var(--ink); width: 100%; }\n.champ textarea',
    'border: 1px solid var(--bord); border-radius: 12px; background: var(--surface); color: var(--ink); width: 100%; min-width: 0; }\n.champ textarea')

# Lignes de l'offre : la grille passe sur .rows, les lignes et les titres en sous-grille.
rem('.rows { --cols: minmax(0, 1fr) 66px 58px 66px 40px; }\n', '')
rem('.rows.une .cat { display: grid; grid-template-columns: var(--cols); column-gap: 4px; align-items: end; }',
    '.rows.une .cat { display: grid; grid-template-columns: subgrid; align-items: end; }')
rem('.rows.une .row { display: grid; grid-template-columns: var(--cols); column-gap: 4px; align-items: center; min-height: 52px; scroll-margin: 90px 0 160px; }',
    '.rows.une .row { display: grid; grid-template-columns: subgrid; align-items: center; min-height: 52px; scroll-margin: 90px 0 160px; }')
rem('a.nm { min-height: 44px; display: block; }', 'a.nm { min-height: 48px; display: block; }')
rem('.q .saisie { width: 44px; }\n.u { font-size: 12px; color: var(--muted); }',
    '.u { font-size: 12px; color: var(--muted); }\n.rows .q .u { min-width: 40px; }')
rem('.p .saisie { width: 100%; }\n', '')
rem('/* Champ de quantité ou de prix : il a l\'air d\'une pastille, se touche, se corrige sur place. */\n'
    '.saisie { font: inherit; font-size: 14px; color: var(--ink); background: var(--chip); border: 1px solid transparent; border-radius: 6px; min-height: 44px; padding: 4px 5px; text-align: right; font-variant-numeric: tabular-nums; }\n'
    '.saisie:hover { border-color: var(--line); }\n'
    '.saisie:focus { outline: none; border: 2px solid var(--accent); background: var(--surface); }\n'
    '.saisie.manque { background: var(--bad-soft); }\n',
    '/* Champ de quantité ou de prix (Xavier, 2026-11-19) : un cadre fin sans fond, à la largeur de son contenu (2 chiffres pour\n'
    '   la quantité, 3 pour le prix ; il s\'élargit à la frappe, app.js prend le relais sans field-sizing). Le cadre se voit\n'
    '   sur 34 px, la zone à toucher fait 48 px. 16 px : Safari ne zoome pas la page au toucher (R5). */\n'
    '.saisie { font: inherit; font-size: 16px; color: var(--ink); background: transparent; border: 0; border-radius: 14px; min-height: 48px; padding: 0 12px; outline: 1px solid var(--bord); outline-offset: -7px; text-align: right; font-variant-numeric: tabular-nums; width: auto; field-sizing: content; }\n'
    '.q .saisie { min-width: calc(2ch + 26px); max-width: calc(7ch + 26px); }\n'
    '.p .saisie { min-width: calc(3ch + 26px); max-width: calc(9ch + 26px); }\n'
    '.saisie:hover { outline-color: var(--ink); }\n'
    '.saisie:focus { outline: 2px solid var(--accent); outline-offset: -7px; }\n'
    '.saisie.manque { outline-color: var(--bad); }\n')
rem('.del { width: 40px; min-height: 44px; display: grid;', '.del { width: 48px; min-height: 48px; display: grid;')

# R8 : filtres du catalogue.
rem('.fchips { display: flex; gap: 6px; overflow-x: auto; padding: 0 0 4px; scrollbar-width: none; }',
    '/* Filtres (R8) : au téléphone, la rangée défile ; un fondu au bord droit dit qu\'il en reste, et s\'efface au bout. */\n'
    '.fchips { display: flex; gap: 6px; overflow-x: auto; padding: 0 44px 4px 0; scrollbar-width: none; -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 44px), transparent); mask-image: linear-gradient(90deg, #000 calc(100% - 44px), transparent); }')

# Colonnes des lignes : partagées par toute l'offre, elles s'élargissent pour une valeur longue.
rem('/* Lignes : champs de 48 px, corbeille de 48 px écartée du montant (D4 A) ; glisser à gauche retire (D4 B). */\n'
    '.rows { --cols: minmax(0, 1fr) 62px 60px 66px 48px; }\n'
    '.rows.une .row { min-height: 60px; }\n'
    '.saisie { min-height: 48px; }\n'
    '.del { width: 48px; min-height: 48px; margin-left: 4px; }',
    '/* Lignes : corbeille de 48 px écartée du montant (D4 A) ; glisser à gauche retire (D4 B). Les lignes et les titres\n'
    '   partagent les colonnes de .rows (sous-grille) : un prix plus long élargit sa colonne pour toute l\'offre, en-tête\n'
    '   compris, sans empiéter sur la désignation (Xavier, 2026-11-19). */\n'
    '.rows { --cols: minmax(0, 1fr) minmax(96px, max-content) minmax(104px, max-content) 112px 52px; }\n'
    '.rows.une { display: grid; grid-template-columns: var(--cols); column-gap: 4px; }\n'
    '.rows.une > * { grid-column: 1 / -1; }\n'
    '.rows.une .row { min-height: 60px; }\n'
    '.del { margin-left: 4px; }')

# R3, R9 et dessin des lignes au téléphone.
ancien_l1 = css[css.index('/* Téléphone (décision L1 de Xavier, 2026-11-17'):css.index('.row[data-glisse-ligne]')]
rem(ancien_l1,
    '/* Téléphone, sous 700 px (L1 du 2026-11-17 ; R3, R9 et dessin des lignes du 2026-11-19) : l\'écran est une colonne de\n'
    '   480 px au plus. La désignation prend la première rangée, la corbeille en face d\'elle (choix B) ; quantité, prix et\n'
    '   total dessous. La désignation fait 48 px de haut, posée sur ses chiffres. Avant, le seuil de 420 px laissait la\n'
    '   quantité recouvrir la désignation sur un téléphone de 430 px. */\n'
    '@media (max-width: 699px) {\n'
    '  .rows { --cols: max-content max-content minmax(0, 1fr) 48px; }\n'
    '  .rows.une { column-gap: 10px; }\n'
    '  .rows.une .row { row-gap: 0; min-height: 0; }\n'
    '  .rows.une .cat h5 { grid-column: 1 / -1; }\n'
    '  .rows.une .cat .ch { text-align: left; padding-left: 7px; }\n'
    '  .rows.une .cat .ch:nth-child(4) { text-align: right; padding-left: 0; }\n'
    '  .rows.une .row > .nm { grid-column: 1 / 4; grid-row: 1; min-height: 48px; padding: 20px 0 0; }\n'
    '  .rows.une .row .q { grid-column: 1; grid-row: 2; justify-content: flex-start; gap: 4px; }\n'
    '  .rows.une .row .p { grid-column: 2; grid-row: 2; text-align: left; }\n'
    '  .rows.une .row .tt { grid-column: 3; grid-row: 2; }\n'
    '  .rows.une .row .del { grid-column: 4; grid-row: 1; align-self: start; justify-self: end; margin-left: 0; }\n'
    '}\n')

# B4 : segments à 48 px.
rem('.segments span { display: flex; align-items: center; justify-content: center; min-height: 44px;',
    '.segments span { display: flex; align-items: center; justify-content: center; min-height: 48px;')

# R1 A : la barre d'actions réserve la place du micro (téléphone et tablette).
rem('@media not all and (min-width: 1024px) and (hover: hover) and (pointer: fine) {\n  .pc-seul { display: none !important; }\n}',
    '@media not all and (min-width: 1024px) and (hover: hover) and (pointer: fine) {\n  .pc-seul { display: none !important; }\n'
    '  /* Le micro (72 px) dépasse de 24 px au-dessus des onglets : la barre d\'actions lui réserve sa place (R1 A, 2026-11-19). */\n'
    '  .bas:has(.tabs:not(.pc-seul)) .actionbar { padding-bottom: 34px; }\n}')

# PC : les colonnes viennent de la règle commune ; filtres sur plusieurs rangées.
rem('  /* Les lignes de l\'offre : place pour les montants. */\n  .rows { --cols: minmax(0, 1fr) 96px 104px 112px 52px; }\n',
    '  .fchips { flex-wrap: wrap; overflow: visible; padding-right: 0; -webkit-mask-image: none; mask-image: none; }\n')
rem('body.doc-a-cote .rows { --cols: minmax(0, 1fr) 62px 72px 80px 48px; }',
    'body.doc-a-cote .rows { --cols: minmax(0, 1fr) minmax(62px, max-content) minmax(72px, max-content) 80px 48px; }')

# R10 b C : trois volets dès 1800 px (dans le bloc PC, après celui de 1280 px).
rem('  .aide-clavier { font-size: 13px; color: var(--muted); line-height: 2; }',
    '  /* Grand écran (R10 b C, 2026-11-19) : dès 1800 px, la liste des offres reste à côté du document. Trois volets : la\n'
    '     liste, l\'offre, le document qui suit chaque correction. app.js l\'ouvre d\'office, sauf si l\'artisan l\'a fermé. */\n'
    '  @media (min-width: 1800px) {\n'
    '    body.doc-a-cote .colonne-liste { display: block; }\n'
    '    body.doc-a-cote:has(.colonne-liste) .app { margin-left: calc(var(--menu) + var(--liste)); margin-right: var(--doc); }\n'
    '  }\n'
    '  .aide-clavier { font-size: 13px; color: var(--muted); line-height: 2; }')

# Tablette : colonnes communes, filtres sur plusieurs rangées, formulaires de 720 px (R6).
rem('  .tabs a { font-size: 13px; }\n  .rows { --cols: minmax(0, 1fr) 96px 104px 112px 52px; }\n',
    '  .tabs a { font-size: 13px; }\n'
    '  .fchips { flex-wrap: wrap; overflow: visible; padding-right: 0; -webkit-mask-image: none; mask-image: none; }\n'
    '  /* Formulaires et fiches (R6) : 720 px au plus, au milieu, les boutons alignés sur eux. Facturer garde ses deux volets. */\n'
    '  .screen:has(.champ):not(:has(.facturer)) > * { max-width: 720px; margin-left: auto; margin-right: auto; }\n'
    '  .app:has(.screen .champ):not(:has(.facturer)) .actionbar { padding-left: max(28px, calc((100% - 720px) / 2)); padding-right: max(28px, calc((100% - 720px) / 2)); }\n')

# R2 A et R10 a B : la colonne d'onglets à gauche, après les blocs tablette.
rem('/* Identité Dicta : titres et boutons en Archivo',
    '/* Colonne d\'onglets à gauche (R2 A et R10 a B, 2026-11-19) : téléphone couché (390 px de haut, la barre du bas en\n'
    '   prenait la moitié) et fenêtre étroite d\'un PC. Le micro reste au milieu, dans l\'ordre de la barre du téléphone, à\n'
    '   mi-hauteur sous le pouce. La barre de l\'offre garde ses 64 px (B4). */\n'
    '@media (orientation: landscape) and (max-height: 500px) and (pointer: coarse), (min-width: 700px) and (max-width: 1023px) and (hover: hover) and (pointer: fine) {\n'
    '  body:has(.tabs:not(.pc-seul)) .app { margin-left: 80px; }\n'
    '  .tabs:not(.pc-seul) { position: fixed; left: 0; top: 0; bottom: 0; width: 80px; z-index: 6; flex-direction: column; justify-content: center; gap: 2px; border-top: 0; border-right: 1px solid var(--line); padding: 4px 0 4px env(safe-area-inset-left); }\n'
    '  .tabs:not(.pc-seul) a { flex: none; min-height: 56px; justify-content: center; font-size: 11px; padding: 4px 2px; }\n'
    '  .tabs:not(.pc-seul) .micro-central { flex: none; min-height: 68px; margin: 6px 0; }\n'
    '  .tabs:not(.pc-seul) .micro-central > span { position: static; transform: none; margin: 0 auto; width: 60px; height: 60px; border: 0; }\n'
    '  .bas:has(.tabs:not(.pc-seul)) { display: flex; align-items: center; border-top: 1px solid var(--line); }\n'
    '  .bas .totalbar { border-top: 0; padding: 0 4px 0 20px; flex: none; gap: 12px; }\n'
    '  .bas:has(.tabs:not(.pc-seul)) .actionbar { flex: 1; border-top: 0; padding: 8px 16px; }\n'
    '}\n'
    '@media (orientation: landscape) and (max-height: 500px) and (pointer: coarse) {\n'
    '  .bar { min-height: 44px; padding: 2px 20px; }\n'
    '  .bar .logo { height: 24px; }\n'
    '}\n'
    '/* Fenêtre étroite d\'un PC : la largeur sert aux lignes ; les fiches et formulaires restent une colonne. */\n'
    '@media (min-width: 700px) and (max-width: 1023px) and (hover: hover) and (pointer: fine) {\n'
    '  .app { max-width: none; }\n'
    '  body:not(:has(.tabs:not(.pc-seul))) .app { max-width: 640px; }\n'
    '  .screen { padding: 20px 28px 32px; }\n'
    '  .fchips { flex-wrap: wrap; overflow: visible; padding-right: 0; -webkit-mask-image: none; mask-image: none; }\n'
    '}\n\n'
    '/* Identité Dicta : titres et boutons en Archivo')

for ancien, nouveau in R:
    n = css.count(ancien)
    if n != 1:
        sys.exit(f'ARRÊT, trouvé {n} fois : {ancien[:90]!r}')
    css = css.replace(ancien, nouveau)
open(chemin, 'w', encoding='utf-8', newline='\n').write(css)
print(len(R), 'remplacements appliqués')
