"""Serveur Whisper local d'offreGen : outil de développement, jamais déployé.

Imite les deux routes de transcription d'Infomaniak (documentation lue le 2026-11-12), pour que
l'application parle le même protocole en local et en production :

  POST /1/ai/{produit}/openai/audio/transcriptions   (multipart : file, model, language, response_format)
       -> {"batch_id": "..."}
  GET  /1/ai/{produit}/results/{batch_id}
       -> {"status": "pending|processing|success|failed", "url", "file_name", "file_size", "data"}

Il reprend aussi leurs refus : fichier de plus de 50 000 Ko, format hors liste (ni amr ni 3gp).
Il n'écoute que 127.0.0.1 et ne journalise jamais le texte transcrit.

L'audio est décodé par ffmpeg puis transcrit par faster-whisper sur le processeur : sur la carte,
depuis WSL, ctranslate2 plante dans encode (constaté le 2026-11-12, pilote 610.88).
"""

import json
import os
import queue
import re
import subprocess
import sys
import tempfile
import threading
import time
import uuid
from email.parser import BytesParser
from email.policy import default as politique_courriel
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import numpy as np

HOTE = "127.0.0.1"
PORT = int(os.environ.get("WHISPER_PORT", "8090"))
CLE = os.environ.get("WHISPER_CLE", "")
MODELE = os.environ.get("WHISPER_MODELE", "mobiuslabsgmbh/faster-whisper-large-v3-turbo")
APPAREIL = os.environ.get("WHISPER_APPAREIL", "cpu")
CALCUL = os.environ.get("WHISPER_CALCUL", "int8")
FILS = int(os.environ.get("WHISPER_FILS", "8"))
TAILLE_MAX = 50000 * 1024
FORMATS = {"mp3", "mp4", "aac", "wav", "flac", "ogg", "opus", "wma", "m4a", "webm"}
INACTIVITE_AVANT_LIBERATION = 300

ROUTE_ENVOI = re.compile(r"^/1/ai/\d+/openai/audio/transcriptions$")
ROUTE_RESULTAT = re.compile(r"^/1/ai/\d+/results/([0-9a-f-]{36})$")

lots = {}
verrou_lots = threading.Lock()
file_attente = queue.Queue()


def journal(message):
    print(time.strftime("%H:%M:%S"), message, flush=True)


class Moteur:
    """Le modèle Whisper, chargé au premier fichier et libéré après 5 minutes sans travail."""

    def __init__(self):
        self.modele = None
        self.dernier_usage = 0.0
        self.verrou = threading.Lock()

    def transcrire(self, chemin, langue):
        with self.verrou:
            if self.modele is None:
                from faster_whisper import WhisperModel
                debut = time.time()
                self.modele = WhisperModel(MODELE, device=APPAREIL, compute_type=CALCUL, cpu_threads=FILS)
                journal(f"modèle chargé en {time.time() - debut:.1f} s ({APPAREIL}, {CALCUL})")
            brut = subprocess.run(
                ["ffmpeg", "-nostdin", "-v", "error", "-i", chemin, "-f", "f32le", "-ac", "1", "-ar", "16000", "-"],
                capture_output=True, check=True,
            ).stdout
            audio = np.frombuffer(brut, np.float32)
            segments, info = self.modele.transcribe(audio, language=langue or None, vad_filter=True, beam_size=5)
            texte = " ".join(s.text.strip() for s in segments).strip()
            self.dernier_usage = time.time()
            return texte, info.duration

    def liberer_si_inactif(self):
        with self.verrou:
            if self.modele is not None and time.time() - self.dernier_usage > INACTIVITE_AVANT_LIBERATION:
                self.modele = None
                journal("modèle libéré après inactivité")


moteur = Moteur()


def travailleur():
    while True:
        batch_id = file_attente.get()
        with verrou_lots:
            lot = lots[batch_id]
            lot["status"] = "processing"
        debut = time.time()
        try:
            texte, duree = moteur.transcrire(lot["chemin"], lot["langue"])
            donnees = texte if lot["format"] == "text" else json.dumps({"text": texte}, ensure_ascii=False)
            with verrou_lots:
                lot.update(status="success", data=donnees, file_size=len(donnees.encode()))
            journal(f"lot {batch_id} : {duree:.0f} s d'audio en {time.time() - debut:.1f} s")
        except Exception as e:  # noqa: BLE001 : tout échec devient un lot « failed », comme chez Infomaniak
            with verrou_lots:
                lot.update(status="failed", data=f"Échec de la transcription : {type(e).__name__}")
            journal(f"lot {batch_id} en échec : {type(e).__name__}")
        finally:
            try:
                os.unlink(lot["chemin"])
            except OSError:
                pass


def veilleur():
    while True:
        time.sleep(30)
        moteur.liberer_si_inactif()


class Gestionnaire(BaseHTTPRequestHandler):
    server_version = "offregen-whisper-local"

    def log_message(self, format, *args):  # le journal par défaut afficherait les adresses : inutile ici
        pass

    def repondre(self, code, corps):
        donnees = json.dumps(corps, ensure_ascii=False).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(donnees)))
        self.end_headers()
        self.wfile.write(donnees)

    def lire_par_morceaux(self):
        """Corps envoyé par morceaux (Transfer-Encoding: chunked) ; None au-delà de la taille permise."""
        corps = bytearray()
        while True:
            taille = int(self.rfile.readline().split(b";")[0].strip() or b"0", 16)
            if taille == 0:
                self.rfile.readline()
                return bytes(corps)
            corps += self.rfile.read(taille)
            self.rfile.readline()
            if len(corps) > TAILLE_MAX + 1024 * 1024:
                return None

    def autorise(self):
        if CLE and self.headers.get("Authorization", "") != f"Bearer {CLE}":
            self.repondre(401, {"result": "error", "error": {"code": "not_authorized"}})
            return False
        return True

    def do_POST(self):
        if not ROUTE_ENVOI.match(self.path):
            return self.repondre(404, {"result": "error", "error": {"code": "not_found"}})
        if not self.autorise():
            return
        if self.headers.get("Transfer-Encoding", "").lower() == "chunked":
            corps = self.lire_par_morceaux()
            if corps is None:
                return self.repondre(413, {"result": "error", "error": {"code": "file_too_large"}})
        else:
            longueur = int(self.headers.get("Content-Length", "0"))
            if longueur > TAILLE_MAX + 1024 * 1024:
                return self.repondre(413, {"result": "error", "error": {"code": "file_too_large"}})
            corps = self.rfile.read(longueur)
        message = BytesParser(policy=politique_courriel).parsebytes(
            b"Content-Type: " + self.headers.get("Content-Type", "").encode() + b"\r\n\r\n" + corps
        )
        champs, fichier, nom = {}, None, ""
        for partie in message.iter_parts():
            cle = partie.get_param("name", header="content-disposition")
            if cle == "file":
                fichier, nom = partie.get_payload(decode=True), partie.get_filename() or ""
            elif cle:
                champs[cle] = partie.get_content().strip()
        if fichier is None:
            return self.repondre(422, {"result": "error", "error": {"code": "validation_failed", "description": "file is required"}})
        if len(fichier) > TAILLE_MAX:
            return self.repondre(422, {"result": "error", "error": {"code": "validation_failed", "description": "file too large"}})
        extension = nom.rsplit(".", 1)[-1].lower() if "." in nom else ""
        if extension not in FORMATS:
            return self.repondre(422, {"result": "error", "error": {"code": "validation_failed", "description": "file type not allowed"}})
        if champs.get("model") not in ("whisper", "whisperV2"):
            return self.repondre(422, {"result": "error", "error": {"code": "validation_failed", "description": "unknown model"}})
        descripteur, chemin = tempfile.mkstemp(suffix="." + extension, prefix="offregen-whisper-")
        with os.fdopen(descripteur, "wb") as f:
            f.write(fichier)
        batch_id = str(uuid.uuid4())
        with verrou_lots:
            lots[batch_id] = {
                "status": "pending", "data": "", "file_size": 0, "chemin": chemin,
                "langue": champs.get("language", ""), "format": champs.get("response_format", "json"),
            }
        file_attente.put(batch_id)
        journal(f"lot {batch_id} reçu : {len(fichier) // 1024} Ko, {extension}")
        self.repondre(200, {"batch_id": batch_id})

    def do_GET(self):
        trouve = ROUTE_RESULTAT.match(self.path)
        if not trouve:
            return self.repondre(404, {"result": "error", "error": {"code": "not_found"}})
        if not self.autorise():
            return
        with verrou_lots:
            lot = lots.get(trouve.group(1))
            if lot is None:
                return self.repondre(404, {"result": "error", "error": {"code": "not_found"}})
            corps = {
                "status": lot["status"],
                "url": f"http://{HOTE}:{PORT}{self.path}/download",
                "file_name": trouve.group(1) + (".txt" if lot["format"] == "text" else ".json"),
                "file_size": lot["file_size"],
                "data": lot["data"],
            }
        self.repondre(200, corps)


def main():
    threading.Thread(target=travailleur, daemon=True).start()
    threading.Thread(target=veilleur, daemon=True).start()
    serveur = ThreadingHTTPServer((HOTE, PORT), Gestionnaire)
    journal(f"Whisper local prêt sur http://{HOTE}:{PORT} ({APPAREIL}, {CALCUL}, {FILS} fils)")
    try:
        serveur.serve_forever()
    except KeyboardInterrupt:
        journal("arrêt")
        sys.exit(0)


if __name__ == "__main__":
    main()
