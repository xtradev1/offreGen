"""Détail d'un enregistrement de calib.txt : segments et niveaux seconde par seconde."""
import json
import sys

voulu = {int(x) for x in sys.argv[2:]}
cur = None
for ligne in open(sys.argv[1], encoding="utf-8"):
    ligne = ligne.rstrip("\n")
    if ligne.startswith("====="):
        cur = int(ligne.split(" ")[1])
        if cur in voulu:
            print(ligne)
    elif cur in voulu and ligne.startswith("NIVEAUX:"):
        n = [x for x in ligne[8:].split(",") if x]
        print("niveaux par seconde :", " ".join(f"{i}:{float(x):.0f}" for i, x in enumerate(n)))
    elif cur in voulu and ligne.startswith("{"):
        brut = json.loads(ligne)
        d = brut["data"] if isinstance(brut["data"], dict) else json.loads(brut["data"])
        for s in d["segments"]:
            print(f"  [{s['start']:6.2f}-{s['end']:6.2f}] ns={s['no_speech_prob']:.2f} lp={s['avg_logprob']:.2f} {s['text'][:150]}")
