// Comparatif avant / après du chantier « sans rechargement » (2026-11-23). Avant : resultats.jsonl (audit, cache périmé,
// la situation du site de test). Après : apres-resultats.jsonl (lots 1 à 5 et revue ; banc corrigé : le service worker
// reçoit le même bridage de réseau que la page). Même calcul du temps affiché pour un geste qui ouvre une page :
// durée - (fin du chargement - premier affichage). Sortie : comparatif.json ({ lignes, resume }) et un résumé console.
'use strict';
const fs = require('fs');
const path = require('path');
const scenarios = require('./scenarios');
const inventaire = require('./inventaire');

const lire = (f) => fs.readFileSync(path.join(__dirname, f), 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l)).filter((x) => !x.echec);
const avant = lire('resultats.jsonl');
const apres = lire('apres-resultats.jsonl');
const PROFILS = ['local', 'fibre', '4g', 'chantier'];
const vuDe = (duree, doc) => (doc && doc.fcp !== null && doc.load ? Math.max(0, Math.round(duree - Math.max(0, doc.load - doc.fcp))) : duree);
// La dernière mesure d'un geste fait foi (les reprises remplacent les premières).
const derniere = (liste, filtre) => liste.filter(filtre).sort((a, b) => String(a.quand).localeCompare(String(b.quand))).pop();
const lieDe = (id) => inventaire.find((g) => g.mesure === id) || null;
const mesureApres = (p) => (p ? { vu: vuDe(p.vu, p.document), tout: p.confirme, requetes: p.requetes, octets: p.octets, recharge: !!p.document || !!p.recharge, nav: !!p.document, sw: p.swBrides } : null);

const lignes = [];
for (const s of scenarios) {
  for (const profil of PROFILS) {
    const a = derniere(avant, (x) => x.id === s.id && x.profil === profil && x.cache === 'froid' && x.variante === 'actuel');
    const ac = derniere(avant, (x) => x.id === s.id && x.profil === profil && x.cache === 'chaud' && x.variante === 'actuel');
    const as = derniere(avant, (x) => x.id === s.id && x.profil === profil && x.cache === 'froid' && x.variante === 'simule');
    // « Générer » : avant, chaque ouverture refaisait le PDF ; le cas qui se compare est donc l'offre modifiée juste avant
    // (PDF refait). Le PDF déjà vu (gardé par le serveur et le téléphone) est rendu à part.
    const p = derniere(apres, (x) => x.id === (s.id === 'generer' ? 'generer-modifiee' : s.id) && x.profil === profil);
    const pg = s.id === 'generer' ? derniere(apres, (x) => x.id === 'generer' && x.profil === profil) : null;
    if (!a && !p) continue;
    const g = lieDe(s.id);
    lignes.push({
      id: s.id, ecran: s.ecran, geste: s.geste, type: s.type, frequence: s.frequence, profil,
      lot: g ? g.lot : null, utile: g ? g.utile : null, pourquoi: g ? g.pourquoi : null, propose: g ? g.propose : null,
      avant: a ? { vu: vuDe(a.duree, a.document), tout: a.duree, requetes: a.requetes, octets: a.octets, recharge: !!a.document } : null,
      avant_chaud: ac ? { vu: vuDe(ac.duree, ac.document), tout: ac.duree, requetes: ac.requetes, octets: ac.octets } : null,
      simule: as ? { tout: as.duree, octets: as.octets } : null,
      apres: mesureApres(p),
      apres_pdf_garde: pg ? mesureApres(pg) : undefined,
    });
  }
}

const mediane = (v) => { const t = v.filter((x) => x !== null && x !== undefined).sort((x, y) => x - y); return t.length ? t[Math.floor((t.length - 1) / 2)] : null; };
const somme = (v) => v.reduce((s, x) => s + (x || 0), 0);
const resumeDe = (l) => ({
  gestes: l.length,
  vu: [mediane(l.map((x) => x.avant.vu)), mediane(l.map((x) => x.apres.vu))],
  tout: [mediane(l.map((x) => x.avant.tout)), mediane(l.map((x) => x.apres.tout))],
  octets: [somme(l.map((x) => x.avant.octets)), somme(l.map((x) => x.apres.octets))],
  requetes: [somme(l.map((x) => x.avant.requetes)), somme(l.map((x) => x.apres.requetes))],
  recharges: [l.filter((x) => x.avant.recharge).length, l.filter((x) => x.apres.recharge).length],
  sansSw: l.filter((x) => x.profil !== 'local' && !x.apres.sw).map((x) => x.id),
});
const resume = {};
for (const profil of PROFILS) {
  const l = lignes.filter((x) => x.profil === profil && x.avant && x.apres);
  resume[profil] = { tous: resumeDe(l), tresFrequents: resumeDe(l.filter((x) => x.frequence === 'très fréquent')) };
}
fs.writeFileSync(path.join(__dirname, 'comparatif.json'), JSON.stringify({ lignes, resume }, null, 1));

console.log('réseau     gestes  vu avant  vu après  tout avant  tout après  Ko avant  Ko après  rechargés avant  après   (très fréquents : vu avant, après)');
for (const profil of PROFILS) {
  const r = resume[profil].tous;
  const f = resume[profil].tresFrequents;
  console.log(profil.padEnd(10), String(r.gestes).padStart(6), String(r.vu[0]).padStart(9), String(r.vu[1]).padStart(9),
    String(r.tout[0]).padStart(11), String(r.tout[1]).padStart(11), String(Math.round(r.octets[0] / 1024)).padStart(9), String(Math.round(r.octets[1] / 1024)).padStart(9),
    String(r.recharges[0]).padStart(16), String(r.recharges[1]).padStart(6), '  ', f.gestes, 'gestes :', f.vu[0], '->', f.vu[1]);
  if (r.sansSw.length) console.log('   ALERTE : mesures sans service worker bridé :', r.sansSw.join(', '));
}
for (const profil of ['chantier']) {
  console.log('\n' + profil + ' : geste, vu avant -> après (ms), tout avant -> après, Ko avant -> après');
  lignes.filter((x) => x.profil === profil && x.avant && x.apres).forEach((x) => console.log('  ' + x.id.padEnd(22),
    String(x.avant.vu).padStart(6), '->', String(x.apres.vu).padStart(6), ' ', String(x.avant.tout).padStart(6), '->', String(x.apres.tout).padStart(6), ' ',
    String(Math.round(x.avant.octets / 102.4) / 10).padStart(6), '->', String(Math.round(x.apres.octets / 102.4) / 10).padStart(5), x.apres.recharge ? ' (page)' : ''));
}
