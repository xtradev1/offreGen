#!/bin/sh
# Après la campagne : « Générer » et « Créer la facture » rejoués cache gardé avec PDF.js déjà vu, puis coupures de
# réseau, images de la maquette, poids du PDF, dictées. Journal : suite.log.
cd "$(dirname "$0")"
until grep -q "CAMPAGNE FINIE" campagne.log; do sleep 10; done
node -e "
const fs = require('fs');
const l = fs.readFileSync('resultats.jsonl', 'utf8').split('\n').filter(Boolean);
const garde = l.filter((x) => { const r = JSON.parse(x); return !(r.cache === 'chaud' && (r.id === 'generer' || r.id === 'facturer-creer')); });
fs.writeFileSync('resultats.jsonl', garde.join('\n') + '\n');
console.log('lignes retirées :', l.length - garde.length);
"
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh chaud
node mesurer.js chaud fibre,4g,chantier generer
node mesurer.js chaud fibre,4g,chantier facturer-creer
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh froid
echo "--- hors ligne"; node hors-ligne.js
echo "--- démo"; node demo.js
echo "--- après"; node apres.js
echo "--- poids du PDF"; node poids-pdf.js
echo "--- dictée fibre"; node dicter.js fibre
echo "--- dictée chantier"; node dicter.js chantier
echo "SUITE FINIE"
