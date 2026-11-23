// Banc d'après (lots 1 à 5) : chaque geste de apres-scenarios.js, mêmes profils que le banc d'avant, serveur en mode
// « apres » (règles du public/.htaccess du lot 1). Usage : node apres-mesurer.js <profils séparés par des virgules>
// [filtre d'identifiant]. Résultats ajoutés à apres-resultats.jsonl, une ligne par mesure.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const scenarios = require('./apres-scenarios');
const { BASE, ICI, navigateur, reseau, briderServiceWorkers } = require('./commun');

const [profilsTexte = 'local', filtre = ''] = process.argv.slice(2);
const SORTIE = path.join(ICI, 'apres-resultats.jsonl');
const remettre = () => execSync('wsl -d Ubuntu -u root -e sh /home/xavie/offreGen-fluidite-banc/base.sh remettre', { stdio: 'pipe' });
const calme = (page) => page.waitForFunction(() => !window.fluide || !window.fluide.occupe(), null, { timeout: 60000 }).catch(() => {});

// Le réseau réel du contexte (pages et service worker) : une requête de la page servie par le service worker ne touche
// pas le réseau (0 octet, pas comptée) ; celle que le service worker envoie au serveur compte. Une page qui passe par lui
// et va au serveur compte donc une fois.
function compteurReseau(contexte) {
  let actif = false;
  let liste = [];
  const enCours = new Set();
  contexte.on('request', (r) => { if (actif) enCours.add(r); });
  const finir = async (r, echec) => {
    if (!enCours.has(r)) return;
    enCours.delete(r);
    let octets = 0;
    let parSw = false;
    try {
      const rep = await r.response();
      parSw = !!(rep && rep.fromServiceWorker());
      const s = await r.sizes();
      octets = s.responseBodySize + s.responseHeadersSize;
    } catch (e) { /* requête annulée */ }
    liste.push({ url: r.url().replace(BASE, ''), methode: r.method(), octets: parSw ? 0 : octets, parSw, sw: !!r.serviceWorker(), echec: !!echec });
  };
  contexte.on('requestfinished', (r) => finir(r, false));
  contexte.on('requestfailed', (r) => finir(r, true));
  return {
    debut() { actif = true; liste = []; enCours.clear(); },
    async fin() {
      await new Promise((ok) => setTimeout(ok, 300));
      const t0 = Date.now();
      while (enCours.size > 0 && Date.now() - t0 < 30000) await new Promise((ok) => setTimeout(ok, 50));
      actif = false;
      const reseauReel = liste.filter((x) => !x.parSw && !(x.echec && x.octets === 0));
      return { requetes: reseauReel.length, octets: reseauReel.reduce((s, x) => s + x.octets, 0), detail: liste };
    },
  };
}

async function preparerPage(contexte, profil, s) {
  const page = await contexte.newPage();
  page.on('dialog', (d) => d.accept());
  await reseau(page, profil);
  await page.goto(BASE + s.depart, { waitUntil: 'load', timeout: 90000 });
  // Ce que la page fait seule en arrière-plan (« + Ligne » préparée, D4) n'est pas le geste : on l'attend avant.
  if (/^\/offres\/\d+$/.test(s.depart)) {
    await page.waitForFunction(() => performance.getEntriesByType('resource').some((e) => /\/ajouter$/.test(e.name)), null, { timeout: 30000 }).catch(() => {});
  }
  await calme(page);
  await page.waitForTimeout(400);
  if (s.preparer) {
    await s.preparer(page);
    await calme(page);
    await page.waitForTimeout(300);
  }
  // Le service worker reçoit le même profil que la page, juste avant le geste (voir commun.js) ; le nombre de service
  // workers bridés va dans la mesure, comme preuve.
  page.swBrides = await briderServiceWorkers(contexte.browser(), profil);
  return page;
}

async function mesurer(contexte, profil, s, c) {
  const page = await preparerPage(contexte, profil, s);
  const origine = await page.evaluate(() => performance.timeOrigin);
  c.debut();
  const t0 = Date.now();
  await s.faire(page);
  const vu = Date.now() - t0;
  if (s.confirmer) await s.confirmer(page);
  await calme(page);
  const confirme = Date.now() - t0;
  const m = await c.fin();
  let doc = null;
  if (s.nav) {
    await page.waitForFunction((o) => performance.timeOrigin === o || performance.getEntriesByName('first-contentful-paint').length > 0, origine, { timeout: 5000 }).catch(() => {});
    doc = await page.evaluate((o) => {
      if (performance.timeOrigin === o) return null;
      const n = performance.getEntriesByType('navigation')[0];
      const fcp = performance.getEntriesByName('first-contentful-paint')[0];
      return n ? { ttfb: Math.round(n.responseStart), dcl: Math.round(n.domContentLoadedEventEnd), load: Math.round(n.loadEventEnd), fcp: fcp ? Math.round(fcp.startTime) : null } : null;
    }, origine).catch(() => null);
  }
  const recharge = s.nav ? null : await page.evaluate((o) => performance.timeOrigin !== o, origine).catch(() => null);
  const swBrides = page.swBrides;
  await page.close();
  return { vu, confirme, requetes: m.requetes, octets: m.octets, detail: m.detail, document: doc, recharge, swBrides };
}

(async () => {
  const choisis = scenarios.filter((s) => !filtre || s.id.includes(filtre));
  const profils = profilsTexte.split(',');
  console.log(`${choisis.length} gestes, profils ${profils.join(', ')}, cache apres`);
  for (const profil of profils) {
    const { browser, contexte } = await navigateur();
    // Le préchargement des 30 derniers jours (une fois l'heure) est mesuré à part : suspendu pendant le banc.
    await contexte.addInitScript(() => {
      const lire = Storage.prototype.getItem;
      Storage.prototype.getItem = function (cle) { return String(cle).startsWith('offregen-garde-') ? String(Date.now()) : lire.call(this, cle); };
    });
    const c = compteurReseau(contexte);
    // Premier usage : la liste ouverte une fois, le service worker en place, les fichiers dans le téléphone. Sur la base
    // remise : sinon le PDF gardé ici est celui de l'état laissé par la mesure précédente, et « PDF déjà vu » se
    // retéléchargeait (constaté le 2026-11-23, 32 Ko au lieu d'une réponse « rien n'a changé »).
    remettre();
    const p0 = await contexte.newPage();
    await p0.goto(BASE + '/', { waitUntil: 'load' });
    await p0.evaluate(() => navigator.serviceWorker && navigator.serviceWorker.ready).catch(() => {});
    await p0.goto(BASE + '/offres/2/apercu', { waitUntil: 'load' });
    await p0.waitForSelector('[data-pdf] canvas', { timeout: 60000 }).catch(() => {});
    await p0.close();
    for (const s of choisis) {
      try {
        remettre();
        const r = await mesurer(contexte, profil, s, c);
        const ligne = { id: s.id, profil, cache: 'apres', variante: 'apres', ...r, quand: new Date().toISOString() };
        fs.appendFileSync(SORTIE, JSON.stringify(ligne) + '\n');
        console.log(`${profil.padEnd(8)} ${s.id.padEnd(22)} vu ${String(r.vu).padStart(6)} ms  confirmé ${String(r.confirme).padStart(6)} ms ${String(r.requetes).padStart(3)} req ${String(Math.round(r.octets / 102.4) / 10).padStart(6)} Ko${r.document ? ' fcp ' + r.document.fcp + ' load ' + r.document.load : ''}${r.recharge ? ' RECHARGÉE' : ''}`);
      } catch (e) {
        console.log(`${profil.padEnd(8)} ${s.id.padEnd(22)} ÉCHEC ${String(e.message).split('\n')[0].slice(0, 160)}`);
        fs.appendFileSync(SORTIE, JSON.stringify({ id: s.id, profil, cache: 'apres', variante: 'apres', echec: String(e.message).slice(0, 300), quand: new Date().toISOString() }) + '\n');
        for (const p of contexte.pages()) await p.close().catch(() => {});
      }
    }
    await browser.close();
  }
})().catch((e) => { console.error(e); process.exit(1); });
