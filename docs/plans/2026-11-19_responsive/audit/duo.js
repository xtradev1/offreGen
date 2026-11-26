// Ligne du compte de recette téléphone : quantité 0 (refusée par le serveur), puis mesure des deux champs côte à côte.
const fs = require('fs'); const { chromium } = require(process.env.MK_PLAYWRIGHT);
(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const etat = await (async () => { const c = await b.newContext(); const p = await c.newPage();
    await p.goto('http://localhost:8000/connexion'); await p.fill('#courriel', 'recette-tel@exemple.ch');
    await p.fill('#mot_de_passe', fs.readFileSync('//wsl.localhost/Ubuntu/home/xavie/.offregen-recette-tel-compte', 'utf8').trim());
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit][form=connexion]')]); const s = await c.storageState(); await c.close(); return s; })();
  for (const [n, w, h, m] of [['tel', 390, 844, true], ['tab', 820, 1180, true], ['pc', 1366, 768, false]]) {
    const c = await b.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: m ? 2 : 1, isMobile: m, hasTouch: m, storageState: etat }); const p = await c.newPage();
    await p.goto('http://localhost:8000/offres/171/lignes/743', { waitUntil: 'networkidle' });
    await p.fill('#quantite, [name=quantite]', '0');
    await Promise.all([p.waitForLoadState('networkidle'), p.click('button.primary[type=submit][form=ligne]')]);
    await p.waitForTimeout(400);
    const m1 = await p.evaluate(() => [...document.querySelectorAll('.duo')].map(d => [...d.children].map(c => { const i = c.querySelector('input, select'), l = c.querySelector('label'); return `libellé ${l ? Math.round(l.getBoundingClientRect().top) : '-'} champ ${i ? Math.round(i.getBoundingClientRect().top) : '-'}${c.querySelector('.err') ? ' (erreur)' : ''}`; }).join(' / ')).join(' | '));
    console.log(n, m1);
    await p.screenshot({ path: `out6/duo-erreur0-${n}.png` });
    await p.goto('http://localhost:8000/reglages/entreprise', { waitUntil: 'networkidle' });
    console.log(n, 'texte indicatif :', await p.evaluate(() => { const i = document.querySelector('#iban'); return i ? `${getComputedStyle(i, '::placeholder').fontStyle}, ${getComputedStyle(i, '::placeholder').color}` : 'pas de #iban'; }),
      '| Téléphone/IDE :', await p.evaluate(() => ['#telephone', '#ide'].map(s => Math.round(document.querySelector(s).getBoundingClientRect().top)).join('/')));
    await c.close();
  }
  await b.close();
})();
