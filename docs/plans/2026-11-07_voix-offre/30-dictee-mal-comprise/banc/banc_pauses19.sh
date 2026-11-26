#!/bin/bash
# Sous-chantier 30 : dictées 14 et 19 coupées aux pauses (relevées en local), morceaux envoyés tous ensemble,
# avec le temps d'attente de chacun. Lecture seule sur le site de test.
set -u
P=~/sites/easy-offres.xtradev.ch/partage
FF=~/outils/ffmpeg-7.0.2/ffmpeg
v() { grep -E "^$1=" "$P/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
URL=$(v TRANSCRIPTION_URL); PROD=$(v TRANSCRIPTION_PRODUIT); CLE=$(v TRANSCRIPTION_CLE); MOD=$(v TRANSCRIPTION_MODELE)
T=$(mktemp -d)
trap 'rm -rf "$T"' EXIT

couper() { # $1 source, $2 préfixe, reste : bornes en secondes
  local src=$1 pre=$2 i=0 debut=0; shift 2
  for fin in "$@" end; do
    if [ "$fin" = end ]; then "$FF" -nostdin -v error -ss "$debut" -i "$src" -c:a libopus -b:a 32k "$T/${pre}_$i.ogg"
    else "$FF" -nostdin -v error -ss "$debut" -to "$fin" -i "$src" -c:a libopus -b:a 32k "$T/${pre}_$i.ogg"; fi
    debut=$fin; i=$((i + 1))
  done
}
couper "$P/var/audio/16/dd5a2160-ff78-46c5-9aae-796d61ce95aa.webm" p19 17.5 33.0 40.0

# Tous les envois d'abord, puis l'attente : on voit si la file les traite ensemble.
declare -A ID DEPART
for f in "$T"/p19_*.ogg; do
  n=$(basename "$f")
  r=$(curl -s -X POST "$URL/1/ai/$PROD/openai/audio/transcriptions" -H "Authorization: Bearer $CLE" \
      -F "model=$MOD" -F "language=fr" -F "response_format=verbose_json" -F "file=@$f")
  ID[$n]=$(printf '%s' "$r" | sed -n 's/.*"batch_id":"\([^"]*\)".*/\1/p')
  DEPART[$n]=$(date +%s)
  [ -z "${ID[$n]}" ] && echo "ENVOI REFUSÉ $n : $(printf '%s' "$r" | head -c 300)"
done
restants=${#ID[@]}
for i in $(seq 1 300); do
  [ "$restants" -eq 0 ] && break
  sleep 3
  for n in "${!ID[@]}"; do
    [ -z "${ID[$n]}" ] && continue
    s=$(curl -s "$URL/1/ai/$PROD/results/${ID[$n]}" -H "Authorization: Bearer $CLE")
    case "$s" in *'"status":"pending"'*|*'"status":"processing"'*) continue;; esac
    echo "===== $n | attente $(( $(date +%s) - ${DEPART[$n]} )) s"
    printf '%s\n' "$s"
    ID[$n]=""; restants=$((restants - 1))
  done
done
