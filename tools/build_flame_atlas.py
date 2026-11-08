"""Extract original horizontal jet wisps into the existing eight-cell VFX contract."""
from pathlib import Path
from PIL import Image
import json,hashlib
root=Path(__file__).resolve().parents[1]
source=root/'tools/flame-source/jet-board.png'
board=Image.open(source).convert('RGBA');sheet=Image.new('RGBA',(768,128))
for i in range(8):
    cell=board.crop((round(i*board.width/8),0,round((i+1)*board.width/8),board.height))
    alpha=cell.getchannel('A').point(lambda a:255 if a>24 else 0)
    box=alpha.getbbox()
    if box is None:raise ValueError('Empty flame cell')
    cell=cell.crop(box).resize((92,58),Image.Resampling.LANCZOS)
    sheet.alpha_composite(cell,(i*96+2,35))
sheet.putalpha(sheet.getchannel('A').point(lambda a:255 if a>=112 else 0))
path=root/'assets/sprites/arsenal/flame.png';sheet.save(path,optimize=True)
manifest={'source':str(source.relative_to(root)),'atlas':[768,128],'frame':[96,128],
 'files':{str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for p in [source,path]}}
(root/'tools/flame-source/manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
print('Built eight flowing jet wisps',board.size)
