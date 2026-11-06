TM = 41406 / 146
P = {"Gemma 4 31B": (0.20, 0.40), "Qwen3.8 27B": (0.40, 3.00), "Kimi K2.6": (0.60, 3.00)}
WH = 0.006

# Correction mesuree : 874 tokens pour 8 lignes, 42 en sortie. Tokens par ligne estimes.
BASE, PAR_LIGNE = 874 - 8 * 20, 20


def correction(nl, x, secs=10, out=42):
    return BASE + nl * PAR_LIGNE, out, secs / 60 / x * WH


def scenario(nom, minutes, nl, ambig, cand, out_ligne, K, x, raison=0, relances=0):
    T = TM * minutes
    # Appel 1 : transcription -> fiche JSON, sans outils. Appel 2 : lignes ambigues + candidats trouves par le code.
    i1, o1 = 1000 + T, nl * out_ligne + raison
    i2, o2 = 1000 + ambig * cand * 30, ambig * 25
    i = (i1 + i2) * (1 + relances)
    o = (o1 + o2) * (1 + relances)
    ci, co, cw = correction(nl, x)
    w = minutes / x * WH
    print(f"\n== {nom}: {minutes} min, {nl} lignes, {ambig} ambigues, {K} corrections, Whisper x{x}, relances {relances}")
    print(f" entree {i:,.0f}  sortie {o:,.0f}  pic {max(i1, i2):,.0f}  whisper {w:.4f}  correction: {ci} in, {co} out")
    for m, (a, b) in P.items():
        g = i / 1e6 * a + o / 1e6 * b
        c = K * (ci / 1e6 * a + co / 1e6 * b + cw)
        print(f" {m:14} generation {g:.4f} whisper {w:.4f} corrections {c:.4f} TOTAL {g + w + c:.3f}")


scenario("Central", 30, 25, 5, 3, 60, 4, 10)
scenario("Lourd", 60, 60, 20, 5, 60, 50, 5, raison=5000, relances=1)
scenario("Court", 15, 15, 3, 3, 60, 4, 10)

print("\n== Comparaison d'une correction, 30 min, Qwen3.8")
ctx = 5000 + TM * 30 + 3000 + TM
i = ctx * 3 + 600 * 3
print(f" avec boucle, patch: {i:,.0f} in -> {i / 1e6 * 0.4 + 500 / 1e6 * 3 + 1 / 10 * WH:.4f} CHF")
ctx = 8000 + TM * 30 + 6000 + TM
i = ctx * 3 + 2150 * 3
print(f" lourd, regeneration: {i:,.0f} in -> {i / 1e6 * 0.4 + 6300 / 1e6 * 3 + 1 / 5 * WH:.4f} CHF")
ci, co, cw = correction(8, 10)
print(f" mesure 8 lignes: {ci} in -> {ci / 1e6 * 0.4 + co / 1e6 * 3 + cw:.5f} CHF")
ci, co, cw = correction(30, 10)
print(f" estime 30 lignes: {ci} in -> {ci / 1e6 * 0.4 + co / 1e6 * 3 + cw:.5f} CHF, 50 fois {50 * (ci / 1e6 * 0.4 + co / 1e6 * 3 + cw):.3f}")

print("\n== Marge abonnement (Qwen3.8, frais 3 %)")
for nom, c in [("central", 0.0), ("lourd", 0.0)]:
    pass
for prix, n in [(49, 20), (89, 50)]:
    for nom, c in [("central", 0.040), ("lourd", 0.150)]:
        v = n * c + prix * 0.03
        print(f" {prix} CHF {n} offres {nom:8} cout offre {c:.3f} total {v:.2f} marge {prix - v:.2f} ({(prix - v) / prix:.0%})")
