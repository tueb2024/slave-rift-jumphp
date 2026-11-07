#!/usr/bin/env python3
"""Pack the eight Blender RGBA plates into the active 96x128 FPS contract.
No audio or gameplay generation. Run render_shotgun.py in Blender first.
"""
from pathlib import Path
import hashlib, json
from PIL import Image
ROOT=Path(__file__).resolve().parents[1]
SOURCE=ROOT/'tools/blender/shotgun-source'
FRAME=(96,128)
POSES=('ready','ignition','fire','recoil','open','insert','close','settle')

def build():
    atlas=Image.new('RGBA',(768,128))
    for i,name in enumerate(POSES):
        plate=Image.open(SOURCE/f'pose-{i}.png').convert('RGBA')
        if plate.size!=(288,384):
            raise ValueError(f'{name}: unexpected source dimensions {plate.size}')
        frame=plate.resize(FRAME,Image.Resampling.LANCZOS)
        # Binary edge coverage matches the seven indexed arsenal atlases. Removing
        # low-alpha pixels prevents pale fringes on the dark game backgrounds.
        frame.putalpha(frame.getchannel('A').point(lambda a:255 if a>=128 else 0))
        atlas.alpha_composite(frame,(i*96,0))
    path=ROOT/'assets/sprites/arsenal/shotgun.png'
    atlas.quantize(colors=256,method=Image.Quantize.FASTOCTREE,dither=Image.Dither.NONE).save(path,optimize=True)
    paths=[SOURCE/'shotgun.blend',*(SOURCE/f'pose-{i}.png' for i in range(8)),path]
    manifest={'producer':'Blender Cycles, original procedural model','frame':[96,128], 'atlas':[768,128], 'poses':POSES,
              'files':{str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in paths}}
    (SOURCE/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
    print('Packed eight sawn-off poses:',path)

if __name__=='__main__':build()
