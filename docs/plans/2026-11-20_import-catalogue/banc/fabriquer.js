// Fabrique les PDF des offres inventées (mises en page classique, tableur, liste) avec l'impression de Chromium.
// La mise en page « colonnes » est faite par fabriquer_colonnes.py (texte posé colonne par colonne).
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const fs = require('fs');
const path = require('path');
const { offres } = JSON.parse(fs.readFileSync(path.join(__dirname, 'offres.json'), 'utf8'));
const SORTIE = path.join(__dirname, '..', 'corpus', 'inventees');
fs.mkdirSync(SORTIE, { recursive: true });

const chf = (n) => n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
const qte = (n) => String(n).replace('.', ',');
const total = (o) => o.sections.reduce((s, sec) => s + sec.lignes.reduce((t, l) => t + l.qte * l.pu, 0), 0);

function pied(o) {
  const brut = total(o), rabais = o.rabais ? brut * o.rabais.pourcent / 100 : 0, net = brut - rabais, tva = net * o.tva / 100;
  let h = `<table class="tot"><tr><td>Total brut</td><td>${chf(brut)}</td></tr>`;
  if (o.rabais) h += `<tr><td>${o.rabais.libelle} ${o.rabais.pourcent} %</td><td>-${chf(rabais)}</td></tr><tr><td>Total net hors taxe</td><td>${chf(net)}</td></tr>`;
  h += `<tr><td>TVA ${String(o.tva).replace('.', ',')} %</td><td>${chf(tva)}</td></tr><tr class="g"><td>Total TTC, CHF</td><td>${chf(net + tva)}</td></tr></table><p class="pied">${o.pied}</p>`;
  return h;
}

const MISES = {
  classique(o) {
    let h = `<table class="l"><tr><th>Pos.</th><th>Désignation</th><th class="d">Qté</th><th>Unité</th><th class="d">Prix unitaire</th><th class="d">Total CHF</th></tr>`;
    for (const sec of o.sections) {
      h += `<tr class="sec"><td colspan="6">${sec.titre}</td></tr>`;
      for (const l of sec.lignes) h += `<tr><td>${l.pos}</td><td>${l.des}</td><td class="d">${qte(l.qte)}</td><td>${l.u}</td><td class="d">${chf(l.pu)}</td><td class="d">${chf(l.qte * l.pu)}</td></tr>`;
      h += `<tr class="st"><td colspan="5">Sous-total ${sec.titre.toLowerCase()}</td><td class="d">${chf(sec.lignes.reduce((t, l) => t + l.qte * l.pu, 0))}</td></tr>`;
    }
    return h + '</table>';
  },
  tableur(o) {
    let h = `<table class="l grille"><tr><th class="d">Qté</th><th>Un.</th><th>Libellé</th><th class="d">P.U.</th><th class="d">Montant</th></tr>`;
    for (const l of o.sections[0].lignes) h += `<tr><td class="d">${qte(l.qte)}</td><td>${l.u}</td><td>${l.des}</td><td class="d">${chf(l.pu)}</td><td class="d">${chf(l.qte * l.pu)}</td></tr>`;
    return h + '</table>';
  },
  liste(o) {
    let h = '';
    for (const sec of o.sections) {
      h += `<h3>${sec.titre}</h3>`;
      for (const l of sec.lignes) {
        const prix = Number.isInteger(l.pu) ? `${l.pu}.-` : chf(l.pu);
        const detail = l.u === 'forfait' ? `forfait` : `${qte(l.qte)} ${l.u} à CHF ${prix}`;
        h += `<p class="li"><span>${l.des}<br><i>${detail}</i></span><b>CHF ${chf(l.qte * l.pu)}</b></p>`;
      }
    }
    return h;
  },
};

const CSS = `body{font:10.5pt/1.4 Arial,Helvetica,sans-serif;color:#111;margin:0}
.e{display:flex;justify-content:space-between;gap:30px;margin-bottom:26px}.e div{max-width:45%}
h1{font-size:14pt;margin:0 0 4px}h3{font-size:11pt;margin:16px 0 4px;border-bottom:1px solid #999}
table.l{width:100%;border-collapse:collapse;margin-top:12px}table.l th{text-align:left;border-bottom:1.5px solid #111;padding:4px 6px;font-size:9.5pt}
table.l td{padding:4px 6px;border-bottom:.5px solid #ccc;vertical-align:top}.d{text-align:right}
table.grille td,table.grille th{border:.5px solid #777}
tr.sec td{font-weight:bold;background:#eee}tr.st td{font-style:italic;border-bottom:1px solid #111}
table.tot{margin:18px 0 0 auto;border-collapse:collapse}table.tot td{padding:3px 8px;text-align:right}tr.g td{font-weight:bold;border-top:1.5px solid #111}
.pied{margin-top:26px;font-size:9.5pt}.li{display:flex;justify-content:space-between;gap:20px;margin:6px 0}.li i{color:#444}`;

(async () => {
  const nav = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const page = await nav.newPage();
  for (const o of offres) {
    if (!MISES[o.mise_en_page]) continue;
    const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>${CSS}</style></head><body>
      <div class="e"><div><b>${o.entreprise.split(',')[0]}</b><br>${o.entreprise.split(',').slice(1).join(',')}</div><div>${o.client.replace(/, /g, '<br>')}</div></div>
      <h1>${o.titre}</h1><p>${o.date}</p>${MISES[o.mise_en_page](o)}${pied(o)}</body></html>`;
    await page.setContent(html);
    const cible = path.join(SORTIE, o.id + '.pdf');
    await page.pdf({ path: cible, format: 'A4', margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' } });
    console.log('fabriqué :', path.basename(cible), fs.statSync(cible).size, 'octets');
  }
  await nav.close();
})();
