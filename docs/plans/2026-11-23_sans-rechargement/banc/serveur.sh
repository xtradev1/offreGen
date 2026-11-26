#!/bin/sh
# Démarre (ou redémarre) le serveur de mesure du chantier « sans rechargement ».
# Usage : sh serveur.sh [froid|chaud]   (port 8093, base offregen_fluidite_vue)
MODE=${1:-froid}
ICI=/home/xavie/offreGen-fluidite-banc
if [ -f "$ICI/serveur.pid" ]; then
  kill "$(cat "$ICI/serveur.pid")" 2>/dev/null
  sleep 1
fi
cd /home/xavie/offreGen-fluidite
DB_NOM=offregen_fluidite_vue MESURE_CACHE="$MODE" PHP_CLI_SERVER_WORKERS=6 \
  nohup php -d display_errors=0 -S 0.0.0.0:8093 -t public "$ICI/routeur.php" > "$ICI/serveur.log" 2>&1 &
echo $! > "$ICI/serveur.pid"
sleep 1
echo "serveur $MODE, pid $(cat "$ICI/serveur.pid")"
curl -s -o /dev/null -w 'connexion : %{http_code}\n' http://127.0.0.1:8093/connexion
curl -s -o /dev/null -D - -H 'Accept-Encoding: gzip' http://127.0.0.1:8093/css/app.css | grep -i -E '^(HTTP|content-encoding|content-length|etag|cache-control|last-modified)' | tr -d '\r'
curl -s -o /dev/null -D - -H 'Accept-Encoding: gzip' http://127.0.0.1:8093/connexion | grep -i -E '^(HTTP|content-encoding|cache-control)' | tr -d '\r'
