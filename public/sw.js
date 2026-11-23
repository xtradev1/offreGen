// offreGen : le service worker (chantier docs/plans/2026-11-23_sans-rechargement, lot 4, D5, D6). Il garde dans le
// téléphone les fichiers de l'application, les pages déjà vues ou préchargées et le dernier PDF de chaque offre, pour
// qu'une offre ouverte reste lisible et modifiable sans réseau. Les envois (POST) ne passent pas par lui : la file de
// fluide.js les garde. Jamais gardés : la connexion, l'administration, l'état d'une dictée, les morceaux de page.
// Tout ce qui est gardé l'est pour un compte, 14 jours au plus (la durée d'une session) : un autre compte, une entrée de
// session (connexion, lien de démo, mot de passe) ou la déconnexion l'efface.
'use strict';

const VERSION = '2026-11-23-2';
const FICHIERS = 'offregen-fichiers-' + VERSION;
const PAGES = 'offregen-pages-';
const ETAT = 'offregen-etat';
const ATTENTE_MS = 6000;
const GARDE_MS = 14 * 86400000;
// Pages jamais gardées ; entrées de session, qui effacent ce qui était gardé (revue du 2026-11-23, B-I9 et D-I2).
const JAMAIS = /^\/(connexion|deconnexion|mot-de-passe|admin|d|sante|essai|decouvrir|passer-a-l-essai|fin-de-demo|bienvenue|retour-espace|import)(\/|$)|\/etat$|\/reglages\/logo$/;
const ENTREES = /^\/(connexion|d|mot-de-passe|retour-espace|passer-a-l-essai|essai)(\/|$)/;
const PDF = /^\/(offres|factures)\/\d+\/pdf$/;

// Le compte des pages gardées, rangé aussi dans le cache : un service worker relancé le retrouve avant le message de la page.
let compte = null;
const lireCompte = async () => {
  if (compte) return compte;
  const r = await (await caches.open(ETAT)).match('/compte');
  compte = r ? (await r.text()) || null : null;
  return compte;
};
const noterCompte = async (c) => {
  compte = c;
  const cache = await caches.open(ETAT);
  if (c) await cache.put('/compte', new Response(c)); else await cache.delete('/compte');
};
const nomDesPages = (c) => (c ? PAGES + c : null);
const cle = (url) => url.pathname + url.search;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const noms = await caches.keys();
    await Promise.all(noms.filter((n) => n.startsWith('offregen-fichiers-') && n !== FICHIERS).map((n) => caches.delete(n)));
    await self.clients.claim();
  })());
});

const effacerLesPages = async (sauf) => {
  const noms = await caches.keys();
  await Promise.all(noms.filter((n) => n.startsWith(PAGES) && n !== sauf).map((n) => caches.delete(n)));
};

// Un fichier versionné (?v=, empreinte du contenu) ou de PDF.js (version dans son dossier) ne change jamais : gardé, servi
// sans réseau ; l'ancienne version du même fichier part. Un fichier sans version suit le réseau et ses règles de cache
// (.htaccess) ; la copie gardée ne sert que sans réseau (revue, B-I11 et C-I2).
const versionne = (url) => /(^|&)v=[0-9a-f]{12}(&|$)/.test(url.search.slice(1)) || url.pathname.startsWith('/js/vendor/');
const garderFichier = async (cache, requete, reponse) => {
  if (!reponse.ok || reponse.type !== 'basic') return;
  const url = new URL(requete.url);
  const anciens = (await cache.keys()).filter((k) => { const u = new URL(k.url); return u.pathname === url.pathname && u.search !== url.search; });
  await Promise.all(anciens.map((k) => cache.delete(k)));
  await cache.put(requete, reponse.clone());
};
const fichier = async (requete) => {
  const cache = await caches.open(FICHIERS);
  if (versionne(new URL(requete.url))) {
    const garde = await cache.match(requete);
    if (garde) return garde;
    const reponse = await fetch(requete);
    await garderFichier(cache, requete, reponse);
    return reponse;
  }
  try {
    const reponse = await fetch(requete);
    await garderFichier(cache, requete, reponse);
    return reponse;
  } catch (err) {
    const garde = await cache.match(requete);
    if (garde) return garde;
    throw err;
  }
};

// La page gardée, marquée pour l'écran : data-garde-le (date de la copie), data-garde-raison (hors réseau, ou lent).
const marquer = async (reponse, raison) => {
  const texte = await reponse.text();
  const le = reponse.headers.get('X-Garde-Le') || '';
  const entetes = new Headers(reponse.headers);
  entetes.delete('Content-Length');
  return new Response(texte.replace('<body', '<body data-garde-le="' + le + '" data-garde-raison="' + raison + '"'), { status: 200, headers: entetes });
};

const horsReseau = async () => {
  const cache = await caches.open(FICHIERS);
  const feuille = (await cache.keys()).map((k) => new URL(k.url)).find((u) => u.pathname === '/css/app.css');
  const lien = feuille ? '<link rel="stylesheet" href="' + cle(feuille) + '">' : '';
  const corps = '<!doctype html><html lang="fr-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    + lien + '<title>Hors réseau</title></head><body><div class="app app-seule"><main class="screen"><h1>Hors réseau</h1>'
    + '<p>Cette page n’a pas été gardée dans le téléphone. Les offres ouvertes ou préparées le sont&nbsp;: revenez à la liste.</p>'
    + '<p><a class="btn primary" href="/">Mes offres</a></p></main></div></body></html>';
  return new Response(corps, { status: 503, headers: {
    'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store',
    'Content-Security-Policy': "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'",
    'X-Frame-Options': 'SAMEORIGIN', 'X-Content-Type-Options': 'nosniff',
  } });
};

// Les fichiers qu'une page cite (feuilles de style, scripts, images) et les polices de ses feuilles : gardés avec elle,
// sinon la page rouverte sans réseau s'afficherait sans style ni script (ceux chargés avant le service worker manquent).
const garderFichiers = async (texte) => {
  const cache = await caches.open(FICHIERS);
  const deja = new Set((await cache.keys()).map((k) => cle(new URL(k.url))));
  const adresses = [...texte.matchAll(/\s(?:href|src|srcset)="(\/(?:css|js|img|fonts)\/[^"\s]+)"/g)].map((m) => m[1].replace(/&amp;/g, '&'));
  for (const adresse of [...new Set(adresses)]) {
    if (deja.has(adresse)) continue;
    try {
      const reponse = await fichier(new Request(adresse));
      deja.add(adresse);
      if (/\.css(\?|$)/.test(adresse) && reponse.ok) {
        const css = await reponse.clone().text();
        for (const m of css.matchAll(/url\(["']?(\/fonts\/[^"')]+)["']?\)/g)) {
          if (!deja.has(m[1])) { await fichier(new Request(m[1])).catch(() => {}); deja.add(m[1]); }
        }
      }
    } catch (err) {
      return; // plus de réseau : la prochaine page gardée reprendra
    }
  }
};

// Garder une page reçue : seulement une vraie page de l'artisan (200, HTML, même adresse, pas l'écran de connexion).
const garder = async (requete, reponse) => {
  const type = reponse.headers.get('Content-Type') || '';
  if (!reponse.ok || reponse.redirected || reponse.type !== 'basic') return;
  const url = new URL(requete.url);
  const entetes = new Headers(reponse.headers);
  entetes.set('X-Garde-Le', new Date().toISOString());
  // La couleur de l'artisan (/reglages/marque.css?v=…) : gardée avec les pages de son compte, sous sa vraie adresse.
  if (url.pathname === '/reglages/marque.css' && type.includes('text/css')) {
    const c = await lireCompte();
    if (c) await (await caches.open(nomDesPages(c))).put(cle(url), new Response(await reponse.text(), { status: 200, headers: entetes }));
    return;
  }
  if (!type.includes('text/html')) return;
  const texte = await reponse.clone().text();
  const m = /<body[^>]*\sdata-compte="(\d+)"/.exec(texte);
  if (!m) return;
  if (m[1] !== (await lireCompte())) { await noterCompte(m[1]); await effacerLesPages(nomDesPages(m[1])); }
  const version = requete.headers.get('X-Version-Garde');
  if (version) entetes.set('X-Version-Garde', version);
  await (await caches.open(nomDesPages(m[1]))).put(cle(url), new Response(texte, { status: 200, headers: entetes }));
  await garderFichiers(texte);
};

// La copie d'une adresse, pour le seul compte noté, et pas plus vieille que la durée d'une session.
const chercherGardee = async (requete) => {
  const c = await lireCompte();
  if (!c) return null;
  const cache = await caches.open(nomDesPages(c));
  const url = cle(new URL(requete.url));
  const r = await cache.match(url);
  if (!r) return null;
  const le = Date.parse(r.headers.get('X-Garde-Le') || '');
  if (!Number.isFinite(le) || Date.now() - le > GARDE_MS) { await cache.delete(url); return null; }
  return r;
};

// Une page : le réseau d'abord ; sans réponse en 6 secondes ou sans réseau, la copie gardée (le réseau continue, met la
// copie à jour et le dit à la page) ; ni réseau ni copie : l'écran « hors réseau ».
const page = async (e) => {
  const requete = e.request;
  const html = new URL(requete.url).pathname !== '/reglages/marque.css';
  let servieGardee = false;
  const reseau = fetch(requete).then(async (reponse) => {
    if (new URL(reponse.url).pathname === '/connexion') { await effacerLesPages(null); await noterCompte(null); }
    else await garder(requete, reponse.clone()).catch(() => {});
    // Réseau lent : la page a reçu la copie ; la page à jour est là, on le lui dit.
    if (servieGardee && html && reponse.ok) {
      const client = await self.clients.get(e.resultingClientId || e.clientId);
      if (client) client.postMessage({ type: 'page-a-jour', adresse: cle(new URL(requete.url)) });
    }
    return reponse;
  });
  e.waitUntil(reseau.then(() => {}, () => {}));
  const attendre = (ms) => new Promise((ok) => setTimeout(() => ok(null), ms));
  let raison = 'lent';
  try {
    const premiere = await Promise.race([reseau, attendre(ATTENTE_MS)]);
    if (premiere) return premiere;
  } catch (err) {
    raison = 'hors';
  }
  const gardee = await chercherGardee(requete);
  if (gardee) { servieGardee = true; return html ? marquer(gardee, raison) : gardee; }
  // Ni réponse ni copie : le réseau a encore un moment, puis l'écran le dit. Une feuille de style qui attend sans fin
  // bloquerait les scripts de la page : elle reçoit vite une réponse vide.
  if (html) {
    try {
      const tard = await Promise.race([reseau, attendre(15000)]);
      if (tard) return tard;
    } catch (err) { /* idem */ }
  }
  return html ? horsReseau() : new Response('', { status: 503, headers: { 'Content-Type': 'text/css' } });
};

// Le PDF d'une offre ou d'une facture (lot 5) : le dernier reçu est gardé avec son ETag et revalidé ; inchangé, le
// serveur répond 304 et rien ne se retélécharge ; sans réseau, le dernier PDF vu s'ouvre encore (fluide.js dit, sur
// l'aperçu, que les changements en attente n'y sont pas).
const pdf = async (requete) => {
  const c = await lireCompte();
  const cache = c ? await caches.open(nomDesPages(c)) : null;
  const url = new URL(requete.url).pathname;
  let garde = cache ? await cache.match(url) : null;
  if (garde && !(Date.now() - Date.parse(garde.headers.get('X-Garde-Le') || '') <= GARDE_MS)) { await cache.delete(url); garde = null; }
  const entetes = new Headers(requete.headers);
  if (garde && garde.headers.get('ETag')) entetes.set('If-None-Match', garde.headers.get('ETag'));
  let reponse;
  try {
    reponse = await fetch(requete.url, { headers: entetes, credentials: 'same-origin', cache: 'no-store' });
  } catch (err) {
    if (garde) return garde;
    throw err;
  }
  if (reponse.status === 304 && garde) return garde;
  if (reponse.ok && cache && (reponse.headers.get('Content-Type') || '').includes('application/pdf')) {
    const garder = new Headers(reponse.headers);
    garder.set('X-Garde-Le', new Date().toISOString());
    await cache.put(url, new Response(await reponse.clone().blob(), { status: 200, headers: garder }));
  }
  return reponse;
};

self.addEventListener('fetch', (e) => {
  const requete = e.request;
  if (requete.method !== 'GET') return;
  const url = new URL(requete.url);
  if (url.origin !== self.location.origin || requete.headers.has('X-Morceaux')) return;
  // Une entrée de session (connexion, lien de démo, mot de passe, retour à son espace) : ce qui était gardé part.
  if (requete.mode === 'navigate' && ENTREES.test(url.pathname)) { e.waitUntil(effacerLesPages(null).then(() => noterCompte(null))); return; }
  if (/^\/(css|js|fonts|img)\//.test(url.pathname)) { e.respondWith(fichier(requete)); return; }
  if (PDF.test(url.pathname)) { e.respondWith(pdf(requete)); return; }
  if (JAMAIS.test(url.pathname)) return;
  const html = requete.mode === 'navigate' || (requete.headers.get('Accept') || '').includes('text/html') || requete.headers.has('X-Version-Garde');
  if (html || url.pathname === '/reglages/marque.css') e.respondWith(page(e));
});

// La page dit son compte et ce qu'il faut garder d'avance (D6 : liste, catalogue, clients, offres des 30 derniers
// jours) ; la déconnexion efface tout.
self.addEventListener('message', (e) => {
  const m = e.data || {};
  if (m.type === 'compte' && m.compte) {
    e.waitUntil((async () => {
      if ((await lireCompte()) !== String(m.compte)) { await noterCompte(String(m.compte)); await effacerLesPages(nomDesPages(String(m.compte))); }
    })());
  }
  if (m.type === 'oublier') e.waitUntil(effacerLesPages(null).then(() => noterCompte(null)));
  if (m.type === 'garder' && Array.isArray(m.pages)) e.waitUntil(precharger(m.pages));
});

// Une à une, sans presser le réseau ; une page déjà gardée à la même version n'est pas redemandée.
const precharger = async (pages) => {
  const c = await lireCompte();
  if (!c) return;
  const cache = await caches.open(nomDesPages(c));
  for (const p of pages.slice(0, 80)) {
    if (!p || typeof p.adresse !== 'string') continue;
    const url = new URL(p.adresse, self.location.origin);
    if (url.origin !== self.location.origin || JAMAIS.test(url.pathname)) continue;
    const garde = await cache.match(cle(url));
    if (garde && p.version && garde.headers.get('X-Version-Garde') === String(p.version)) continue;
    try {
      const requete = new Request(cle(url), { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Version-Garde': String(p.version || '') } });
      await garder(requete, await fetch(requete));
    } catch (err) {
      return; // plus de réseau : la suite attendra la prochaine ouverture
    }
    await new Promise((ok) => setTimeout(ok, 400));
  }
};
