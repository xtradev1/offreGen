"""Reproduit en local les réglages supposés d'Infomaniak (filtre de silence, sans repères de temps) pour comparer."""
import subprocess
import sys
import time

import numpy as np
from faster_whisper import WhisperModel


def charger(chemin):
    brut = subprocess.run(["ffmpeg", "-nostdin", "-v", "error", "-i", chemin, "-f", "f32le", "-ac", "1", "-ar", "16000", "-"],
                          check=True, capture_output=True).stdout
    return np.frombuffer(brut, dtype=np.float32)


nom_modele = sys.argv[1]
modele = WhisperModel(nom_modele, device="cpu", compute_type="int8", cpu_threads=8)
VARIANTES = [
    ("sans filtre, repères", dict(vad_filter=False)),
    ("filtre, repères", dict(vad_filter=True)),
    ("filtre, sans repères", dict(vad_filter=True, without_timestamps=True)),
    ("sans filtre, sans repères", dict(vad_filter=False, without_timestamps=True)),
    ("filtre, sans repères, glouton", dict(vad_filter=True, without_timestamps=True, beam_size=1)),
]
for chemin in sys.argv[2:]:
    audio = charger(chemin)
    for nom, reglages in VARIANTES:
        reglages = {"beam_size": 5, **reglages}
        t0 = time.time()
        segments, info = modele.transcribe(audio, language="fr", **reglages)
        segments = list(segments)
        n = sum(len(s.text.split()) for s in segments)
        print(f"===== {chemin.rsplit('/', 1)[-1]} | {nom_modele.rsplit('/', 1)[-1]} | {nom} | {n} mots | {time.time() - t0:.0f} s")
        for s in segments:
            print(f"  [{s.start:6.1f} -> {s.end:6.1f}] {s.text.strip()[:200]}")
        sys.stdout.flush()
