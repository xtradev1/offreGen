#!/bin/sh
# Captures du banc en JPEG (900 px de large au plus) dans le dossier d'images de la maquette.
SRC=/mnt/c/Users/xavie/AppData/Local/Temp/claude/42fd285555aa8bf3/2b7eef8e-b575-4b46-bc07-408b5fbc51a7/scratchpad/banc/captures
DST=/home/xavie/offreGen-fluidite/docs/plans/2026-11-23_sans-rechargement/MAQUETTE-sans-rechargement-img
mkdir -p "$DST"
for n in demo-avant demo-apres-rechargement demo-corbeille-apres-rechargement avant-ajout \
         apres-juste apres-corbeille apres-hors-ligne apres-retour-reseau apres-ajout apres-ajout-hors-ligne apres-liste-hors-ligne \
         hors-ligne-juste hors-ligne-quantite hors-ligne-ajout hors-ligne-ouvrir lent-deux-corbeilles \
         session-expiree-quantite dictee-chantier-suivi; do
  if [ -f "$SRC/$n.png" ]; then
    ffmpeg -v error -y -i "$SRC/$n.png" -vf "scale='min(900,iw)':-2" -q:v 4 "$DST/$n.jpg" && echo "$n.jpg $(wc -c < "$DST/$n.jpg")"
  else
    echo "ABSENT : $n.png"
  fi
done
