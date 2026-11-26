#!/bin/bash
# Lot 1 du sous-chantier 30 : pour chaque audio du test, niveaux par seconde (ffmpeg) et transcription détaillée
# (verbose_json, Infomaniak). Lecture seule sur le site : rien n'est écrit en base, les fichiers ne bougent pas.
# Arguments : id:fichier relatif, autant que d'enregistrements.
set -u
P=~/sites/easy-offres.xtradev.ch/partage
FF=~/outils/ffmpeg-7.0.2/ffmpeg
v() { grep -E "^$1=" "$P/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
URL=$(v TRANSCRIPTION_URL); PROD=$(v TRANSCRIPTION_PRODUIT); CLE=$(v TRANSCRIPTION_CLE); MOD=$(v TRANSCRIPTION_MODELE)

transcrire() {
  local r id s
  r=$(curl -s -X POST "$URL/1/ai/$PROD/openai/audio/transcriptions" -H "Authorization: Bearer $CLE" \
      -F "model=$MOD" -F "language=fr" -F "response_format=verbose_json" -F "file=@$1")
  id=$(printf '%s' "$r" | sed -n 's/.*"batch_id":"\([^"]*\)".*/\1/p')
  if [ -z "$id" ]; then echo "ENVOI REFUSÉ : $(printf '%s' "$r" | head -c 300)"; return; fi
  for i in $(seq 1 120); do
    sleep 2
    s=$(curl -s "$URL/1/ai/$PROD/results/$id" -H "Authorization: Bearer $CLE")
    case "$s" in *'"status":"pending"'*|*'"status":"processing"'*) continue;; esac
    printf '%s\n' "$s"; return
  done
  echo "DÉLAI DÉPASSÉ"
}

for a in "$@"; do
  id=${a%%:*}; f=${a#*:}
  echo "===== $id $f"
  if [ ! -f "$P/var/audio/$f" ]; then echo "ABSENT"; continue; fi
  n=$("$FF" -v error -i "$P/var/audio/$f" -ac 1 -ar 16000 \
      -af "asetnsamples=n=16000:p=0,astats=metadata=1:reset=1,ametadata=print:key=lavfi.astats.Overall.RMS_level:file=-" \
      -f null - 2>/dev/null | sed -n 's/^lavfi.astats.Overall.RMS_level=//p' | tr '\n' ',')
  echo "NIVEAUX:$n"
  transcrire "$P/var/audio/$f"
done
