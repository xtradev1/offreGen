// Outils communs du banc « sans rechargement » (2026-11-23). Chromium de Playwright, téléphone 390 x 844.
'use strict';
const fs = require('fs');
const path = require('path');
const pw = require('C:/Users/xavie/AppData/Local/npm-cache/_npx/9833c18b2d85bc59/node_modules/playwright');

const CHROMIUM = 'C:/Users/xavie/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe';
const BASE = process.env.BANC_BASE || 'http://localhost:8093';
const ICI = __dirname;
const SESSION = path.join(ICI, 'etat-session.json');
const CAPTURES = path.join(ICI, 'captures');
fs.mkdirSync(CAPTURES, { recursive: true });

// Profils de réseau : latence ajoutée à chaque requête (ms), débits en octets par seconde. Simulés par Chromium
// (Network.emulateNetworkConditions), en plus du réseau réel (local, ~0 ms).
const PROFILS = {
  local: null,
  fibre: { latency: 25, downloadThroughput: 12500000, uploadThroughput: 12500000 }, // aller-retour mesuré vers le test : 25 ms
  '4g': { latency: 100, downloadThroughput: 1125000, uploadThroughput: 375000 }, // 9 Mbit/s, 3 Mbit/s
  chantier: { latency: 500, downloadThroughput: 50000, uploadThroughput: 25000 }, // 3G faible : 400 kbit/s, 200 kbit/s
};

async function navigateur(opts = {}) {
  const browser = await pw.chromium.launch({ executablePath: CHROMIUM, headless: true, args: opts.args || [] });
  const contexte = await browser.newContext({
    viewport: opts.viewport || { width: 390, height: 844 },
    deviceScaleFactor: opts.echelle || 2,
    isMobile: opts.mobile !== false,
    hasTouch: opts.mobile !== false,
    locale: 'fr-CH',
    storageState: fs.existsSync(SESSION) && !opts.sansSession ? SESSION : undefined,
  });
  return { browser, contexte };
}

const conditionsDe = (profil) => {
  const p = PROFILS[profil];
  return p
    ? { offline: false, latency: p.latency, downloadThroughput: p.downloadThroughput, uploadThroughput: p.uploadThroughput }
    : { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 };
};

// Le service worker envoie ses propres requêtes au serveur : le bridage de la page ne les touche pas, un GET qu'il relaie
// passait à la vitesse locale (constaté le 2026-11-23 : /clients ouverte en 71 ms « sur le chantier », 653 ms une fois le
// service worker bridé). Chaque service worker reçoit donc le même profil que la page.
const sessionsSw = new WeakMap();
async function briderServiceWorkers(browser, profil) {
  if (!browser) return 0;
  let etat = sessionsSw.get(browser);
  if (!etat) { etat = { b: await browser.newBrowserCDPSession(), cibles: new Map(), id: 0 }; sessionsSw.set(browser, etat); }
  const envoyer = (sessionId, method, params) => etat.b.send('Target.sendMessageToTarget', { sessionId, message: JSON.stringify({ id: ++etat.id, method, params }) });
  const { targetInfos } = await etat.b.send('Target.getTargets');
  let n = 0;
  for (const t of targetInfos.filter((x) => x.type === 'service_worker')) {
    for (let essai = 0; essai < 2; essai++) {
      try {
        let sessionId = etat.cibles.get(t.targetId);
        if (!sessionId) {
          ({ sessionId } = await etat.b.send('Target.attachToTarget', { targetId: t.targetId, flatten: false }));
          etat.cibles.set(t.targetId, sessionId);
          await envoyer(sessionId, 'Network.enable', {});
        }
        await envoyer(sessionId, 'Network.emulateNetworkConditions', conditionsDe(profil));
        n++;
        break;
      } catch (e) {
        etat.cibles.delete(t.targetId); // session périmée (service worker arrêté puis relancé) : on se rattache.
      }
    }
  }
  return n;
}

async function reseau(page, profil) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', conditionsDe(profil));
  await briderServiceWorkers(page.context().browser(), profil);
  // BANC_CPU=4 : processeur ralenti quatre fois, l'ordre de grandeur d'un téléphone moyen face à ce PC.
  if (Number(process.env.BANC_CPU) > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: Number(process.env.BANC_CPU) });
  return cdp;
}

// Compte les requêtes et les octets reçus (corps encodé + en-têtes) entre debut() et fin().
function compteur(page) {
  let actif = false;
  let liste = [];
  const enCours = new Set();
  page.on('request', (r) => { if (actif) enCours.add(r); });
  const finir = async (r, echec) => {
    if (!enCours.has(r)) return;
    enCours.delete(r);
    let octets = 0;
    try { const s = await r.sizes(); octets = s.responseBodySize + s.responseHeadersSize; } catch (e) { /* requête annulée */ }
    const t = r.timing();
    liste.push({ url: r.url().replace(BASE, ''), methode: r.method(), type: r.resourceType(), octets, echec: !!echec,
      attente: t && t.responseStart > 0 ? Math.round(t.responseStart - t.requestStart) : null });
  };
  page.on('requestfinished', (r) => finir(r, false));
  page.on('requestfailed', (r) => finir(r, true));
  return {
    debut() { actif = true; liste = []; enCours.clear(); },
    async fin() {
      // Les événements de Playwright arrivent après coup : un délai de grâce, puis l'attente des requêtes en vol.
      await new Promise((ok) => setTimeout(ok, 300));
      const t0 = Date.now();
      while (enCours.size > 0 && Date.now() - t0 < 30000) await new Promise((ok) => setTimeout(ok, 50));
      actif = false;
      // Le saut de redirection qu'un fetch « redirect: manual » annule aussitôt n'est pas une requête du geste.
      const vraies = liste.filter((x) => !(x.echec && x.octets === 0));
      return { requetes: vraies.length, octets: vraies.reduce((s, x) => s + x.octets, 0), detail: vraies };
    },
  };
}

async function connecter() {
  const mdp = fs.readFileSync('\\\\wsl.localhost\\Ubuntu\\home\\xavie\\offreGen-fluidite-banc\\.mdp', 'utf8').trim();
  const { browser, contexte } = await navigateur({ sansSession: true });
  const page = await contexte.newPage();
  await page.goto(BASE + '/connexion');
  await page.fill('#courriel', 'fluidite@exemple.ch');
  await page.fill('#mot_de_passe', mdp);
  await page.click('button[form="connexion"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/connexion'), { timeout: 15000 });
  await contexte.storageState({ path: SESSION });
  const ou = page.url();
  await browser.close();
  return ou;
}

module.exports = { pw, BASE, ICI, SESSION, CAPTURES, PROFILS, navigateur, reseau, briderServiceWorkers, compteur, connecter };
