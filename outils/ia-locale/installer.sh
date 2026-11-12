#!/bin/sh
# Installe le Whisper local d'offreGen dans ~/offregen-ia (hors du dépôt), puis crée la variante
# de Qwen pour Ollama. À lancer une fois, dans WSL. Supprimable par : rm -rf ~/offregen-ia
set -e
D="$HOME/offregen-ia"
mkdir -p "$D/hf/hub"
command -v ffmpeg > /dev/null || { echo "ffmpeg manque : sudo apt install ffmpeg"; exit 1; }
[ -x "$D/venv/bin/pip" ] || python3 -m venv "$D/venv" || { echo "python3-venv manque : sudo apt install python3.12-venv"; exit 1; }
"$D/venv/bin/pip" install -q --upgrade pip
# Version figée. PyAV n'est pas utilisé : faster-whisper 1.2.1 l'appelle avec un paramètre retiré de PyAV 19,
# et l'audio est de toute façon décodé par ffmpeg (constaté le 2026-11-12).
"$D/venv/bin/pip" install -q --no-cache-dir "faster-whisper==1.2.1"
# Modèle turbo : copié depuis le cache de Windows s'il y est, sinon téléchargé (1,6 Go).
M=models--mobiuslabsgmbh--faster-whisper-large-v3-turbo
if [ ! -d "$D/hf/hub/$M" ]; then
  if [ -d "/mnt/c/Users/$USER/.cache/huggingface/hub/$M" ]; then
    cp -rL "/mnt/c/Users/$USER/.cache/huggingface/hub/$M" "$D/hf/hub/"
  else
    HF_HOME="$D/hf" "$D/venv/bin/python" -c "from faster_whisper import WhisperModel; WhisperModel('mobiuslabsgmbh/faster-whisper-large-v3-turbo', device='cpu', compute_type='int8')"
  fi
fi
du -sh "$D/venv" "$D/hf"
echo "Whisper local installé dans $D."
echo "Variante de Qwen (sous Windows, une fois) : ollama create offregen-qwen3.8 -f outils/ia-locale/Modelfile"
