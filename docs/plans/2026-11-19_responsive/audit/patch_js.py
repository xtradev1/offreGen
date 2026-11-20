"""B3 (bouton pendant un envoi), R10 b C (trois volets dès 1800 px) et largeur des champs sans field-sizing, dans
public/js/app.js. Chaque remplacement doit trouver son texte exactement une fois, sinon rien n'est écrit."""
import sys

chemin = sys.argv[1]
js = open(chemin, encoding='utf-8').read()
R = []


def rem(ancien, nouveau):
    R.append((ancien, nouveau))


# B3, ligne 83 : le bouton d'un formulaire envoyé garde sa couleur, un rond tourne devant son mot d'attente.
rem("        if (b.form === form) { b.disabled = true; b.textContent = b.dataset.attente; }",
    "        if (b.form === form) { b.disabled = true; b.setAttribute('aria-busy', 'true'); b.textContent = b.dataset.attente; }")

# B3, lignes 878 et 907 : dans une fenêtre, le bouton touché porte le rond (et son mot d'attente s'il en a un) ; tous
# les boutons restent bloqués jusqu'à la réponse.
rem("    const boutons = fond.querySelectorAll('button[type=submit]');\n"
    "    boutons.forEach((b) => { b.disabled = true; });",
    "    const boutons = fond.querySelectorAll('button[type=submit]');\n"
    "    const touche = e.submitter || boutons[0] || null;\n"
    "    const libelle = touche ? touche.textContent : '';\n"
    "    boutons.forEach((b) => { b.disabled = true; });\n"
    "    if (touche) {\n"
    "      touche.setAttribute('aria-busy', 'true');\n"
    "      if (touche.dataset.attente) touche.textContent = touche.dataset.attente;\n"
    "    }")
rem("      .finally(() => boutons.forEach((b) => { b.disabled = false; }));",
    "      .finally(() => {\n"
    "        boutons.forEach((b) => { b.disabled = false; });\n"
    "        if (touche) {\n"
    "          touche.removeAttribute('aria-busy');\n"
    "          if (touche.dataset.attente) touche.textContent = libelle;\n"
    "        }\n"
    "      });")

# R10 b C : dès 1800 px, le document s'ouvre d'office à côté de la liste, sauf si l'artisan l'a fermé ; ce choix
# automatique n'est pas gardé, il ne suit pas l'artisan sur un écran plus petit.
rem("// « Document à côté » (PC, D13) : le document de l'offre remplace la colonne des offres et se recharge après chaque\n"
    "// correction. Le choix est gardé dans ce navigateur.",
    "// « Document à côté » (PC, D13) : le document de l'offre remplace la colonne des offres et se recharge après chaque\n"
    "// correction. Le choix est gardé dans ce navigateur. Dès 1800 px (R10 b C, 2026-11-19), la liste reste et le document\n"
    "// s'ouvre d'office, sauf si l'artisan l'a fermé.")
rem("  const toujours = bouton.hasAttribute('data-doc-cote-toujours');\n"
    "  const montrer = (oui) => {\n"
    "    document.body.classList.toggle('doc-a-cote', oui);\n"
    "    bouton.setAttribute('aria-pressed', String(oui));\n"
    "    bouton.textContent = oui ? (toujours ? 'Cacher le document' : 'Revenir à la liste') : 'Document à côté';",
    "  const toujours = bouton.hasAttribute('data-doc-cote-toujours');\n"
    "  const troisVolets = window.matchMedia('(min-width: 1800px) and (hover: hover) and (pointer: fine)');\n"
    "  const montrer = (oui, garder = true) => {\n"
    "    document.body.classList.toggle('doc-a-cote', oui);\n"
    "    bouton.setAttribute('aria-pressed', String(oui));\n"
    "    const fermer = toujours ? 'Cacher le document' : (troisVolets.matches ? 'Fermer le document' : 'Revenir à la liste');\n"
    "    bouton.textContent = oui ? fermer : 'Document à côté';")
rem("    if (toujours) return;\n"
    "    try { window.localStorage.setItem('offregen-doc-a-cote', oui ? '1' : '0'); }",
    "    if (toujours || !garder) return;\n"
    "    try { window.localStorage.setItem('offregen-doc-a-cote', oui ? '1' : '0'); }")
rem("  let garde = false;\n"
    "  try { garde = window.localStorage.getItem('offregen-doc-a-cote') === '1'; } catch (err) { /* idem */ }\n"
    "  // Le document à côté demande 1280 px (app.css) ; plus étroit, le choix reste gardé pour un plus grand écran.\n"
    "  if ((garde || toujours) && window.matchMedia('(min-width: 1280px) and (hover: hover) and (pointer: fine)').matches) montrer(true);",
    "  let garde = null;\n"
    "  try { garde = window.localStorage.getItem('offregen-doc-a-cote'); } catch (err) { /* idem */ }\n"
    "  // Le document à côté demande 1280 px (app.css) ; plus étroit, le choix reste gardé pour un plus grand écran.\n"
    "  if ((garde === '1' || toujours) && window.matchMedia('(min-width: 1280px) and (hover: hover) and (pointer: fine)').matches) montrer(true);\n"
    "  else if (garde !== '0' && troisVolets.matches) montrer(true, false);")

# Largeur des champs de quantité et de prix au contenu, pour un navigateur sans field-sizing (Safari).
rem("// L'œil d'un mot de passe : montre ou masque",
    "// Champs de quantité et de prix à la largeur de leur contenu (Xavier, 2026-11-19). app.css le fait par field-sizing ;\n"
    "// un navigateur qui ne le connaît pas (Safari) reçoit l'attribut size, recalculé à la frappe et quand les lignes\n"
    "// sont remplacées après un enregistrement. Minimum : 2 chiffres pour la quantité, 3 pour le prix.\n"
    "function largeurDesSaisies() {\n"
    "  if (window.CSS && CSS.supports('field-sizing', 'content')) return;\n"
    "  const ajuster = (c) => { c.size = Math.max(c.name === 'prix' ? 3 : 2, (c.value || c.placeholder || '').length); };\n"
    "  const tous = () => document.querySelectorAll('input.saisie').forEach(ajuster);\n"
    "  tous();\n"
    "  document.addEventListener('input', (e) => { if (e.target.matches && e.target.matches('input.saisie')) ajuster(e.target); });\n"
    "  const ecran = document.querySelector('.screen');\n"
    "  if (ecran) new MutationObserver(tous).observe(ecran, { childList: true, subtree: true });\n"
    "}\n\n"
    "// L'œil d'un mot de passe : montre ou masque")
rem("  corrections();\n", "  corrections();\n  largeurDesSaisies();\n")

for ancien, nouveau in R:
    n = js.count(ancien)
    if n != 1:
        sys.exit(f'ARRÊT, trouvé {n} fois : {ancien[:90]!r}')
    js = js.replace(ancien, nouveau)
open(chemin, 'w', encoding='utf-8', newline='\n').write(js)
print(len(R), 'remplacements appliqués')
