// « Vos prix » (docs/plans/2026-11-19_metiers-demo, lot 10). Sans ce script, l'écran marche : chaque bouton envoie
// le formulaire et la page suivante se charge. Avec lui, sur téléphone :
// - le produit suivant arrive sans recharger la page, et le champ du prix garde le curseur : le clavier reste ouvert ;
// - glisser la carte vers la gauche fait « Passer », et rien d'autre ;
// - le message « Annuler » s'efface seul après 5 secondes.
(() => {
  'use strict';
  const form = document.querySelector('form[data-vos-prix]');
  if (!form || !window.fetch || !window.DOMParser) return;
  const champ = form.querySelector('input[name="prix"]');
  const ZONES = ['nom', 'unite', 'sous', 'haut'];
  let envoi = false;

  // Toucher un bouton ne retire pas le curseur du champ.
  form.addEventListener('mousedown', (e) => {
    if (e.target instanceof Element && e.target.closest('button')) e.preventDefault();
  });

  // Les boutons s'appellent « action » : form.action désignerait ces boutons, d'où getAttribute et setAttribute.
  const remplacer = (doc, adresse) => {
    const nouveau = doc.querySelector('form[data-vos-prix]');
    const nouveauChamp = nouveau && nouveau.querySelector('input[name="prix"]');
    const jeton = nouveau && nouveau.querySelector('input[name="_csrf"]');
    if (!nouveau || !nouveauChamp || !jeton || ZONES.some((z) => !nouveau.querySelector(`[data-zone="${z}"]`))) return false;
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
    // Les messages de la page reçue remplacent ceux de l'écran.
    document.querySelectorAll('.app > .snack').forEach((s) => s.remove());
    const bas = document.querySelector('.app > .bas');
    doc.querySelectorAll('.app > .snack').forEach((s) => {
      const message = document.importNode(s, true);
      bas.before(message);
      setTimeout(() => message.remove(), 5000);
    });
    if (adresse) history.replaceState(null, '', adresse);
    champ.focus();
    return true;
  };

  // Repli : l'envoi ordinaire du formulaire, page rechargée.
  const envoyerSansScript = (action) => {
    const cache = document.createElement('input');
    cache.type = 'hidden';
    cache.name = 'action';
    cache.value = action;
    form.append(cache);
    form.dataset.natif = '1';
    form.submit();
  };

  const envoyer = (action) => {
    if (envoi) return;
    envoi = true;
    const corps = new FormData(form);
    corps.set('action', action);
    fetch(form.getAttribute('action'), { method: 'POST', body: corps, credentials: 'same-origin' })
      .then((reponse) => reponse.text().then((html) => ({ reponse, html })))
      .then(({ reponse, html }) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        if (remplacer(doc, reponse.redirected ? reponse.url : null)) return;
        // Le bilan, ou une page inattendue (session finie) : la page entière.
        if (reponse.redirected) location.assign(reponse.url);
        else location.reload();
      })
      .catch(() => envoyerSansScript(action))
      .finally(() => { envoi = false; });
  };

  form.addEventListener('submit', (e) => {
    if (form.dataset.natif) return;
    e.preventDefault();
    const bouton = e.submitter instanceof HTMLButtonElement && e.submitter.name === 'action' ? e.submitter : null;
    envoyer(bouton ? bouton.value : 'suivant');
  });

  // Glisser à gauche : « Passer ». Un geste raté ne doit ni enregistrer un prix ni retirer un produit.
  let departX = null;
  let departY = 0;
  let ecart = 0;
  form.addEventListener('touchstart', (e) => {
    if (e.touches.length !== 1) return;
    departX = e.touches[0].clientX;
    departY = e.touches[0].clientY;
    ecart = 0;
    form.classList.remove('vp-glisse');
  }, { passive: true });
  form.addEventListener('touchmove', (e) => {
    if (departX === null) return;
    const dx = e.touches[0].clientX - departX;
    const dy = e.touches[0].clientY - departY;
    if (Math.abs(dy) > Math.abs(dx)) return;
    ecart = dx;
    form.style.transform = dx < 0 ? `translateX(${dx}px)` : '';
  }, { passive: true });
  const finDuGeste = () => {
    if (departX === null) return;
    departX = null;
    form.classList.add('vp-glisse');
    form.style.transform = '';
    if (ecart < -80) envoyer('passer');
    ecart = 0;
  };
  form.addEventListener('touchend', finDuGeste);
  form.addEventListener('touchcancel', () => { ecart = 0; finDuGeste(); });
})();
