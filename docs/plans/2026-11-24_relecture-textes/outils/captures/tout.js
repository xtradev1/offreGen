// Toutes les captures de la page de relecture (chantier 2026-11-24_relecture-textes), dans l'ordre où l'artisan
// découvre Dicta : le site, la démo et son guide, le passage à l'essai, les courriels.
// Site local de la branche (base offregen_relecture, messagerie en fichiers : aucun courriel ne part).
// Chaque capture garde trois choses :
//   - une image JPEG (img/<id>.jpg), la capture d'origine ;
//   - les textes visibles, dans l'ordre de lecture ;
//   - une copie figée de la page (ecrans/<fichier>.txt et .css) : HTML de Dicta sans scripts, styles posés par les
//     scripts convertis en feuille de style, chaque texte marqué data-rl="<capture>:<rang>" pour l'aperçu en direct.
// Usage : node tout.js   (MK_PLAYWRIGHT, MK_CHROMIUM ; serveur local sur le port 8010)
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const pw = require(process.env.MK_PLAYWRIGHT);

const BASE = 'http://localhost:8010';
const ICI = __dirname;
const IMG = path.join(ICI, 'img');
const ECRANS = path.join(ICI, 'ecrans');
// Adresse des copies figées sur le site : leurs images de canevas y sont lues.
const ECRANS_WEB = '/relecture-textes/ecrans/';
// « node tout.js courriels » : seulement les courriels, fusionnés aux captures déjà faites.
const SEULEMENT_COURRIELS = process.argv[2] === 'courriels';
for (const d of [IMG, ECRANS]) {
  if (!SEULEMENT_COURRIELS) fs.rmSync(d, { recursive: true, force: true });
  fs.mkdirSync(d, { recursive: true });
}
const captures = [];
const journal = (...a) => console.log(...a);

function wsl(cmd) {
  const f = path.join(ICI, '_cmd.sh');
  fs.writeFileSync(f, 'cd /home/xavie/offreGen-relecture\n' + cmd + '\n');
  const unix = '/mnt/c' + f.slice(2).replace(/\\/g, '/');
  return execSync(`wsl -d Ubuntu -e bash ${unix}`, { encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } });
}
function sql(q) {
  fs.writeFileSync(path.join(ICI, '_q.sql'), q);
  return wsl(`php /mnt/c${path.join(ICI, '_sql.php').slice(2).replace(/\\/g, '/')}`);
}

// Les textes visibles, regroupés par bloc, dans l'ordre du document, et leur marque data-rl sur l'élément qui les porte.
async function textesVisibles(page, portee, id) {
  return page.evaluate(({ portee, id }) => {
    const racine = portee ? document.querySelector(portee) : document.body;
    if (!racine) return [];
    racine.querySelectorAll('[data-rl]').forEach((e) => e.removeAttribute('data-rl'));
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
    // Deux morceaux de texte portés par des éléments différents sont séparés à l'écran si l'un des deux, jusqu'à
    // leur ancêtre commun, est en ligne-bloc, flexible, ou porte une marge ou un retrait de son côté.
    const separe = (el, cote) => {
      const s = getComputedStyle(el);
      return !/^inline$/.test(s.display) || parseFloat(s['margin' + cote]) > 0 || parseFloat(s['padding' + cote]) > 0;
    };
    const separes = (a, b) => {
      if (!a || a === b) return false;
      for (let e = a; e && !e.contains(b); e = e.parentElement) { if (separe(e, 'Right')) return true; }
      for (let e = b; e && !e.contains(a); e = e.parentElement) { if (separe(e, 'Left')) return true; }
      return false;
    };
    const dernier = new Map();
    const ordre = [];
    const parBloc = new Map();
    const w = document.createTreeWalker(racine, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT);
    const sauts = new Set();
    for (let n = w.nextNode(); n; n = w.nextNode()) {
      if (n.nodeType === 1) {
        if (n.tagName === 'BR') sauts.add(bloc(n.parentElement));
        continue;
      }
      const t = n.textContent.replace(/\s+/g, ' ');
      if (!t.trim()) continue;
      const p = n.parentElement;
      if (!p || ['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'TITLE'].includes(p.tagName) || p.closest('svg') || !vis(p)) continue;
      const b = bloc(p);
      if (!parBloc.has(b)) { parBloc.set(b, []); ordre.push(b); }
      const morceaux = parBloc.get(b);
      if (morceaux.length && (sauts.has(b) || separes(dernier.get(b), p)) && /\S$/.test(morceaux[morceaux.length - 1]) && /^\S/.test(t)) morceaux.push(' ');
      sauts.delete(b);
      morceaux.push(t);
      dernier.set(b, p);
    }
    const sortie = [];
    const vus = new Set();
    const garder = (t, el) => {
      if (!t || !/[A-Za-zÀ-ÿ]/.test(t) || vus.has(t)) return;
      vus.add(t);
      el.setAttribute('data-rl', id + ':' + sortie.length);
      sortie.push(t);
    };
    for (const b of ordre) garder(parBloc.get(b).join('').replace(/\s+/g, ' ').trim(), b);
    racine.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((i) => {
      if (vis(i) && i.placeholder.trim()) garder('Exemple dans le champ : ' + i.placeholder.trim(), i);
    });
    return sortie;
  }, { portee: portee || null, id });
}

// Copie figée de la page : sans scripts ; les styles écrits sur les éléments (bulles du guide, hauteurs) passent dans
// une feuille à part, car la CSP du site les refuse dans le HTML ; un canevas (PDF dessiné) devient une image.
async function instantane(page, fichier) {
  const r = await page.evaluate(({ fichier, ECRANS_WEB }) => {
    const orig = document.documentElement;
    const copie = orig.cloneNode(true);
    const o = [orig, ...orig.querySelectorAll('*')];
    const c = [copie, ...copie.querySelectorAll('*')];
    const regles = [];
    const canevas = [];
    o.forEach((el, k) => {
      const x = c[k];
      if (el instanceof HTMLInputElement) {
        if (['checkbox', 'radio'].includes(el.type)) { if (el.checked) x.setAttribute('checked', ''); else x.removeAttribute('checked'); }
        else if (!['hidden', 'password', 'file'].includes(el.type)) x.setAttribute('value', el.value);
      }
      if (el instanceof HTMLTextAreaElement) x.textContent = el.value;
      const st = el.getAttribute('style');
      if (st && st.trim()) {
        const cls = 'rls-' + regles.length;
        x.removeAttribute('style');
        x.setAttribute('class', ((x.getAttribute('class') || '') + ' ' + cls).trim());
        const decl = st.split(';').map((d) => d.trim()).filter(Boolean).map((d) => (/!important$/.test(d) ? d : d + ' !important')).join('; ');
        regles.push('.' + cls + ' { ' + decl + ' }');
      }
      if (el.tagName === 'CANVAS') {
        let donnee = null;
        try { donnee = el.toDataURL('image/png'); } catch (e) { /* canevas protégé : reste vide */ }
        if (donnee) {
          const img = document.createElement('img');
          for (const a of x.attributes) img.setAttribute(a.name, a.value);
          img.setAttribute('src', ECRANS_WEB + fichier + '-c' + canevas.length + '.png');
          img.setAttribute('alt', '');
          canevas.push(donnee);
          x.replaceWith(img);
        }
      }
    });
    copie.querySelectorAll('style').forEach((s) => { regles.push(s.textContent); s.remove(); });
    copie.querySelectorAll('script, noscript, link[rel=manifest], link[rel=modulepreload], link[rel=preload], link[rel=icon], link[rel=apple-touch-icon], meta[http-equiv]').forEach((e) => e.remove());
    copie.querySelectorAll('input[name=_csrf]').forEach((e) => e.setAttribute('value', ''));
    copie.querySelectorAll('[href^="blob:"], [src^="blob:"], [href^="data:"], [src^="data:"]').forEach((e) => { e.removeAttribute('href'); e.removeAttribute('src'); });
    copie.querySelectorAll('*').forEach((e) => [...e.attributes].forEach((a) => { if (/^on/i.test(a.name)) e.removeAttribute(a.name); }));
    const tete = copie.querySelector('head') || copie.insertBefore(document.createElement('head'), copie.firstChild);
    for (const f of ['commun.css', fichier + '.css']) {
      const l = document.createElement('link');
      l.setAttribute('rel', 'stylesheet');
      l.setAttribute('href', ECRANS_WEB + f);
      tete.appendChild(l);
    }
    return {
      html: '<!doctype html>\n' + copie.outerHTML,
      css: regles.join('\n'),
      canevas,
      largeur: innerWidth,
      hauteurVue: innerHeight,
      hauteurPage: Math.max(document.documentElement.scrollHeight, innerHeight),
    };
  }, { fichier, ECRANS_WEB });
  const html = r.html.split('http://localhost:8010').join('');
  fs.writeFileSync(path.join(ECRANS, fichier + '.txt'), html);
  fs.writeFileSync(path.join(ECRANS, fichier + '.css'), r.css + '\n');
  r.canevas.forEach((d, k) => fs.writeFileSync(path.join(ECRANS, `${fichier}-c${k}.png`), Buffer.from(d.split(',')[1], 'base64')));
  return { largeur: r.largeur, hauteurVue: r.hauteurVue, hauteurPage: r.hauteurPage };
}

// Une capture : image, textes, copie figée. opts.pleine = false : l'écran visible seulement (bulles du guide).
async function capturer(page, id, section, titre, opts = {}) {
  await page.waitForTimeout(opts.attente ?? 700);
  const pleine = opts.pleine !== false;
  await page.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72, fullPage: pleine, animations: 'disabled' });
  const textes = await textesVisibles(page, null, id);
  const m = await instantane(page, id);
  const apercu = { fichier: id, largeur: m.largeur, hauteurPage: pleine ? m.hauteurPage : m.hauteurVue, decalage: 0, hauteurVue: pleine ? m.hauteurPage : m.hauteurVue };
  captures.push({ id, section, titre, image: id + '.jpg', url: page.url().replace(BASE, ''), textes, note: opts.note || '', apercu });
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
async function defiler(page, pas = 500, attente = 300) {
  const h = await page.evaluate(() => document.body.scrollHeight);
  for (let y = 0; y < h; y += pas) { await page.evaluate((y) => window.scrollTo(0, y), y); await page.waitForTimeout(attente); }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(600);
}

(async () => {
  const navigateur = await pw.chromium.launch({
    executablePath: process.env.MK_CHROMIUM,
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  });
  const telephone = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };
  const bureau = { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 0.7, locale: 'fr-CH', timezoneId: 'Europe/Zurich' };

  if (!SEULEMENT_COURRIELS) await parcours(navigateur, telephone, bureau);
  await courriels(navigateur);
  await navigateur.close();
  const anciens = SEULEMENT_COURRIELS ? JSON.parse(fs.readFileSync(path.join(ICI, 'captures.json'), 'utf8')).filter((c) => c.section !== 'courriels') : [];
  fs.writeFileSync(path.join(ICI, 'captures.json'), JSON.stringify(anciens.concat(captures), null, 1));
  journal('fini', captures.length, 'captures');
})();

async function parcours(navigateur, telephone, bureau) {
  // ---------- 1. Le site : une seule copie figée, une vue par section ----------
  await etape('site', async () => {
    const ctx = await navigateur.newContext(bureau);
    const page = await ctx.newPage();
    await page.goto(BASE + '/');
    await defiler(page);
    const parties = [['site-haut', null, 'Haut de la page'], ['site-produit', '#produit', 'Ce que Dicta fait'], ['site-image', '#image', 'Exemple de dictée et d’offre'],
      ['site-suisse', '#suisse', 'Fait en Suisse'], ['site-essai', '#essai', 'Demander un essai'], ['site-pied', 'footer', 'Pied de page']];
    const vues = [];
    for (const [id, sel, titre] of parties) {
      const textes = await textesVisibles(page, sel, id);
      let rect;
      if (sel === null) {
        rect = await page.evaluate(() => ({ top: 0, height: document.querySelector('#produit').getBoundingClientRect().top + scrollY }));
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72, clip: { x: 0, y: 0, width: 1280, height: Math.ceil(rect.height) } });
      } else {
        const el = page.locator(sel).first();
        await el.scrollIntoViewIfNeeded();
        await page.waitForTimeout(400);
        rect = await el.evaluate((e) => { const r = e.getBoundingClientRect(); return { top: r.top + scrollY, height: r.height }; });
        await el.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72 });
      }
      vues.push({ id, titre, textes, rect });
    }
    // Le haut de page garde les textes qu'aucune section ne porte.
    const ailleurs = new Set(vues.slice(1).flatMap((v) => v.textes));
    const haut = vues[0];
    haut.textes = haut.textes.map((t) => (ailleurs.has(t) ? null : t));
    await page.evaluate(() => window.scrollTo(0, 0));
    const m = await instantane(page, 'site');
    for (const v of vues) {
      captures.push({ id: v.id, section: 'site', titre: v.titre, image: v.id + '.jpg', url: '/', textes: v.textes, note: '',
        apercu: { fichier: 'site', largeur: m.largeur, hauteurPage: m.hauteurPage, decalage: Math.round(v.rect.top), hauteurVue: Math.ceil(v.rect.height) } });
      journal('capture', v.id, v.textes.filter(Boolean).length, 'textes');
    }
    for (const etat of ['recue', 'invalide', 'trop']) {
      const id = 'site-demande-' + etat;
      await page.goto(BASE + '/?demande=' + etat + '#demande');
      await defiler(page, 700, 120);
      const el = page.locator('#demande').first();
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(400);
      await el.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72 });
      const textes = await textesVisibles(page, '#demande', id);
      const rect = await el.evaluate((e) => { const r = e.getBoundingClientRect(); return { top: r.top + scrollY, height: r.height }; });
      const mm = await instantane(page, id);
      captures.push({ id, section: 'site', titre: etat, image: id + '.jpg', url: '/', textes, note: '',
        apercu: { fichier: id, largeur: mm.largeur, hauteurPage: mm.hauteurPage, decalage: Math.round(rect.top), hauteurVue: Math.ceil(rect.height) } });
      journal('capture', id, textes.length, 'textes');
    }
    await ctx.close();
  });

  // ---------- 2. Une démo créée par l'administration ----------
  let lien = null;
  const mdpAdmin = wsl('cat ~/.offregen-relecture-admin-compte').split('\n').find((l) => l.startsWith('Mot de passe')).split(': ').pop().trim();
  await etape('création de la démo', async () => {
    const ctx = await navigateur.newContext(bureau);
    const page = await ctx.newPage();
    await seConnecter(page, 'admin-relecture@exemple.ch', mdpAdmin);
    await page.goto(BASE + '/admin/demos/nouvelle');
    await page.check('input[name=metier][value=peintre]');
    await Promise.all([page.waitForURL(/\/qr$/), page.click('button[type=submit] >> visible=true')]);
    lien = (await page.content()).match(/\/d\/[A-Za-z0-9_-]{43}/)[0];
    await ctx.close();
  });

  // ---------- 3. La démo, son guide, le passage à l'essai ----------
  const ctx = await navigateur.newContext(telephone);
  await ctx.addInitScript(() => { navigator.canShare = () => true; navigator.share = async () => {}; });
  const page = await ctx.newPage();
  let demoId = null;
  await etape('entrée', async () => {
    await page.goto(BASE + lien);
    await capturer(page, 'demo-entrer', 'demo', 'Entrer dans la démo');
    await page.fill('input[name=courriel]', 'prospect-relecture@exemple.ch');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/d/')), page.click('button[type=submit] >> visible=true')]);
    demoId = JSON.parse(sql("SELECT id FROM artisan WHERE courriel_saisi = 'prospect-relecture@exemple.ch' ORDER BY id DESC LIMIT 1"))[0].id;
  });
  await etape('guide 1', async () => {
    await page.goto(BASE + '/');
    await capturer(page, 'guide-1-micro', 'guides', 'Guide 1', { attente: 1800, pleine: false });
  });
  await etape('guide 2', async () => {
    await page.locator('.micro-central').first().click();
    await capturer(page, 'guide-2-conseils', 'guides', 'Guide 2', { attente: 1500, pleine: false });
  });
  await etape('guide 3', async () => {
    await page.locator('[data-guide-commencer]').first().click();
    await page.waitForTimeout(6500);
    await capturer(page, 'guide-3-terminer', 'guides', 'Guide 3', { attente: 800, pleine: false });
  });
  await etape('guide 4', async () => {
    await page.locator('[data-terminer]').first().click();
    await capturer(page, 'guide-4-attente', 'guides', 'Guide 4', { attente: 2500, pleine: false });
  });
  await etape('guide 5', async () => {
    const dictee = page.locator('a[href^="/offres/"]').filter({ hasNotText: /séjour|Façade/ });
    for (let essai = 0; essai < 3; essai++) {
      await page.waitForTimeout(3000);
      journal(wsl('php bin/traiter-file 2>&1 | tail -1').trim());
      await page.goto(BASE + '/');
      if (await dictee.count()) break;
    }
    await dictee.first().click();
    await page.waitForURL(/\/offres\/\d+$/);
    await capturer(page, 'guide-5-reperes', 'guides', 'Guide 5', { attente: 2000, pleine: false, note: 'La dictée de la capture était muette : l’offre est vide. Avec une vraie dictée, les pastilles 1, 2, 3 se posent sur les lignes.' });
    await page.locator('button').filter({ hasText: /^\s*Compris\s*$/ }).first().click();
    await page.waitForTimeout(800);
  });
  await etape('avant l’envoi', async () => {
    await page.goto(BASE + '/');
    await page.locator('a[href^="/offres/"]').filter({ hasText: /séjour/ }).first().click();
    await page.waitForURL(/\/offres\/\d+$/);
    const id = page.url().match(/\/offres\/(\d+)/)[1];
    await page.goto(BASE + `/offres/${id}/apercu`);
    await page.waitForTimeout(2500);
    await page.locator('a, button').filter({ hasText: /^\s*Envoyer\s*$/ }).first().click();
    await capturer(page, 'avant-envoi', 'guides', 'Avant l’envoi', { attente: 1200, pleine: false });
  });
  await etape('fenêtres de fin', async () => {
    sql(`UPDATE artisan SET demo_fin = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 44 HOUR) WHERE id = ${demoId}`);
    await page.goto(BASE + '/');
    await capturer(page, 'demo-fenetre-2-jours', 'demo', '2 jours', { attente: 1200, pleine: false });
    sql(`UPDATE artisan SET demo_fin = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 HOUR) WHERE id = ${demoId}`);
    await page.goto(BASE + '/');
    await capturer(page, 'demo-fenetre-demain', 'demo', 'demain', { attente: 1200, pleine: false });
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
    await capturer(p2, 'demo-perimee', 'demo', 'Lien périmé');
    await p2.close();
  });
  await etape('passage à l’essai', async () => {
    await page.goto(BASE + '/passer-a-l-essai');
    await page.fill('input[name=courriel]', 'essai-relecture@exemple.ch');
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/passer-a-l-essai')), page.click('button[type=submit] >> visible=true')]);
    await capturer(page, 'essai-mot-de-passe', 'demo', 'Mot de passe');
    const champs = page.locator('input[type=password]');
    for (let i = 0; i < await champs.count(); i++) await champs.nth(i).fill('Relecture-des-textes-2026');
    await Promise.all([page.waitForURL((u) => !u.pathname.includes('mot-de-passe')), page.click('button[type=submit] >> visible=true')]);
  });
  for (const nom of ['entreprise', 'tva', 'logo', 'marque']) {
    await etape('mise en route ' + nom, async () => {
      await page.goto(BASE + '/bienvenue/' + nom);
      await capturer(page, 'mise-en-route-' + nom, 'guides', nom);
    });
  }
  await etape('vos prix', async () => {
    await page.goto(BASE + '/vos-prix');
    await capturer(page, 'mise-en-route-prix', 'guides', 'prix');
  });
  await ctx.close();
}

async function courriels(navigateur) {
  // ---------- 4. Les courriels, rendus par la classe Courriel ----------
  await etape('courriels', async () => {
    const unix = '/mnt/c' + path.join(ICI, 'courriels.php').slice(2).replace(/\\/g, '/');
    const liste = JSON.parse(execSync(`wsl -d Ubuntu -e php ${unix}`, { encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } }));
    const c2 = await navigateur.newContext({ viewport: { width: 390, height: 700 }, deviceScaleFactor: 2 });
    await c2.route('**/*', (r) => (r.request().url().startsWith(BASE + '/') ? r.continue() : r.abort()));
    const p = await c2.newPage();
    for (const c of liste) {
      const id = 'courriel-' + c.id;
      await p.setContent(c.html.replace(/>http:\/\/localhost:8010\/lien-d-exemple</g, '>https://dicta.ch/d/exemple<'), { waitUntil: 'load' });
      await p.evaluate((sujet) => {
        const style = document.createElement('style');
        style.textContent = '.rl-objet { font: 14px/1.4 Arial, Helvetica, sans-serif; color: #4B5058; background: #FFFFFF; border-bottom: 1px solid #E3E0D8; padding: 12px 16px; margin: 0 0 8px; } .rl-objet b { color: #1F2328; }';
        document.head.appendChild(style);
        const objet = document.createElement('div');
        objet.className = 'rl-objet';
        objet.append('Objet\u00A0: ');
        const b = document.createElement('b');
        b.textContent = sujet;
        objet.appendChild(b);
        document.body.insertBefore(objet, document.body.firstChild);
      }, c.champs[0][1]);
      await p.screenshot({ path: path.join(IMG, id + '.jpg'), type: 'jpeg', quality: 72, fullPage: true });
      const textes = c.champs.map(([g, t]) => `${g} : ${t}`);
      // Chaque partie du courriel est marquée sur l'élément qui porte exactement son texte.
      await p.evaluate(({ champs, id }) => {
        const norm = (s) => s.replace(/\s+/g, ' ').trim();
        // Dans l'ordre de la page, l'élément le plus profond qui porte le texte : l'objet, en tête, avant un titre identique.
        const els = [...document.body.querySelectorAll('*')];
        const profond = (e, test) => ![...e.children].some((x) => test(norm(x.textContent)));
        champs.forEach(([, t], i) => {
          const libre = (e) => !e.hasAttribute('data-rl') && !e.querySelector('[data-rl]');
          const egal = (x) => x === norm(t);
          const debut = (x) => x.startsWith(norm(t));
          const el = els.find((e) => egal(norm(e.textContent)) && libre(e) && profond(e, egal))
            || els.find((e) => debut(norm(e.textContent)) && libre(e) && profond(e, debut));
          if (el) el.setAttribute('data-rl', id + ':' + i);
        });
      }, { champs: c.champs, id });
      const m = await instantane(p, id);
      captures.push({ id, section: 'courriels', titre: c.titre, image: id + '.jpg', url: '', textes, note: '',
        apercu: { fichier: id, largeur: m.largeur, hauteurPage: m.hauteurPage, decalage: 0, hauteurVue: m.hauteurPage } });
      journal('courriel', id, textes.length);
    }
    await c2.close();
  });
}
