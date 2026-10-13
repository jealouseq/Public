"""Увеличение вырезанных котов в 2 раза нейросетью EDSR (OpenCV dnn_superres). Запуск:
python3 scripts/upscale.py вход.webp выход.webp
Модель EDSR_x2.pb (38 МБ): https://github.com/Saafke/EDSR_Tensorflow/tree/master/models — положите рядом со скриптом.
Нужны: opencv-contrib-python-headless, numpy, pillow (scipy необязателен)."""
import cv2, numpy as np, sys, time, os
from PIL import Image
src, dst = sys.argv[1], sys.argv[2]
im = Image.open(src).convert('RGBA'); a = np.array(im)
rgb = cv2.cvtColor(a[..., :3], cv2.COLOR_RGB2BGR); alpha = a[..., 3]
# цвет под прозрачным краем: заменяем ближайшим непрозрачным, чтобы не было тёмной каймы
solid = (alpha > 200).astype(np.uint8)
try:
    from scipy import ndimage
    idx = ndimage.distance_transform_edt(solid == 0, return_distances=False, return_indices=True)
    rgb = rgb[idx[0], idx[1]]
except Exception:
    mask = (solid == 0).astype(np.uint8) * 255
    rgb = cv2.inpaint(rgb, mask, 5, cv2.INPAINT_TELEA)
sr = cv2.dnn_superres.DnnSuperResImpl_create(); sr.readModel(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'EDSR_x2.pb')); sr.setModel('edsr', 2)
t = time.time(); big = sr.upsample(rgb); print(os.path.basename(src), big.shape, round(time.time() - t, 1), 's')
alpha2 = cv2.resize(alpha, (big.shape[1], big.shape[0]), interpolation=cv2.INTER_LANCZOS4)
alpha2 = cv2.GaussianBlur(alpha2, (0, 0), 0.6)
out = np.dstack([cv2.cvtColor(big, cv2.COLOR_BGR2RGB), alpha2])
Image.fromarray(out, 'RGBA').save(dst, 'WEBP', quality=84, method=6, alpha_quality=92)
print(dst, os.path.getsize(dst) // 1024, 'KB')
