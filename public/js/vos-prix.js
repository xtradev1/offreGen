// « Vos prix » (docs/plans/2026-11-19_metiers-demo, lot 10). Sans ce script, l'écran marche : chaque bouton envoie
// le formulaire et la page suivante se charge. Avec lui, sur téléphone :
// - le clavier s'ouvre dès qu'on touche « Mettre mes prix » ou « Compléter » : l'écran arrive sans changer de page ;
// - le produit suivant arrive sans recharger la page, et le champ du prix garde le curseur : le clavier reste ouvert ;
// - glisser la carte vers la gauche fait « Passer », et rien d'autre ;
// - le message « Annuler » s'efface seul après 5 secondes, et « Annuler » ne recharge pas la page non plus.
// Chargé par l'écran lui-même, et par « Mes offres » et le catalogue quand ils portent le bandeau ou le rappel.
// Tout est écouté sur le document : la carte peut arriver après le chargement de la page.
(() => {
  'use strict';
  if (!window.fetch || !window.DOMParser) return;
  const ZONES = ['nom', 'unite', 'sous', 'haut'];
  const carte = () => document.querySelector('form[data-vos-prix]');
  const champDe = (form) => form.querySelector('input[name="prix"]');
  const lire = (reponse) => reponse.text().then((html) => ({ reponse, doc: new DOMParser().parseFromString(html, 'text/html') }));
  let envoi = false;

  // Les messages de la page reçue remplacent ceux de l'écran.
  const messages = (doc) => {
    document.querySelectorAll('.app > .snack').forEach((s) => s.remove());
    const bas = document.querySelector('.app > .bas');
    if (!bas) return;
    doc.querySelectorAll('.app > .snack').forEach((s) => {
      const message = document.importNode(s, true);
      bas.before(message);
      setTimeout(() => message.remove(), 5000);
    });
  };

  // D'une carte à la suivante : le champ reste le même élément, il garde le curseur et le clavier.
  // Les boutons s'appellent « action » : form.action désignerait ces boutons, d'où getAttribute et setAttribute.
  const remplacer = (doc, adresse) => {
    const form = carte();
    const nouveau = doc.querySelector('form[data-vos-prix]');
    const nouveauChamp = nouveau && champDe(nouveau);
    const jeton = nouveau && nouveau.querySelector('input[name="_csrf"]');
    if (!form || !nouveau || !nouveauChamp || !jeton || ZONES.some((z) => !nouveau.querySelector(`[data-zone="${z}"]`))) return false;
    const champ = champDe(form);
    form.setAttribute('action', nouveau.getAttribute('action'));
    form.querySelector('input[name="_csrf"]').value = jeton.value;
    for (const z of ZONES) {
      form.querySelector(`[data-zone="${z}"]`).innerHTML = nouveau.querySelector(`[data-zone="${z}"]`).innerHTML;
    }
    const avance = document.querySelector('[data-zone="avance"]');
    const nouvelleAvance = doc.querySelector('[data-zone="avance"]');
    if (avance && nouvelleAvance) avance.innerHTML = nouvelleAvance.innerHTML;
    champ.value = nouveauChamp.value;
    champ.setAttribute('aria-label', nouveauChamp.getAttribute('aria-label') || '');
    champ.closest('label').className = nouveauChamp.closest('label').className;
    document.title = doc.title;
    messages(doc);
    if (adresse) history.replaceState(null, '', adresse);
    champ.focus();
    return true;
  };

  // Depuis un autre écran (« Mes offres », le catalogue, le bilan) : le bandeau, l'écran et le bas de la page
  // reçue prennent la place de ceux de la page, sans navigation.
  const installer = (doc, adresse) => {
    const parties = ['.app > header.bar', '.app > main.screen', '.app > .bas'];
    const ici = parties.map((s) => document.querySelector(s));
    const recu = parties.map((s) => doc.querySelector(s));
    if (!doc.querySelector('form[data-vos-prix]') || ici.some((e) => !e) || recu.some((e) => !e)) return false;
    ici[1].className = recu[1].className;
    ici.forEach((e, i) => { e.innerHTML = recu[i].innerHTML; });
    document.title = doc.title;
    messages(doc);
    history.pushState(null, '', adresse);
    window.scrollTo(0, 0);
    return true;
  };
  // Retour du navigateur après une arrivée sans navigation : la page de l'adresse se recharge.
  window.addEventListener('popstate', () => location.reload());

  // iPhone : le clavier ne s'ouvre que pendant un toucher. Un champ provisoire le prend tout de suite ; le champ
  // du prix le lui reprend quand la carte est là.
  const ouvrir = (adresse) => {
    const provisoire = document.createElement('input');
    provisoire.setAttribute('inputmode', 'decimal');
    provisoire.tabIndex = -1;
    Object.assign(provisoire.style, { position: 'fixed', top: '0', left: '0', width: '1px', height: '1px', opacity: '0', border: '0', padding: '0', fontSize: '16px' });
    document.body.append(provisoire);
    provisoire.focus();
    fetch(adresse, { credentials: 'same-origin' })
      .then(lire)
      .then(({ reponse, doc }) => {
        if (!reponse.ok || !installer(doc, reponse.url)) { location.assign(adresse); return; }
        champDe(carte()).focus();
        provisoire.remove();
      })
      .catch(() => location.assign(adresse));
  };

  document.addEventListener('click', (e) => {
    if (!(e.target instanceof Element) || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const lien = e.target.closest('a[href="/vos-prix"]');
    if (lien) {
      e.preventDefault();
      ouvrir(lien.href);
      return;
    }
    // Toucher la carte hors d'un bouton rend le curseur au champ.
    const form = carte();
    if (form && form.contains(e.target) && !e.target.closest('button')) champDe(form).focus();
  });

  // Toucher un bouton de la carte ou du message ne retire pas le curseur du champ.
  document.addEventListener('mousedown', (e) => {
    if (carte() && e.target instanceof Element && e.target.closest('form[data-vos-prix] button, .snack button')) e.preventDefault();
  });

  // Repli : l'envoi ordinaire du formulaire, page rechargée.
  const envoyerSansScript = (form, action) => {
    if (action) {
      const cache = document.createElement('input');
      cache.type = 'hidden';
      cache.name = 'action';
      cache.value = action;
      form.append(cache);
    }
    form.dataset.natif = '1';
    form.submit();
  };

  // $action : le geste de la carte ; null pour « Annuler », envoyé par le formulaire du message.
  const envoyer = (form, action) => {
    if (envoi) return;
    envoi = true;
    const corps = new FormData(form);
    if (action) corps.set('action', action);
    fetch(form.getAttribute('action'), { method: 'POST', body: corps, credentials: 'same-origin' })
      .then(lire)
      .then(({ reponse, doc }) => {
        if (remplacer(doc, reponse.redirected ? reponse.url : null)) return;
        // Le bilan, ou une page inattendue (session finie) : la page entière.
        if (reponse.redirected) location.assign(reponse.url);
        else location.reload();
      })
      .catch(() => envoyerSansScript(form, action))
      .finally(() => { envoi = false; });
  };

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.natif) return;
    if (form.hasAttribute('data-vos-prix')) {
      e.preventDefault();
      const bouton = e.submitter instanceof HTMLButtonElement && e.submitter.name === 'action' ? e.submitter : null;
      envoyer(form, bouton ? bouton.value : 'suivant');
    } else if (form.getAttribute('action') === '/vos-prix/annuler' && carte()) {
      e.preventDefault();
      envoyer(form, null);
    }
  });

  // Glisser à gauche : « Passer ». Un geste raté ne doit ni enregistrer un prix ni retirer un produit.
  let glisse = null;
  document.addEventListener('touchstart', (e) => {
    const form = carte();
    if (!form || e.touches.length !== 1 || !(e.target instanceof Node) || !form.contains(e.target)) return;
    glisse = { form, x: e.touches[0].clientX, y: e.touches[0].clientY, ecart: 0 };
    form.classList.remove('vp-glisse');
  }, { passive: true });
  document.addEventListener('touchmove', (e) => {
    if (!glisse) return;
    const dx = e.touches[0].clientX - glisse.x;
    const dy = e.touches[0].clientY - glisse.y;
    if (Math.abs(dy) > Math.abs(dx)) return;
    glisse.ecart = dx;
    glisse.form.style.transform = dx < 0 ? `translateX(${dx}px)` : '';
  }, { passive: true });
  const finDuGeste = (annule) => {
    if (!glisse) return;
    const { form, ecart } = glisse;
    glisse = null;
    form.classList.add('vp-glisse');
    form.style.transform = '';
    if (!annule && ecart < -80) envoyer(form, 'passer');
  };
  document.addEventListener('touchend', () => finDuGeste(false));
  document.addEventListener('touchcancel', () => finDuGeste(true));
})();
