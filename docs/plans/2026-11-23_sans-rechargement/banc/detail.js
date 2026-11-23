// Détail d'une mesure, avant et après : node detail.js <id> <profil>
'use strict';
const fs = require('fs');
const [id, profil = 'local'] = process.argv.slice(2);
const lire = (f) => fs.readFileSync(f, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
for (const [nom, f] of [['avant', 'resultats.jsonl'], ['apres', 'apres-resultats.jsonl']]) {
  const r = lire(f).filter((x) => x.id === id && x.profil === profil && (x.variante === 'actuel' || x.variante === 'apres') && x.cache !== 'chaud');
  for (const x of r) {
    console.log(nom, x.cache, 'durée/vu', x.duree || x.vu, 'requêtes', x.requetes, 'octets', x.octets);
    const groupes = {};
    for (const d of x.detail || []) {
      const cle = d.url.replace(/\?v=[0-9a-f]+/, '').replace(/[0-9]+/g, 'N').slice(0, 60);
      groupes[cle] = groupes[cle] || { n: 0, o: 0, sw: 0 };
      groupes[cle].n++; groupes[cle].o += d.octets; if (d.parSw) groupes[cle].sw++;
    }
    Object.entries(groupes).sort((a, b) => b[1].o - a[1].o).slice(0, 12).forEach(([k, v]) => console.log('   ', String(v.n).padStart(3), String(Math.round(v.o / 1024)).padStart(5), 'Ko', v.sw ? '(' + v.sw + ' par le sw)' : '', k));
  }
}
