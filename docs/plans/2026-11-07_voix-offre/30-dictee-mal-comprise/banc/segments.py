"""Résume la sortie de banc_temoin.sh : segments, confiance, mots par seconde."""
import json
import sys

for ligne in open(sys.argv[1], encoding="utf-8"):
    ligne = ligne.strip()
    if ligne.startswith("====="):
        print(ligne)
        continue
    if not ligne.startswith("{"):
        print(ligne[:300])
        continue
    d = json.loads(json.loads(ligne)["data"])
    mots = len(d["text"].split())
    print(f"durée {d['duration']:.1f} s, {mots} mots, {mots / d['duration']:.2f} mot/s")
    for s in d["segments"]:
        print(f"  [{s['start']:6.2f}-{s['end']:6.2f}] ns={s['no_speech_prob']:.2f} lp={s['avg_logprob']:.2f} cr={s['compression_ratio']:.2f} {s['text'][:80]}")
