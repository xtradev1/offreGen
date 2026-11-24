/* Kit maquette (skill « maquette »). Sans dépendance, injecté en ligne avant </body>.
 * 1. Zoom des images : un clic ouvre l'image en grand dans la page ; jamais de navigation vers
 *    le fichier. Deuxième clic : taille réelle, la souris ou le doigt déplace l'image.
 *    ← → passent à l'image voisine, Échap ou un clic à côté ferme.
 * 2. Sommaire : chaque <h2 id> alimente une barre de navigation collante (#mk-toc).
 * 3. Avant / après : <div class="mk-compare"> avec deux <figure> ; boutons Avant, Après, Côte à côte.
 * 4. Filtres : <div class="mk-filters" data-target="#liste"> et boutons data-v ; les éléments
 *    [data-v] de la cible s'affichent selon le bouton pressé (data-v="all" : tout).
 * 5. Thème : bouton clair / sombre (#mk-theme), mémorisé dans le navigateur quand c'est possible.
 * 6. Retour en haut.
 */
(function () {
  'use strict';
  var doc = document;

  /* ---------- 1. Zoom ---------- */
  var IMG_LINK = /\.(png|jpe?g|gif|webp|avif|svg)(\?.*)?$/i;
  function zoomables() {
    return Array.prototype.filter.call(doc.querySelectorAll('img'), function (i) {
      return !i.closest('#mk-lightbox') && !i.hasAttribute('data-no-zoom');
    });
  }
  var box = doc.createElement('div');
  box.id = 'mk-lightbox';
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.setAttribute('aria-label', 'Image agrandie');
  box.hidden = true;
  box.innerHTML = '<div class="mk-lb-bar"><span class="mk-lb-count"></span><span class="mk-lb-cap"></span>' +
    '<button type="button" class="mk-lb-btn" data-act="prev" aria-label="Image précédente">←</button>' +
    '<button type="button" class="mk-lb-btn" data-act="next" aria-label="Image suivante">→</button>' +
    '<button type="button" class="mk-lb-btn" data-act="real" aria-label="Taille réelle">1:1</button>' +
    '<button type="button" class="mk-lb-btn" data-act="close" aria-label="Fermer">✕</button></div>' +
    '<div class="mk-lb-stage"><img alt=""></div>';
  var stage, big, cap, count, list = [], idx = 0, opener = null;
  function srcOf(el) {
    var a = el.closest('a[href]');
    if (a && IMG_LINK.test(a.getAttribute('href'))) { return a.getAttribute('href'); }
    return el.getAttribute('data-full') || el.currentSrc || el.src;
  }
  function captionOf(el) {
    var fig = el.closest('figure');
    var fc = fig && fig.querySelector('figcaption');
    return (fc && fc.textContent.trim()) || el.getAttribute('alt') || '';
  }
  function show(i) {
    idx = (i + list.length) % list.length;
    var el = list[idx];
    box.classList.remove('is-real');
    big.src = srcOf(el);
    big.alt = el.getAttribute('alt') || '';
    cap.textContent = captionOf(el);
    count.textContent = list.length > 1 ? (idx + 1) + ' / ' + list.length : '';
    stage.scrollTop = 0; stage.scrollLeft = 0;
  }
  function open(el) {
    list = zoomables().filter(function (i) { return i.offsetParent !== null || i === el; });
    var i = list.indexOf(el);
    if (i < 0) { list.push(el); i = list.length - 1; }
    opener = el;
    box.hidden = false;
    doc.documentElement.classList.add('mk-lb-open');
    show(i);
    box.querySelector('[data-act="close"]').focus();
  }
  function close() {
    box.hidden = true;
    doc.documentElement.classList.remove('mk-lb-open');
    big.removeAttribute('src');
    if (opener && opener.focus) { opener.focus({ preventScroll: true }); }
  }
  function initZoom() {
    doc.body.appendChild(box);
    stage = box.querySelector('.mk-lb-stage');
    big = stage.querySelector('img');
    cap = box.querySelector('.mk-lb-cap');
    count = box.querySelector('.mk-lb-count');
    zoomables().forEach(function (img) {
      img.classList.add('mk-zoomable');
      if (!img.hasAttribute('tabindex') && !img.closest('a,button')) { img.setAttribute('tabindex', '0'); }
    });
    // Capture : on passe avant tout lien qui entoure l'image, pour ne jamais quitter la page.
    doc.addEventListener('click', function (e) {
      var img = e.target.closest && e.target.closest('img.mk-zoomable');
      var a = !img && e.target.closest && e.target.closest('a[href]');
      if (a && IMG_LINK.test(a.getAttribute('href')) && !a.hasAttribute('data-no-zoom')) {
        img = a.querySelector('img.mk-zoomable');
        if (!img) {
          e.preventDefault();
          var tmp = doc.createElement('img'); tmp.src = a.getAttribute('href'); tmp.alt = a.textContent.trim();
          list = [tmp]; opener = a; box.hidden = false; doc.documentElement.classList.add('mk-lb-open'); show(0);
          return;
        }
      }
      if (img) { e.preventDefault(); e.stopPropagation(); open(img); }
    }, true);
    doc.addEventListener('keydown', function (e) {
      if (!box.hidden) {
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowRight') { show(idx + 1); }
        else if (e.key === 'ArrowLeft') { show(idx - 1); }
        return;
      }
      var t = e.target;
      if ((e.key === 'Enter' || e.key === ' ') && t.classList && t.classList.contains('mk-zoomable')) { e.preventDefault(); open(t); }
    });
    box.addEventListener('click', function (e) {
      var act = e.target.closest('[data-act]');
      if (act) {
        var a = act.getAttribute('data-act');
        if (a === 'close') { close(); } else if (a === 'next') { show(idx + 1); }
        else if (a === 'prev') { show(idx - 1); } else if (a === 'real') { box.classList.toggle('is-real'); }
        return;
      }
      if (e.target === big) { box.classList.toggle('is-real'); return; }
      if (e.target === stage || e.target === box) { close(); }
    });
    // Glisser pour déplacer l'image en taille réelle.
    var drag = null;
    stage.addEventListener('pointerdown', function (e) {
      if (!box.classList.contains('is-real')) { return; }
      drag = { x: e.clientX, y: e.clientY, l: stage.scrollLeft, t: stage.scrollTop, moved: false };
    });
    stage.addEventListener('pointermove', function (e) {
      if (!drag) { return; }
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (Math.abs(dx) + Math.abs(dy) > 4) { drag.moved = true; }
      stage.scrollLeft = drag.l - dx; stage.scrollTop = drag.t - dy;
    });
    stage.addEventListener('pointerup', function (e) {
      if (drag && drag.moved) { e.preventDefault(); stage.addEventListener('click', function stop(ev) { ev.stopPropagation(); stage.removeEventListener('click', stop, true); }, true); }
      drag = null;
    });
  }

  /* ---------- 2. Sommaire ---------- */
  function initToc() {
    var toc = doc.getElementById('mk-toc');
    if (!toc || toc.children.length) { return; }
    Array.prototype.forEach.call(doc.querySelectorAll('h2[id]'), function (h) {
      var a = doc.createElement('a'); a.href = '#' + h.id; a.textContent = h.getAttribute('data-toc') || h.textContent.trim();
      toc.appendChild(a);
    });
  }

  /* ---------- 3. Avant / après ---------- */
  function initCompare() {
    Array.prototype.forEach.call(doc.querySelectorAll('.mk-compare'), function (c) {
      if (c.querySelector('.mk-compare-bar')) { return; }
      var bar = doc.createElement('div'); bar.className = 'mk-compare-bar'; bar.setAttribute('role', 'group');
      [['before', 'Avant'], ['after', 'Après'], ['both', 'Côte à côte']].forEach(function (m) {
        var b = doc.createElement('button'); b.type = 'button'; b.textContent = m[1]; b.dataset.mode = m[0];
        b.setAttribute('aria-pressed', m[0] === 'both' ? 'true' : 'false');
        bar.appendChild(b);
      });
      c.insertBefore(bar, c.firstChild);
      c.dataset.mode = 'both';
      bar.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) { return; }
        c.dataset.mode = b.dataset.mode;
        Array.prototype.forEach.call(bar.children, function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      });
    });
  }

  /* ---------- 4. Filtres ---------- */
  function initFilters() {
    Array.prototype.forEach.call(doc.querySelectorAll('.mk-filters[data-target]'), function (f) {
      f.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-v]'); if (!b) { return; }
        Array.prototype.forEach.call(f.querySelectorAll('button[data-v]'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        var target = doc.querySelector(f.getAttribute('data-target'));
        if (!target) { return; }
        Array.prototype.forEach.call(target.querySelectorAll('[data-v]'), function (el) {
          el.hidden = !(b.dataset.v === 'all' || el.dataset.v === b.dataset.v);
        });
      });
    });
  }

  /* ---------- 5. Thème et 6. retour en haut ---------- */
  function store(k, v) { try { if (v === undefined) { return localStorage.getItem(k); } localStorage.setItem(k, v); } catch (e) { return null; } return null; }
  function initChrome() {
    var saved = store('mk-theme');
    if (saved) { doc.documentElement.setAttribute('data-theme', saved); }
    var tools = doc.createElement('div'); tools.className = 'mk-tools';
    tools.innerHTML = '<button type="button" id="mk-theme" aria-label="Changer de thème">◐</button>' +
      '<a href="#" id="mk-top" aria-label="Retour en haut">↑</a>';
    doc.body.appendChild(tools);
    tools.querySelector('#mk-theme').addEventListener('click', function () {
      var cur = doc.documentElement.getAttribute('data-theme') ||
        (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      var next = cur === 'dark' ? 'light' : 'dark';
      doc.documentElement.setAttribute('data-theme', next); store('mk-theme', next);
    });
  }

  function init() { initZoom(); initToc(); initCompare(); initFilters(); initChrome(); }
  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
