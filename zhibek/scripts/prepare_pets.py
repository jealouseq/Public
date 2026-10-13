"""Подготовка присланных картинок: обрезка прозрачных полей, масштаб, WebP.
Запуск: python3 scripts/prepare_pets.py <папка_с_исходниками>
Имена исходников (номера файлов из переписки) сопоставлены ниже."""
import os, sys
from PIL import Image

SRC = sys.argv[1]
OUT = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'theme', 'assets', 'img')

PETS = {
    'abyssinian': {'sit': 37, 'stand': 36, 'lie': 38, 'stretch': 39, 'walk': 40, 'up': 41, 'paw': 42, 'lick': 43, 'play': 44, 'sleep': 45},
    'bengal': {'stand': 26, 'sit': 27, 'lie': 28, 'stretch': 29, 'walk': 30, 'play': 31, 'lick': 32, 'up': 33, 'paw': 34, 'sleep': 35},
    'siamese': {'sit': 20, 'stand': 21, 'stretch': 22, 'paw': 23, 'up': 24, 'lie': 25},
}
STICKERS = {'paw': 46, 'face': 47, 'sit': 48, 'sleep': 49, 'ears': 50, 'whiskers': 51, 'yarn': 52, 'fish': 53, 'tail': 54, 'jump': 55}
PHOTOS = {'bengal-table': 12, 'bengal-forest': 13, 'bengal-portrait': 14, 'bengal-studio': 15, 'abyssinian-kitten': 16, 'abyssinian-blue': 17, 'abyssinian-portrait': 18, 'siamese-dark': 19}


def find(n):
    for f in os.listdir(SRC):
        if os.path.splitext(f)[0] == str(n):
            return os.path.join(SRC, f)
    raise FileNotFoundError(n)


def defog(im, knee=60):
    """Убирает едва заметную дымку по краям вырезки (мягкая тень из ChatGPT): прозрачность ниже порога сходит на нет."""
    import numpy as np
    a = np.array(im.convert('RGBA')).astype(float)
    al = a[..., 3]
    a[..., 3] = np.where(al >= knee, al, al * (al / knee) ** 2)
    return Image.fromarray(a.round().astype('uint8'), 'RGBA')


def save(im, path, long, q, small=0):
    """Сохраняет WebP; для вырезок (small>0) рядом кладёт лёгкую версию name-sm.webp."""
    os.makedirs(os.path.dirname(path), exist_ok=True)
    s = long / max(im.size)
    big = im.resize((round(im.width * s), round(im.height * s)), Image.LANCZOS) if s < 1 else im
    big.save(path, 'WEBP', quality=q, method=6, alpha_quality=95)
    print(os.path.relpath(path, OUT), big.size, os.path.getsize(path) // 1024, 'KB')
    if small:
        k = small / max(im.size)
        sm = im.resize((round(im.width * k), round(im.height * k)), Image.LANCZOS)
        p2 = path[:-5] + '-sm.webp'
        sm.save(p2, 'WEBP', quality=76, method=6, alpha_quality=92)
        print('  +', os.path.basename(p2), sm.size, os.path.getsize(p2) // 1024, 'KB')


def cut(n):
    im = Image.open(find(n)).convert('RGBA')
    bbox = im.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox()
    return im.crop(bbox)


for breed, poses in PETS.items():
    for pose, n in poses.items():
        main = pose in ('sit', 'stand')
        save(cut(n), os.path.join(OUT, 'pets', f'{breed}-{pose}.webp'), 900 if main else 720, 76, small=520)
# голова породы (для сравнения характеров): верхняя треть сидящего кота, обрезка до видимого
for breed in ('abyssinian', 'bengal', 'siamese'):
    im = cut(PETS[breed]['sit'])
    top = im.crop((0, 0, im.width, round(im.height * 0.30)))
    top = top.crop(top.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox())
    save(top, os.path.join(OUT, 'pets', f'{breed}-head.webp'), 520, 80)
for name, n in STICKERS.items():
    save(cut(n), os.path.join(OUT, 'stickers', f'{name}.webp'), 520, 88)
save(defog(cut(58)), os.path.join(OUT, 'pets', 'peek.webp'), 1100, 82)
save(defog(cut(59)), os.path.join(OUT, 'pets', 'peek-2.webp'), 1100, 82)


def peek_side(n):
    """Кот выглядывает из-за угла: убираем белую стену слева, оставляем кота и лапы на ней."""
    import numpy as np
    im = Image.open(find(n)).convert('RGBA'); a = np.array(im).astype(int)
    r, g, b = a[..., 0], a[..., 1], a[..., 2]
    sat = np.max(a[..., :3], axis=2) - np.min(a[..., :3], axis=2)
    wall = (sat < 38) & (np.max(a[..., :3], axis=2) > 150)
    wall[:, 300:] = False  # стена занимает только левую полосу
    a[..., 3] = np.where(wall, 0, a[..., 3])
    out = Image.fromarray(a.astype('uint8'), 'RGBA')
    bbox = out.getchannel('A').point(lambda v: 255 if v > 12 else 0).getbbox()
    return out.crop(bbox)


save(defog(peek_side(60)), os.path.join(OUT, 'pets', 'peek-side.webp'), 1000, 82)
for name, n in PHOTOS.items():
    im = Image.open(find(n)).convert('RGB')
    save(im, os.path.join(OUT, 'photos', f'{name}.webp'), 1500, 74)
