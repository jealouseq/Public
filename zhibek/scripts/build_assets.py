"""Build the theme's image assets from the cleaned cutouts and the studio frames.

Inputs (not in the repo, see docs/assets.md): cleaned RGBA cutouts and the original studio frames.
Usage: python3 build_assets.py <clean_dir> <studio_dir> <theme_assets_dir>
"""
import sys, os
from PIL import Image

clean, studio, out = sys.argv[1:4]
os.makedirs(f'{out}/img/cats', exist_ok=True)
os.makedirs(f'{out}/img/scenes', exist_ok=True)
os.makedirs(f'{out}/img/products', exist_ok=True)


def trim(im, pad=6):
    bbox = im.getchannel('A').point(lambda v: 255 if v > 8 else 0).getbbox()
    x0, y0, x1, y1 = bbox
    return im.crop((max(0, x0 - pad), max(0, y0 - pad), min(im.width, x1 + pad), min(im.height, y1 + pad)))


for name in ['abyssinian', 'bengal', 'siamese', 'k-abyssinian', 'k-bengal', 'k-siamese', 'hero-abyssinian', 'peek-bengal']:
    im = trim(Image.open(f'{clean}/{name}.png').convert('RGBA'))
    im.save(f'{out}/img/cats/{name}.webp', 'WEBP', quality=90, alpha_quality=100, method=6)
    print(name, im.size, os.path.getsize(f'{out}/img/cats/{name}.webp') // 1024, 'KB')

for src, dst in [('breeds.webp', 'trio.webp'), ('kittens.webp', 'kittens.webp'), ('lifestyle.webp', 'lifestyle.webp')]:
    im = Image.open(f'{studio}/{src}').convert('RGB')
    im.save(f'{out}/img/scenes/{dst}', 'WEBP', quality=88, method=6)
    print(dst, im.size)

prod = Image.open(f'{studio}/products.webp').convert('RGB')
W, H = prod.size
cuts = {'house': (0, 560), 'bowl': (560, 1090), 'food': (1090, 1630), 'toy': (1630, W)}
for k, (a, b) in cuts.items():
    p = prod.crop((a, 0, b, H)).resize(((b - a) , H))
    p.save(f'{out}/img/products/product-{k}.webp', 'WEBP', quality=88, method=6)
    print('product', k, p.size)
