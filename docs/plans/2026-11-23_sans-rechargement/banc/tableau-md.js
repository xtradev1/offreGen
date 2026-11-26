// Tableau Markdown de tous les gestes (pour AUDIT.md), regroupé par écran. Usage : node tableau-md.js > tableau.md
'use strict';
const { gestes, temps, ko } = require('./donnees');

const cellule = (c, p) => {
  if (!c || !c[p]) return '';
  const x = c[p];
  const a = x.froid ? `${temps(x.froid.duree)} (${x.froid.requetes} req., ${ko(x.froid.octets)})` : '';
  const b = x.simule ? `${temps(x.simule.duree)} (${x.simule.requetes} req., ${ko(x.simule.octets)})` : '';
  const k = x.chaud ? `${temps(x.chaud.duree)} (${ko(x.chaud.octets)})` : '';
  return [a, k ? `cache gardé : ${k}` : '', b ? `sans rechargement : ${b}` : ''].filter(Boolean).join('<br>');
};

const lignes = [];
let ecran = null;
lignes.push('| n° | Écran | Geste | Aujourd\'hui | Rechargement utile ? | 4G | Chantier | Proposé | Lot |');
lignes.push('|---|---|---|---|---|---|---|---|---|');
gestes.forEach((g) => {
  lignes.push(`| ${g.n} | ${g.ecran === ecran ? '' : g.ecran} | ${g.geste} | ${g.libelle} | ${g.utile === 'oui' ? 'Oui' : 'Non'} : ${g.pourquoi} | ${cellule(g.chiffres, '4g')} | ${cellule(g.chiffres, 'chantier')} | ${g.propose} | ${g.nomLot} |`);
  ecran = g.ecran;
});
console.log(lignes.join('\n'));
