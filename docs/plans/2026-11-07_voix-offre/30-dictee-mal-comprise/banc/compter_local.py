import subprocess, sys
import numpy as np
from faster_whisper import WhisperModel
m = WhisperModel("mobiuslabsgmbh/faster-whisper-large-v3-turbo", device="cpu", compute_type="int8", cpu_threads=8)
for c in sys.argv[1:]:
    a = np.frombuffer(subprocess.run(["ffmpeg", "-nostdin", "-v", "error", "-i", c, "-f", "f32le", "-ac", "1", "-ar", "16000", "-"], check=True, capture_output=True).stdout, dtype=np.float32)
    seg, info = m.transcribe(a, language="fr", beam_size=5, vad_filter=False)
    t = " ".join(s.text.strip() for s in seg)
    print("=====", c.rsplit("/", 1)[-1], len(t.split()), "mots")
    print(t)
    sys.stdout.flush()
