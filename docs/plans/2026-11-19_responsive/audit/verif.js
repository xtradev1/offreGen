// Contre-recette des défauts relevés par les trois agents, après correction. Base locale, comptes de recette (tout
// permis) et compte de démonstration (lecture seule : aucun formulaire validé, aucune saisie).
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.MK_PLAYWRIGHT);
const B = 'http://localhost:8000';
const OUT = path.join(__dirname, 'out6');
fs.mkdirSync(OUT, { recursive: true });
const mdp = id => fs.readFileSync(`//wsl.localhost/Ubuntu/home/xavie/.offregen-${id}-compte`, 'utf8').trim().split('\n')[0].trim();
const T = { tel: [390, 844, true], t360: [360, 740, true], couche: [844, 390, true], tab: [820, 1180, true], tabl: [1180, 820, true], pc: [1366, 768, false], pc19: [1920, 1080, false], pc9: [900, 900, false] };
const r = {};

(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const etats = {};
  for (const [id, courriel, fichier] of [['tel', 'recette-tel@exemple.ch', 'recette-tel'], ['tab', 'recette-tab@exemple.ch', 'recette-tab'], ['pc', 'recette-pc@exemple.ch', 'recette-pc'], ['demo', 'demo-peintre@exemple.ch', 'demo']]) {
    const c = await b.newContext(); const p = await c.newPage();
    await p.goto(B + '/connexion'); await p.fill('#courriel', courriel); await p.fill('#mot_de_passe', mdp(fichier));
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit][form=connexion]')]);
    if (p.url().includes('/connexion')) throw new Error('refus ' + id);
    etats[id] = await c.storageState(); await c.close();
  }
  const page = async (compte, t, theme = 'light') => {
    const [w, h, m] = T[t];
    const ctx = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: m ? 2 : 1, isMobile: m, hasTouch: m, colorScheme: theme, storageState: etats[compte] });
    if (compte === 'demo') await ctx.route('**/*', x => (x.request().method() === 'GET' ? x.continue() : x.abort()));
    const p = await ctx.newPage(); p.__e = []; p.on('pageerror', e => p.__e.push(String(e).slice(0, 100)));
    return { ctx, p };
  };
  const cap = (p, n, o = {}) => p.screenshot({ path: path.join(OUT, n + '.png'), ...o });
  const debord = p => p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

  // 1. Champs côte à côte avec une erreur (ligne envoyée vide, compte de recette) : téléphone et tablette.
  for (const t of ['tel', 'tab']) {
    const { ctx, p } = await page('tel', t);
    await p.goto(B + '/offres/171/lignes/743', { waitUntil: 'networkidle' });
    await p.fill('[name=quantite]', '');
    await Promise.all([p.waitForLoadState('networkidle'), p.click('.actionbar button[type=submit], button.primary[type=submit]')]);
    await p.waitForTimeout(400);
    r['duo-erreur-' + t] = await p.evaluate(() => [...document.querySelectorAll('.duo')].map(d => [...d.children].map(c => { const ch = c.querySelector('input, select'); return ch ? Math.round(ch.getBoundingClientRect().top) : null; }).join(' / ')).join(' | ') + ' | erreur : ' + (document.querySelector('.duo .err') ? 'oui' : 'non'));
    await cap(p, 'duo-erreur-' + t);
    await ctx.close();
  }
  // Facturer, Modifier ouvert, et nouveau client : rangées de deux champs.
  for (const [n, url] of [['facturer', '/offres/171/facturer'], ['client', '/clients/nouveau']]) {
    const { ctx, p } = await page('tel', 'tel');
    await p.goto(B + url, { waitUntil: 'networkidle' });
    await p.evaluate(() => document.querySelectorAll('details').forEach(d => { d.open = true; }));
    r['duo-' + n] = await p.evaluate(() => [...document.querySelectorAll('.duo')].map(d => [...d.children].map(c => { const ch = c.querySelector('input, select'); return ch ? Math.round(ch.getBoundingClientRect().top) : null; }).join('/')).join(' | '));
    r['placeholder-' + n] = await p.evaluate(() => { const i = document.querySelector('input[placeholder]'); return i ? getComputedStyle(i, '::placeholder').fontStyle : 'aucun'; });
    r['debord-' + n] = await debord(p);
    await ctx.close();
  }

  // 2. Lignes signalées (démo, offre 910) : colonnes alignées, téléphone et PC.
  for (const t of ['tel', 'pc']) {
    const { ctx, p } = await page('demo', t);
    await p.goto(B + '/offres/160', { waitUntil: 'networkidle' });
    r['flag-' + t] = await p.evaluate(() => { const rows = [...document.querySelectorAll('.rows .row')].filter(x => x.querySelector('.nm')); const x = s => [...new Set(rows.map(r => r.querySelector(s)).filter(Boolean).map(e => Math.round(e.getBoundingClientRect().left)))]; return { nom: x('.nm'), qte: x('.q .saisie'), prix: x('.p .saisie'), signalees: rows.filter(r => r.matches('.flag, .miss')).length, debord: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
    await p.evaluate(() => { const e = document.querySelector('.row.flag, .row.miss'); if (e) e.scrollIntoView({ block: 'center' }); });
    await cap(p, 'flag-' + t);
    await ctx.close();
  }

  // 3. Document à côté (démo, 1366 et 1920) : corbeille dans sa colonne ; « Qté » au-dessus du champ.
  for (const t of ['pc', 'pc19']) {
    const { ctx, p } = await page('demo', t);
    await p.goto(B + '/offres/161', { waitUntil: 'networkidle' });
    if (!(await p.evaluate(() => document.body.classList.contains('doc-a-cote')))) await p.click('.ohead .doc-cote');
    await p.waitForTimeout(800);
    r['doc-cote-' + t] = await p.evaluate(() => { const rows = document.querySelector('.rows'); const del = [...document.querySelectorAll('.rows .del')].map(d => Math.round(d.getBoundingClientRect().right)); const cat = document.querySelector('.rows .cat .ch:nth-child(2)'), q = document.querySelector('.rows .q .saisie'); return { rangee: Math.round(rows.getBoundingClientRect().right), corbeilleMax: Math.max(...del), qteTitreDroite: Math.round(cat.getBoundingClientRect().right - parseFloat(getComputedStyle(cat).paddingRight)), qteChampDroite: Math.round(q.getBoundingClientRect().right) }; });
    await ctx.close();
  }
  { const { ctx, p } = await page('demo', 'couche'); await p.goto(B + '/offres/161', { waitUntil: 'networkidle' });
    r['qte-couche'] = await p.evaluate(() => { const cat = document.querySelector('.rows .cat .ch:nth-child(2)'), q = document.querySelector('.rows .q .saisie'); return { titreDroite: Math.round(cat.getBoundingClientRect().right - parseFloat(getComputedStyle(cat).paddingRight)), champDroite: Math.round(q.getBoundingClientRect().right) }; });
    await ctx.close(); }

  // 4. Tablette couchée : largeur des écrans sans lignes, document, aperçu, TVA, accueil, liste à côté de l'offre.
  for (const [n, url, compte] of [['apercu', '/offres/173/apercu', 'tab'], ['document', '/offres/173/document', 'tab'], ['tva', '/reglages/tva', 'tab'], ['accueil', '/', 'tab'], ['client-offre', '/offres/173/client', 'tab'], ['offre', '/offres/173', 'tab'], ['facture', '/factures/27/apercu', 'tab'], ['facturer', '/offres/172/facturer', 'tab']]) {
    const { ctx, p } = await page(compte, 'tabl');
    await p.goto(B + url, { waitUntil: 'networkidle' }); await p.waitForTimeout(900);
    r['tab-' + n] = await p.evaluate(() => { const s = document.querySelector('.screen'); const plusLarge = Math.max(...[...s.children].map(c => Math.round(c.getBoundingClientRect().width))); const cv = document.querySelector('.pdf-pages canvas, article.paper'); return { plusLarge, document: cv ? Math.round(cv.getBoundingClientRect().width) : null, debord: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
    if (['apercu', 'accueil', 'tva'].includes(n)) await cap(p, 'tab-' + n);
    await ctx.close();
  }
  { const { ctx, p } = await page('demo', 'tabl'); await p.goto(B + '/offres/161', { waitUntil: 'networkidle' });
    r['lcard'] = await p.evaluate(() => [...document.querySelectorAll('.lcard b')].map(e => ({ coupe: e.scrollHeight > e.clientHeight + 1, h: Math.round(e.getBoundingClientRect().height) })));
    await cap(p, 'tab-lcard'); await ctx.close(); }

  // 5. Fenêtre du micro refusé (téléphone) : la croix ne couvre plus le message.
  { const { ctx, p } = await page('tel', 'tel'); await p.goto(B + '/', { waitUntil: 'networkidle' });
    await p.click('.tabs .micro-central'); await p.waitForTimeout(1200);
    r['micro-refus'] = await p.evaluate(() => { const x = document.querySelector('.feuille-fond.ouvert .x.coin'), a = document.querySelector('.feuille-fond.ouvert [data-etat="refus"]:not([hidden]) .alerte'); if (!x || !a) return 'état refus non affiché : ' + [...document.querySelectorAll('.feuille-fond.ouvert [data-etat]:not([hidden])')].map(e => e.dataset.etat).join(','); const q = x.getBoundingClientRect(), b = a.getBoundingClientRect(); return { recouvrement: Math.max(0, Math.min(q.bottom, b.bottom) - Math.max(q.top, b.top)) > 0 && Math.min(q.right, b.right) > Math.max(q.left, b.left) ? `${Math.round(Math.min(q.right, b.right) - Math.max(q.left, b.left))}x${Math.round(Math.min(q.bottom, b.bottom) - Math.max(q.top, b.top))}` : 'aucun' }; });
    await cap(p, 'micro-refus'); await ctx.close(); }
  // Téléphone couché, état « enregistrement » affiché sans enregistrer (comme l'agent) : le lien tient dans la fenêtre.
  { const { ctx, p } = await page('tel', 'couche'); await p.goto(B + '/', { waitUntil: 'networkidle' });
    await p.click('.tabs .micro-central'); await p.waitForTimeout(1000);
    r['micro-couche'] = await p.evaluate(() => { const f = document.querySelector('.feuille-fond.ouvert .feuille'); if (!f) return 'pas de fenêtre'; f.querySelectorAll('[data-etat]').forEach(e => { e.hidden = e.dataset.etat !== 'enregistrement'; }); const rec = f.querySelector('[data-etat="enregistrement"]'); const liens = [...rec.querySelectorAll('a, button')].filter(e => e.getBoundingClientRect().width); const bf = f.getBoundingClientRect(); return { fenetre: [Math.round(bf.top), Math.round(bf.bottom)], contenu: f.scrollHeight, visible: f.clientHeight, dernier: liens.length ? Math.round(liens[liens.length - 1].getBoundingClientRect().bottom) : null }; });
    await cap(p, 'micro-couche'); await ctx.close(); }

  // 6. Cibles des factures : étiquettes sur l'offre, liste de Facturer, panneau de la facture, brouillon.
  for (const [n, url, sel] of [['etiquettes', '/offres/171', '.factures-offre a'], ['facturer', '/offres/171/facturer', '.liste-factures a'], ['panneau', '/factures/33/apercu', '.panneau-facture dd a'], ['brouillon', '/factures/34', '.lien-offre, .ohead a.t']]) {
    const { ctx, p } = await page('tel', 'tel'); await p.goto(B + url, { waitUntil: 'networkidle' });
    r['cible-' + n] = await p.evaluate(s => [...document.querySelectorAll(s)].map(e => `${Math.round(e.getBoundingClientRect().width)}x${Math.round(e.getBoundingClientRect().height)}`).join(' ') || 'aucun élément', sel);
    if (n === 'etiquettes') await cap(p, 'cible-etiquettes');
    await ctx.close();
  }

  // 7. Recherche des clients sans résultat ; bords des puces ; chevrons des volets ; texte d'erreur 409.
  { const { ctx, p } = await page('tel', 'tel'); await p.goto(B + '/clients', { waitUntil: 'networkidle' });
    await p.fill('#kq', 'zzzz'); await p.waitForTimeout(400);
    r['clients-vide'] = await p.evaluate(() => ({ compteur: getComputedStyle(document.querySelector('.compteur')).display, aucun: !document.querySelector('[data-aucun]').hidden }));
    await p.goto(B + '/catalogue/nouveau', { waitUntil: 'networkidle' });
    r['chevron'] = await p.evaluate(() => [...document.querySelectorAll('summary')].map(s => getComputedStyle(s, '::after').content).join(' '));
    await p.goto(B + '/reglages/conditions', { waitUntil: 'networkidle' });
    r['puce-bord'] = await p.evaluate(() => { const e = document.querySelector('.puce'); return e ? getComputedStyle(e).borderTopColor : 'aucune'; });
    await p.goto(B + '/factures/30/annuler', { waitUntil: 'networkidle' });
    r['409'] = await p.evaluate(() => ({ doublePoint: /\.\.(\s|$)/.test(document.body.innerText), texte: (document.querySelector('.muted') || {}).innerText }));
    await ctx.close(); }

  // 8. Bandeau au-dessus du micro : archiver une offre du compte de recette, regarder, restaurer.
  { const { ctx, p } = await page('tel', 'tel'); await p.goto(B + '/', { waitUntil: 'networkidle' });
    const n0 = await p.locator('.glisse').count();
    await p.evaluate(() => { const g = document.querySelector('.glisse'); g.classList.add('ouverte'); });
    await Promise.all([p.waitForNavigation(), p.click('.glisse.ouverte .arch')]);
    await p.waitForTimeout(300);
    r['snack-micro'] = await p.evaluate(() => { const s = document.querySelector('.snack'), m = document.querySelector('.tabs .micro-central > span'); if (!s) return 'pas de bandeau'; return { snackBas: Math.round(s.getBoundingClientRect().bottom), microHaut: Math.round(m.getBoundingClientRect().top) }; });
    await cap(p, 'snack-micro');
    const annuler = p.locator('.snack button');
    if (await annuler.count()) { await Promise.all([p.waitForNavigation(), annuler.click()]); }
    r['snack-restaure'] = (await p.locator('.glisse').count()) === n0;
    await ctx.close(); }

  // 9. PC : survol, relecture, bande blanche du bas, largeur.
  { const { ctx, p } = await page('demo', 'pc'); await p.goto(B + '/', { waitUntil: 'networkidle' });
    const avant = await p.evaluate(() => getComputedStyle(document.querySelector('.ocard')).borderTopColor);
    await p.hover('.ocard'); await p.waitForTimeout(200);
    r['survol'] = { avant, apres: await p.evaluate(() => getComputedStyle(document.querySelector('.ocard')).borderTopColor) };
    r['bande-bas'] = await p.evaluate(() => { const n = document.querySelector('.bas > .nouvelle'); return n ? Math.round(n.getBoundingClientRect().height) : 'absente'; });
    await p.goto(B + '/offres/160/relire', { waitUntil: 'networkidle' });
    r['relecture'] = await p.evaluate(() => { const a = document.querySelector('.relu-reste'), c = document.querySelector('.relu-carte, .relu-nom'); return a && c ? Math.round(c.getBoundingClientRect().top - a.getBoundingClientRect().bottom) : 'élément absent'; });
    await cap(p, 'relecture-pc'); await ctx.close(); }

  // 10. Document du client au téléphone : totaux sur une ligne ; pied à 7:1.
  { const { ctx, p } = await page('demo', 'tel'); await p.goto(B + '/offres/161/document', { waitUntil: 'networkidle' });
    r['doc-totaux'] = await p.evaluate(() => [...document.querySelectorAll('.doc-totaux td:first-child')].map(td => `${td.innerText.trim()} ${Math.round(td.getBoundingClientRect().height)}`).join(' ; '));
    r['doc-pied'] = await p.evaluate(() => getComputedStyle(document.querySelector('.doc-pied')).color);
    await p.evaluate(() => document.querySelector('.doc-totaux').scrollIntoView({ block: 'center' }));
    await cap(p, 'doc-totaux-390'); await ctx.close(); }

  // 11. PDF : nombre de pages et texte de la dernière (offre de 8 lignes, factures finales).
  for (const [n, compte, url] of [['offre175', 'pc', '/offres/175/pdf'], ['offre161', 'demo', '/offres/161/pdf'], ['facture33', 'tel', '/factures/33/pdf'], ['facture28', 'pc', '/factures/28/pdf']]) {
    const { ctx, p } = await page(compte, 'pc');
    const rep = await ctx.request.get(B + url);
    fs.writeFileSync(path.join(OUT, n + '.pdf'), await rep.body());
    r['pdf-' + n] = rep.status();
    await ctx.close();
  }
  fs.writeFileSync(path.join(OUT, 'verif.json'), JSON.stringify(r, null, 1));
  console.log(JSON.stringify(r, null, 1));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
