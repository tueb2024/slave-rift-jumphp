"""Portable deterministic faceted rendering of the same original model primitives.
Requires Pillow for developer fallback only; Blender pipeline has no pip dependency.
"""
from pathlib import Path
import math,json,hashlib
from PIL import Image,ImageDraw
from art_spec import mecha,weapon,WEAPONS,PALETTE
from raider_parts import raider
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'assets/generated'

def render(parts,size,yaw=0,weapon_view=False):
    w,h=size; polys=[]; c=math.cos(yaw);s=math.sin(yaw)
    scale=h/(1.3 if weapon_view else 3.05)
    def proj(v):
        x,y,z=v;rx=c*x+s*y;ry=-s*x+c*y
        return (w/2+rx*scale,h*(.60 if weapon_view else .91)-z*scale-ry*scale*.16),ry
    for part in parts:
        p=part['p'];d=part['s'];verts=[(p[0]+x*d[0]/2,p[1]+y*d[1]/2,p[2]+z*d[2]/2) for x,y,z in [(-1,-1,-1),(1,-1,-1),(1,1,-1),(-1,1,-1),(-1,-1,1),(1,-1,1),(1,1,1),(-1,1,1)]]
        for face,shade in [([0,1,5,4],.65),([1,2,6,5],.83),([2,3,7,6],1),([3,0,4,7],.8),([4,5,6,7],1.12)]:
            xy=[proj(verts[i]) for i in face];col=tuple(min(255,int(v*shade)) for v in part['c'])
            polys.append((sum(v[1] for v in xy)/4,[v[0] for v in xy],col))
    im=Image.new('RGBA',size);draw=ImageDraw.Draw(im)
    for _,poly,col in sorted(polys,key=lambda x:x[0]): draw.polygon(poly,fill=col+(255,),outline=tuple(int(v*.50) for v in col)+(255,))
    return im

def main():
    for kind in PALETTE:
        atlas=Image.new('RGBA',(768,1120))
        for row in range(10):
            for direction in range(8):
                # Direction zero faces the viewer; death columns are time frames.
                im=render(raider(row,direction) if kind=='siege' else mecha(kind,row,direction),(96,112),0 if row==9 else direction*math.pi/4)
                atlas.paste(im,(direction*96,row*112))
        folder=OUT/('bosses' if kind=='siege' else 'mechas');folder.mkdir(parents=True,exist_ok=True);atlas.quantize(colors=128,method=Image.Quantize.FASTOCTREE).save(folder/(kind+'.png'),optimize=True)
    for kind in WEAPONS:
        atlas=Image.new('RGBA',(576,96))
        for frame in range(4):atlas.paste(render(weapon(kind,frame),(144,96),-.46,True),(frame*144,0))
        folder=OUT/'weapons';folder.mkdir(parents=True,exist_ok=True);atlas.quantize(colors=128,method=Image.Quantize.FASTOCTREE).save(folder/(kind+'.png'),optimize=True)
        icon=render(weapon(kind),(64,32),1.2,True).resize((32,16),Image.Resampling.LANCZOS)
        icon.save(folder/(kind+'-hud.png'))
        dark=icon.copy();dark.putdata([tuple(int(c*.35) for c in p[:3])+(p[3],) for p in icon.getdata()]);dark.save(folder/(kind+'-hud-dim.png'))
    manifest={'version':1,'renderer':'portable-faceted-fallback','enemyFrame':[96,112],'enemyAtlas':[768,1120], 'weaponFrame':[144,96],'files':{str(p.relative_to(OUT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(OUT.rglob('*.png'))}}
    (OUT/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
if __name__=='__main__':main()
