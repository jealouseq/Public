"""Наклейки для блока «Как забрать кота»: обрезка прозрачных полей, лёгкая очистка краёв, WebP.
Запуск: python3 scripts/prepare_steps.py <папка_с_исходниками>"""
import os, sys
import numpy as np
from PIL import Image

SRC = sys.argv[1]
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'theme', 'assets', 'img', 'stickers')
STEPS = {'step-head': 61, 'step-sleep': 62, 'step-paw': 63, 'step-carrier': 64, 'step-yarn': 66}


def find(n):
    for f in os.listdir(SRC):
        if os.path.splitext(f)[0] == str(n):
            return os.path.join(SRC, f)
    raise FileNotFoundError(n)


def defog(im, knee=60):
    a = np.array(im.convert('RGBA')).astype(float)
    al = a[..., 3]
    a[..., 3] = np.where(al >= knee, al, al * (al / knee) ** 2)
    return Image.fromarray(a.round().astype('uint8'), 'RGBA')


os.makedirs(OUT, exist_ok=True)
for name, n in STEPS.items():
    im = defog(Image.open(find(n)).convert('RGBA'))
    bbox = im.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox()
    im = im.crop(bbox)
    s = 560 / max(im.size)
    if s < 1:
        im = im.resize((round(im.width * s), round(im.height * s)), Image.LANCZOS)
    p = os.path.join(OUT, name + '.webp')
    im.save(p, 'WEBP', quality=84, method=6, alpha_quality=95)
    print(name, im.size, os.path.getsize(p) // 1024, 'KB')
