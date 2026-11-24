// Parcours de captures pour la maquette des textes (site local de la branche, base offregen_relecture).
// Chaque capture garde son image (JPEG) et les textes visibles à l'écran à ce moment, dans l'ordre de lecture.
// Aucun courriel ne part : la messagerie locale écrit des fichiers.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const pw = require(process.env.MK_PLAYWRIGHT);

const BASE = 'http://localhost:8010';
const SORTIE = __dirname;
const IMG = path.join(SORTIE, 'img');
fs.mkdirSync(IMG, { recursive: true });
const captures = [];
const journal = (...a) => console.log(...a);

function wsl(cmd) {
  const f = path.join(SORTIE, '_cmd.sh');
  fs.writeFileSync(f, 'cd /home/xavie/offreGen-relecture\n' + cmd + '\n');
  const unix = '/mnt/c' + f.slice(2).replace(/\\/g, '/');
  return execSync(`wsl -d Ubuntu -e bash ${unix}`, { encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
}
function sql(q) {
  fs.writeFileSync(path.join(SORTIE, '_q.sql'), q);
  const unix = '/mnt/c' + path.join(SORTIE, '_sql.php').slice(2).replace(/\\/g, '/');
  return wsl(`php ${unix}`);
}

// Les textes visibles, regroupés par bloc, dans l'ordre du document. Champs : texte d'exemple (placeholder) compris.
async function textesVisibles(page, portee) {
  return page.evaluate((portee) => {
    const racine = portee ? document.querySelector(portee) : document.body;
    if (!racine) return [];
    const vis = (el) => {
      for (let e = el; e && e !== document.documentElement; e = e.parentElement) {
        if (e.hidden) return false;
        const s = getComputedStyle(e);
        if (s.display === 'none' || s.visibility === 'hidden' || +s.opacity === 0) return false;
      }
      const r = el.getClientRects();
      return r.length > 0 && [...r].some((x) => x.width > 0 && x.height > 0);
    };
    const bloc = (el) => {
      for (let e = el; e && e !== racine.parentElement; e = e.parentElement) {
        const d = getComputedStyle(e).display;
        if (!d.startsWith('inline') || ['BUTTON', 'A', 'LABEL', 'OPTION'].includes(e.tagName)) return e;
      }
      return racine;
    };
    const ordre = [];
    const parBloc = new Map();
    const w = document.createTreeWalker(racine, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) {
      const t = n.textContent.replace(/\s+/g, ' ');
      if (!t.trim()) continue;
      const p = n.parentElement;
      if (!p || ['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'TITLE'].includes(p.tagName) || p.closest('svg') || !vis(p)) continue;
      const b = bloc(p);
      if (!parBloc.has(b)) { parBloc.set(b, []); ordre.push(b); }
      parBloc.get(b).push(t);
    }
    const sortie = [];
    for (const b of ordre) {
      const t = parBloc.get(b).join('').replace(/\s+/g, ' ').trim();
      if (t && /[A-Za-zÀ-ÿ]/.test(t)) sortie.push(t);
    }
    racine.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((i) => {
      if (vis(i) && i.placeholder.trim()) sortie.push('Exemple dans le champ : ' + i.placeholder.trim());
    });
    return [...new Set(sortie)];
  }, portee || null);
}

async function capturer(page, id, section, titre, opts = {}) {
  await page.waitForTimeout(opts.attente ?? 700);
  const fichier = `${id}.jpg`;
  const cible = opts.element ? page.locator(opts.element).first() : page;
  await cible.screenshot({ path: path.join(IMG, fichier), type: 'jpeg', quality: 72, fullPage: !opts.element && opts.pleine !== false, animations: 'disabled' });
  const textes = await textesVisibles(page, opts.portee);
  captures.push({ id, section, titre, image: fichier, url: page.url().replace(BASE, ''), textes, note: opts.note || '' });
  journal('capture', id, textes.length, 'textes', page.url().replace(BASE, ''));
}

async function seConnecter(page, courriel, motDePasse) {
  await page.goto(BASE + '/connexion');
  await page.fill('input[name=courriel]', courriel);
  await page.fill('input[name=mot_de_passe]', motDePasse);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/connexion')), page.click('button[type=submit] >> visible=true')]);
}

async function etape(nom, f) {
  try { await f(); } catch (e) { journal('ÉCHEC', nom, e.message.split('\n')[0]); }
}

(async () => {
  const navigateur = await pw.chromium.launch({
    executablePath: process.env.MK_CHROMIUM,
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  });
  const telephone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };
  const bureau = { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 0.7, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };

  // ---------- 1. Site de présentation ----------
  await etape('site', async () => {
    const ctx = await navigateur.newContext(bureau);
    const page = await ctx.newPage();
    await page.goto(BASE + '/');
    // Le site révèle ses blocs au défilement : on descend écran par écran avant la capture entière.
    const hauteur = await page.evaluate(() => document.body.scrollHeight);
    for (let y = 0; y < hauteur; y += 700) { await page.evaluate((y) => window.scrollTo(0, y), y); await page.waitForTimeout(250); }
    await page.evaluate(() => window.scrollTo(0, 0));
    await capturer(page, 'site-accueil', 'site', 'Page de présentation', { attente: 900 });
    for (const [etat, titre] of [['recue', 'Demande d’essai reçue'], ['invalide', 'Courriel non valide'], ['trop', 'Trop de demandes']]) {
      await page.goto(BASE + '/?demande=' + etat + '#demande');
      for (let y = 0; y < hauteur; y += 700) { await page.evaluate((y) => window.scrollTo(0, y), y); await page.waitForTimeout(120); }
      await capturer(page, 'site-demande-' + etat, 'site', 'Demande d’essai : ' + titre.toLowerCase(), { element: '#demande', portee: '#demande' });
    }
    await ctx.close();
  });

  // ---------- 2. Créer une démo depuis l'administration ----------
  let lien = null;
  const mdpAdmin = wsl('cat ~/.offregen-relecture-admin-compte').split('\n').find((l) => l.startsWith('Mot de passe')).split(': ').pop().trim();
  await etape('création de la démo', async () => {
    const ctx = await navigateur.newContext(bureau);
    const page = await ctx.newPage();
    await seConnecter(page, 'admin-relecture@exemple.ch', mdpAdmin);
    await page.goto(BASE + '/admin/demos/nouvelle');
    await page.check('input[name=metier][value=peintre]');
    await Promise.all([page.waitForURL(/\/qr$/), page.click('button[type=submit] >> visible=true')]);
    lien = await page.evaluate(() => { const a = [...document.querySelectorAll('a[href*="/d/"], [data-lien], input[value*="/d/"]')][0]; return a ? (a.href || a.value || a.getAttribute('data-lien')) : null; });
    if (!lien) { const m = (await page.content()).match(/\/d\/[A-Za-z0-9_-]{43}/); lien = m ? m[0] : null; }
    journal('lien de démo', lien);
    await ctx.close();
  });

  // ---------- 3. Entrer dans la démo, le guide ----------
  const ctx = await navigateur.newContext(telephone);
  await ctx.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await ctx.newPage();
  let demoId = null;
  await etape('entrée', async () => {
    const chemin = lien.replace(/^https?:\/\/[^/]+/, '');
    await page.goto(BASE + chemin);
    await capturer(page, 'demo-entrer', 'demo', 'Entrer dans la démo');
    await page.fill('input[name=courriel]', 'prospect-relecture@exemple.ch');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/d/')), page.click('button[type=submit] >> visible=true')]);
    demoId = JSON.parse(sql("SELECT id FROM artisan WHERE courriel_saisi = 'prospect-relecture@exemple.ch' ORDER BY id DESC LIMIT 1"))[0].id;
    journal('démo', demoId, page.url());
  });
  await etape('guide 1', async () => {
    await page.goto(BASE + '/');
    await capturer(page, 'guide-1-micro', 'guides', 'Guide de la démo, étape 1 : le micro', { attente: 1800, pleine: false });
  });
  await etape('guide 2', async () => {
    await page.locator('.micro-central').first().click();
    await capturer(page, 'guide-2-conseils', 'guides', 'Guide de la démo, étape 2 : les conseils avant de dicter', { attente: 1500, pleine: false });
  });
  await etape('guide 3', async () => {
    await page.locator('[data-guide-commencer]').first().click();
    await page.waitForTimeout(6500);
    await capturer(page, 'guide-3-terminer', 'guides', 'Guide de la démo, étape 3 : pendant la dictée', { attente: 800, pleine: false });
  });
  await etape('guide 4', async () => {
    await page.locator('[data-terminer]').first().click();
    await capturer(page, 'guide-4-attente', 'guides', 'Guide de la démo, étape 4 : l’offre se prépare', { attente: 2500, pleine: false });
  });
  await etape('traitement', async () => {
    journal(wsl('php bin/traiter-file 2>&1 | tail -3'));
    await page.goto(BASE + '/');
    await capturer(page, 'demo-offres', 'demo', 'Mes offres, dans la démo', { attente: 1200 });
  });
  await etape('guide 5', async () => {
    const offre = page.locator('a[href^="/offres/"]').first();
    await offre.click();
    await page.waitForURL(/\/offres\/\d+/);
    await capturer(page, 'guide-5-reperes', 'guides', 'Guide de la démo, étape 5 : les repères de l’offre', { attente: 1800, pleine: false });
  });
  await etape('avant l’envoi', async () => {
    const compris = page.locator('.guide-bulle button, [data-guide] button').filter({ hasText: /Compris/ });
    if (await compris.count()) await compris.first().click();
    const generer = page.locator('a').filter({ hasText: /Générer l.offre/ }).first();
    await generer.click();
    await page.waitForLoadState('load');
    await page.waitForTimeout(1500);
    await capturer(page, 'apercu', 'guides', 'Aperçu du document, avant l’envoi', { attente: 800, pleine: false });
    const envoyer = page.locator('button, a').filter({ hasText: /^\s*Envoyer/ }).first();
    await envoyer.click();
    await capturer(page, 'avant-envoi', 'guides', 'Avant l’envoi : le nom de l’entreprise', { attente: 1000, pleine: false });
  });
  await etape('fenêtre de fin', async () => {
    sql(`UPDATE artisan SET demo_fin = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 HOUR) WHERE id = ${demoId}`);
    await page.goto(BASE + '/');
    await capturer(page, 'demo-fenetre-demain', 'demo', 'La démo se termine demain', { attente: 1200, pleine: false });
    sql(`UPDATE artisan SET demo_fin = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 44 HOUR) WHERE id = ${demoId}`);
    await page.goto(BASE + '/');
    await capturer(page, 'demo-fenetre-2-jours', 'demo', 'La démo se termine dans 2 jours', { attente: 1200, pleine: false });
  });
  await etape('passer à l’essai (écran)', async () => {
    await page.goto(BASE + '/passer-a-l-essai');
    await capturer(page, 'essai-passer', 'demo', 'Passer à l’essai');
  });
  await etape('démo terminée', async () => {
    sql(`UPDATE artisan SET demo_fin = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ${demoId}`);
    await page.goto(BASE + '/fin-de-demo');
    await capturer(page, 'demo-terminee', 'demo', 'Démo terminée');
    sql(`UPDATE artisan SET demo_fin = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 DAY) WHERE id = ${demoId}`);
  });
  await etape('démo périmée', async () => {
    const p2 = await ctx.newPage();
    await p2.goto(BASE + '/d/' + 'x'.repeat(43));
    await capturer(p2, 'demo-perimee', 'demo', 'Lien de démo périmé');
    await p2.close();
  });
  await etape('passage à l’essai', async () => {
    await page.goto(BASE + '/passer-a-l-essai');
    await page.fill('input[name=courriel]', 'essai-relecture@exemple.ch');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/passer-a-l-essai')), page.click('button[type=submit] >> visible=true')]);
    await capturer(page, 'essai-mot-de-passe', 'demo', 'Après le passage : choisir son mot de passe');
    const champs = page.locator('input[type=password]');
    for (let i = 0; i < await champs.count(); i++) await champs.nth(i).fill('Relecture-des-textes-2026');
    await Promise.all([page.waitForURL((u) => !u.pathname.includes('mot-de-passe')), page.click('button[type=submit] >> visible=true')]);
    journal('après le mot de passe', page.url());
  });
  for (const [nom, titre] of [['entreprise', 'Votre entreprise'], ['tva', 'La TVA'], ['logo', 'Votre logo'], ['marque', 'Modèle et couleur']]) {
    await etape('mise en route ' + nom, async () => {
      await page.goto(BASE + '/bienvenue/' + nom);
      await capturer(page, 'mise-en-route-' + nom, 'guides', 'Mise en route de l’essai : ' + titre.toLowerCase());
    });
  }
  await etape('vos prix', async () => {
    await page.goto(BASE + '/vos-prix');
    await capturer(page, 'mise-en-route-prix', 'guides', 'Mise en route de l’essai : vos prix');
  });
  await ctx.close();
  await navigateur.close();
  fs.writeFileSync(path.join(SORTIE, 'captures.json'), JSON.stringify(captures, null, 1));
  journal('fini', captures.length, 'captures');
})();
