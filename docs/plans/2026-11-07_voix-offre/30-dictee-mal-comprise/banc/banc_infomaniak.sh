#!/bin/bash
# Banc de diagnostic : renvoie l'audio de l'offre 61 à la transcription d'Infomaniak sous plusieurs formes.
# Tourne sur le serveur de test ; lit la clé dans partage/.env sans l'afficher.
set -u
P=~/sites/easy-offres.xtradev.ch/partage
FF=~/outils/ffmpeg-7.0.2/ffmpeg
v() { grep -E "^$1=" "$P/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
URL=$(v TRANSCRIPTION_URL); PROD=$(v TRANSCRIPTION_PRODUIT); CLE=$(v TRANSCRIPTION_CLE); MOD=$(v TRANSCRIPTION_MODELE)
T=$(mktemp -d ~/tmp/banc-XXXX); trap 'rm -rf "$T"' EXIT
SRC=$P/var/audio/16/dd5a2160-ff78-46c5-9aae-796d61ce95aa.webm

cp "$SRC" "$T/original.webm"
$FF -nostdin -v error -y -i "$SRC" -c copy "$T/remux.webm"
$FF -nostdin -v error -y -i "$SRC" -t 30 -c:a libopus -b:a 32k "$T/debut30.ogg"
$FF -nostdin -v error -y -i "$SRC" -ss 30 -c:a libopus -b:a 32k "$T/fin30.ogg"
$FF -nostdin -v error -y -i "$SRC" -af loudnorm=I=-18 -c:a libopus -b:a 32k "$T/normalise.ogg"
ls -la "$T"

transcrire() { # $1 fichier, $2 format
  local r id s
  r=$(curl -s -X POST "$URL/1/ai/$PROD/openai/audio/transcriptions" -H "Authorization: Bearer $CLE" \
      -F "model=$MOD" -F "language=fr" -F "response_format=$2" -F "file=@$1")
  id=$(printf '%s' "$r" | sed -n 's/.*"batch_id":"\([^"]*\)".*/\1/p')
  if [ -z "$id" ]; then echo "ENVOI REFUSÉ : $(printf '%s' "$r" | head -c 300)"; return; fi
  for i in $(seq 1 90); do
    sleep 2
    s=$(curl -s "$URL/1/ai/$PROD/results/$id" -H "Authorization: Bearer $CLE")
    case "$s" in *'"status":"pending"'*|*'"status":"processing"'*) continue;; esac
    printf '%s\n' "$s" | head -c 6000; echo; return
  done
  echo "DÉLAI DÉPASSÉ"
}

for cas in "original.webm text" "original.webm text" "original.webm verbose_json" "remux.webm text" "debut30.ogg text" "fin30.ogg text" "normalise.ogg text"; do
  set -- $cas
  echo "===== $1 ($2)"
  transcrire "$T/$1" "$2"
done
