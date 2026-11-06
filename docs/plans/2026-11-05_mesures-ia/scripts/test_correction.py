import json, copy, time, threading, subprocess, urllib.request, sys, random

URL = "http://localhost:11434/api/chat"
LIM = 23000
peak = [0]
stop = [False]
abort = [False]


def vram():
    return int(subprocess.check_output(["nvidia-smi", "--query-gpu=memory.used", "--format=csv,noheader,nounits"]).decode().strip())


def post(d, t=300):
    r = urllib.request.Request(URL, json.dumps(d).encode(), {"Content-Type": "application/json"})
    return json.load(urllib.request.urlopen(r, timeout=t))


def unload(m):
    for _ in range(5):
        try:
            post({"model": m, "messages": [], "keep_alive": 0}, 60)
        except Exception:
            pass
        time.sleep(1)


def guard(m):
    while not stop[0]:
        v = vram()
        peak[0] = max(peak[0], v)
        if v > LIM:
            abort[0] = True
            print("GARDE-FOU VRAM", v, flush=True)
            unload(m)
            return
        time.sleep(0.5)


OFFRE = {
    "lignes": {
        "L1": dict(label="Dépose ancien revêtement, salon", unite="m2", qte=40, prix=12),
        "L2": dict(label="Parquet chêne, salon", unite="m2", qte=40, prix=60),
        "L3": dict(label="Parquet chêne, chambre", unite="m2", qte=15, prix=60),
        "L4": dict(label="Plinthes chêne", unite="ml", qte=35, prix=14),
        "L5": dict(label="Peinture murs, salon", unite="m2", qte=70, prix=35),
        "L6": dict(label="Main-d'oeuvre pose", unite="jour", qte=3, prix=600),
        "L7": dict(label="Évacuation gravats", unite="forfait", qte=1, prix=350),
        "L8": dict(label="Déplacement", unite="forfait", qte=1, prix=80),
    },
    "remise": 0,
    "remises_ligne": {},
}


def compact(o):
    lignes = "\n".join(f"{k}|{l['label']}|{l['qte']} {l['unite']}|{l['prix']}" for k, l in o["lignes"].items())
    return lignes + f"\nremise_globale|{o['remise']}%"


def fn(n, d, props, req):
    return {"type": "function", "function": {"name": n, "description": d, "parameters": {"type": "object", "properties": props, "required": req}}}


S = {"type": "string"}
N = {"type": "number"}
TOOLS = [
    fn("update_line", "Modifie quantité ou prix unitaire d'une ligne", {"line_id": S, "qte": N, "prix": N}, ["line_id"]),
    fn("add_line", "Ajoute une ligne", {"label": S, "unite": S, "qte": N, "prix": N}, ["label", "unite", "qte", "prix"]),
    fn("remove_line", "Supprime une ligne", {"line_id": S}, ["line_id"]),
    fn("set_global_discount", "Remise sur toute l'offre, en pour cent", {"percent": N}, ["percent"]),
    fn("set_line_discount", "Remise sur une seule ligne, en pour cent", {"line_id": S, "percent": N}, ["line_id", "percent"]),
    fn("ask_clarification", "Pose une question si la commande est ambiguë, incomplète ou n'est pas un ordre", {"question": S}, ["question"]),
]
SYS = (
    "Tu modifies une offre d'artisan selon une commande vocale. Appelle uniquement les outils nécessaires, en une fois. "
    "Ne calcule aucun total. Si la commande est ambiguë, incomplète ou n'est pas un ordre explicite, appelle ask_clarification et ne modifie rien."
)


def apply(calls):
    o = copy.deepcopy(OFFRE)
    asked = False
    for c in calls:
        f = c["function"]
        a = f["arguments"]
        n = f["name"]
        if isinstance(a, str):
            a = json.loads(a)
        if n == "update_line":
            l = o["lignes"][a["line_id"]]
            if a.get("qte"):
                l["qte"] = a["qte"]
            if a.get("prix"):
                l["prix"] = a["prix"]
        elif n == "add_line":
            o["lignes"]["N" + str(len(o["lignes"]))] = dict(label=a["label"], unite=a["unite"], qte=a["qte"], prix=a["prix"])
        elif n == "remove_line":
            del o["lignes"][a["line_id"]]
        elif n == "set_global_discount":
            o["remise"] = a["percent"]
        elif n == "set_line_discount":
            o["remises_ligne"][a["line_id"]] = a["percent"]
        elif n == "ask_clarification":
            asked = True
    return o, asked


def same(o, maj=None, sup=(), remise=0, rl=None):
    b = copy.deepcopy(OFFRE)
    for k, v in (maj or {}).items():
        b["lignes"][k].update(v)
    for k in sup:
        del b["lignes"][k]
    b["remise"] = remise
    b["remises_ligne"] = rl or {}
    return o == b


def chk_add(o):
    n = [v for k, v in o["lignes"].items() if k.startswith("N")]
    base = {k: v for k, v in o["lignes"].items() if not k.startswith("N")}
    return (
        base == OFFRE["lignes"]
        and len(n) == 1
        and n[0]["qte"] == 2
        and n[0]["prix"] == 720
        and "lectric" in n[0]["label"]
        and n[0]["unite"].lower().startswith("jour")
        and o["remise"] == 0
    )


C = [
    ("ajoute une remise de 5 %", lambda o, q: not q and same(o, remise=5), "ordre"),
    ("le parquet du salon c'est 65 francs le mètre carré", lambda o, q: not q and same(o, maj={"L2": {"prix": 65}}), "ordre"),
    ("finalement c'est 42 mètres carrés de parquet dans le salon", lambda o, q: not q and same(o, maj={"L2": {"qte": 42}}), "ordre"),
    ("enlève les plinthes", lambda o, q: not q and same(o, sup=["L4"]), "ordre"),
    ("ajoute deux jours d'électricien à 720 francs", lambda o, q: not q and chk_add(o), "ordre"),
    ("passe le parquet de la chambre à 70", lambda o, q: not q and same(o, maj={"L3": {"prix": 70}}), "ordre"),
    ("5 % de remise sur la peinture seulement", lambda o, q: not q and same(o, rl={"L5": 5}), "ordre"),
    ("le parquet du salon à 65, les plinthes à 16 et une remise de 3 %", lambda o, q: not q and same(o, maj={"L2": {"prix": 65}, "L4": {"prix": 16}}, remise=3), "ordre"),
    ("trois jours et demi de pose au lieu de trois", lambda o, q: not q and same(o, maj={"L6": {"qte": 3.5}}), "ordre"),
    ("change le prix du parquet", lambda o, q: q and same(o), "piege"),
    ("mets 70", lambda o, q: q and same(o), "piege"),
    ("le client va peut-être demander une remise", lambda o, q: same(o), "piege"),
]
PRIX = {"qwen3.8:27b": (0.40, 3.00), "gemma4:31b": (0.20, 0.40)}
WHISPER = 10 / 10 / 60 * 0.006  # 10 s de voix, x10, CHF 0.006/min
res = {}
for m in sys.argv[1:]:
    peak[0] = 0
    stop[0] = False
    abort[0] = False
    th = threading.Thread(target=guard, args=(m,), daemon=True)
    th.start()
    pin, pout = PRIX[m]
    rows = []
    for passe in range(3):
        for cmd, ok, typ in C:
            if abort[0]:
                break
            nonce = random.randint(10000, 99999)  # casse le cache de prefixe pour que prompt_eval_count compte tout
            msg = [
                {"role": "system", "content": f"Session {nonce}. {SYS}\n\nOFFRE\n{compact(OFFRE)}"},
                {"role": "user", "content": cmd},
            ]
            t = time.time()
            r = post({"model": m, "messages": msg, "tools": TOOLS, "stream": False, "think": False, "keep_alive": "5m", "options": {"temperature": 0, "num_ctx": 4096, "num_predict": 400}})
            d = time.time() - t
            calls = r["message"].get("tool_calls") or []
            try:
                o, q = apply(calls)
                good = bool(ok(o, q))
            except Exception:
                good = False
            i, e = r.get("prompt_eval_count", 0), r.get("eval_count", 0)
            rows.append(
                dict(passe=passe, cmd=cmd, typ=typ, ok=good, i=i, e=e, cost=i / 1e6 * pin + e / 1e6 * pout + WHISPER, d=round(d, 2), load=round(r.get("load_duration", 0) / 1e9, 1),
                     calls=[(c["function"]["name"], c["function"]["arguments"]) for c in calls], texte=r["message"].get("content", "")[:120])
            )
    stop[0] = True
    th.join(3)
    unload(m)
    res[m] = rows
    n = len(rows)
    g = sum(r["ok"] for r in rows)
    rr = [r for r in rows if r["load"] < 1] or rows
    print(f"\n=== {m}: {g}/{n} justes, pic VRAM {peak[0]} Mo, abort={abort[0]}")
    if rows:
        print(f" entree min {min(r['i'] for r in rows)} med {sorted(r['i'] for r in rows)[n // 2]} max {max(r['i'] for r in rows)} ; sortie med {sorted(r['e'] for r in rows)[n // 2]} max {max(r['e'] for r in rows)} ; duree med {sorted(r['d'] for r in rr)[len(rr) // 2]} s ; cout med CHF {sorted(r['cost'] for r in rows)[n // 2]:.5f} max CHF {max(r['cost'] for r in rows):.5f}")
    for cmd, _, ty in C:
        x = [r for r in rows if r["cmd"] == cmd]
        print(f"  {sum(r['ok'] for r in x)}/{len(x)} [{ty}] {cmd}")
    for r in rows:
        if not r["ok"] and r["passe"] == 0:
            print("   ECHEC", r["cmd"], "->", json.dumps(r["calls"], ensure_ascii=False)[:240], "| texte:", r["texte"])
    time.sleep(2)
json.dump(res, open(sys.argv[0] + ".json", "w", encoding="utf-8"), ensure_ascii=False, indent=1)
print("\nVRAM finale", vram())
