// Correctifs proposés, injectés dans la page du site de test (page.addStyleTag) : rien n'est écrit sur le serveur.
// Une feuille par point ; chaque variante se montre seule par-dessus la version déployée.
const PAS_PC = 'not all and (min-width: 1024px) and (hover: hover) and (pointer: fine)';
const COMPACT = `
  .rows { --cols: 86px 60px 68px minmax(48px, 1fr); }
  .rows.une .row { row-gap: 4px; }
  .rows.une .row > .nm { grid-column: 1 / -1; padding: 10px 0 0; min-height: 0; }
  .rows.une .cat h5 { grid-column: 1 / -1; }
  .rows.une .cat .ch { text-align: left; }
  .rows.une .row .q { justify-content: flex-start; }
  .rows.une .row .tt, .rows.une .cat .ch:nth-child(4) { text-align: left; padding-left: 8px; }
  .rows.une .row .del { justify-self: end; margin-left: 0; }`;
const RAIL = `
  .app { margin-left: 80px; max-width: none; }
  .tabs:not(.pc-seul) { position: fixed; left: 0; top: 0; bottom: 0; width: 80px; z-index: 6; flex-direction: column; justify-content: center; gap: 2px; border-top: 0; border-right: 1px solid var(--line); padding: 4px 0 4px env(safe-area-inset-left); }
  .tabs:not(.pc-seul) a { flex: none; min-height: 56px; justify-content: center; font-size: 11px; padding: 4px 2px; }
  .tabs:not(.pc-seul) .micro-central { flex: none; min-height: 68px; order: -1; margin-bottom: 6px; }
  .tabs:not(.pc-seul) .micro-central > span { position: static; transform: none; margin: 0 auto; width: 60px; height: 60px; border: 0; }
  .bas:has(.tabs:not(.pc-seul)) { display: flex; align-items: center; border-top: 1px solid var(--line); }
  .bas .totalbar { border-top: 0; padding: 0 4px 0 20px; flex: none; gap: 12px; }
  .bas .totalbar + .actionbar, .bas:has(.tabs:not(.pc-seul)) .actionbar { flex: 1; border-top: 0; padding: 8px 16px; }`;

module.exports = {
  R1A: `@media ${PAS_PC} { .bas:has(.tabs:not(.pc-seul)) .actionbar { padding-bottom: 34px; } }`,
  R1B: `@media ${PAS_PC} {
    .tabs .micro-central > span { width: 64px; height: 64px; top: -12px; }
    .tabs .micro-central svg { width: 28px; height: 28px; }
    .bas:has(.tabs:not(.pc-seul)) .actionbar { padding-bottom: 20px; } }`,
  R2A: `@media (orientation: landscape) and (max-height: 500px) and (pointer: coarse) {
    ${RAIL}
    .bar { min-height: 44px; padding: 2px 20px; }
    .bar .logo { height: 24px; }
    .actionbar .btn.grand, .actionbar .btn.carre { min-height: 52px; }
    .actionbar .btn.carre { flex-basis: 52px; }
    .bas > :last-child { padding-bottom: 0; } }`,
  R2B: `@media (orientation: landscape) and (max-height: 500px) and (pointer: coarse) {
    body::after { content: "Tournez le téléphone pour dicter et corriger vos offres."; position: fixed; inset: 0; z-index: 99; display: grid; place-items: center; padding: 40px; text-align: center; font: 600 20px/1.4 var(--titre); background: var(--bande); color: var(--on-bande); } }`,
  R3: `@media (min-width: 420px) and (max-width: 699px), (min-width: 420px) and (max-width: 1023px) and (not (pointer: coarse)) { ${COMPACT} }`,
  R4: `.duo { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } .duo > * { min-width: 0; } .champ input, .champ select { min-width: 0; }`,
  R5: `.saisie { font-size: 16px; } .q .saisie { width: 52px; }
    @media (max-width: 419px) { .rows { --cols: 94px 76px 72px minmax(48px, 1fr); } }`,
  R6: `@media (min-width: 700px) and (pointer: coarse) {
    .screen:has(.champ):not(:has(.facturer)) > * { max-width: 720px; margin-left: auto; margin-right: auto; }
    .app:has(.screen .champ):not(:has(.facturer)) .actionbar { padding-left: max(28px, calc((100% - 720px) / 2)); padding-right: max(28px, calc((100% - 720px) / 2)); } }`,
  R7: `.duo { align-items: end; }`,
  R8: `.fchips { -webkit-mask-image: linear-gradient(90deg, #000 calc(100% - 44px), transparent); mask-image: linear-gradient(90deg, #000 calc(100% - 44px), transparent); padding-right: 44px; }
    @media (min-width: 700px) and (pointer: coarse), (min-width: 1024px) and (hover: hover) and (pointer: fine) {
      .fchips { flex-wrap: wrap; overflow: visible; -webkit-mask-image: none; mask-image: none; padding-right: 0; } }`,
  R9: `@media (max-width: 419px) { .rows.une .row > .nm { min-height: 48px; padding: 12px 0 4px; } }`,
  R10aB: `@media (min-width: 700px) and (max-width: 1023px) and (hover: hover) and (pointer: fine) {
    ${RAIL}
    .rows { --cols: minmax(0, 1fr) 96px 104px 112px 52px; }
    .screen { padding: 20px 28px 32px; } }`,
  R10bB: `@media (min-width: 1600px) and (hover: hover) and (pointer: fine) {
    body:not(.doc-a-cote):has(.tabs) .app { margin-left: calc(var(--menu) + (100vw - var(--menu) - 1040px) / 2); }
    body:not(.doc-a-cote):has(.colonne-liste) .app { margin-left: calc(var(--menu) + var(--liste) + (100vw - var(--menu) - var(--liste) - 1000px) / 2); } }`,
};
