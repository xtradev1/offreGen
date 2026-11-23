// Écrit AUDIT.md à partir du modèle (marqueurs @@BREF@@, @@CONSTATS@@, @@TABLEAU@@) et des mesures. Usage : node audit.js <AUDIT.md>
'use strict';
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { gestes, temps, ko } = require('./donnees');

const fichier = process.argv[2];
const ICI = __dirname;
const lire = (f, d) => { try { return JSON.parse(fs.readFileSync(path.join(ICI, f), 'utf8')); } catch (e) { return d; } };
const hl = lire('hors-ligne.json', []);
const h = (cas) => hl.find((x) => x.cas === cas) || {};
const dictees = (() => { try { return fs.readFileSync(path.join(ICI, 'dictees.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l)); } catch (e) { return []; } })();
const g = (id) => gestes.find((x) => x.mesure === id);
const c = (id, p, v) => { const x = g(id); return x && x.chiffres && x.chiffres[p] ? x.chiffres[p][v] : null; };
const t = (ms) => temps(ms) || 'non mesuré';
const vu = (id, p) => { const x = c(id, p, 'froid'); return x ? (id === 'generer' ? x.duree : x.vu) : null; };
const req = (n) => `${n} requête${n > 1 ? 's' : ''}`;
const m2 = (milliemes) => `${Number(milliemes) / 1000} m²`;
// Une page qui change : « affiché » puis « tout chargé » ; sinon une seule durée.
const resume = (id, p) => {
  const x = c(id, p, 'froid');
  if (!x) return 'non mesuré';
  return x.fcp && id !== 'generer' ? `${t(vu(id, p))} affiché, ${t(x.duree)} tout chargé, ${req(x.requetes)}, ${ko(x.octets)}` : `${t(vu(id, p))}, ${req(x.requetes)}, ${ko(x.octets)}`;
};
const seul = (id, p) => { const x = c(id, p, 'simule'); return x ? `${t(x.duree)}, ${req(x.requetes)}, ${ko(x.octets)}` : 'non mesuré'; };
const inutiles = gestes.filter((x) => x.categorie === 'inutile');
const lourds = gestes.filter((x) => x.categorie === 'lourd');
const utiles = gestes.filter((x) => x.categorie === 'utile');
const peu = gestes.filter((x) => x.categorie === 'peu');
const bien = gestes.filter((x) => x.categorie === 'bien');
const ajout = ['ajout-ouvrir', 'ajout-produit', 'ajout-termine'];
const sommeVu = (p) => ajout.reduce((s, id) => s + (vu(id, p) || 0), 0);
const sommeKo = (p) => ajout.reduce((s, id) => s + ((c(id, p, 'froid') || {}).octets || 0), 0);
const df = dictees.find((d) => d.profil === 'fibre');
const dc = dictees.find((d) => d.profil === 'chantier');

const bref = [
  `- ${gestes.length} gestes passés en revue. ${inutiles.length} rechargent la page entière alors qu'un morceau d'écran change ; ${lourds.length} évitent déjà le rechargement mais font revenir beaucoup trop (l'offre entière, tout le catalogue) ; ${utiles.length} gardent un rechargement utile ; ${peu.length} le gardent parce qu'ils sont rares (équipe, page publique) ; ${bien.length} sont déjà bien.`,
  `- « C'est juste », sur le réseau de chantier : ${resume('offre-juste', 'chantier')} ; la même requête seule : ${seul('offre-juste', 'chantier')}. En 4G : ${t(vu('offre-juste', '4g'))} contre ${t((c('offre-juste', '4g', 'simule') || {}).duree)}. Le serveur fait l'action en ${t((c('offre-juste', 'local', 'simule') || {}).duree)} : tout le reste est le rechargement.`,
  `- Ouvrir une offre retélécharge feuille de style, script, polices et logos : ${ko((c('liste-ouvrir-offre', 'chantier', 'froid') || {}).octets)} au lieu de ${ko((c('liste-ouvrir-offre', 'chantier', 'chaud') || {}).octets)} avec ces fichiers gardés (affichée après ${t(vu('liste-ouvrir-offre', 'chantier'))} au lieu de ${t((c('liste-ouvrir-offre', 'chantier', 'chaud') || {}).vu)} sur le réseau de chantier). Cause : aucune durée de cache sur le test, et un ETag que la compression d'Apache rend inutilisable.`,
  `- Ajouter un produit (ouvrir « + Ligne », toucher un produit, « Terminé ») : ${t(sommeVu('chantier'))} d'attente cumulée et ${ko(sommeKo('chantier'))} sur le réseau de chantier, tout le catalogue étant téléchargé deux fois ; avec le catalogue gardé dans le téléphone, un envoi de ${t((c('ajout-produit', 'chantier', 'simule') || {}).duree)}.`,
  `- Sans réseau, tout geste qui recharge ouvre la page d'erreur du navigateur ; une quantité tapée affiche « ${h('hors-ligne-quantite').message || '?'} »${h('hors-ligne-quantite').apres_retour_du_reseau_8s === h('hors-ligne-quantite').avant ? ' et n\'est renvoyée que si l\'artisan quitte l\'offre une fois le réseau revenu' : ''}.`,
].join('\n');

const bandeau = (s) => (s && s.length ? s.map((x) => x.replace(/Annuler$/, ' · Annuler')).join(' ; ') : 'aucun');
const lentJuste = h('lent-juste');
const deuxCorb = h('lent-deux-corbeilles');
const deuxArch = h('lent-deux-archiver');
const session = h('session-expiree-quantite');
const constats = [
  `1. **${inutiles.length} gestes rechargent la page pour changer un morceau d'écran.** Sur l'offre : « C'est juste », la corbeille, « Annuler », le prix dicté, la ligne, l'objet, le client, le représentant, le rabais, les conditions, la pièce, la ligne hors catalogue, « Terminé » après un ajout ; ailleurs : la relecture, l'archivage et la suppression d'une offre, le tri, les catégories du catalogue, le brouillon de facture. Mesuré : « C'est juste » ${resume('offre-juste', '4g')} en 4G, ${resume('offre-juste', 'chantier')} sur le réseau de chantier ; seule, la requête prend ${seul('offre-juste', '4g')} en 4G et ${seul('offre-juste', 'chantier')} sur le chantier. Le serveur fait l'action elle-même en 10 à 30 ms en local ; rendre l'offre de 33 lignes lui en coûte encore 40.`,
  `2. **Un rechargement retélécharge les fichiers qui n'ont pas changé.** Le site de test n'envoie ni \`Cache-Control\` ni \`Expires\` ; l'ETag est suffixé « -gzip » par la compression d'Apache, si bien qu'une revalidation (\`If-None-Match\`) reçoit tout le fichier au lieu de « 304, rien n'a changé » (vérifié par curl sur \`app.css\`, \`app.js\` et une police). Sur une page rechargée : 21 Ko de feuille de style, 31 Ko de script, 84 Ko de polices, 4 Ko de logos, pour 4 à 25 Ko de page. Mesuré avec ces fichiers gardés : ouvrir une offre passe de ${ko((c('liste-ouvrir-offre', '4g', 'froid') || {}).octets)} à ${ko((c('liste-ouvrir-offre', '4g', 'chaud') || {}).octets)}, de ${t(vu('liste-ouvrir-offre', '4g'))} à ${t((c('liste-ouvrir-offre', '4g', 'chaud') || {}).vu)} en 4G et de ${t(vu('liste-ouvrir-offre', 'chantier'))} à ${t((c('liste-ouvrir-offre', 'chantier', 'chaud') || {}).vu)} sur le chantier. « Générer l'offre » et « Créer la facture » téléchargent en plus PDF.js : 515 Ko compressés (136 Ko pour la bibliothèque, 379 Ko pour son worker), à chaque fois que le cache est périmé.`,
  `3. **Des jetons CSRF gonflent les pages.** Chaque formulaire porte son jeton, et la garde en tire une version aléatoire à chaque appel : 110 jetons différents sur l'offre de 33 lignes (23,9 Ko compressés, 13,1 Ko sans eux), 277 dans la fenêtre « + Ligne » (36,7 Ko compressés, 9,6 Ko sans eux). Un jeton tiré une fois par réponse et répété partout garde la protection et se compresse presque à zéro.`,
  `4. **Les fenêtres du bas font revenir l'offre trois fois.** Client, représentant, rabais, conditions, rattachement d'une ligne : la réponse à l'envoi est l'offre entière (redirection suivie), puis la liste derrière est rafraîchie (l'offre entière encore), puis la fermeture recharge la page (l'offre et tous les fichiers). Mesuré pour le client : ${resume('client-offre', 'chantier')} sur le chantier ; seul, l'envoi prend ${seul('client-offre', 'chantier')}.`,
  `5. **Ajouter un produit retélécharge tout le catalogue.** Ouvrir « + Ligne » : ${resume('ajout-ouvrir', 'chantier')} ; toucher un produit : ${resume('ajout-produit', 'chantier')} (l'envoi, puis toute la fenêtre de nouveau) ; « Terminé » : ${resume('ajout-termine', 'chantier')} (l'offre rechargée). Soit ${t(sommeVu('chantier'))} d'attente pour un produit sur le chantier, ${t(sommeVu('4g'))} en 4G.`,
  `6. **Les gestes déjà sans rechargement font revenir l'offre entière.** Quantité, prix, option, textes partent par fetch (bien), mais la réponse suivie est l'offre complète : ${resume('offre-quantite', 'chantier')} sur le chantier pour un chiffre ; seul, l'envoi prend ${seul('offre-quantite', 'chantier')}.`,
  `7. **Catalogue et clients : toute la liste revient après un enregistrement.** Nouveau produit : ${resume('catalogue-nouveau', '4g')} en 4G (l'envoi, la réponse, puis le catalogue entier) ; seul : ${seul('catalogue-nouveau', '4g')}. Nouveau client : ${resume('client-nouveau', '4g')}.`,
  `8. **Sans réseau.** « C'est juste » : ${h('hors-ligne-juste').titre ? `la page d'erreur du navigateur (« ${(h('hors-ligne-juste').texte || '').split('\n')[0].slice(0, 60)} »)` : 'non mesuré'} ; « + Ligne » : ${h('hors-ligne-ajout').adresse ? 'la fenêtre échoue, le navigateur part sur la page d\'erreur' : 'non mesuré'} ; ouvrir une offre : ${h('hors-ligne-ouvrir').adresse ? 'page d\'erreur' : 'non mesuré'}. Une quantité tapée (24 m² passés à 27) : « ${h('hors-ligne-quantite').message || '?'} » ; en base, ${m2(h('hors-ligne-quantite').avant)} avant, ${m2(h('hors-ligne-quantite').apres_retour_du_reseau_8s)} huit secondes après le retour du réseau, ${m2(h('hors-ligne-quantite').apres_avoir_quitte_la_page)} une fois l'offre quittée. Le chiffre ne repart que si l'artisan quitte l'offre avec du réseau  ; s'il ferme l'application sans réseau, la seule relance échoue (déduit du code : elle part à la sortie de la page ; non testé).`,
  `9. **Réseau lent : rien ne bouge, on retouche.** Pendant le rechargement, rien ne dit que le toucher a été pris : seuls les formulaires à identifiant changent de bouton pendant l'envoi (\`envoisDeFormulaires\`, \`app.js\`), et « C'est juste », la corbeille, l'archivage n'en ont pas. Sur le chantier, la page rechargée arrive après ${t(lentJuste.rendu_ms)}. **Défaut trouvé :** deux corbeilles touchées à 0,7 s d'écart retirent les deux lignes (lignes ${deuxCorb.lignes_retirees || '?'} en base, deux réponses 303), mais la page qui arrive n'offre qu'un « Annuler », celui de la seconde (${bandeau(deuxCorb.snack)}). La première ne peut plus être remise. Cause probable, non vérifiée : son message est parti avec la page abandonnée en route. Une corbeille seule affiche bien le sien (${bandeau(h('lent-une-corbeille').snack)}). « Archiver » retouché 0,6 s plus tard : un seul archivage, le second toucher ne trouve plus le bouton (la carte s'est refermée) ; rien à signaler.`,
  `10. **Session perdue : un chiffre tapé disparaît sans rien dire.** ${session.message !== undefined ? `Sans session, une quantité tapée (24 m² passés à 33) laisse l'indicateur sur « ${session.message} » pour de bon ; le chiffre reste dans le champ, le total de la ligne ne suit pas, et la base garde ${m2(session.apres)}. Cause : la réponse est la page de connexion, qu'\`app.js\` lit comme une offre sans rien y trouver ni signaler. Défaut d'aujourd'hui, indépendant du chantier, que l'envoi sans rechargement doit traiter.` : 'Non mesuré.'}`,
  `11. **Le PDF.** « Générer l'offre » : ${t(vu('generer', 'local'))} en local, ${t(vu('generer', '4g'))} en 4G, ${t(vu('generer', 'chantier'))} sur le chantier jusqu'au PDF dessiné (PDF.js retéléchargé, PDF refabriqué par le serveur à chaque ouverture : 0,3 s et 33 Ko pour 6 lignes, 0,6 s et 43 Ko pour 33 lignes). « Envoyer » redemande le même PDF (réponse \`no-store\`, refabriquée) avant d'ouvrir le partage, puis recharge la page pour noter l'envoi.`,
  `12. **La dictée est déjà sans rechargement.** Dictée de peintre de 28 s, vraie transcription et vraie extraction. ${df ? `Fibre : envoi ${t(df.envoi)}, offre prête après ${t(df.offrePrete)} ; ${df.suivis} questions « où en est-on ? » (${ko(df.suiviOctets)}).` : ''} ${dc ? `Chantier : envoi ${t(dc.envoi)}, offre prête après ${t(dc.offrePrete)} ; ${dc.suivis} questions.` : ''} Le temps vient de la file de transcription d'Infomaniak, qui varie d'une dictée à l'autre.`,
  `13. **Ce qui est déjà bien.** Filtres du catalogue et des clients (aucune requête), réglages et fiche client enregistrés seuls (réponse JSON), adresses, « Vos prix », import du catalogue, aperçu de la facture, document à côté au PC, retour arrière (${t(vu('liste-retour', 'chantier'))}, le navigateur garde la page).`,
].join('\n');

const tableau = execSync('node tableau-md.js', { cwd: ICI }).toString().trim();
let texte = fs.readFileSync(path.join(ICI, 'AUDIT.modele.md'), 'utf8');
texte = texte.replace('@@BREF@@', bref).replace('@@CONSTATS@@', constats).replace('@@TABLEAU@@', tableau);
fs.writeFileSync(fichier, texte);
console.log('AUDIT.md rempli :', texte.length, 'caractères');
