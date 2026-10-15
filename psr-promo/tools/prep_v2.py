#!/usr/bin/env python3
"""v2 textures (photoreal Earth).  All NASA imagery = public domain.
  assets/v2/bmng_topo_bathy_jul_21600.jpg  Blue Marble Next Generation w/ topography+bathymetry, July (Visible Earth 73751)
  assets/v2/viirs_2026-09-14.jpg           VIIRS SNPP true-colour daily mosaic (NASA GIBS) -> real cloud layer
  assets/v2/bmng_region_test.jpg           BMNG 500 m crop lon 22..54, lat 40..56 (NASA GIBS WMS)
Outputs (data/): day_rgb.u8 (10800x5400x3), clouds.u8 (8192x4096), region_rgb.u8 (6400x3200x3), meta2.json
"""
import json, os
import numpy as np
from PIL import Image
Image.MAX_IMAGE_PIXELS = None
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
A = os.path.join(ROOT, "assets/v2"); D = os.path.join(ROOT, "data")

# ---- day colour (global)
im = Image.open(os.path.join(A, "bmng_topo_bathy_jul_21600.jpg")).convert("RGB").resize((10800, 5400), Image.BOX)
np.asarray(im, dtype=np.uint8).tofile(os.path.join(D, "day_rgb.u8")); print("day_rgb", im.size)

# ---- clouds from VIIRS true colour: white = cloud (min channel high), dark = ocean/land
v = np.asarray(Image.open(os.path.join(A, "viirs_2026-09-14.jpg")).convert("RGB"), dtype=np.float32) / 255.0
m = v.min(axis=2)                       # clouds & snow are bright in ALL channels
lum = v.mean(axis=2)
a = np.clip((m - 0.34) / 0.46, 0, 1)
a = a * a * (3 - 2 * a)
a *= np.clip(lum * 3.0, 0, 1)           # no-data (black) -> no cloud
# polar caps: the mosaic has no data there -> extend the last valid row (avoids a dark hole at the pole)
rowlum = lum.mean(axis=1)
valid = np.where(rowlum > 0.18)[0]
top, bot = int(valid.min()) + 4, int(valid.max()) - 4
def polar_row(rows):
    m = a[rows].mean(axis=0)
    k = np.exp(-0.5 * (np.arange(-400, 401) / 140.0) ** 2); k /= k.sum()
    mm = np.convolve(np.concatenate([m[-400:], m, m[:400]]), k, mode="same")[400:-400]
    return np.clip(mm * 0.9, 0, 1)
pt = polar_row(slice(top, top + 80)); pb = polar_row(slice(bot - 80, bot))
for i in range(0, top):
    w = (i / max(1, top)) ** 1.5; a[i] = a[top] * w + pt * (1 - w)
for i in range(bot + 1, a.shape[0]):
    w = ((a.shape[0] - 1 - i) / max(1, a.shape[0] - 1 - bot)) ** 1.5; a[i] = a[bot] * w + pb * (1 - w)
print('valid rows', top, bot, 'of', a.shape[0])
# polar caps: blend towards a soft, noisy, nearly uniform veil (equirectangular pinching otherwise makes wedge shapes)
rr = np.random.default_rng(5)
def veil(rows):
    n = rr.standard_normal((rows, a.shape[1])).astype(np.float32)
    k = np.exp(-0.5 * (np.arange(-120, 121) / 40.0) ** 2); k /= k.sum()
    n = np.apply_along_axis(lambda v: np.convolve(np.concatenate([v[-120:], v, v[:120]]), k, mode="same")[120:-120], 1, n)
    return np.clip(0.62 + n * 0.9, 0.35, 0.9)
cap = int((90 - 76) / 180 * a.shape[0])
vt = veil(cap)
for i in range(cap):
    w = float(np.clip((cap - i) / (cap * 0.55), 0, 1)); w = w * w * (3 - 2 * w)
    a[i] = a[i] * (1 - w) + vt[i] * w
vb = veil(cap)
for j in range(cap):
    i = a.shape[0] - 1 - j; w = float(np.clip((cap - j) / (cap * 0.55), 0, 1)); w = w * w * (3 - 2 * w)
    a[i] = a[i] * (1 - w) + vb[j] * w
(a * 255).astype(np.uint8).tofile(os.path.join(D, "clouds.u8")); print("clouds", a.shape, "coverage", float((a > 0.3).mean()))

# ---- regional satellite crop (sharper, same colour family as the global texture)
r = Image.open(os.path.join(A, "bmng_region_test.jpg")).convert("RGB")
np.asarray(r, dtype=np.uint8).tofile(os.path.join(D, "region_rgb.u8")); print("region", r.size)

json.dump({"day": {"w": 10800, "h": 5400}, "clouds": {"w": 8192, "h": 4096},
           "region": {"lon0": 22.0, "lon1": 54.0, "lat0": 40.0, "lat1": 56.0, "w": r.size[0], "h": r.size[1]}},
          open(os.path.join(D, "meta2.json"), "w"))
print("ok")
