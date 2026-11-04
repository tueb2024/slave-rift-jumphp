"""Render exported real ProceduralScore/RhythmClock events with runtime PCM and 80ms release."""
import pathlib,json,wave,sys
import numpy as np
root=pathlib.Path(__file__).resolve().parents[1]; rate=22050
events=json.loads(pathlib.Path(sys.argv[1]).read_text(encoding='utf-8-sig'))
mix=np.zeros(rate*50); manifest=json.loads((root/'assets/audio/manifest.json').read_text())
active={}; voices=[]
for time,voice,note in events:
    key=voice if note is None else f'{voice}_{note}'
    with wave.open(str(root/manifest[key]['file']),'rb') as w: data=np.frombuffer(w.readframes(w.getnframes()),dtype='<i2').astype(float)/32768
    start=round(time*rate)
    if note is not None and voice in active:
        old=voices[active[voice]]; cutoff=start-old[0]; release=round(.08*rate)
        if cutoff<len(old[1]):
            end=min(len(old[1]),cutoff+release)
            old[1][cutoff:end]*=np.linspace(1,0,end-cutoff);old[1][end:]=0
    voices.append([start,data]);active[voice]=len(voices)-1
for start,data in voices:
    length=min(len(data),len(mix)-start);mix[start:start+length]+=data[:length]
peak=float(max(abs(mix)))
assert 0.02<peak<1, f'Invalid peak {peak}'
dest=root/'docs/audio';dest.mkdir(parents=True,exist_ok=True)
with wave.open(str(dest/'slave-rift-music-demo.wav'),'wb') as w:
    w.setparams((1,2,rate,0,'NONE','not compressed'));w.writeframes(np.round(mix*32767).astype('<i2').tobytes())
report=dict(seconds=len(mix)/rate,peak=peak,rms=float(np.sqrt(np.mean(mix**2))),clipped=int(np.sum(abs(mix)>=1)),events=len(events),samples=len(manifest))
(dest/'analysis.json').write_text(json.dumps(report,indent=2)+'\n');print(report)
