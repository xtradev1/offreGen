"""Infomaniak (calib.txt) contre Whisper local sans filtre (compter_tous.txt), enregistrement par enregistrement."""
import json
import re
import sys

infomaniak = {}
cur = None
for ligne in open(sys.argv[1], encoding="utf-8"):
    if ligne.startswith("====="):
        cur = int(ligne.split()[1])
    elif ligne.startswith("{") and cur is not None:
        b = json.loads(ligne)
        d = b["data"] if isinstance(b["data"], dict) else json.loads(b["data"])
        infomaniak[cur] = (d["text"].strip(), float(d.get("duration") or 0))

local = {}
lignes = open(sys.argv[2], encoding="utf-8").read().split("\n")
for i, ligne in enumerate(lignes):
    m = re.match(r"===== e(\d+)\.\w+ (\d+) mots", ligne)
    if m:
        local[int(m.group(1))] = lignes[i + 1].strip() if i + 1 < len(lignes) else ""

VOIR = {10, 15, 25}
detail = "--detail" in sys.argv
print(f"{'id':>3} {'durée':>6} {'Infomaniak':>10} {'local':>6} {'écart':>6}")
for k in sorted(infomaniak):
    ti, duree = infomaniak[k]
    tl = local.get(k)
    if tl is None:
        print(f"{k:>3} {duree:6.1f} {len(ti.split()):10d}   (pas de local)")
        continue
    ni, nl = len(ti.split()), len(tl.split())
    ecart = (ni - nl) / nl * 100 if nl else 0
    marque = "  <== perte" if ecart < -15 else ""
    print(f"{k:>3} {duree:6.1f} {ni:10d} {nl:6d} {ecart:5.0f} %{marque}")
    if detail and (ecart < -15 or k in VOIR):
        print("      INFOMANIAK :", ti[:400])
        print("      LOCAL      :", tl[:400])
