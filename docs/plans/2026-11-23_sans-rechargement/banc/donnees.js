// Croise l'inventaire (inventaire.js) et les mesures (synthese.json) : une ligne par geste, chiffres prêts à afficher.
'use strict';
const fs = require('fs');
const path = require('path');
const inventaire = require('./inventaire');

const synthese = JSON.parse(fs.readFileSync(path.join(__dirname, 'synthese.json'), 'utf8'));
const parId = Object.fromEntries(synthese.gestes.map((g) => [g.id, g]));

const NBSP = ' ';
const temps = (ms) => (ms === null || ms === undefined ? null : ms < 1000 ? `${ms}${NBSP}ms` : `${(ms / 1000).toFixed(1).replace('.', ',')}${NBSP}s`);
const ko = (o) => (o === null || o === undefined ? null : `${(o / 1024).toFixed(o < 10240 ? 1 : 0).replace('.', ',')}${NBSP}Ko`);
const LIBELLES = { R: 'Page rechargée', F: 'Sans rechargement, mais l\'offre entière revient', J: 'Sans rechargement, réponse légère', N: 'Changement d\'écran', S: 'Fenêtre chargée par fetch', C: 'Dans le navigateur, sans réseau', X: 'Envoi en arrière-plan' };
const LOTS = { 0: 'Garder', 1: 'Lot 1, socle', 2: 'Lot 2, offre', 3: 'Lot 3, ajouter et catalogue', 4: 'Lot 4, hors réseau', 5: 'Lot 5, PDF et envoi' };

// Le banc compte 346 octets pour le worker de PDF.js, chargé par le worker lui-même : le fichier pèse 379 226 octets
// compressés (gzip -6 de public/js/vendor/pdfjs-6.5.438/pdf.worker.min.js, mesuré le 2026-11-23). Corrigé ici quand le
// cache est périmé (le fichier est alors retéléchargé), pour « Générer l'offre » et « Créer la facture ».
const WORKER_PDF = 379226 - 346;

function chiffres(id) {
  const g = parId[id];
  if (!g) return null;
  const corriger = (m, mode) => (m && mode === 'froid' && (id === 'generer' || id === 'facturer-creer') ? { ...m, octets: m.octets + WORKER_PDF } : m);
  const out = {};
  ['local', 'fibre', '4g', 'chantier'].forEach((p) => {
    const x = g.profils[p] || {};
    // « vu » : la nouvelle page affichée (premier affichage) quand le geste charge une page, sinon la durée du geste.
    out[p] = {
      froid: x.froid ? corriger({ duree: x.froid.duree, vu: x.froid.vu, requetes: x.froid.requetes, octets: x.froid.octets, fcp: x.froid.fcp }, 'froid') : null,
      chaud: x.chaud ? { duree: x.chaud.duree, vu: x.chaud.vu, requetes: x.chaud.requetes, octets: x.chaud.octets, fcp: x.chaud.fcp } : null,
      simule: x.simule ? { duree: x.simule.duree, requetes: x.simule.requetes, octets: x.simule.octets } : null,
    };
  });
  return out;
}

// inutile : rechargement à retirer ; lourd : déjà sans rechargement, réponse à alléger ; utile : rechargement qui sert ;
// peu : rechargement gardé, geste rare ; bien : rien à changer.
const categorie = (x) => (x.utile === 'oui' ? 'utile' : (x.aujourdhui === 'R' || x.aujourdhui === 'N') ? (x.lot >= 2 ? 'inutile' : 'peu') : x.lot >= 2 ? 'lourd' : 'bien');

const gestes = inventaire.map((g, i) => ({ n: i + 1, ...g, categorie: categorie(g), libelle: LIBELLES[g.aujourdhui], nomLot: LOTS[g.lot], chiffres: g.mesure ? chiffres(g.mesure) : null }));

module.exports = { gestes, temps, ko, LIBELLES, LOTS, synthese };
