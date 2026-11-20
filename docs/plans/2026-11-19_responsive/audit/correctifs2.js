// Tour 2 : les points validés par Xavier (R1 A, R3, R4, R5, R6, R7, R8, R9) forment la base ; par-dessus, le nouveau
// dessin des lignes, la place du micro dans la colonne d'onglets, et la variante C du grand écran.
const C = require('./correctifs.js');
// R10 a B validé : la fenêtre PC étroite n'est plus une colonne de 480 px, la vue compacte s'arrête donc sous 700 px.
const ETROIT = '(max-width: 699px)';
const R3_SEUL = '@media (min-width: 420px) and (max-width: 699px) {' + C.R3.slice(C.R3.indexOf('{') + 1);

const CHAMPS = `
  /* Champs légers : cadre fin, sans fond. Cadre visible de 34 px, zone à toucher de 48 px (CODING_RULES). */
  .saisie { font-size: 16px; background: transparent; border: 0; border-radius: 14px; min-height: 48px; padding: 0 12px; outline: 1px solid var(--bord); outline-offset: -7px; width: auto; field-sizing: content; text-align: right; }
  .saisie:hover { border: 0; outline-color: var(--ink); }
  .saisie:focus { border: 0; background: transparent; outline: 2px solid var(--accent); outline-offset: -7px; }
  .q .saisie { width: auto; min-width: calc(2ch + 26px); max-width: calc(7ch + 26px); }
  .p .saisie { width: auto; min-width: calc(3ch + 26px); max-width: calc(9ch + 26px); }
  .saisie.manque { background: transparent; outline-color: var(--bad); }`;

const LIGNES = `
  @media ${ETROIT} {
    /* Une seule grille pour toute l'offre : les lignes partagent les colonnes, qui suivent la valeur la plus large. */
    .rows.une { display: grid; grid-template-columns: max-content max-content minmax(0, 1fr) 48px; column-gap: 10px; }
    .rows.une > * { grid-column: 1 / -1; }
    .rows.une .cat, .rows.une .row { display: grid; grid-template-columns: subgrid; column-gap: 10px; }
    .rows.une .cat h5 { grid-column: 1 / -1; }
    .rows.une .cat .ch { text-align: left; padding-left: 7px; }
    .rows.une .cat .ch:nth-child(4) { text-align: right; padding-left: 0; }
    .rows.une .row { row-gap: 0; min-height: 0; }
    /* La désignation se pose sur ses chiffres : le vide passe au-dessus, entre deux lignes. */
    .rows.une .row > .nm { grid-column: 1 / 4; grid-row: 1; min-height: 48px; padding: 20px 0 0; }
    .rows.une .row .q { grid-column: 1; grid-row: 2; justify-content: flex-start; gap: 4px; }
    .rows.une .row .p { grid-column: 2; grid-row: 2; text-align: left; }
    .rows.une .row .tt { grid-column: 3; grid-row: 2; text-align: right; padding-left: 0; }
    .rows.une .row .del { grid-column: 4; grid-row: 1 / span 2; align-self: center; justify-self: end; margin: 0; }
    .rows.une .row > .note, .rows.une .row > .ranger-ligne { grid-column: 1 / -1; }
  }`;
const POUBELLE_HAUT = `@media ${ETROIT} { .rows.une .row .del { grid-row: 1; align-self: start; } }`;

// Colonne d'onglets (R2, R10 a) : le micro au milieu, à l'ordre de la barre du téléphone ; ou en bas.
const RAIL_MILIEU = C.R2A.replace('order: -1; margin-bottom: 6px;', 'margin: 6px 0;');
const RAIL_BAS = C.R2A.replace('order: -1; margin-bottom: 6px;', 'order: 9; margin-top: auto; margin-bottom: 10px;').replace('justify-content: center; gap: 2px;', 'justify-content: flex-start; gap: 2px;');
const RAIL_PC_MILIEU = C.R10aB.replace('order: -1; margin-bottom: 6px;', 'margin: 6px 0;');

const BASE = [C.R1A, R3_SEUL, C.R4, C.R5, C.R6, C.R7, C.R8, C.R9].join('\n');

module.exports = {
  avant: '',
  base: BASE,
  lignesA: BASE + CHAMPS + LIGNES,
  lignesB: BASE + CHAMPS + LIGNES + POUBELLE_HAUT,
  railMilieu: BASE + CHAMPS + LIGNES + RAIL_MILIEU,
  railBas: BASE + CHAMPS + LIGNES + RAIL_BAS,
  pcMilieu: BASE + CHAMPS + LIGNES + RAIL_PC_MILIEU,
  // R10 b, variante C : dès 1800 px, la liste des offres reste quand le document est à côté (trois volets).
  troisVolets: BASE + CHAMPS + `@media (min-width: 1800px) and (hover: hover) and (pointer: fine) {
    body.doc-a-cote .colonne-liste { display: block; }
    body.doc-a-cote:has(.colonne-liste) .app { margin-left: calc(var(--menu) + var(--liste)); margin-right: var(--doc); } }`,
  _verif: { RAIL_MILIEU_OK: RAIL_MILIEU !== C.R2A, RAIL_BAS_OK: RAIL_BAS !== C.R2A, RAIL_PC_OK: RAIL_PC_MILIEU !== C.R10aB },
};
