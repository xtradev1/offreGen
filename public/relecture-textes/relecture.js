// Page de relecture des textes de Dicta (chantier 2026-11-24_relecture-textes).
// Les textes viennent du bloc de données #donnees, tels que l'application les affiche : rien ne les retouche ici.
// Les corrections restent dans le navigateur du correcteur (localStorage) ; « Copier mes corrections » les rassemble.
(function () {
  'use strict';
  var CLE = 'dicta-relecture-textes-v1';
  var donnees = JSON.parse(document.getElementById('donnees').textContent);
  var etat = lire();

  function lire() {
    try { return JSON.parse(localStorage.getItem(CLE) || '{}') || {}; } catch (e) { return {}; }
  }
  function garder() {
    try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) { /* navigation privée : la page marche, sans mémoire */ }
    compter();
  }
  etat.textes = etat.textes || {};
  etat.ecrans = etat.ecrans || {};

  function el(tag, classe, texte) {
    var e = document.createElement(tag);
    if (classe) { e.className = classe; }
    if (texte !== undefined) { e.textContent = texte; }
    return e;
  }
  // Les espaces insécables restent tels quels ; marqués, ils se surlignent quand le correcteur le demande.
  function avecInsecables(parent, texte) {
    texte.split(/([  ])/).forEach(function (morceau) {
      if (morceau === ' ' || morceau === ' ') { parent.appendChild(el('span', 'nb', morceau)); }
      else if (morceau) { parent.appendChild(document.createTextNode(morceau)); }
    });
  }

  function ligneTexte(t) {
    var li = el('li', 'texte');
    li.id = t.n;
    li.appendChild(el('span', 'num', t.n));
    var p = el('p', 'original');
    avecInsecables(p, t.t);
    li.appendChild(p);
    var bouton = el('button', 'corriger', 'Corriger');
    bouton.type = 'button';
    bouton.setAttribute('aria-expanded', 'false');
    li.appendChild(bouton);
    var zone = null;

    function ouvrir(valeur) {
      if (zone) { return; }
      zone = el('div', 'zone');
      var id = 'c-' + t.n;
      var label = el('label', '', 'Votre version de ' + t.n + ', ou une remarque');
      label.htmlFor = id;
      var champ = el('textarea');
      champ.id = id;
      champ.value = valeur;
      champ.addEventListener('input', function () { etat.textes[t.n] = { avant: t.t, apres: champ.value, ecran: t.ecran }; garder(); });
      var retirer = el('button', 'retirer', 'Retirer ma correction');
      retirer.type = 'button';
      retirer.addEventListener('click', function () { delete etat.textes[t.n]; garder(); zone.remove(); zone = null; li.classList.remove('corrige'); bouton.textContent = 'Corriger'; bouton.setAttribute('aria-expanded', 'false'); bouton.focus(); });
      zone.appendChild(label);
      zone.appendChild(champ);
      zone.appendChild(retirer);
      li.appendChild(zone);
      li.classList.add('corrige');
      bouton.textContent = 'Corrigé';
      bouton.setAttribute('aria-expanded', 'true');
      return champ;
    }
    bouton.addEventListener('click', function () {
      if (zone) { zone.querySelector('textarea').focus(); return; }
      var champ = ouvrir(t.t);
      etat.textes[t.n] = { avant: t.t, apres: t.t, ecran: t.ecran };
      garder();
      champ.focus();
    });
    if (etat.textes[t.n]) { ouvrir(etat.textes[t.n].apres); }
    return li;
  }

  function blocEcran(e) {
    var art = el('article', 'ecran' + (e.large ? ' large' : ''));
    art.id = 'e-' + e.id;
    var fig = el('figure');
    var img = el('img');
    img.src = donnees.images + e.image;
    img.alt = e.titre;
    img.loading = 'lazy';
    fig.appendChild(img);
    fig.appendChild(el('figcaption', '', e.titre));
    var col = el('div');
    col.appendChild(el('h3', '', e.titre));
    if (e.contexte) { col.appendChild(el('p', 'contexte', e.contexte)); }
    if (e.note) { col.appendChild(el('p', 'note', e.note)); }
    var ol = el('ol', 'textes');
    e.textes.forEach(function (t) { t.ecran = e.titre; ol.appendChild(ligneTexte(t)); });
    col.appendChild(ol);
    if (e.deja) {
      col.appendChild(el('p', 'deja', e.deja + (e.deja > 1 ? ' autres textes de cet écran sont déjà numérotés plus haut.' : ' autre texte de cet écran est déjà numéroté plus haut.')));
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
    col.appendChild(rem);
    art.appendChild(fig);
    art.appendChild(col);
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

  var nom = document.getElementById('nom');
  nom.value = etat.nom || '';
  nom.addEventListener('input', function () { etat.nom = nom.value; garder(); });
  document.getElementById('voir-nb').addEventListener('change', function (ev) { document.body.classList.toggle('voir-nb', ev.target.checked); });

  var titres = {};
  donnees.parties.forEach(function (p) { p.ecrans.forEach(function (e) { titres[e.id] = e.titre; }); });

  function texteExport() {
    var lignes = ['Relecture des textes de Dicta' + (etat.nom ? ', par ' + etat.nom : ''), ''];
    Object.keys(etat.textes).sort().forEach(function (n) {
      var c = etat.textes[n];
      if (c.apres === c.avant) { return; }
      lignes.push(n + ' (' + c.ecran + ')', '  Avant : ' + c.avant, '  Après : ' + c.apres, '');
    });
    Object.keys(etat.ecrans).forEach(function (id) {
      lignes.push('Remarque sur « ' + (titres[id] || id) + ' » :', '  ' + etat.ecrans[id].replace(/\n/g, '\n  '), '');
    });
    return lignes.join('\n');
  }
  function nombre() {
    var n = Object.keys(etat.textes).filter(function (k) { return etat.textes[k].apres !== etat.textes[k].avant; }).length;
    return n + Object.keys(etat.ecrans).length;
  }
  function compter() {
    var n = nombre();
    document.getElementById('compte').textContent = n === 0 ? 'Aucune correction pour l’instant' : n + (n > 1 ? ' corrections et remarques' : ' correction ou remarque');
  }
  document.getElementById('copier').addEventListener('click', function () {
    var texte = texteExport();
    var zone = document.getElementById('export-texte');
    var bloc = document.getElementById('export');
    zone.value = texte;
    bloc.hidden = false;
    var etatCopie = document.getElementById('etat-copie');
    function repli() { zone.focus(); zone.select(); etatCopie.textContent = 'Le texte est sélectionné en bas de la page : copiez-le avec Ctrl+C.'; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function () { etatCopie.textContent = 'Copié. Collez-le dans un courriel.'; }, repli);
    } else { repli(); }
  });
  compter();
})();
