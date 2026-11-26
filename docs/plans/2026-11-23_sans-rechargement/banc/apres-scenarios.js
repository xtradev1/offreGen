// Les 47 gestes du banc, après les lots 1 à 5 (chantier « sans rechargement », 2026-11-23). Mêmes identifiants, mêmes
// données (offregen_fluidite_vue, instantané remis avant chaque geste), mêmes touchers que scenarios.js. Ce qui change :
// faire() rend la main quand l'écran a changé (« vu ») ; la mesure attend ensuite la confirmation du serveur (file de
// fluide.js vide, et confirmer() quand le geste a un effet visible à la réponse). nav : le geste ouvre une autre page,
// mesurée comme avant (premier affichage de la nouvelle page).
'use strict';

const chargement = async (page, geste) => {
  const fin = page.waitForEvent('load', { timeout: 60000 });
  fin.catch(() => {});
  await geste(page);
  await fin;
};
const clic = (sel) => async (page) => { await page.locator(sel).first().click({ timeout: 15000 }); };
const quand = (page, f, arg) => page.waitForFunction(f, arg === undefined ? null : arg, { timeout: 60000, polling: 16 });
const glisserCarte = async (page, offre) => {
  const carte = page.locator(`[data-glisse]:has(form[action="/offres/${offre}/archiver"]) .ocard`);
  const b = await carte.boundingBox();
  const y = b.y + b.height / 2;
  await page.mouse.move(b.x + b.width - 30, y);
  await page.mouse.down();
  for (let dx = 20; dx <= 220; dx += 20) await page.mouse.move(b.x + b.width - 30 - dx, y);
  await page.mouse.up();
  await page.waitForFunction((o) => document.querySelector(`[data-glisse]:has(form[action="/offres/${o}/archiver"])`).classList.contains('ouverte'), offre, { timeout: 5000 });
  await page.waitForTimeout(350);
};
const feuilleOuverte = (page) => quand(page, () => {
  const f = document.querySelector('[data-feuille-page]');
  const c = f && f.querySelector('[data-feuille-corps]');
  return f && !f.hidden && f.classList.contains('ouvert') && c && c.children.length > 0;
});
const feuilleFermee = (page) => quand(page, () => { const f = document.querySelector('[data-feuille-page]'); return !f || !f.classList.contains('ouvert'); });
const calme = (page) => quand(page, () => !window.fluide || !window.fluide.occupe());
const ouvrirFeuille = async (page, sel) => { await page.click(sel); await feuilleOuverte(page); await page.waitForTimeout(400); };

module.exports = [
  // ---------- Liste des offres
  { id: 'liste-ouvrir-offre', nav: true, depart: '/', faire: (p) => chargement(p, clic('a.ocard[href="/offres/3"]')) },
  { id: 'liste-tri', depart: '/', faire: async (p) => { await p.click('nav.tri a:nth-child(2)'); await quand(p, () => document.querySelector('nav.tri a:nth-child(2)').getAttribute('aria-current') === 'true'); } },
  { id: 'liste-archiver', depart: '/', preparer: (p) => glisserCarte(p, 2),
    faire: async (p) => { await p.click('form[action="/offres/2/archiver"] button'); await quand(p, () => document.getElementById('o2').hidden); } },
  { id: 'liste-supprimer', depart: '/', preparer: (p) => glisserCarte(p, 1),
    faire: async (p) => { await p.click('form[action="/offres/1/supprimer"] button'); await quand(p, () => document.getElementById('o1').hidden); } },
  { id: 'liste-restaurer', depart: '/', preparer: async (p) => { await p.click('details.archives summary'); },
    faire: async (p) => { await p.click('form[action="/offres/5/restaurer"] button'); await quand(p, () => document.getElementById('a5').hidden); },
    confirmer: (p) => quand(p, () => !!document.getElementById('o5')) },
  { id: 'liste-retour', nav: true, depart: '/', preparer: async (p) => { await chargement(p, clic('a.ocard[href="/offres/3"]')); },
    faire: (p) => chargement(p, async () => { await p.goBack({ waitUntil: 'commit' }); }) },

  // ---------- Onglets
  { id: 'onglet-clients', nav: true, depart: '/', faire: (p) => chargement(p, clic('a[href="/clients"]:visible')) },
  { id: 'onglet-plus', nav: true, depart: '/', faire: (p) => chargement(p, clic('a[href="/plus"]:visible')) },
  { id: 'plus-catalogue', nav: true, depart: '/plus', faire: (p) => chargement(p, clic('a[href="/catalogue"]:visible')) },
  { id: 'plus-reglages', nav: true, depart: '/plus', faire: (p) => chargement(p, clic('a[href="/reglages"]:visible')) },

  // ---------- Offre : lignes
  { id: 'offre-juste', depart: '/offres/3',
    faire: async (p) => { await p.click('#l14 form[action$="/juste"] button'); await quand(p, () => !document.getElementById('l14').classList.contains('flag')); } },
  { id: 'offre-corbeille', depart: '/offres/3',
    faire: async (p) => { await p.click('#l37 form[action$="/supprimer"] button'); await quand(p, () => document.getElementById('l37').hidden && !!document.querySelector('.app > form.snack')); } },
  { id: 'offre-annuler', depart: '/offres/3',
    preparer: async (p) => { await p.click('#l37 form[action$="/supprimer"] button'); await calme(p); await p.waitForTimeout(300); },
    faire: async (p) => { await p.click('.app > form.snack[action$="/37/restaurer"] button'); await quand(p, () => !!document.getElementById('l37') && !document.getElementById('l37').hidden); } },
  { id: 'offre-prix-dicte', depart: '/offres/3',
    faire: async (p) => { await p.click('#l15 form.prix-dit button'); await quand(p, () => !!document.querySelector('#l15 form.prix-dit button[value="oui"]')); } },
  { id: 'offre-prix-catalogue', depart: '/offres/3',
    preparer: async (p) => { await p.click('#l15 form.prix-dit button'); await quand(p, () => !!document.querySelector('#l15 form.prix-dit button[value="oui"]')); await calme(p); },
    faire: async (p) => { await p.click('#l15 form.prix-dit button[value="oui"]'); await quand(p, () => !document.querySelector('#l15 form.prix-dit')); } },
  { id: 'offre-quantite', depart: '/offres/3',
    faire: async (p) => { await p.fill('#l12 input[name=quantite]', '27'); await p.press('#l12 input[name=quantite]', 'Enter'); await quand(p, () => document.querySelector('#l12 .tt').classList.contains('provisoire') || document.querySelector('#l12 input[name=quantite]').defaultValue === '27'); },
    confirmer: (p) => quand(p, () => !document.querySelector('#l12 .tt.provisoire')) },
  { id: 'offre-prix', depart: '/offres/3',
    faire: async (p) => { await p.fill('#l13 input[name=prix]', '19.50'); await p.press('#l13 input[name=prix]', 'Enter'); await quand(p, () => document.querySelector('#l13 .tt').classList.contains('provisoire') || document.querySelector('#l13 input[name=prix]').defaultValue === '19.50'); },
    confirmer: (p) => quand(p, () => !document.querySelector('#l13 .tt.provisoire')) },
  { id: 'offre-option', depart: '/offres/3',
    faire: async (p) => { await p.check('#l41 input[type=checkbox][name=retenue]'); await quand(p, () => document.querySelector('#l41 input[type=checkbox][name=retenue]').checked); },
    confirmer: (p) => quand(p, () => /Retenue,/.test(document.querySelector('#l41 form[data-choix]').textContent)) },
  { id: 'offre-texte', depart: '/offres/3',
    faire: async (p) => { await p.fill('#texte-avant', 'Suite à notre visite du 20 novembre, voici notre offre.'); await p.locator('#texte-avant').blur(); await quand(p, () => /Enregistr/.test(document.getElementById('sauvegarde').textContent)); },
    confirmer: (p) => quand(p, () => /Enregistrée le/.test(document.getElementById('sauvegarde').textContent)) },
  { id: 'offre-ouvrir-ligne', depart: '/offres/3', faire: async (p) => { await p.click('#l12 a.nm'); await feuilleOuverte(p); } },
  { id: 'ligne-enregistrer', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, '#l12 a.nm'); await p.fill('[data-feuille-corps] #ligne input[name=quantite]', '31'); },
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="ligne"]'); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => document.querySelector('#l12 input[name=quantite]').value === '31') },

  // ---------- Offre : fenêtres du bas
  { id: 'ajout-ouvrir', depart: '/offres/3', faire: async (p) => { await p.click('a.btn.ajout'); await feuilleOuverte(p); } },
  { id: 'ajout-produit', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, 'a.btn.ajout'); await p.evaluate(() => { window.__lignes = document.querySelectorAll('#lignes .row').length; }); },
    faire: async (p) => { await p.locator('[data-feuille-corps] #res form:not([hidden]) button.pick').nth(5).click(); await quand(p, () => !!document.querySelector('[data-feuille-corps] .ajoutee')); },
    confirmer: (p) => quand(p, () => document.querySelectorAll('#lignes .row').length > window.__lignes) },
  { id: 'ajout-termine', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, 'a.btn.ajout'); await p.locator('[data-feuille-corps] #res form:not([hidden]) button.pick').nth(5).click(); await calme(p); await p.waitForTimeout(300); },
    faire: async (p) => { await p.click('[data-feuille-pied] a.btn.primary'); await feuilleFermee(p); } },
  { id: 'ajout-libre', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, 'a.btn.ajout'); await p.fill('[data-feuille-corps] #cherche', 'Nettoyage spécial des stores'); },
    faire: async (p) => { await p.click('[data-feuille-corps] form.pick-libre button'); await quand(p, () => !!document.querySelector('[data-feuille-corps] .ajoutee')); },
    confirmer: (p) => quand(p, () => [...document.querySelectorAll('#lignes .row a.nm')].some((a) => a.textContent.includes('Nettoyage spécial des stores'))) },
  { id: 'client-offre', depart: '/offres/3', preparer: (p) => ouvrirFeuille(p, 'a.who'),
    faire: async (p) => { await p.click('[data-feuille-corps] button.kchoix:not(.on)'); await feuilleFermee(p); } },
  { id: 'representant', depart: '/offres/3', preparer: (p) => ouvrirFeuille(p, 'a.rep'),
    faire: async (p) => { await p.click('[data-feuille-corps] button.kchoix'); await feuilleFermee(p); } },
  { id: 'rabais', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, 'a.lien.plus'); await p.fill('[data-feuille-corps] #libelle', 'Rabais fidélité'); await p.locator('[data-feuille-corps] label.puce:has(input[value="5"])').click(); },
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="rabais"]'); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => /Rabais fidélité/.test(document.getElementById('totaux').textContent)) },
  { id: 'conditions', depart: '/offres/3', preparer: (p) => ouvrirFeuille(p, 'a.carte-lien.conditions'),
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="conditions"]'); await feuilleFermee(p); } },
  { id: 'ligne-rattacher', depart: '/offres/3', preparer: (p) => ouvrirFeuille(p, '#l65 a.vers-catalogue'),
    faire: async (p) => { await p.locator('[data-feuille-corps] form[action$="/rattacher"]:not([hidden]) button:visible').first().click(); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => !document.querySelector('#l65 a.vers-catalogue')) },
  { id: 'ligne-creer-produit', depart: '/offres/3',
    preparer: async (p) => { await ouvrirFeuille(p, '#l65 a.vers-catalogue'); await p.click('[data-feuille-corps] label:has(input[value="nouveau"])'); },
    faire: async (p) => { await p.click('[data-feuille-corps] form#nouveau-produit button:has-text("Créer et ranger")'); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => !document.querySelector('#l65 a.vers-catalogue')) },

  // ---------- Offre : écrans
  { id: 'offre-objet', depart: '/offres/3', preparer: (p) => ouvrirFeuille(p, '#carte-objet'),
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="entete"]'); await feuilleFermee(p); } },
  { id: 'relire-ouvrir', nav: true, depart: '/offres/3', faire: (p) => chargement(p, clic('a[href="/offres/3/relire"]')) },
  { id: 'relire-juste', depart: '/offres/3/relire',
    faire: async (p) => { const id = await p.locator('section[data-relu]:not([hidden])').getAttribute('data-relu'); await p.click('[data-relu-gestes] button[type=submit]'); await quand(p, (x) => document.querySelector('section[data-relu="' + x + '"]').hidden, id); } },
  { id: 'generer', nav: true, depart: '/offres/2',
    faire: async (p) => {
      await chargement(p, clic('a[href="/offres/2/apercu"]'));
      await p.waitForSelector('[data-pdf] canvas', { timeout: 60000 });
      await quand(p, () => !/Préparation/.test((document.querySelector('[data-pdf-etat]') || {}).textContent || ''));
    } },
  // Le cas honnête du PDF : une quantité changée juste avant (valeur tirée au sort), donc un PDF que ni le serveur ni le
  // téléphone n'ont encore. Avant le chantier, chaque ouverture refaisait le PDF : c'est ce cas qui se compare à « generer ».
  { id: 'generer-modifiee', nav: true, depart: '/offres/2',
    preparer: async (p) => {
      const champ = p.locator('#lignes input[name=quantite]').first();
      await champ.fill(String(100 + Math.floor(Math.random() * 800)));
      await champ.press('Enter');
      await calme(p);
    },
    faire: async (p) => {
      await chargement(p, clic('a[href="/offres/2/apercu"]'));
      await p.waitForSelector('[data-pdf] canvas', { timeout: 60000 });
      await quand(p, () => !/Préparation/.test((document.querySelector('[data-pdf-etat]') || {}).textContent || ''));
    } },
  { id: 'facturer-ouvrir', depart: '/offres/4', faire: async (p) => { await p.click('a[href="/offres/4/facturer"]'); await feuilleOuverte(p); await p.waitForSelector('[data-feuille-corps] [data-apercu-mini] .apercu-facture', { timeout: 60000 }); } },
  { id: 'facturer-creer', nav: true, depart: '/offres/4',
    preparer: async (p) => { await p.click('a[href="/offres/4/facturer"]'); await feuilleOuverte(p); await p.waitForSelector('[data-feuille-corps] [data-apercu-mini] .apercu-facture', { timeout: 60000 }); },
    faire: (p) => chargement(p, clic('button[form="facturer"][value="creer"]')) },

  // ---------- Catalogue
  { id: 'catalogue-filtrer', depart: '/catalogue', faire: async (p) => { await p.fill('#cq', 'porte'); await p.waitForTimeout(50); } },
  { id: 'catalogue-categorie', depart: '/catalogue',
    faire: async (p) => { await p.click('nav.fchips a:nth-child(3)'); await quand(p, () => document.querySelector('nav.fchips a:nth-child(3)').getAttribute('aria-current') === 'true'); } },
  { id: 'catalogue-fiche', depart: '/catalogue', faire: async (p) => { await p.locator('#clist a.crow').nth(3).click(); await feuilleOuverte(p); } },
  { id: 'catalogue-modifier', depart: '/catalogue',
    preparer: async (p) => { await p.locator('#clist a.crow').nth(3).click(); await feuilleOuverte(p); await p.waitForTimeout(400); await p.fill('[data-feuille-corps] #prix', '47.50'); },
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="produit"]'); await feuilleFermee(p); } },
  { id: 'catalogue-nouveau', depart: '/catalogue',
    preparer: async (p) => {
      await p.locator('a[href^="/catalogue/nouveau"]:visible').last().click(); await feuilleOuverte(p); await p.waitForTimeout(400);
      await p.fill('[data-feuille-corps] #designation', 'Peinture de radiateur en fonte');
      await p.fill('[data-feuille-corps] #prix', '85');
    },
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="produit"]'); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => [...document.querySelectorAll('#clist .crow')].some((r) => r.textContent.includes('Peinture de radiateur en fonte'))) },

  // ---------- Clients
  { id: 'client-fiche', depart: '/clients', faire: async (p) => { await p.locator('a[href^="/clients/"][data-en-feuille][data-texte]').nth(2).click(); await feuilleOuverte(p); } },
  { id: 'client-champ', depart: '/clients',
    preparer: async (p) => { await p.locator('a[href^="/clients/"][data-en-feuille][data-texte]').nth(2).click(); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: async (p) => {
      await p.fill('[data-feuille-corps] #telephone', '079 123 45 67');
      await quand(p, () => /Enregistré/.test((document.querySelector('[data-feuille-corps] [data-auto-etat]') || {}).textContent || ''));
    } },
  { id: 'client-nouveau', depart: '/clients',
    preparer: async (p) => { await p.locator('a[href="/clients/nouveau"]:visible').last().click(); await feuilleOuverte(p); await p.waitForTimeout(400); await p.fill('[data-feuille-corps] #nom', 'Famille Mesure'); },
    faire: async (p) => { await p.click('[data-feuille-pied] button[form="client"]'); await feuilleFermee(p); },
    confirmer: (p) => quand(p, () => [...document.querySelectorAll('#klist .krow')].some((r) => r.textContent.includes('Famille Mesure'))) },

  // ---------- Réglages
  { id: 'reglages-rubrique', nav: true, depart: '/reglages', faire: (p) => chargement(p, clic('a[href="/reglages/entreprise"]')) },
  { id: 'reglages-champ', depart: '/reglages/entreprise',
    faire: async (p) => {
      await p.locator('form#reglages input[type=text]:visible, form#reglages input:not([type]):visible').nth(1).fill('Jean Mesure');
      await quand(p, () => /Enregistré/.test((document.querySelector('form#reglages [data-auto-etat]') || {}).textContent || ''));
    } },
];
