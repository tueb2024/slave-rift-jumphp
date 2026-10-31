#!/usr/bin/env python3
from pathlib import Path
from PIL import Image, ImageFilter
import numpy as np

ROOT = Path(__file__).resolve().parents[1] if 'tools' in Path(__file__).parts else Path('/tmp')
FRAME_W, FRAME_H = 144, 96

# Destination quadrilaterals and placement are scaled versions of the FPS staging
# used for the original 384x256 renders: the muzzle is narrowed and aimed toward
# the centre while the stock/grip stays large in the lower-right foreground.
CONFIG = {
    'colt':    dict(rot=8,  dw=93,  dh=62, dst=[(27,14),(93,4),(92,62),(0,54)],  place=(47,31), shadow=(3,3)),
    'mp44':    dict(rot=6,  dw=107, dh=66, dst=[(32,14),(107,3),(106,66),(0,58)], place=(33,27), shadow=(4,3)),
    'bar':     dict(rot=7,  dw=113, dh=68, dst=[(35,15),(113,4),(111,68),(0,60)], place=(26,25), shadow=(4,3)),
    'gatling': dict(rot=4,  dw=124, dh=71, dst=[(41,14),(124,7),(122,71),(0,62)], place=(16,24), shadow=(5,3)),
}


def find_coeffs(dst, src):
    matrix = []
    for p1, p2 in zip(dst, src):
        matrix.append([p1[0], p1[1], 1, 0, 0, 0, -p2[0]*p1[0], -p2[0]*p1[1]])
        matrix.append([0, 0, 0, p1[0], p1[1], 1, -p2[1]*p1[0], -p2[1]*p1[1]])
    a = np.matrix(matrix, dtype=float)
    b = np.array(src).reshape(8)
    return np.array(np.dot(np.linalg.pinv(a), b)).reshape(8)


def reframe(frame, name):
    cfg = CONFIG[name]
    box = frame.getchannel('A').getbbox()
    crop = frame.crop(box) if box else frame
    crop = crop.rotate(cfg['rot'], expand=True, resample=Image.Resampling.BICUBIC)
    src = [(0,0),(crop.width,0),(crop.width,crop.height),(0,crop.height)]
    coeffs = find_coeffs(cfg['dst'], src)
    warped = crop.transform((cfg['dw'], cfg['dh']), Image.Transform.PERSPECTIVE,
                            coeffs, resample=Image.Resampling.BICUBIC)

    alpha = warped.getchannel('A').point(lambda a: int(a * 0.42))
    shadow = Image.new('RGBA', warped.size, (0,0,0,0))
    shadow.putalpha(alpha)
    shadow = shadow.filter(ImageFilter.GaussianBlur(1.5))

    out = Image.new('RGBA', (FRAME_W, FRAME_H), (0,0,0,0))
    px, py = cfg['place']; sx, sy = cfg['shadow']
    out.alpha_composite(shadow, (px+sx, py+sy))
    out.alpha_composite(warped, (px, py))
    return out


def process(path, name):
    atlas = Image.open(path).convert('RGBA')
    if atlas.size != (FRAME_W * 4, FRAME_H):
        raise RuntimeError(f'{path}: expected 576x96 atlas, got {atlas.size}')
    out = Image.new('RGBA', atlas.size, (0,0,0,0))
    for i in range(4):
        frame = atlas.crop((i*FRAME_W, 0, (i+1)*FRAME_W, FRAME_H))
        fixed = reframe(frame, name)
        out.alpha_composite(fixed, (i*FRAME_W, 0))
    out.save(path, optimize=True)


def main(root):
    for name in CONFIG:
        for rel in [
            Path('assets/sprites/weapons') / f'fps-{name}.png',
            Path('games/slave-rift/assets/sprites/weapons') / f'fps-{name}.png',
        ]:
            path = root / rel
            if path.exists():
                process(path, name)
                print(f'reframed {rel}')

if __name__ == '__main__':
    import sys
    main(Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else ROOT)
