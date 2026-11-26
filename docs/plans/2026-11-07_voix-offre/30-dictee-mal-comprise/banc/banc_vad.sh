#!/bin/bash
# Sous-chantier 30 : pourquoi Infomaniak comprend moins que Whisper en local. Modèles proposés, puis la dictée 14
# (offre 54) et la dictée 19 (offre 61) envoyées avec plusieurs réglages. Lecture seule sur le site de test.
set -u
P=~/sites/easy-offres.xtradev.ch/partage
FF=~/outils/ffmpeg-7.0.2/ffmpeg
v() { grep -E "^$1=" "$P/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
URL=$(v TRANSCRIPTION_URL); PROD=$(v TRANSCRIPTION_PRODUIT); CLE=$(v TRANSCRIPTION_CLE); MOD=$(v TRANSCRIPTION_MODELE)
T=$(mktemp -d)
trap 'rm -rf "$T"' EXIT

echo "===== modèle réglé : $MOD"
echo "===== modèles"
curl -s "$URL/1/ai/models" -H "Authorization: Bearer $CLE" | head -c 6000
echo
curl -s "$URL/1/ai/$PROD/openai/models" -H "Authorization: Bearer $CLE" | head -c 3000
echo

# $1 étiquette, $2 fichier, reste : champs -F supplémentaires
transcrire() {
  local nom=$1 f=$2 r id s; shift 2
  r=$(curl -s -X POST "$URL/1/ai/$PROD/openai/audio/transcriptions" -H "Authorization: Bearer $CLE" \
      -F "language=fr" -F "response_format=verbose_json" -F "file=@$f" "$@")
  echo "===== $nom"
  id=$(printf '%s' "$r" | sed -n 's/.*"batch_id":"\([^"]*\)".*/\1/p')
  if [ -z "$id" ]; then echo "ENVOI REFUSÉ : $(printf '%s' "$r" | head -c 400)"; return; fi
  for i in $(seq 1 200); do
    sleep 2
    s=$(curl -s "$URL/1/ai/$PROD/results/$id" -H "Authorization: Bearer $CLE")
    case "$s" in *'"status":"pending"'*|*'"status":"processing"'*) continue;; esac
    printf '%s\n' "$s"; return
  done
  echo "DÉLAI DÉPASSÉ"
}

A14=$P/var/audio/20/38be7aeb-af52-48b5-9af0-57706677083d.webm
A19=$P/var/audio/16/dd5a2160-ff78-46c5-9aae-796d61ce95aa.webm

transcrire "14 défaut" "$A14" -F "model=$MOD"
transcrire "14 vad_filter=false" "$A14" -F "model=$MOD" -F "vad_filter=false"
transcrire "14 vad=false" "$A14" -F "model=$MOD" -F "vad=false"
transcrire "14 temperature=0.2" "$A14" -F "model=$MOD" -F "temperature=0.2"
transcrire "14 prompt" "$A14" -F "model=$MOD" -F "prompt=Dictée d'un artisan pour une offre : travaux, quantités, prix."
transcrire "19 vad_filter=false" "$A19" -F "model=$MOD" -F "vad_filter=false"

# Découpe en morceaux de 30 s, sans réencodage du son (copie du flux Opus).
"$FF" -v error -i "$A14" -f segment -segment_time 30 -c copy "$T/m14_%02d.webm"
for m in "$T"/m14_*.webm; do transcrire "14 morceau $(basename "$m")" "$m" -F "model=$MOD"; done
