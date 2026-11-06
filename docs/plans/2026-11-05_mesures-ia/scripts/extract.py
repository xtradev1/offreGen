import re,sys,html
s=open(sys.argv[1],encoding='utf-8',errors='replace').read()
for key in ['moonshotai/Kimi-K2.6','Qwen/Qwen3.5-397B-A17B-FP8','Whisper V3</p>']:
    i=s.find(key)
    t=re.sub(r'<[^>]+>','|',s[i:i+2500]); t=re.sub(r'\|+','|',html.unescape(t))
    print('==',key,'\n',t[:420],'\n')
i=s.find('facturation-content')
t=re.sub(r'<[^>]+>',' ',s[i:i+2500]); print('== facturation\n',re.sub(r'\s+',' ',html.unescape(t))[:1100])
m=re.search(r'(CHF|EUR|€)[^<]{0,12}',s); print('\n== devise', m.group(0) if m else None)
for w in ['excl','VAT','Input','Output']:
    print(w, len(re.findall(w,s)))
