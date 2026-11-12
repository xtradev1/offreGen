// offreGen : aides d'interface. Les pages marchent sans ce fichier, sauf l'enregistrement dans le
// navigateur, qui en a besoin. La CSP refuse le JavaScript en ligne : tout passe par ce fichier.
'use strict';

// Même règle que Catalogue\Recherche::normaliser côté serveur : minuscules, sans accents.
const normaliser = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/\p{Mn}+/gu, '').replace(/\s+/g, ' ').trim();

// Corrections de quantité et de prix sur l'offre. Chaque champ modifié part tout seul, par fetch avec
// keepalive : l'envoi aboutit même si l'artisan quitte la page aussitôt (retour, onglet). Constaté le
// 2026-11-12 : un envoi de formulaire classique était annulé par le lien touché juste après.
function corrections() {
  const etat = () => document.querySelector('[data-sauvegarde]');
  const enAttente = new Set();

  const envoyer = (champ) => {
    const form = champ.form;
    if (!form || champ.value === champ.defaultValue) return;
    champ.defaultValue = champ.value;
    const corps = new URLSearchParams(new FormData(form));
    const ligne = form.closest('.row');
    const requete = fetch(form.action, { method: 'POST', body: corps, keepalive: true, credentials: 'same-origin' });
    enAttente.add(requete);
    if (etat()) etat().textContent = 'Enregistrement…';
    requete.then((r) => r.text().then((html) => {
      // La réponse est l'offre à jour : on reprend ses lignes, ses totaux et ses messages.
      const page = new DOMParser().parseFromString(html, 'text/html');
      const actif = document.activeElement;
      const garde = actif && actif.closest && actif.closest('.row') ? [actif.closest('.row').id, actif.name, actif.selectionStart, actif.selectionEnd] : null;
      // Une autre saisie en cours (pas encore partie) survit au remplacement des lignes.
      const enCours = [...document.querySelectorAll('input[data-envoi-change]')]
        .filter((c) => c.value !== c.defaultValue)
        .map((c) => [c.closest('.row').id, c.name, c.value]);
      ['.rows', '.totals', '[data-sauvegarde]'].forEach((s) => {
        const neuf = page.querySelector(s);
        const vieux = document.querySelector(s);
        if (neuf && vieux) vieux.replaceWith(neuf);
      });
      enCours.forEach(([ligneId, nom, valeur]) => {
        const c = document.querySelector('#' + ligneId + ' input[name=' + nom + ']');
        if (c) c.value = valeur;
      });
      page.querySelectorAll('.snack').forEach((s) => { document.querySelector('.app').append(s); setTimeout(() => s.remove(), 6000); });
      if (garde) {
        const retrouve = document.querySelector('#' + garde[0] + ' input[name=' + garde[1] + ']');
        if (retrouve) { retrouve.focus(); try { retrouve.setSelectionRange(garde[2], garde[3]); } catch (e) { /* champ sans sélection */ } }
      }
      if (!r.ok && etat()) etat().textContent = 'Non enregistré, réessayez';
    })).catch(() => {
      if (etat()) etat().textContent = 'Non enregistré : pas de connexion';
      if (ligne) ligne.classList.add('miss');
      champ.defaultValue = '';
    }).finally(() => enAttente.delete(requete));
  };

  document.addEventListener('change', (e) => { if (e.target.matches('input[data-envoi-change]')) envoyer(e.target); });
  document.addEventListener('keydown', (e) => {
    if (!e.target.matches('input[data-envoi-change]')) return;
    if (e.key === 'Enter') { e.preventDefault(); envoyer(e.target); e.target.select(); }
    if (e.key === 'Escape') { e.target.value = e.target.defaultValue; e.target.blur(); }
  });
  document.addEventListener('focusin', (e) => { if (e.target.matches('input[data-envoi-change]')) e.target.select(); });
  // Filet : un champ encore modifié quand la page s'en va part quand même.
  window.addEventListener('pagehide', () => {
    document.querySelectorAll('input[data-envoi-change]').forEach((c) => { if (c.value !== c.defaultValue) envoyer(c); });
  });
}

function envoisDeFormulaires() {
  // Pendant l'envoi d'un formulaire, le bouton le dit et ne part pas deux fois.
  document.querySelectorAll('form[id]').forEach((form) => {
    form.addEventListener('submit', () => {
      document.querySelectorAll('button[data-attente]').forEach((b) => {
        if (b.form === form) { b.disabled = true; b.textContent = b.dataset.attente; }
      });
    });
  });
}

// Filtre à la frappe d'une liste rendue en entier par le serveur (catalogue, ajout à l'offre).
function filtres() {
  document.querySelectorAll('input[data-filtre]').forEach(brancherFiltre);
}

function brancherFiltre(champ) {
  if (champ.dataset.branche) return;
  champ.dataset.branche = '1';
  const liste = document.querySelector(champ.dataset.filtre);
  if (!liste) return;
  const appliquer = () => {
    const mots = normaliser(champ.value).split(' ').filter(Boolean);
    let trouves = 0;
    let groupe = null;
    let dansGroupe = 0;
    const fermer = () => {
      if (groupe) {
        groupe.hidden = dansGroupe === 0;
        const compte = groupe.querySelector('[data-compte]');
        if (compte) compte.textContent = String(dansGroupe);
      }
    };
    [...liste.children].forEach((el) => {
      if (el.hasAttribute('data-groupe')) { fermer(); groupe = el; dansGroupe = 0; return; }
      if (!el.hasAttribute('data-texte')) return;
      // Un produit d'une autre catégorie que celle choisie reste caché, quoi qu'on tape.
      const garde = !el.hasAttribute('data-hors-cat') && mots.every((m) => el.dataset.texte.includes(m));
      el.hidden = !garde;
      if (garde) { trouves++; dansGroupe++; }
    });
    fermer();
    const aucun = liste.querySelector('[data-aucun]');
    if (aucun) aucun.hidden = trouves > 0;
    const creer = liste.querySelector('[data-creer]');
    if (creer) creer.href = (creer.dataset.creer || '/catalogue/nouveau?q=') + encodeURIComponent(champ.value.trim());
    const copie = champ.dataset.copie && document.querySelector(champ.dataset.copie);
    if (copie && trouves === 0) copie.value = champ.value.trim();
  };
  champ.addEventListener('input', appliquer);
  // La liste est déjà filtrée : Entrée ne recharge pas la page.
  if (champ.form) champ.form.addEventListener('submit', (e) => { e.preventDefault(); appliquer(); });
}

function bandeaux() {
  document.querySelectorAll('.snack').forEach((s) => {
    // « Annuler » reste quelques secondes (CODING_RULES), les autres messages moins longtemps.
    setTimeout(() => s.remove(), s.hasAttribute('data-disparait') ? 8000 : 4000);
  });
  // Ligne ajoutée depuis le catalogue : sa quantité s'ouvre aussitôt. Après « load » : Chrome
  // retire le focus en allant à l'ancre #l… de l'adresse (constaté le 2026-11-12).
  const focus = document.querySelector('[data-focus-ligne]');
  const champ = focus && document.querySelector('#l' + focus.dataset.focusLigne + ' input[name=quantite]');
  if (champ) {
    const ouvrir = () => setTimeout(() => { champ.scrollIntoView({ block: 'center' }); champ.focus(); }, 0);
    if (document.readyState === 'complete') ouvrir(); else window.addEventListener('load', ouvrir, { once: true });
  }
}

// Calques : fenêtres du bas (enregistrement, fiche produit). Ils montent à l'ouverture, redescendent à la
// fermeture. Le retour du téléphone ferme le calque du dessus au lieu de quitter la page.
const DUREE_ANIMATION = 330;
const minuteries = new WeakMap();
const apparaitre = (el) => {
  clearTimeout(minuteries.get(el));
  el.hidden = false;
  void el.offsetWidth; // l'état de départ est posé avant la transition
  el.classList.add('ouvert');
};
const disparaitre = (el) => {
  el.classList.remove('ouvert');
  clearTimeout(minuteries.get(el));
  minuteries.set(el, setTimeout(() => { el.hidden = true; }, DUREE_ANIMATION));
};

const calques = (() => {
  const pile = [];
  const ouvert = () => pile[pile.length - 1] || null;
  const marquer = () => document.body.classList.toggle('calque-ouvert', pile.length > 0);
  window.addEventListener('popstate', () => {
    const haut = ouvert();
    if (!haut) return;
    if (haut.retenir && haut.retenir()) { history.pushState({ calque: true }, ''); return; }
    pile.pop();
    marquer();
    haut.cacher();
  });
  return {
    ouvert,
    ouvrir(c) {
      if (pile.includes(c)) return;
      // Un message resté à l'écran recouvrirait la fenêtre qui monte.
      document.querySelectorAll('.app > .snack').forEach((s) => s.remove());
      pile.push(c);
      marquer();
      history.pushState({ calque: true }, '');
      c.montrer();
    },
    // La fermeture passe par l'historique : popstate cache le calque, comme pour le bouton retour.
    fermer(c) { if (ouvert() === c) history.back(); },
  };
})();

// Étapes du traitement (envoi, transcription, création de l'offre, offre prête) : fait, en cours, en
// échec ou à venir, selon le nombre d'étapes faites.
function marquerEtapes(liste, faites, echec) {
  [...liste.children].forEach((li, i) => {
    li.className = i < faites ? 'done' : i === faites ? (echec ? 'bad' : 'cur') : 'todo';
  });
}

// Demande l'état chaque seconde et fait avancer les étapes ; appelle fini(offre) quand l'offre est prête.
// Rend une fonction qui arrête le suivi.
function suivreEtapes(liste, uuid, fini) {
  const message = liste.parentElement.querySelector('[data-etapes-message]');
  const dire = (t) => { if (message) message.textContent = t; };
  let arret = false;
  const tour = () => fetch('/enregistrements/' + uuid + '/etat', { credentials: 'same-origin', cache: 'no-store' })
    .then((r) => (r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status))))
    .then((e) => {
      if (arret) return;
      marquerEtapes(liste, e.faites, e.echec);
      dire(e.echec ? 'La préparation a échoué après plusieurs essais.' : e.essais > 0 ? 'Un souci passager, nouvel essai en cours…' : '');
      if (e.offre) { arret = true; fini(e.offre); }
      if (e.echec) arret = true;
    })
    .catch(() => dire('Connexion perdue, nouvel essai…'))
    .finally(() => { if (!arret) setTimeout(tour, 1000); });
  tour();
  return () => { arret = true; };
}

// Page de préparation : les étapes avancent seules, et l'offre s'ouvre quand elle est prête.
function preparation() {
  const liste = document.querySelector('main [data-etapes][data-uuid]');
  if (!liste) return;
  suivreEtapes(liste, liste.dataset.uuid, (offre) => setTimeout(() => window.location.replace('/offres/' + offre), 900));
}

// Nouvelle offre : parler ou importer. Sur l'accueil, deux boutons ronds au centre ; l'enregistrement
// et l'envoi montent dans une fenêtre du bas. Sur /import, tout est dans la page.
// Le micro n'est permis que sur https ou sur localhost. Les deux chemins partent par POST /import.
function nouvelleOffre() {
  const zone = document.querySelector('[data-nouvelle]');
  if (!zone) return;
  const el = (s) => zone.querySelector(s);
  const form = el('#import');
  const entree = el('[data-envoi-fichier]');
  const feuille = el('[data-feuille]');
  const fermerX = el('[data-fermer]');
  const importer = el('[data-importer]');
  const micro = el('[data-enregistrer]');
  const possible = window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder;
  micro.hidden = !possible;
  el('[data-sans-micro]').hidden = !!possible;

  let etatCourant = 'choix';
  let occupe = false;
  let arreterSuivi = () => {};
  let duMicro = false; // le fichier en cours vient du micro : le fermer sans l'envoyer le perdrait
  const perteAcceptee = () => !duMicro || window.confirm('Fermer sans envoyer ? L’enregistrement est perdu s’il n’est pas gardé dans un fichier.');
  window.addEventListener('beforeunload', (e) => { if (occupe) e.preventDefault(); });
  // Retour sur une page gardée en mémoire par le navigateur : la liste a changé depuis.
  window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });

  const fenetre = feuille && {
    montrer: () => apparaitre(feuille),
    cacher: () => { disparaitre(feuille); etatCourant = 'choix'; occupe = false; arreterSuivi(); },
    // Pendant l'enregistrement et l'envoi, le retour ne ferme rien ; après un échec, il demande.
    retenir: () => {
      // Après l'envoi, le traitement continue au serveur : la fenêtre peut se fermer.
      if (etatCourant === 'choix' || etatCourant === 'suivi' || etatCourant === 'court') return false;
      if (etatCourant === 'echec') return !perteAcceptee();
      return true;
    },
  };

  const montrer = (etat) => {
    etatCourant = etat;
    zone.querySelectorAll('[data-etat]').forEach((s) => { s.hidden = s.dataset.etat !== etat; });
    if (fermerX) fermerX.hidden = etat !== 'echec' && etat !== 'court';
    if (!fenetre) return;
    feuille.classList.toggle('pleine', etat === 'enregistrement');
    if (etat === 'choix') { calques.fermer(fenetre); return; }
    if (calques.ouvert() !== fenetre) calques.ouvrir(fenetre);
  };

  // Importer : avec JavaScript, le choix du fichier s'ouvre sur place ; sans, le lien mène à /import.
  if (importer) importer.addEventListener('click', (e) => { e.preventDefault(); entree.click(); });
  // Onde au toucher des boutons ronds.
  zone.addEventListener('pointerdown', (e) => {
    const b = e.target.closest('.pastille');
    if (!b) return;
    b.classList.remove('touche');
    void b.offsetWidth;
    b.classList.add('touche');
  });
  zone.addEventListener('animationend', (e) => { if (e.animationName === 'onde') e.target.classList.remove('touche'); });
  if (fermerX) fermerX.addEventListener('click', () => { if (perteAcceptee()) montrer('choix'); });

  // Quitter pour la page de préparation. Une entrée d'historique ajoutée par un calque est remplacée :
  // le retour ramène à la liste, pas à une fenêtre fermée.
  const partir = (url) => {
    occupe = false;
    if (calques.ouvert()) window.location.replace(url); else window.location.assign(url);
  };

  let fichier = null;
  const echec = (texte) => { el('[data-echec-texte]').textContent = texte; montrer('echec'); };
  const liste = el('[data-etapes]');
  const envoyer = () => {
    montrer('envoi');
    occupe = true;
    marquerEtapes(liste, 0, false);
    const progression = el('[data-progression]');
    const texte = el('[data-progression-texte]');
    progression.value = 0;
    progression.hidden = false;
    texte.textContent = '';
    const donnees = new FormData();
    donnees.append('_csrf', form.querySelector('input[name=_csrf]').value);
    donnees.append('audio', fichier, fichier.name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/import');
    xhr.upload.addEventListener('progress', (e) => {
      if (!e.lengthComputable) return;
      progression.value = Math.round((e.loaded / e.total) * 100);
      texte.textContent = (e.loaded / 1048576).toFixed(1) + ' sur ' + (e.total / 1048576).toFixed(1) + ' Mo';
    });
    xhr.addEventListener('load', () => {
      // Le serveur répond par une redirection vers la page de préparation, que XHR suit. L'envoi est
      // fait : l'audio est au serveur, la fenêtre peut se fermer. Les étapes suivantes avancent ici.
      if (xhr.status === 200 && xhr.responseURL.includes('/enregistrements/')) {
        occupe = false;
        etatCourant = 'suivi';
        progression.hidden = true;
        texte.textContent = '';
        marquerEtapes(liste, 1, false);
        const uuid = xhr.responseURL.split('/enregistrements/')[1].split(/[/?#]/)[0];
        arreterSuivi = suivreEtapes(liste, uuid, (offre) => setTimeout(() => { if (etatCourant === 'suivi') partir('/offres/' + offre); }, 900));
        return;
      }
      const page = new DOMParser().parseFromString(xhr.responseText || '', 'text/html');
      const message = page.querySelector('.alerte') || page.querySelector('h1');
      echec((message ? message.textContent.trim() : 'Le serveur a refusé l’envoi') + ' (code ' + xhr.status + ').');
    });
    xhr.addEventListener('error', () => echec('Pas de connexion au serveur. Vérifiez le réseau, puis réessayez.'));
    xhr.send(donnees);
  };
  el('[data-reessayer]').addEventListener('click', envoyer);

  // Importer : le fichier choisi part aussitôt, avec la progression.
  entree.addEventListener('change', (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    fichier = f;
    duMicro = false;
    el('[data-garder]').hidden = true;
    envoyer();
  });

  // Parler.
  let flux = null;
  let enregistreur = null;
  let morceaux = [];
  let minuteur = null;
  let veille = null;
  let debut = 0;
  // Règle de Xavier (2026-11-12) : moins de 5 secondes, l'enregistrement n'est pas envoyé.
  const DUREE_MIN_MS = 5000;
  const libererMicro = () => {
    clearInterval(minuteur);
    if (flux) flux.getTracks().forEach((t) => t.stop());
    flux = null;
    if (veille) veille.release().catch(() => {});
    veille = null;
  };
  const TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];
  micro.addEventListener('click', async () => {
    try {
      flux = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 } });
    } catch (e) {
      const avis = el('[data-sans-micro]');
      avis.textContent = 'Le micro est refusé ou absent. Autorisez-le pour ce site dans les réglages du navigateur, ou enregistrez avec le téléphone puis « Importer ».';
      avis.hidden = false;
      return;
    }
    const type = TYPES.find((t) => MediaRecorder.isTypeSupported(t)) || '';
    enregistreur = new MediaRecorder(flux, type ? { mimeType: type, audioBitsPerSecond: 32000 } : {});
    morceaux = [];
    enregistreur.addEventListener('dataavailable', (e) => { if (e.data && e.data.size > 0) morceaux.push(e.data); });
    enregistreur.start(1000);
    occupe = true;
    duMicro = true;
    debut = Date.now();
    const affichage = el('[data-minuteur]');
    const deux = (n) => String(n).padStart(2, '0');
    affichage.textContent = '00:00';
    minuteur = setInterval(() => {
      const s = Math.floor((Date.now() - debut) / 1000);
      const h = Math.floor(s / 3600);
      affichage.textContent = (h ? h + ':' : '') + deux(Math.floor(s / 60) % 60) + ':' + deux(s % 60);
    }, 500);
    if (navigator.wakeLock) navigator.wakeLock.request('screen').then((v) => { veille = v; }).catch(() => {});
    montrer('enregistrement');
  });

  el('[data-terminer]').addEventListener('click', () => {
    if (!enregistreur) return;
    const tropCourt = Date.now() - debut < DUREE_MIN_MS;
    enregistreur.addEventListener('stop', () => {
      libererMicro();
      if (tropCourt) {
        occupe = false;
        duMicro = false;
        montrer('court');
        return;
      }
      const type = enregistreur.mimeType || 'audio/webm';
      const extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
      const d = new Date();
      const p = (n) => String(n).padStart(2, '0');
      const nom = 'enregistrement-' + d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + '-' + p(d.getHours()) + 'h' + p(d.getMinutes()) + '.' + extension;
      fichier = new File(morceaux, nom, { type });
      const garder = el('[data-garder]');
      garder.href = URL.createObjectURL(fichier);
      garder.download = nom;
      garder.hidden = false;
      envoyer();
    }, { once: true });
    enregistreur.stop();
  });

  // Trop court : on recommence tout de suite, le micro se rouvre.
  el('[data-recommencer]').addEventListener('click', () => micro.click());

  el('[data-abandonner]').addEventListener('click', () => {
    if (!window.confirm('Abandonner l’enregistrement ? Il ne sera pas gardé.')) return;
    occupe = false;
    duMicro = false;
    if (enregistreur && enregistreur.state !== 'inactive') enregistreur.stop();
    libererMicro();
    montrer('choix');
  });
}

// Catalogue : la fiche d'un produit (nouveau, modification, suppression) monte dans une fenêtre du bas,
// par-dessus la liste. app.js y charge la page du lien ; sans JavaScript, le lien ouvre la page seule.
// Après l'enregistrement, la liste se met à jour sur place et la ligne touchée s'éclaire.
function fichesEnFenetre() {
  const fond = document.querySelector('[data-feuille-page]');
  if (!fond) return;
  const boite = fond.querySelector('.feuille');
  const titre = fond.querySelector('[data-feuille-titre]');
  const corps = fond.querySelector('[data-feuille-corps]');
  const pied = fond.querySelector('[data-feuille-pied]');
  const ici = window.location.pathname;
  // Ce que la fermeture recharge, la ligne qui s'éclaire, le champ qui refiltre (défaut : le catalogue).
  const aRafraichir = (fond.dataset.rafraichir || '#clist,.fchips').split(',');
  const lignes = fond.dataset.lignes || '#clist .crow';
  const prefixe = fond.dataset.prefixe || 'p';
  const recherche = fond.dataset.recherche || '#cq';
  let declencheur = null;
  let enregistreSeul = null;
  const calque = {
    montrer: () => apparaitre(fond),
    cacher: () => {
      disparaitre(fond);
      if (declencheur && declencheur.isConnected) declencheur.focus({ preventScroll: true });
      // Une fiche enregistrée au fil de la saisie : la liste derrière se met à jour.
      if (enregistreSeul !== null) { const id = enregistreSeul; enregistreSeul = null; rafraichir('', new Set(), id); }
    },
  };
  fond.addEventListener('enregistre', (e) => {
    const m = (e.target.getAttribute('action') || '').match(/\/(\d+)$/);
    enregistreSeul = m ? m[1] : '';
  });
  const fermer = () => calques.fermer(calque);

  const afficher = (html) => {
    const page = new DOMParser().parseFromString(html, 'text/html');
    const contenu = page.querySelector('main.screen');
    if (!contenu) return false;
    const h1 = page.querySelector('header.bar h1');
    titre.textContent = h1 ? h1.textContent.trim() : '';
    corps.replaceChildren(...contenu.childNodes);
    const barre = page.querySelector('.bas .actionbar');
    pied.replaceChildren(...(barre ? [barre] : []));
    corps.scrollTop = 0;
    corps.querySelectorAll('form[data-auto]').forEach(brancherAuto);
    corps.querySelectorAll('input[data-filtre]').forEach(brancherFiltre);
    const dejaOuverte = calques.ouvert() === calque;
    if (!dejaOuverte) calques.ouvrir(calque);
    // Nouveau produit : la désignation s'ouvre au clavier. Champ refusé : il reçoit le focus.
    const cible = corps.querySelector('[aria-invalid="true"]') || corps.querySelector('[data-premier]') || boite;
    setTimeout(() => cible.focus({ preventScroll: true }), dejaOuverte ? 0 : DUREE_ANIMATION);
    return true;
  };

  const charger = (url) => fetch(url, { credentials: 'same-origin' })
    .then((r) => (r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status))))
    .then((html) => { if (!afficher(html)) throw new Error('page sans contenu'); })
    .catch(() => window.location.assign(url));

  // La liste à jour, avec la recherche et la catégorie de l'adresse ; le message vient de l'envoi.
  const rafraichir = (reponse, avant, modifie) => {
    const message = new DOMParser().parseFromString(reponse, 'text/html');
    return fetch(window.location.pathname + window.location.search, { credentials: 'same-origin' })
      .then((r) => r.text())
      .then((html) => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        aRafraichir.forEach((s) => {
          const neuf = page.querySelector(s);
          const vieux = document.querySelector(s);
          if (neuf && vieux) vieux.replaceChildren(...neuf.childNodes);
        });
        const champ = document.querySelector(recherche);
        const neufChamp = page.querySelector(recherche);
        if (champ && neufChamp) { champ.placeholder = neufChamp.placeholder; champ.dispatchEvent(new Event('input')); }
        // Un nouveau message remplace le précédent : deux bandeaux au même endroit se chevauchent.
        const messages = message.querySelectorAll('.snack');
        if (messages.length) document.querySelectorAll('.app > .snack').forEach((s) => s.remove());
        messages.forEach((s) => { document.querySelector('.app').append(s); setTimeout(() => s.remove(), 4000); });
        const ligne = modifie ? document.getElementById(prefixe + modifie) : [...document.querySelectorAll(lignes)].find((r) => !avant.has(r.id));
        if (ligne && !ligne.hidden) {
          declencheur = ligne;
          ligne.classList.remove('eclair');
          void ligne.offsetWidth;
          ligne.classList.add('eclair');
          ligne.scrollIntoView({ block: 'center' });
        }
      });
  };

  document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a[href]');
    if (!a) return;
    if (fond.contains(a)) {
      // Un lien qui mène ailleurs (une offre depuis la fiche d'un client) quitte la page.
      if (a.hasAttribute('data-sortir')) return;
      // Dans la fiche : un lien vers la liste ferme la fenêtre, un autre (Supprimer, Garder) s'y charge.
      e.preventDefault();
      const u = new URL(a.href, window.location.href);
      if (u.pathname === ici) fermer(); else charger(u.href);
      return;
    }
    if (a.hasAttribute('data-en-feuille')) {
      e.preventDefault();
      declencheur = a;
      charger(a.href);
    }
  });
  fond.addEventListener('click', (e) => { if (e.target === fond || e.target.closest('[data-fermer]')) fermer(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && calques.ouvert() === calque) fermer(); });

  fond.addEventListener('submit', (e) => {
    // Déjà pris en main (enregistrement automatique d'une fiche) : rien à envoyer en plus.
    if (e.defaultPrevented) return;
    e.preventDefault();
    const form = e.target;
    const boutons = fond.querySelectorAll('button[type=submit]');
    boutons.forEach((b) => { b.disabled = true; });
    const avant = new Set([...document.querySelectorAll(lignes)].map((r) => r.id));
    const modifie = (form.getAttribute('action').match(new RegExp('^' + ici + '/(\\d+)$')) || [])[1] || null;
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then((r) => r.text().then((html) => {
        if (r.ok && r.redirected && new URL(r.url).pathname === ici) {
          fermer();
          return rafraichir(html, avant, modifie);
        }
        // Champ refusé (422) ou page de confirmation : elle remplace la fiche.
        if (!afficher(html)) throw new Error('réponse sans contenu');
        return null;
      }))
      .catch(() => {
        const avis = document.createElement('p');
        avis.className = 'alerte bad';
        avis.setAttribute('role', 'alert');
        avis.textContent = 'Pas de connexion au serveur : rien n’est enregistré. Réessayez.';
        corps.prepend(avis);
      })
      .finally(() => boutons.forEach((b) => { b.disabled = false; }));
  });
}

// Liste des offres : balayer une offre vers la gauche montre Archiver et Supprimer (choix de Xavier du
// 2026-11-12). Un glissé n'ouvre pas l'offre, un défilement vertical reste un défilement, une seule
// offre ouverte à la fois. Au clavier, Tab atteint les deux boutons (CSS :focus-within).
function balayage() {
  const cartes = [...document.querySelectorAll('[data-glisse]')];
  if (!cartes.length) return;
  const LARGEUR = 176;
  const fermerTout = (sauf) => cartes.forEach((c) => { if (c !== sauf) c.classList.remove('ouverte'); });
  cartes.forEach((c) => {
    const face = c.querySelector('.ocard');
    let x0 = null;
    let y0 = 0;
    let dx = 0;
    let glisse = false;
    face.addEventListener('pointerdown', (e) => {
      if (e.button !== 0) return;
      x0 = e.clientX;
      y0 = e.clientY;
      dx = 0;
      glisse = false;
    });
    face.addEventListener('pointermove', (e) => {
      if (x0 === null) return;
      const mx = e.clientX - x0;
      if (!glisse) {
        if (Math.abs(mx) < 8) return;
        if (Math.abs(e.clientY - y0) > Math.abs(mx)) { x0 = null; return; }
        glisse = true;
        c.classList.add('tire');
        face.setPointerCapture(e.pointerId);
        face.style.transition = 'none';
        fermerTout(c);
      }
      const depart = c.classList.contains('ouverte') ? -LARGEUR : 0;
      dx = Math.min(0, Math.max(-LARGEUR - 24, depart + mx));
      face.style.transform = 'translateX(' + dx + 'px)';
    });
    const fin = () => {
      if (x0 === null) return;
      x0 = null;
      face.style.transition = '';
      face.style.transform = '';
      c.classList.remove('tire');
      if (glisse) c.classList.toggle('ouverte', dx < -LARGEUR / 2);
    };
    face.addEventListener('pointerup', fin);
    face.addEventListener('pointercancel', fin);
    // La fin d'un glissé envoie un clic : il ne fait rien (constaté le 2026-11-12, il refermait l'offre
    // qu'on venait d'ouvrir). Une offre déjà ouverte se referme au toucher au lieu de s'ouvrir.
    face.addEventListener('click', (e) => {
      if (glisse) {
        e.preventDefault();
        glisse = false;
        return;
      }
      if (c.classList.contains('ouverte')) {
        e.preventDefault();
        c.classList.remove('ouverte');
      }
    });
    face.addEventListener('dragstart', (e) => e.preventDefault());
  });
  document.addEventListener('pointerdown', (e) => { if (!e.target.closest('[data-glisse]')) fermerTout(null); });
}

// Réglages : la phrase « Paiement » de l'offre suit les choix avant l'enregistrement.
function choixDesReglages() {
  const bloc = document.querySelector('[data-paiement]');
  const sortie = bloc && bloc.querySelector('[data-phrase]');
  if (!sortie) return;
  const form = bloc.closest('form');
  const coche = (nom) => { const c = form.querySelector('input[name="' + nom + '"]:checked'); return c ? c.value : ''; };
  const tape = (nom) => (form.elements[nom] ? form.elements[nom].value.trim() : '');
  // Même règle que Reglages::conditionsPaiement(), qui reste celle du document.
  const phrase = () => {
    if (coche('paiement_type') === 'acomptes') {
      const choix = coche('paiement_acomptes_choix');
      const t = ((choix === 'autre' ? tape('paiement_acomptes') : choix).match(/\d+/g) || []).map(Number);
      const n = t.length;
      const juste = n >= 2 && n <= 4 && t.every((x) => x >= 1 && x <= 99) && t.reduce((s, x) => s + x, 0) === 100;
      if (!juste) return 'acomptes à compléter, ils doivent faire 100 %';
      return t.map((p, i) => p + ' % ' + (i === 0 ? 'à la commande' : i === n - 1 ? 'à la fin des travaux' : 'en cours de travaux')).join(', ') + '.';
    }
    const choix = coche('paiement_jours_choix');
    const j = choix === 'autre' ? tape('paiement_jours') : choix;
    if (!/^\d{1,3}$/.test(j) || +j < 1 || +j > 120) return 'délai à compléter, de 1 à 120 jours';
    return coche('paiement_type') === 'net' ? 'Net à ' + +j + ' jours dès réception de la facture.' : 'À ' + +j + ' jours dès la fin des travaux.';
  };
  form.addEventListener('input', () => { sortie.textContent = phrase(); });
  form.addEventListener('change', (e) => {
    sortie.textContent = phrase();
    // « Autre » touché : le champ qui vient d'apparaître reçoit le clavier.
    if (e.target.type === 'radio' && e.target.value === 'autre') {
      const champ = e.target.closest('.choix').querySelector('.si-autre input');
      if (champ) champ.focus();
    }
  });
}

// Un fichier choisi part tout de suite (logo des réglages) : un toucher de moins.
function envoisAutomatiques() {
  document.querySelectorAll('input[type=file][data-envoi-auto]').forEach((champ) => {
    champ.addEventListener('change', () => { if (champ.files && champ.files.length && champ.form) champ.form.submit(); });
  });
}

// Réglages et fiche client : chaque changement s'enregistre seul (choix de Xavier du 2026-11-12). Le bouton
// Enregistrer reste pour qui n'a pas JavaScript, et revient si un envoi automatique échoue.
function enregistrementsAutomatiques() {
  document.querySelectorAll('form[data-auto]').forEach(brancherAuto);
}

function brancherAuto(form) {
  if (form.dataset.branche) return;
  form.dataset.branche = '1';
  const etat = form.querySelector('[data-auto-etat]');
  const boutons = document.querySelectorAll('[data-sans-auto]');
  let secours = false;
  const montrerBoutons = (voir) => {
    secours = voir;
    boutons.forEach((b) => {
      b.hidden = !voir;
      const barre = b.closest('.actionbar');
      if (barre) barre.hidden = !voir;
    });
  };
  montrerBoutons(false);
  const dire = (texte, classe) => {
    if (!etat) return;
    etat.textContent = texte;
    etat.className = 'auto-etat' + (classe ? ' ' + classe : '');
  };

  // Les champs attendus (badge « à compléter ») : le badge suit la saisie une fois enregistrée.
  const attendus = [];
  form.querySelectorAll('.champ').forEach((ch) => {
    const badge = ch.querySelector('label .badge.manque');
    const champ = ch.querySelector('input.manque, textarea.manque');
    if (badge && champ) attendus.push({ badge, champ });
  });
  const rafraichirBadges = () => attendus.forEach(({ badge, champ }) => {
    const rempli = champ.value.trim() !== '';
    badge.hidden = rempli;
    champ.classList.toggle('manque', !rempli);
  });

  const afficherErreurs = (erreurs) => {
    form.querySelectorAll('[data-erreur]').forEach((s) => {
      const nom = s.dataset.erreur;
      const message = erreurs[nom] || '';
      s.textContent = message;
      s.hidden = message === '';
      form.querySelectorAll('[aria-describedby="err-' + nom + '"]').forEach((c) => {
        if (message) c.setAttribute('aria-invalid', 'true'); else c.removeAttribute('aria-invalid');
      });
    });
  };

  let minuterie = null;
  let enCours = false;
  let encore = false;
  const corps = () => new FormData(form);
  const envoyer = async () => {
    clearTimeout(minuterie);
    minuterie = null;
    if (enCours) { encore = true; return; }
    enCours = true;
    dire('Enregistrement…');
    try {
      const r = await fetch(form.action, { method: 'POST', body: corps(), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const json = (r.headers.get('Content-Type') || '').indexOf('json') >= 0 ? await r.json() : null;
      if (r.ok && json && json.ok) {
        afficherErreurs({});
        rafraichirBadges();
        dire('Enregistré', 'ok');
        // La fenêtre qui monte rafraîchit la liste derrière elle à sa fermeture.
        form.dispatchEvent(new CustomEvent('enregistre', { bubbles: true }));
      } else if (r.status === 422 && json) {
        afficherErreurs(json.erreurs || {});
        dire('Pas enregistré : corrigez le champ signalé.', 'bad');
      } else {
        throw new Error('réponse ' + r.status);
      }
    } catch (e) {
      // Session expirée, réseau coupé : on rend la main au bouton, qui recharge la page.
      dire('Pas enregistré. Touchez Enregistrer.', 'bad');
      montrerBoutons(true);
    } finally {
      enCours = false;
      if (encore) { encore = false; envoyer(); }
    }
  };
  const attendre = () => { clearTimeout(minuterie); minuterie = setTimeout(envoyer, 900); };
  const tape = (c) => c.matches('textarea, input:not([type]), input[type=text], input[type=tel], input[type=email], input[type=number]');

  form.addEventListener('input', (e) => { if (e.target.form === form && tape(e.target)) attendre(); });
  form.addEventListener('change', (e) => {
    const c = e.target;
    if (c.form !== form) return; // le champ du logo appartient à son propre formulaire
    // « Autre » touché alors que son champ est vide : on attend la saisie, rien à enregistrer encore.
    if (c.type === 'radio' && c.value === 'autre') {
      const champ = c.closest('.choix') && c.closest('.choix').querySelector('.si-autre input');
      if (champ && champ.value.trim() === '') return;
    }
    envoyer();
  });
  form.addEventListener('submit', (e) => { if (!secours) { e.preventDefault(); envoyer(); } });
  // Une saisie en attente part quand on quitte l'écran (retour au sommaire, autre onglet).
  window.addEventListener('pagehide', () => {
    if (minuterie === null) return;
    clearTimeout(minuterie);
    minuterie = null;
    fetch(form.action, { method: 'POST', body: corps(), headers: { Accept: 'application/json' }, credentials: 'same-origin', keepalive: true });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  enregistrementsAutomatiques();
  envoisAutomatiques();
  choixDesReglages();
  corrections();
  envoisDeFormulaires();
  filtres();
  bandeaux();
  nouvelleOffre();
  preparation();
  balayage();
  fichesEnFenetre();
});
