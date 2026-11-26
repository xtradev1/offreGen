// Texte d'un PDF, ligne par ligne, pour l'import du catalogue (chantier 2026-11-20_import-catalogue).
// PDF.js rend les morceaux de texte dans l'ordre du fichier : un tableau exporté colonne par colonne sort mélangé.
// Les morceaux sont donc rangés par leurs coordonnées : même hauteur = même ligne, de gauche à droite ; un grand
// écart entre deux morceaux devient une séparation de cellule « | ».

const ECART_CELLULE = 14; // points : au-delà, deux morceaux d'une même ligne sont deux cellules

/**
 * @param {object} pdfjs le module PDF.js
 * @param {object} source ce que getDocument accepte : { url } ou { data }
 * @returns {Promise<{pages: number, lignes: string[], caracteres: number}>}
 */
export async function lignesDuPdf(pdfjs, source) {
  const tache = pdfjs.getDocument({ ...source, isEvalSupported: false });
  const doc = await tache.promise;
  const pages = doc.numPages;
  const lignes = [];
  for (let n = 1; n <= doc.numPages; n++) {
    const page = await doc.getPage(n);
    const contenu = await page.getTextContent();
    const morceaux = contenu.items
      .filter((m) => typeof m.str === 'string' && m.str.trim() !== '')
      .map((m) => ({ texte: m.str.trim(), x: m.transform[4], y: m.transform[5], largeur: m.width, hauteur: m.height || Math.abs(m.transform[3]) || 10 }));
    morceaux.sort((a, b) => b.y - a.y || a.x - b.x);
    const rangees = [];
    for (const m of morceaux) {
      const derniere = rangees[rangees.length - 1];
      if (derniere && Math.abs(derniere.y - m.y) <= Math.max(2, m.hauteur * 0.45)) {
        derniere.morceaux.push(m);
      } else {
        rangees.push({ y: m.y, morceaux: [m] });
      }
    }
    for (const r of rangees) {
      r.morceaux.sort((a, b) => a.x - b.x);
      let texte = '';
      let fin = null;
      for (const m of r.morceaux) {
        if (fin !== null) {
          texte += m.x - fin > ECART_CELLULE ? ' | ' : ' ';
        }
        texte += m.texte;
        fin = m.x + m.largeur;
      }
      lignes.push(texte.replace(/\s+/g, ' ').trim());
    }
    if (n < doc.numPages) {
      lignes.push('');
    }
  }
  await tache.destroy();
  return { pages, lignes, caracteres: lignes.join('').length };
}
