"""Lot 1 : tableau de calibrage à partir de calib.txt (niveaux par seconde et verbose_json)."""
import json
import math
import sys


def centile(valeurs, p):
    v = sorted(valeurs)
    if not v:
        return float("nan")
    k = (len(v) - 1) * p / 100
    b = math.floor(k)
    h = min(b + 1, len(v) - 1)
    return v[b] + (v[h] - v[b]) * (k - b)


def regles(mps, ns, voix):
    causes = []
    if mps < 1.0:
        causes.append("peu_de_mots")
    if ns > 0.2 and mps < 0.5:
        causes.append("voix_couverte")
    if voix < -35:
        causes.append("voix_faible")
    return causes


fiches = []
cur = None
for ligne in open(sys.argv[1], encoding="utf-8"):
    ligne = ligne.rstrip("\n")
    if ligne.startswith("====="):
        _, ident, fichier = ligne.split(" ", 2)
        cur = {"id": int(ident), "fichier": fichier}
        fiches.append(cur)
    elif ligne.startswith("NIVEAUX:"):
        cur["niveaux"] = [float(x) for x in ligne[8:].split(",") if x and x not in ("-inf", "inf", "nan")]
    elif ligne.startswith("{"):
        brut = json.loads(ligne)
        d = brut["data"] if isinstance(brut["data"], dict) else json.loads(brut["data"])
        cur["json"] = d
    elif ligne:
        cur["erreur"] = ligne[:200]

def regles2(mpp, ns, voix):
    """Jeu calibré : seuils au milieu de l'écart entre dictées normales et ratées."""
    causes = []
    if voix < -32:
        causes.append("voix_faible")
    if mpp < 1.0:
        causes.append("peu_de_mots")
    if ns > 0.6:
        causes.append("sans_parole")
    return causes


detail = "--detail" in sys.argv
print(f"{'id':>3} {'durée':>6} {'mots':>5} {'mot/s':>6} {'m/s par':>7} {'ns max':>6} {'voix p80':>8} {'p50':>6} {'net%':>5}  départ | calibré")
for f in fiches:
    d = f.get("json")
    if d is None:
        print(f"{f['id']:>3} ERREUR {f.get('erreur', '')}")
        continue
    duree = float(d.get("duration") or len(f["niveaux"]))
    mots = len(d["text"].split())
    mps = mots / duree if duree else 0
    ns = max((s["no_speech_prob"] for s in d.get("segments", [])), default=1.0)
    niv = f["niveaux"]
    p80 = centile(niv, 80)
    p50 = centile(niv, 50)
    net = 100 * sum(1 for x in niv if x >= -38) / len(niv) if niv else 0
    parole = duree * net / 100
    mpp = mots / parole if parole else 0
    causes = regles(mps, ns, p80)
    verdict = "douteux" if causes else "bon"
    causes2 = regles2(mpp, ns, p80)
    verdict2 = "douteux" if causes2 else "bon"
    if mots == 0:
        verdict = verdict2 = "inexploitable"
    print(f"{f['id']:>3} {duree:6.1f} {mots:5d} {mps:6.2f} {mpp:7.2f} {ns:6.2f} {p80:8.1f} {p50:6.1f} {net:5.0f}  {verdict} | {verdict2} ({', '.join(causes2)})")
    if detail:
        print("      " + d["text"].strip()[:260])
