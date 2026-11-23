#!/bin/sh
# Campagne complète : cache périmé (comportement du test aujourd'hui), puis cache valide. Journal : campagne.log.
cd "$(dirname "$0")"
rm -f resultats.jsonl
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh froid
node mesurer.js froid local,fibre,4g,chantier
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh chaud
node mesurer.js chaud fibre,4g,chantier
MSYS_NO_PATHCONV=1 wsl -d Ubuntu -e sh /home/xavie/offreGen-fluidite-banc/serveur.sh froid
echo "CAMPAGNE FINIE"
