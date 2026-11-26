// Génère MAQUETTE-sans-rechargement.html dans le dossier du chantier, à partir de l'inventaire et des mesures.
// Usage : node maquette.js <dossier du chantier, vu de Windows>
'use strict';
const fs = require('fs');
const path = require('path');
const { gestes, temps, ko, synthese } = require('./donnees');

const DOSSIER = process.argv[2];
const IMG = 'MAQUETTE-sans-rechargement-img';
const ICI = __dirname;
const lireJson = (f, defaut) => { try { return JSON.parse(fs.readFileSync(path.join(ICI, f), 'utf8')); } catch (e) { return defaut; } };
const horsLigne = lireJson('hors-ligne.json', []);
const dictees = (() => { try { return fs.readFileSync(path.join(ICI, 'dictees.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l)); } catch (e) { return []; } })();
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const g = (id) => gestes.find((x) => x.mesure === id) || null;
const c = (id, p, v) => { const x = g(id); return x && x.chiffres && x.chiffres[p] && x.chiffres[p][v] ? x.chiffres[p][v] : null; };
const t = (ms) => temps(ms) || 'non mesuré';
const k = (o) => ko(o) || 'non mesuré';
const fois = (a, b) => (a && b ? Math.round(a / b) : null);

// Chiffres de tête.
const juste = { f4: c('offre-juste', '4g', 'froid'), fc: c('offre-juste', 'chantier', 'froid'), s4: c('offre-juste', '4g', 'simule'), sc: c('offre-juste', 'chantier', 'simule'), k4: c('offre-juste', '4g', 'chaud'), kc: c('offre-juste', 'chantier', 'chaud') };
const ouvrir = { f4: c('liste-ouvrir-offre', '4g', 'froid'), fc: c('liste-ouvrir-offre', 'chantier', 'froid'), k4: c('liste-ouvrir-offre', '4g', 'chaud'), kc: c('liste-ouvrir-offre', 'chantier', 'chaud') };
const somme = (ids, p, v) => ids.map((i) => c(i, p, v)).reduce((s, x) => (x ? { duree: s.duree + (x.vu || x.duree), requetes: s.requetes + x.requetes, octets: s.octets + x.octets } : s), { duree: 0, requetes: 0, octets: 0 });
const ajout = { fc: somme(['ajout-ouvrir', 'ajout-produit', 'ajout-termine'], 'chantier', 'froid'), f4: somme(['ajout-ouvrir', 'ajout-produit', 'ajout-termine'], '4g', 'froid'), sc: c('ajout-produit', 'chantier', 'simule'), s4: c('ajout-produit', '4g', 'simule') };
const quantite = { f4: c('offre-quantite', '4g', 'froid'), fc: c('offre-quantite', 'chantier', 'froid'), s4: c('offre-quantite', '4g', 'simule'), sc: c('offre-quantite', 'chantier', 'simule') };
const generer = { f4: c('generer', '4g', 'froid'), fc: c('generer', 'chantier', 'froid'), l: c('generer', 'local', 'froid') };
const inutiles = gestes.filter((x) => x.categorie === 'inutile');
const lourds = gestes.filter((x) => x.categorie === 'lourd');
const utiles = gestes.filter((x) => x.categorie === 'utile');
const peu = gestes.filter((x) => x.categorie === 'peu');
const bien = gestes.filter((x) => x.categorie === 'bien');
const categorie = (x) => x.categorie;
const hl = (cas) => horsLigne.find((h) => h.cas === cas) || {};
const dicteeFibre = dictees.find((d) => d.profil === 'fibre');
const dicteeChantier = dictees.find((d) => d.profil === 'chantier');

// Ce que le banc a vu sans réseau et sur réseau lent (hors-ligne.json), en phrases.
function constatsReseau() {
  const q = hl('hors-ligne-quantite');
  const corb = hl('lent-deux-corbeilles');
  const arch = hl('lent-deux-archiver');
  const ses = hl('session-expiree-quantite');
  const li = [];
  const m2 = (milliemes) => `${Number(milliemes) / 1000}&nbsp;m²`;
  if (q.message !== undefined) {
    li.push(`<li><b>Quantité tapée sans réseau (24 m² passés à 27).</b> L'écran dit « ${esc(q.message)} ». En base : ${m2(q.avant)} avant ; ${m2(q.apres_retour_du_reseau_8s)} huit secondes après le retour du réseau ; ${m2(q.apres_avoir_quitte_la_page)} une fois l'offre quittée. ${q.apres_retour_du_reseau_8s === q.avant ? 'Le chiffre ne repart pas seul : il ne part que si l\'artisan quitte l\'offre avec du réseau.' : ''}</li>`);
  }
  const une = hl('lent-une-corbeille');
  const bandeau = (s) => (s && s.length ? s.map((x) => x.replace(/Annuler$/, ' · Annuler')).map((x) => esc(x)).join(' ; ') : 'aucun');
  if (corb.reponses) li.push(`<li><b>Deux corbeilles touchées à 0,7 s d'écart, réseau de chantier.</b> Le serveur accepte les deux envois (${corb.reponses.length} réponses 303), les lignes ${esc(corb.lignes_retirees || '?')} sont retirées en base. La page qui arrive n'affiche qu'un bandeau : ${bandeau(corb.snack)} (une corbeille seule affiche bien le sien : ${bandeau(une.snack)}). <b>Les deux lignes partent, un seul « Annuler » : la première ne peut plus être remise.</b></li>`);
  if (arch.reponses) li.push(`<li><b>« Archiver » retouché 0,6 s plus tard, réseau de chantier.</b> Un seul archivage (${arch.archivee === '1' ? 'offre archivée en base' : 'offre non archivée'}) ; le second toucher ne trouve plus le bouton, la carte s'est refermée. La liste revient avec ${bandeau(arch.snack)}. Rien à signaler.</li>`);
  if (ses.message !== undefined) li.push(`<li><b>Session perdue, une quantité tapée (24 m² passés à 33).</b> L'indicateur reste sur « ${esc(ses.message)} » pour de bon ; en base : ${m2(ses.avant)} avant, ${m2(ses.apres)} après. Rien n'est enregistré, rien ne le dit.</li>`);
  return li.length ? `<ul class="serre">${li.join('')}</ul>` : '';
}

const cellule = (x, p) => {
  if (!x.chiffres || !x.chiffres[p]) return '<span class="muted">non mesuré</span>';
  const m = x.chiffres[p];
  const vu = x.mesure === 'generer' ? m.froid && m.froid.duree : m.froid && m.froid.vu;
  const complet = m.froid && m.froid.fcp && x.mesure !== 'generer' ? `, tout chargé ${temps(m.froid.duree)}` : '';
  const a = m.froid ? `<b>${temps(vu)}</b><small>${m.froid.requetes}&nbsp;req., ${ko(m.froid.octets)}${complet}</small>` : '';
  const b = m.simule ? `<span class="fleche">→</span><b class="ok">${temps(m.simule.duree)}</b><small>${m.simule.requetes}&nbsp;req., ${ko(m.simule.octets)}</small>` : '';
  return a + b || '<span class="muted">non mesuré</span>';
};

const lignesTableau = gestes.map((x) => `<tr data-v="${categorie(x)}">
<td data-label="Geste"><b>${esc(x.geste)}</b><small>${esc(x.ecran)}</small></td>
<td data-label="Aujourd'hui">${esc(x.libelle)}</td>
<td data-label="Rechargement utile ?"><span class="pastille ${x.utile === 'oui' ? 'p-oui' : 'p-non'}">${x.utile === 'oui' ? 'Oui' : 'Non'}</span> ${esc(x.pourquoi)}</td>
<td data-label="4G" class="n">${cellule(x, '4g')}</td>
<td data-label="Chantier" class="n">${cellule(x, 'chantier')}</td>
<td data-label="Proposé">${esc(x.propose)}${x.lot > 0 ? `<small>${esc(x.nomLot)}</small>` : ''}</td>
</tr>`).join('\n');

const decision = (q, libelle, options, aide) => `<div class="decision" data-q="${esc(q)}">
<p class="q">${libelle}</p>
<div class="opts">${options.map(([v, texte]) => `<button type="button" data-v="${v}">${texte}</button>`).join('')}</div>
${aide ? `<p class="aide">${aide}</p>` : ''}<textarea aria-label="Commentaire : ${esc(q)}" placeholder="Commentaire (facultatif)"></textarea>
</div>`;
const fig = (fichier, alt, legende) => `<figure><img src="${IMG}/${fichier}.jpg" alt="${esc(alt)}" loading="lazy"><figcaption>${legende}</figcaption></figure>`;

// Durées de la démo : réseau → [aujourd'hui (cache périmé), aujourd'hui (cache gardé), sans rechargement (confirmation)].
const demo = {};
['fibre', '4g', 'chantier'].forEach((p) => { demo[p] = [c('offre-juste', p, 'froid')?.vu || null, c('offre-juste', p, 'chaud')?.vu || null, c('offre-juste', p, 'simule')?.duree || null]; });

const html = `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sans rechargement : chaque geste mesuré</title>
<style>
:root { --ink: #23272D; --muted: #4A505A; --bg: #F4F1EA; --surface: #FFFFFF; --line: #DDD8CC; --accent: #23272D; --marque: #A8441F; --ok: #13553A; --ok-soft: #E1EFE6; --warn: #5A4300; --warn-soft: #FFF0B3; --bad: #8C1D18; --bad-soft: #FBE7E4;
  --mk-ink: var(--ink); --mk-bg: var(--bg); --mk-surface: var(--surface); --mk-line: var(--line); --mk-accent: var(--marque); }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --ink: #ECEAE6; --muted: #B4B9C0; --bg: #15171A; --surface: #1E2125; --line: #33373D; --accent: #ECEAE6; --marque: #F0936E; --ok: #7DD3A8; --ok-soft: #183126; --warn: #F0B860; --warn-soft: #3A2A10; --bad: #FF9A91; --bad-soft: #3B1613; } }
:root[data-theme="dark"] { --ink: #ECEAE6; --muted: #B4B9C0; --bg: #15171A; --surface: #1E2125; --line: #33373D; --accent: #ECEAE6; --marque: #F0936E; --ok: #7DD3A8; --ok-soft: #183126; --warn: #F0B860; --warn-soft: #3A2A10; --bad: #FF9A91; --bad-soft: #3B1613; }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
main { max-width: 1180px; margin: 0 auto; padding: 16px 16px 80px; }
h1 { font-size: 27px; line-height: 1.2; margin: 18px 0 6px; }
h2 { font-size: 21px; line-height: 1.25; margin: 0 0 6px; scroll-margin-top: 70px; }
h3 { font-size: 17px; margin: 18px 0 6px; }
p { margin: 0 0 10px; }
.chapeau { color: var(--muted); max-width: 780px; }
section.point { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 18px 18px 14px; margin: 22px 0; }
figure { margin: 0; }
figure img { max-width: 100%; height: auto; display: block; border: 1px solid var(--line); border-radius: 8px; }
figcaption { font-size: 14px; color: var(--muted); margin: 6px 0 0; }
.paire { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin: 12px 0; }
.trio { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 12px 0; }
.mk-compare figure img, .paire figure img, .trio figure img { max-width: 360px; }
.chiffres { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; margin: 14px 0; }
.chiffre { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
.chiffre .gros { font-size: 26px; font-weight: 750; line-height: 1.15; font-variant-numeric: tabular-nums; display: block; }
.chiffre .gros .ok { color: var(--ok); }
.chiffre small { display: block; color: var(--muted); font-size: 14px; margin-top: 4px; }
.conseil { border-left: 4px solid var(--marque); background: var(--bg); padding: 10px 14px; border-radius: 0 10px 10px 0; margin: 12px 0; }
.conseil b:first-child { display: block; margin-bottom: 2px; }
.alerte { border: 1px solid var(--bad); background: var(--bad-soft); border-radius: 12px; padding: 12px 14px; margin: 14px 0; }
.alerte b:first-child { color: var(--bad); }
ul.serre { margin: 4px 0 10px; padding-left: 20px; }
ul.serre li { margin: 3px 0; }
code { font: 13.5px ui-monospace, Consolas, monospace; background: var(--bg); border-radius: 4px; padding: 1px 4px; overflow-wrap: anywhere; }
.muted { color: var(--muted); }
table { border-collapse: collapse; width: 100%; font-size: 14.5px; margin: 8px 0 12px; }
th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { font-weight: 650; background: var(--bg); position: sticky; top: 52px; }
td small { display: block; color: var(--muted); font-size: 13px; }
td.n b { font-variant-numeric: tabular-nums; }
td.n .ok, b.ok { color: var(--ok); }
.fleche { color: var(--muted); margin: 0 4px; }
.pastille { display: inline-block; font-size: 12.5px; font-weight: 700; border-radius: 999px; padding: 1px 8px; margin-right: 4px; }
.p-oui { background: var(--ok-soft); color: var(--ok); }
.p-non { background: var(--warn-soft); color: var(--warn); }
.simple td, .simple th { font-size: 14.5px; }
.simple th { position: static; }
@media (max-width: 760px) {
  table.gestes thead { display: none; }
  table.gestes tr[hidden] { display: none; }
  table.gestes tr { display: block; border: 1px solid var(--line); border-radius: 10px; margin: 0 0 10px; padding: 6px 8px; background: var(--surface); }
  table.gestes td { display: block; border: 0; padding: 4px 0; }
  table.gestes td::before { content: attr(data-label); display: block; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
}
.mk-filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
.mk-filters button { font: inherit; font-size: 14.5px; min-height: 44px; padding: 6px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; }
.mk-filters button[aria-pressed="true"] { background: var(--accent); color: var(--surface); border-color: var(--accent); }
.demo { background: var(--bg); border: 1px solid var(--line); border-radius: 14px; padding: 14px; margin: 12px 0; }
.demo .reglages-demo { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; }
.demo .reglages-demo button { font: inherit; font-size: 15px; min-height: 44px; padding: 6px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; }
.demo .reglages-demo button[aria-pressed="true"] { background: var(--accent); color: var(--surface); border-color: var(--accent); }
.demo label.case { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; font-size: 15px; }
.telephones { display: grid; grid-template-columns: repeat(2, minmax(0, 300px)); gap: 18px; justify-content: center; }
.tel { background: #111; border-radius: 28px; padding: 10px; position: relative; }
.tel figcaption { color: #fff; text-align: center; font-weight: 650; font-size: 15px; margin: 0 0 6px; }
.tel .ecran { position: relative; border-radius: 18px; overflow: hidden; background: #fff; aspect-ratio: 390 / 844; }
.tel .ecran img { width: 100%; height: 100%; object-fit: cover; object-position: top; border: 0; border-radius: 0; }
.tel .chrono { position: absolute; left: 50%; top: 10px; transform: translateX(-50%); background: rgba(0,0,0,.78); color: #fff; font-weight: 700; font-variant-numeric: tabular-nums; border-radius: 999px; padding: 3px 12px; font-size: 15px; }
.tel .chrono[hidden] { display: none; }
.tel .etat { color: #ddd; font-size: 13.5px; text-align: center; margin: 6px 0 0; min-height: 40px; }
.demo .lancer { display: block; margin: 14px auto 0; font: inherit; font-weight: 700; min-height: 52px; padding: 10px 22px; border-radius: 12px; border: 0; background: var(--marque); color: #fff; cursor: pointer; }
@media (max-width: 560px) { .telephones { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; } .tel { padding: 6px; border-radius: 18px; } .tel .etat { font-size: 12px; } }
.decision { border-top: 1px dashed var(--line); margin-top: 14px; padding-top: 12px; }
.decision .q { font-weight: 650; margin: 0 0 8px; }
.decision .aide { font-size: 14px; color: var(--muted); }
.opts { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 8px; }
.opts button { font: inherit; font-size: 15px; min-height: 44px; padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; text-align: left; }
.opts button[aria-pressed="true"] { background: var(--accent); border-color: var(--accent); color: var(--surface); font-weight: 650; }
.opts button:focus-visible, textarea:focus-visible, .copier:focus-visible, .lancer:focus-visible { outline: 3px solid var(--marque); outline-offset: 2px; }
textarea { width: 100%; min-height: 60px; font: inherit; font-size: 15px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); color: var(--ink); resize: vertical; }
.recap textarea { min-height: 170px; font-family: ui-monospace, Consolas, monospace; font-size: 13.5px; }
.copier { font: inherit; font-weight: 650; min-height: 48px; padding: 10px 18px; border-radius: 10px; border: 0; background: var(--accent); color: var(--surface); cursor: pointer; }
.copie-ok { margin-left: 10px; color: var(--ok); font-weight: 600; }
.approches { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin: 12px 0; }
.approche { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; background: var(--surface); }
.approche h3 { margin-top: 0; }
.approche.conseillee { border: 2px solid var(--marque); }
.plus-moins { margin: 6px 0; padding-left: 0; list-style: none; }
.plus-moins li { padding-left: 22px; position: relative; margin: 4px 0; font-size: 15px; }
.plus-moins li.plus::before { content: "+"; position: absolute; left: 4px; color: var(--ok); font-weight: 800; }
.plus-moins li.moins::before { content: "−"; position: absolute; left: 4px; color: var(--bad); font-weight: 800; }
</style>
</head>
<body>
<nav id="mk-toc"></nav>
<main>
<h1>Une application qui ne recharge plus : chaque geste mesuré</h1>
<p class="chapeau">Chantier « sans rechargement », relevé du 23 novembre 2026. ${gestes.length} gestes passés en revue, ${synthese.gestes.length} chronométrés au clic sur quatre réseaux, plus la dictée de bout en bout (banc Playwright, copie de travail du 23.11, données inventées). Les choix t'attendent sous chaque écran ; « Copier mes réponses », en bas, les rassemble.</p>

<h2 id="bref" data-toc="Bref">En bref</h2>
<div class="chiffres">
<div class="chiffre"><span class="gros">${inutiles.length + lourds.length} <span class="muted">gestes sur ${gestes.length}</span></span><small>rechargent la page, ou font revenir la page entière, alors qu'une ligne, une carte ou un total change. ${utiles.length} gestes gardent un rechargement utile, ${peu.length} le gardent parce qu'ils sont rares, ${bien.length} sont déjà bien.</small></div>
<div class="chiffre"><span class="gros">${t(juste.fc?.vu)} <span class="fleche">→</span> <span class="ok">${t(juste.sc?.duree)}</span></span><small>« C'est juste » sur un réseau de chantier : la page rechargée s'affiche après ${t(juste.fc?.vu)} et finit de charger après ${t(juste.fc?.duree)} (${juste.fc?.requetes} requêtes, ${k(juste.fc?.octets)}) ; le même envoi sans rechargement : ${t(juste.sc?.duree)}, ${juste.sc?.requetes} requête, ${k(juste.sc?.octets)}. En changeant l'écran au toucher, l'artisan n'attend plus du tout.</small></div>
<div class="chiffre"><span class="gros">${k(ouvrir.fc?.octets)} <span class="fleche">→</span> <span class="ok">${k(ouvrir.kc?.octets)}</span></span><small>pour ouvrir une offre : le serveur de test ne dit pas combien de temps garder la feuille de style, le script et les polices, et son ETag empêche la réponse « rien n'a changé ». Corrigé : affichée après ${t(ouvrir.kc?.vu)} au lieu de ${t(ouvrir.fc?.vu)} sur le réseau de chantier.</small></div>
<div class="chiffre"><span class="gros">${t(ajout.fc.duree)} <span class="fleche">→</span> <span class="ok">${t(ajout.sc?.duree)}</span></span><small>pour ajouter un produit (ouvrir « + Ligne », toucher le produit, « Terminé ») sur un réseau de chantier : ${ajout.fc.requetes} requêtes et ${k(ajout.fc.octets)} aujourd'hui ; avec le catalogue gardé dans le téléphone, un seul envoi.</small></div>
</div>
<div class="alerte"><b>Sans réseau, aujourd'hui.</b> ${esc(hl('hors-ligne-juste').texte ? 'Un toucher sur « C\'est juste » affiche la page d\'erreur de Chrome : l\'artisan perd son offre de l\'écran.' : 'Mesure hors réseau à venir.')} ${hl('hors-ligne-quantite').message ? `Une quantité tapée affiche « ${esc(hl('hors-ligne-quantite').message)} » ; le réseau revenu, ${hl('hors-ligne-quantite').apres_retour_du_reseau_8s === hl('hors-ligne-quantite').avant ? 'elle n\'est pas renvoyée tant que l\'artisan reste sur l\'offre' : 'elle est renvoyée'}.` : ''}</div>
<div class="alerte"><b>Deux défauts d'aujourd'hui, trouvés en route.</b> Sur un réseau lent, deux corbeilles touchées à 0,7 s d'écart retirent les deux lignes, mais la page qui arrive n'offre qu'un « Annuler » : la première ligne ne peut plus être remise. Sans session (expirée, ou cookie perdu), une quantité tapée n'est pas enregistrée et l'écran reste sur « Enregistrement… » sans jamais le dire. Le lot 2 les règle ; s'il tarde, mieux vaut les corriger à part avant.</div>
<div class="conseil"><b>Ce que je conseille.</b> Garder les pages du serveur, et faire changer sur place tout ce qui ne change qu'un morceau d'écran : le serveur renvoie le morceau (la ligne, les totaux), l'écran change dès le toucher, l'envoi suit. Dans cet ordre : le socle (cache des fichiers, un jeton par page, double toucher), l'offre, la fenêtre « + Ligne » avec le catalogue gardé dans le téléphone, puis le hors réseau. Le PDF à part.</div>

<h2 id="demo" data-toc="Démo">Le même toucher, deux téléphones</h2>
<section class="point">
<p>« C'est juste » sur la ligne 5 d'une offre de 33 lignes. À gauche, l'application d'aujourd'hui ; à droite, la proposition. Les durées sont celles mesurées par le banc sur le réseau choisi ; « cache gardé » rejoue le cas où le navigateur garde la feuille de style et le script (lot 1).</p>
<div class="demo" id="demo-bloc">
<div class="reglages-demo" role="group" aria-label="Réseau de la démo">
<button type="button" data-reseau="fibre">Fibre</button><button type="button" data-reseau="4g">4G</button><button type="button" data-reseau="chantier" aria-pressed="true">Chantier (3G faible)</button>
<label class="case"><input type="checkbox" id="demo-cache"> Cache gardé (lot 1)</label>
</div>
<div class="telephones">
<figure class="tel"><figcaption>Aujourd'hui</figcaption><div class="ecran"><img id="demo-g" src="${IMG}/demo-avant.jpg" alt="Offre, la ligne 5 à vérifier au milieu de l'écran" data-no-zoom><span class="chrono" id="demo-chrono-g" hidden></span></div><p class="etat" id="demo-etat-g">Prête.</p></figure>
<figure class="tel"><figcaption>Proposé</figcaption><div class="ecran"><img id="demo-d" src="${IMG}/demo-avant.jpg" alt="La même offre" data-no-zoom><span class="chrono" id="demo-chrono-d" hidden></span></div><p class="etat" id="demo-etat-d">Prête.</p></figure>
</div>
<button type="button" class="lancer" id="demo-lancer">Toucher « C'est juste » sur les deux</button>
</div>
<p class="muted">Après le rechargement, la page saute : le navigateur la ramène sur la ligne touchée (ancre <code>#l14</code>), en haut de l'écran. Sans rechargement, seule la ligne change.</p>
</section>

<h2>Comment j'ai mesuré</h2>
<section class="point">
<ul class="serre">
<li><b>Où.</b> Ma copie de travail dans WSL (<code>~/offreGen-fluidite</code>, tête de <code>chantier-voix-offre</code> du 23.11), sa propre base (<code>offregen_fluidite_vue</code>), remise à zéro avant chaque mesure. Aucune donnée réelle : un compte de démo Peintre, 274 produits, 42 clients, une offre de 33 lignes (4 à vérifier, 2 sans prix, 2 prix dictés, une option, une variante, une ligne libre).</li>
<li><b>Comme sur le test.</b> Un routeur imite l'Apache du site de test, relevé par curl le 23.11 : pages et fichiers compressés, aucune durée de cache, ETag suffixé « -gzip ». Contrôle : la feuille de style pèse 20 607 octets des deux côtés.</li>
<li><b>Réseaux.</b> Simulés par Chromium, une latence ajoutée à chaque requête : fibre 25 ms (l'aller-retour mesuré vers le test), 4G 100 ms à 9 et 3 Mbit/s, chantier 500 ms à 400 et 200 kbit/s. Une vraie 3G perd aussi des paquets : les chiffres du chantier sont optimistes.</li>
<li><b>Deux caches.</b> « Périmé » : chaque page redemande feuille de style, script et polices, ce que fait le test quand le navigateur ne sait plus s'ils sont frais. « Gardé » : rien n'est redemandé. Le vrai cas est entre les deux, selon l'âge du dernier déploiement.</li>
<li><b>Le chemin sans rechargement</b> est mesuré en envoyant la même requête par fetch, sans suivre la redirection : le serveur fait le même travail. Il manque la réponse utile (la ligne et les totaux, 1 à 2 Ko compressés, estimés).</li>
<li><b>Pas mesuré.</b> Un vrai téléphone (processeur plus lent : le rechargement y coûte plus, l'envoi seul presque pareil), Safari, la perte de paquets. Les gestes de l'équipe (administration) ont été lus sans être chronométrés.</li>
</ul>
</section>

<h2 id="gestes" data-toc="Gestes">Tous les gestes</h2>
<section class="point">
<p>Durées médianes, du toucher à l'écran à jour, cache périmé. En vert : le même envoi sans rechargement. « req. » : requêtes au serveur.</p>
<div class="mk-filters" data-target="#liste-gestes">
<button type="button" data-v="all" aria-pressed="true">Tous (${gestes.length})</button>
<button type="button" data-v="inutile" aria-pressed="false">Rechargement inutile (${inutiles.length})</button>
<button type="button" data-v="lourd" aria-pressed="false">Sans rechargement, mais trop lourd (${lourds.length})</button>
<button type="button" data-v="utile" aria-pressed="false">Rechargement utile (${utiles.length})</button>
<button type="button" data-v="peu" aria-pressed="false">Gardé, geste rare (${peu.length})</button>
<button type="button" data-v="bien" aria-pressed="false">Déjà bien (${bien.length})</button>
</div>
<table class="gestes">
<thead><tr><th>Geste</th><th>Aujourd'hui</th><th>Rechargement utile ?</th><th>4G</th><th>Chantier</th><th>Proposé</th></tr></thead>
<tbody id="liste-gestes">
${lignesTableau}
</tbody>
</table>
</section>

<h2 id="socle" data-toc="Socle">Le socle : cache des fichiers, un jeton par page, double toucher</h2>
<section class="point">
<p>Trois corrections sans toucher aux écrans, qui servent à tous les gestes, y compris ceux qui gardent leur page :</p>
<ul class="serre">
<li><b>Cache des fichiers.</b> Dire au navigateur de garder un an la feuille de style, le script, les polices et les images, et changer leur adresse à chaque déploiement (<code>app.css?v=…</code>) ; corriger l'ETag « -gzip ». Mesuré : ouvrir une offre passe de ${k(ouvrir.f4?.octets)} à ${k(ouvrir.k4?.octets)}, ${t(ouvrir.f4?.vu)} à ${t(ouvrir.k4?.vu)} en 4G, ${t(ouvrir.fc?.vu)} à ${t(ouvrir.kc?.vu)} sur le chantier.</li>
<li><b>Un jeton par page.</b> Les 274 produits de « + Ligne » portent chacun un jeton de 124 caractères tirés au hasard : 36,7 Ko compressés au lieu de 9,6 Ko. Un seul formulaire pour la liste suffit, sans JavaScript aussi.</li>
<li><b>Double toucher.</b> Sur un réseau lent, l'écran ne bouge pas pendant plusieurs secondes et le bouton reste actif : l'artisan retouche.</li>
</ul>
${decision('D1 Socle', 'D1. Le socle (cache des fichiers, un jeton par page, double toucher)', [['valider', 'Valider'], ['refuser', 'Pas valider']])}
</section>

<h2 id="offre" data-toc="Offre">L'offre sans rechargement</h2>
<section class="point">
<p>Sur l'offre, presque chaque geste recharge la page pour changer une ligne, une carte ou un total (${gestes.filter((x) => x.lot === 2 && /^(Offre|Ligne|Relecture)/.test(x.ecran)).length} gestes du tableau, lot 2). Proposé : l'écran change au toucher, l'envoi part derrière, le serveur renvoie la ligne et les totaux qu'il a recalculés (TVA, rabais, arrondi restent calculés au serveur, comme aujourd'hui). Un échec remet l'écran comme avant et le dit.</p>
<div class="mk-compare">
${fig('demo-apres-rechargement', 'Après « C\'est juste » aujourd\'hui : la page rechargée, la ligne en haut de l\'écran', `Aujourd'hui : page rechargée, ${t(juste.fc?.vu)} sur le réseau de chantier, la page saute.`)}
${fig('apres-juste', 'Proposé : la ligne validée sur place, un message discret', 'Proposé : la ligne change au toucher, « Enregistrée à l\'instant » quand le serveur a répondu.')}
</div>
<div class="mk-compare">
${fig('demo-corbeille-apres-rechargement', 'Après la corbeille aujourd\'hui : la page rechargée revient en haut de l\'offre', 'Aujourd\'hui, la corbeille : la redirection n\'a pas d\'ancre, l\'offre revient tout en haut. L\'artisan qui retirait la ligne 28 doit redescendre.')}
${fig('apres-corbeille', 'Proposé : la ligne retirée sur place, Annuler en bas', 'Proposé : la ligne part, l\'écran reste où il était, « Annuler » comme aujourd\'hui.')}
</div>
${decision('D2 Gestes de l\'offre', 'D2. Quand l\'artisan touche « C\'est juste », la corbeille, un prix dicté, une option…', [['A', 'A. L\'écran change tout de suite, l\'envoi suit (conseillé)'], ['B', 'B. Un petit sablier jusqu\'à la réponse du serveur'], ['C', 'C. Garder le rechargement']], `A : immédiat sur tous les réseaux, l\'écran se corrige si le serveur refuse (rare). B : ${t(juste.s4?.duree)} en 4G, ${t(juste.sc?.duree)} sur le chantier, sans rien recharger. C : ${t(juste.f4?.vu)} en 4G, ${t(juste.fc?.vu)} sur le chantier.`)}
${decision('D3 Ce qui garde la réponse du serveur', 'D3. « Créer la facture », « Émettre », se connecter et se déconnecter attendent la réponse du serveur avant d\'afficher la suite (numéro de facture, session).', [['valider', 'Valider'], ['refuser', 'Pas valider']])}
</section>

<h2 id="ajouter" data-toc="Ajouter">« + Ligne » et le catalogue dans le téléphone</h2>
<section class="point">
<p>Aujourd'hui, la fenêtre « + Ligne » télécharge tout le catalogue à chaque ouverture : ${ko(c('ajout-ouvrir', 'chantier', 'froid')?.octets)} pour 274 produits, dont environ 27 Ko de jetons de sécurité (un jeton aléatoire par produit, que la compression ne réduit pas ; un seul suffirait, la fenêtre pèserait 9,6 Ko). Chaque produit touché retélécharge tout le catalogue, et « Terminé » recharge l'offre.</p>
<div class="mk-compare">
${fig('avant-ajout', 'La fenêtre Ajouter aujourd\'hui', `Aujourd'hui : ${t(c('ajout-ouvrir', 'chantier', 'froid')?.vu)} pour l'ouvrir sur le réseau de chantier, ${t(c('ajout-produit', 'chantier', 'froid')?.vu)} par produit, ${t(c('ajout-termine', 'chantier', 'froid')?.vu)} pour « Terminé ».`)}
${fig('apres-ajout', 'Proposé : la fenêtre ouverte depuis le catalogue du téléphone', `Proposé : la fenêtre s'ouvre sans requête, chaque produit part en un envoi (${t(ajout.sc?.duree)} sur le chantier), « Terminé » ferme seulement.`)}
</div>
<p>Le catalogue part dans le téléphone quand la connexion est bonne (à l'ouverture de l'application, puis à chaque changement), 3,6 Ko compressés pour les 274 produits (30 Ko bruts, mesuré sur un export JSON de la base), 0,8 Ko pour les 42 clients : moins qu'une seule ouverture de la fenêtre aujourd'hui. Il sert aussi à « Pas dans votre catalogue », au rattachement et à la fiche produit.</p>
<div class="paire">
${fig('apres-ajout-hors-ligne', 'Proposé : la fenêtre Ajouter sans réseau', 'Sans réseau, avec le lot 4 : la fenêtre s\'ouvre quand même, le produit ajouté attend dans le téléphone et le dit.')}
</div>
${decision('D4 Catalogue dans le téléphone', 'D4. Quand le téléphone reçoit-il le catalogue ?', [['A', 'A. À l\'ouverture de l\'application, puis à chaque changement (conseillé)'], ['B', 'B. À la première ouverture de « + Ligne »'], ['C', 'C. Jamais : la fenêtre se télécharge à chaque fois, allégée']])}
</section>

<h2 id="reseau" data-toc="Réseau">Sans réseau, ou presque</h2>
<section class="point">
<h3>Aujourd'hui</h3>
<p>Sur un réseau lent, rien ne dit que le toucher a été pris : seuls les formulaires à identifiant changent de bouton pendant l'envoi (<code>envoisDeFormulaires</code> dans <code>app.js</code>), et « C'est juste », la corbeille, l'archivage n'en ont pas. L'écran reste figé jusqu'à la page suivante, ${t(c('offre-juste', 'chantier', 'froid')?.vu)} plus tard sur le chantier. On retouche.</p>
<div class="trio">
${fig('hors-ligne-juste', 'Sans réseau : la page d\'erreur de Chrome', 'Sans réseau, « C\'est juste » : la page d\'erreur du navigateur remplace l\'offre.')}
${fig('hors-ligne-quantite', 'Sans réseau : une quantité tapée, message Non enregistré', `Sans réseau, une quantité : « ${esc(hl('hors-ligne-quantite').message || 'Non enregistré')} ».`)}
${fig('hors-ligne-ajout', 'Sans réseau : « + Ligne »', 'Sans réseau, « + Ligne » : la fenêtre échoue et le navigateur part sur la page d\'erreur.')}
</div>
<div class="trio">
${fig('hors-ligne-ouvrir', 'Sans réseau : ouvrir une offre', 'Sans réseau, ouvrir une offre depuis la liste : page d\'erreur.')}
${fig('lent-deux-corbeilles', 'Réseau de chantier : deux touchers sur la corbeille', 'Réseau de chantier, deux corbeilles touchées à 0,7 s d\'écart : la page qui arrive (constat ci-dessous).')}
${fig('session-expiree-quantite', 'Session perdue : 33 tapé, le total reste celui de 24', `Session perdue, « 33 » tapé : le chiffre reste affiché, le total de la ligne reste celui de 24, l'indicateur en haut de l'offre reste sur « ${esc(hl('session-expiree-quantite').message || '')} ». Rien n'est enregistré, rien ne le dit.`)}
</div>
${constatsReseau()}
<h3>Proposé</h3>
<div class="trio">
${fig('apres-hors-ligne', 'Proposé sans réseau : bandeau, lignes en attente, total provisoire', 'Sans réseau : un bandeau le dit, les lignes changées portent « en attente d\'envoi », le total est provisoire.')}
${fig('apres-retour-reseau', 'Proposé : le réseau revient, les changements partent', 'Le réseau revient : la file part seule, un message le dit.')}
${fig('apres-liste-hors-ligne', 'Proposé : la liste des offres sans réseau', 'Les offres ouvertes ces 30 derniers jours restent lisibles et modifiables.')}
</div>
<p>Comment : un « service worker » (un petit script que le navigateur garde) sert la feuille de style, le script et les pages déjà vues quand le réseau manque ; chaque geste fait hors réseau part dans une file d'attente rangée dans le téléphone, avec une clé unique pour que le serveur ne l'applique jamais deux fois (même principe que la dictée à l'abri d'aujourd'hui).</p>
${decision('D5 Hors réseau', 'D5. Que peut faire l\'artisan sans réseau ?', [['A', 'A. Tout ce qui touche une offre déjà ouverte : les changements attendent dans le téléphone (conseillé)'], ['B', 'B. Lire seulement : offres et catalogue lisibles, modifications bloquées avec un message'], ['C', 'C. Rien de plus qu\'un message clair à la place de la page d\'erreur']])}
${decision('D6 Ce que le téléphone garde', 'D6. Ce que le téléphone garde, effacé à la déconnexion', [['A', 'A. Catalogue, clients et offres des 30 derniers jours (conseillé)'], ['B', 'B. Catalogue seul'], ['C', 'C. Rien']], 'Données de clients de l\'artisan (noms, adresses) : à écrire dans la politique de confidentialité, comme la dictée gardée dans le téléphone.')}
${decision('D7 Total sans réseau', 'D7. Le total, sans réseau', [['A', 'A. Total provisoire calculé dans le téléphone, recalculé par le serveur au retour du réseau'], ['B', 'B. « Total à recalculer », sans montant']], 'A : le téléphone fait la somme des lignes ; rabais, TVA et arrondi restent au serveur. B : aucun risque de montant faux à l\'écran.')}
</section>

<h2>Le PDF et « Envoyer »</h2>
<section class="point">
<p>« Générer l'offre » ouvre une page, télécharge PDF.js (515 Ko compressés, bibliothèque et worker) quand le cache est périmé, et fait refabriquer le PDF au serveur (0,3 s pour 6 lignes, 0,6 s pour 33) : ${t(generer.l?.duree)} en local, ${t(generer.f4?.duree)} en 4G, ${t(generer.fc?.duree)} sur le chantier, jusqu'au PDF dessiné. Avec PDF.js gardé dans le navigateur (lot 1) : ${t(c('generer', '4g', 'chaud')?.duree)} en 4G, ${t(c('generer', 'chantier', 'chaud')?.duree)} sur le chantier. « Envoyer » redemande ce même PDF au serveur avant d'ouvrir le partage du téléphone, puis recharge la page pour noter l'envoi.</p>
<ul class="serre">
<li>Partager le PDF déjà affiché : aucune requête de plus.</li>
<li>Noter « envoyée » sans recharger la page.</li>
<li>Garder le PDF au serveur tant que l'offre ne change pas.</li>
<li><b>Hypothèse, non vérifiée :</b> sur iPhone, Safari n'accepte d'ouvrir le partage que peu après le toucher ; si le PDF met plus longtemps à arriver, le partage peut être refusé sans rien dire. À essayer sur ton iPhone en 3G.</li>
</ul>
${decision('D8 PDF et envoi', 'D8. PDF partagé tel qu\'affiché, « envoyée » sans rechargement, PDF gardé tant que l\'offre ne change pas', [['valider', 'Valider'], ['refuser', 'Pas valider']])}
</section>

<h2>Parler : déjà sans rechargement</h2>
<section class="point">
<p>La dictée est le seul long chemin qui ne recharge rien : l'enregistrement part avec sa progression, reste à l'abri dans le téléphone jusqu'à l'offre prête, et les étapes avancent seules (une question au serveur chaque seconde). Mesuré avec une dictée de peintre de 28 s et la vraie transcription :</p>
<table class="simple"><thead><tr><th>Réseau</th><th>Envoi de la dictée</th><th>Offre prête</th><th>Offre affichée</th><th>Questions « où en est-on ? »</th></tr></thead><tbody>
${[dicteeFibre, dicteeChantier].filter(Boolean).map((d) => `<tr><td>${d.profil === 'fibre' ? 'Fibre' : 'Chantier'}</td><td class="n">${t(d.envoi)}</td><td class="n">${t(d.offrePrete)}</td><td class="n">${t(d.offreAffichee)}</td><td class="n">${d.suivis} (${ko(d.suiviOctets)})</td></tr>`).join('') || '<tr><td colspan="5">Mesure à venir.</td></tr>'}
</tbody></table>
<div class="paire">
${fig('dictee-chantier-suivi', 'La fenêtre de préparation pendant l\'envoi de la dictée', 'Réseau de chantier : la fenêtre de préparation pendant l\'envoi, avec sa progression. Rien ne se recharge.')}
</div>
<p>Rien à changer pour la fluidité. L'offre prête ouvre une page entière : c'est un autre écran, le rechargement y est utile. Le temps d'attente vient de la file de transcription d'Infomaniak (56 s puis 25 s pour la même dictée, à quelques minutes d'écart).</p>
</section>

<h2>Trois façons de faire</h2>
<section class="point">
<div class="approches">
<div class="approche conseillee"><h3>A. Morceaux de page, script maison (conseillé)</h3>
<p>Le serveur garde ses pages. Pour un envoi fait par le script, il répond par les morceaux qui changent (la ligne, les totaux, la carte) au lieu d'une redirection ; <code>app.js</code> les remplace.</p>
<ul class="plus-moins"><li class="plus">Aucune dépendance nouvelle, conforme à <code>CODING_RULES.md</code>.</li><li class="plus">Les calculs restent au serveur : TVA, rabais, arrondi, options ne sont pas écrits deux fois.</li><li class="plus">Sans JavaScript, les formulaires marchent comme aujourd'hui.</li><li class="plus">Geste par geste : chaque lot se livre et se teste seul.</li><li class="moins">Chaque contrôleur apprend une seconde réponse ; environ 300 lignes de script à écrire.</li></ul></div>
<div class="approche"><h3>B. Une bibliothèque (htmx)</h3>
<p>Même principe, porté par une bibliothèque : des attributs dans les gabarits disent quel morceau remplacer.</p>
<ul class="plus-moins"><li class="plus">Moins de script à écrire pour les échanges.</li><li class="moins">Une dépendance qui donne la forme des écrans : <code>CODING_RULES.md</code> l'écarte.</li><li class="moins">Réglages à faire pour la politique de sécurité (pas de code évalué, pas de style en ligne).</li><li class="moins">Rien pour le hors réseau : la file d'attente reste à écrire.</li></ul></div>
<div class="approche"><h3>C. Application JavaScript complète</h3>
<p>Le serveur ne rend plus que des données ; le téléphone dessine tous les écrans.</p>
<ul class="plus-moins"><li class="plus">La plus fluide, le hors réseau devient naturel.</li><li class="moins">Totaux, TVA, rabais, options réécrits dans le navigateur : deux calculs qui peuvent diverger du PDF.</li><li class="moins">Tous les écrans à refaire : des semaines, et un cadre applicatif que tu as écarté.</li></ul></div>
</div>
${decision('D9 Approche', 'D9. Quelle approche ?', [['A', 'A. Morceaux de page, script maison (conseillé)'], ['B', 'B. Bibliothèque htmx'], ['C', 'C. Application JavaScript complète']])}
</section>

<h2>Lots, ordre et estimation</h2>
<section class="point">
<table class="simple"><thead><tr><th>Lot</th><th>Contenu</th><th>Estimation</th></tr></thead><tbody>
<tr><td>1. Socle</td><td>Cache des fichiers et adresses versionnées, ETag corrigé, un jeton par page, double toucher.</td><td class="n">3 h</td></tr>
<tr><td>2. L'offre</td><td>Les ${gestes.filter((x) => x.lot === 2).length} gestes de l'offre, de la relecture, de la liste et du brouillon de facture changent sur place ; la ligne s'ouvre en fenêtre au téléphone ; réponses réduites pour quantité et prix.</td><td class="n">12 h</td></tr>
<tr><td>3. Ajouter et catalogue</td><td>Catalogue et clients dans le téléphone, « + Ligne » sans requête, catalogue et clients sans retéléchargement de la liste, tri et catégories dans le navigateur.</td><td class="n">8 h</td></tr>
<tr><td>4. Hors réseau</td><td>Service worker, file d'envoi avec clé unique, bandeau, total provisoire, effacement à la déconnexion, essais de coupure.</td><td class="n">16 h</td></tr>
<tr><td>5. PDF et envoi</td><td>PDF partagé tel qu'affiché, « envoyée » sans rechargement, PDF gardé tant que l'offre ne change pas.</td><td class="n">3 h</td></tr>
<tr><td>Revue et recette</td><td>Sur le site de test, réseau lent et coupé, iPhone et Android.</td><td class="n">5 h</td></tr>
</tbody></table>
<p>Total : 47 h, estimation de Claude. La tâche Origin 504 en prévoyait 16, pour la relecture et la création d'un produit seulement.</p>
${decision('D10 Ordre', 'D10. Ordre des lots : 1, 2, 3, 4, 5', [['valider', 'Valider'], ['autre', 'Autre ordre (dans le commentaire)']])}
</section>

<h2>Risques et limites</h2>
<section class="point">
<ul class="serre">
<li><b>iPhone.</b> Safari peut effacer ce qu'un site a rangé dans le téléphone après 7 jours sans visite (application non installée sur l'écran d'accueil). Ce que le téléphone garde peut disparaître : la file d'envoi part dès que possible et le dit si elle attend.</li>
<li><b>Deux appareils.</b> Une ligne changée hors réseau sur le téléphone et au PC entre-temps : le dernier envoi gagne, l'écran le signale.</li>
<li><b>Session expirée hors réseau.</b> La file attend la reconnexion, puis repart avec un jeton neuf.</li>
<li><b>Données dans le téléphone.</b> Noms et adresses des clients de l'artisan : effacés à la déconnexion, rangés par compte (comme la dictée à l'abri), à écrire dans la politique de confidentialité.</li>
<li><b>Mesures.</b> Réseaux simulés sans perte de paquets, processeur de PC : un vrai téléphone sur un vrai chantier sera plus lent, surtout pour les rechargements.</li>
</ul>
</section>

<h2 id="reponses" data-toc="Réponses">Copier mes réponses</h2>
<div class="recap">
<p>Ce texte se remplit avec tes choix. Copie-le et colle-le dans la conversation.</p>
<textarea id="recap" readonly aria-label="Récapitulatif des réponses"></textarea>
<p><button type="button" class="copier" id="copier">Copier le texte</button><span class="copie-ok" id="copie-ok" hidden>Copié.</span></p>
</div>
</main>

<script>
(function () {
  var CLE = 'offregen-sans-rechargement-2026-11-23';
  var etat = {};
  try { etat = JSON.parse(localStorage.getItem(CLE) || '{}') || {}; } catch (e) { etat = {}; }
  function garder() { try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) {} }
  var blocs = document.querySelectorAll('.decision');
  function recap() {
    var lignes = [];
    blocs.forEach(function (b) {
      var q = b.getAttribute('data-q'), r = etat[q] || {};
      var choisi = b.querySelector('.opts button[data-v="' + (r.v || '') + '"]');
      var l = q + ' : ' + (choisi ? choisi.textContent : '(pas de réponse)');
      if (r.c) l += '. Commentaire : ' + r.c.replace(/\\s+/g, ' ').trim();
      lignes.push(l);
    });
    document.getElementById('recap').value = lignes.join('\\n');
  }
  blocs.forEach(function (b) {
    var q = b.getAttribute('data-q');
    etat[q] = etat[q] || {};
    var boutons = b.querySelectorAll('.opts button');
    var champ = b.querySelector('textarea');
    boutons.forEach(function (bt) {
      bt.setAttribute('aria-pressed', bt.getAttribute('data-v') === etat[q].v ? 'true' : 'false');
      bt.addEventListener('click', function () {
        etat[q].v = etat[q].v === bt.getAttribute('data-v') ? '' : bt.getAttribute('data-v');
        boutons.forEach(function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-v') === etat[q].v ? 'true' : 'false'); });
        garder(); recap();
      });
    });
    if (champ) {
      champ.value = etat[q].c || '';
      champ.addEventListener('input', function () { etat[q].c = champ.value; garder(); recap(); });
    }
  });
  recap();
  document.getElementById('copier').addEventListener('click', function () {
    var t = document.getElementById('recap');
    var ok = function () { var s = document.getElementById('copie-ok'); s.hidden = false; setTimeout(function () { s.hidden = true; }, 2000); };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t.value).then(ok, function () { t.select(); document.execCommand('copy'); ok(); }); }
    else { t.select(); document.execCommand('copy'); ok(); }
  });

  // Démo des deux téléphones : durées mesurées par le banc (offre-juste), en millisecondes.
  var DUREES = ${JSON.stringify(demo)};
  var IMG = '${IMG}/';
  var reseau = 'chantier';
  var minuteries = [];
  var g = document.getElementById('demo-g'), d = document.getElementById('demo-d');
  var cg = document.getElementById('demo-chrono-g'), cd = document.getElementById('demo-chrono-d');
  var eg = document.getElementById('demo-etat-g'), ed = document.getElementById('demo-etat-d');
  var cache = document.getElementById('demo-cache');
  function secondes(ms) { return (ms / 1000).toFixed(1).replace('.', ',') + '\\u00a0s'; }
  function remettre() {
    minuteries.forEach(clearTimeout); minuteries.forEach(clearInterval); minuteries = [];
    g.src = IMG + 'demo-avant.jpg'; d.src = IMG + 'demo-avant.jpg';
    cg.hidden = true; cd.hidden = true; eg.textContent = 'Prête.'; ed.textContent = 'Prête.';
  }
  document.querySelectorAll('[data-reseau]').forEach(function (b) {
    b.addEventListener('click', function () {
      reseau = b.getAttribute('data-reseau');
      document.querySelectorAll('[data-reseau]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      remettre();
    });
  });
  cache.addEventListener('change', remettre);
  document.getElementById('demo-lancer').addEventListener('click', function () {
    remettre();
    var m = DUREES[reseau] || [];
    var avant = (cache.checked ? m[1] : m[0]) || 0, envoi = m[2] || 0, debut = Date.now();
    cg.hidden = false; cg.textContent = '0,0\\u00a0s';
    eg.textContent = 'Touché. La page se recharge…';
    var tic = setInterval(function () { cg.textContent = secondes(Math.min(Date.now() - debut, avant)); }, 50);
    minuteries.push(tic);
    minuteries.push(setTimeout(function () {
      clearInterval(tic); cg.textContent = secondes(avant); g.src = IMG + 'demo-apres-rechargement.jpg';
      eg.textContent = 'Page rechargée après ' + secondes(avant) + ', elle a sauté.';
    }, avant));
    d.src = IMG + 'apres-juste.jpg';
    cd.hidden = false; cd.textContent = '0,0\\u00a0s';
    ed.textContent = 'Ligne validée au toucher. Envoi en cours…';
    minuteries.push(setTimeout(function () { cd.textContent = secondes(envoi); ed.textContent = 'Serveur d\\u2019accord après ' + secondes(envoi) + '. L\\u2019artisan n\\u2019a rien attendu.'; }, envoi));
  });
})();
</script>
</body>
</html>
`;

fs.mkdirSync(path.join(DOSSIER, IMG), { recursive: true });
fs.writeFileSync(path.join(DOSSIER, 'MAQUETTE-sans-rechargement.html'), html);
console.log('maquette écrite :', html.length, 'caractères ;', gestes.length, 'gestes ;', inutiles.length, 'inutiles,', lourds.length, 'lourds,', utiles.length, 'utiles,', bien.length, 'déjà bien');
