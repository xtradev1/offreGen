// offreGen : l'application sans rechargement (chantier docs/plans/2026-11-23_sans-rechargement, D2, D3, D9).
// Un geste part par fetch et l'écran change au toucher ; le serveur répond par les seuls morceaux de page qui ont pu
// changer (src/Http/Morceaux.php). Les gestes partent dans l'ordre, un à la fois, chacun avec une clé unique que le
// serveur reconnaît (X-Cle-Envoi) : un geste rejoué n'est jamais fait deux fois. Sans réseau, ils attendent et partent
// au retour du réseau. Sans ce fichier, chaque formulaire marche comme avant : envoi, puis page entière.
// La CSP refuse le JavaScript en ligne : tout passe par les fichiers de public/js.
'use strict';

const fluide = (() => {
  const NB = String.fromCharCode(0xA0);
  const PC = '(min-width: 1024px) and (hover: hover) and (pointer: fine)';
  const ecran = () => document.querySelector('main.screen');
  const liste = (texte) => String(texte || '').split(' ').filter(Boolean);
  const resume = () => liste(ecran() && ecran().dataset.resume);
  const jeton = () => { const c = document.querySelector('input[name="_csrf"]'); return c ? c.value : ''; };
  const compte = () => document.body.dataset.compte || '';
  const cleNeuve = () => (window.crypto && crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14));

  // Montants au format du serveur (Http\Formats::montant, de-CH : 1’234.50), pour les estimations au toucher.
  const montant = (centimes) => (centimes / 100).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/'/g, '’');
  const lireMontant = (texte) => {
    const n = parseFloat(String(texte || '').replace(/CHF/, '').replace(/[\s'’]/g, '').replace(String.fromCharCode(0x2212), '-'));
    return Number.isFinite(n) ? Math.round(n * 100) : null;
  };
  const lireNombre = (texte) => { const n = parseFloat(String(texte || '').replace(/[\s'’]/g, '').replace(',', '.')); return Number.isFinite(n) ? n : null; };
  // Un demi s'arrondit en s'éloignant de zéro, comme Offre\Totaux.
  const arrondir = (x, pas) => Math.sign(x) * Math.round(Math.abs(x) / pas) * pas;

  class Refus extends Error {
    constructor(genre, message, code = 0, html = '') { super(message); this.genre = genre; this.code = code; this.html = html; }
  }

  // Le message d'une page de refus : erreurs d'un champ, sinon le titre de la page d'erreur.
  const messageDe = (html, code) => {
    const page = new DOMParser().parseFromString(html || '', 'text/html');
    const erreurs = [...page.querySelectorAll('.err, .alerte.bad')].map((e) => e.textContent.trim()).filter(Boolean);
    if (erreurs.length) return erreurs.join(' ');
    const titre = page.querySelector('main.screen p.muted') || page.querySelector('header.bar h1');
    return titre && titre.textContent.trim() ? titre.textContent.trim() : (code ? 'Refusé par le serveur (' + code + ')' : 'Refusé par le serveur');
  };

  // Bandeaux du bas (« Annuler », messages) : plusieurs à la fois, empilés, chacun avec sa durée.
  const messages = {
    empiler() {
      const tous = [...document.querySelectorAll('.app > .snack:not(.dans-total)')];
      tous.forEach((el, i) => el.style.setProperty('--rang', String(tous.length - 1 - i)));
    },
    poser(el, duree) {
      const app = document.querySelector('.app');
      if (!app || !el) return null;
      // Un « Annuler » pour la même adresse est déjà là (posé au toucher) : celui du serveur ne se pose pas en double.
      const action = el.getAttribute('action');
      if (action && [...app.querySelectorAll(':scope > .snack, .bas > .snack')].some((s) => s.getAttribute('action') === action)) return null;
      app.append(el);
      messages.empiler();
      setTimeout(() => { el.remove(); messages.empiler(); }, duree || (el.hasAttribute('data-disparait') ? 8000 : 4000));
      if (typeof bandeauDansLeTotal === 'function') bandeauDansLeTotal();
      return el;
    },
    dire(texte, mauvais = false) {
      const el = document.createElement('div');
      el.className = 'snack' + (mauvais ? ' bad' : '');
      el.setAttribute('role', mauvais ? 'alert' : 'status');
      const s = document.createElement('span');
      s.textContent = texte;
      el.append(s);
      return messages.poser(el, mauvais ? 7000 : 4000);
    },
    annuler(action, texte) {
      const f = document.createElement('form');
      f.className = 'snack';
      f.method = 'post';
      f.setAttribute('action', action);
      f.setAttribute('role', 'status');
      f.setAttribute('data-disparait', '');
      const j = document.createElement('input');
      j.type = 'hidden';
      j.name = '_csrf';
      j.value = jeton();
      const s = document.createElement('span');
      s.textContent = texte;
      const b = document.createElement('button');
      b.type = 'submit';
      b.textContent = 'Annuler';
      f.append(j, s, b);
      return messages.poser(f);
    },
  };

  // Les saisies en cours dans un élément remplacé survivent : la valeur pas encore partie, le curseur.
  const saisiesDe = (el) => [...el.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea')]
    .filter((c) => c.value !== c.defaultValue || c === document.activeElement)
    .map((c) => {
      const porteur = c.closest('[id]');
      return { porteur: porteur ? porteur.id : '', nom: c.name, valeur: c.value, change: c.value !== c.defaultValue, focus: c === document.activeElement, debut: c.selectionStart, fin: c.selectionEnd };
    });
  const restaurerSaisies = (saisies) => saisies.forEach((s) => {
    const porteur = s.porteur && document.getElementById(s.porteur);
    const c = porteur && s.nom ? porteur.querySelector('[name="' + s.nom + '"]:not([type=hidden])') : null;
    if (!c) return;
    if (s.change) c.value = s.valeur;
    if (s.focus) {
      c.focus({ preventScroll: true });
      try { c.setSelectionRange(s.debut, s.fin); } catch (e) { /* champ sans sélection */ }
    }
  });

  // Les morceaux reçus remplacent les éléments de même id ; un absent part ; un nouveau se pose après son voisin
  // (data-apres) ; les messages se posent en bas. `sauter(id)` : un geste plus récent attend ce morceau, il l'apportera.
  const appliquer = (html, options = {}) => {
    const recu = new DOMParser().parseFromString('<!doctype html><body>' + html, 'text/html');
    const poses = [];
    [...recu.body.children].forEach((neuf) => {
      if (neuf.matches('template[data-absent]')) {
        const id = neuf.dataset.absent;
        if (!(options.sauter && options.sauter(id))) { const vieux = document.getElementById(id); if (vieux) vieux.remove(); }
        return;
      }
      if (neuf.matches('.snack')) { messages.poser(document.adoptNode(neuf)); return; }
      if (neuf.matches('[data-focus-ligne]')) {
        const champ = document.querySelector('#l' + neuf.dataset.focusLigne + ' input[name=quantite]');
        if (champ) { champ.scrollIntoView({ block: 'center' }); champ.focus(); }
        return;
      }
      if (!neuf.id || (options.sauter && options.sauter(neuf.id))) return;
      const vieux = document.getElementById(neuf.id);
      const pose = document.adoptNode(neuf);
      if (!vieux) {
        const voisin = pose.dataset.apres && document.getElementById(pose.dataset.apres);
        if (voisin) { voisin.after(pose); poses.push(pose); }
        return;
      }
      const saisies = saisiesDe(vieux);
      if (vieux.tagName === 'DETAILS' && vieux.open) pose.open = true;
      vieux.replaceWith(pose);
      restaurerSaisies(saisies);
      poses.push(pose);
    });
    if (poses.length) document.dispatchEvent(new CustomEvent('morceaux-poses', { detail: { elements: poses } }));
    return poses;
  };

  // État du réseau et des envois, dit par un bandeau en haut de l'écran (lot 4 : il lit aussi la file gardée).
  const bandeau = (() => {
    let el = null;
    const poser = (texte, genre, lien) => {
      if (!el) {
        el = document.createElement('div');
        el.className = 'reseau';
        el.setAttribute('role', 'status');
        document.body.prepend(el);
      }
      el.className = 'reseau ' + genre;
      el.replaceChildren(document.createTextNode(texte));
      if (lien) {
        const a = document.createElement('a');
        a.href = lien.href;
        a.textContent = lien.texte;
        el.append(' ', a);
      }
      el.hidden = false;
      document.body.classList.add('avec-reseau');
    };
    const attente = (n) => (n > 1 ? n + ' changements attendent' : n === 1 ? '1 changement attend' : 'rien n’attend');
    return {
      horsReseau: (n) => poser('Hors réseau' + NB + ': ' + attente(n) + ', ils partiront au retour du réseau.', 'hors'),
      session: (n) => poser('Session expirée' + NB + ': ' + attente(n) + '.', 'session', { href: '/connexion', texte: 'Se reconnecter' }),
      envoi: (n) => poser('Envoi de ' + (n > 1 ? n + ' changements' : '1 changement') + '…', 'envoi'),
      page: (texte, lien) => poser(texte, 'hors', lien),
      ok: () => { if (el) { el.hidden = true; document.body.classList.remove('avec-reseau'); } },
    };
  })();

  // La file des gestes : dans l'ordre, un à la fois. `garde` range chaque geste dans le téléphone jusqu'à sa réponse
  // (lot 4) ; sans lui, la file vit le temps de la page.
  const file = [];
  const demandes = new Map(); // id d'un morceau -> nombre de gestes en attente qui le demandent
  let pompe = false;
  let horsReseau = false;
  let sessionPerdue = false;

  // Lot 4 : chaque geste est rangé dans le téléphone (IndexedDB, par compte) jusqu'à sa réponse. Une page fermée, un
  // téléphone éteint ou une session perdue ne le perdent pas : la page suivante le renvoie, avec sa clé. Sans stockage
  // (navigation privée, refus du navigateur), rien ne casse : la file vit le temps de la page.
  const garde = (() => {
    let base = null;
    const ouvrir = () => {
      if (!base) {
        base = new Promise((ok, ko) => {
          if (!window.indexedDB) { ko(new Error('sans IndexedDB')); return; }
          const r = indexedDB.open('offregen-fluide', 1);
          r.onupgradeneeded = () => {
            const s = r.result.createObjectStore('envois', { keyPath: 'cle' });
            s.createIndex('compte', 'compte');
          };
          r.onsuccess = () => ok(r.result);
          r.onerror = () => ko(r.error);
          r.onblocked = () => ko(new Error('base bloquée'));
        });
        base.catch(() => { base = null; });
      }
      return base;
    };
    const faire = (mode, f) => ouvrir().then((db) => new Promise((ok, ko) => {
      const t = db.transaction('envois', mode);
      const demande = f(t.objectStore('envois'));
      t.oncomplete = () => ok(demande ? demande.result : undefined);
      t.onerror = () => ko(t.error);
      t.onabort = () => ko(t.error);
    }));
    const sans = (p, defaut) => p.catch(() => defaut);
    return {
      ranger: (item) => sans(faire('readwrite', (s) => s.put(item)), undefined),
      oublier: (cle) => sans(faire('readwrite', (s) => s.delete(cle)), undefined),
      relire: (c) => sans(faire('readonly', (s) => s.index('compte').getAll(c)), []).then((l) => (l || []).sort((a, b) => a.cree - b.cree)),
      vider: (c) => sans(faire('readwrite', (s) => {
        const r = s.index('compte').openCursor(c);
        r.onsuccess = () => { const cur = r.result; if (cur) { cur.delete(); cur.continue(); } };
        return null;
      }), undefined),
    };
  })();

  const compter = (item, pas) => item.morceaux.forEach((id) => {
    const n = (demandes.get(id) || 0) + pas;
    if (n > 0) demandes.set(id, n); else demandes.delete(id);
  });

  const memeEcran = (item) => item.ici.split('?')[0] === window.location.pathname;
  // La file attend le réseau (ou la session) : chaque geste en file le sait, même derrière un autre (ligne provisoire,
  // fenêtre fermée) ; une seule fois par geste.
  const direAttente = () => file.forEach(({ item, options }) => {
    if (options.attente && !item.attenteDite) { item.attenteDite = true; options.attente(); }
  });

  const poster = async (item, gardee = false) => {
    const ici = memeEcran(item);
    const entetes = {
      'X-Morceaux': ici ? item.morceaux.join(' ') : '',
      'X-Cle-Envoi': item.cle,
      'X-Morceaux-Ici': window.location.pathname + window.location.search,
    };
    if (item.page && ici) entetes['X-Morceaux-Page'] = item.page;
    const corps = new URLSearchParams(item.corps);
    corps.set('_csrf', jeton());
    let r;
    let html;
    // 25 secondes au plus : un envoi pendu (réseau qui ne répond plus) ne bloque pas la file ; il repartira avec sa clé,
    // et le serveur ne le fera qu'une fois s'il l'avait reçu.
    const arret = new AbortController();
    const minuterie = setTimeout(() => arret.abort(), 25000);
    try {
      r = await fetch(item.action, { method: 'POST', body: corps, headers: entetes, credentials: 'same-origin', keepalive: !gardee, signal: arret.signal });
      html = await r.text();
    } catch (e) {
      throw new Refus('reseau', 'Pas de connexion au serveur');
    } finally {
      clearTimeout(minuterie);
    }
    if (r.status === 401 && r.headers.get('X-Morceaux-Session')) throw new Refus('session', 'Session expirée');
    if (r.status === 403 && r.headers.get('X-Morceaux-Jeton')) throw new Refus('jeton', 'Formulaire expiré', 403);
    if (r.status === 409 && /déjà en cours/.test(html)) throw new Refus('reseau', 'Envoi déjà en cours');
    if (!r.ok) throw new Refus('refus', messageDe(html, r.status), r.status, html);
    return { html, ici, adresse: r.headers.get('X-Morceaux-Adresse') || '', aller: r.headers.get('X-Morceaux-Aller') === '1' };
  };

  // Un jeton refusé (session renouvelée ailleurs) : la page du moment en porte un bon.
  const jetonNeuf = async () => {
    const r = await fetch(window.location.pathname + window.location.search, { credentials: 'same-origin', cache: 'no-store' });
    const m = /name="_csrf" value="([^"]+)"/.exec(await r.text());
    if (!m) throw new Error('sans jeton');
    document.querySelectorAll('input[name="_csrf"]').forEach((c) => { c.value = m[1]; });
  };

  const pomper = async () => {
    if (pompe) return;
    pompe = true;
    try {
      while (file.length && !sessionPerdue) {
        const { item, options } = file[0];
        if (navigator.onLine === false) { horsReseau = true; bandeau.horsReseau(file.length); break; }
        if (file.length > 1 || horsReseau) bandeau.envoi(file.length);
        let reponse;
        try {
          try {
            reponse = await poster(item, options.gardee);
          } catch (e) {
            if (e.genre !== 'jeton' || item.jetonRepris) throw e;
            item.jetonRepris = true;
            await jetonNeuf().catch(() => {});
            reponse = await poster(item, options.gardee);
          }
        } catch (e) {
          if (e.genre === 'reseau') {
            horsReseau = true;
            bandeau.horsReseau(file.length);
            direAttente();
            break;
          }
          if (e.genre === 'session') {
            sessionPerdue = true;
            bandeau.session(file.length);
            direAttente();
            break;
          }
          file.shift();
          compter(item, -1);
          await garde.oublier(item.cle);
          if (options.refus) options.refus(e); else messages.dire('Pas enregistré' + NB + ': ' + e.message, true);
          // Ce que montre l'écran redevient ce que dit le serveur.
          if (memeEcran(item)) rafraichir([...item.morceaux, ...(options.aRelire || [])]);
          continue;
        }
        horsReseau = false;
        file.shift();
        compter(item, -1);
        await garde.oublier(item.cle);
        if (reponse.aller && reponse.ici && !options.gardee) {
          window.location.assign(reponse.adresse);
          return;
        }
        if (reponse.ici) appliquer(reponse.html, { sauter: (id) => demandes.has(id) });
        else if (reponse.html) appliquer(reponse.html, { sauter: () => true });
        if (options.apres) options.apres(reponse);
        if (reponse.ici && /^\/(offres|factures)\/\d+/.test(window.location.pathname)) document.dispatchEvent(new Event('offre-modifiee'));
      }
      if (!file.length && !sessionPerdue) bandeau.ok();
    } finally {
      pompe = false;
    }
  };

  // Le réseau revient, l'onglet revient au premier plan, ou toutes les 20 secondes tant qu'un geste attend.
  const relancer = () => { if (file.length && !pompe) { horsReseau = false; pomper(); } };
  window.addEventListener('online', relancer);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') relancer(); });
  setInterval(() => { if (file.length && navigator.onLine !== false) relancer(); }, 20000);
  window.addEventListener('offline', () => { if (file.length) bandeau.horsReseau(file.length); });

  const mettreEnFile = (item, options) => {
    file.push({ item, options });
    compter(item, 1);
    garde.ranger(item).catch(() => {});
    if (navigator.onLine === false || horsReseau || sessionPerdue) {
      if (navigator.onLine === false) horsReseau = true;
      if (sessionPerdue) bandeau.session(file.length); else bandeau.horsReseau(file.length);
      direAttente();
      if (navigator.onLine === false || sessionPerdue) return;
    }
    pomper();
  };

  /**
   * Un geste : l'envoi d'un formulaire, à son tour. options : morceaux (ids demandés en plus du résumé de l'écran,
   * data-resume) ; sansResume ; page (X-Morceaux-Page) ; bouton (le bouton touché, son nom part) ; anticiper()
   * (l'écran change au toucher) ; apres(reponse) ; refus(erreur) ; attente() (pas de réseau, le geste attend).
   */
  const envoyer = (form, options = {}) => {
    const corps = new URLSearchParams(new FormData(form));
    if (options.bouton && options.bouton.name) corps.set(options.bouton.name, options.bouton.value);
    corps.delete('_csrf');
    const item = {
      cle: cleNeuve(),
      action: form.getAttribute('action'),
      corps: corps.toString(),
      morceaux: [...new Set([...(options.morceaux || []), ...(options.sansResume ? [] : resume())])],
      page: options.page || null,
      ici: window.location.pathname + window.location.search,
      compte: compte(),
      cree: Date.now(),
    };
    if (options.anticiper) options.anticiper();
    mettreEnFile(item, options);
    return item;
  };

  // Relire des morceaux de l'écran sans rien écrire (GET avec X-Morceaux) : après un refus, au retour du réseau.
  const rafraichir = (ids) => {
    const tous = [...new Set([...(ids || []), ...resume()])];
    if (!tous.length) return Promise.resolve();
    return fetch(window.location.pathname + window.location.search, { headers: { 'X-Morceaux': tous.join(' ') }, credentials: 'same-origin', cache: 'no-store' })
      .then((r) => (r.ok && !r.headers.get('X-Morceaux-Aller') ? r.text() : Promise.reject(new Error(String(r.status)))))
      .then((html) => { appliquer(html, { sauter: (id) => demandes.has(id) }); })
      .catch(() => {});
  };

  // Ce qui a été retiré de l'écran au toucher, pour « Annuler » : par adresse de remise.
  const retirees = new Map();
  const retirer = (cle, noeuds) => {
    const dernier = noeuds[noeuds.length - 1];
    retirees.set(cle, { noeuds, parent: dernier.parentNode, suivant: dernier.nextElementSibling });
    // Une ligne glissée garde son décalage (glisserLignes) : remise, elle doit revenir droite.
    noeuds.forEach((n) => { n.hidden = true; n.classList.remove('ouverte', 'tire', 'assez'); n.style.removeProperty('--dx'); });
    setTimeout(() => retirees.delete(cle), 120000);
  };
  // Remet ce qui a été retiré à sa place ; faux si la place a disparu (la liste a été remplacée entre-temps) : le serveur
  // renverra alors la liste entière.
  const remettre = (cle) => {
    const r = retirees.get(cle);
    retirees.delete(cle);
    if (!r || !r.parent || !r.parent.isConnected || (r.suivant && !r.suivant.isConnected)) return false;
    r.noeuds.forEach((n) => {
      n.hidden = false;
      if (!n.isConnected) r.parent.insertBefore(n, r.suivant && r.suivant.parentNode === r.parent ? r.suivant : null);
    });
    return true;
  };

  // Les morceaux que touche un geste sur une ligne : elle et ses variantes (rangées .var qui la suivent) ; dans une offre
  // rangée par pièce, le titre de sa pièce, qui porte le sous-total.
  const parPiece = () => !!document.querySelector('#lignes .cat .st');
  const idsDeLaLigne = (row) => {
    if (!row || !row.id) return ['lignes'];
    const ids = [row.id];
    for (let s = row.nextElementSibling; s && s.classList.contains('var'); s = s.nextElementSibling) if (s.id) ids.push(s.id);
    let titre = row.previousElementSibling;
    while (titre && !titre.classList.contains('cat')) titre = titre.previousElementSibling;
    if (titre && titre.querySelector('.st')) {
      if (!titre.id) return ['lignes'];
      ids.push(titre.id);
    }
    return ids;
  };
  const variantesDe = (row) => {
    const v = [];
    for (let s = row.nextElementSibling; s && s.classList.contains('var'); s = s.nextElementSibling) v.push(s);
    return v;
  };
  const nomDeLaLigne = (row) => {
    const b = row.querySelector('button.del');
    const m = b && /^Supprimer la ligne (.*)$/.exec(b.getAttribute('aria-label') || '');
    return m ? m[1] : 'La ligne';
  };

  // Le montant d'une ligne et le total, estimés au toucher (D7) : la ligne, quantité fois prix ; le total, par différence
  // avec le dernier total du serveur (juste pour un seul taux de TVA et des rabais en pour cent). Marqués « provisoire »
  // jusqu'à la réponse du serveur, qui calcule tout (Offre\Totaux).
  const compteDansLeTotal = (row) => {
    if (row.classList.contains('remplacee') || row.querySelector('.tt.offert')) return false;
    if (row.classList.contains('opt') || row.classList.contains('var')) {
      const c = row.querySelector('input[name=retenue][type=checkbox]');
      return !!(c && c.checked);
    }
    return true;
  };
  // Le total change de `delta` centimes au sous-total : il suit dans la même proportion (rabais, TVA), aux 5 centimes.
  // Les deux valeurs estimées gardent la base du serveur (data-serveur) jusqu'à sa réponse.
  const ajusterTotal = (delta) => {
    if (!delta) return;
    const sousTotal = document.querySelector('#totaux .r span:last-child');
    const fixe = document.querySelector('#total-fixe b');
    if (!sousTotal || !fixe) return;
    if (!sousTotal.dataset.provisoire) sousTotal.dataset.provisoire = String(lireMontant(sousTotal.textContent) || 0);
    if (!fixe.dataset.serveur) fixe.dataset.serveur = String(lireMontant(fixe.textContent) || 0);
    const base = Number(sousTotal.dataset.provisoire);
    const ttc = Number(fixe.dataset.serveur);
    sousTotal.dataset.ecart = String(Number(sousTotal.dataset.ecart || 0) + delta);
    const nouveau = base + Number(sousTotal.dataset.ecart);
    const estime = base > 0 ? arrondir(ttc * nouveau / base, 5) : arrondir(nouveau * (1 + 0.081), 5);
    [fixe, document.querySelector('[data-total-bouton]')].forEach((el) => {
      if (el) { el.textContent = 'CHF' + NB + montant(Math.max(0, estime)); el.classList.add('provisoire'); }
    });
  };
  const estimer = (row, montantAvant) => {
    const tt = row.querySelector('.tt');
    const q = lireNombre(row.querySelector('input[name=quantite]') && row.querySelector('input[name=quantite]').value);
    const p = lireNombre(row.querySelector('input[name=prix]') && row.querySelector('input[name=prix]').value);
    if (!tt || tt.classList.contains('offert') || q === null || p === null) return;
    const apres = arrondir(Math.round(q * 1000) * Math.round(p * 100) / 1000, 1);
    tt.textContent = montant(apres);
    tt.classList.add('provisoire');
    if (montantAvant !== null && compteDansLeTotal(row)) ajusterTotal(apres - montantAvant);
  };
  const montantDe = (row) => {
    const tt = row.querySelector('.tt');
    return tt && !tt.classList.contains('offert') ? lireMontant(tt.firstChild ? tt.firstChild.textContent : '') : null;
  };

  /**
   * Un produit ajouté sans réseau (« + Ligne ») : une ligne provisoire au bas de l'offre, le total estimé (D7). Le
   * serveur la remplace par la vraie au retour du réseau (morceau « lignes »).
   */
  const ligneEnAttente = (designation, unite, prixCentimes) => {
    const lignes = document.getElementById('lignes');
    if (!lignes) return;
    const row = document.createElement('div');
    row.className = 'row en-attente';
    const cellule = (balise, classe, texte) => { const el = document.createElement(balise); el.className = classe; el.textContent = texte; return el; };
    const q = cellule('span', 'q fige', '1' + NB);
    q.append(cellule('span', 'u', unite || ''));
    row.append(cellule('span', 'nm', designation), q, cellule('span', 'p fige', prixCentimes > 0 ? montant(prixCentimes) : ''),
      cellule('div', 'tt provisoire', prixCentimes > 0 ? montant(prixCentimes) : ''), document.createElement('span'));
    const note = cellule('div', 'note', '');
    note.append(cellule('span', '', 'En attente du réseau'));
    row.append(note);
    lignes.append(row);
    if (prixCentimes > 0) ajusterTotal(prixCentimes);
  };

  /** Quantité, prix ou choix du client d'une ligne (app.js, corrections) : l'estimation au toucher, l'envoi suit. */
  const ligne = (champ) => {
    const form = champ.form;
    const row = form.closest('.row');
    const caseACocher = champ.type === 'checkbox';
    const avant = row ? montantDe(row) : null;
    envoyer(form, {
      // Retenir une variante décoche les autres de sa ligne et remplace la ligne : toute la liste revient.
      morceaux: caseACocher ? ['lignes'] : idsDeLaLigne(row),
      anticiper: () => { if (!caseACocher && row) estimer(row, avant); },
      refus: (e) => {
        messages.dire('Non enregistré' + NB + ': ' + e.message, true);
        if (caseACocher && champ.isConnected) champ.checked = !champ.checked;
      },
    });
  };

  /** Un texte avant ou après les lignes (app.js, textesOffre). */
  const texte = (form) => envoyer(form, { sansResume: true, morceaux: ['sauvegarde', 'renvoi', 'etat-offre'] });

  /**
   * Un formulaire d'une fenêtre du bas, sur un écran à morceaux (data-resume) : il part à son tour, les morceaux de
   * l'écran derrière se posent. Résout « pose » (fermer la fenêtre), « attente » (pas de réseau : le geste attend) ou
   * {html, code} (refus à montrer dans la fenêtre). `page` : l'écran derrière, quand l'action mène ailleurs (« + Ligne »).
   */
  const fenetre = (form, bouton, page = null, ids = null) => new Promise((ok) => {
    envoyer(form, {
      bouton,
      page,
      morceaux: ids || liste(ecran() && ecran().dataset.fenetre),
      apres: (r) => ok({ etat: 'pose', reponse: r }),
      attente: () => ok({ etat: 'attente' }),
      refus: (e) => ok({ etat: 'refus', html: e.html, code: e.code, message: e.message }),
    });
  });

  // Les gestes de l'écran qui partent sans rechargement : les formulaires de l'offre, du brouillon de facture, de la
  // relecture et de la liste. Ceux d'une fenêtre du bas passent par elle (app.js, fichesEnFenetre).
  const gestePourFormulaire = (form, bouton) => {
    const action = form.getAttribute('action') || '';
    const row = form.closest('#lignes .row, #options .row');
    // « C'est juste » : l'étiquette part au toucher.
    if (row && form.matches('.note.warn')) {
      return () => envoyer(form, {
        morceaux: idsDeLaLigne(row),
        anticiper: () => { form.hidden = true; row.classList.remove('flag'); },
        refus: (e) => { messages.dire('Non enregistré' + NB + ': ' + e.message, true); },
      });
    }
    // La corbeille : la ligne part au toucher, son « Annuler » à elle (deux corbeilles, deux « Annuler »).
    if (row && /\/lignes\/\d+\/supprimer$/.test(action)) {
      return () => {
        const remise = action.replace(/\/supprimer$/, '/restaurer');
        const variantes = variantesDe(row);
        envoyer(form, {
          // Ses variantes passent en options ; le sous-total de sa pièce change ; elle-même reste là, cachée, pour « Annuler ».
          morceaux: idsDeLaLigne(row).filter((id) => id !== row.id),
          aRelire: ['lignes'],
          anticiper: () => {
            const m = montantDe(row);
            if (m !== null && compteDansLeTotal(row)) ajusterTotal(-m);
            retirer(remise, [row, ...variantes]);
            messages.annuler(remise, '«' + NB + nomDeLaLigne(row) + NB + '» supprimée');
          },
          refus: (e) => { remettre(remise); messages.dire('Non supprimée' + NB + ': ' + e.message, true); },
        });
      };
    }
    // Prix dicté : « Prendre », « Mettre au catalogue ? Oui / Non ».
    if (row && form.matches('.note.prix-dit')) {
      return () => envoyer(form, { bouton, morceaux: idsDeLaLigne(row), anticiper: () => form.setAttribute('aria-busy', 'true') });
    }
    return null;
  };

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || (form.getAttribute('method') || '').toLowerCase() !== 'post') return;
    if (form.closest('[data-feuille-page]') || !ecran() || !(ecran().dataset.resume || ecran().dataset.relecture)) return;
    const action = form.getAttribute('action') || '';
    const bouton = e.submitter || null;

    // « Annuler » d'une ligne ou d'une offre : remise à sa place, puis les morceaux du serveur.
    if (form.matches('.snack') && /\/restaurer$/.test(action)) {
      e.preventDefault();
      const ligneId = (/\/lignes\/(\d+)\/restaurer$/.exec(action) || [])[1];
      const r = retirees.get(action);
      const remis = remettre(action);
      if (remis && ligneId && r) {
        const m = montantDe(r.noeuds[0]);
        if (m !== null && compteDansLeTotal(r.noeuds[0])) ajusterTotal(m);
      }
      form.remove();
      messages.empiler();
      let morceaux;
      // Une offre rangée par pièce : le titre de la pièce a pu partir avec sa dernière ligne, la liste revient entière.
      if (ligneId) morceaux = remis && r && !parPiece() ? r.noeuds.map((n) => n.id) : ['lignes'];
      else morceaux = remis ? [] : ['liste-offres'];
      envoyer(form, { morceaux, refus: (err) => messages.dire('Non remise' + NB + ': ' + err.message, true) });
      return;
    }

    const geste = gestePourFormulaire(form, bouton);
    if (geste) { e.preventDefault(); geste(); return; }

    // Liste des offres : archiver ou supprimer en glissant ; la carte part, « Annuler » à elle.
    const carte = form.closest('.glisse[data-glisse]');
    if (carte && /\/offres\/\d+\/(archiver|supprimer)$/.test(action)) {
      e.preventDefault();
      const remise = action.replace(/\/(archiver|supprimer)$/, '/restaurer');
      const titre = carte.querySelector('.titre');
      const fait = /archiver$/.test(action) ? 'archivée' : 'supprimée';
      envoyer(form, {
        anticiper: () => {
          retirer(remise, [carte]);
          messages.annuler(remise, '«' + NB + (titre ? titre.textContent.trim() : 'L’offre') + NB + '» ' + fait);
        },
        refus: (err) => { remettre(remise); messages.dire('Non ' + fait + NB + ': ' + err.message, true); },
      });
      return;
    }
    // Une offre archivée remise dans la liste, un échec de dictée retiré, « Plus tard » des prix.
    const archive = form.closest('#archives .archive');
    if (archive && /\/restaurer$/.test(action)) {
      e.preventDefault();
      envoyer(form, { morceaux: ['liste-offres'], anticiper: () => { archive.hidden = true; } });
      return;
    }
    const echec = form.closest('#preparation .ocard.echec');
    if (echec && /\/retirer$/.test(action)) {
      e.preventDefault();
      envoyer(form, { morceaux: ['preparation'], anticiper: () => { echec.hidden = true; } });
      return;
    }
    if (form.closest('#vos-prix') && action === '/vos-prix/plus-tard') {
      e.preventDefault();
      envoyer(form, { morceaux: ['vos-prix'] });
    }
  });

  // Relecture : toutes les lignes à relire sont dans la page ; « C'est juste » montre la suivante au toucher, l'envoi
  // suit. Refusée (quantité illisible) : retour à cette ligne, avec le message du serveur.
  const relecture = () => {
    if (!ecran() || !ecran().dataset.relecture) return;
    const gestes = document.querySelector('[data-relu-gestes]');
    if (!gestes) return;
    const bouton = gestes.querySelector('button[type=submit]');
    const corriger = gestes.querySelector('[data-relu-corriger]');
    const montrer = (section) => {
      document.querySelectorAll('section[data-relu]').forEach((s) => { s.hidden = s !== section; });
      const fini = !section;
      document.querySelectorAll('[data-relu-fin]').forEach((el) => { el.hidden = !fini; });
      gestes.hidden = fini;
      if (section) {
        bouton.setAttribute('form', section.querySelector('form').id);
        if (corriger) corriger.href = section.dataset.corriger;
      }
      window.scrollTo({ top: 0 });
    };
    document.addEventListener('submit', (e) => {
      const form = e.target;
      const section = form.closest && form.closest('section[data-relu]');
      if (!section || e.defaultPrevented) return;
      e.preventDefault();
      let suivante = section.nextElementSibling;
      while (suivante && !suivante.matches('section[data-relu]')) suivante = suivante.nextElementSibling;
      envoyer(form, {
        sansResume: true,
        anticiper: () => montrer(suivante || null),
        refus: (err) => { montrer(section); messages.dire('Pas enregistré' + NB + ': ' + err.message, true); },
      });
    });
  };

  // Lot 3 : trier les offres et choisir une catégorie du catalogue dans le navigateur, sans requête (toutes les offres et
  // tous les produits sont déjà à l'écran). L'adresse suit : rechargée, la page garde le même tri.
  const alphabet = new Intl.Collator('fr-CH', { sensitivity: 'base' });
  const ordres = {
    recent: () => 0,
    client: (a, b) => (!a.dataset.client) - (!b.dataset.client) || alphabet.compare(a.dataset.client || '', b.dataset.client || ''),
    montant: (a, b) => Number(b.dataset.montant || 0) - Number(a.dataset.montant || 0),
    relire: (a, b) => Number(b.dataset.relire || 0) - Number(a.dataset.relire || 0),
  };
  const trier = (lien) => {
    const conteneur = document.getElementById('liste-offres');
    const cle = new URL(lien.href, window.location.href).searchParams.get('tri') || 'recent';
    if (!conteneur || !ordres[cle]) return false;
    const cartes = [...conteneur.querySelectorAll(':scope > .glisse')];
    cartes.sort((a, b) => ordres[cle](a, b) || Number(a.dataset.rang) - Number(b.dataset.rang)).forEach((c) => conteneur.append(c));
    return true;
  };
  const choisirCategorie = (lien) => {
    const produits = document.getElementById('clist');
    const champ = document.querySelector('input[data-filtre="#clist"]');
    if (!produits || !champ) return false;
    const cat = new URL(lien.href, window.location.href).searchParams.get('cat') || '';
    produits.querySelectorAll('.crow').forEach((r) => r.toggleAttribute('data-hors-cat', cat !== '' && r.dataset.cat !== cat));
    // La recherche du catalogue repart avec la catégorie, « Nouveau produit » la propose.
    const form = champ.form;
    let cache = form && form.querySelector('input[name=cat]');
    if (form && cat && !cache) { cache = document.createElement('input'); cache.type = 'hidden'; cache.name = 'cat'; form.prepend(cache); }
    if (cache) { if (cat) cache.value = cat; else cache.remove(); }
    const nouveau = document.querySelector('.actionbar a[href^="/catalogue/nouveau"]');
    if (nouveau) nouveau.setAttribute('href', '/catalogue/nouveau' + (cat ? '?cat=' + encodeURIComponent(cat) : ''));
    champ.dispatchEvent(new Event('input'));
    return true;
  };
  document.addEventListener('click', (e) => {
    const lien = e.target.closest && e.target.closest('nav.fchips.tri a[href], #categories a[href]');
    if (!lien || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (!(lien.closest('.tri') ? trier(lien) : choisirCategorie(lien))) return;
    e.preventDefault();
    lien.parentNode.querySelectorAll('a').forEach((a) => { if (a === lien) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current'); });
    const u = new URL(lien.href, window.location.href);
    const champ = document.querySelector('input[data-filtre="#clist"]');
    if (champ && champ.value.trim()) u.searchParams.set('q', champ.value.trim()); else u.searchParams.delete('q');
    history.replaceState(history.state, '', u.pathname + u.search);
  });

  // Lot 1 : un formulaire envoyé de façon classique ne part pas deux fois ; ses boutons se bloquent jusqu'à la page
  // suivante. Après le choix du bouton : le nom du bouton touché part encore avec le formulaire.
  window.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || (form.getAttribute('method') || '').toLowerCase() !== 'post') return;
    if (form.dataset.envoiEnCours) { e.preventDefault(); return; }
    form.dataset.envoiEnCours = '1';
    setTimeout(() => form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => { b.disabled = true; b.setAttribute('aria-busy', 'true'); }), 0);
  });
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-envoi-en-cours]').forEach((f) => {
      delete f.dataset.envoiEnCours;
      f.querySelectorAll('button[aria-busy]').forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
    });
  });

  // La page s'en va avec des gestes pas encore partis : ils partent quand même (keepalive), chacun avec sa clé ; la file
  // gardée (lot 4) les renvoie à la page suivante, le serveur ne les fait qu'une fois.
  window.addEventListener('pagehide', () => {
    file.slice(pompe ? 1 : 0).forEach(({ item }) => {
      const corps = new URLSearchParams(item.corps);
      corps.set('_csrf', jeton());
      try {
        fetch(item.action, { method: 'POST', body: corps, keepalive: true, credentials: 'same-origin', headers: { 'X-Morceaux': '', 'X-Cle-Envoi': item.cle } });
      } catch (e) { /* trop gros pour keepalive : la file gardée le renverra */ }
    });
  });

  // Lot 4 : les gestes rangés par une page précédente repartent, dans l'ordre, avec leur clé.
  const reprendre = async () => {
    const c = compte();
    if (!c) return;
    const gardes = await garde.relire(c);
    const connus = new Set(file.map((f) => f.item.cle));
    gardes.filter((item) => !connus.has(item.cle)).forEach((item) => {
      file.push({ item, options: { gardee: true } });
      compter(item, 1);
    });
    if (file.length) { if (navigator.onLine === false) bandeau.horsReseau(file.length); else pomper(); }
  };

  // Lot 4 : le service worker garde les fichiers et les pages (public/sw.js). Ce que le téléphone garde d'avance (D6) :
  // la liste, le catalogue, les clients et les offres modifiées depuis 30 jours, quand la connexion est bonne et que
  // l'artisan ne fait rien (D4) ; au plus une fois par heure, et seulement les offres qui ont changé.
  const connexionBonne = () => {
    const c = navigator.connection;
    return navigator.onLine !== false && !(c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || '')));
  };
  const prechargerLesPages = (sw) => {
    const cartes = [...document.querySelectorAll('#liste-offres .glisse[data-modifiee]')];
    if (!cartes.length && window.location.pathname !== '/') return;
    let derniere = 0;
    try { derniere = Number(localStorage.getItem('offregen-garde-' + compte()) || 0); } catch (e) { /* sans stockage : chaque ouverture */ }
    if (Date.now() - derniere < 3600000 || !connexionBonne()) return;
    const depuis = Date.now() - 30 * 86400000;
    // La liste elle-même : la première page s'est chargée avant que le service worker la voie.
    const pages = [{ adresse: '/', version: '' }, { adresse: '/catalogue', version: '' }, { adresse: '/clients', version: '' }]
      .concat(cartes.filter((c) => Date.parse(c.dataset.modifiee.replace(' ', 'T') + 'Z') >= depuis)
        .map((c) => ({ adresse: '/offres/' + c.id.slice(1), version: c.dataset.modifiee })));
    const lancer = () => {
      if (occupe() || document.visibilityState !== 'visible') { setTimeout(lancer, 5000); return; }
      sw.postMessage({ type: 'garder', pages });
      try { localStorage.setItem('offregen-garde-' + compte(), String(Date.now())); } catch (e) { /* idem */ }
    };
    (window.requestIdleCallback || ((f) => setTimeout(f, 3000)))(() => setTimeout(lancer, 2000), { timeout: 10000 });
  };
  const serviceWorker = () => {
    if (!('serviceWorker' in navigator) || !compte()) return;
    navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).then(() => navigator.serviceWorker.ready).then((r) => {
      if (!r.active) return;
      r.active.postMessage({ type: 'compte', compte: compte() });
      prechargerLesPages(r.active);
    }).catch(() => { /* sans service worker : l'application marche, sans copie hors réseau */ });
  };

  // Déconnexion : ce que le téléphone garde pour ce compte part (pages, gestes en attente) ; les dictées à l'abri restent
  // (sous-chantier 27 : elles partent quand leur offre est prête). Un geste encore en attente se perdrait : on le dit.
  const oublierTout = () => {
    const c = compte();
    if (c) garde.vider(c);
    if (window.caches) caches.keys().then((noms) => noms.filter((n) => n.startsWith('offregen-pages-')).forEach((n) => caches.delete(n))).catch(() => {});
    if (navigator.serviceWorker && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ type: 'oublier' });
    try { sessionStorage.setItem('offregen-deconnexion', '1'); } catch (e) { /* le service worker efface aussi à l'écran de connexion */ }
  };
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.getAttribute('action') !== '/deconnexion' || e.defaultPrevented) return;
    if (file.length && !window.confirm((file.length > 1 ? file.length + ' changements attendent' : 'Un changement attend') + ' encore le réseau. Se déconnecter les perd. Se déconnecter quand même ?')) {
      e.preventDefault();
      return;
    }
    oublierTout();
  }, true);

  document.addEventListener('DOMContentLoaded', () => {
    relecture();
    // Les bandeaux posés par le serveur avec la page s'empilent aussi.
    messages.empiler();
    // L'écran de connexion après une déconnexion : ce qui restait du compte part (la navigation a pu couper l'effacement).
    let deconnecte = false;
    try { deconnecte = sessionStorage.getItem('offregen-deconnexion') === '1'; } catch (e) { /* sans stockage */ }
    if (deconnecte && !compte()) {
      try { sessionStorage.removeItem('offregen-deconnexion'); } catch (e) { /* idem */ }
      if (window.indexedDB) indexedDB.deleteDatabase('offregen-fluide');
    }
    // Une page servie par le service worker, sans réseau : elle le dit, et propose la page à jour au retour du réseau.
    if (document.body.dataset.gardeLe) {
      const le = new Date(document.body.dataset.gardeLe);
      const quand = Number.isNaN(le.getTime()) ? '' : ' du ' + le.toLocaleDateString('fr-CH') + ' à ' + le.toLocaleTimeString('fr-CH', { hour: '2-digit', minute: '2-digit' });
      bandeau.page('Hors réseau' + NB + ': page gardée dans le téléphone' + quand + '.');
      window.addEventListener('online', () => bandeau.page('Le réseau est revenu.', { href: window.location.pathname + window.location.search, texte: 'Mettre la page à jour' }));
    }
    reprendre();
    serviceWorker();
  });

  // Un geste attend ou part encore : le préchargement attend (D4), le banc aussi.
  const occupe = () => pompe || file.length > 0;

  return { envoyer, appliquer, rafraichir, ligne, texte, fenetre, ligneEnAttente, messages, garde, bandeau, file, occupe, retirer, remettre, NB, PC, jeton, compte, montant, lireMontant };
})();
// Une constante du script n'est pas une propriété de window : app.js teste window.fluide pour savoir s'il est là.
window.fluide = fluide;
