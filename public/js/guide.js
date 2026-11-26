// Le guide à anneau (chantier onboarding, maquettes v2 à v6 validées par Xavier) : un anneau terracotta qui pulse
// sur ce qu'il faut toucher, une bulle qui dit quoi faire, un pas à chaque toucher.
//
// Une page déclare les étapes qu'elle porte :
//   <div class="guide" hidden data-guide="artisan" data-total="5" data-fin="/guide/fini" data-csrf="…">
//     <template data-n="1" data-cible="sélecteur" data-cible-pc="sélecteur à 1024 px et plus">texte de la bulle</template>
//   </div>
// Marques d'une étape :
//   data-forme="rond"     anneau rond, si la cible est à peu près carrée ;
//   data-bulle            « haut », « bas » ou « tout-haut » (en haut de l'écran) ; sinon, du côté libre ;
//   data-sans-voile       l'anneau seul, sans assombrir l'écran ;
//   data-suivant          la cible se regarde : la bulle porte « Suivant » ;
//   data-dernier          la bulle porte « Compris » et le guide se termine ;
//   data-ouvre="sél."     le toucher sur la cible ouvre cet élément à la place de son action (les conseils) ;
//   data-relance          le toucher sur la cible ferme ce qui a été ouvert et rejoue la cible de data-ouvre ;
//   data-pastilles="a|b"  des pastilles 1, 2, 3 sur ces éléments, la bulle en bas de l'écran ;
//   data-facultative      sans cible après une seconde, la bulle seule ;
//   data-exacte           à l'ouverture d'une page, seulement si c'est l'étape en cours.
// Le numéro de l'étape en cours suit l'artisan d'un écran à l'autre (sessionStorage) : chaque page montre la
// première de ses étapes qui n'est pas encore faite.
(function () {
  'use strict';
  var zone = document.querySelector('[data-guide]');
  if (!zone) { return; }
  var cle = 'guide-' + zone.getAttribute('data-guide');
  var total = +zone.getAttribute('data-total');
  var etapes = Array.prototype.slice.call(zone.querySelectorAll('template[data-n]'));
  var anneau = null, bulle = null, cible = null, suivi = 0, pastilles = [], intercepter = null, relancer = null;

  function lire() { try { return +(sessionStorage.getItem(cle) || 1); } catch (e) { return 1; } }
  function garder(n) { try { sessionStorage.setItem(cle, String(n)); } catch (e) { /* navigation privée : le guide repart de sa page */ } }
  function oublier() { try { sessionStorage.removeItem(cle); } catch (e) { /* rien à oublier */ } }

  // « Revoir le guide » (POST /guide/revoir) ramène ici avec ?guide=depart : le guide repart de sa première étape.
  if (/[?&]guide=depart\b/.test(window.location.search)) {
    garder(1);
    window.history.replaceState(null, '', window.location.pathname);
  }

  function etape(n) { return etapes.filter(function (e) { return +e.getAttribute('data-n') === n; })[0]; }

  function visible(el) {
    if (!el || el.closest('[hidden]')) { return false; }
    var r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
  }
  // Les sélecteurs séparés par des virgules se lisent dans l'ordre : le premier qui trouve une cible visible gagne.
  function trouver(selecteur) {
    var parts = selecteur.split(',');
    for (var p = 0; p < parts.length; p++) {
      var liste = document.querySelectorAll(parts[p]);
      for (var i = 0; i < liste.length; i++) { if (visible(liste[i])) { return liste[i]; } }
    }
    return null;
  }

  function retirer() {
    if (anneau) { anneau.remove(); anneau = null; }
    if (bulle) { bulle.remove(); bulle = null; }
    pastilles.forEach(function (p) { p.el.remove(); });
    pastilles = [];
    if (cible) { cible.removeEventListener('click', toucher, true); cible = null; }
    if (intercepter) { document.removeEventListener('click', intercepter, true); intercepter = null; }
    window.cancelAnimationFrame(suivi);
  }

  function terminer(etat) {
    retirer();
    oublier();
    document.querySelectorAll('[data-guide-conseils]').forEach(function (c) { c.hidden = true; });
    var d = new FormData();
    d.append('_csrf', zone.getAttribute('data-csrf'));
    d.append('etat', etat);
    return fetch(zone.getAttribute('data-fin'), { method: 'POST', body: d, credentials: 'same-origin', keepalive: true }).catch(function () { /* le guide reviendra à la prochaine ouverture */ });
  }

  function placer(t) {
    pastilles.forEach(function (p) {
      var r = p.cible.getBoundingClientRect();
      p.el.style.left = (r.left - 10) + 'px';
      p.el.style.top = (r.top - 10) + 'px';
    });
    if (!cible || !anneau) { return; }
    var r = cible.getBoundingClientRect(), m = 6;
    anneau.style.left = (r.left - m) + 'px';
    anneau.style.top = (r.top - m) + 'px';
    anneau.style.width = (r.width + 2 * m) + 'px';
    anneau.style.height = (r.height + 2 * m) + 'px';
    // Sur grand écran, la bulle se pose à côté de sa cible plutôt qu'au milieu de la page.
    if (window.innerWidth > 600) {
      bulle.style.right = 'auto';
      bulle.style.margin = '0';
      bulle.style.left = Math.max(16, Math.min(r.left, window.innerWidth - bulle.offsetWidth - 16)) + 'px';
    }
    var sens = t.getAttribute('data-bulle') || (r.top + r.height / 2 > window.innerHeight / 2 ? 'haut' : 'bas');
    if (sens === 'tout-haut') {
      bulle.style.bottom = '';
      bulle.style.top = '16px';
    } else if (sens === 'haut') {
      bulle.style.top = '';
      bulle.style.bottom = Math.max(8, window.innerHeight - r.top + m + 12) + 'px';
    } else {
      bulle.style.bottom = '';
      // Jamais sous le bas de l'écran : sur un petit téléphone, la bulle remonte sur sa cible plutôt que de sortir.
      bulle.style.top = Math.max(8, Math.min(r.bottom + m + 12, window.innerHeight - bulle.offsetHeight - 8)) + 'px';
    }
  }

  function suivre(t) {
    placer(t);
    suivi = window.requestAnimationFrame(function () { suivre(t); });
  }

  function avancer(n) {
    garder(n + 1);
    retirer();
    attendre(n + 1, 0, null);
  }

  // Toucher sur la cible : elle fait son travail (ouvrir la feuille, terminer la dictée) ; l'étape suivante l'attend.
  function toucher() {
    var n = lire(), t = etape(n);
    if (t && t.hasAttribute('data-relance')) {
      var ouvert = cible.closest('[data-guide-conseils]');
      if (ouvert) { ouvert.hidden = true; }
      var micro = relancer;
      garder(n + 1);
      retirer();
      if (micro) { micro.click(); }
      attendre(n + 1, 0, null);
      return;
    }
    if (t && t.hasAttribute('data-dernier')) { terminer('fini'); return; }
    avancer(n);
  }

  // La cible de l'étape n peut tarder (feuille qui monte, autorisation du micro) : 30 secondes d'attente. Faute de
  // cible, « repli » reprend la première étape de la page (une page rechargée au milieu de la dictée).
  function attendre(n, essais, repli) {
    var t = etape(n);
    if (!t) { return; }
    var sel = window.innerWidth >= 1024 && t.getAttribute('data-cible-pc') ? t.getAttribute('data-cible-pc') : t.getAttribute('data-cible');
    var c = sel ? trouver(sel) : null;
    if (sel && !c) {
      // Une cible facultative (une ligne « à vérifier » que l'offre n'a peut-être pas) : la bulle seule, après 1 seconde.
      if (t.hasAttribute('data-facultative') && essais >= 10) { montrer(t, null); return; }
      if (essais < (repli === null ? 300 : 20)) { window.setTimeout(function () { attendre(n, essais + 1, repli); }, 100); }
      else if (repli !== null && repli !== n) { attendre(repli, 0, null); }
      return;
    }
    montrer(t, c);
  }

  function bouton(classe, texte, action) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = classe;
    b.textContent = texte;
    b.addEventListener('click', action);
    return b;
  }

  function montrer(t, c) {
    retirer();
    var n = +t.getAttribute('data-n');
    garder(n);
    var dernier = t.hasAttribute('data-dernier');
    bulle = document.createElement('div');
    bulle.className = 'guide-bulle';
    bulle.setAttribute('role', 'dialog');
    bulle.setAttribute('aria-live', 'polite');
    var pas = document.createElement('span');
    pas.className = 'guide-pas';
    pas.textContent = 'Guide · ' + n + ' sur ' + total;
    var texte = document.createElement('div');
    texte.className = 'guide-texte';
    texte.appendChild(t.content.cloneNode(true));
    var bas = document.createElement('div');
    bas.className = 'guide-bas';
    bas.appendChild(bouton('guide-quitter', 'Quitter le guide', function () { terminer('quitte'); }));
    if (t.hasAttribute('data-suivant') || dernier || !c) {
      bas.appendChild(bouton('guide-suite', dernier ? 'Compris' : 'Suivant', function () { if (dernier) { terminer('fini'); } else { avancer(n); } }));
    }
    bulle.appendChild(pas);
    bulle.appendChild(texte);
    bulle.appendChild(bas);
    document.body.appendChild(bulle);

    if (t.getAttribute('data-pastilles')) {
      t.getAttribute('data-pastilles').split('|').forEach(function (sel, i) {
        var e = trouver(sel);
        if (!e) { return; }
        var p = document.createElement('span');
        p.className = 'guide-pastille';
        p.textContent = String(i + 1);
        p.setAttribute('aria-hidden', 'true');
        document.body.appendChild(p);
        pastilles.push({ el: p, cible: e });
      });
    }
    if (!c) {
      bulle.classList.add(t.getAttribute('data-pastilles') ? 'en-bas' : 'centre');
      if (pastilles.length) { suivre(t); }
      return;
    }
    cible = c;
    anneau = document.createElement('div');
    var rc = c.getBoundingClientRect();
    // Un rond seulement autour d'une cible à peu près carrée : sur PC, le micro devient un bouton long.
    anneau.className = 'guide-anneau' + (t.getAttribute('data-forme') === 'rond' && Math.abs(rc.width - rc.height) < 12 ? ' rond' : '') + (t.hasAttribute('data-sans-voile') ? ' sans-voile' : '');
    anneau.setAttribute('aria-hidden', 'true');
    document.body.appendChild(anneau);
    if (t.getAttribute('data-ouvre')) {
      // Le premier toucher du micro montre d'abord les conseils ; « Commencer la dictée » le rejouera.
      var declencheur = c.closest('[data-enregistrer]') || c;
      intercepter = function (e) {
        if (!declencheur.contains(e.target)) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        relancer = declencheur;
        var o = document.querySelector(t.getAttribute('data-ouvre'));
        if (o) { o.hidden = false; }
        avancer(n);
      };
      document.addEventListener('click', intercepter, true);
    } else if (!t.hasAttribute('data-suivant') && !dernier) {
      cible.addEventListener('click', toucher, true);
    }
    // Une cible de la page cachée sous les barres fixes du bas (actions, menu) remonte au milieu de l'écran : sinon
    // l'anneau entourerait le bouton de la barre qui la recouvre.
    var barres = document.querySelector('.bas');
    var limite = barres && !barres.contains(c) ? barres.getBoundingClientRect().top : window.innerHeight;
    if (rc.bottom > limite || rc.top < 0) { c.scrollIntoView({ block: 'center' }); }
    suivre(t);
  }

  // Les conseils ouverts, la touche D du PC (app.js) ne lance pas la dictée par-dessus : « Commencer la dictée » le fait.
  window.addEventListener('keydown', function (e) {
    if ((e.key === 'd' || e.key === 'D') && document.querySelector('[data-guide-conseils]:not([hidden])')) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  // La première étape de cette page qui n'est pas encore faite ; repli sur la première de la page.
  var en = lire();
  // « data-exacte » : cette étape ne se montre que si c'est elle qui est en cours (« Revenir à mon espace » après la ligne
  // à vérifier, jamais à sa place).
  var ici = etapes.filter(function (e) { var n = +e.getAttribute('data-n'); return e.hasAttribute('data-exacte') ? n === en : n >= en; })[0];
  var premiere = etapes[0] ? +etapes[0].getAttribute('data-n') : null;
  if (ici) { attendre(+ici.getAttribute('data-n'), 0, premiere); }
})();
