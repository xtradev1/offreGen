# Prix : page Infomaniak (CHF HT / M tokens). Whisper CHF 0.006 / min de generation.
PRIX={'Gemma 4 31B':(.20,.40),'Ministral 3 14B':(.30,.40),'Mistral Small 4':(.20,.75),'Qwen3.8 27B':(.40,3.00),
      'Apertus 70B':(.70,2.50),'Kimi K2.6':(.60,3.00),'Qwen3.5 397B':(.80,3.60)}
TOK_MIN=41406/146   # mesure : 41 406 tokens pour 2 h 26 (reunion a 3, majorant)
print(f"tokens/min parole = {TOK_MIN:.0f}, mots/min = {26607/146:.0f}")
print(f"verif audio test: 600 s, large-v3 batch x65 ; parole {87.8/150:.0%} de l'audio ; 1277 mots/10 min={127.7:.0f} mots/min contre 182")
def offre(minutes,S,N,r,c,O,R,K,xw,patch=True,extraction=False,E=1500):
    T=TOK_MIN*minutes; inp=out=0
    base=S+T
    if extraction:              # etape 1 : transcript -> JSON de besoins, puis on oublie le transcript
        inp+=2000+T; out+=E; base=S+E
    for i in range(N+1):        # chaque tour renvoie tout l'historique, sans remise de cache
        inp+=base+i*(c+r); out+=c if i<N else O
    pic=base+N*(c+r)+O
    out+=R
    # corrections : 1 min de voix, on renvoie contexte court + offre, 2 appels d'outil, sortie = patch ou offre entiere
    for k in range(K):
        ctx=S+(E if extraction else T)+O+TOK_MIN
        for i in range(3):
            inp+=ctx+i*(c+r); out+=c if i<2 else (300 if patch else O)
    whisper=(minutes+K)/xw*0.006
    return inp,out,pic,whisper
SC={'Sobre':dict(S=3000,N=8,r=300,c=80,O=2000,R=0,K=2,xw=30),
    'Central':dict(S=5000,N=15,r=500,c=100,O=3000,R=2000,K=4,xw=10),
    'Lourd':dict(S=8000,N=40,r=2000,c=150,O=6000,R=20000,K=10,xw=5,patch=False)}
for nom,p in SC.items():
    print('\n==',nom,p)
    for mins in (15,30,60):
        i,o,pic,w=offre(mins,**p)
        print(f" {mins:>2} min: entree {i:>9,.0f} sortie {o:>7,.0f} pic contexte {pic:>7,.0f} whisper CHF {w:.4f}")
    print(f" {'modele':16}"+''.join(f"{m:>9} min" for m in (15,30,60)))
    for m,(a,b) in PRIX.items():
        l=[]
        for mins in (15,30,60):
            i,o,pic,w=offre(mins,**p); l.append(i/1e6*a+o/1e6*b+w)
        print(f" {m:16}"+''.join(f"{x:13.3f}" for x in l))
print('\n== Variante extraction (Central, 30 min)')
for m in ('Gemma 4 31B','Qwen3.8 27B','Kimi K2.6'):
    a,b=PRIX[m]
    i,o,pic,w=offre(30,**SC['Central']); n=i/1e6*a+o/1e6*b+w
    i2,o2,pic2,w2=offre(30,**SC['Central'],extraction=True); e=i2/1e6*a+o2/1e6*b+w2
    print(f" {m:14} naif {n:.3f} ({i:,.0f} in)  extraction {e:.3f} ({i2:,.0f} in) gain {1-e/n:.0%}")
print('\n== Mensuel CHF HT, 30 min')
V=(10,15,20,30,100)
print(f" {'':26}"+''.join(f"{v:>9}" for v in V))
for sc in ('Sobre','Central','Lourd'):
    for m in ('Gemma 4 31B','Mistral Small 4','Qwen3.8 27B','Kimi K2.6','Qwen3.5 397B'):
        a,b=PRIX[m]; i,o,pic,w=offre(30,**SC[sc]); c=i/1e6*a+o/1e6*b+w
        print(f" {sc:8}{m:18}"+''.join(f"{c*v:9.2f}" for v in V))
print('\n== Whisper seul, 30 min + 4 corrections')
for x in (50,35,10,5,1): print(f" x{x}: {(34)/x*0.006:.4f} CHF")
print('\n== Part de la sortie, Central 30 min, Kimi')
i,o,_,w=offre(30,**SC['Central']); print(f" entree {i/1e6*.6:.3f} sortie {o/1e6*3:.3f} whisper {w:.3f}")
