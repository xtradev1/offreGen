#!/bin/sh
# Reprise du 2026-11-23 : « Archiver » et « Supprimer » de la liste avaient été mesurés avec un clic forcé qui ouvrait
# l'offre ; rejoués avec le vrai glissé. Puis les cas de double toucher de hors-ligne.js, réécrits. Journal : reprise.log.
cd "$(dirname "$0")"
until grep -q "SUITE FINIE" suite.log; do sleep 10; done
node -e "
const fs = require('fs');
const l = fs.readFileSync('resultats.jsonl', 'utf8').split('\n').filter(Boolean);
const garde = l.filter((x) => { const r = JSON.parse(x); return !(r.id === 'liste-archiver' || r.id === 'liste-supprimer'); });
fs.writeFileSync('resultats.jsonl', garde.join('\n') + '\n');
console.log('lignes retirées :', l.length - garde.length);
"
node mesurer.js froid local,fibre,4g,chantier liste-archiver
node mesurer.js froid local,fibre,4g,chantier liste-supprimer
echo "--- hors ligne (reprise)"; node hors-ligne.js
echo "REPRISE FINIE"
