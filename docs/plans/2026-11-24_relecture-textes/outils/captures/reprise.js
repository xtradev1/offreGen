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
  const anciens = JSON.parse(fs.readFileSync(path.join(SORTIE, 'captures.json'), 'utf8'));
  const navigateur = await pw.chromium.launch({ executablePath: process.env.MK_CHROMIUM, args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'] });
  const telephone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };
  const bureau = { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 0.7, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };
  const mdpAdmin = wsl('cat ~/.offregen-relecture-admin-compte').split('\n').find((l) => l.startsWith('Mot de passe')).split(': ').pop().trim();
  let lien = null;
  await etape('création de la démo', async () => {
    const ctx = await navigateur.newContext(bureau);
    const page = await ctx.newPage();
    await seConnecter(page, 'admin-relecture@exemple.ch', mdpAdmin);
    await page.goto(BASE + '/admin/demos/nouvelle');
    await page.check('input[name=metier][value=peintre]');
    await Promise.all([page.waitForURL(/\/qr$/), page.click('button[type=submit] >> visible=true')]);
    const m = (await page.content()).match(/\/d\/[A-Za-z0-9_-]{43}/);
    lien = m[0];
    await ctx.close();
  });
  const ctx = await navigateur.newContext(telephone);
  await ctx.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await ctx.newPage();
  await etape('entrée', async () => {
    await page.goto(BASE + lien);
    await page.fill('input[name=courriel]', 'prospect5-relecture@exemple.ch');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/d/')), page.click('button[type=submit] >> visible=true')]);
  });
  await etape('guide 5', async () => {
    await page.goto(BASE + '/');
    await page.waitForTimeout(1200);
    await page.locator('.micro-central').first().click();
    await page.waitForTimeout(1000);
    await page.locator('[data-guide-commencer]').first().click();
    await page.waitForTimeout(6500);
    await page.locator('[data-terminer]').first().click();
    await page.waitForTimeout(3000);
    journal(wsl('php bin/traiter-file 2>&1 | tail -1'));
    await page.goto(BASE + '/');
    const liens = page.locator('a[href^="/offres/"]').filter({ hasNotText: /séjour|Façade/ });
    await liens.first().click();
    await page.waitForURL(/\/offres\/\d+$/);
    await capturer(page, 'guide-5-reperes', 'guides', 'Guide de la démo, étape 5 : les repères de l’offre', { attente: 2000, pleine: false, note: 'La dictée de la capture était muette : l’offre est vide. Avec une vraie dictée, les pastilles 1, 2, 3 se posent sur les lignes.' });
  });
  await etape('aperçu et avant l’envoi', async () => {
    await page.goto(BASE + '/');
    await page.locator('a[href^="/offres/"]').filter({ hasText: /séjour/ }).first().click();
    await page.waitForURL(/\/offres\/\d+$/);
    const id = page.url().match(/\/offres\/(\d+)/)[1];
    await page.goto(BASE + `/offres/${id}/apercu`);
    await page.waitForTimeout(2500);
    await capturer(page, 'apercu', 'guides', 'Aperçu du document', { attente: 500, pleine: false });
    await page.locator('a, button').filter({ hasText: /^\s*Envoyer\s*$/ }).first().click();
    await capturer(page, 'avant-envoi', 'guides', 'Avant l’envoi : le nom de l’entreprise', { attente: 1200, pleine: false });
  });
  await ctx.close();
  await navigateur.close();
  const ids = new Set(captures.map((c) => c.id));
  const fusion = anciens.filter((c) => !ids.has(c.id)).concat(captures);
  fs.writeFileSync(path.join(SORTIE, 'captures.json'), JSON.stringify(fusion, null, 1));
  journal('fusion', fusion.length);
})();
