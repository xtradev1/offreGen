C={'central':.245,'lourd':1.608}; KIMI={'central':.344,'lourd':2.246}
FEE=.03; FIXE=300
print("Estimation offres/an, solo: CA/ticket/conversion")
for nom,ca,t,part,conv in [('renovation solo',200000,8000,1,.4),('depannage solo',200000,1500,.5,.5),('equipe 5 renovation',800000,15000,1,.35)]:
    jobs=ca/t; of=jobs*part/conv; print(f" {nom:22} {jobs:5.0f} chantiers/an -> {of:5.0f} offres/an = {of/12:4.1f}/mois")
print("\nMarge par client (Qwen3.8, 30 min)")
for prix,n,ov in [(29,10,0),(49,20,0),(89,50,0),(89,100,50)]:
    ca=prix+ov*2.5
    for sc,c in C.items():
        v=n*c+ca*FEE; print(f" {prix} CHF {n:>3} offres ({ca:>5.0f} CHF) {sc:8} cout {v:6.2f} marge {ca-v:6.2f} ({(ca-v)/ca:4.0%})")
print("\nClients necessaires (fixe %d + revenu cible), 15 offres/mois, central Qwen3.8"%FIXE)
for prix in (29,49,79):
    m=prix-15*C['central']-prix*FEE
    print(f" {prix} CHF marge {m:5.2f}: "+', '.join(f"{t} CHF -> {(t+FIXE)/m:4.0f}" for t in (1000,5000,10000)))
print("\nPire cas Kimi lourd, 20 offres a 49:",round(49-20*KIMI['lourd']-49*FEE,2),"; 50 offres a 89:",round(89-50*KIMI['lourd']-89*FEE,2))
print("Depassement 2.50: Qwen lourd marge",round(2.5*(1-FEE)-1.608,2),"Kimi lourd",round(2.5*(1-FEE)-2.246,2))
print("Temps de parole: 15 offres x 30 min =",15*.5,"h ; 30 offres =",30*.5,"h ; 100 offres =",100*.5,"h")
