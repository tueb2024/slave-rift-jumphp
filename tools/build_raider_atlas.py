from pathlib import Path
from PIL import Image
import json,hashlib
root=Path(__file__).resolve().parents[1];source=root/'tools/blender/raider-source';atlas=Image.new('RGBA',(768,1120))
for row in range(10):
 for direction in range(8):
    tile=Image.open(source/f'{row}-{direction}.png').convert('RGBA').resize((96,112),Image.Resampling.LANCZOS)
    tile.putalpha(tile.getchannel('A').point(lambda a:255 if a>=128 else 0));atlas.alpha_composite(tile,(direction*96,row*112))
path=root/'assets/generated/bosses/siege.png';atlas.quantize(colors=256,method=Image.Quantize.FASTOCTREE).save(path,optimize=True)
manifest=root/'assets/generated/manifest.json';m=json.loads(manifest.read_text());m['files']['bosses/siege.png']=hashlib.sha256(path.read_bytes()).hexdigest();m['bossProducer']='Original Blender Cycles agile raider, 2026-11-08';manifest.write_text(json.dumps(m,indent=2)+'\n')
files=[source/'raider.blend',path];(source/'manifest.json').write_text(json.dumps({'files':{str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for p in files}},indent=2)+'\n')
print('Packed agile boss: eight directions, ten rows')
