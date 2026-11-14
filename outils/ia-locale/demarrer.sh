#!/bin/sh
# Démarre le Whisper local et vérifie Ollama. Dans WSL : outils/ia-locale/demarrer.sh
# Ctrl+C l'arrête. L'adresse d'Ollama (passerelle vers Windows) change au redémarrage de Windows :
# le script la corrige dans .env s'il le faut.
cd "$(dirname "$0")/../.."
D="$HOME/offregen-ia"
[ -x "$D/venv/bin/python" ] || { echo "Whisper local absent : lancer outils/ia-locale/installer.sh"; exit 1; }

PASSERELLE=$(ip route show default | awk '{print $3}')
OLLAMA="http://$PASSERELLE:11434/v1"
if curl -s -m 3 -o /dev/null "http://$PASSERELLE:11434/api/tags"; then
  echo "Ollama joignable : $OLLAMA"
  curl -s -m 3 "http://$PASSERELLE:11434/api/tags" | grep -q '"offregen-qwen3.8' \
    || echo "ATTENTION : variante offregen-qwen3.8 absente. Sous Windows : ollama create offregen-qwen3.8 -f outils/ia-locale/Modelfile"
else
  echo "ATTENTION : Ollama injoignable sur $PASSERELLE:11434. Est-il lancé sous Windows ?"
fi
if [ -f .env ] && grep -q '^EXTRACTION_URL=http://172\.' .env && ! grep -q "^EXTRACTION_URL=$OLLAMA\$" .env; then
  sed -i "s#^EXTRACTION_URL=http://172\..*#EXTRACTION_URL=$OLLAMA#" .env
  echo "EXTRACTION_URL corrigée dans .env : $OLLAMA"
fi

# Tient lieu de cron en local : reprend toutes les 30 s ce que le traitement immédiat a laissé
# (enregistrement long, panne passagère). S'arrête avec ce script.
( while true; do sleep 30; bin/traiter-file --silencieux; done ) &
BOUCLE=$!
trap 'kill $BOUCLE 2>/dev/null' EXIT INT TERM
echo "Reprise automatique (rôle de cron) toutes les 30 s."

export HF_HOME="$D/hf" HF_HUB_OFFLINE=1
"$D/venv/bin/python" outils/ia-locale/whisper_serveur.py
