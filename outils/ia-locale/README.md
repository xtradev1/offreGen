# IA locale d'offreGen

Outils de développement : jamais déployés. Ils font tourner la vraie transcription et la vraie
extraction sur le PC, avec le même protocole qu'Infomaniak. Passer au serveur ne change que le `.env`.

| Service | Où | Rôle |
|---|---|---|
| Whisper turbo (faster-whisper) | WSL, `~/offregen-ia`, sur le processeur | transcription, imite `/1/ai/{produit}/openai/audio/transcriptions` et `/1/ai/{produit}/results/{lot}` |
| Ollama, modèle `offregen-qwen3.8` | Windows, sur la carte graphique | extraction, route `/v1/chat/completions` |

## Une fois

1. Dans WSL : `outils/ia-locale/installer.sh` (environ 4 Go dans `~/offregen-ia`).
2. Sous Windows : `ollama create offregen-qwen3.8 -f outils/ia-locale/Modelfile` (aucun téléchargement : variante de `qwen3.8:27b` avec un contexte de 16 384 jetons).
3. Dans `.env` : `TRANSCRIPTION=api` et `EXTRACTION=api` (les autres valeurs locales sont celles de `.env.example`).

## À chaque séance

`outils/ia-locale/demarrer.sh` dans un terminal WSL, laissé ouvert. Il :

- corrige dans `.env` l'adresse d'Ollama (la passerelle vers Windows change au redémarrage) ;
- démarre le Whisper local sur `127.0.0.1:8090` ;
- tient lieu de cron : `bin/traiter-file` toutes les 30 s.

`bin/verifier-ia` dit si les deux services répondent.

## Pièges constatés le 2026-11-12

- Sur la carte, depuis WSL, ctranslate2 plante dans `encode` (pilote 610.88), avec cuDNN 9.28 comme 9.5 et ctranslate2 4.9 comme 4.5. Whisper tourne donc sur le processeur : 28 s de dictée en 9 s.
- faster-whisper 1.2.1 appelle PyAV avec un paramètre retiré de PyAV 19 : l'audio est décodé par ffmpeg.
- Avec le contexte par défaut de 131 072 jetons, Qwen 27B déborde de la carte (59 s par offre au lieu de 13 s).
- Symfony envoie un gros fichier par morceaux si la longueur n'est pas annoncée : `TranscripteurApi` l'annonce.
