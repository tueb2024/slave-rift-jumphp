"""Original canon-scié poses and deterministic PCM effects; build-time only, no runtime generator."""
from pathlib import Path
from PIL import Image, ImageDraw
import math, random, struct, wave
root=Path(__file__).resolve().parents[1]
im=Image.new('RGBA',(384,128))
for frame in range(4):
    pose=Image.new('RGBA',(96,128));d=ImageDraw.Draw(pose)
    drop=[0,8,14,22][frame]
    d.polygon([(29,128),(67,128),(61,94+drop),(35,94+drop)],fill='#72452d',outline='#241c23')
    d.polygon([(25,117),(71,117),(62,77+drop),(34,77+drop)],fill='#434957',outline='#12141d')
    for x in [34,50]:
        muzzle=42+drop+(12 if frame==3 else 0)
        d.polygon([(x-6,101+drop),(x+15,101+drop),(x+9,muzzle),(x,muzzle)],fill='#737d86',outline='#171c26')
        d.line([(x+2,muzzle+6),(x-1,99+drop)],fill='#bbc3c4',width=2)
        d.ellipse((x-1,muzzle-4,x+10,muzzle+7),fill='#171c22',outline='#b6b8ad',width=2)
    d.rectangle((42,77+drop,54,101+drop),fill='#292d3a')
    d.polygon([(17,128),(32,128),(38,107+drop),(26,103+drop)],fill='#ac825f',outline='#362c2e')
    d.polygon([(65,128),(82,128),(71,105+drop),(60,109+drop)],fill='#ac825f',outline='#362c2e')
    if frame==1:
        for x in [37,53]:d.polygon([(x-13,42),(x-4,37),(x-6,20),(x+3,30),(x+12,18),(x+11,37),(x+18,44),(x+6,52)],fill='#ffd078')
    im.alpha_composite(pose,(frame*96,0))
path=root/'assets/sprites/arsenal/shotgun.png';path.parent.mkdir(parents=True,exist_ok=True);im.save(path)
audio=root/'assets/audio/combat';audio.mkdir(parents=True,exist_ok=True)
for name,duration,freq in [('shotgun_fire',.34,75),('boss_charge',.35,380),('boss_launch',.2,140),('boss_impact',.18,90),('gate_open',.45,220)]:
    rng=random.Random(42);rate=44100;pcm=bytearray()
    for i in range(int(duration*rate)):
        t=i/rate;env=min(1,t/.003)*math.exp(-6*t/duration)
        noise=rng.uniform(-1,1)
        val=env*((.45*noise+.45*math.sin(2*math.pi*freq*t)) if name=='shotgun_fire' else .55*math.sin(2*math.pi*(freq+160*t/duration)*t))
        pcm.extend(struct.pack('<h',round(max(-1,min(1,val))*32767)))
    with wave.open(str(audio/(name+'.wav')),'wb') as w:w.setparams((1,2,rate,0,'NONE','not compressed'));w.writeframes(pcm)
