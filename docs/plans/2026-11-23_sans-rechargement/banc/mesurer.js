// Banc : chaque geste au clic, tel qu'il est (variante « actuel »), puis le même envoi sans rechargement (« simulé »).
// Usage : node mesurer.js <froid|chaud> <profils séparés par des virgules> [filtre d'identifiant] [répétitions]
// La base est remise à l'instantané avant chaque mesure. Résultats : resultats.jsonl (une ligne par mesure).
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const scenarios = require('./scenarios');
const { BASE, ICI, navigateur, reseau, compteur } = require('./commun');

const [mode = 'froid', profilsTexte = 'local', filtre = '', repetitionsTexte = '1'] = process.argv.slice(2);
const profils = profilsTexte.split(',');
const repetitions = Number(repetitionsTexte) || 1;
const SORTIE = path.join(ICI, 'resultats.jsonl');

const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });

async function preparerPage(contexte, profil, s) {
  const page = await contexte.newPage();
  page.on('dialog', (d) => d.accept());
  await reseau(page, profil);
  await page.goto(BASE + s.depart, { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(300);
  if (s.preparer) {
    await s.preparer(page);
    await page.waitForTimeout(300);
  }
  return page;
}

async function actuel(contexte, profil, s) {
  const page = await preparerPage(contexte, profil, s);
  const origine = await page.evaluate(() => performance.timeOrigin);
  const c = compteur(page);
  c.debut();
  const t0 = Date.now();
  await s.faire(page);
  const duree = Date.now() - t0;
  const m = await c.fin();
  await page.waitForFunction((o) => performance.timeOrigin === o || performance.getEntriesByName('first-contentful-paint').length > 0, origine, { timeout: 5000 }).catch(() => {});
  const doc = await page.evaluate((o) => {
    if (performance.timeOrigin === o) return null;
    const n = performance.getEntriesByType('navigation')[0];
    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
    return n ? { ttfb: Math.round(n.responseStart), dcl: Math.round(n.domContentLoadedEventEnd), load: Math.round(n.loadEventEnd), fcp: fcp ? Math.round(fcp.startTime) : null } : null;
  }, origine).catch(() => null);
  await page.close();
  return { duree, requetes: m.requetes, octets: m.octets, detail: m.detail, document: doc };
}

async function simule(contexte, profil, s) {
  const page = await preparerPage(contexte, profil, s);
  const c = compteur(page);
  let poignee;
  let champ = null;
  if (s.simulerChamp) {
    poignee = await page.locator(s.simulerChamp.sel).first().elementHandle();
    champ = s.simulerChamp.valeur;
  } else {
    poignee = await page.locator(s.simuler).first().elementHandle();
  }
  c.debut();
  const r = await page.evaluate(async ({ el, champ, bouton }) => {
    let form = el;
    if (champ !== null) { form = el.form; el.value = champ; el.defaultValue = champ; }
    const donnees = new FormData(form);
    if (bouton === 'produit') { const b = form.querySelector('button[name=produit]'); donnees.set('produit', b.value); }
    if (bouton === 'oui') donnees.set('choix', 'oui');
    const t = performance.now();
    const rep = await fetch(form.action, { method: 'POST', body: new URLSearchParams(donnees), redirect: 'manual', credentials: 'same-origin' });
    await rep.text().catch(() => '');
    return { duree: Math.round(performance.now() - t), statut: rep.status, type: rep.type };
  }, { el: poignee, champ, bouton: s.bouton || null });
  const m = await c.fin();
  await page.close();
  return { duree: r.duree, statut: r.statut, type: r.type, requetes: m.requetes, octets: m.octets, detail: m.detail };
}

(async () => {
  // Cache gardé : seuls les gestes qui chargent une page entière changent ; l'envoi simulé est le même qu'à froid.
  // chaud-ids.txt (facultatif) : les gestes représentatifs à rejouer avec le cache gardé.
  const fichierChaud = path.join(ICI, 'chaud-ids.txt');
  const listeChaud = mode === 'chaud' && fs.existsSync(fichierChaud) ? fs.readFileSync(fichierChaud, 'utf8').split(/\s+/).filter(Boolean) : null;
  const choisis = scenarios.filter((s) => (!filtre || s.id.includes(filtre)) && (mode !== 'chaud' || /chargement\(/.test(s.faire.toString())) && (!listeChaud || listeChaud.includes(s.id)));
  console.log(`${choisis.length} gestes, profils ${profils.join(', ')}, cache ${mode}, ${repetitions} passe(s)`);
  for (const profil of profils) {
    const { browser, contexte } = await navigateur();
    // Cache gardé : le navigateur a déjà vu l'aperçu PDF (PDF.js et son worker en cache), comme après un premier usage.
    if (mode === 'chaud') {
      const p = await contexte.newPage();
      await p.goto(BASE + '/offres/2/apercu', { waitUntil: 'load' });
      await p.waitForSelector('[data-pdf] canvas', { timeout: 60000 }).catch(() => {});
      await p.close();
    }
    for (const s of choisis) {
      for (let n = 0; n < repetitions; n++) {
        for (const variante of ['actuel', 'simule']) {
          if (variante === 'simule' && (mode === 'chaud' || (!s.simuler && !s.simulerChamp))) continue;
          try {
            remettre();
            const r = variante === 'actuel' ? await actuel(contexte, profil, s) : await simule(contexte, profil, s);
            const ligne = { id: s.id, ecran: s.ecran, geste: s.geste, type: s.type, frequence: s.frequence, profil, cache: mode, variante, passe: n + 1, ...r, quand: new Date().toISOString() };
            fs.appendFileSync(SORTIE, JSON.stringify(ligne) + '\n');
            console.log(`${profil.padEnd(8)} ${variante.padEnd(7)} ${s.id.padEnd(22)} ${String(r.duree).padStart(6)} ms ${String(r.requetes).padStart(3)} req ${String(Math.round(r.octets / 1024)).padStart(5)} Ko${r.document ? ' fcp ' + r.document.fcp + ' dcl ' + r.document.dcl : ''}${r.statut !== undefined ? ' statut ' + r.statut : ''}`);
          } catch (e) {
            console.log(`${profil.padEnd(8)} ${variante.padEnd(7)} ${s.id.padEnd(22)} ÉCHEC ${String(e.message).split('\n')[0].slice(0, 160)}`);
            fs.appendFileSync(SORTIE, JSON.stringify({ id: s.id, profil, cache: mode, variante, echec: String(e.message).slice(0, 300), quand: new Date().toISOString() }) + '\n');
            for (const p of contexte.pages()) await p.close().catch(() => {});
          }
        }
      }
    }
    await browser.close();
  }
})().catch((e) => { console.error(e); process.exit(1); });
