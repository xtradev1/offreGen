// Import du catalogue depuis d'anciennes offres (chantier 2026-11-20_import-catalogue, maquette validée le 2026-11-20).
// Page de choix : le texte de chaque PDF est sorti ici, dans le navigateur ; le fichier ne part jamais. Un PDF sans
// texte (photo, scan) est refusé sur place. « Lire » crée l'import, envoie chaque offre (le serveur la lit par le
// modèle), puis demande le regroupement. Page de relecture : le bouton dit combien de prix restent à choisir.
import * as pdfjs from '/js/vendor/pdfjs-6.5.438/pdf.min.js';
import { lignesDuPdf } from '/js/texte-pdf.js';

const TAILLE_MAX = 10 * 1024 * 1024;
const LIGNES_MAX = 1200; // les limites de LecteurApi
const CARACTERES_MAX = 80000;
const TEXTE_MIN = 40; // en dessous, le PDF n'a pas de texte : une photo ou un scan

const page = document.querySelector('[data-import-catalogue]');
if (page && page.querySelector('[data-fichiers]')) {
  pdfjs.GlobalWorkerOptions.workerSrc = page.dataset.worker;
  const max = Number(page.dataset.offresMax);
  const entree = page.querySelector('[data-fichiers]');
  const depot = page.querySelector('[data-depot]');
  const liste = page.querySelector('[data-liste]');
  const etat = page.querySelector('[data-etat]');
  const erreur = page.querySelector('[data-erreur]');
  const bouton = document.querySelector('[data-lire]');
  const fichiers = [];
  let regroupement = null; // null, 'attente', 'cours', 'fait'
  let enCours = false;
  let url = null;

  const dire = (texte) => { erreur.textContent = texte; erreur.hidden = !texte; };
  const pluriel = (n, mot) => n + ' ' + mot + (n > 1 ? 's' : '');

  const badge = (classe, texte, tourne) => {
    const b = document.createElement('span');
    b.className = 'ic-badge' + (classe ? ' ' + classe : '');
    if (tourne) {
      const t = document.createElement('i');
      t.className = 'ic-tourne';
      b.append(t);
    }
    b.append(texte);
    return b;
  };

  const ligne = (titre, detail, droite, detailFaux) => {
    const li = document.createElement('li');
    const texte = document.createElement('span');
    const b = document.createElement('b');
    b.textContent = titre;
    const s = document.createElement('small');
    s.textContent = detail;
    if (detailFaux) s.className = 'faux';
    texte.append(b, s);
    li.append(texte, droite);
    return li;
  };

  const afficher = () => {
    const elements = fichiers.map((f, i) => {
      if (f.etat === 'pret' && !enCours && !url) {
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'ic-retirer';
        x.setAttribute('aria-label', 'Retirer ' + f.nom);
        x.textContent = '×';
        x.addEventListener('click', () => { fichiers.splice(i, 1); afficher(); });
        return ligne(f.nom, f.detail, x);
      }
      const b = {
        ouverture: badge('', 'Ouverture', true), pret: badge('', 'Prêt'), attente: badge('', 'En attente'),
        lecture: badge('', 'Lecture', true), lu: badge('ok', f.badge || 'Lu'), refuse: badge('ko', 'Refusé'),
      }[f.etat];
      return ligne(f.nom, f.detail, b, f.etat === 'refuse');
    });
    if (regroupement) {
      const b = regroupement === 'fait' ? badge('ok', 'Fait') : regroupement === 'cours' ? badge('', 'En cours', true) : badge('', 'En attente');
      elements.push(ligne('Regroupement des produits', 'Les lignes qui disent la même chose sont réunies.', b));
    }
    liste.replaceChildren(...elements);
    liste.hidden = elements.length === 0;
    depot.hidden = enCours || url !== null;
    const prets = fichiers.filter((f) => f.etat === 'pret').length;
    if (url) {
      bouton.textContent = 'Voir les produits trouvés';
      bouton.disabled = false;
    } else if (enCours) {
      bouton.textContent = 'Lecture en cours';
      bouton.disabled = true;
    } else {
      bouton.textContent = prets ? 'Lire ' + (prets > 1 ? 'mes ' + prets + ' offres' : 'mon offre') : 'Choisissez des PDF';
      bouton.disabled = prets === 0;
    }
  };

  const ajouter = async (choisis) => {
    dire('');
    for (const fichier of choisis) {
      if (fichiers.filter((f) => f.etat !== 'refuse').length >= max) {
        dire(max + ' offres au plus par import.');
        break;
      }
      const f = { nom: fichier.name, etat: 'ouverture', detail: '' };
      fichiers.push(f);
      afficher();
      if (fichier.type !== 'application/pdf' && !/\.pdf$/i.test(fichier.name)) {
        Object.assign(f, { etat: 'refuse', detail: 'Ce fichier n’est pas un PDF.' });
      } else if (fichier.size > TAILLE_MAX) {
        Object.assign(f, { etat: 'refuse', detail: 'Plus de 10 Mo.' });
      } else {
        try {
          const t = await lignesDuPdf(pdfjs, { data: new Uint8Array(await fichier.arrayBuffer()) });
          if (t.caracteres < TEXTE_MIN) {
            Object.assign(f, { etat: 'refuse', detail: 'Photo ou scan, sans texte à lire.' });
          } else if (t.lignes.length > LIGNES_MAX || t.caracteres > CARACTERES_MAX) {
            Object.assign(f, { etat: 'refuse', detail: 'Offre trop longue pour être lue.' });
          } else {
            Object.assign(f, { etat: 'pret', detail: pluriel(t.pages, 'page'), lignes: t.lignes });
          }
        } catch {
          Object.assign(f, { etat: 'refuse', detail: 'Ce PDF ne s’ouvre pas.' });
        }
      }
      afficher();
    }
  };

  const envoyer = async (chemin, champs) => {
    const corps = new FormData();
    corps.append('_csrf', page.dataset.csrf);
    for (const [cle, valeur] of Object.entries(champs)) corps.append(cle, valeur);
    const r = await fetch(chemin, { method: 'POST', body: corps, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    let json = {};
    try { json = await r.json(); } catch { /* page d'erreur en HTML */ }
    if (!r.ok) throw new Error(json.erreur || 'Le serveur a refusé la demande (' + r.status + ').');
    return json;
  };

  entree.addEventListener('change', async () => {
    const choisis = [...entree.files];
    entree.value = '';
    await ajouter(choisis);
  });
  depot.addEventListener('dragover', (e) => { e.preventDefault(); depot.classList.add('survol'); });
  depot.addEventListener('dragleave', () => depot.classList.remove('survol'));
  depot.addEventListener('drop', async (e) => {
    e.preventDefault();
    depot.classList.remove('survol');
    await ajouter([...e.dataTransfer.files]);
  });

  bouton.addEventListener('click', async () => {
    if (url) {
      location.assign(url);
      return;
    }
    const prets = fichiers.filter((f) => f.etat === 'pret');
    if (prets.length === 0) return;
    enCours = true;
    dire('');
    // Les fichiers refusés restent dans la liste, avec leur raison (décision D12).
    prets.forEach((f) => { f.etat = 'attente'; });
    regroupement = 'attente';
    etat.textContent = 'Lecture de vos offres. Gardez cet écran ouvert.';
    etat.hidden = false;
    afficher();
    try {
      const { id } = await envoyer('/import-catalogue', {});
      let lues = 0;
      let lignes = 0;
      for (const f of prets) {
        f.etat = 'lecture';
        afficher();
        try {
          const r = await envoyer('/import-catalogue/' + id + '/offres', { lignes: JSON.stringify(f.lignes) });
          Object.assign(f, { etat: 'lu', badge: pluriel(r.lignes, 'ligne') + ' lue' + (r.lignes > 1 ? 's' : '') });
          lues++;
          lignes += r.lignes;
        } catch (e) {
          Object.assign(f, { etat: 'refuse', detail: e.message });
        }
        afficher();
      }
      if (lues === 0) throw new Error('Aucune offre n’a pu être lue.');
      regroupement = 'cours';
      afficher();
      url = (await envoyer('/import-catalogue/' + id + '/regrouper', {})).url;
      regroupement = 'fait';
      etat.textContent = 'Lecture terminée : ' + pluriel(lues, 'offre') + ' lue' + (lues > 1 ? 's' : '') + ', ' + pluriel(lignes, 'ligne') + '.';
    } catch (e) {
      dire(e.message);
      etat.hidden = true;
      regroupement = null;
    }
    enCours = false;
    afficher();
  });

  afficher();
}

// Relecture : le bouton dit ce qui reste à faire ; un prix sans réponse arrête l'envoi et montre la question.
const relecture = document.querySelector('[data-relecture]');
const ajouterBouton = document.querySelector('[data-ajouter]');
if (relecture && ajouterBouton) {
  const questions = [...relecture.querySelectorAll('[data-question]')];
  const sansReponse = () => questions.filter((q) => !q.querySelector('input[type=radio]:checked'));
  const compter = () => {
    // Pas de nombre de produits : un prix du catalogue gardé tel quel n'écrit rien, le compte serait faux.
    const reste = sansReponse().length;
    ajouterBouton.textContent = reste > 0 ? reste + ' prix à choisir d’abord' : 'Ajouter au catalogue';
  };
  relecture.addEventListener('change', () => {
    questions.forEach((q) => { if (q.querySelector('input[type=radio]:checked')) q.classList.remove('faux'); });
    compter();
  });
  relecture.addEventListener('submit', (e) => {
    const premiere = sansReponse()[0];
    if (premiere) {
      e.preventDefault();
      premiere.classList.add('faux');
      premiere.scrollIntoView({ block: 'center', behavior: 'smooth' });
      premiere.querySelector('input').focus({ preventScroll: true });
    }
  });
  compter();
}
