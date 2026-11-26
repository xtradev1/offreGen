#!/bin/bash
# Sous-chantier 30, vocabulaire du métier : même audio chez Infomaniak sans prompt, avec le catalogue de l'artisan
# en prompt (P1), puis catalogue et termes du métier (P2). Lecture seule : rien n'est écrit en base, les fichiers ne bougent pas.
# Arguments : id:fichier relatif[:artisan dont prendre le catalogue], autant que d'enregistrements.
set -u
P=~/sites/easy-offres.xtradev.ch/partage
v() { grep -E "^$1=" "$P/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
URL=$(v TRANSCRIPTION_URL); PROD=$(v TRANSCRIPTION_PRODUIT); CLE=$(v TRANSCRIPTION_CLE); MOD=$(v TRANSCRIPTION_MODELE)
export MYSQL_PWD=$(v DB_MOT_DE_PASSE)
DBU=$(v DB_UTILISATEUR); DBB=$(v DB_NOM); DBH=$(v DB_HOTE)
sql() { mariadb -N -B -h "$DBH" -u "$DBU" "$DBB" -e "$1"; }

declare -A LEXIQUE
LEXIQUE[peintre]="Termes du métier : laquer, laquage, lasure, enduit, ponçage, rebouchage, sous-couche, couche d'impression, dispersion, crépi, plinthes, embrasures, cadre de porte, huisseries, imperméable, anti-moisissure."

transcrire() { # fichier, prompt (vide : sans prompt)
  local r id s args=(-F "model=$MOD" -F "language=fr" -F "response_format=text" -F "file=@$1")
  [ -n "$2" ] && args+=(--form-string "prompt=$2")
  r=$(curl -s -X POST "$URL/1/ai/$PROD/openai/audio/transcriptions" -H "Authorization: Bearer $CLE" "${args[@]}")
  id=$(printf '%s' "$r" | sed -n 's/.*"batch_id":"\([^"]*\)".*/\1/p')
  if [ -z "$id" ]; then echo "ENVOI REFUSÉ : $(printf '%s' "$r" | head -c 300)"; return; fi
  for i in $(seq 1 120); do
    sleep 2
    s=$(curl -s "$URL/1/ai/$PROD/results/$id" -H "Authorization: Bearer $CLE")
    case "$s" in *'"status":"pending"'*|*'"status":"processing"'*) continue;; esac
    printf '%s\n' "$s" | sed -n 's/.*"data":"\(.*\)".*/\1/p'; return
  done
  echo "DÉLAI DÉPASSÉ"
}

for a in "$@"; do
  id=${a%%:*}; f=${a#*:}; force=""
  case "$f" in *:*) force=${f##*:}; f=${f%:*};; esac
  echo "===== $id $f"
  if [ ! -f "$P/var/audio/$f" ]; then echo "ABSENT"; continue; fi
  art=$(sql "SELECT artisan_id FROM enregistrement WHERE id = $id")
  [ -n "$force" ] && art=$force   # catalogue d'un autre compte : voix faible sans catalogue, risque d'hallucination
  met=$(sql "SELECT COALESCE(metier, '') FROM artisan WHERE id = $art")
  cat=$(sql "SELECT designation FROM produit WHERE artisan_id = $art ORDER BY id LIMIT 60" | paste -sd ',' | sed 's/,/, /g')
  p1=""; [ -n "$cat" ] && p1="Offre. $cat."
  p2="$p1 ${LEXIQUE[$met]:-}"; p2=$(echo "$p2" | sed 's/^ *//')
  echo "artisan $art, métier « $met », $(echo "$cat" | tr ',' '\n' | grep -c .) produits"
  echo "--- sans  : $(transcrire "$P/var/audio/$f" "")"
  if [ -n "$p1" ]; then echo "--- P1    : $(transcrire "$P/var/audio/$f" "$p1")"; else echo "--- P1    : (catalogue vide)"; fi
  if [ "$p2" != "$p1" ] && [ -n "$p2" ]; then echo "--- P2    : $(transcrire "$P/var/audio/$f" "$p2")"; else echo "--- P2    : (pas de lexique pour ce métier)"; fi
done
