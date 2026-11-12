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
  document.querySelectorAll('input[data-filtre]').forEach((champ) => {
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
      if (creer) creer.href = '/catalogue/nouveau?q=' + encodeURIComponent(champ.value.trim());
      const copie = champ.dataset.copie && document.querySelector(champ.dataset.copie);
      if (copie && trouves === 0) copie.value = champ.value.trim();
    };
    champ.addEventListener('input', appliquer);
    // La liste est déjà filtrée : Entrée ne recharge pas la page.
    if (champ.form) champ.form.addEventListener('submit', (e) => { e.preventDefault(); appliquer(); });
  });
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
  let duMicro = false; // le fichier en cours vient du micro : le fermer sans l'envoyer le perdrait
  const perteAcceptee = () => !duMicro || window.confirm('Fermer sans envoyer ? L’enregistrement est perdu s’il n’est pas gardé dans un fichier.');
  window.addEventListener('beforeunload', (e) => { if (occupe) e.preventDefault(); });
  // Retour sur une page gardée en mémoire par le navigateur : la liste a changé depuis.
  window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });

  const fenetre = feuille && {
    montrer: () => apparaitre(feuille),
    cacher: () => { disparaitre(feuille); etatCourant = 'choix'; occupe = false; },
    // Pendant l'enregistrement et l'envoi, le retour ne ferme rien ; après un échec, il demande.
    retenir: () => {
      if (etatCourant === 'choix') return false;
      if (etatCourant === 'echec') return !perteAcceptee();
      return true;
    },
  };

  const montrer = (etat) => {
    etatCourant = etat;
    zone.querySelectorAll('[data-etat]').forEach((s) => { s.hidden = s.dataset.etat !== etat; });
    if (fermerX) fermerX.hidden = etat !== 'echec';
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
  const envoyer = () => {
    montrer('envoi');
    occupe = true;
    const progression = el('[data-progression]');
    const texte = el('[data-progression-texte]');
    progression.value = 0;
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
      // Le serveur répond par une redirection vers la page de préparation, que XHR suit.
      if (xhr.status === 200 && xhr.responseURL.includes('/enregistrements/')) {
        partir(xhr.responseURL);
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
    const debut = Date.now();
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
    enregistreur.addEventListener('stop', () => {
      libererMicro();
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
  let declencheur = null;
  const calque = {
    montrer: () => apparaitre(fond),
    cacher: () => {
      disparaitre(fond);
      if (declencheur && declencheur.isConnected) declencheur.focus({ preventScroll: true });
    },
  };
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
        ['#clist', '.fchips'].forEach((s) => {
          const neuf = page.querySelector(s);
          const vieux = document.querySelector(s);
          if (neuf && vieux) vieux.replaceChildren(...neuf.childNodes);
        });
        const champ = document.querySelector('#cq');
        const neufChamp = page.querySelector('#cq');
        if (champ && neufChamp) { champ.placeholder = neufChamp.placeholder; champ.dispatchEvent(new Event('input')); }
        // Un nouveau message remplace le précédent : deux bandeaux au même endroit se chevauchent.
        const messages = message.querySelectorAll('.snack');
        if (messages.length) document.querySelectorAll('.app > .snack').forEach((s) => s.remove());
        messages.forEach((s) => { document.querySelector('.app').append(s); setTimeout(() => s.remove(), 4000); });
        const ligne = modifie ? document.getElementById('p' + modifie) : [...document.querySelectorAll('#clist .crow')].find((r) => !avant.has(r.id));
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
    e.preventDefault();
    const form = e.target;
    const boutons = fond.querySelectorAll('button[type=submit]');
    boutons.forEach((b) => { b.disabled = true; });
    const avant = new Set([...document.querySelectorAll('#clist .crow')].map((r) => r.id));
    const modifie = (form.getAttribute('action').match(/^\/catalogue\/(\d+)$/) || [])[1] || null;
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

document.addEventListener('DOMContentLoaded', () => {
  choixDesReglages();
  corrections();
  envoisDeFormulaires();
  filtres();
  bandeaux();
  nouvelleOffre();
  fichesEnFenetre();
});
