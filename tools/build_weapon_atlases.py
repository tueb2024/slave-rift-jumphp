#!/usr/bin/env python3
"""Rebuild active 576x96 atlases from attributed CC0 sources; requires Pillow."""
from pathlib import Path
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
SOURCES = ROOT / 'tools/weapon-sources'
SIZE = (144, 96)

def flash(im, muzzle, radius):
    x, y = muzzle
    d = ImageDraw.Draw(im)
    d.polygon([(x-radius,y-2),(x-4,y-4),(x-2,y-radius),(x+2,y-5),(x+radius,y-7),(x+5,y),(x+radius,y+4),(x+3,y+3),(x,y+8),(x-3,y+3)], fill=(255,119,28,255))
    d.polygon([(x-6,y),(x-2,y-4),(x,y-8),(x+3,y-2),(x+7,y),(x+2,y+3),(x-2,y+3)], fill=(255,231,128,255))
    d.rectangle((x-1,y-2,x+2,y+1), fill=(255,255,225,255))

def overlay_row(row, crop, dest, position):
    source = Image.open(ROOT / 'assets/sprites/weapons/fps-weapons-cc0.png').convert('RGBA')
    cell = source.crop((0,row*64,64,(row+1)*64)).crop(crop)
    im = Image.new('RGBA', SIZE)
    im.alpha_composite(cell.resize(dest,Image.Resampling.NEAREST), position)
    return im

def build():
    pistol=overlay_row(1,(16,32,49,64),(132,128),(6,-32))
    smg=overlay_row(2,(16,36,49,64),(165,140),(-10,-44))
    rifle_source=Image.open(SOURCES/'brn10.png').convert('RGBA')
    rifle=[]
    for col in (0,0,1,0):
        cell=rifle_source.crop((col*503,0,(col+1)*503,409))
        im=Image.new('RGBA',SIZE)
        im.alpha_composite(cell.crop((130,85,503,409)).resize((108,94),Image.Resampling.NEAREST),(36,2))
        rifle.append(im)
    gatling=[]
    for phase in ('ready','ready','spin','spin'):
        raw=Image.open(SOURCES/f'gatling-{phase}.png').convert('RGBA')
        im=Image.new('RGBA',SIZE)
        im.alpha_composite(raw.crop((100,30,370,280)).resize((108,100),Image.Resampling.NEAREST),(32,-4))
        gatling.append(im)
    flash(gatling[2],(50,22),15)
    frames={'colt':[pistol.copy() for _ in range(4)], 'mp44':[smg.copy() for _ in range(4)], 'bar':rifle, 'gatling':gatling}
    flash(frames['colt'][2],(72,28),9)
    flash(frames['mp44'][2],(72,26),12)
    for name,cells in frames.items():
        sheet=Image.new('RGBA',(576,96))
        for i,cell in enumerate(cells): sheet.alpha_composite(cell,(i*144,0))
        sheet.save(ROOT/f'assets/sprites/weapons/fps-{name}.png', optimize=True)

if __name__=='__main__': build()
