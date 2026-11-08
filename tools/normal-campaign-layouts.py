"""Compare generated room footprints at a common scale; requires Pillow."""
from pathlib import Path
import json
from PIL import Image,ImageDraw
root=Path(__file__).resolve().parents[1]
folder=root/'docs/normal-progression'
rows=json.loads((folder/'geometry.json').read_text())['runs'][1]['stages']
out=Image.new('RGB',(1400,430),'#15151c');draw=ImageDraw.Draw(out)
for i,row in enumerate(rows):
    rooms=row['rooms']
    minx=min(r['bounds']['min'][0] for r in rooms);maxx=max(r['bounds']['max'][0] for r in rooms)
    minz=min(r['bounds']['min'][2] for r in rooms);maxz=max(r['bounds']['max'][2] for r in rooms)
    scale=1.05;ox=i*350+175-(minx+maxx)/2*scale;oy=225-(minz+maxz)/2*scale
    draw.text((i*350+12,12),f"MAP {i+1} / {row['geometry']['mainRooms']} salles principales",fill='#f1d4a5')
    draw.text((i*350+12,29),f"{row['encounters']} rencontres + boss / {row['totalEnemies']} ennemis",fill='#79d5d1')
    for room in rooms:
        bounds=room['bounds'];main=room['mainPath']
        rect=(ox+bounds['min'][0]*scale,oy+bounds['min'][2]*scale,ox+bounds['max'][0]*scale,oy+bounds['max'][2]*scale)
        draw.rectangle(rect,fill='#5d4a36' if main else '#264c51',outline='#deb77f' if main else '#60c6ca',width=1)
    draw.text((i*350+12,395),f"Surface : {row['geometry']['footprint']:.0f} / x{row['geometry']['surfaceRatio']:.2f}",fill='#f1d4a5')
draw.text((12,416),'Vue de dessus des salles generees, meme echelle pour les quatre maps. Seed de campagne : 42.',fill='#bdb7bd')
out.save(folder/'layouts.png')
