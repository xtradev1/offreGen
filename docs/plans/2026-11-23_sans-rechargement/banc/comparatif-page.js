// Génère COMPARATIF-sans-rechargement.html dans le dossier du chantier : avant / après des lots 1 à 5, global puis geste
// par geste, sur quatre réseaux. Usage : node comparatif-page.js <dossier du chantier, vu de Windows>
// Entrées : comparatif.json (comparatif.js), recette-*.json, et, s'ils existent : deploiement.json, preuve-d1.json,
// preuve-prechargement.json. Les images « avant » sont celles de l'audit (MAQUETTE-sans-rechargement-img).
'use strict';
const fs = require('fs');
const path = require('path');

const DOSSIER = process.argv[2];
const IMG = 'COMPARATIF-sans-rechargement-img';
const AV = 'MAQUETTE-sans-rechargement-img';
const ICI = __dirname;
const lireJson = (f, defaut) => { try { return JSON.parse(fs.readFileSync(path.join(ICI, f), 'utf8')); } catch (e) { return defaut; } };
const { lignes, resume } = lireJson('comparatif.json', { lignes: [], resume: {} });
const horsLigne = lireJson('recette-hors-ligne.json', []);
const deploiement = lireJson('deploiement.json', null);
const preuveD1 = lireJson('preuve-d1.json', null);
const preuvePre = lireJson('preuve-prechargement.json', null);
const session = lireJson('recette-session.json', null);
// La campagne faite avant le correctif du service worker (page rendue après ses fichiers) : ses chiffres sont cités.
const avantCorrectif = (() => {
  try {
    return fs.readFileSync(path.join(ICI, 'apres-resultats-avant-correctif-sw.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
  } catch (e) { return []; }
})();
const vuAvantCorrectif = (id, p) => { const l = avantCorrectif.filter((x) => x.id === id && x.profil === p && !x.echec).pop(); return l ? l.vu : null; };

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const virgule = (n, d) => n.toFixed(d).replace('.', ',');
const ms = (v) => (v === null || v === undefined ? 'n. m.' : v < 1000 ? Math.round(v) + ' ms' : virgule(v / 1000, 1) + ' s');
const ko = (o) => {
  if (o === null || o === undefined) return 'n. m.';
  const k = o / 1024;
  if (k >= 1024) return virgule(k / 1024, 1) + ' Mo';
  if (k === 0) return '0 Ko';
  if (k < 10) return virgule(k, 1) + ' Ko';
  return Math.round(k) + ' Ko';
};
const L = (id, p) => lignes.find((x) => x.id === id && x.profil === p) || null;
const av = (id, p) => (L(id, p) || {}).avant || null;
const ap = (id, p) => (L(id, p) || {}).apres || null;
const hl = (cle) => (horsLigne.find((h) => h.cle === cle) || {}).valeur;
const fleche = '<span class="fleche">→</span>';
const passe = (a, b) => `${ms(a)} ${fleche} <b class="ok">${ms(b)}</b>`;
const R = resume;
const PROFILS = ['local', 'fibre', '4g', 'chantier'];
const NOMS = { local: 'Local', fibre: 'Fibre', '4g': '4G', chantier: 'Chantier' };
const RESEAUX = {
  local: 'même machine, aucun délai ajouté',
  fibre: '25 ms par aller-retour, 100 Mbit/s',
  '4g': '100 ms, 9 Mbit/s en réception, 3 en envoi',
  chantier: '500 ms, 400 kbit/s en réception, 200 en envoi (3G faible)',
};

// Ce que fait chaque geste maintenant, constaté dans le code et au banc (l'audit disait ce qui était proposé).
const FAIT = {
  'liste-ouvrir-offre': 'Change de page (voulu). Style, scripts et polices viennent du téléphone : seule la page voyage.',
  'liste-tri': 'Tri dans le téléphone, aucune requête.',
  'liste-archiver': 'La carte part au toucher, « Annuler » tout de suite ; l\'envoi suit.',
  'liste-supprimer': 'La carte part au toucher, « Annuler » tout de suite ; l\'envoi suit.',
  'liste-restaurer': 'L\'offre revient au toucher, à sa place dans le tri ; l\'envoi suit.',
  'liste-retour': 'Change de page (voulu), aussi vite qu\'avant.',
  'onglet-clients': 'Change de page (voulu) ; seule la page voyage.',
  'onglet-plus': 'Change de page (voulu) ; seule la page voyage.',
  'plus-catalogue': 'Change de page (voulu) ; seule la page voyage.',
  'plus-reglages': 'Change de page (voulu) ; seule la page voyage.',
  'offre-juste': 'L\'étiquette part au toucher, l\'envoi suit.',
  'offre-corbeille': 'La ligne part au toucher avec son « Annuler » ; total provisoire, confirmé par le serveur.',
  'offre-annuler': 'La ligne revient au toucher, à sa place ; l\'envoi suit.',
  'offre-prix-dicte': 'Le bouton se grise au toucher ; la question suivante vient du serveur, l\'écran l\'attend.',
  'offre-prix-catalogue': 'Le bouton se grise au toucher ; le prix et les totaux viennent du serveur, l\'écran les attend.',
  'offre-quantite': 'Montant de la ligne et total provisoires dès la frappe ; le serveur confirme.',
  'offre-prix': 'Montant de la ligne et total provisoires dès la frappe ; le serveur confirme.',
  'offre-option': 'La case change au toucher ; « Retenue » et les totaux suivent, du serveur.',
  'offre-texte': 'Texte gardé à la frappe, envoyé en fond.',
  'offre-ouvrir-ligne': 'Fenêtre du bas sur l\'offre, sans quitter l\'écran.',
  'ligne-enregistrer': 'La fenêtre attend la réponse, puis la ligne change sur place, sans rechargement.',
  'ajout-ouvrir': 'Fenêtre préparée à l\'ouverture de l\'offre : elle s\'ouvre sans requête.',
  'ajout-produit': '« Ajoutée » au toucher, la fenêtre reste ouverte ; l\'envoi suit.',
  'ajout-termine': 'Les lignes sont déjà dans l\'offre : la fenêtre se ferme sans requête.',
  'ajout-libre': 'Comme un produit : au toucher, l\'envoi suit.',
  'client-offre': 'La fenêtre attend la réponse, puis la carte du client change sur place.',
  'representant': 'La fenêtre attend la réponse, puis la carte change sur place.',
  'rabais': 'La fenêtre attend la réponse (le serveur calcule les totaux), puis ils changent sur place.',
  'conditions': 'La fenêtre attend la réponse, puis la carte change sur place.',
  'ligne-rattacher': 'La fenêtre attend la réponse, puis la ligne change sur place.',
  'ligne-creer-produit': 'La fenêtre attend la réponse, puis la ligne change sur place.',
  'offre-objet': 'La fenêtre attend la réponse, puis l\'objet change sur place.',
  'relire-ouvrir': 'Change de page (voulu) ; toutes les lignes à relire arrivent ensemble.',
  'relire-juste': 'Ligne suivante au toucher, bouton retenu 700 ms pour la lire ; l\'envoi suit.',
  'generer': 'Change de page (voulu). Offre modifiée : PDF refait par le serveur. Offre inchangée : PDF gardé, ouvert sans le retélécharger.',
  'facturer-ouvrir': 'Fenêtre, comme avant ; ses fichiers viennent du téléphone.',
  'facturer-creer': 'Change de page (voulu) ; attend toujours la confirmation du serveur, jamais mis en attente.',
  'catalogue-filtrer': 'Filtre dans le téléphone, comme avant.',
  'catalogue-categorie': 'Catégorie choisie dans le téléphone, aucune requête.',
  'catalogue-fiche': 'Fenêtre, comme avant.',
  'catalogue-modifier': 'La fenêtre se ferme, seule la ligne du produit change.',
  'catalogue-nouveau': 'La fenêtre se ferme, le produit paraît dans la liste sans rechargement.',
  'client-fiche': 'Fenêtre, comme avant.',
  'client-champ': 'Enregistré seul après la frappe, comme avant. Hors de la file : sans réseau, l\'écran dit « Pas enregistré ».',
  'client-nouveau': 'La fenêtre se ferme, la liste des clients revient entière, sans rechargement.',
  'reglages-rubrique': 'Change de page (voulu) ; seule la page voyage.',
  'reglages-champ': 'Enregistré seul après la frappe, comme avant. Hors de la file : sans réseau, l\'écran dit « Pas enregistré ».',
};
const FENETRES = ['client-offre', 'representant', 'rabais', 'conditions', 'ligne-rattacher', 'ligne-creer-produit', 'offre-objet', 'offre-ouvrir-ligne', 'ligne-enregistrer'];
const groupe = (x) => {
  if (FENETRES.includes(x.id)) return 'fenetres';
  if (['Mes offres', 'Barre du bas', 'Plus'].includes(x.ecran) || x.id === 'liste-retour') return 'liste';
  if (x.ecran === 'Ajouter' || x.id === 'ajout-ouvrir') return 'ajout';
  if (['Relecture', 'Offre envoyée', 'Facturer'].includes(x.ecran) || ['relire-ouvrir', 'generer', 'facturer-ouvrir'].includes(x.id)) return 'suite';
  if (['Catalogue', 'Clients', 'Fiche client', 'Réglages'].includes(x.ecran)) return 'fiches';
  return 'offre';
};
const GROUPES = [['liste', 'Liste et onglets'], ['offre', 'Offre'], ['ajout', '« + Ligne »'], ['fenetres', 'Fenêtres de l\'offre'], ['suite', 'Relecture, PDF, facture'], ['fiches', 'Catalogue, clients, réglages']];

// ---------- Chiffres de tête
const tc = R.chantier ? R.chantier.tous : null;
const tf = R.chantier ? R.chantier.tresFrequents : null;
const t4 = R['4g'] ? R['4g'].tous : null;
const somme = (ids, p, cote, champ) => ids.reduce((s, id) => { const m = cote === 'avant' ? av(id, p) : ap(id, p); return s + (m ? m[champ] || 0 : 0); }, 0);
const AJOUT = ['ajout-ouvrir', 'ajout-produit', 'ajout-termine'];

const tableauGlobal = PROFILS.map((p) => {
  const r = R[p] ? R[p].tous : null;
  const f = R[p] ? R[p].tresFrequents : null;
  if (!r) return '';
  return `<tr><th scope="row">${NOMS[p]}<small>${RESEAUX[p]}</small></th>
<td class="n">${passe(r.vu[0], r.vu[1])}<small>très fréquents (${f.gestes}) : ${ms(f.vu[0])} → ${ms(f.vu[1])}</small></td>
<td class="n">${passe(r.tout[0], r.tout[1])}</td>
<td class="n">${ko(r.octets[0])} ${fleche} <b class="ok">${ko(r.octets[1])}</b><small>${r.requetes[0]} → ${r.requetes[1]} requêtes</small></td>
<td class="n">${r.recharges[0]} ${fleche} <b class="ok">${r.recharges[1]}</b></td></tr>`;
}).join('\n');

// ---------- Une carte de geste : chiffres du chantier et de la 4G
const chiffresDe = (id, libelle) => {
  const ligne = (p) => {
    const a = av(id, p);
    const b = ap(id, p);
    if (!a || !b) return '';
    return `<li><b>${NOMS[p]}</b> : à l'écran ${passe(a.vu, b.vu)} ; fini ${ms(a.tout)} → ${ms(b.tout)} ; ${ko(a.octets)} → ${ko(b.octets)}${a.recharge && !b.recharge ? ' ; plus de rechargement' : ''}.</li>`;
  };
  return `<ul class="serre">${libelle ? `<li class="titre">${libelle}</li>` : ''}${ligne('chantier')}${ligne('4g')}</ul>`;
};
const figure = (src, alt, legende) => `<figure><img src="${src}" alt="${esc(alt)}" loading="lazy"><figcaption>${legende}</figcaption></figure>`;
const a = (n) => `${AV}/${n}.jpg`;
const b = (n) => `${IMG}/${n}.jpg`;

const decision = (q, libelle, options, aide) => `<div class="decision" data-q="${esc(q)}">
<p class="q">${libelle}</p>
<div class="opts">${options.map(([v, texte]) => `<button type="button" data-v="${v}">${texte}</button>`).join('')}</div>
${aide ? `<p class="aide">${aide}</p>` : ''}<textarea aria-label="Commentaire : ${esc(q)}" placeholder="Commentaire (facultatif)"></textarea>
</div>`;

// ---------- Tableau détaillé
const ids = [...new Set(lignes.map((x) => x.id))];
const celluleDe = (m, cote, p, x) => {
  if (!m) return `<div class="r r-${p}"><span class="muted">non mesuré</span></div>`;
  const page = m.recharge ? (cote === 'apres' ? 'autre page' : 'page rechargée') : 'sur place';
  const garde = cote === 'apres' && x.apres_pdf_garde ? `<small>PDF déjà vu : ${ms(x.apres_pdf_garde.vu)}, ${ko(x.apres_pdf_garde.octets)}</small>` : '';
  return `<div class="r r-${p}"><b${cote === 'apres' ? ' class="ok"' : ''}>${ms(m.vu)}</b> à l'écran<small>fini ${ms(m.tout)} · ${m.requetes} req. · ${ko(m.octets)} · ${page}</small>${garde}</div>`;
};
const gainDe = (x) => {
  if (!x.avant || !x.apres) return '';
  const d = x.avant.vu - x.apres.vu;
  // Sous 50 ms d'écart, deux passages du même geste ne se distinguent pas (bruit du banc) : « pareil ».
  if (Math.abs(d) < 50) return '<span class="muted">pareil</span>';
  return d > 0 ? `<b class="ok">−${ms(d)}</b>` : `<b class="bad">+${ms(-d)}</b>`;
};
const lignesDetail = ids.map((id) => {
  const parP = Object.fromEntries(PROFILS.map((p) => [p, L(id, p)]));
  const x = parP.chantier || parP['4g'] || parP.local;
  return `<tr data-v="${groupe(x)}">
<td data-label="Geste"><b>${esc(x.geste)}</b><small>${esc(x.ecran)} · ${esc(x.frequence)}</small></td>
<td data-label="Avant" class="n">${PROFILS.map((p) => celluleDe(parP[p] && parP[p].avant, 'avant', p, parP[p] || {})).join('')}</td>
<td data-label="Après" class="n">${PROFILS.map((p) => celluleDe(parP[p] && parP[p].apres, 'apres', p, parP[p] || {})).join('')}</td>
<td data-label="Gain à l'écran" class="n">${PROFILS.map((p) => `<div class="r r-${p}">${parP[p] ? gainDe(parP[p]) : ''}</div>`).join('')}</td>
<td data-label="Maintenant">${esc(FAIT[id] || '')}</td>
</tr>`;
}).join('\n');
const compteGroupe = (g) => ids.filter((id) => groupe(L(id, 'chantier') || L(id, 'local')) === g).length;

// ---------- Hors réseau : constats de la recette (coupure réelle du serveur)
const enBase = hl('en base au retour du réseau') || {};
const constatsHors = [
  ['Trois gestes faits pendant la coupure (« C\'est juste », une quantité, « Bâche de protection » ajoutée)', `bandeau : « ${esc(hl('bandeau après trois gestes') || '')} » ; en base pendant la coupure : ${esc(String(hl('en base pendant la coupure : lignes')))} ligne ajoutée.`],
  ['Total provisoire', `${esc(hl('total estimé') || '')} après le premier geste, recalculé par le serveur au retour.`],
  ['La liste, puis une autre offre, ouvertes sans réseau', `${esc(String(hl('liste sans réseau : page gardée') || ''))} ; offre 2 : ${esc(String(hl('autre offre sans réseau (/offres/2)') || ''))}.`],
  ['Offre 3 rouverte sans réseau', `affichée après ${esc(String(hl('offre 3 rouverte : affichée après (ms)') || ''))} ms, ses trois changements toujours en attente et nommés.`],
  ['Page jamais ouverte, sans réseau', 'une page de l\'application le dit (« Cette page n\'a pas été gardée dans le téléphone ») ; plus de dinosaure de Chrome.'],
  ['Retour du réseau', `en base : étiquette ${enBase.etiquette === '0' ? 'levée' : esc(String(enBase.etiquette))}, quantité ${enBase.quantite ? esc(String(Number(enBase.quantite) / 1000)) : '?'}, ${esc(String(enBase.lignes_en_plus))} ligne ajoutée, ${esc(String(enBase.cles))} clés d'envoi ; après un rechargement, toujours ${esc(String(hl('après rechargement : lignes en plus (1 attendue)')))} ligne en plus : rien en double.`],
  ['Session perdue pendant un geste', `bandeau : « ${esc(hl('session perdue : bandeau') || '')} » ; en base, rien tant que la session manque (${esc(String(Number(hl('session perdue : en base')) / 1000))}) ; reconnecté : ${esc(String(Number(hl('session revenue : en base (4000 attendu)')) / 1000))}, la valeur tapée.`],
  ['Déconnexion', `pages gardées effacées (${JSON.stringify(hl('après déconnexion : caches de pages') || [])}) ; file du compte vidée ; les dictées à l'abri restent.`],
  ['Erreurs JavaScript pendant toute la recette', String((hl('erreurs JavaScript') || []).length)],
].map(([q, r]) => `<tr><th scope="row">${q}</th><td>${r}</td></tr>`).join('\n');

// ---------- Revue
const REVUE = [
  ['Bloquante', 'Une clé d\'envoi restée vide (processus tué) bloquait la file de l\'artisan pendant 30 jours.', 'Réservation abandonnée libérée après 3 minutes.', 'test EnvoiCleTest'],
  ['Bloquante', 'Corbeille d\'une ligne à variantes : la variante paraissait deux fois, trois après « Annuler ».', 'La liste entière revient pour une ligne à variantes.', 'recette, capture ci-dessous'],
  ['Bloquante', 'La carte des conditions portait l\'id de son formulaire : « Enregistrer » ne faisait rien.', 'Carte renommée ; test qui interdit les doublons d\'id.', 'test rouge avant, vert après'],
  ['Bloquante', '« Créer la facture » passait par la file : sans réseau, facture créée plus tard, en silence, peut-être deux fois.', 'Les actes qui mènent ailleurs n\'entrent jamais dans la file.', 'recette hors réseau, capture ci-dessous'],
  ['Importante', 'Un geste rejoué perdait ses messages (« Annuler », refus).', 'Messages rangés avec la clé, rendus au rejeu.', 'test'],
  ['Importante', 'Gestes envoyés en vrac à la fermeture de la page.', 'Le geste en vol finit seul, les autres repartent dans l\'ordre.', 'recette hors réseau'],
  ['Importante', 'Deux comptes dans le même navigateur : un geste pouvait partir dans la session de l\'autre.', 'Chaque geste porte son compte ; le serveur refuse l\'autre (409), le téléphone garde le geste.', 'test'],
  ['Importante', 'Le service worker pouvait montrer les pages d\'un compte à un autre.', 'Copies lues sous le seul compte noté, effacées à toute entrée de session.', 'lecture, recette'],
  ['Importante', 'Données de clients gardées sans limite dans le téléphone.', '14 jours au plus, effacées à la déconnexion. Reste à toi : la mention dans la politique de confidentialité.', 'test PdfGardeTest'],
  ['Importante', 'Corriger une quantité puis toucher la corbeille faisait revivre la ligne.', 'Une ligne retirée reste cachée quelle que soit la réponse suivante.', 'recette'],
  ['Importante', 'Relecture : deux touchers rapides validaient une ligne pas vue.', 'Bouton retenu 700 ms après chaque ligne.', 'recette'],
  ['Importante', 'Sans réseau, « Envoyer » partageait le PDF sans les changements en attente.', '« Envoyer » le dit et attend ; la file partie, l\'aperçu se refait.', 'lecture'],
  ['Importante', 'Une page gardée ne montrait pas les gestes en attente : risque de refaire un ajout.', 'Le bandeau nomme le geste ; la ligne provisoire se remontre.', 'recette, capture'],
  ['Importante', 'Fichiers sans version servis du cache pour toujours.', 'Seul un fichier versionné est servi du téléphone d\'abord.', 'lecture'],
  ['Importante', 'Le bandeau du réseau faisait sauter la page.', 'Posé au-dessus des boutons du bas, sans décaler l\'écran.', 'captures'],
  ['Importante', 'Aucun test 422 ni 409 sur les morceaux ; trois assertions de la relecture passaient à vide.', 'Tests ajoutés, assertions refaites.', 'tests'],
].map(([g, t, c, p]) => `<tr data-v="${g === 'Bloquante' ? 'bloquante' : 'importante'}"><td data-label="Gravité"><span class="pastille ${g === 'Bloquante' ? 'p-bad' : 'p-warn'}">${g}</span></td><td data-label="Trouvé">${t}</td><td data-label="Corrigé">${c}</td><td data-label="Preuve">${p}</td></tr>`).join('\n');

// ---------- Blocs facultatifs (déploiement, preuve D1, préchargement)
const blocDeploiement = deploiement
  ? `<div class="${deploiement.ok ? 'conseil' : 'alerte'}"><b>${deploiement.ok ? 'Livré sur le site de test.' : 'Déploiement à reprendre.'}</b> ${deploiement.texte}</div>
<div class="paire">
<figure><img src="${IMG}/test-corbeille.jpg" alt="Site de test : ligne supprimée, Annuler" loading="lazy"><figcaption>Site de test, offre de démo : la ligne part au toucher, « Annuler » tout de suite, total à jour (CHF 2’912.20).</figcaption></figure>
<figure><img src="${IMG}/test-annuler.jpg" alt="Site de test : ligne remise" loading="lazy"><figcaption>« Annuler » : la ligne revient à sa place, total revenu à CHF 3’106.80, sans rechargement.</figcaption></figure>
</div>`
  : '<div class="alerte"><b>Déploiement sur le site de test : pas encore fait.</b> Il part après ce comparatif, s\'il ne montre pas de gros problème.</div>';
const blocD1 = preuveD1
  ? `<div class="conseil"><b>Preuve faite au banc, ${esc(preuveD1.quand || '')}.</b> ${preuveD1.texte}</div>`
  : '<p class="muted">Preuve au banc à venir.</p>';
const blocPre = preuvePre ? `<div class="conseil"><b>Vérifié au banc.</b> ${preuvePre.texte}</div>` : '';

// ---------- Critères de succès de la SPEC, un par un
const corbeille = lireJson('mesure-corbeille.json', null);
const sansJs = lireJson('recette-sans-js.json', []);
const sj = (cle) => (sansJs.find((c) => c.cle.startsWith(cle)) || {}).valeur;
const c = (id) => ap(id, 'chantier') || {};
const geste = (nom, id) => `${nom} ${ms(c(id).vu)}, ${c(id).requetes} req., ${ko(c(id).octets)}`;
const mediane = (v) => { const t = [...v].sort((x, y) => x - y); return t[Math.floor((t.length - 1) / 2)]; };
const CRITERES = [
  ['« C\'est juste », corbeille, « Annuler », prix dicté, option : écran changé en moins de 100 ms, une requête, moins de 3 Ko, l\'écran ne saute pas',
    `${geste('« C\'est juste »', 'offre-juste')} ; corbeille ${corbeille ? mediane(corbeille.chantier) + ' ms dans la page, du clic au masquage' : ms(c('offre-corbeille').vu)} (${ms(c('offre-corbeille').vu)} au banc, défilement de Playwright jusqu'à la ligne compris), ${ko(c('offre-corbeille').octets)} ; ${geste('« Annuler »', 'offre-annuler')} ; ${geste('option', 'offre-option')} ; ${geste('prix dicté', 'offre-prix-dicte')}.`,
    'en partie', '« Annuler » et l\'option reçoivent 4,5 Ko (la liste ou la ligne avec ses variantes) ; le prix dicté attend le serveur (écart à D2, à trancher plus bas).'],
  ['Ouvrir une offre : aucun fichier statique redemandé, moins de 15 Ko', `${c('liste-ouvrir-offre').requetes} requête, ${ko(c('liste-ouvrir-offre').octets)}.`, 'tenu', ''],
  ['« + Ligne » sans requête ; trois produits puis « Terminé » : trois requêtes au plus, aucun rechargement', `ouverture : ${c('ajout-ouvrir').requetes} requête ; un produit : ${c('ajout-produit').requetes} envoi de ${ko(c('ajout-produit').octets)} ; « Terminé » : ${c('ajout-termine').requetes} requête. Recette : trois produits, trois envois.`, 'tenu', ''],
  ['Sans réseau, offre ouverte : aucune page d\'erreur ; chaque geste gardé, appliqué une seule fois au retour, même rejoué', 'recette par vraie coupure (trois gestes, une seule fois en base, rien en double après rechargement) ; tests de la clé d\'envoi (rejeu, réservation abandonnée).', 'tenu', ''],
  ['Deux gestes touchés vite, réseau lent : chacun garde son « Annuler »', 'deux corbeilles, deux « Annuler » (capture plus haut).', 'tenu', ''],
  ['Session perdue pendant un envoi : l\'écran le dit, le geste attend, rien n\'est perdu', session ? `« ${esc(session.bandeau)} » ; base inchangée pendant ; reconnecté : ${esc(session.apres)}.` : 'recette hors réseau.', 'tenu', ''],
  ['Sans JavaScript, chaque formulaire marche comme aujourd\'hui', `navigateur au JavaScript coupé : « C'est juste » ${sj('« C\'est juste » : en base') === '0' ? 'enregistré' : 'à revoir'}, quantité ${sj('quantité 27') === '27000' ? 'enregistrée' : 'à revoir'}, corbeille ${sj('corbeille : supprimée') === '1' ? 'faite' : 'à revoir'} avec son « Annuler » ; page entière, comme avant.`, 'tenu', 'Défaut ancien, inchangé par le chantier : sans JavaScript, « Archiver » de la liste est sous la carte ; le toucher ouvre l\'offre.'],
  ['Suite de tests verte ; un test par réponse en morceaux (droits, CSRF, isolation entre artisans)', '1440 tests, 12 015 assertions, verts sur la base du déploiement ; MorceauxTest, EnvoiCleTest (autre artisan, autre compte : 409), PdfGardeTest.', 'tenu', ''],
].map(([crit, mesure, verdict, note]) => `<tr data-v="${verdict === 'tenu' ? 'tenu' : 'partie'}"><td data-label="Critère de la SPEC">${crit}</td><td data-label="Mesuré (chantier)">${mesure}${note ? `<small>${note}</small>` : ''}</td><td data-label="Verdict"><span class="pastille ${verdict === 'tenu' ? 'p-ok' : 'p-warn'}">${verdict === 'tenu' ? 'Tenu' : 'En partie'}</span></td></tr>`).join('\n');

const html = `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sans rechargement : avant, après</title>
<style>
:root { --ink: #23272D; --muted: #4A505A; --bg: #F4F1EA; --surface: #FFFFFF; --line: #DDD8CC; --accent: #23272D; --marque: #A8441F; --ok: #13553A; --ok-soft: #E1EFE6; --warn: #5A4300; --warn-soft: #FFF0B3; --bad: #8C1D18; --bad-soft: #FBE7E4;
  --mk-ink: var(--ink); --mk-bg: var(--bg); --mk-surface: var(--surface); --mk-line: var(--line); --mk-accent: var(--marque); }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { --ink: #ECEAE6; --muted: #B4B9C0; --bg: #15171A; --surface: #1E2125; --line: #33373D; --accent: #ECEAE6; --marque: #F0936E; --ok: #7DD3A8; --ok-soft: #183126; --warn: #F0B860; --warn-soft: #3A2A10; --bad: #FF9A91; --bad-soft: #3B1613; } }
:root[data-theme="dark"] { --ink: #ECEAE6; --muted: #B4B9C0; --bg: #15171A; --surface: #1E2125; --line: #33373D; --accent: #ECEAE6; --marque: #F0936E; --ok: #7DD3A8; --ok-soft: #183126; --warn: #F0B860; --warn-soft: #3A2A10; --bad: #FF9A91; --bad-soft: #3B1613; }
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
main { max-width: 1180px; margin: 0 auto; padding: 16px 16px 80px; }
h1 { font-size: 27px; line-height: 1.2; margin: 18px 0 6px; }
h2 { font-size: 21px; line-height: 1.25; margin: 30px 0 8px; scroll-margin-top: 70px; }
@media (max-width: 760px) { h2, .reponse { scroll-margin-top: 112px; } } /* sommaire sur deux rangées au téléphone */
h3 { font-size: 17px; margin: 0 0 6px; }
p { margin: 0 0 10px; }
.chapeau { color: var(--muted); max-width: 800px; }
section.point { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px 16px 12px; margin: 16px 0; }
figure { margin: 0; }
figure img { max-width: 100%; height: auto; display: block; border: 1px solid var(--line); border-radius: 8px; }
figcaption { font-size: 14px; color: var(--muted); margin: 6px 0 0; }
.paire, .trio { display: grid; gap: 14px; margin: 12px 0; }
.paire { grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
.trio { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
.paire figure img, .trio figure img, .mk-compare figure img { max-width: 340px; width: 100%; }
.chiffres { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; margin: 14px 0; }
.chiffre { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
.chiffre .gros { font-size: 25px; font-weight: 750; line-height: 1.15; font-variant-numeric: tabular-nums; display: block; }
.chiffre small { display: block; color: var(--muted); font-size: 14px; margin-top: 4px; }
.ok { color: var(--ok); }
.bad { color: var(--bad); }
.conseil { border-left: 4px solid var(--ok); background: var(--ok-soft); padding: 10px 14px; border-radius: 0 10px 10px 0; margin: 12px 0; }
.alerte { border-left: 4px solid var(--bad); background: var(--bad-soft); padding: 10px 14px; border-radius: 0 10px 10px 0; margin: 12px 0; }
.note { border-left: 4px solid var(--warn); background: var(--warn-soft); padding: 10px 14px; border-radius: 0 10px 10px 0; margin: 12px 0; color: var(--ink); }
.conseil b:first-child, .alerte b:first-child, .note b:first-child { display: block; margin-bottom: 2px; }
ul.serre { margin: 4px 0 10px; padding-left: 20px; }
ul.serre li { margin: 3px 0; }
ul.serre li.titre { list-style: none; margin-left: -20px; font-weight: 650; }
code { font: 13.5px ui-monospace, Consolas, monospace; background: var(--bg); border-radius: 4px; padding: 1px 4px; overflow-wrap: anywhere; }
.muted { color: var(--muted); }
.fleche { color: var(--muted); margin: 0 3px; }
table { border-collapse: collapse; width: 100%; font-size: 14.5px; margin: 8px 0 12px; }
th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
thead th { font-weight: 650; background: var(--bg); }
th small, td small { display: block; color: var(--muted); font-size: 13px; font-weight: 400; }
td.n b { font-variant-numeric: tabular-nums; }
.pastille { display: inline-block; font-size: 12.5px; font-weight: 700; border-radius: 999px; padding: 1px 8px; }
.p-bad { background: var(--bad-soft); color: var(--bad); }
.p-ok { background: var(--ok-soft); color: var(--ok); }
.p-warn { background: var(--warn-soft); color: var(--warn); }
.boutons, .mk-filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
.boutons button, .mk-filters button { font: inherit; font-size: 14.5px; min-height: 44px; padding: 6px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; }
.boutons button[aria-pressed="true"], .mk-filters button[aria-pressed="true"] { background: var(--accent); color: var(--surface); border-color: var(--accent); }
.boutons button:focus-visible, .mk-filters button:focus-visible { outline: 3px solid var(--marque); outline-offset: 2px; }
#detail-table .r { display: none; }
#detail-table[data-reseau="local"] .r-local, #detail-table[data-reseau="fibre"] .r-fibre, #detail-table[data-reseau="4g"] .r-4g, #detail-table[data-reseau="chantier"] .r-chantier { display: block; }
@media (max-width: 760px) {
  table.cartes thead { display: none; }
  table.cartes tr[hidden] { display: none; }
  table.cartes tr { display: block; border: 1px solid var(--line); border-radius: 10px; margin: 0 0 10px; padding: 6px 8px; background: var(--surface); }
  table.cartes td, table.cartes th { display: block; border: 0; padding: 4px 0; }
  table.cartes td::before { content: attr(data-label); display: block; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
  table.global th, table.global td { font-size: 14px; padding: 6px 4px; }
}
.defile { overflow-x: auto; }
/* En-tête collant au bureau seulement : dans un conteneur à défilement, il collait à ce conteneur et couvrait la
   première ligne. */
@media (min-width: 761px) { .defile { overflow: visible; } thead th { position: sticky; top: 52px; z-index: 1; } }
.decision { border-top: 1px dashed var(--line); margin-top: 14px; padding-top: 12px; }
.decision .q { font-weight: 650; margin: 0 0 8px; }
.decision .aide { font-size: 14px; color: var(--muted); }
.opts { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 8px; }
.opts button { font: inherit; font-size: 15px; min-height: 44px; padding: 8px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; text-align: left; }
.opts button[aria-pressed="true"] { background: var(--accent); border-color: var(--accent); color: var(--surface); font-weight: 650; }
.opts button:focus-visible, textarea:focus-visible, .copier:focus-visible { outline: 3px solid var(--marque); outline-offset: 2px; }
textarea { width: 100%; min-height: 60px; font: inherit; font-size: 15px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface); color: var(--ink); resize: vertical; }
.recap textarea { min-height: 150px; font-family: ui-monospace, Consolas, monospace; font-size: 13.5px; }
.copier { font: inherit; font-weight: 650; min-height: 48px; padding: 10px 18px; border-radius: 10px; border: 0; background: var(--accent); color: var(--surface); cursor: pointer; }
.copie-ok { margin-left: 10px; color: var(--ok); font-weight: 600; }
.reponse { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; margin: 12px 0; }
.reponse h3 { margin-bottom: 8px; }
.reponse ol { margin: 6px 0 10px; padding-left: 22px; }
.reponse li { margin: 4px 0; }
</style>
</head>
<body>
<nav id="mk-toc"></nav>
<main>
<h1>Sans rechargement : avant, après</h1>
<p class="chapeau">Chantier « sans rechargement », lots 1 à 5 et revue, mesurés le 23 novembre 2026. Les mêmes 47 gestes que l'audit, les mêmes données inventées, le même téléphone simulé (390 × 844), quatre réseaux. « Avant » : le site de test d'aujourd'hui. « Après » : la copie du chantier, sur un téléphone qui a déjà ouvert l'application une fois (au tout premier passage, tout se télécharge une fois, comme aujourd'hui). « À l'écran » : le moment où l'artisan voit le résultat ; « fini » : quand le serveur a confirmé.</p>

<h2 id="bref" data-toc="Bref">En bref</h2>
${blocDeploiement}
<div class="chiffres">
<div class="chiffre"><span class="gros">${ms(tc && tc.vu[0])} ${fleche} <span class="ok">${ms(tc && tc.vu[1])}</span></span><small>temps médian avant de voir le résultat d'un geste, réseau de chantier, 47 gestes. Gestes très fréquents (${tf ? tf.gestes : '?'}) : ${ms(tf && tf.vu[0])} → ${ms(tf && tf.vu[1])}.</small></div>
<div class="chiffre"><span class="gros">${tc ? tc.recharges[0] : '?'} ${fleche} <span class="ok">${tc ? tc.recharges[1] : '?'}</span> <span class="muted">rechargements</span></span><small>Les ${tc ? tc.recharges[1] : '?'} qui restent changent d'écran exprès (ouvrir une offre, un onglet, l'aperçu du PDF, la facture créée). Seule la page voyage.</small></div>
<div class="chiffre"><span class="gros">${ko(tc && tc.octets[0])} ${fleche} <span class="ok">${ko(tc && tc.octets[1])}</span></span><small>téléchargés pour les 47 gestes, une fois chacun : style, scripts, polices et images restent dans le téléphone tant qu'ils ne changent pas.</small></div>
<div class="chiffre"><span class="gros"><span class="bad">Dinosaure</span> ${fleche} <span class="ok">gestes gardés</span></span><small>Sans réseau, avant : la page d'erreur de Chrome, le geste perdu. Après : l'écran change, le changement attend dans le téléphone et part une seule fois au retour du réseau.</small></div>
</div>
<div class="defile"><table class="global">
<thead><tr><th>Réseau simulé</th><th>À l'écran (médiane)</th><th>Fini (médiane)</th><th>Téléchargé, 47 gestes</th><th>Rechargements</th></tr></thead>
<tbody>
${tableauGlobal}
</tbody></table></div>
<div class="note"><b>Deux erreurs trouvées pendant ce comparatif, corrigées avant ces chiffres.</b>
<ul class="serre">
<li><b>Dans la mesure.</b> La première campagne « après » bridait le réseau de la page, pas celui du service worker : chaque page qu'il allait chercher passait à la vitesse locale (ouvrir une offre « en 166 ms sur le chantier »). Le banc bride maintenant les deux, et chaque mesure note le nombre de service workers bridés. Ouvrir une offre sur le chantier prend ${ms(ap('liste-ouvrir-offre', 'chantier') && ap('liste-ouvrir-offre', 'chantier').vu)}.</li>
<li><b>Dans le code (lot 4).</b> Une fois le banc juste, deux écrans sont sortis plus lents qu'avant sur le chantier : le catalogue (${ms(av('plus-catalogue', 'chantier') && av('plus-catalogue', 'chantier').vu)} avant, ${ms(vuAvantCorrectif('plus-catalogue', 'chantier'))} après) et « Facturer » (${ms(av('facturer-ouvrir', 'chantier') && av('facturer-ouvrir', 'chantier').vu)} avant, ${ms(vuAvantCorrectif('facturer-ouvrir', 'chantier'))} après). Le service worker gardait les fichiers d'une page, un par un, avant de la rendre. Il la rend maintenant dès qu'elle arrive : ${ms(ap('plus-catalogue', 'chantier') && ap('plus-catalogue', 'chantier').vu)} et ${ms(ap('facturer-ouvrir', 'chantier') && ap('facturer-ouvrir', 'chantier').vu)}. Toutes les mesures ont été refaites après ce correctif.</li>
</ul></div>

<h2 id="criteres" data-toc="Critères">Les critères de la SPEC, un par un</h2>
<p>Ceux que tu as validés avant le chantier, mesurés sur le réseau de chantier.</p>
<div class="defile"><table class="cartes"><thead><tr><th>Critère de la SPEC</th><th>Mesuré (chantier)</th><th>Verdict</th></tr></thead><tbody>
${CRITERES}
</tbody></table></div>

<h2 id="gestes" data-toc="Gestes clés">Les gestes qui comptent</h2>

<section class="point">
<h3>« C'est juste » sur une ligne à vérifier</h3>
${chiffresDe('offre-juste')}
<div class="trio">
${figure(a('demo-avant'), 'Avant : la ligne à vérifier, au moment du toucher', 'Avant, au toucher : la ligne « Peinture des murs » à vérifier.')}
${figure(a('demo-apres-rechargement'), 'Avant : la page rechargée a sauté', `Avant, ${ms(av('offre-juste', 'chantier') && av('offre-juste', 'chantier').vu)} plus tard sur le chantier : la page rechargée a sauté, la ligne n'est plus sous le doigt.`)}
${figure(b('recette-offre-juste'), 'Après : l\'étiquette est partie, l\'écran n\'a pas bougé', `Après : l'étiquette part au toucher (${ms(ap('offre-juste', 'chantier') && ap('offre-juste', 'chantier').vu)}), rien ne bouge ; le serveur confirme en fond.`)}
</div>
</section>

<section class="point">
<h3>Deux corbeilles touchées vite, réseau lent</h3>
${chiffresDe('offre-corbeille', 'Une corbeille :')}
${corbeille ? `<p class="muted">Mesurée dans la page, du clic au masquage de la ligne : ${mediane(corbeille.chantier)} ms sur le chantier (cinq passages : ${corbeille.chantier.join(', ')}). Le chiffre du banc compte en plus le défilement de Playwright jusqu'à la ligne.</p>` : ''}
<div class="mk-compare">
${figure(a('lent-deux-corbeilles'), 'Avant : deux lignes supprimées, un seul Annuler', 'Avant : deux lignes supprimées en base, un seul « Annuler » à l\'écran. La première ne pouvait plus être remise.')}
${figure(b('recette-offre-deux-corbeilles'), 'Après : deux lignes supprimées, deux Annuler', 'Après : chaque ligne a son « Annuler ». Au-dessus, « Ligne remise » : un « Annuler » touché juste avant.')}
</div>
</section>

<section class="point">
<h3>Ajouter un produit : « + Ligne », un produit, « Terminé »</h3>
<ul class="serre">
<li><b>Chantier</b> : à l'écran ${passe(somme(AJOUT, 'chantier', 'avant', 'vu'), somme(AJOUT, 'chantier', 'apres', 'vu'))} pour les trois touchers ; ${ko(somme(AJOUT, 'chantier', 'avant', 'octets'))} → ${ko(somme(AJOUT, 'chantier', 'apres', 'octets'))}. L'envoi du produit se confirme en fond (${ms(ap('ajout-produit', 'chantier') && ap('ajout-produit', 'chantier').tout)}), sans bloquer le produit suivant.</li>
<li><b>4G</b> : ${passe(somme(AJOUT, '4g', 'avant', 'vu'), somme(AJOUT, '4g', 'apres', 'vu'))} ; ${ko(somme(AJOUT, '4g', 'avant', 'octets'))} → ${ko(somme(AJOUT, '4g', 'apres', 'octets'))}.</li>
</ul>
<div class="mk-compare">
${figure(a('avant-ajout'), 'Avant : la fenêtre Ajouter', `Avant : la même fenêtre ; chaque toucher la refaisait au serveur (${ms(av('ajout-produit', 'chantier') && av('ajout-produit', 'chantier').vu)} sur le chantier) et « Terminé » rechargeait l'offre.`)}
${figure(b('recette-ajout-produit-3'), 'Après : produit ajouté, la fenêtre reste', 'Après : « Conseil couleur » ajoutée au toucher, la fenêtre reste ouverte pour le suivant.')}
</div>
</section>

<section class="point">
<h3>Quantité ou prix tapés</h3>
${chiffresDe('offre-quantite', 'Quantité :')}
<p>Avant, le montant attendait la réponse du serveur (« Enregistrement… »). Après, le montant de la ligne et le total changent à la frappe, marqués provisoires, puis le serveur les confirme. Sans réseau, ils restent provisoires et le changement attend (voir plus bas).</p>
</section>

<section class="point">
<h3>La liste : archiver, puis « Annuler »</h3>
${chiffresDe('liste-archiver', 'Archiver :')}
${chiffresDe('liste-restaurer', 'Remettre une offre des archives :')}
<div class="paire">
${figure(b('recette-liste-archiver'), 'Après : la carte archivée part, Annuler', 'Après : la carte part au toucher, « Annuler » tout de suite.')}
${figure(b('recette-liste-annuler'), 'Après : Annuler remet la carte à sa place', 'Après « Annuler » : l\'offre revient à sa place dans le tri.')}
</div>
</section>

<section class="point">
<h3>Relecture</h3>
${chiffresDe('relire-juste', '« C\'est juste » dans la relecture :')}
${chiffresDe('relire-ouvrir', 'Ouvrir la relecture :')}
<div class="paire">${figure(b('recette-relire-juste'), 'Après : ligne suivante au toucher', 'Après : la ligne suivante paraît au toucher, « Encore 4 à relire ». Le bouton reste retenu 700 ms : deux touchers rapides ne valident plus une ligne pas lue.')}</div>
</section>

<section class="point">
<h3>Changer d'écran</h3>
<p>Ces gestes changent de page exprès. Le gain vient du lot 1 : la page seule voyage, le reste est dans le téléphone.</p>
${chiffresDe('liste-ouvrir-offre', 'Ouvrir une offre (33 lignes) :')}
${chiffresDe('onglet-clients', 'Onglet « Clients » :')}
${chiffresDe('plus-catalogue', 'Ouvrir le catalogue :')}
</section>

<section class="point">
<h3>PDF : générer, rouvrir, envoyer</h3>
${chiffresDe('generer', 'Offre modifiée juste avant, PDF refait par le serveur :')}
<ul class="serre"><li class="titre">Offre inchangée, PDF déjà vu :</li>${['chantier', '4g'].map((p) => { const l = L('generer', p); return l && l.apres_pdf_garde ? `<li><b>${NOMS[p]}</b> : ${ms(l.apres_pdf_garde.vu)}, ${ko(l.apres_pdf_garde.octets)} (le serveur répond « rien n'a changé »).</li>` : ''; }).join('')}</ul>
<p>« Envoyer » partage le PDF déjà chargé : une requête pour noter l'envoi, aucun rechargement.</p>
<div class="paire">${figure(b('recette-pdf-envoyer'), 'Après : Offre 2 envoyée', 'Après : « Offre 2 envoyée », l\'aperçu reste à l\'écran.')}</div>
</section>

<section class="point">
<h3>Facturer</h3>
${chiffresDe('facturer-ouvrir', 'Ouvrir « Facturer » :')}
${chiffresDe('facturer-creer', 'Créer la facture :')}
<div class="paire">${figure(b('revue-facturer-case'), 'Après : choix du client dans Facturer, on reste sur l\'offre', 'Après : une case du choix du client se coche dans la fenêtre, on reste sur l\'offre.')}</div>
</section>

<h2 id="hors-reseau" data-toc="Sans réseau">Sans réseau</h2>
<p>Recette par une vraie coupure : le serveur du banc arrêté pendant que le téléphone se croit connecté (le pire cas réel). Le mode « hors ligne » de Chrome laissait passer le service worker, il ne prouvait rien.</p>
<section class="point">
<h3>Un geste sans réseau</h3>
<div class="mk-compare">
${figure(a('hors-ligne-juste'), 'Avant : page d\'erreur de Chrome', 'Avant : « C\'est juste » sans réseau, la page d\'erreur de Chrome. L\'offre quitte l\'écran.')}
${figure(b('hors-ligne-apres-trois-gestes'), 'Après : bandeau, montants provisoires', 'Après : l\'écran a changé, le montant est provisoire (en gris), le bandeau compte ce qui attend.')}
</div>
</section>
<section class="point">
<h3>Ouvrir une autre offre sans réseau</h3>
<div class="mk-compare">
${figure(a('hors-ligne-ouvrir'), 'Avant : page d\'erreur de Chrome', 'Avant : la page d\'erreur de Chrome.')}
${figure(b('hors-ligne-apres-autre-offre'), 'Après : l\'offre gardée dans le téléphone', 'Après : l\'offre 2, gardée d\'avance, s\'ouvre ; le bandeau rappelle les trois changements en attente.')}
</div>
</section>
${session ? `<section class="point">
<h3>Session perdue pendant un geste</h3>
<div class="mk-compare">
${figure(a('session-expiree-quantite'), 'Avant : quantité tapée, rien enregistré, rien dit', 'Avant : 33 tapé, le montant reste à 5’292.00 ; rien n\'est enregistré et l\'écran ne le dit pas.')}
${figure(b('session-apres'), 'Après : bandeau Session expirée', `Après : « ${esc(session.bandeau || '')} ». Reconnecté, la valeur part : ${esc(session.apres || '')}.`)}
</div>
</section>` : ''}
<section class="point">
<h3>Le réseau revient</h3>
<div class="paire">${figure(b('hors-ligne-apres-retour-reseau'), 'Après : les changements sont partis', 'Les trois changements sont partis, une seule fois ; le total vient du serveur.')}</div>
<div class="defile"><table class="cartes"><tbody>
${constatsHors}
</tbody></table></div>
<p class="muted">Total provisoire et total du serveur peuvent différer de quelques centimes (arrondi au 5 centimes fait par le serveur) : constaté 23’529.55 provisoire, 23’529.60 au retour. Le provisoire est marqué comme tel.</p>
</section>

<section class="reponse" id="question-d5">
<h3>Ta question (D5) : comment il sait quoi garder et quoi renvoyer</h3>
<ol>
<li><b>Ce qu'il garde pour te le montrer sans réseau.</b> Chaque page que tu ouvres, sous ton compte, 14 jours au plus. En plus, quand tu ouvres la liste (au plus une fois par heure, écran calme) : la liste, le catalogue, les clients et les offres modifiées depuis 30 jours, une page à la fois (D6). Une offre déjà gardée se redemande seulement si elle a changé. Pas sur un réseau 2G ni en mode économie de données.</li>
<li><b>Ce qu'il garde pour le renvoyer.</b> Chaque changement fait dans une offre (une quantité, « C'est juste », une corbeille, un produit ajouté) part dans une file rangée dans le téléphone, avec une clé unique. La file part dans l'ordre. Sans réseau, elle attend, même application fermée ; elle repart seule au retour du réseau, ou à la prochaine ouverture de l'application. Un changement vieux de plus d'une semaine ne part plus, et l'écran le dit. Le serveur reconnaît la clé : un changement renvoyé deux fois n'est fait qu'une fois.</li>
<li><b>Ce qui ne passe pas par la file.</b> Créer une facture (sans réseau, rien n'est fait et la fenêtre le dit) ; les fiches du client et des réglages (sans réseau, l'écran dit « Pas enregistré ») ; la dictée, qui a son propre abri.</li>
<li><b>Se connecter sans réseau : impossible, c'est normal.</b> Le serveur doit vérifier le mot de passe. Connecté une fois, si le réseau tombe ensuite : les offres gardées s'ouvrent et se modifient, les changements attendent. C'est exactement ce que tu as constaté.</li>
<li><b>À la déconnexion</b>, tout ce que le téléphone gardait pour ton compte part (pages, PDF, changements en attente, après confirmation s'il en reste). Les dictées à l'abri restent : elles partent quand leur offre est prête.</li>
</ol>
${decision('Mention dans la politique de confidentialité', 'Le téléphone garde 14 jours au plus des pages avec les noms, adresses et prix des clients. On l\'écrit dans la politique de confidentialité ?', [['oui', 'Valider : j\'ajoute une phrase'], ['non', 'Pas maintenant']], 'La revue de sécurité l\'a relevé (D-I3) ; c\'est ta décision.')}
${decision('Durée de garde dans le téléphone', 'Combien de temps le téléphone garde-t-il les pages et les PDF ?', [['A', 'A. 14 jours (actuel)'], ['B', 'B. 7 jours'], ['C', 'C. 30 jours']], 'Plus court : moins de données de clients dans un téléphone perdu ; plus long : des offres anciennes lisibles sans réseau.')}
</section>

<section class="reponse" id="question-d1">
<h3>Ta remarque (D1) : un style changé doit arriver sans histoire de cache</h3>
<p>Chaque adresse de fichier porte l'empreinte de son contenu (<code>app.css?v=…</code>, 12 caractères tirés du fichier). Tant qu'il ne change pas, le téléphone le garde un an sans rien demander. Tu changes une ligne du style : l'empreinte change, la page demande une autre adresse, le téléphone prend le nouveau fichier à la page suivante. Rien à vider, rien à numéroter à la main. Le service worker lui-même (<code>sw.js</code>) n'est jamais gardé : une nouvelle version arrive à l'ouverture suivante.</p>
${blocD1}
</section>

<section class="reponse" id="question-d4">
<h3>Ta remarque (D4) : le préchargement ne doit pas gêner</h3>
<p>Il part au plus une fois par heure, quand l'écran est calme depuis quelques secondes et qu'aucun changement n'attend ; une page à la fois. Pendant qu'il tourne, toute requête de l'écran le met en pause : il reprend après 3 secondes de calme.</p>
${blocPre}
</section>

<h2 id="revue" data-toc="Revue">Revue : ce qui a été trouvé et corrigé</h2>
<p>Quatre relecteurs indépendants, une lentille chacun : serveur, navigateur, régressions, sécurité. 4 bloquantes et 19 importantes, corrigées ; une seule en partie (la mention dans la politique de confidentialité, à toi). Le tableau reprend les 4 bloquantes et 12 importantes ; toutes, et les écartées avec leur raison, sont dans <code>REVUE.md</code>.</p>
<div class="mk-filters" data-target="#revue-corps"><button type="button" data-v="all" aria-pressed="true">Toutes</button><button type="button" data-v="bloquante">Bloquantes</button><button type="button" data-v="importante">Importantes</button></div>
<div class="defile"><table class="cartes"><thead><tr><th>Gravité</th><th>Trouvé</th><th>Corrigé</th><th>Preuve</th></tr></thead><tbody id="revue-corps">
${REVUE}
</tbody></table></div>
<div class="paire">
${figure(b('revue-variante'), 'Après correction : la variante une seule fois', 'Corbeille d\'une ligne à variantes : la variante paraît une fois, dans les options.')}
${figure(b('revue-facturer-hors-reseau'), 'Après correction : Créer la facture sans réseau ne fait rien et le dit', '« Créer la facture » sans réseau : rien n\'est fait, la fenêtre le dit, aucune facture en base.')}
</div>
<p><b>Trouvé pendant ce comparatif</b>, en plus des deux erreurs du début : le bandeau disait « 1 changement attend, ils partiront » (accord corrigé) ; la mesure de « Générer » comparait un PDF déjà fait à un PDF refait (remesurée, offre modifiée juste avant) ; le préchargement ne cédait pas la place aux gestes une fois lancé (D4, corrigé : il se met en pause).</p>

<h2 id="detail" data-toc="Détail">Le détail, geste par geste</h2>
<p>Choisis le réseau, puis filtre par écran. « À l'écran » en gras, « fini » quand le serveur a confirmé. « Pareil » : moins de 50 ms d'écart, ce qui sépare deux passages du même geste au banc. Sur téléphone, chaque geste devient une carte.</p>
<div class="boutons" role="group" aria-label="Réseau">${PROFILS.map((p) => `<button type="button" data-reseau-choix="${p}" aria-pressed="${p === 'chantier' ? 'true' : 'false'}">${NOMS[p]}</button>`).join('')}</div>
<p class="muted" id="reseau-texte">${RESEAUX.chantier}</p>
<div class="mk-filters" data-target="#detail-corps"><button type="button" data-v="all" aria-pressed="true">Tous (${ids.length})</button>${GROUPES.map(([g, n]) => `<button type="button" data-v="${g}">${n} (${compteGroupe(g)})</button>`).join('')}</div>
<div class="defile"><table class="cartes" id="detail-table" data-reseau="chantier">
<thead><tr><th>Geste</th><th>Avant</th><th>Après</th><th>Gain</th><th>Maintenant</th></tr></thead>
<tbody id="detail-corps">
${lignesDetail}
</tbody></table></div>

<h2 id="limites" data-toc="Limites">Ce qui reste, et les limites</h2>
<ul class="serre">
<li><b>Les fenêtres de l'offre attendent le serveur</b> (client, représentant, rabais, conditions, rattacher, créer un produit, objet, enregistrer une ligne) : sans rechargement ; l'écran attend la réponse, ${ms(ap('rabais', 'chantier') && ap('rabais', 'chantier').vu)} sur le chantier, parce que le serveur calcule ce qu'il faut montrer. « Prendre 30.00 » (prix dicté) aussi : la question suivante vient de lui. C'est un écart à D2, à trancher ci-dessous.</li>
<li><b>Les fiches du client et des réglages</b> s'enregistrent seules comme avant (${ms(ap('client-champ', 'chantier') && ap('client-champ', 'chantier').vu)} sur le chantier, attente de fin de frappe comprise) et ne passent pas par la file : sans réseau, l'écran dit « Pas enregistré ».</li>
<li><b>Premier passage</b> : la première ouverture après la livraison télécharge tout une fois, comme aujourd'hui. Les chiffres « après » valent à partir de la deuxième page. Le catalogue et « Facturer » sont mesurés à leur toute première ouverture dans le téléphone (leurs fichiers propres se téléchargent alors une fois) : les fois suivantes sont plus rapides que ce qu'affiche le tableau.</li>
<li><b>Safari (iPhone)</b> efface ce qu'un site garde s'il n'est pas ouvert pendant 7 jours. Sur iPhone, une offre gardée peut donc manquer après une semaine sans ouvrir l'application. « Envoyer » sur iPhone n'a pas été essayé (le banc tourne sous Chrome).</li>
<li><b>Le rattachement à un produit et la fiche produit</b> ne sont pas préparés d'avance ; la fenêtre « + Ligne » l'est, et se refait en fond après chaque usage.</li>
<li><b>Défaut ancien, trouvé en vérifiant le critère « sans JavaScript »</b> : sans JavaScript, le bouton « Archiver » d'une carte de la liste est recouvert par la carte ; le toucher ouvre l'offre. Le chantier n'y a rien changé (il n'a ajouté que des attributs à la carte). Presque personne ne navigue sans JavaScript : noté, pas corrigé.</li>
<li><b>Une page gardée ne dit qu'une chose à la fois</b> : quand des changements attendent, le bandeau les compte et ne dit plus de quelle heure date la copie.</li>
<li><b>Mesures</b> : réseau simulé par Chrome sur cette machine, pas un vrai téléphone sur un vrai chantier ; les fibre, 4G et chantier sont des profils, pas des relevés. Le hors réseau est prouvé par une vraie coupure du serveur.</li>
</ul>
${decision('Fenêtres de l\'offre et prix dicté', 'Les fenêtres de l\'offre et « Prendre 30.00 » attendent la réponse du serveur (environ 0,7 s sur le chantier, contre 2,6 à 4,2 s avant). On les laisse ainsi ?', [['A', 'A. Les laisser ainsi (conseillé)'], ['B', 'B. Les rendre immédiats']], 'B demande de refaire dans le téléphone des calculs du serveur (client, rabais, totaux, prix) : un chantier en soi, et deux endroits à tenir justes.')}

<section class="recap">
<h2 id="reponses" data-toc="Réponses">Tes réponses</h2>
<p>Les choix posés sous chaque bloc, rassemblés pour les copier dans la conversation.</p>
<textarea id="recap" readonly aria-label="Réponses à copier"></textarea>
<p><button type="button" class="copier" id="copier">Copier mes réponses</button><span class="copie-ok" id="copie-ok" hidden>Copié</span></p>
</section>
</main>
<script>
(function () {
  var CLE = 'comparatif-sans-rechargement';
  var etat = {};
  try { etat = JSON.parse(localStorage.getItem(CLE) || '{}') || {}; } catch (e) { etat = {}; }
  var garder = function () { try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) { /* sans stockage */ } };
  var blocs = Array.prototype.slice.call(document.querySelectorAll('.decision'));
  function recap() {
    var lignes = [];
    blocs.forEach(function (b) {
      var q = b.getAttribute('data-q');
      var r = etat[q] || {};
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

  // Réseau du tableau détaillé : un seul à la fois, retenu par le navigateur.
  var TEXTES = ${JSON.stringify(RESEAUX)};
  var table = document.getElementById('detail-table');
  var choix = Array.prototype.slice.call(document.querySelectorAll('[data-reseau-choix]'));
  function choisir(p) {
    table.setAttribute('data-reseau', p);
    choix.forEach(function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-reseau-choix') === p ? 'true' : 'false'); });
    document.getElementById('reseau-texte').textContent = TEXTES[p] || '';
    try { localStorage.setItem(CLE + '-reseau', p); } catch (e) { /* sans stockage */ }
  }
  choix.forEach(function (x) { x.addEventListener('click', function () { choisir(x.getAttribute('data-reseau-choix')); }); });
  try { var r = localStorage.getItem(CLE + '-reseau'); if (r && TEXTES[r]) choisir(r); } catch (e) { /* idem */ }
})();
</script>
</body>
</html>
`;

fs.mkdirSync(path.join(DOSSIER, IMG), { recursive: true });
fs.writeFileSync(path.join(DOSSIER, 'COMPARATIF-sans-rechargement.html'), html);
console.log('comparatif écrit :', html.length, 'caractères ;', ids.length, 'gestes ;', lignes.length, 'lignes de mesure');
