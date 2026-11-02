"""Original code-drawn 32x16 weapon pictograms; output artwork CC0-1.0."""
from pathlib import Path
from PIL import Image, ImageDraw
out=Path(__file__).resolve().parents[1]/'assets/sprites/secondaries'
out.mkdir(parents=True,exist_ok=True)
for kind in ['flame','rockets','pulse']:
 im=Image.new('RGBA',(32,16));d=ImageDraw.Draw(im)
 edge=(12,17,24,255);steel=(155,171,179,255);light=(223,230,221,255)
 if kind=='flame':
  d.rounded_rectangle((2,2,9,12),2,fill=edge);d.rectangle((3,3,8,10),fill=(185,54,44));d.rectangle((4,3,5,10),fill=(244,107,62))
  d.line([(7,11),(12,14),(17,11)],fill=(102,87,59),width=2)
  d.rectangle((10,5,27,9),fill=edge);d.rectangle((11,6,26,8),fill=steel);d.rectangle((16,9,19,13),fill=edge)
  d.rectangle((25,4,28,10),fill=(198,136,59));d.polygon([(28,6),(31,4),(30,7),(31,9),(28,8)],fill=(255,169,37))
 elif kind=='rockets':
  d.rectangle((2,4,28,10),fill=edge);d.rectangle((3,5,27,9),fill=(83,118,60));d.line((4,5,26,5),fill=(163,187,107))
  d.rectangle((1,3,4,11),fill=steel);d.rectangle((27,3,29,11),fill=(83,92,84));d.rectangle((11,10,15,14),fill=edge)
  d.line([(19,4),(19,1),(23,1)],fill=light,width=1);d.polygon([(6,7),(10,6),(13,7),(10,8)],fill=(238,174,63))
 else:
  d.rectangle((3,5,27,11),fill=edge);d.rectangle((4,6,26,10),fill=(54,87,125));d.rectangle((7,2,13,6),fill=(67,132,179))
  d.rectangle((10,11,14,14),fill=edge);d.rectangle((24,4,28,12),fill=steel)
  for x in [15,18,21]:d.rectangle((x,3,x+1,12),fill=(79,231,248))
  d.rectangle((28,6,30,10),fill=(174,252,255))
 im.save(out/(kind+'.png'))
 dim=im.copy();pixels=dim.load()
 for y in range(16):
  for x in range(32):
   r,g,b,a=pixels[x,y];pixels[x,y]=(int(r*.35),int(g*.35),int(b*.35),a)
 dim.save(out/(kind+'-dim.png'))
