import re,sys,html
s=open(sys.argv[1],encoding='utf-8',errors='replace').read()
i=s.find('PricesSection-module')
t=html.unescape(re.sub(r'<[^>]+>','|',s[i:])); t=re.sub(r'[|\s]*\|[|\s]*','|',t)
pat=re.compile(r'([\w.\-]+/[\w.\-]+)\|Input token:\|CHF\|([\d.]+)\|/ ?1M tokens\|Output token:\|CHF\|([\d.]+)')
seen=set()
for m in pat.finditer(t):
    if m.group(1) not in seen:
        seen.add(m.group(1)); print(f"{m.group(1):45} in {m.group(2):>6} out {m.group(3):>6}")
print(len(seen),'modeles')
for w in ['GPU','NVIDIA','tool','function call','rate limit','requests per','per minute']:
    ms=[m.start() for m in re.finditer(w,t)]
    print('==',w,len(ms))
    for p in ms[:3]: print('   ',t[max(0,p-120):p+160].replace('\n',' '))
