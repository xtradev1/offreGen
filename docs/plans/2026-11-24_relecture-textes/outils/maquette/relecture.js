// Page de relecture des textes de Dicta (chantier 2026-11-24_relecture-textes).
// Trois colonnes par texte : l'original, la version du correcteur (toujours modifiable), les différences.
// À gauche, l'aperçu en direct de l'écran : sa copie figée, où chaque texte suit la version du correcteur.
// Les textes viennent du bloc de données #donnees, tels que l'application les affiche : rien ne les retouche ici.
// Les corrections restent dans le navigateur du correcteur (localStorage) ; « Copier mes corrections » les rassemble.
(function () {
  'use strict';
  var CLE = 'dicta-relecture-textes-v1';
  var INSECABLE = ' ';
  var FINE = ' ';
  var donnees = JSON.parse(document.getElementById('donnees').textContent);
  var etat = lire();
  etat.textes = etat.textes || {};
  etat.ecrans = etat.ecrans || {};

  function lire() {
    try { return JSON.parse(localStorage.getItem(CLE) || '{}') || {}; } catch (e) { return {}; }
  }
  function garder() {
    try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) { /* navigation privée : la page marche, sans mémoire */ }
    compter();
  }
  function el(tag, classe, texte) {
    var e = document.createElement(tag);
    if (classe) { e.className = classe; }
    if (texte !== undefined) { e.textContent = texte; }
    return e;
  }

  // ---------- Différences, mot à mot ; chaque espace est un élément à part ----------
  function morceaux(s) {
    return s.match(/[\p{L}\p{N}]+|\s|[^\s\p{L}\p{N}]/gu) || [];
  }
  // Plus longue sous-suite commune : les textes font quelques dizaines de mots, le tableau reste petit.
  function differences(a, b) {
    var x = morceaux(a), y = morceaux(b), n = x.length, m = y.length, i, j;
    var t = [];
    for (i = 0; i <= n; i++) { t.push(new Array(m + 1).fill(0)); }
    for (i = n - 1; i >= 0; i--) {
      for (j = m - 1; j >= 0; j--) { t[i][j] = x[i] === y[j] ? t[i + 1][j + 1] + 1 : Math.max(t[i + 1][j], t[i][j + 1]); }
    }
    var sortie = [];
    function pousser(sorte, mot) {
      var der = sortie[sortie.length - 1];
      if (der && der.sorte === sorte) { der.mots.push(mot); } else { sortie.push({ sorte: sorte, mots: [mot] }); }
    }
    i = 0; j = 0;
    while (i < n && j < m) {
      if (x[i] === y[j]) { pousser('egal', x[i]); i++; j++; }
      else if (t[i + 1][j] >= t[i][j + 1]) { pousser('retrait', x[i]); i++; }
      else { pousser('ajout', y[j]); j++; }
    }
    while (i < n) { pousser('retrait', x[i++]); }
    while (j < m) { pousser('ajout', y[j++]); }
    return sortie;
  }
  // Une espace dans un ajout ou un retrait se voit : ␣ espace normale, ⍽ insécable.
  function ecrireMots(parent, mots, marquer) {
    mots.forEach(function (mot) {
      if (mot === INSECABLE || mot === FINE) {
        var s = el('span', 'nb' + (marquer ? ' signe' : ''), marquer ? '⍽' : mot);
        s.title = mot === FINE ? 'Espace fine insécable' : 'Espace insécable';
        parent.appendChild(s);
      } else if (marquer && /^\s$/.test(mot)) {
        var e = el('span', 'signe', mot === '\n' ? '↵' : '␣');
        e.title = mot === '\n' ? 'Retour à la ligne' : 'Espace';
        parent.appendChild(e);
      } else {
        parent.appendChild(document.createTextNode(mot));
      }
    });
  }
  function dessinerDifferences(cible, avant, apres) {
    cible.textContent = '';
    if (apres === avant) {
      cible.appendChild(el('span', 'inchange', 'Inchangé'));
      return;
    }
    differences(avant, apres).forEach(function (bloc) {
      if (bloc.sorte === 'egal') { ecrireMots(cible, bloc.mots, false); return; }
      var balise = el(bloc.sorte === 'ajout' ? 'ins' : 'del');
      ecrireMots(balise, bloc.mots, true);
      cible.appendChild(balise);
    });
  }

  // Le champ grandit avec son texte (propriété de style posée par le script : la CSP du site l'accepte).
  function ajuster(champ) {
    champ.style.height = 'auto';
    champ.style.height = (champ.scrollHeight + 2) + 'px';
  }
  // Ctrl+Maj+Espace pose une espace insécable, comme dans Word.
  function raccourcis(champ) {
    champ.addEventListener('keydown', function (ev) {
      if (ev.key === ' ' && ev.ctrlKey && ev.shiftKey) {
        ev.preventDefault();
        var d = champ.selectionStart, f = champ.selectionEnd;
        champ.value = champ.value.slice(0, d) + INSECABLE + champ.value.slice(f);
        champ.selectionStart = champ.selectionEnd = d + 1;
        champ.dispatchEvent(new Event('input'));
      }
    });
  }

  // ---------- Aperçu en direct : la copie figée de l'écran, dans un cadre, suit les corrections ----------
  var apercus = [];
  var originaux = new WeakMap();
  // Le texte d'un élément de la copie est remplacé par la version du correcteur. Les icônes et les blocs qui portent
  // d'autres textes restent à leur place ; le reste du texte devient la nouvelle version.
  function remplacer(cible, texte, original) {
    if (cible.tagName === 'INPUT' || cible.tagName === 'TEXTAREA') {
      if (!originaux.has(cible)) { originaux.set(cible, cible.getAttribute('placeholder') || ''); }
      var ph = texte === original ? originaux.get(cible) : texte.replace(/^Exemple dans le champ\s?:\s?/, '');
      cible.setAttribute('placeholder', ph);
      cible.classList.toggle('rl-change', texte !== original);
      return;
    }
    if (!originaux.has(cible)) { originaux.set(cible, cible.innerHTML); }
    cible.innerHTML = originaux.get(cible);
    // Le texte numéroté peut porter le nom de sa partie (courriels : « Titre : ») que l'écran n'affiche pas.
    var affiche = cible.textContent.replace(/[ \t\n\r]+/g, ' ').trim();
    var debut = original.indexOf(affiche);
    if (affiche && debut > 0 && texte.indexOf(original.slice(0, debut)) === 0) { texte = texte.slice(debut); }
    cible.classList.toggle('rl-change', texte !== original);
    if (texte === original) { return; }
    var avant = [], apres = [], vu = false;
    Array.prototype.slice.call(cible.childNodes).forEach(function (n) {
      var garder = n.nodeType === 1 && (n.textContent.trim() === '' || n.hasAttribute('data-t') || n.querySelector('[data-t]'));
      if (garder) { (vu ? apres : avant).push(n); } else if (n.textContent.trim() !== '') { vu = true; }
      cible.removeChild(n);
    });
    avant.forEach(function (n) { cible.appendChild(n); });
    cible.appendChild(cible.ownerDocument.createTextNode(texte));
    apres.forEach(function (n) { cible.appendChild(n); });
  }
  function majApercus(numero, texte, original) {
    apercus.forEach(function (a) {
      if (!a.doc) { return; }
      a.doc.querySelectorAll('[data-t="' + numero + '"]').forEach(function (c) { remplacer(c, texte, original); });
    });
  }
  // L'élément du texte en cours d'édition est entouré, et amené en vue dans l'aperçu de son écran.
  function montrer(numero, ecranId, oui) {
    apercus.forEach(function (a) {
      if (!a.doc) { return; }
      a.doc.querySelectorAll('[data-t="' + numero + '"]').forEach(function (c, k) {
        c.classList.toggle('rl-actif', oui);
        if (oui && k === 0 && a.ecran.id === ecranId) {
          var r = c.getBoundingClientRect();
          var y = (r.top - a.ecran.apercu.decalage) * a.echelle;
          a.cadre.scrollTop = Math.max(0, y - a.cadre.clientHeight / 3);
        }
      });
    });
  }
  function dimensionner(a) {
    var ap = a.ecran.apercu;
    var largeurDispo = a.cadre.clientWidth || ap.largeur;
    a.echelle = Math.min(1, largeurDispo / ap.largeur);
    a.boite.style.width = Math.round(ap.largeur * a.echelle) + 'px';
    a.boite.style.height = Math.round(ap.hauteurVue * a.echelle) + 'px';
    a.iframe.style.width = ap.largeur + 'px';
    a.iframe.style.height = ap.hauteurPage + 'px';
    a.iframe.style.transform = 'translateY(' + (-ap.decalage * a.echelle) + 'px) scale(' + a.echelle + ')';
  }
  function chargerApercu(a, repli) {
    fetch(donnees.ecrans + a.ecran.apercu.fichier + '.txt').then(function (r) {
      if (!r.ok) { throw new Error(String(r.status)); }
      return r.text();
    }).then(function (html) {
      var d = a.iframe.contentDocument;
      d.open();
      d.write(html);
      d.close();
      a.doc = d;
      Object.keys(etat.textes).forEach(function (numero) {
        var c = etat.textes[numero];
        d.querySelectorAll('[data-t="' + numero + '"]').forEach(function (x) { remplacer(x, c.apres, c.avant); });
      });
      dimensionner(a);
    }).catch(function () { repli(); });
  }
  var observateur = 'IntersectionObserver' in window ? new IntersectionObserver(function (entrees) {
    entrees.forEach(function (en) {
      if (!en.isIntersecting) { return; }
      observateur.unobserve(en.target);
      var a = apercus.filter(function (x) { return x.cadre === en.target; })[0];
      if (a) { a.charger(); }
    });
  }, { rootMargin: '1200px 0px' }) : null;

  function ligneTexte(t, ecran, ecranId) {
    var li = el('li', 'texte');
    li.id = t.n;
    li.appendChild(el('span', 'num', t.n));

    var original = el('div', 'colonne original');
    original.appendChild(el('span', 'etiquette-col', 'Original'));
    var p = el('p');
    ecrireMots(p, morceaux(t.t), false);
    original.appendChild(p);

    var version = el('div', 'colonne version');
    var id = 'v-' + t.n;
    var label = el('label', 'etiquette-col', 'Votre version');
    label.htmlFor = id;
    var champ = el('textarea');
    champ.id = id;
    champ.rows = 1;
    champ.spellcheck = true;
    champ.lang = 'fr-CH';
    champ.value = etat.textes[t.n] ? etat.textes[t.n].apres : t.t;
    version.appendChild(label);
    version.appendChild(champ);

    var evolution = el('div', 'colonne evolution');
    evolution.appendChild(el('span', 'etiquette-col', 'Différences'));
    var diff = el('p', 'diff');
    diff.setAttribute('aria-live', 'polite');
    evolution.appendChild(diff);
    var annuler = el('button', 'revenir', 'Revenir à l’original');
    annuler.type = 'button';
    evolution.appendChild(annuler);

    function maj() {
      var change = champ.value !== t.t;
      if (change) { etat.textes[t.n] = { avant: t.t, apres: champ.value, ecran: ecran }; } else { delete etat.textes[t.n]; }
      li.classList.toggle('change', change);
      annuler.hidden = !change;
      dessinerDifferences(diff, t.t, champ.value);
      majApercus(t.n, champ.value, t.t);
    }
    champ.addEventListener('focus', function () { montrer(t.n, ecranId, true); });
    champ.addEventListener('blur', function () { montrer(t.n, ecranId, false); });
    champ.addEventListener('input', function () { maj(); ajuster(champ); garder(); });
    annuler.addEventListener('click', function () { champ.value = t.t; maj(); ajuster(champ); garder(); champ.focus(); });
    raccourcis(champ);
    maj();

    li.appendChild(original);
    li.appendChild(version);
    li.appendChild(evolution);
    return li;
  }

  function blocEcran(e) {
    var art = el('article', 'ecran' + (e.large ? ' large' : ''));
    art.id = 'e-' + e.id;
    var tete = el('header', 'ecran-tete');
    tete.appendChild(el('h3', '', e.titre));
    if (e.contexte) { tete.appendChild(el('p', 'contexte', e.contexte)); }
    if (e.note) { tete.appendChild(el('p', 'note', e.note)); }
    art.appendChild(tete);

    var fig = el('figure', 'capture');
    var img = el('img');
    img.src = donnees.images + e.image;
    img.alt = e.titre;
    img.loading = 'lazy';
    var legende = el('figcaption', '', e.titre);
    if (donnees.ecrans && e.apercu) {
      // L'aperçu en direct ; la capture d'origine reste à un clic, dans la visionneuse du kit.
      var cadre = el('div', 'apercu');
      var boite = el('div', 'apercu-boite');
      var iframe = el('iframe');
      iframe.title = 'Aperçu en direct : ' + e.titre;
      iframe.tabIndex = -1;
      iframe.setAttribute('aria-hidden', 'true');
      boite.appendChild(iframe);
      cadre.appendChild(boite);
      fig.appendChild(cadre);
      img.className = 'origine';
      fig.appendChild(img);
      var voir = el('button', 'voir-origine', 'Voir la capture d’origine');
      voir.type = 'button';
      voir.addEventListener('click', function () { img.click(); });
      legende.textContent = 'Aperçu en direct\u00A0: ' + e.titre;
      fig.appendChild(legende);
      fig.appendChild(voir);
      var a = { ecran: e, cadre: cadre, boite: boite, iframe: iframe, doc: null, echelle: 1 };
      a.charger = function () {
        chargerApercu(a, function () { cadre.remove(); voir.remove(); img.className = ''; legende.textContent = e.titre; });
      };
      apercus.push(a);
      if (observateur) { observateur.observe(cadre); } else { a.charger(); }
    } else {
      fig.appendChild(img);
      fig.appendChild(legende);
    }
    art.appendChild(fig);

    var corps = el('div', 'corps');
    var titres = el('div', 'titres-col');
    titres.setAttribute('aria-hidden', 'true');
    ['', 'Original', 'Votre version', 'Différences'].forEach(function (t) { titres.appendChild(el('span', '', t)); });
    corps.appendChild(titres);
    var ol = el('ol', 'textes');
    e.textes.forEach(function (t) { ol.appendChild(ligneTexte(t, e.titre, e.id)); });
    corps.appendChild(ol);
    if (e.deja) {
      corps.appendChild(el('p', 'deja', e.deja + (e.deja > 1 ? ' autres textes de cet écran sont déjà plus haut.' : ' autre texte de cet écran est déjà plus haut.')));
    }
    var rem = el('div', 'remarque');
    var id = 'r-' + e.id;
    var label = el('label', '', 'Remarque sur cet écran (facultatif)');
    label.htmlFor = id;
    var champ = el('textarea');
    champ.id = id;
    champ.value = etat.ecrans[e.id] || '';
    champ.addEventListener('input', function () { if (champ.value.trim()) { etat.ecrans[e.id] = champ.value; } else { delete etat.ecrans[e.id]; } garder(); });
    rem.appendChild(label);
    rem.appendChild(champ);
    corps.appendChild(rem);
    art.appendChild(corps);
    return art;
  }

  var racine = document.getElementById('ecrans');
  donnees.parties.forEach(function (p) {
    var sec = el('section', 'partie');
    var h2 = el('h2', '', p.titre);
    h2.id = p.id;
    sec.appendChild(h2);
    sec.appendChild(el('p', 'intro', p.intro));
    p.ecrans.forEach(function (e) { sec.appendChild(blocEcran(e)); });
    racine.appendChild(sec);
  });
  // Hauteur des champs une fois la page en place, et quand la largeur change.
  function toutAjuster() { document.querySelectorAll('.version textarea').forEach(ajuster); }
  toutAjuster();
  // La police du site arrive après le premier calcul : elle est plus large que celle de secours, les lignes changent.
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(toutAjuster); }
  window.addEventListener('load', toutAjuster);
  var minuterie;
  window.addEventListener('resize', function () { clearTimeout(minuterie); minuterie = setTimeout(function () { toutAjuster(); apercus.forEach(function (a) { if (a.doc) { dimensionner(a); } }); }, 150); });

  var nom = document.getElementById('nom');
  nom.value = etat.nom || '';
  nom.addEventListener('input', function () { etat.nom = nom.value; garder(); });
  document.getElementById('voir-nb').addEventListener('change', function (ev) { document.body.classList.toggle('voir-nb', ev.target.checked); });

  var titresEcrans = {};
  donnees.parties.forEach(function (p) { p.ecrans.forEach(function (e) { titresEcrans[e.id] = e.titre; }); });
  function visible(s) { return s.replace(/ /g, '[insécable]').replace(/ /g, '[fine]'); }

  function texteExport() {
    var lignes = ['Relecture des textes de Dicta' + (etat.nom ? ', par ' + etat.nom : ''), ''];
    Object.keys(etat.textes).sort().forEach(function (n) {
      var c = etat.textes[n];
      lignes.push(n + ' (' + c.ecran + ')', '  Avant : ' + visible(c.avant), '  Après : ' + visible(c.apres), '');
    });
    Object.keys(etat.ecrans).forEach(function (id) {
      lignes.push('Remarque sur « ' + (titresEcrans[id] || id) + ' » :', '  ' + etat.ecrans[id].replace(/\n/g, '\n  '), '');
    });
    return lignes.join('\n');
  }
  function compter() {
    var t = Object.keys(etat.textes).length, r = Object.keys(etat.ecrans).length;
    var parties = [];
    if (t) { parties.push(t + (t > 1 ? ' textes modifiés' : ' texte modifié')); }
    if (r) { parties.push(r + (r > 1 ? ' remarques' : ' remarque')); }
    document.getElementById('compte').textContent = parties.length ? parties.join(', ') : 'Aucune correction pour l’instant';
  }
  document.getElementById('copier').addEventListener('click', function () {
    var texte = texteExport();
    var zone = document.getElementById('export-texte');
    document.getElementById('export').hidden = false;
    zone.value = texte;
    var etatCopie = document.getElementById('etat-copie');
    function repli() { zone.focus(); zone.select(); etatCopie.textContent = 'Le texte est sélectionné en bas de la page : copiez-le avec Ctrl+C.'; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function () { etatCopie.textContent = 'Copié. Collez-le dans un courriel.'; }, repli);
    } else { repli(); }
  });
  compter();
})();
