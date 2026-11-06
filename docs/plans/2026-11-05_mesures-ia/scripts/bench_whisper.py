import sys,time,subprocess
import os,torch
from pathlib import Path
os.environ["PATH"]=str(Path(torch.__file__).parent/"lib")+os.pathsep+os.environ["PATH"]
from faster_whisper import WhisperModel,BatchedInferencePipeline
audio=sys.argv[1]; dur=float(sys.argv[2])
def vram():
    return int(subprocess.check_output(['nvidia-smi','--query-gpu=memory.used','--format=csv,noheader,nounits']).decode().strip())
print('VRAM depart',vram(),'Mo')
def run(label,fn):
    t=time.time(); segs,info=fn(); n=0; w=0
    for s in segs: n+=1; w+=len(s.text.split())
    d=time.time()-t
    print(f"{label:34} {d:7.1f} s  x{dur/d:6.1f}  {w} mots  {n} seg  VRAM {vram()} Mo",flush=True)
for name,repo in [('turbo','mobiuslabsgmbh/faster-whisper-large-v3-turbo'),('large-v3','Systran/faster-whisper-large-v3')]:
    t=time.time(); m=WhisperModel(repo,device='cuda',compute_type='float16',local_files_only=True); print(name,'chargement',round(time.time()-t,1),'s',flush=True)
    p=BatchedInferencePipeline(model=m)
    run(name+' batch16 vad',lambda:p.transcribe(audio,language='fr',batch_size=16,vad_filter=True))
    run(name+' batch16 vad (2e)',lambda:p.transcribe(audio,language='fr',batch_size=16,vad_filter=True))
    run(name+' sequentiel beam5 sans batch',lambda:m.transcribe(audio,language='fr',beam_size=5,vad_filter=True))
    del p,m
    import gc;gc.collect()
