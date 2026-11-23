// Bloc « Preuves » de ETAT.md : sorties du banc collées telles quelles (extraits choisis, pas de résumé).
// Usage : node preuves.js <ETAT.md> [sortie de check.js]
'use strict';
const fs = require('fs');
const path = require('path');

const [etat, controle] = process.argv.slice(2);
const ICI = __dirname;
const lignes = (f) => fs.readFileSync(path.join(ICI, f), 'utf8').split('\n');
const ids = /\b(offre-juste|offre-corbeille|liste-ouvrir-offre|client-offre|ajout-ouvrir|ajout-produit|ajout-termine|offre-quantite|generer|facturer-creer|liste-archiver)\s/;

const campagne = lignes('campagne.log');
const debutChaud = campagne.findIndex((l) => /cache chaud/.test(l));
const froid = campagne.slice(0, debutChaud);
const chaud = campagne.slice(debutChaud);

const blocFroid = [
  '$ sh banc/campagne.sh   (puis banc/suite.sh et banc/reprise.sh)',
  ...froid.filter((l) => /gestes, profils/.test(l)),
  ...froid.filter((l) => /^(4g|chantier)\s/.test(l) && ids.test(l) && !/liste-archiver/.test(l)),
  '# liste-archiver rejoué par banc/reprise.sh (glissé réel) :',
  ...lignes('reprise.log').filter((l) => /^(4g|chantier)\s/.test(l) && /liste-archiver/.test(l)),
];
const blocChaud = [
  ...chaud.filter((l) => /gestes, profils/.test(l)),
  ...chaud.filter((l) => /^chantier\s/.test(l) && !/generer|facturer-creer/.test(l)),
  '# generer et facturer-creer rejoués par banc/suite.sh après un premier aperçu (PDF.js en cache) :',
  ...lignes('suite.log').filter((l) => /^chantier\s+actuel\s+(generer|facturer-creer)/.test(l)),
];
const sortie = [
  '### Phase 1 : campagne du banc (extraits, cache périmé)', '', '```', ...blocFroid, '```', '',
  '### Phase 1 : cache gardé, réseau de chantier', '', '```', ...blocChaud, '```', '',
  '### Phase 1 : coupures et lenteur (`banc/hors-ligne.json`)', '', '```json', fs.readFileSync(path.join(ICI, 'hors-ligne.json'), 'utf8').trim(), '```', '',
  '### Phase 1 : dictées réelles (`banc/dictees.jsonl`, sans le détail des requêtes)', '', '```',
  ...fs.readFileSync(path.join(ICI, 'dictees.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => { const d = JSON.parse(l); delete d.detail; return JSON.stringify(d); }),
  '```',
];
if (controle && fs.existsSync(controle)) sortie.push('', '### Phase 3 : contrôle de la maquette (`check.js`)', '', '```', fs.readFileSync(controle, 'utf8').trim(), '```');

const texte = fs.readFileSync(etat, 'utf8');
if (!texte.includes('@@PREUVES@@')) throw new Error('marqueur @@PREUVES@@ absent');
fs.writeFileSync(etat, texte.replace('@@PREUVES@@', sortie.join('\n')));
console.log('preuves :', sortie.length, 'lignes');
