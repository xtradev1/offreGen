#!/bin/sh
# Captures « après » du banc en JPEG (900 px de large au plus) dans le dossier d'images du comparatif. Les captures
# « avant » restent celles de l'audit (MAQUETTE-sans-rechargement-img), citées telles quelles.
SRC=/mnt/c/Users/xavie/AppData/Local/Temp/claude/42fd285555aa8bf3/2b7eef8e-b575-4b46-bc07-408b5fbc51a7/scratchpad/banc/captures
DST=/home/xavie/offreGen-fluidite/docs/plans/2026-11-23_sans-rechargement/COMPARATIF-sans-rechargement-img
mkdir -p "$DST"
for n in recette-offre-juste recette-offre-deux-corbeilles recette-ajout-produit-3 recette-liste-archiver recette-liste-annuler \
         recette-relire-juste recette-pdf-envoyer revue-facturer-case revue-variante revue-facturer-hors-reseau \
         hors-ligne-apres-trois-gestes hors-ligne-apres-autre-offre hors-ligne-apres-retour-reseau session-apres \
         test-corbeille test-annuler; do
  if [ -f "$SRC/$n.png" ]; then
    ffmpeg -v error -y -i "$SRC/$n.png" -vf "scale='min(900,iw)':-2" -q:v 4 "$DST/$n.jpg" && echo "$n.jpg $(wc -c < "$DST/$n.jpg")"
  else
    echo "ABSENT : $n.png"
  fi
done
