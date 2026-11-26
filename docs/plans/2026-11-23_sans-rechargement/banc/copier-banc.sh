#!/bin/sh
# Copie le banc dans le dossier du chantier (preuves, pas du code à reprendre). Jamais : la session enregistrée
# (etat-session.json), le mot de passe (.mdp), l'instantané de la base (empreinte du mot de passe), les captures PNG
# (leurs JPEG sont dans les dossiers d'images des pages), le fichier audio converti.
SRC=/mnt/c/Users/xavie/AppData/Local/Temp/claude/42fd285555aa8bf3/2b7eef8e-b575-4b46-bc07-408b5fbc51a7/scratchpad/banc
WSLB=/home/xavie/offreGen-fluidite-banc
DST=/home/xavie/offreGen-fluidite/docs/plans/2026-11-23_sans-rechargement/banc
mkdir -p "$DST"
# Audit (avant).
for f in commun.js scenarios.js mesurer.js synthese.js donnees.js inventaire.js tableau-md.js audit.js maquette.js \
         hors-ligne.js dicter.js demo.js apres.js poids-pdf.js elements.py typo.py images.sh campagne.sh suite.sh reprise.sh preuves.js copier-banc.sh \
         chaud-ids.txt elements.txt AUDIT.modele.md resultats.jsonl synthese.json synthese.md hors-ligne.json dictees.jsonl; do
  [ -f "$SRC/$f" ] && cp "$SRC/$f" "$DST/$f"
done
# Après (lots 1 à 5, revue, comparatif).
for f in apres-scenarios.js apres-mesurer.js comparatif.js comparatif-page.js detail.js sw-bride.js images-comparatif.sh \
         recette-gestes.js recette-listes.js recette-hors-ligne.js recette-pdf.js recette-revue.js recette-session.js \
         verifier-prechargement.js preuve-d1.js regarder-comparatif.js recette-test.js recette-test.json \
         mesure-corbeille.js mesure-corbeille.json recette-sans-js.js recette-sans-js.json sans-js-liste.js \
         apres-resultats.jsonl apres-resultats-avant-correctif-sw.jsonl apres-resultats-sw-libre.jsonl comparatif.json \
         recette-gestes-chantier.json recette-gestes-local.json recette-reannuler.js verif-reannuler.js recette-carre.js recette-carre-webkit.js recette-hors-ligne.json recette-session.json preuve-prechargement.json preuve-d1.json deploiement.json; do
  [ -f "$SRC/$f" ] && cp "$SRC/$f" "$DST/$f"
done
for f in routeur.php serveur.sh base.sh semis.py offre-mesure.sql; do
  [ -f "$WSLB/$f" ] && cp "$WSLB/$f" "$DST/$f"
done
# Contrôles : ni session, ni mot de passe, ni BOM, ni tiret long.
if grep -l -E "PHP""SESSID|offregen_""session|same""Site|\"offregen\", *\"value\"" "$DST"/* 2>/dev/null; then echo "ALERTE : session dans le banc"; fi
if grep -l -i -E "mot_de_passe\" *: *\"[^\"]|password\" *: *\"[^\"]" "$DST"/* 2>/dev/null; then echo "ALERTE : mot de passe dans le banc"; fi
for f in "$DST"/*; do head -c3 "$f" | od -An -tx1 | grep -q "ef bb bf" && echo "BOM : $f"; done
grep -l -P "\x{2014}|\x{2013}|&m""dash;" "$DST"/* 2>/dev/null && echo "tiret long ci-dessus"
ls "$DST" | wc -l
du -sh "$DST"
