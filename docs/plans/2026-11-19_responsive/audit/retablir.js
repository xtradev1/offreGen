// Remet la quantité de « Rebouchage et ponçage des murs » (offre 17, compte d'audit) à 42, par l'interface.
const fs = require('fs'); const { chromium } = require(process.env.MK_PLAYWRIGHT);
const BASE = 'https://easy-offres.xtradev.ch';
const MDP = fs.readFileSync('//wsl.localhost/Ubuntu/home/xavie/.offregen-audit-compte', 'utf8').split('\n')[1].replace(/^[^:]*:\s*/, '').trim();
(async () => {
  const b = await chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const p = await (await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })).newPage();
  await p.goto(BASE + '/connexion'); await p.fill('#courriel', 'audit-responsive@exemple.ch'); await p.fill('#mot_de_passe', MDP);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit][form=connexion]')]);
  await p.goto(BASE + '/offres/17', { waitUntil: 'networkidle' });
  const ligne = p.locator('.rows .row', { hasText: 'Rebouchage et ponçage des murs' });
  const q = ligne.locator('input[name=quantite]');
  console.log('avant :', await q.inputValue(), '|', (await ligne.locator('.tt').innerText()).trim(), '| total', (await p.locator('[data-total-fixe]').first().innerText().catch(() => '?')).replace(/\s+/g, ' '));
  if ((await q.inputValue()) !== '42') {
    const rep = p.waitForResponse(r => r.request().method() === 'POST');
    await q.click(); await q.fill('42'); await q.press('Enter');
    console.log('envoi', (await rep).status());
    await p.waitForTimeout(800);
  }
  await p.reload({ waitUntil: 'networkidle' });
  const l2 = p.locator('.rows .row', { hasText: 'Rebouchage et ponçage des murs' });
  console.log('après rechargement :', await l2.locator('input[name=quantite]').inputValue(), '|', (await l2.locator('.tt').innerText()).trim(), '|', (await p.locator('.totalbar').innerText()).replace(/\s+/g, ' '));
  console.log('quantités :', (await p.$$eval('.rows input[name=quantite]', a => a.map(e => e.value))).join(', '), '| prix :', (await p.$$eval('.rows input[name=prix]', a => a.map(e => e.value))).join(', '));
  await b.close();
})();
