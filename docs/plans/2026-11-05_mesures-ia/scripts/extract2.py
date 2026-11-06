import re,sys,html
s=open(sys.argv[1],encoding='utf-8',errors='replace').read()
def clean(x): return re.sub(r'\s+',' ',html.unescape(re.sub(r'<[^>]+>',' ',x)))
seen=set()
for m in re.finditer(r'billed per minute of generation',s):
    t=clean(s[max(0,m.start()-300):m.start()+1500])[:1000]
    if t[:200] not in seen:
        seen.add(t[:200]); print('-- pos',m.start(),'\n',t,'\n')
for pat in ['How does per-minute billing work','excl','VAT']:
    ms=[m.start() for m in re.finditer(pat,s)]
    print('==',pat,len(ms))
    for p in ms[:3]:
        print('  ',clean(s[max(0,p-120):p+220])[:260])
print('== cache words', {w:len(re.findall(w,s,re.I)) for w in ['cached','caching','prompt cache','cache hit','batch']})
print('== timing words', {w:len(re.findall(w,s,re.I)) for w in ['real-time','real time','audio duration','duration of the audio','length of the audio']})
