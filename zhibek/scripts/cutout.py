"""Вырезка котов из студийных кадров (rembg) + очистка цвета по краю шерсти (pymatting).

Зависимости: pip install rembg onnxruntime pillow numpy pymatting
Модели rembg кладутся в ~/.u2net/ (isnet-general-use.onnx) или скачиваются автоматически.

Пример:
  python3 cutout.py breeds.webp 10,60,480,950 abyssinian.png
  # аргументы: исходный кадр, рамка x0,y0,x1,y1, имя результата
"""
import sys
import numpy as np
from PIL import Image
from rembg import remove, new_session
from pymatting import estimate_foreground_ml

src, box, out = sys.argv[1], [int(v) for v in sys.argv[2].split(',')], sys.argv[3]
model = sys.argv[4] if len(sys.argv) > 4 else 'isnet-general-use'
im = Image.open(src).convert('RGB').crop(tuple(box))
alpha = np.asarray(remove(im, session=new_session(model), only_mask=True, post_process_mask=True)).astype(np.float64) / 255
alpha = np.clip((alpha - 0.06) / (0.96 - 0.06), 0, 1)          # убрать слабый «хвост» маски
rgb = np.asarray(im).astype(np.float64) / 255
fg = estimate_foreground_ml(rgb, alpha)                          # убрать примесь фона на краях шерсти
rgba = np.dstack([np.clip(fg, 0, 1), alpha])
Image.fromarray((rgba * 255 + 0.5).astype(np.uint8), 'RGBA').save(out)
print('saved', out)
