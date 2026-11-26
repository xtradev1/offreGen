// Vidéo de démonstration de la page de relecture (https://easy-offres.xtradev.ch/relecture) : un correcteur fictif
// suit les gestes un par un. Playwright ne filme pas le curseur : un point le remplace. Sous-titres en bas de l'écran.
// Rien n'est écrit sur le serveur : les corrections restent dans le navigateur de l'enregistrement, jeté à la fin.
'use strict';
const path = require('path');
const fs = require('fs');
const pw = require(process.env.MK_PLAYWRIGHT);
const URL = 'https://easy-offres.xtradev.ch/relecture';
const ICI = __dirname;
const NB = ' ';
const pause = (p, ms) => p.waitForTimeout(ms);

(async () => {
  for (const f of fs.readdirSync(ICI)) if (f.endsWith('.webm')) fs.unlinkSync(path.join(ICI, f));
  const b = await pw.chromium.launch({ executablePath: process.env.MK_CHROMIUM });
  const ctx = await b.newContext({
    viewport: { width: 1280, height: 800 }, deviceScaleFactor: 1, locale: 'fr-CH',
    recordVideo: { dir: ICI, size: { width: 1280, height: 800 } },
  });
  await ctx.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: 'https://easy-offres.xtradev.ch' });
  // Curseur et sous-titres : des éléments posés par script, stylés par leurs propriétés (la CSP de la page refuse
  // une feuille de style ajoutée, pas une propriété posée par script).
  await ctx.addInitScript(() => {
    window.addEventListener('DOMContentLoaded', () => {
      const c = document.createElement('div');
      Object.assign(c.style, { position: 'fixed', zIndex: 99999, width: '22px', height: '22px', borderRadius: '50%', background: 'rgba(196,85,45,.85)',
        border: '3px solid #fff', boxShadow: '0 1px 6px rgba(0,0,0,.45)', left: '640px', top: '400px', marginLeft: '-11px', marginTop: '-11px',
        pointerEvents: 'none', transition: 'transform .12s' });
      document.body.appendChild(c);
      document.addEventListener('mousemove', (e) => { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; }, true);
      document.addEventListener('mousedown', () => { c.style.transform = 'scale(.7)'; }, true);
      document.addEventListener('mouseup', () => { c.style.transform = 'scale(1)'; }, true);
      const s = document.createElement('div');
      Object.assign(s.style, { position: 'fixed', zIndex: 99998, left: '50%', bottom: '96px', transform: 'translateX(-50%)', maxWidth: '980px',
        background: 'rgba(35,39,45,.94)', color: '#fff', font: '600 23px/1.35 Inter, system-ui, sans-serif', padding: '14px 24px', borderRadius: '12px',
        boxShadow: '0 4px 18px rgba(0,0,0,.35)', textAlign: 'center', pointerEvents: 'none', opacity: '0', transition: 'opacity .25s' });
      document.body.appendChild(s);
      window.__legende = (t) => { s.textContent = t; s.style.opacity = t ? '1' : '0'; };
    });
  });
  const p = await ctx.newPage();
  const legende = async (t, ms = 3800) => { await p.evaluate((t) => window.__legende(t), t); if (ms) await pause(p, ms); };
  // aCote : le curseur se pose à droite de l'élément, pour ne pas cacher le texte qu'on montre.
  const viser = async (loc, aCote = false) => {
    await loc.scrollIntoViewIfNeeded();
    const r = await loc.boundingBox();
    const x = aCote ? r.x + r.width + 18 : r.x + Math.min(r.width / 2, 60);
    await p.mouse.move(x, r.y + r.height / 2, { steps: 28 });
    await pause(p, 250);
  };
  const cliquer = async (loc) => { await viser(loc); await loc.click(); };
  const defilerVers = async (sel, decalage = 70) => {
    await p.evaluate(({ sel, decalage }) => window.scrollTo({ top: document.querySelector(sel).getBoundingClientRect().top + scrollY - decalage, behavior: 'smooth' }), { sel, decalage });
    await pause(p, 1400);
  };

  await p.goto(URL, { waitUntil: 'networkidle' });
  await pause(p, 800);

  // 1. L'arrivée
  await legende(`Voici la page de relecture des textes de Dicta.`);
  await legende(`Tout se fait ici, sans compte${NB}: les consignes sont en haut de la page.`, 4200);

  // 2. Le nom
  const nom = p.locator('#nom');
  await cliquer(nom);
  await legende(`Indiquez d’abord votre nom.`, 600);
  await nom.pressSequentially('Marie Exemple', { delay: 90 });
  await pause(p, 1200);

  // 3. Le sommaire
  await legende(`Le sommaire mène aux quatre parties${NB}: le site, la démo, l’essai, les courriels.`, 1200);
  await cliquer(p.locator('#mk-toc a').nth(1));
  await pause(p, 1800);

  // 4. Un écran
  await defilerVers('#e-guide-2-conseils', 60);
  await pause(p, 1500);
  await legende(`À gauche, l’écran tel que l’artisan le voit. À droite, ses textes, un par ligne.`, 4500);

  // 5. Cliquer dans un texte
  const lignes = p.locator('#e-guide-2-conseils .texte');
  const c1 = lignes.nth(0).locator('textarea');
  await legende(`Cliquez dans «${NB}Votre version${NB}»${NB}: le texte s’entoure dans l’écran.`, 900);
  await cliquer(c1);
  await pause(p, 2600);

  // 6. Corriger
  await legende(`Corrigez${NB}: l’écran change en même temps, surligné en jaune.`, 900);
  await c1.press('End');
  await c1.pressSequentially('.', { delay: 200 });
  await pause(p, 3200);

  // 7. Les différences
  await viser(lignes.nth(0).locator('.diff'), true);
  await legende(`La colonne «${NB}Différences${NB}» montre le changement${NB}: en vert l’ajout, en rouge barré le retrait.`, 4800);

  // 8. L'espace insécable
  const c2 = lignes.nth(1).locator('textarea');
  await cliquer(c2);
  await legende(`Ctrl+Maj+Espace pose une espace insécable, marquée ⍽ dans les différences.`, 900);
  await c2.evaluate((el) => { const i = el.value.lastIndexOf(' »'); el.setSelectionRange(i, i + 1); });
  await pause(p, 700);
  await p.keyboard.press('Control+Shift+Space');
  await pause(p, 900);
  await viser(lignes.nth(1).locator('.diff'), true);
  await pause(p, 3200);

  // 9. Revenir à l'original
  const c3 = lignes.nth(2).locator('textarea');
  await cliquer(c3);
  await c3.press('End');
  await c3.pressSequentially(' Merci.', { delay: 90 });
  await legende(`Une correction de trop${NB}? «${NB}Revenir à l’original${NB}» l’annule.`, 1400);
  await cliquer(lignes.nth(2).locator('.revenir'));
  await pause(p, 2600);

  // 10. Remarque sur l'écran
  const rem = p.locator('#r-guide-2-conseils');
  await cliquer(rem);
  await legende(`Une remarque sur tout l’écran va dans ce champ.`, 600);
  await rem.pressSequentially(`La bulle cache le haut de la fenêtre.`, { delay: 55 });
  await pause(p, 1800);

  // 11. La capture d'origine
  await legende(`La capture d’origine reste à un clic.`, 800);
  await cliquer(p.locator('#e-guide-2-conseils .voir-origine'));
  await pause(p, 2600);
  await p.keyboard.press('Escape');
  await pause(p, 800);
  await legende('', 300);

  // 12. Un courriel
  await defilerVers('#e-courriel-rappel-2-jours', 60);
  await pause(p, 1800);
  const objet = p.locator('#e-courriel-rappel-2-jours .texte').first().locator('textarea');
  await cliquer(objet);
  await legende(`Les courriels aussi${NB}: l’objet corrigé s’affiche en tête du courriel.`, 900);
  await objet.evaluate((el) => { const i = el.value.indexOf('se termine'); el.setSelectionRange(i, i + 'se termine'.length); });
  await pause(p, 600);
  await objet.pressSequentially('finit', { delay: 150 });
  await pause(p, 3600);

  // 13. Copier
  await legende(`À la fin, «${NB}Copier mes corrections${NB}», puis collez le tout dans un courriel.`, 900);
  await cliquer(p.locator('#copier'));
  await pause(p, 1200);
  await defilerVers('#export', 120);
  await pause(p, 3800);

  // 14. Fin
  await legende(`Vos corrections restent dans ce navigateur, même si vous fermez la page.`, 4200);
  await legende('', 600);

  const video = p.video();
  await ctx.close();
  await b.close();
  console.log(await video.path());
})();
