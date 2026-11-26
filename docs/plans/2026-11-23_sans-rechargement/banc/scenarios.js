// Gestes mesurés (chantier « sans rechargement », 2026-11-23). Données : base offregen_fluidite_vue, remise à
// l'instantané avant chaque geste. Offre 3 : 33 lignes (4 à vérifier, 2 sans prix, 2 prix dictés, option 41,
// variante 42, ligne libre 65) ; offre 4 envoyée ; offre 5 archivée ; 274 produits ; 42 clients.
// type : P = envoi qui recharge la page ; N = navigation ; F = fenêtre chargée par fetch ; A = déjà sans rechargement ;
// C = côté navigateur, sans réseau. « simuler » : le même envoi par fetch, sans rechargement (redirect: 'manual').
'use strict';

const chargement = async (page, geste) => {
  const fin = page.waitForEvent('load', { timeout: 60000 });
  fin.catch(() => {});
  await geste(page);
  await fin;
};
const clic = (sel) => async (page) => { await page.locator(sel).first().click({ timeout: 15000 }); };
const reponse = (page, motif, methode) => page.waitForResponse((r) => motif.test(new URL(r.url()).pathname) && (!methode || r.request().method() === methode), { timeout: 60000 });
// Glisse la carte d'une offre vers la gauche (app.js, balayage) : Archiver et Supprimer apparaissent.
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
const feuilleOuverte = async (page) => {
  await page.waitForFunction(() => {
    const f = document.querySelector('[data-feuille-page]');
    const c = f && f.querySelector('[data-feuille-corps]');
    return f && !f.hidden && c && c.children.length > 0;
  }, null, { timeout: 60000 });
};

module.exports = [
  // ---------- Liste des offres
  { id: 'liste-ouvrir-offre', ecran: 'Mes offres', geste: 'Ouvrir une offre (33 lignes)', type: 'N', frequence: 'très fréquent',
    depart: '/', faire: (p) => chargement(p, clic('a.ocard[href="/offres/3"]')) },
  { id: 'liste-tri', ecran: 'Mes offres', geste: 'Trier les offres (puce de tri)', type: 'N', frequence: 'occasionnel',
    depart: '/', faire: (p) => chargement(p, clic('nav.tri a:nth-child(2)')) },
  // Corrigé le 2026-11-23 : un clic forcé sur le bouton caché touchait la carte posée dessus et ouvrait l'offre. Le
  // geste est maintenant celui de l'artisan : glisser la carte vers la gauche, puis toucher le bouton apparu.
  { id: 'liste-archiver', ecran: 'Mes offres', geste: 'Archiver une offre (glisser, Archiver)', type: 'P', frequence: 'fréquent',
    depart: '/', preparer: (p) => glisserCarte(p, 2), faire: (p) => chargement(p, clic('form[action="/offres/2/archiver"] button')), simuler: 'form[action="/offres/2/archiver"]' },
  { id: 'liste-supprimer', ecran: 'Mes offres', geste: 'Supprimer une offre (glisser, Supprimer)', type: 'P', frequence: 'occasionnel',
    depart: '/', preparer: (p) => glisserCarte(p, 1), faire: (p) => chargement(p, clic('form[action="/offres/1/supprimer"] button')), simuler: 'form[action="/offres/1/supprimer"]' },
  { id: 'liste-restaurer', ecran: 'Mes offres', geste: 'Restaurer une offre archivée', type: 'P', frequence: 'rare',
    depart: '/', preparer: async (p) => { await p.click('details.archives summary'); },
    faire: (p) => chargement(p, clic('form[action="/offres/5/restaurer"] button')), simuler: 'form[action="/offres/5/restaurer"]' },
  { id: 'liste-retour', ecran: 'Offre', geste: 'Revenir à la liste (bouton retour du téléphone)', type: 'N', frequence: 'très fréquent',
    depart: '/', preparer: async (p) => { await chargement(p, clic('a.ocard[href="/offres/3"]')); },
    faire: (p) => chargement(p, async () => { await p.goBack({ waitUntil: 'commit' }); }) },

  // ---------- Onglets
  { id: 'onglet-clients', ecran: 'Barre du bas', geste: 'Onglet Clients', type: 'N', frequence: 'fréquent',
    depart: '/', faire: (p) => chargement(p, clic('a[href="/clients"]:visible')) },
  { id: 'onglet-plus', ecran: 'Barre du bas', geste: 'Onglet Plus', type: 'N', frequence: 'fréquent',
    depart: '/', faire: (p) => chargement(p, clic('a[href="/plus"]:visible')) },
  { id: 'plus-catalogue', ecran: 'Plus', geste: 'Ouvrir le catalogue (274 produits)', type: 'N', frequence: 'fréquent',
    depart: '/plus', faire: (p) => chargement(p, clic('a[href="/catalogue"]:visible')) },
  { id: 'plus-reglages', ecran: 'Plus', geste: 'Ouvrir les réglages', type: 'N', frequence: 'occasionnel',
    depart: '/plus', faire: (p) => chargement(p, clic('a[href="/reglages"]:visible')) },

  // ---------- Offre : lignes
  { id: 'offre-juste', ecran: 'Offre', geste: '« C\'est juste » sur une ligne à vérifier', type: 'P', frequence: 'très fréquent',
    depart: '/offres/3', faire: (p) => chargement(p, clic('#l14 form[action$="/juste"] button')), simuler: '#l14 form[action$="/juste"]' },
  { id: 'offre-corbeille', ecran: 'Offre', geste: 'Retirer une ligne (corbeille ou glisser)', type: 'P', frequence: 'très fréquent',
    depart: '/offres/3', faire: (p) => chargement(p, clic('#l37 form[action$="/supprimer"] button')), simuler: '#l37 form[action$="/supprimer"]' },
  { id: 'offre-annuler', ecran: 'Offre', geste: '« Annuler » après avoir retiré une ligne', type: 'P', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await chargement(p, clic('#l37 form[action$="/supprimer"] button')); },
    faire: (p) => chargement(p, clic('form.snack button')), simuler: 'form.snack' },
  { id: 'offre-prix-dicte', ecran: 'Offre', geste: 'Prix dicté : « Prendre »', type: 'P', frequence: 'fréquent',
    depart: '/offres/3', faire: (p) => chargement(p, clic('#l15 form.prix-dit button')), simuler: '#l15 form.prix-dit' },
  { id: 'offre-prix-catalogue', ecran: 'Offre', geste: 'Prix dicté : « Mettre aussi au catalogue ? Oui »', type: 'P', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await chargement(p, clic('#l15 form.prix-dit button')); },
    faire: (p) => chargement(p, clic('#l15 form.prix-dit button[value="oui"]')), simuler: '#l15 form.prix-dit', bouton: 'oui' },
  { id: 'offre-quantite', ecran: 'Offre', geste: 'Corriger une quantité (taper, Entrée)', type: 'A', frequence: 'très fréquent',
    depart: '/offres/3',
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/lignes\/12$/, 'POST');
      await p.fill('#l12 input[name=quantite]', '27');
      await p.press('#l12 input[name=quantite]', 'Enter');
      await r;
      await p.waitForFunction(() => !/Enregistrement/.test((document.querySelector('[data-sauvegarde]') || {}).textContent || ''), null, { timeout: 60000 });
    }, simulerChamp: { sel: '#l12 input[name=quantite]', valeur: '27' } },
  { id: 'offre-prix', ecran: 'Offre', geste: 'Corriger un prix (taper, Entrée)', type: 'A', frequence: 'très fréquent',
    depart: '/offres/3',
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/lignes\/13$/, 'POST');
      await p.fill('#l13 input[name=prix]', '19.50');
      await p.press('#l13 input[name=prix]', 'Enter');
      await r;
      await p.waitForFunction(() => !/Enregistrement/.test((document.querySelector('[data-sauvegarde]') || {}).textContent || ''), null, { timeout: 60000 });
    }, simulerChamp: { sel: '#l13 input[name=prix]', valeur: '19.50' } },
  { id: 'offre-option', ecran: 'Offre', geste: 'Cocher « Retenue par le client » (option)', type: 'A', frequence: 'occasionnel',
    depart: '/offres/3',
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/lignes\/41\/retenue$/, 'POST');
      await p.check('#l41 input[type=checkbox][name=retenue]');
      await r;
      await p.waitForFunction(() => !/Enregistrement/.test((document.querySelector('[data-sauvegarde]') || {}).textContent || ''), null, { timeout: 60000 });
    }, simuler: '#l41 form[data-choix]' },
  { id: 'offre-texte', ecran: 'Offre', geste: 'Écrire le texte avant les lignes', type: 'A', frequence: 'occasionnel',
    depart: '/offres/3',
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/textes$/, 'POST');
      await p.fill('#texte-avant', 'Suite à notre visite du 20 novembre, voici notre offre.');
      await p.locator('#texte-avant').blur();
      await r;
      await p.waitForFunction(() => /Enregistrée|Non enregistr/.test((document.querySelector('[data-sauvegarde]') || {}).textContent || ''), null, { timeout: 60000 });
    }, simuler: 'form[data-texte-offre]' },
  { id: 'offre-ouvrir-ligne', ecran: 'Offre', geste: 'Toucher une ligne pour l\'ouvrir', type: 'N', frequence: 'très fréquent',
    depart: '/offres/3', faire: (p) => chargement(p, clic('#l12 a.nm')) },
  { id: 'ligne-enregistrer', ecran: 'Ligne', geste: 'Ligne : « Enregistrer »', type: 'P', frequence: 'fréquent',
    depart: '/offres/3/lignes/12', preparer: async (p) => { await p.fill('#ligne input[name=quantite]', '31'); },
    faire: (p) => chargement(p, clic('button[form="ligne"]')), simuler: 'form#ligne' },

  // ---------- Offre : fenêtres du bas
  { id: 'ajout-ouvrir', ecran: 'Offre', geste: '« + Ligne » : ouvrir l\'ajout (274 produits)', type: 'F', frequence: 'très fréquent',
    depart: '/offres/3', faire: async (p) => { await p.click('a.btn.ajout'); await feuilleOuverte(p); } },
  { id: 'ajout-produit', ecran: 'Ajouter', geste: 'Toucher un produit pour l\'ajouter', type: 'F', frequence: 'très fréquent',
    depart: '/offres/3', preparer: async (p) => { await p.click('a.btn.ajout'); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/ajouter$/, 'GET');
      await p.locator('[data-feuille-corps] #res form:not([hidden]) button.pick').nth(5).click();
      await r;
      await p.waitForSelector('[data-feuille-corps] .ajoutee', { timeout: 60000 });
    }, simuler: '[data-feuille-corps] #res form:not([hidden]) >> nth=5', bouton: 'produit' },
  { id: 'ajout-termine', ecran: 'Ajouter', geste: '« Terminé » après un ajout (l\'offre se recharge)', type: 'P', frequence: 'très fréquent',
    depart: '/offres/3', preparer: async (p) => {
      await p.click('a.btn.ajout'); await feuilleOuverte(p); await p.waitForTimeout(400);
      const r = reponse(p, /^\/offres\/3\/ajouter$/, 'GET');
      await p.locator('[data-feuille-corps] #res form:not([hidden]) button.pick').nth(5).click();
      await r; await p.waitForSelector('[data-feuille-corps] .ajoutee');
    },
    faire: (p) => chargement(p, clic('[data-feuille-pied] a.btn.primary')) },
  { id: 'ajout-libre', ecran: 'Ajouter', geste: 'Ajouter une ligne libre (texte tapé)', type: 'F', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await p.click('a.btn.ajout'); await feuilleOuverte(p); await p.waitForTimeout(400); await p.fill('[data-feuille-corps] #cherche', 'Nettoyage spécial des stores'); },
    faire: async (p) => {
      const r = reponse(p, /^\/offres\/3\/ajouter$/, 'GET');
      await p.click('[data-feuille-corps] form.pick-libre button');
      await r;
      await p.waitForSelector('[data-feuille-corps] .ajoutee', { timeout: 60000 });
    }, simuler: '[data-feuille-corps] form.pick-libre' },
  { id: 'client-offre', ecran: 'Offre', geste: 'Changer le client de l\'offre (fenêtre, choix)', type: 'F', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await p.click('a.who'); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: (p) => chargement(p, clic('[data-feuille-corps] button.kchoix:not(.on)')), simuler: '[data-feuille-corps] form:has(button.kchoix:not(.on))' },
  { id: 'representant', ecran: 'Offre', geste: '« Représenté par » : choisir une régie', type: 'F', frequence: 'occasionnel',
    depart: '/offres/3', preparer: async (p) => { await p.click('a.rep'); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: (p) => chargement(p, clic('[data-feuille-corps] button.kchoix')), simuler: '[data-feuille-corps] form:has(button.kchoix)' },
  { id: 'rabais', ecran: 'Offre', geste: '« + Rabais » : ajouter un rabais de 5 %', type: 'F', frequence: 'occasionnel',
    depart: '/offres/3', preparer: async (p) => {
      await p.click('a.lien.plus'); await feuilleOuverte(p); await p.waitForTimeout(400);
      await p.fill('[data-feuille-corps] #libelle', 'Rabais fidélité');
      await p.locator('[data-feuille-corps] label.puce:has(input[value="5"])').click();
    },
    faire: (p) => chargement(p, clic('[data-feuille-pied] button[form="rabais"]')), simuler: '[data-feuille-corps] form#rabais' },
  { id: 'conditions', ecran: 'Offre', geste: 'Conditions de l\'offre : « Enregistrer »', type: 'F', frequence: 'occasionnel',
    depart: '/offres/3', preparer: async (p) => { await p.click('a.carte-lien.conditions'); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: (p) => chargement(p, clic('[data-feuille-pied] button[form="conditions"]')), simuler: '[data-feuille-corps] form#conditions' },
  { id: 'ligne-rattacher', ecran: 'Offre', geste: 'Ligne hors catalogue : la rattacher au produit proposé', type: 'F', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await p.click('#l65 a.vers-catalogue'); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: (p) => chargement(p, clic('[data-feuille-corps] form[action$="/rattacher"]:not([hidden]) button:visible')), simuler: '[data-feuille-corps] form[method="post"][action$="/rattacher"]:not([hidden]):visible', bouton: 'produit' },
  { id: 'ligne-creer-produit', ecran: 'Offre', geste: 'Ligne hors catalogue : « Créer et ranger »', type: 'F', frequence: 'fréquent',
    depart: '/offres/3', preparer: async (p) => { await p.click('#l65 a.vers-catalogue'); await feuilleOuverte(p); await p.waitForTimeout(400); await p.click('[data-feuille-corps] label:has(input[value="nouveau"])'); },
    faire: (p) => chargement(p, clic('[data-feuille-corps] form#nouveau-produit button:has-text("Créer et ranger")')), simuler: '[data-feuille-corps] form#nouveau-produit' },

  // ---------- Offre : écrans
  { id: 'offre-objet', ecran: 'Objet de l\'offre', geste: 'Objet : « Enregistrer »', type: 'P', frequence: 'occasionnel',
    depart: '/offres/3/modifier', faire: (p) => chargement(p, clic('button[form="entete"]')), simuler: 'form#entete' },
  { id: 'relire-ouvrir', ecran: 'Offre', geste: '« Relire 6 lignes »', type: 'N', frequence: 'très fréquent',
    depart: '/offres/3', faire: (p) => chargement(p, clic('a[href="/offres/3/relire"]')) },
  { id: 'relire-juste', ecran: 'Relecture', geste: 'Relecture : « C\'est juste », ligne suivante', type: 'P', frequence: 'très fréquent',
    depart: '/offres/3/relire', faire: (p) => chargement(p, clic('button[form="relecture"]')), simuler: 'form#relecture' },
  { id: 'generer', ecran: 'Offre', geste: '« Générer l\'offre » : PDF affiché', type: 'N', frequence: 'très fréquent',
    depart: '/offres/2',
    faire: async (p) => {
      await chargement(p, clic('a[href="/offres/2/apercu"]'));
      await p.waitForSelector('[data-pdf] canvas', { timeout: 60000 });
      await p.waitForFunction(() => !/Préparation/.test((document.querySelector('[data-pdf-etat]') || {}).textContent || ''), null, { timeout: 60000 });
    } },
  { id: 'facturer-ouvrir', ecran: 'Offre envoyée', geste: '« Facturer » : fenêtre et aperçu', type: 'F', frequence: 'fréquent',
    depart: '/offres/4', faire: async (p) => { await p.click('a[href="/offres/4/facturer"]'); await feuilleOuverte(p); await p.waitForSelector('[data-feuille-corps] [data-apercu-mini] .apercu-facture', { timeout: 60000 }); } },
  { id: 'facturer-creer', ecran: 'Facturer', geste: '« Créer la facture »', type: 'P', frequence: 'fréquent',
    depart: '/offres/4', preparer: async (p) => { await p.click('a[href="/offres/4/facturer"]'); await feuilleOuverte(p); await p.waitForSelector('[data-feuille-corps] [data-apercu-mini] .apercu-facture', { timeout: 60000 }); },
    faire: (p) => chargement(p, clic('button[form="facturer"][value="creer"]')) },

  // ---------- Catalogue
  { id: 'catalogue-filtrer', ecran: 'Catalogue', geste: 'Filtrer en tapant « porte »', type: 'C', frequence: 'très fréquent',
    depart: '/catalogue', faire: async (p) => { await p.fill('#cq', 'porte'); await p.waitForTimeout(50); } },
  { id: 'catalogue-categorie', ecran: 'Catalogue', geste: 'Choisir une catégorie (puce)', type: 'N', frequence: 'fréquent',
    depart: '/catalogue', faire: (p) => chargement(p, clic('nav.fchips a:nth-child(3)')) },
  { id: 'catalogue-fiche', ecran: 'Catalogue', geste: 'Ouvrir la fiche d\'un produit', type: 'F', frequence: 'fréquent',
    depart: '/catalogue', faire: async (p) => { await p.locator('#clist a.crow').nth(3).click(); await feuilleOuverte(p); } },
  { id: 'catalogue-modifier', ecran: 'Catalogue', geste: 'Fiche produit : changer le prix, « Enregistrer »', type: 'F', frequence: 'fréquent',
    depart: '/catalogue', preparer: async (p) => { await p.locator('#clist a.crow').nth(3).click(); await feuilleOuverte(p); await p.waitForTimeout(400); await p.fill('[data-feuille-corps] #prix', '47.50'); },
    faire: async (p) => {
      const r = reponse(p, /^\/catalogue$/, 'GET');
      await p.click('[data-feuille-pied] button[form="produit"]');
      await r;
      await p.waitForTimeout(100);
    }, simuler: '[data-feuille-corps] form#produit' },
  { id: 'catalogue-nouveau', ecran: 'Catalogue', geste: 'Nouveau produit : remplir, « Enregistrer »', type: 'F', frequence: 'fréquent',
    depart: '/catalogue', preparer: async (p) => {
      await p.locator('a[href^="/catalogue/nouveau"]:visible').last().click(); await feuilleOuverte(p); await p.waitForTimeout(400);
      await p.fill('[data-feuille-corps] #designation', 'Peinture de radiateur en fonte');
      await p.fill('[data-feuille-corps] #prix', '85');
    },
    faire: async (p) => {
      const r = reponse(p, /^\/catalogue$/, 'GET');
      await p.click('[data-feuille-pied] button[form="produit"]');
      await r;
      await p.waitForTimeout(100);
    }, simuler: '[data-feuille-corps] form#produit' },

  // ---------- Clients
  { id: 'client-fiche', ecran: 'Clients', geste: 'Ouvrir la fiche d\'un client', type: 'F', frequence: 'fréquent',
    depart: '/clients', faire: async (p) => { await p.locator('a[href^="/clients/"][data-en-feuille][data-texte]').nth(2).click(); await feuilleOuverte(p); } },
  { id: 'client-champ', ecran: 'Fiche client', geste: 'Fiche client : taper un téléphone (enregistré seul)', type: 'A', frequence: 'fréquent',
    depart: '/clients', preparer: async (p) => { await p.locator('a[href^="/clients/"][data-en-feuille][data-texte]').nth(2).click(); await feuilleOuverte(p); await p.waitForTimeout(400); },
    faire: async (p) => {
      const r = reponse(p, /^\/clients\/\d+$/, 'POST');
      await p.fill('[data-feuille-corps] #telephone', '079 123 45 67');
      await r;
      await p.waitForFunction(() => /Enregistré/.test((document.querySelector('[data-feuille-corps] [data-auto-etat]') || {}).textContent || ''), null, { timeout: 60000 });
    } },
  { id: 'client-nouveau', ecran: 'Clients', geste: 'Nouveau client : nom, « Ajouter »', type: 'F', frequence: 'occasionnel',
    depart: '/clients', preparer: async (p) => { await p.locator('a[href="/clients/nouveau"]:visible').last().click(); await feuilleOuverte(p); await p.waitForTimeout(400); await p.fill('[data-feuille-corps] #nom', 'Famille Mesure'); },
    faire: async (p) => {
      const r = reponse(p, /^\/clients$/, 'GET');
      await p.click('[data-feuille-pied] button[form="client"]');
      await r;
      await p.waitForTimeout(100);
    }, simuler: '[data-feuille-corps] form#client' },

  // ---------- Réglages
  { id: 'reglages-rubrique', ecran: 'Réglages', geste: 'Ouvrir la rubrique Entreprise', type: 'N', frequence: 'rare',
    depart: '/reglages', faire: (p) => chargement(p, clic('a[href="/reglages/entreprise"]')) },
  { id: 'reglages-champ', ecran: 'Réglages', geste: 'Réglage : taper un champ (enregistré seul)', type: 'A', frequence: 'rare',
    depart: '/reglages/entreprise',
    faire: async (p) => {
      const r = reponse(p, /^\/reglages$/, 'POST');
      await p.locator('form#reglages input[type=text]:visible, form#reglages input:not([type]):visible').nth(1).fill('Jean Mesure');
      await r;
      await p.waitForFunction(() => /Enregistré/.test((document.querySelector('form#reglages [data-auto-etat]') || {}).textContent || ''), null, { timeout: 60000 });
    } },
];
