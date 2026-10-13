"""Убирает из кадров анимации пустые (кот вне кадра) и кадры «ухода», чтобы при прокрутке кот не исчезал и не оставлял пустой экран.
Работает на месте в theme/assets/anim/<набор>, кадры перенумеровываются подряд с 0001.
Запуск: python3 scripts/trim_frames.py"""
import os, glob
import numpy as np
from PIL import Image

ROOT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'theme', 'assets', 'anim')
# набор: (минимум видимых пикселей, последний кадр, который оставляем; 0 = до конца)
SETS = {'bengal-bottom': (3000, 63), 'bengal-right': (3000, 40)}

for name, (min_px, last) in SETS.items():
    d = os.path.join(ROOT, name)
    files = sorted(glob.glob(os.path.join(d, 'frame_*.webp')))
    keep = []
    for i, f in enumerate(files, 1):
        if last and i > last:
            continue
        a = np.array(Image.open(f).convert('RGBA'))[..., 3]
        if int((a > 20).sum()) >= min_px:
            keep.append(f)
    tmp = []
    for f in files:
        if f not in keep:
            os.remove(f)
    for k, f in enumerate(keep, 1):
        t = os.path.join(d, f'tmp_{k:04d}.webp'); os.rename(f, t); tmp.append(t)
    for k, t in enumerate(tmp, 1):
        os.rename(t, os.path.join(d, f'frame_{k:04d}.webp'))
    print(name, len(files), '->', len(keep), 'кадров')
