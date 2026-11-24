// Capture de chaque courriel rendu par courriels.php, dans une fenêtre de téléphone ; ajoute les entrées à captures.json.
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const pw = require(process.env.MK_PLAYWRIGHT);
const ICI = __dirname;
const unix = '/mnt/c' + path.join(ICI, 'courriels.php').slice(2).replace(/\\/g, '/');
const liste = JSON.parse(execSync(`wsl -d Ubuntu -e php ${unix}`, { encoding: 'utf8', env: { ...process.env, MSYS_NO_PATHCONV: '1' } }));
(async () => {
  const b = await pw.chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const ctx = await b.newContext({ viewport: { width: 390, height: 700 }, deviceScaleFactor: 2 });
  // Rien d'autre que le serveur local : toute autre adresse est coupée.
  await ctx.route('**/*', (r) => (r.request().url().startsWith('http://localhost:8010/') || r.request().url().startsWith('data:') ? r.continue() : r.abort()));
  const page = await ctx.newPage();
  const sortie = [];
  for (const c of liste) {
    // Le lien affiché en clair prend l'allure d'un vrai lien ; le symbole reste chargé du serveur local.
    await page.setContent(c.html.replace(/>http:\/\/localhost:8010\/lien-d-exemple</g, '>https://dicta.ch/d/exemple<'), { waitUntil: 'load' });
    await page.screenshot({ path: path.join(ICI, 'img', `courriel-${c.id}.jpg`), type: 'jpeg', quality: 72, fullPage: true });
    sortie.push({ id: 'courriel-' + c.id, section: 'courriels', titre: c.titre, image: `courriel-${c.id}.jpg`, url: '', textes: c.champs.map(([g, t]) => `${g} : ${t}`), note: '' });
    console.log('courriel', c.id, c.champs.length);
  }
  await b.close();
  const anciens = JSON.parse(fs.readFileSync(path.join(ICI, 'captures.json'), 'utf8')).filter((x) => x.section !== 'courriels');
  fs.writeFileSync(path.join(ICI, 'captures.json'), JSON.stringify(anciens.concat(sortie), null, 1));
})();
