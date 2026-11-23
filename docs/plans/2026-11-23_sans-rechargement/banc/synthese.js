// Synthèse de resultats.jsonl : une ligne par geste, médiane par profil et par variante. Écrit synthese.json et
// synthese.md (tableaux de l'audit). Usage : node synthese.js
'use strict';
const fs = require('fs');
const path = require('path');
const scenarios = require('./scenarios');

const ICI = __dirname;
const lignes = fs.readFileSync(path.join(ICI, 'resultats.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
const PROFILS = ['local', 'fibre', '4g', 'chantier'];
const mediane = (v) => { const s = [...v].sort((a, b) => a - b); return s.length ? s[Math.floor((s.length - 1) / 2)] : null; };
const ko = (o) => (o === null || o === undefined ? '' : (o / 1024).toFixed(o < 10240 ? 1 : 0).replace('.', ','));
const s = (ms) => (ms === null || ms === undefined ? '' : ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(1).replace('.', ',')} s`);

const mesure = (id, profil, cache, variante) => {
  const l = lignes.filter((x) => x.id === id && x.profil === profil && x.cache === cache && x.variante === variante && !x.echec);
  if (!l.length) return null;
  // « vu » : du toucher au premier affichage de la nouvelle page. Le premier affichage (fcp) et la fin du chargement
  // (load) se comptent depuis le début de la navigation, qui peut venir après le toucher (fenêtre, puis rechargement) :
  // vu = durée totale - (load - fcp).
  const vu = (x) => (x.document && x.document.fcp !== null && x.document.load > 0 ? Math.max(0, x.duree - (x.document.load - x.document.fcp)) : x.duree);
  return {
    vu: mediane(l.map(vu)),
    duree: mediane(l.map((x) => x.duree)), requetes: mediane(l.map((x) => x.requetes)), octets: mediane(l.map((x) => x.octets)),
    fcp: mediane(l.filter((x) => x.document && x.document.fcp !== null).map((x) => x.document.fcp)),
    html: mediane(l.map((x) => (x.detail || []).filter((d) => d.type === 'document' || d.type === 'fetch' || d.type === 'xhr').reduce((t, d) => t + d.octets, 0))),
    statique: mediane(l.map((x) => (x.detail || []).filter((d) => ['stylesheet', 'script', 'font', 'image', 'other'].includes(d.type)).reduce((t, d) => t + d.octets, 0))),
    serveur: mediane(l.map((x) => (x.detail || []).filter((d) => d.methode === 'POST' || d.type === 'document').reduce((t, d) => t + (d.attente || 0), 0))),
    n: l.length,
  };
};

const sortie = scenarios.map((sc) => {
  const r = { id: sc.id, ecran: sc.ecran, geste: sc.geste, type: sc.type, frequence: sc.frequence, profils: {} };
  PROFILS.forEach((p) => {
    r.profils[p] = { froid: mesure(sc.id, p, 'froid', 'actuel'), chaud: mesure(sc.id, p, 'chaud', 'actuel'), simule: mesure(sc.id, p, 'froid', 'simule') || mesure(sc.id, p, 'chaud', 'simule') };
  });
  return r;
});
const echecs = lignes.filter((x) => x.echec);
fs.writeFileSync(path.join(ICI, 'synthese.json'), JSON.stringify({ quand: new Date().toISOString(), mesures: lignes.length, echecs: echecs.length, gestes: sortie }, null, 1));

const md = [];
md.push(`Mesures : ${lignes.length}, échecs : ${echecs.length}.`, '');
md.push('| Geste | Type | Fréq. | Profil | Aujourd\'hui, cache périmé | Aujourd\'hui, cache gardé | Sans rechargement (envoi seul) |');
md.push('|---|---|---|---|---|---|---|');
sortie.forEach((g) => {
  PROFILS.forEach((p, i) => {
    const x = g.profils[p];
    const f = x.froid ? `${s(x.froid.duree)}, ${x.froid.requetes} req., ${ko(x.froid.octets)} Ko` : '';
    const c = x.chaud ? `${s(x.chaud.duree)}, ${x.chaud.requetes} req., ${ko(x.chaud.octets)} Ko` : '';
    const a = x.simule ? `${s(x.simule.duree)}, ${x.simule.requetes} req., ${ko(x.simule.octets)} Ko` : '';
    md.push(`| ${i === 0 ? `${g.ecran} : ${g.geste}` : ''} | ${i === 0 ? g.type : ''} | ${i === 0 ? g.frequence : ''} | ${p} | ${f} | ${c} | ${a} |`);
  });
});
fs.writeFileSync(path.join(ICI, 'synthese.md'), md.join('\n') + '\n');
console.log(md.slice(0, 3).join('\n'));
console.log(`${sortie.length} gestes, ${lignes.length} mesures, ${echecs.length} échecs`);
