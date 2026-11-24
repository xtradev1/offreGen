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
  const anciens = JSON.parse(fs.readFileSync(path.join(SORTIE, 'captures.json'), 'utf8')).filter((c) => c.section !== 'site' || c.id.startsWith('site-demande'));
  const navigateur = await pw.chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const ctx = await navigateur.newContext({ viewport: { width: 1280, height: 860 }, deviceScaleFactor: 0.7, locale: 'fr-CH', timezoneId: 'Europe/Zurich' });
  const page = await ctx.newPage();
  await page.goto(BASE + '/');
  const hauteur = await page.evaluate(() => document.body.scrollHeight);
  for (let y = 0; y < hauteur; y += 500) { await page.evaluate((y) => window.scrollTo(0, y), y); await page.waitForTimeout(300); }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(800);
  const parties = [['site-haut', 'haut', 'Haut de la page'], ['site-produit', '#produit', 'Ce que Dicta fait'], ['site-image', '#image', 'Exemple de dictée et d’offre'],
    ['site-suisse', '#suisse', 'Fait en Suisse'], ['site-essai', '#essai', 'Demander un essai'], ['site-pied', 'footer', 'Pied de page']];
  for (const [id, sel, titre] of parties) {
    await etape(id, async () => {
      const el = page.locator(sel === 'haut' ? 'body' : sel).first();
      if (sel !== 'haut') await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      let textes = sel === 'haut' ? [] : await textesVisibles(page, sel);
      if (sel === 'haut') {
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(600);
        textes = await textesVisibles(page, null);
        const bas = await page.evaluate(() => document.querySelector('#produit').getBoundingClientRect().top);
        await page.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72, clip: { x: 0, y: 0, width: 1280, height: Math.ceil(bas) } });
      } else {
        await el.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72 });
      }
      captures.push({ id, section: 'site', titre, image: id + '.jpg', url: '/', textes: [...new Set(textes)], note: '' });
      journal('capture', id, textes.length);
    });
  }
  await navigateur.close();
  const ailleurs = new Set(captures.filter((c) => c.id !== 'site-haut').flatMap((c) => c.textes));
  const haut = captures.find((c) => c.id === 'site-haut');
  if (haut) haut.textes = haut.textes.filter((t) => !ailleurs.has(t));
  const site = captures.concat(anciens.filter((c) => c.section === 'site'));
  fs.writeFileSync(path.join(SORTIE, 'captures.json'), JSON.stringify(site.concat(anciens.filter((c) => c.section !== 'site')), null, 1));
})();
