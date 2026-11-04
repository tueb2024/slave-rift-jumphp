"""Offline SFZ rendering. Usage: python tools/prepare_music.py FREEPATS_DIR FFMPEG.
Sources are pinned in assets/audio/manifest.json; no synthesis at runtime.
Requires numpy. The upstream SFZ and FLAC stay outside the game package.
"""
import sys, pathlib, re, subprocess, urllib.request, hashlib, json, wave
import numpy as np

root = pathlib.Path(__file__).resolve().parents[1]
source = pathlib.Path(sys.argv[1]); ffmpeg = sys.argv[2]
out = root/'assets/audio'; out.mkdir(parents=True, exist_ok=True)
manifest = {}
def save(name, data, provenance):
    data = np.asarray(data, dtype=float)
    data -= data.mean()
    n = min(220, len(data)//4)
    data[:n] *= np.linspace(0,1,n); data[-n:] *= np.linspace(1,0,n)
    pcm = np.round(np.clip(data,-.99,.99)*32767).astype('<i2')
    path = out/(name+'.wav')
    with wave.open(str(path),'wb') as w:
        w.setparams((1,2,22050,0,'NONE','not compressed')); w.writeframes(pcm.tobytes())
    manifest[name] = dict(file='assets/audio/'+path.name, frames=len(pcm), rate=22050,
        sha256=hashlib.sha256(path.read_bytes()).hexdigest(), **provenance)

drum_ref = '85fbecf1bec32553395625ea659e2a56dfd7c0e1'
for voice, path, gain in [('kick','bd8/BD5025.WAV',.55),('snare','sd8/SD5050.WAV',.28),('hat','ch8/CH.WAV',.15),('openhat','oh8/OH25.WAV',.14),('clap','cp8/CP.WAV',.23)]:
    url = 'https://raw.githubusercontent.com/tidalcycles/sounds-tr808-fischer/'+drum_ref+'/'+path
    raw = urllib.request.urlopen(url).read()
    tmp = out/'source.wav'; tmp.write_bytes(raw)
    pcm = subprocess.check_output([ffmpeg,'-v','error','-i',str(tmp),'-f','f32le','-ac','1','-ar','22050','-'])
    data = np.frombuffer(pcm,dtype='<f4').copy(); data *= gain/max(abs(data))
    save(voice,data,dict(source=url, source_sha256=hashlib.sha256(raw).hexdigest(),license='CC0-1.0'))
    tmp.unlink()

sfz = next(source.glob('*.sfz')).read_text()
regions = []
for region in sfz.split('<region>')[1:]:
    r = {k:int(v) for k,v in re.findall(r'(lokey|hikey|pitch_keycenter|loop_start|loop_end)=(\d+)',region)}
    r['sample'] = re.search(r'sample=(.+)',region)[1].strip(); regions.append(r)
cache = {}
for voice,lo,hi,seconds,gain in [('bass',33,47,1.35,.24),('lead',60,76,.85,.16),('counter',41,56,1.05,.12)]:
    for note in range(lo,hi+1):
        r = next(r for r in regions if r['lokey']<=note<=r['hikey'])
        path = source/r['sample']
        if str(path) not in cache:
            raw = subprocess.check_output([ffmpeg,'-v','error','-i',str(path),'-f','f32le','-ac','1','-ar','44100','-'])
            cache[str(path)] = np.frombuffer(raw,dtype='<f4')
        data = cache[str(path)]; ratio = 2**((note-r['pitch_keycenter'])/12)
        positions = np.arange(int(seconds*22050))*2*ratio
        end = r['loop_end']+1; start=r['loop_start']
        positions = np.where(positions<end,positions,start+(positions-start)%(end-start))
        data = np.interp(positions,np.arange(len(data)),data)
        if voice=='bass': data=np.convolve(data,np.ones(17)/17,mode='same')
        data *= gain/max(abs(data))
        release=int(.12*22050); data[-release:] *= np.linspace(1,0,release)
        save(f'{voice}_{note}',data,dict(source=r['sample'],root=r['pitch_keycenter'],midi=note,license='CC0-1.0'))
(out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
print(len(manifest),'samples;',sum(p.stat().st_size for p in out.glob('*.wav')),'bytes')
