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
      // Le total fixé en bas (D3) suit aussi : il restait à l'ancien montant jusqu'au lot 8 (constaté le 2026-11-15).
      ['.rows', '.totals', '[data-sauvegarde]', '[data-total-fixe]', '.lcard[aria-current="page"]'].forEach((s) => {
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
      // Le document à côté des lignes (PC, D13) se met à jour.
      if (r.ok) document.dispatchEvent(new Event('offre-modifiee'));
    })).catch(() => {
      if (etat()) etat().textContent = 'Non enregistré : pas de connexion';
      if (ligne) ligne.classList.add('miss');
      champ.defaultValue = '';
    }).finally(() => enAttente.delete(requete));
  };

  document.addEventListener('change', (e) => { if (e.target.matches('input[data-envoi-change]')) envoyer(e.target); });
  document.addEventListener('keydown', (e) => {
    if (!e.target.matches('input[data-envoi-change]')) return;
    // Entrée : enregistre, puis le même champ de la ligne suivante (PC, D12) ; sur la dernière ligne, on reste.
    if (e.key === 'Enter') {
      e.preventDefault();
      envoyer(e.target);
      const memes = [...document.querySelectorAll('input[data-envoi-change][name=' + e.target.name + ']')];
      const suivant = memes[memes.indexOf(e.target) + 1];
      if (suivant) suivant.focus(); else e.target.select();
    }
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
    // Le texte tapé, repris dans « Ajouter « … » » (Ajouter, E7) ; rien de tapé : la proposition se cache.
    const texte = champ.dataset.copieTexte && document.querySelector(champ.dataset.copieTexte);
    if (texte) texte.textContent = champ.value.trim();
    if (aucun && champ.value.trim() === '') aucun.hidden = true;
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

// Dictées à l'abri (sous-chantier 27, Xavier, 2026-11-17) : chaque tranche d'une seconde est rangée dans le
// navigateur pendant l'enregistrement, et la dictée y reste jusqu'à ce que son offre soit prête. Une coupure de
// réseau, une fenêtre fermée ou un téléphone éteint ne la perdent plus. Chaque dictée porte le compte qui l'a
// faite : un autre compte, sur le même appareil, ne la voit pas. Sans stockage (navigation privée, refus du
// navigateur, stockage qui ne répond pas), rien ne casse et rien n'attend : l'enregistrement part comme avant.
// États : « enregistrement » (en cours ou coupé), « a_envoyer » (terminé, pas arrivé), « envoye » (au serveur).
// « fin » : l'heure de la dernière tranche (durée réelle) ; « vu » : le dernier signe de vie de l'onglet qui la tient.
const abri = (() => {
  let base = null;
  const avecDelai = (p, ms) => Promise.race([p, new Promise((_, ko) => setTimeout(() => ko(new Error('délai dépassé')), ms))]);
  const ouvrir = () => {
    if (!base) {
      base = avecDelai(new Promise((ok, ko) => {
        if (!window.indexedDB) { ko(new Error('sans stockage')); return; }
        const r = indexedDB.open('offregen-abri', 1);
        r.onupgradeneeded = () => {
          const db = r.result;
          db.createObjectStore('dictees', { keyPath: 'id' }).createIndex('uuid', 'uuid');
          db.createObjectStore('morceaux', { keyPath: ['id', 'n'] });
        };
        r.onsuccess = () => {
          const db = r.result;
          // Une version plus récente ouverte dans un autre onglet, ou une connexion perdue (Safari en arrière-plan) :
          // la suivante rouvre la base.
          db.onversionchange = () => { db.close(); base = null; };
          db.onclose = () => { base = null; };
          ok(db);
        };
        r.onerror = () => ko(r.error);
        r.onblocked = () => ko(new Error('ouverture bloquée'));
      }), 4000);
      base.catch(() => { base = null; });
    }
    return base;
  };
  const resultat = (r) => new Promise((ok, ko) => { r.onsuccess = () => ok(r.result); r.onerror = () => ko(r.error); });
  const fin = (t) => new Promise((ok, ko) => { t.oncomplete = () => ok(); t.onerror = () => ko(t.error); t.onabort = () => ko(t.error); });
  // Une panne du stockage ne remonte jamais : la dictée continue, seule la copie manque.
  const sans = (p, defaut) => p.catch(() => defaut);
  const tranches = (id) => IDBKeyRange.bound([id, 0], [id, Infinity]);

  // seulementSi : les états d'où la modification est permise (une dictée arrivée ne redevient pas « à envoyer »).
  const modifier = (id, champs, seulementSi) => sans(ouvrir().then((db) => {
    const t = db.transaction('dictees', 'readwrite');
    const s = t.objectStore('dictees');
    const g = s.get(id);
    g.onsuccess = () => { if (g.result && (!seulementSi || seulementSi.includes(g.result.etat))) s.put(Object.assign(g.result, champs)); };
    return fin(t);
  }), null);
  const supprimer = (id) => sans(ouvrir().then((db) => {
    const t = db.transaction(['dictees', 'morceaux'], 'readwrite');
    t.objectStore('dictees').delete(id);
    t.objectStore('morceaux').delete(tranches(id));
    return fin(t);
  }), null);

  return {
    // id : la clé de la dictée, aussi envoyée au serveur. Rend true si la copie est commencée.
    commencer: (id, compte, type) => sans(ouvrir().then((db) => {
      const t = db.transaction('dictees', 'readwrite');
      const maintenant = Date.now();
      t.objectStore('dictees').put({ id, compte, type, debut: maintenant, fin: maintenant, vu: maintenant, etat: 'enregistrement', nom: '', uuid: '', tranches: 0 });
      return fin(t).then(() => true);
    }), false),
    ajouter: (id, n, blob) => sans(ouvrir().then((db) => {
      const t = db.transaction(['dictees', 'morceaux'], 'readwrite');
      t.objectStore('morceaux').put({ id, n, blob });
      const s = t.objectStore('dictees');
      const g = s.get(id);
      g.onsuccess = () => {
        if (!g.result) return;
        g.result.tranches = Math.max(g.result.tranches, n + 1);
        g.result.fin = Date.now();
        g.result.vu = g.result.fin;
        s.put(g.result);
      };
      return fin(t);
    }), null),
    modifier,
    supprimer,
    // L'offre de cet enregistrement est prête : la copie du téléphone n'a plus de raison d'être.
    oublier: (uuid) => sans(ouvrir()
      .then((db) => resultat(db.transaction('dictees').objectStore('dictees').index('uuid').getAll(uuid)))
      .then((liste) => Promise.all(liste.map((d) => supprimer(d.id)))), null),
    lister: (compte) => sans(ouvrir()
      .then((db) => resultat(db.transaction('dictees').objectStore('dictees').getAll()))
      .then((liste) => liste.filter((d) => d.compte === compte)), []),
    // Tranches sans dictée (écrites après une suppression) : effacées.
    menage: () => sans(ouvrir().then((db) => {
      const t = db.transaction(['dictees', 'morceaux'], 'readwrite');
      const ids = t.objectStore('dictees').getAllKeys();
      const cles = t.objectStore('morceaux').getAllKeys();
      cles.onsuccess = () => {
        const vivantes = new Set(ids.result);
        cles.result.forEach((k) => { if (!vivantes.has(k[0])) t.objectStore('morceaux').delete(k); });
      };
      return fin(t);
    }), null),
    // Le fichier de la dictée, ses tranches dans l'ordre. Rejette si le stockage ne répond pas.
    fichier: (d) => avecDelai(ouvrir()
      .then((db) => resultat(db.transaction('morceaux').objectStore('morceaux').getAll(tranches(d.id))))
      .then((liste) => {
        if (liste.length === 0) throw new Error('aucune tranche');
        liste.sort((a, b) => a.n - b.n);
        return new File(liste.map((m) => m.blob), d.nom || nomDictee(d.type, new Date(d.debut)), { type: d.type });
      }), 8000),
  };
})();

function nomDictee(type, d) {
  const extension = type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
  const p = (n) => String(n).padStart(2, '0');
  return 'enregistrement-' + d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + '-' + p(d.getHours()) + 'h' + p(d.getMinutes()) + '.' + extension;
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
      if (e.offre) { arret = true; abri.oublier(uuid); fini(e.offre); }
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
  // Le micro est au centre de la barre (décision D1, 2026-11-15) et l'import aussi dans « Plus » : hors de la zone.
  const importers = [...document.querySelectorAll('[data-importer]')];
  const micros = [...document.querySelectorAll('[data-enregistrer]')];
  const avis = el('[data-sans-micro]');
  const possible = window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder;
  // Page /import : le bouton Parler n'apparaît que si le micro est possible. Le micro de la barre reste : sans
  // micro, il ouvre la feuille qui propose l'import.
  micros.forEach((m) => { if (!m.classList.contains('micro-central')) m.hidden = !possible; });
  if (!feuille) avis.hidden = !!possible;
  // Compte d'essai au bout de ses offres (Xavier, 2026-11-17) : prévenir avant de parler, une dictée refusée à l'envoi
  // serait perdue. Ni micro ni import ; le serveur refuse de toute façon (429).
  const epuise = zone.dataset.epuise || '';
  if (epuise && !feuille) {
    avis.textContent = epuise;
    avis.hidden = false;
    zone.querySelectorAll('.choix2').forEach((c) => { c.hidden = true; });
  }
  const bloquer = (e) => {
    if (!epuise) return false;
    if (e) e.preventDefault();
    avis.textContent = epuise;
    zone.querySelectorAll('[data-etat="refus"] [data-importer]').forEach((b) => { b.hidden = true; });
    if (feuille) montrer('refus'); else avis.hidden = false;
    return true;
  };

  let etatCourant = 'choix';
  let occupe = false;
  let arreterSuivi = () => {};
  let duMicro = false; // le fichier en cours vient du micro : le fermer sans l'envoyer pourrait le perdre
  let copieAbri = false; // le fichier en cours est aussi gardé dans le navigateur
  const perteAcceptee = () => !duMicro || window.confirm(copieAbri
    ? 'Fermer sans envoyer ? La dictée reste sur ce téléphone et sera proposée à la prochaine ouverture.'
    : 'Fermer sans envoyer ? L’enregistrement est perdu s’il n’est pas gardé dans un fichier.');
  window.addEventListener('beforeunload', (e) => { if (occupe) e.preventDefault(); });
  // Retour sur une page gardée en mémoire par le navigateur : la liste a changé depuis.
  window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });

  // Une dictée proposée puis écartée (croix, geste retour) ne revient pas avant la prochaine ouverture de l'onglet.
  let repriseCourante = null;
  const plusTard = (d) => { try { if (d) sessionStorage.setItem('offregen-plus-tard-' + d.id, '1'); } catch (e) { /* sans stockage de session : elle reviendra */ } };
  const remiseAPlusTard = (d) => { try { return sessionStorage.getItem('offregen-plus-tard-' + d.id) !== null; } catch (e) { return false; } };

  const fenetre = feuille && {
    montrer: () => apparaitre(feuille),
    cacher: () => {
      if (etatCourant === 'reprise') plusTard(repriseCourante);
      disparaitre(feuille);
      etatCourant = 'choix';
      occupe = false;
      arreterSuivi();
    },
    // Pendant l'enregistrement et l'envoi, le retour ne ferme rien ; après un échec, il demande.
    retenir: () => {
      // Après l'envoi, le traitement continue au serveur : la fenêtre peut se fermer.
      if (['choix', 'suivi', 'court', 'refus', 'reprise'].includes(etatCourant)) return false;
      if (etatCourant === 'echec') return !perteAcceptee();
      return true;
    },
  };

  const montrer = (etat) => {
    if (etatCourant === 'reprise' && etat !== 'reprise') plusTard(repriseCourante);
    etatCourant = etat;
    zone.querySelectorAll('[data-etat]').forEach((s) => { s.hidden = s.dataset.etat !== etat; });
    if (fermerX) fermerX.hidden = !['echec', 'court', 'refus', 'reprise'].includes(etat);
    if (!fenetre) return;
    feuille.classList.toggle('pleine', etat === 'enregistrement');
    if (etat === 'choix') { calques.fermer(fenetre); return; }
    if (calques.ouvert() !== fenetre) calques.ouvrir(fenetre);
  };

  // Importer : avec JavaScript, le choix du fichier s'ouvre sur place ; sans, le lien mène à /import.
  importers.forEach((i) => i.addEventListener('click', (e) => { if (bloquer(e)) return; e.preventDefault(); entree.click(); }));
  // Onde au toucher des boutons ronds.
  zone.addEventListener('pointerdown', (e) => {
    const b = e.target.closest('.pastille');
    if (!b) return;
    b.classList.remove('touche');
    void b.offsetWidth;
    b.classList.add('touche');
  });
  zone.addEventListener('animationend', (e) => { if (e.animationName === 'onde') e.target.classList.remove('touche'); });
  if (fermerX) fermerX.addEventListener('click', () => {
    if (etatCourant === 'reprise') { montrer('choix'); return; }
    if (perteAcceptee()) montrer('choix');
  });

  // Quitter pour la page de préparation. Une entrée d'historique ajoutée par un calque est remplacée :
  // le retour ramène à la liste, pas à une fenêtre fermée.
  const partir = (url) => {
    occupe = false;
    if (calques.ouvert()) window.location.replace(url); else window.location.assign(url);
  };

  let fichier = null;
  // La clé de la dictée du fichier en cours : envoyée au serveur, qui reconnaît un second envoi de la même dictée
  // (réponse du premier perdue en route), et nom de sa copie à l'abri. null pour un fichier importé.
  let cleCourante = null;
  const echec = (texte) => { el('[data-echec-texte]').textContent = texte; montrer('echec'); };
  const liste = el('[data-etapes]');
  const lienGarder = (f) => {
    const garder = el('[data-garder]');
    if (garder.href.startsWith('blob:')) URL.revokeObjectURL(garder.href);
    garder.href = URL.createObjectURL(f);
    garder.download = f.name;
    garder.hidden = false;
  };
  const envoyer = () => {
    if (!fichier) return;
    montrer('envoi');
    occupe = true;
    marquerEtapes(liste, 0, false);
    const progression = el('[data-progression]');
    const texte = el('[data-progression-texte]');
    progression.value = 0;
    progression.hidden = false;
    texte.textContent = '';
    const cle = cleCourante;
    const donnees = new FormData();
    donnees.append('_csrf', form.querySelector('input[name=_csrf]').value);
    if (cle) donnees.append('cle', cle);
    donnees.append('audio', fichier, fichier.name);
    const xhr = new XMLHttpRequest();
    let signe = 0;
    xhr.open('POST', '/import');
    xhr.upload.addEventListener('progress', (e) => {
      // Signe de vie : un autre onglet ne propose pas une dictée en cours d'envoi.
      if (cle && Date.now() - signe > 5000) { signe = Date.now(); abri.modifier(cle, { vu: signe }); }
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
        // Arrivée au serveur : la copie reste jusqu'à l'offre prête (suivreEtapes, ou au prochain lancement).
        if (cle) abri.modifier(cle, { etat: 'envoye', uuid, vu: Date.now() });
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
    if (bloquer()) { entree.value = ''; return; }
    fichier = f;
    duMicro = false;
    copieAbri = false;
    cleCourante = null;
    el('[data-garder]').hidden = true;
    envoyer();
  });

  // Parler.
  let flux = null;
  let enregistreur = null; // porte .cle, .copie (promesse : copie commencée) et .arret (« terminer », « abandon »)
  let morceaux = [];
  const compte = document.body.dataset.compte || '';
  let minuteur = null;
  let veille = null;
  let debut = 0;
  let finMs = 0; // heure où l'enregistreur s'est arrêté
  // Règle de Xavier (2026-11-12) : moins de 5 secondes, l'enregistrement n'est pas envoyé.
  const DUREE_MIN_MS = 5000;
  // Règle de Xavier (2026-11-17) : 30 minutes au plus. Prévenu 5 minutes avant, l'enregistrement s'arrête et part seul.
  const DUREE_MAX_S = Number(zone.dataset.dureeMax) || 1800;
  const PREAVIS_S = Math.min(300, Math.floor(DUREE_MAX_S / 6));
  const libererMicro = () => {
    clearInterval(minuteur);
    if (flux) flux.getTracks().forEach((t) => t.stop());
    flux = null;
    if (veille) veille.release().catch(() => {});
    veille = null;
  };
  const TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus', 'audio/webm'];
  const refus = (texte) => {
    if (texte) avis.textContent = texte;
    if (feuille) montrer('refus'); else avis.hidden = false;
  };
  const nouvelleCle = () => Date.now() + '-' + Math.random().toString(36).slice(2, 10);

  // « Terminer » : la dictée part depuis la mémoire, sans attendre le stockage du navigateur.
  const finir = (rec) => {
    if ((finMs || Date.now()) - debut < DUREE_MIN_MS) {
      occupe = false;
      duMicro = false;
      rec.copie.then((ok) => ok && abri.supprimer(rec.cle));
      montrer('court');
      return;
    }
    const type = rec.mimeType || 'audio/webm';
    fichier = new File(morceaux, nomDictee(type, new Date(debut)), { type });
    cleCourante = rec.cle;
    lienGarder(fichier);
    const nom = fichier.name;
    rec.copie.then((ok) => ok && abri.modifier(rec.cle, { etat: 'a_envoyer', nom }, ['enregistrement']));
    envoyer();
  };

  const demarrer = async () => {
    if (bloquer()) return;
    if (!possible) { refus(); return; }
    if (enregistreur && enregistreur.state !== 'inactive') return;
    try {
      flux = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 } });
    } catch (e) {
      refus('Le micro est refusé ou absent. Autorisez-le pour ce site dans les réglages du navigateur, ou enregistrez avec le téléphone puis « Importer ».');
      return;
    }
    const type = TYPES.find((t) => MediaRecorder.isTypeSupported(t)) || '';
    const rec = new MediaRecorder(flux, type ? { mimeType: type, audioBitsPerSecond: 32000 } : {});
    enregistreur = rec;
    morceaux = [];
    // Chaque tranche part aussi à l'abri, dans l'ordre : coupé n'importe quand, le début de la dictée reste.
    rec.cle = nouvelleCle();
    rec.arret = null;
    rec.copie = compte ? abri.commencer(rec.cle, compte, type || rec.mimeType || 'audio/webm') : Promise.resolve(false);
    copieAbri = false;
    rec.copie.then((ok) => { if (enregistreur === rec) copieAbri = ok; });
    let rang = 0;
    rec.addEventListener('dataavailable', (e) => {
      if (!e.data || e.data.size === 0) return;
      morceaux.push(e.data);
      const n = rang++;
      rec.copie.then((ok) => ok && abri.ajouter(rec.cle, n, e.data));
    });
    rec.addEventListener('stop', () => {
      finMs = Date.now();
      libererMicro();
      if (rec.arret === 'terminer') finir(rec);
      else if (rec.arret === 'abandon') rec.copie.then((ok) => ok && abri.supprimer(rec.cle));
      // Arrêt sans « Terminer » (appel entrant, autre application sur le micro) : ce qui est enregistré reste.
      else el('[data-consigne]').textContent = 'L’enregistrement s’est arrêté (appel ou autre application). « Terminer » envoie ce qui est enregistré.';
    });
    rec.start(1000);
    occupe = true;
    duMicro = true;
    debut = Date.now();
    finMs = 0;
    const affichage = el('[data-minuteur]');
    const deux = (n) => String(n).padStart(2, '0');
    affichage.textContent = '00:00';
    const consigne = el('[data-consigne]');
    consigne.textContent = 'Parlez, pièce par pièce.';
    minuteur = setInterval(() => {
      const s = Math.floor((Date.now() - debut) / 1000);
      const h = Math.floor(s / 3600);
      affichage.textContent = (h ? h + ':' : '') + deux(Math.floor(s / 60) % 60) + ':' + deux(s % 60);
      if (s >= DUREE_MAX_S) {
        clearInterval(minuteur);
        el('[data-terminer]').click();
      } else if (s >= DUREE_MAX_S - PREAVIS_S) {
        const reste = Math.ceil((DUREE_MAX_S - s) / 60);
        consigne.textContent = 'Encore ' + reste + ' min, puis l’enregistrement s’arrête et part.';
      }
    }, 500);
    if (navigator.wakeLock) navigator.wakeLock.request('screen').then((v) => { veille = v; }).catch(() => {});
    montrer('enregistrement');
  };
  micros.forEach((m) => m.addEventListener('click', demarrer));

  // Un seul « Terminer » compte (deux touchers, ou un toucher pendant l'arrêt automatique des 30 minutes).
  el('[data-terminer]').addEventListener('click', () => {
    const rec = enregistreur;
    if (!rec || rec.arret) return;
    rec.arret = 'terminer';
    if (rec.state === 'inactive') finir(rec); else rec.stop();
  });

  // Trop court : on recommence tout de suite, le micro se rouvre.
  el('[data-recommencer]').addEventListener('click', demarrer);

  el('[data-abandonner]').addEventListener('click', () => {
    if (!window.confirm('Abandonner l’enregistrement ? Il ne sera pas gardé.')) return;
    occupe = false;
    duMicro = false;
    const rec = enregistreur;
    if (rec && !rec.arret) {
      rec.arret = 'abandon';
      // Supprimée après la dernière tranche, qui arrive avec l'arrêt.
      if (rec.state !== 'inactive') rec.stop(); else rec.copie.then((ok) => ok && abri.supprimer(rec.cle));
    }
    libererMicro();
    montrer('choix');
  });

  // Reprise (R1 B, Xavier, 2026-11-18) : à l'ouverture, une dictée restée sur ce téléphone sans être arrivée au
  // serveur est proposée dans la fenêtre du bas. Une dictée arrivée est oubliée dès que son offre est prête.
  const ilYA = (jours) => Date.now() - jours * 86400000;
  const dureeMs = (d) => (d.fin || d.debut) - d.debut;
  const decrire = (d) => {
    const quand = new Date(d.debut);
    const p = (n) => String(n).padStart(2, '0');
    const s = Math.round(dureeMs(d) / 1000);
    const duree = s < 60 ? s + ' s' : Math.round(s / 60) + ' min';
    return 'La dictée du ' + quand.toLocaleDateString('fr-CH', { day: 'numeric', month: 'long' }) + ' à ' + quand.getHours() + ' h ' + p(quand.getMinutes())
      + ' (' + duree + ') est restée sur ce téléphone : elle n’est pas arrivée au serveur.';
  };
  const reprendre = async () => {
    // Sans la fenêtre du bas (page /import), rien n'est proposé : la page garde Parler et Importer.
    if (!compte || !fenetre) return;
    const dictees = await abri.lister(compte);
    abri.menage();
    dictees.forEach((d) => {
      if (d.etat !== 'envoye') return;
      // Arrivée au serveur : oubliée quand l'offre est prête, ou quand le serveur ne la connaît plus (retirée).
      if (d.debut < ilYA(30)) { abri.supprimer(d.id); return; }
      fetch('/enregistrements/' + d.uuid + '/etat', { credentials: 'same-origin', cache: 'no-store' })
        .then((r) => (r.status === 404 ? { offre: true } : r.ok ? r.json() : null))
        .then((e) => { if (e && e.offre) abri.supprimer(d.id); })
        .catch(() => {});
    });
    // Une dictée qu'un autre onglet tient encore (signe de vie depuis moins de 20 s) n'est pas proposée ici.
    const libres = dictees.filter((d) => d.etat !== 'envoye' && Date.now() - (d.vu || d.debut) > 20000);
    // Coupée avant 5 secondes : le serveur la refuserait. Une dictée terminée a déjà passé cette règle.
    const tropCourte = (d) => d.etat === 'enregistrement' && dureeMs(d) < DUREE_MIN_MS;
    libres.filter(tropCourte).forEach((d) => abri.supprimer(d.id));
    const d = libres.filter((x) => !tropCourte(x) && !remiseAPlusTard(x)).sort((a, b) => a.debut - b.debut)[0];
    if (!d || etatCourant !== 'choix') return;
    repriseCourante = d;
    // Compte d'essai au bout de ses offres : la dictée ne peut pas partir, elle peut être gardée.
    el('[data-reprise-envoyer]').hidden = !!epuise;
    el('[data-reprise-texte]').textContent = decrire(d) + (epuise ? ' ' + epuise + ' Gardez-la dans un fichier.' : ' Envoyez-la maintenant pour en faire une offre.');
    montrer('reprise');
  };
  const relectureImpossible = () => {
    el('[data-reprise-texte]').textContent += ' Elle n’a pas pu être relue sur ce téléphone.';
  };
  let relecture = false; // un seul envoi, même pour deux touchers
  el('[data-reprise-envoyer]').addEventListener('click', async () => {
    const d = repriseCourante;
    if (!d || relecture || bloquer()) return;
    relecture = true;
    try {
      fichier = await abri.fichier(d);
    } catch (e) {
      relectureImpossible();
      return;
    } finally {
      relecture = false;
    }
    duMicro = true;
    copieAbri = true;
    cleCourante = d.id;
    lienGarder(fichier);
    envoyer();
  });
  el('[data-reprise-garder]').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!repriseCourante) return;
    let f;
    try {
      f = await abri.fichier(repriseCourante);
    } catch (erreur) {
      relectureImpossible();
      return;
    }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(f);
    a.download = f.name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 60000);
  });
  el('[data-reprise-supprimer]').addEventListener('click', async () => {
    if (!repriseCourante || !window.confirm('Supprimer cette dictée ? Elle ne pourra plus être envoyée.')) return;
    await abri.supprimer(repriseCourante.id);
    repriseCourante = null;
    montrer('choix');
  });
  reprendre();
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
  // Une fenêtre qui a changé l'offre (lignes ajoutées, ligne ou objet corrigés) la recharge en se fermant
  // (data-recharger) : les champs des lignes se rebranchent avec la page.
  let aChange = false;
  const calque = {
    montrer: () => apparaitre(fond),
    cacher: () => {
      if (aChange && fond.dataset.recharger) {
        window.location.reload();
        return;
      }
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
    // Les feuilles de style de la page chargée (le document de la facture, la couleur de l'artisan).
    importerStyles(page);
    corps.replaceChildren(...contenu.childNodes);
    const barre = page.querySelector('.bas .actionbar');
    pied.replaceChildren(...(barre ? [barre] : []));
    corps.scrollTop = 0;
    // Facturer s'ouvre en deux volets sur PC (maquette 25, E8) : la feuille s'élargit.
    boite.classList.toggle('large', !!corps.querySelector('[data-feuille-large]'));
    corps.querySelectorAll('fieldset[data-adresse]').forEach(brancherAdresse);
    corps.querySelectorAll('form[data-auto]').forEach(brancherAuto);
    corps.querySelectorAll('input[data-filtre]').forEach(brancherFiltre);
    corps.querySelectorAll('form[data-facturer]').forEach(brancherFacturer);
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
    // data-en-feuille-pc : une fenêtre au milieu sur PC seulement (la ligne, l'objet, E8) ; au téléphone, la page.
    if (a.hasAttribute('data-en-feuille') || (a.hasAttribute('data-en-feuille-pc') && window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches)) {
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
    // Le bouton touché compte (« Corriger des lignes » contre « Créer la facture ») : FormData seul l'oublie.
    const donnees = new FormData(form);
    if (e.submitter && e.submitter.name) donnees.set(e.submitter.name, e.submitter.value);
    fetch(form.action, { method: 'POST', body: donnees, credentials: 'same-origin' })
      .then((r) => r.text().then((html) => {
        if (r.ok && r.redirected) aChange = true;
        if (r.ok && r.redirected && new URL(r.url).pathname === ici) {
          fermer();
          return rafraichir(html, avant, modifie);
        }
        // Une fiche qui mène ailleurs une fois envoyée (la facture créée, son PDF) : on y va.
        if (r.ok && r.redirected && form.hasAttribute('data-sortir')) {
          window.location.assign(r.url);
          return null;
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
  const rafraichirBadges = () => {
    attendus.forEach(({ badge, champ }) => {
      const rempli = champ.value.trim() !== '';
      badge.hidden = rempli;
      champ.classList.toggle('manque', !rempli);
    });
    // L'adresse : son badge tombe quand elle est enregistrée avec son NPA.
    form.querySelectorAll('fieldset[data-adresse]').forEach((g) => {
      const badge = g.querySelector('[data-adresse-badge]');
      const npa = g.querySelector('[data-a="npa"]');
      if (badge && npa) badge.hidden = npa.value.trim() !== '';
    });
  };

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

  // L'adresse part entière : ses champs attendent qu'on la quitte (ou qu'on choisisse une suggestion), sinon la rue
  // seule serait refusée faute de NPA à chaque pause de la frappe.
  let adresseEnAttente = false;
  const dansAdresse = (c) => c.closest && c.closest('fieldset[data-adresse]');
  form.querySelectorAll('fieldset[data-adresse]').forEach((g) => {
    const partir = () => { if (adresseEnAttente) { adresseEnAttente = false; envoyer(); } };
    g.addEventListener('focusout', (e) => { if (!g.contains(e.relatedTarget)) partir(); });
    g.addEventListener('adresse-choisie', () => { adresseEnAttente = true; partir(); });
  });

  form.addEventListener('input', (e) => {
    if (e.target.form !== form || !tape(e.target)) return;
    if (dansAdresse(e.target)) { adresseEnAttente = true; return; }
    attendre();
  });
  form.addEventListener('change', (e) => {
    const c = e.target;
    if (c.form !== form) return; // le champ du logo appartient à son propre formulaire
    if (dansAdresse(c)) { adresseEnAttente = true; return; }
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
    if (minuterie === null && !adresseEnAttente) return;
    clearTimeout(minuterie);
    minuterie = null;
    adresseEnAttente = false;
    fetch(form.action, { method: 'POST', body: corps(), headers: { Accept: 'application/json' }, credentials: 'same-origin', keepalive: true });
  });
}

// Adresse en champs séparés (sous-chantier 18, choix de Xavier du 2026-11-13 : « Tu tapes ton adresse et puis ça
// te dit oui, tu es ici ou pas »). Pendant la frappe de la rue, les adresses du registre officiel (swisstopo, par
// notre serveur) ; le NPA remplit la localité (répertoire copié chez nous) ; en quittant l'adresse, trouvée ou pas.
// Sans JavaScript ou sans registre, les champs se tapent et le serveur contrôle le NPA quand même.
function adresses() {
  document.querySelectorAll('fieldset[data-adresse]').forEach(brancherAdresse);
}

function brancherAdresse(groupe) {
  if (groupe.dataset.adresseBranchee) return;
  groupe.dataset.adresseBranchee = '1';
  const c = {};
  ['rue', 'numero', 'npa', 'localite', 'pays'].forEach((k) => { c[k] = groupe.querySelector('[data-a="' + k + '"]'); });
  const liste = groupe.querySelector('[data-adresse-suggestions]');
  const etat = groupe.querySelector('[data-adresse-etat]');
  if (!c.rue || !c.npa || !c.localite || !c.pays || !liste || !etat) return;
  const dire = (texte, classe) => { etat.textContent = texte; etat.className = 'adresse-etat' + (classe ? ' ' + classe : ''); };
  const suisse = () => c.pays.value === 'CH' || c.pays.value === 'LI';
  const lire = (url) => fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : null)).catch(() => null);
  const TROUVEE = 'Adresse trouvée au registre officiel des bâtiments.';

  const fermer = () => { liste.hidden = true; liste.textContent = ''; };
  const choisir = (s) => {
    c.rue.value = s.rue; c.numero.value = s.numero; c.npa.value = s.npa; c.localite.value = s.localite; c.pays.value = 'CH';
    fermer();
    dire(TROUVEE, 'ok');
    c.numero.focus();
    groupe.dispatchEvent(new CustomEvent('adresse-choisie', { bubbles: true }));
  };

  let minuterie = null;
  let demandee = '';
  // « Bundesplatz 3 » tapé dans la rue : le numéro va dans son champ (même règle que SaisieAdresse côté serveur).
  const NUMERO_AU_BOUT = /^(.*\S)\s+(\d+\s?[a-zA-Z]?(?:[/.-]\d+[a-zA-Z]?)?)$/;
  const separerNumero = () => {
    const m = c.rue.value.trim().match(NUMERO_AU_BOUT);
    if (m && c.numero) { c.rue.value = m[1]; c.numero.value = m[2].replace(/\s/g, ''); }
  };
  c.rue.addEventListener('change', separerNumero);

  const proposer = async () => {
    // Le texte tapé seul, plus le numéro s'il n'est pas déjà dans la rue : l'ancien NPA ne restreint pas la recherche.
    const rue = c.rue.value.trim();
    const q = (NUMERO_AU_BOUT.test(rue) ? rue : rue + ' ' + c.numero.value).trim().replace(/\s+/g, ' ');
    if (!suisse() || c.rue.value.trim().length < 3) { fermer(); return; }
    if (q === demandee) return;
    demandee = q;
    const r = await lire('/adresses/suggestions?q=' + encodeURIComponent(q));
    if (q !== demandee) return; // une frappe plus récente a sa propre réponse
    fermer();
    // Réponse arrivée après qu'on a quitté l'adresse : la liste ne s'ouvre plus par-dessus le reste.
    if (!groupe.contains(document.activeElement)) return;
    if (!r || !r.suggestions || r.suggestions.length === 0) return;
    r.suggestions.forEach((s) => {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = s.texte;
      // Le clic ne retire pas le curseur du champ : l'adresse ne part pas à moitié.
      b.addEventListener('mousedown', (e) => e.preventDefault());
      b.addEventListener('click', () => choisir(s));
      li.appendChild(b);
      liste.appendChild(li);
    });
    liste.hidden = false;
  };
  const plusTard = () => { dire(''); clearTimeout(minuterie); minuterie = setTimeout(proposer, 350); };
  c.rue.addEventListener('input', plusTard);
  c.numero.addEventListener('input', plusTard);
  groupe.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !liste.hidden) { e.preventDefault(); fermer(); } });

  c.npa.addEventListener('input', async () => {
    dire('');
    const npa = c.npa.value.trim();
    if (!suisse() || !/^\d{4}$/.test(npa)) return;
    const r = await lire('/adresses/localites?npa=' + npa);
    if (!r || c.npa.value.trim() !== npa) return;
    if (r.localites.length === 0) { dire("Ce NPA n'existe pas au répertoire officiel des localités.", 'warn'); return; }
    const tapee = c.localite.value.trim();
    if (r.seule && (tapee === '' || r.localites.indexOf(tapee) < 0)) c.localite.value = r.seule;
    else if (!r.seule && tapee === '') dire('Localités de ce NPA : ' + r.localites.join(', ') + '.');
  });

  const verifier = async () => {
    fermer();
    if (!suisse() || !c.rue.value.trim() || !/^\d{4}$/.test(c.npa.value.trim()) || etat.classList.contains('ok')) return;
    const p = new URLSearchParams({ rue: c.rue.value, numero: c.numero.value, npa: c.npa.value, localite: c.localite.value });
    const r = await lire('/adresses/verification?' + p.toString());
    if (!r || r.trouvee === null) return;
    if (r.trouvee) dire(TROUVEE, 'ok');
    else dire("Adresse introuvable au registre officiel : vérifiez la rue et le numéro. Elle s'enregistre quand même.", 'warn');
  };
  groupe.addEventListener('focusout', (e) => { if (!groupe.contains(e.relatedTarget)) verifier(); });
  groupe.addEventListener('input', () => { if (etat.classList.contains('ok')) dire(''); });
}

// « Ma marque » (2a, 2026-11-19) : les vignettes, vrais documents, à la largeur de leur case ; au toucher d'une pastille
// (ou d'une couleur tapée), leur couleur suit aussitôt, par la feuille d'aperçu (?apercu=, rien n'est écrit).
function vignettesVivantes() {
  const cases = document.querySelectorAll('[data-vignette]');
  if (!cases.length) return;
  if (window.ResizeObserver) {
    const ro = new ResizeObserver((entrees) => entrees.forEach((x) => { if (x.target.clientWidth) x.target.style.setProperty('--echelle', String(x.target.clientWidth / 820)); }));
    cases.forEach((c) => ro.observe(c));
  }
  const feuille = document.querySelector('link[data-marque-css]');
  const form = document.querySelector('form#reglages');
  if (!feuille || !form) return;
  const couleur = () => {
    const choix = form.querySelector('input[name="couleur_choix"]:checked');
    if (!choix) return null;
    const v = choix.value === 'autre' ? (form.querySelector('#couleur') || {}).value : choix.value;
    return /^#[0-9a-fA-F]{6}$/.test(v || '') ? v : null;
  };
  const suivre = () => {
    const c = couleur();
    if (c) feuille.href = '/reglages/marque.css?apercu=' + encodeURIComponent(c.toUpperCase());
  };
  form.addEventListener('change', (e) => { if (e.target.matches('[name="couleur_choix"], #couleur')) suivre(); });
  form.addEventListener('input', (e) => { if (e.target.matches('#couleur')) suivre(); });
}

// 2b (Xavier, 2026-11-20 : « j'ai tenté de mettre du blanc […] j'ai pas de message ») : une couleur tapée, ou celle du
// logo, sous 7:1 sur le blanc ; l'encadré propose deux versions plus foncées de la même teinte. Même calcul que
// Marque::propositions (PHP). Un toucher en prend une : le champ couleur change, l'enregistrement automatique suit.
function avisCouleur() {
  const avis = document.querySelector('[data-avis-couleur]');
  const form = avis && avis.closest('form');
  if (!avis || !form) return;
  const proposees = (avis.dataset.teintes || '').split(',');
  const rvb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
  const lin = (c) => { const s = c / 255; return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4); };
  const contraste = (h) => { const [r, v, b] = rvb(h).map(lin); return 1.05 / (0.2126 * r + 0.7152 * v + 0.0722 * b + 0.05); };
  const hex = (t) => '#' + t.map((c) => c.toString(16).padStart(2, '0')).join('').toUpperCase();
  const foncer = (h, seuil) => {
    let t = rvb(h);
    for (let i = 0; i < 40 && contraste(hex(t)) < seuil; i++) t = t.map((c) => Math.round(c * 0.92));
    return hex(t);
  };
  const rapport = (c) => String(Math.floor(c * 10) / 10).replace('.', ',');
  const couleur = () => {
    const choix = form.querySelector('input[name="couleur_choix"]:checked');
    if (!choix) return null;
    const v = choix.value === 'autre' ? (form.querySelector('#couleur') || {}).value : choix.value;
    return /^#[0-9a-fA-F]{6}$/.test(v || '') ? v.toUpperCase() : null;
  };
  const maj = () => {
    const c = couleur();
    const k = c && !proposees.includes(c) ? contraste(c) : 21;
    avis.hidden = k >= 7;
    if (avis.hidden) return;
    avis.querySelector('[data-avis-titre]').textContent = k >= 4.5 ? 'Cette couleur manque de contraste sur le papier blanc' : 'Cette couleur se lit mal sur le papier blanc';
    avis.querySelector('[data-avis-contraste]').textContent = rapport(k);
    avis.querySelector('[data-avis-pale]').hidden = k >= 4.5;
    avis.querySelectorAll('[data-niveau]').forEach((b) => {
      const h = foncer(c, b.dataset.niveau === 'bien' ? 7 : 4.5);
      b.value = h;
      b.querySelector('circle').setAttribute('fill', h);
      b.querySelector('[data-hex]').textContent = h;
      b.querySelector('[data-rapport]').textContent = rapport(contraste(h));
      b.setAttribute('aria-pressed', String(h === c));
    });
  };
  form.addEventListener('change', (e) => { if (e.target.matches('[name="couleur_choix"], #couleur')) maj(); });
  form.addEventListener('input', (e) => { if (e.target.matches('#couleur')) maj(); });
  avis.addEventListener('click', (e) => {
    const b = e.target.closest('[data-niveau]');
    const autre = form.querySelector('input[name="couleur_choix"][value="autre"]');
    const champ = form.querySelector('#couleur');
    if (!b || !autre || !champ) return;
    e.preventDefault();
    autre.checked = true;
    champ.value = b.value.toLowerCase();
    champ.dispatchEvent(new Event('input', { bubbles: true }));
    champ.dispatchEvent(new Event('change', { bubbles: true }));
  });
  maj();
}

// Feuille A4 des documents (modèles du bâtiment, 2026-11-20) : chaque modèle est dessiné sur 794 px, comme dans le PDF,
// et se réduit à la largeur de son papier (--feuille, lue par document-ecran.css). La réduction ne change pas la place
// que la feuille occupe : une marge basse négative la lui rend. Les papiers qui arrivent plus tard (document à côté,
// aperçu d'une facture) sont suivis de la même façon.
function papiers() {
  if (!window.ResizeObserver || !window.MutationObserver) return;
  const ajuster = (papier) => {
    const feuille = papier.querySelector(':scope > [data-feuille]');
    if (!feuille || !papier.clientWidth) return;
    const echelle = papier.clientWidth / 794;
    papier.style.setProperty('--feuille', String(echelle));
    feuille.style.marginBottom = Math.round(feuille.offsetHeight * (echelle - 1)) + 'px';
  };
  const tailles = new ResizeObserver((entrees) => entrees.forEach((x) => ajuster(x.target)));
  const suivre = (racine) => {
    if (racine.matches && racine.matches('.paper')) tailles.observe(racine);
    racine.querySelectorAll('.paper').forEach((p) => tailles.observe(p));
  };
  suivre(document);
  new MutationObserver((changements) => changements.forEach((c) => c.addedNodes.forEach((n) => { if (n.nodeType === 1) suivre(n); })))
    .observe(document.body, { childList: true, subtree: true });
}

// « Voir en grand » sur un modèle (Xavier, 2026-11-15) : l'image s'agrandit dans la page, sans en ouvrir
// une autre. Flèches pour passer d'un modèle à l'autre, « Choisir ce modèle » coche et ferme (un toucher de
// moins). Sans JavaScript, le lien mène à la page d'aperçu.
function modelesEnGrand() {
  const liens = [...document.querySelectorAll('a.voir[data-grand]')];
  if (!liens.length) return;
  const vue = document.createElement('div');
  vue.className = 'agrandi';
  vue.hidden = true;
  vue.setAttribute('role', 'dialog');
  vue.setAttribute('aria-modal', 'true');
  vue.innerHTML = '<div class="agrandi-haut"><b data-titre></b><button type="button" class="agrandi-x" data-fermer aria-label="Fermer">×</button></div>'
    + '<div class="agrandi-image" data-fond><img alt=""></div>'
    + '<div class="agrandi-bas"><button type="button" class="btn" data-pas="-1" aria-label="Modèle précédent">‹</button>'
    + '<button type="button" class="btn primary" data-choisir>Choisir ce modèle</button>'
    + '<button type="button" class="btn" data-pas="1" aria-label="Modèle suivant">›</button></div>';
  document.body.appendChild(vue);
  const img = vue.querySelector('img');
  const fond = vue.querySelector('[data-fond]');
  let rang = 0;
  const montrer = (i) => {
    rang = (i + liens.length) % liens.length;
    const l = liens[rang];
    // 2a : le vrai document du modèle, dans la couleur touchée ; l'image figée seulement sans vignette.
    const doc = document.querySelector('[data-vignette="' + l.dataset.cle + '"] > .paper');
    fond.querySelectorAll('.paper').forEach((p) => p.remove());
    img.hidden = Boolean(doc);
    if (doc) {
      const copie = doc.cloneNode(true);
      fond.append(copie);
      requestAnimationFrame(() => {
        const e = Math.min(1, (fond.clientWidth - 24) / 820);
        copie.style.transform = 'scale(' + e + ')';
        copie.style.marginBottom = Math.round(copie.offsetHeight * (e - 1)) + 'px';
      });
    }
    img.src = l.dataset.grand;
    img.alt = 'Modèle ' + l.dataset.nom + ', en grand';
    vue.querySelector('[data-titre]').textContent = 'Modèle ' + l.dataset.nom;
    const radio = document.querySelector('input[name="modele"][value="' + l.dataset.cle + '"]');
    vue.querySelector('[data-choisir]').textContent = radio && radio.checked ? 'Modèle choisi' : 'Choisir ce modèle';
  };
  const calque = {
    montrer() { vue.hidden = false; document.body.classList.add('agrandi-ouvert'); vue.querySelector('[data-choisir]').focus(); },
    cacher() { vue.hidden = true; document.body.classList.remove('agrandi-ouvert'); liens[rang].focus(); },
  };
  const fermer = () => calques.fermer(calque);
  liens.forEach((l, i) => l.addEventListener('click', (e) => { e.preventDefault(); montrer(i); calques.ouvrir(calque); }));
  vue.addEventListener('click', (e) => {
    if (e.target.closest('[data-fermer]') || e.target.matches('[data-fond]')) { fermer(); return; }
    const pas = e.target.closest('[data-pas]');
    if (pas) { montrer(rang + Number(pas.dataset.pas)); return; }
    if (e.target.closest('[data-choisir]')) {
      const radio = document.querySelector('input[name="modele"][value="' + liens[rang].dataset.cle + '"]');
      if (radio && !radio.checked) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
      fermer();
    }
  });
  // Au doigt (Xavier, 2026-11-20) : glisser vers la gauche montre le modèle suivant, vers la droite le précédent. Un
  // geste surtout vertical fait défiler la page ; deux doigts, ou une page agrandie, laissent le navigateur zoomer.
  let depart = null;
  vue.addEventListener('touchstart', (e) => {
    const zoom = window.visualViewport ? window.visualViewport.scale : 1;
    depart = e.touches.length === 1 && zoom <= 1.05 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
  }, { passive: true });
  vue.addEventListener('touchmove', (e) => { if (e.touches.length > 1) depart = null; }, { passive: true });
  vue.addEventListener('touchend', (e) => {
    if (!depart || e.target.closest('.agrandi-bas, .agrandi-haut')) { depart = null; return; }
    const t = e.changedTouches[0];
    const dx = t.clientX - depart.x;
    const dy = t.clientY - depart.y;
    depart = null;
    if (Math.abs(dx) >= 50 && Math.abs(dx) > 1.5 * Math.abs(dy)) montrer(rang + (dx < 0 ? 1 : -1));
  }, { passive: true });
  document.addEventListener('keydown', (e) => {
    if (calques.ouvert() !== calque) return;
    if (e.key === 'Escape') fermer();
    else if (e.key === 'ArrowLeft') montrer(rang - 1);
    else if (e.key === 'ArrowRight') montrer(rang + 1);
  });
}

// Lignes d'une offre ou d'un brouillon de facture : glisser vers la gauche retire la ligne (décision D4 B,
// 2026-11-15), par le même envoi que la corbeille, donc avec le même « Annuler ». Un défilement vertical reste
// un défilement ; un glissé qui part d'un champ ou d'un bouton ne fait rien.
function glisserLignes() {
  const SEUIL = 110;
  document.querySelectorAll('[data-glisse-ligne]').forEach((row) => {
    const suppr = row.querySelector('form[action$="/supprimer"]');
    if (!suppr) return;
    let x0 = null;
    let y0 = 0;
    let dx = 0;
    let glisse = false;
    row.addEventListener('pointerdown', (e) => {
      if (e.button !== 0 || e.target.closest('input, button, select, textarea')) return;
      x0 = e.clientX;
      y0 = e.clientY;
      dx = 0;
      glisse = false;
    });
    row.addEventListener('pointermove', (e) => {
      if (x0 === null) return;
      const mx = e.clientX - x0;
      if (!glisse) {
        if (Math.abs(mx) < 10) return;
        if (Math.abs(e.clientY - y0) > Math.abs(mx) || mx > 0) { x0 = null; return; }
        glisse = true;
        row.classList.add('tire');
        row.setPointerCapture(e.pointerId);
      }
      dx = Math.min(0, mx);
      row.style.setProperty('--dx', dx + 'px');
      row.classList.toggle('assez', dx <= -SEUIL);
    });
    const fin = () => {
      if (x0 === null) return;
      x0 = null;
      if (glisse && dx <= -SEUIL) { suppr.submit(); return; }
      row.classList.remove('tire', 'assez');
      row.style.removeProperty('--dx');
    };
    row.addEventListener('pointerup', fin);
    row.addEventListener('pointercancel', fin);
    // Le nom de la ligne est un lien : sans cela, le navigateur le glisse lui-même et interrompt le geste.
    row.addEventListener('dragstart', (e) => e.preventDefault());
    // La fin d'un glissé envoie un clic : il n'ouvre pas la ligne.
    row.addEventListener('click', (e) => { if (glisse) { e.preventDefault(); e.stopPropagation(); glisse = false; } }, true);
  });
}

// Relecture (D5) : le montant suit la quantité et le prix pendant la frappe. Le serveur refait le calcul.
function montantRelu() {
  const q = document.querySelector('[data-relu-quantite]');
  const p = document.querySelector('[data-relu-prix]');
  const sortie = document.querySelector('[data-relu-montant]');
  if (!q || !p || !sortie) return;
  const nombre = (v) => parseFloat(String(v).replace(/[\s'’]/g, '').replace(',', '.'));
  const maj = () => {
    const centimes = Math.round(nombre(q.value) * nombre(p.value) * 100);
    sortie.textContent = Number.isFinite(centimes) && centimes > 0
      ? 'CHF ' + (centimes / 100).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/'/g, '’')
      : '?';
  };
  q.addEventListener('input', maj);
  p.addEventListener('input', maj);
}

// Les feuilles de style d'une page chargée à côté (document de facture, couleur de l'artisan) : ajoutées une fois.
function importerStyles(page) {
  page.querySelectorAll('link[rel="stylesheet"]').forEach((l) => {
    const href = l.getAttribute('href');
    if (![...document.querySelectorAll('link[rel="stylesheet"]')].some((x) => x.getAttribute('href') === href)) {
      const lien = document.createElement('link');
      lien.rel = 'stylesheet';
      lien.href = href;
      document.head.append(lien);
    }
  });
}

// Facturer (maquette 25, E1 à E4) : chaque choix recharge l'aperçu de la facture (rien n'est écrit) et le montant du
// bouton ; Entrée dans un champ recalcule au lieu de créer (une facture créée est définitive) ; toucher la miniature
// l'ouvre en grand, avec « Créer la facture » en bas. Dans la feuille par-dessus l'offre, ou sur la page seule.
function brancherFacturer(form) {
  if (form.dataset.branche) return;
  form.dataset.branche = '1';
  const mini = form.querySelector('[data-apercu-mini]');
  const ouvrir = form.querySelector('[data-apercu-ouvrir]');
  const bouton = () => document.querySelector('[data-bouton-creer]');
  const blocs = { acompte: form.querySelector('[data-si-acompte]'), finale: form.querySelector('[data-si-finale]'), chf: form.querySelector('[data-si-chf]') };
  const valeur = (nom) => {
    const champ = form.querySelector(`input[type="radio"][name="${nom}"]:checked`) || form.querySelector(`[name="${nom}"]:not([type="radio"])`);
    return champ ? champ.value : '';
  };
  const montrer = () => {
    const genre = valeur('genre');
    if (blocs.acompte) blocs.acompte.hidden = genre !== 'acompte';
    if (blocs.finale) blocs.finale.hidden = genre !== 'finale';
    if (blocs.chf) blocs.chf.hidden = valeur('pourcent') !== 'chf';
  };
  let numero = 0;
  const recharger = () => {
    const ce = ++numero;
    // « CHF » choisi, rien de tapé encore : une consigne plutôt qu'une erreur.
    if (valeur('genre') === 'acompte' && valeur('pourcent') === 'chf' && valeur('montant').trim() === '') {
      const consigne = document.createElement('p');
      consigne.className = 'alerte info';
      consigne.textContent = 'Tapez le montant que le client paiera, TVA comprise.';
      mini.replaceChildren(consigne);
      const b = bouton();
      if (b) b.textContent = 'Créer la facture';
      return;
    }
    const params = new URLSearchParams();
    ['genre', 'pourcent', 'montant', 'numero', 'date', 'echeance'].forEach((n) => params.set(n, valeur(n)));
    fetch(form.dataset.apercu + '?' + params, { credentials: 'same-origin' })
      .then((r) => r.text())
      .then((html) => {
        if (ce !== numero) return; // une réponse plus récente est attendue
        const page = new DOMParser().parseFromString(html, 'text/html');
        const doc = page.querySelector('.apercu-facture');
        const refus = page.querySelector('[data-apercu-erreur]');
        const b = bouton();
        if (doc) {
          mini.replaceChildren(document.importNode(doc, true));
          if (b) b.textContent = doc.dataset.bouton;
        } else if (refus) {
          mini.replaceChildren(document.importNode(refus, true));
          if (b) b.textContent = 'Créer la facture';
        }
      })
      .catch(() => { /* sans réseau, l'aperçu reste le dernier ; la création dira l'erreur */ });
  };
  let minuterie = null;
  const plusTard = () => { clearTimeout(minuterie); minuterie = setTimeout(recharger, 300); };
  form.addEventListener('change', (e) => {
    montrer();
    if (e.target.matches('input[name="pourcent"][value="chf"]')) {
      const champ = form.querySelector('#montant');
      if (champ) champ.focus();
    }
    // L'échéance proposée suit le genre (acompte à 30 jours, finale selon les conditions), tant que l'artisan ne l'a
    // pas changée lui-même (revue du 25).
    const echeance = form.querySelector('#echeance');
    if (e.target.matches('input[name="genre"]') && echeance) {
      const genre = valeur('genre');
      const autre = genre === 'acompte' ? 'finale' : 'acompte';
      if (echeance.value === echeance.dataset[autre] && echeance.dataset[genre]) echeance.value = echeance.dataset[genre];
    }
    recharger();
  });
  form.addEventListener('input', (e) => { if (e.target.matches('#montant, #numero')) plusTard(); });
  form.addEventListener('keydown', (e) => {
    // Entrée, même sur un bouton radio, recalcule : elle ne crée rien et n'envoie pas « Corriger des lignes », premier
    // bouton du formulaire (revue du 25).
    if (e.key === 'Enter' && e.target.matches('input')) {
      e.preventDefault();
      recharger();
    }
  });

  // La facture en grand, par-dessus tout, avec « Retour » et « Créer la facture ».
  const enGrand = () => {
    const doc = mini.querySelector('.apercu-facture');
    if (!doc) return;
    const fond = document.createElement('div');
    fond.className = 'apercu-grand';
    fond.setAttribute('role', 'dialog');
    fond.setAttribute('aria-modal', 'true');
    fond.setAttribute('aria-label', 'Aperçu de la facture');
    fond.tabIndex = -1;
    const barre = document.createElement('div');
    barre.className = 'apercu-grand-barre';
    const retour = document.createElement('button');
    retour.type = 'button';
    retour.className = 'btn';
    retour.textContent = 'Retour';
    const note = document.createElement('span');
    note.className = 'muted petit';
    note.textContent = 'Aperçu : rien n’est encore créé';
    barre.append(retour, note);
    const corps = document.createElement('div');
    corps.className = 'apercu-grand-corps';
    corps.append(doc.cloneNode(true));
    const pied = document.createElement('div');
    pied.className = 'apercu-grand-pied';
    const creer = document.createElement('button');
    creer.type = 'submit';
    creer.setAttribute('form', form.id);
    creer.name = 'suite';
    creer.value = 'creer';
    creer.className = 'btn primary grand';
    const b = bouton();
    creer.textContent = b ? b.textContent : 'Créer la facture';
    pied.append(creer);
    fond.append(barre, corps, pied);
    const echap = (e) => {
      if (e.key !== 'Escape') return;
      e.preventDefault();
      e.stopPropagation();
      fermer();
    };
    const fermer = () => {
      fond.remove();
      document.removeEventListener('keydown', echap, true);
      if (ouvrir) ouvrir.focus({ preventScroll: true });
    };
    retour.addEventListener('click', fermer);
    creer.addEventListener('click', () => setTimeout(() => { fond.remove(); document.removeEventListener('keydown', echap, true); }, 0));
    document.addEventListener('keydown', echap, true);
    document.body.append(fond);
    fond.focus();
  };
  if (ouvrir) {
    ouvrir.addEventListener('click', enGrand);
    ouvrir.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); enGrand(); } });
  }
  montrer();
}

function feuillesFacturer() {
  document.querySelectorAll('form[data-facturer]').forEach(brancherFacturer);
}

// Ajouter sur PC (maquette 25, E7) : la ligne de recherche au bas du tableau. Les produits se proposent pendant la
// frappe ; Entrée ajoute le premier (ou celui choisi aux flèches) ; un texte sans produit devient une ligne libre.
// La page revient sur la ligne ajoutée, curseur dans sa quantité (clavierPc). Échap vide le champ.
function ajoutPc() {
  document.querySelectorAll('form[data-ajout-pc]').forEach((form) => {
    const champ = form.querySelector('input[name="q"]');
    const liste = form.querySelector('[role="listbox"]');
    const produits = [...liste.querySelectorAll('li[data-id]')];
    const libre = liste.querySelector('li[data-libre]');
    const libreTexte = libre.querySelector('[data-libre-texte]');
    let actif = -1;
    const visibles = () => [...liste.querySelectorAll('li:not([hidden])')];
    const marquer = (i) => {
      const v = visibles();
      actif = v.length ? (i + v.length) % v.length : -1;
      v.forEach((li, j) => li.setAttribute('aria-selected', String(j === actif)));
    };
    const filtrer = () => {
      const texte = champ.value.trim();
      const mots = normaliser(texte).split(' ').filter(Boolean);
      let n = 0;
      produits.forEach((li) => {
        const garde = mots.length > 0 && n < 6 && mots.every((m) => li.dataset.texte.includes(m));
        li.hidden = !garde;
        if (garde) n++;
      });
      libre.hidden = texte === '';
      libreTexte.textContent = '« ' + texte + ' »';
      liste.hidden = texte === '';
      champ.setAttribute('aria-expanded', String(!liste.hidden));
      marquer(0);
    };
    const envoyer = (li) => {
      const texte = champ.value.trim();
      if (!texte) return;
      const poste = document.createElement('form');
      poste.method = 'post';
      poste.action = form.dataset.poste;
      poste.hidden = true;
      const champs = { _csrf: form.dataset.csrf };
      if (li && li.dataset.id) champs.produit = li.dataset.id;
      else Object.assign(champs, { designation: texte, quantite: '1', unite: 'forfait', prix: '' });
      Object.entries(champs).forEach(([nom, valeur]) => {
        const entree = document.createElement('input');
        entree.type = 'hidden';
        entree.name = nom;
        entree.value = valeur;
        poste.append(entree);
      });
      document.body.append(poste);
      poste.submit();
    };
    champ.addEventListener('input', filtrer);
    champ.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); marquer(actif + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); marquer(actif - 1); }
      else if (e.key === 'Enter') { e.preventDefault(); envoyer(visibles()[actif] || null); }
      else if (e.key === 'Escape') { champ.value = ''; filtrer(); }
    });
    // mousedown plutôt que click : le champ ne perd pas la main avant le choix.
    liste.addEventListener('mousedown', (e) => {
      const li = e.target.closest('li');
      if (li) { e.preventDefault(); envoyer(li); }
    });
    champ.addEventListener('blur', () => setTimeout(() => { liste.hidden = true; champ.setAttribute('aria-expanded', 'false'); }, 150));
    champ.addEventListener('focus', () => { if (champ.value.trim()) filtrer(); });
  });
}

// La hauteur du bas de l'écran (boutons, phrase, onglets) : les bandeaux se posent au-dessus (--bas, app.css). Constaté
// le 2026-11-15 : à hauteur fixe, le bandeau recouvrait « Créer la facture » quand une phrase suit le bouton.
function hauteurDuBas() {
  const bas = document.querySelector('.app > .bas');
  if (!bas) return;
  const poser = () => document.documentElement.style.setProperty('--bas', Math.round(bas.getBoundingClientRect().height) + 'px');
  poser();
  if ('ResizeObserver' in window) new ResizeObserver(poser).observe(bas);
}

// Un message qui commente la recherche de départ disparaît dès qu'on en tape une autre.
function messagesDeRecherche() {
  const messages = document.querySelectorAll('[data-cache-si-recherche]');
  if (!messages.length) return;
  document.querySelectorAll('[data-filtre]').forEach((champ) => champ.addEventListener('input', () => messages.forEach((m) => { m.hidden = true; }), { once: true }));
}

// Clavier du PC (D12, D13, 2026-11-15). D : dicter une offre, hors d'un champ de saisie. Sur une ligne de l'offre :
// J dit « c'est juste » (aussi depuis la quantité ou le prix, où la lettre n'a pas de sens), Suppr la retire hors d'un
// champ (avec « Annuler », comme la corbeille). Aucune touche ne joue avec Ctrl, Alt ou Cmd, ni fenêtre ouverte.
function clavierPc() {
  const saisie = (el) => el.matches('input, textarea, select, [contenteditable="true"]');
  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented || document.body.classList.contains('calque-ouvert')) return;
    const cible = e.target instanceof Element ? e.target : document.body;
    const touche = e.key.toLowerCase();
    if (touche === 'd' && !saisie(cible)) {
      const micro = document.querySelector('.micro-central[data-enregistrer]');
      if (micro) { e.preventDefault(); micro.click(); }
      return;
    }
    const ligne = cible.closest('.row');
    if (!ligne) return;
    if (touche === 'j' && (!saisie(cible) || cible.matches('input[data-envoi-change]'))) {
      const juste = ligne.querySelector('form.note.warn');
      e.preventDefault();
      if (juste) juste.requestSubmit();
      return;
    }
    if (e.key === 'Delete' && !saisie(cible)) {
      const retirer = ligne.querySelector('form[action$="/supprimer"]');
      if (retirer) { e.preventDefault(); retirer.requestSubmit(); }
    }
  });
  // Retour sur l'offre après « c'est juste » ou une correction (#l12) : sur PC, le curseur revient dans la ligne.
  const ancre = /^#l(\d+)$/.exec(window.location.hash);
  if (ancre && window.matchMedia('(min-width: 1024px) and (hover: hover) and (pointer: fine)').matches) {
    const champ = document.querySelector('#l' + ancre[1] + ' input[data-envoi-change]');
    // Après les autres branchements (le focus sélectionne alors le contenu du champ, comme au clic).
    if (champ) setTimeout(() => champ.focus(), 0);
  }
}

// « Document à côté » (PC, D13) : le document de l'offre remplace la colonne des offres et se recharge après chaque
// correction. Le choix est gardé dans ce navigateur. Le document vient de /offres/{id}/document, avec ses feuilles
// de style (document.css et la couleur de l'artisan).
function documentACote() {
  const bouton = document.querySelector('[data-doc-cote]');
  if (!bouton) return;
  bouton.hidden = false;
  let colonne = null;
  let numero = 0;
  const charger = () => {
    const ce = ++numero;
    fetch(bouton.dataset.docCote, { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error(String(r.status)))))
      .then((html) => {
        if (ce !== numero || !colonne) return; // une réponse plus récente est attendue
        const page = new DOMParser().parseFromString(html, 'text/html');
        importerStyles(page);
        // Un brouillon de facture vient avec son filigrane « Aperçu ».
        const papier = page.querySelector('.apercu-facture') || page.querySelector('article.paper');
        if (papier) colonne.replaceChildren(document.importNode(papier, true));
      })
      .catch(() => { if (ce === numero && colonne) colonne.textContent = 'Le document ne s’affiche pas : rechargez la page.'; });
  };
  // Le brouillon d'une facture (maquette 25, E8) montre son document d'office, sans toucher au choix gardé pour l'offre.
  const toujours = bouton.hasAttribute('data-doc-cote-toujours');
  const montrer = (oui) => {
    document.body.classList.toggle('doc-a-cote', oui);
    bouton.setAttribute('aria-pressed', String(oui));
    bouton.textContent = oui ? (toujours ? 'Cacher le document' : 'Revenir à la liste') : 'Document à côté';
    if (oui && !colonne) {
      colonne = document.createElement('aside');
      colonne.className = 'colonne-doc';
      colonne.setAttribute('aria-label', 'Document de l’offre');
      document.body.append(colonne);
    }
    if (colonne) colonne.hidden = !oui;
    if (oui) charger();
    if (toujours) return;
    try { window.localStorage.setItem('offregen-doc-a-cote', oui ? '1' : '0'); } catch (err) { /* stockage refusé : le choix ne se garde pas */ }
  };
  bouton.addEventListener('click', () => montrer(!document.body.classList.contains('doc-a-cote')));
  document.addEventListener('offre-modifiee', () => { if (document.body.classList.contains('doc-a-cote')) charger(); });
  let garde = false;
  try { garde = window.localStorage.getItem('offregen-doc-a-cote') === '1'; } catch (err) { /* idem */ }
  // Le document à côté demande 1280 px (app.css) ; plus étroit, le choix reste gardé pour un plus grand écran.
  if ((garde || toujours) && window.matchMedia('(min-width: 1280px) and (hover: hover) and (pointer: fine)').matches) montrer(true);
}

// L'œil d'un mot de passe : montre ou masque tous les champs de mot de passe du même formulaire (P3 B).
function oeilMotDePasse() {
  document.querySelectorAll('button[data-oeil]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
      const montrer = bouton.getAttribute('aria-pressed') !== 'true';
      bouton.setAttribute('aria-pressed', montrer ? 'true' : 'false');
      bouton.setAttribute('aria-label', montrer ? 'Masquer le mot de passe' : 'Montrer le mot de passe');
      (bouton.form || document).querySelectorAll('input[data-oeil-cible]').forEach((c) => { c.type = montrer ? 'text' : 'password'; });
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  oeilMotDePasse();
  clavierPc();
  documentACote();
  messagesDeRecherche();
  feuillesFacturer();
  hauteurDuBas();
  ajoutPc();
  vignettesVivantes();
  avisCouleur();
  papiers();
  modelesEnGrand();
  glisserLignes();
  montantRelu();
  adresses();
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
