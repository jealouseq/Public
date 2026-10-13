"""Кадры анимации кота для прокрутки: уменьшение до 800×600 и пересборка WebP.
Запуск: python3 scripts/prepare_frames.py <папка frames_webp с подпапками bottom_peek и right_peek>"""
import os, sys
from PIL import Image

SRC = sys.argv[1]
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'theme', 'assets', 'anim')
NAMES = {'bottom_peek': 'bengal-bottom', 'right_peek': 'bengal-right'}
W, H = 800, 600

for src_name, out_name in NAMES.items():
    d_in = os.path.join(SRC, src_name); d_out = os.path.join(OUT, out_name)
    os.makedirs(d_out, exist_ok=True)
    total = 0; n = 0
    for f in sorted(os.listdir(d_in)):
        if not f.endswith('.webp'):
            continue
        im = Image.open(os.path.join(d_in, f)).convert('RGBA').resize((W, H), Image.LANCZOS)
        p = os.path.join(d_out, f)
        im.save(p, 'WEBP', quality=74, method=6, alpha_quality=88)
        total += os.path.getsize(p); n += 1
    print(out_name, n, 'кадров', total // 1024, 'КБ')
