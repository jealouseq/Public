#!/usr/bin/env python3
"""Bake land cover and building footprints for Bakurani from the base map (local-data/bakurani/base_5120.webp).

Outputs in local-data/bakurani/:
  cover_4096_u8.bin   one byte per 4 m cell: 0 none, 1 water, 2 forest canopy, 3 paved/urban, 4 field
  buildings.bin       int32 -count (version 2), then records '<ffBBBBb': centre x, centre z (Minecraft coordinates, metres), width, depth (m),
                      floors (1..4), kind (0 grey/concrete, 1 red roof), rotation in degrees (-45..44, along the street)

Classification rules were checked by eye against the map (see research/DATA_SOURCES.md): water is very dark, forest canopy is a
saturated brown-olive of medium brightness, built-up ground is low-saturation grey, roofs are red/orange.
Needs numpy, Pillow and scipy.
"""
import os, struct, sys
import numpy as np
from PIL import Image
from scipy import ndimage

D = sys.argv[1] if len(sys.argv) > 1 else "local-data/bakurani"
base = np.array(Image.open(os.path.join(D, "base_5120.webp")).convert("RGB")).astype(np.float32)
N = base.shape[0]
r, g, b = base[..., 0], base[..., 1], base[..., 2]
mx = base.max(2); mn = base.min(2); v = mx / 255; d = mx - mn
s = np.where(mx > 0, d / np.maximum(mx, 1), 0)
hr = np.where(mx == r, ((g - b) / np.maximum(d, 1)) % 6, 0)
hg = np.where(mx == g, (b - r) / np.maximum(d, 1) + 2, 0)
hb = np.where(mx == b, (r - g) / np.maximum(d, 1) + 4, 0)
hue = np.where(mx == r, hr, np.where(mx == g, hg, hb)) * 60

water = (v < 0.30) & (s < 0.55)
roof = (r > g + 40) & (r > b + 40) & (v > 0.6)
urban = (s < 0.30) & (v > 0.45) & (v < 0.80) & ~water
forest = (v >= 0.36) & (v < 0.67) & (s >= 0.36) & (hue >= 22) & (hue <= 52) & ~urban & ~water
field = (v >= 0.82) & (s >= 0.5) & (hue >= 38) & (hue <= 52) & ~roof & ~water

cover = np.zeros((N, N), np.uint8)
cover[forest] = 2; cover[urban] = 3; cover[field] = 4; cover[water] = 1
Image.fromarray(cover).resize((4096, 4096), Image.NEAREST).save(os.path.join(D, "_cover.png"))
open(os.path.join(D, "cover_4096_u8.bin"), "wb").write(np.array(Image.open(os.path.join(D, "_cover.png"))).tobytes())
os.remove(os.path.join(D, "_cover.png"))
print("cover: water %.2f%% forest %.1f%% paved %.1f%% field %.1f%%" % tuple(100 * (cover == k).mean() for k in (1, 2, 3, 4)))

# --- roads: drop the interiors of broad filled blobs (plazas, quarries), keep carriageways up to ~20 m wide ---
raw_roads = np.frombuffer(open(os.path.join(D, "roads_4096_u8.bin"), "rb").read(), np.uint8).reshape(4096, 4096) > 0
dist = ndimage.distance_transform_edt(raw_roads)
clean = raw_roads & (dist <= 2.5)
major = clean & (dist >= 1.2)     # carriageways about 10 m or wider are surfaced (class 2), the rest are tracks (class 1)
cls = clean.astype(np.uint8) + major.astype(np.uint8)
open(os.path.join(D, "roads_clean_4096_u8.bin"), "wb").write(cls.tobytes())
print("roads: raw %.2f%% -> clean %.2f%% (surfaced %.2f%%)" % (100 * raw_roads.mean(), 100 * clean.mean(), 100 * major.mean()))

# --- buildings ---------------------------------------------------------------------------------
roads = np.frombuffer(open(os.path.join(D, "roads_4096_u8.bin"), "rb").read(), np.uint8).reshape(4096, 4096)
roads = np.array(Image.fromarray(roads * 255).resize((N, N), Image.NEAREST)) > 0
near_roads = ndimage.binary_dilation(roads, iterations=25)   # buildings stand within ~80 m of a road; bare grey rock does not
roads = ndimage.binary_dilation(roads, iterations=2)
mask = (roof | (urban & ~roads)) & near_roads
mask = ndimage.binary_opening(mask, structure=np.ones((3, 3)))
lab, n = ndimage.label(mask, structure=np.ones((3, 3)))
M_PER_PX = 16384.0 / N  # metres per base pixel
# --- street direction field: dominant orientation of the road mask (structure tensor, ~40 m smoothing), in degrees within [-45, 45) ---
road_n = np.array(Image.fromarray(clean.astype(np.uint8) * 255).resize((N, N), Image.NEAREST)).astype(np.float32) / 255.0
rs = ndimage.gaussian_filter(road_n, 4)
gy_, gx_ = np.gradient(rs)
sig = 12
jxx = ndimage.gaussian_filter(gx_ * gx_, sig); jyy = ndimage.gaussian_filter(gy_ * gy_, sig); jxy = ndimage.gaussian_filter(gx_ * gy_, sig)
coh = np.sqrt((jxx - jyy) ** 2 + 4 * jxy ** 2) / np.maximum(jxx + jyy, 1e-9)
field = np.degrees(0.5 * np.arctan2(2 * jxy, jxx - jyy))
def norm45(a):
    a = ((a + 45.0) % 90.0) - 45.0
    return 0.0 if abs(a) < 10.0 else a   # a slight tilt only makes ragged steps, so near-aligned houses are left square
def street_angle(px, py):
    ix = int(min(N - 1, max(0, px))); iy = int(min(N - 1, max(0, py)))
    return norm45(field[iy, ix]) if coh[iy, ix] > 0.35 else 0.0
out = []
for i, sl in enumerate(ndimage.find_objects(lab), start=1):
    h = sl[0].stop - sl[0].start; w = sl[1].stop - sl[1].start
    comp = lab[sl] == i
    area = comp.sum()
    area_m2 = area * M_PER_PX ** 2
    if not (40 <= area_m2 <= 2500) or not (6 <= w * M_PER_PX <= 60) or not (6 <= h * M_PER_PX <= 60):
        continue
    if area / float(w * h) < 0.45:
        continue
    if water[sl][comp].mean() > 0.10:
        continue
    red = roof[sl][comp].mean() > 0.30
    ys, xs = np.nonzero(comp)
    ys = ys + sl[0].start; xs = xs + sl[1].start
    mx_, my_ = xs.mean(), ys.mean()
    cov = np.cov(np.vstack([xs - mx_, ys - my_]))
    ev = np.linalg.eigvalsh(cov)
    elong = np.sqrt(max(ev[1], 1e-9) / max(ev[0], 1e-9))
    if elong >= 1.25:
        ang = norm45(np.degrees(0.5 * np.arctan2(2 * cov[0, 1], cov[0, 0] - cov[1, 1])))
    else:
        ang = street_angle(mx_, my_)
    th = np.radians(ang)
    uu = (xs - mx_) * np.cos(th) + (ys - my_) * np.sin(th)
    vv = -(xs - mx_) * np.sin(th) + (ys - my_) * np.cos(th)
    w_m = (uu.max() - uu.min() + 1) * M_PER_PX; d_m = (vv.max() - vv.min() + 1) * M_PER_PX
    w_m = min(60, max(6, w_m)); d_m = min(60, max(6, d_m))
    x = (mx_ + 0.5) * M_PER_PX * 0.99902 - 8187.0
    z = (my_ + 0.5) * M_PER_PX * 0.99983 - 8191.0
    floors = 1 if area_m2 < 90 else 2 if area_m2 < 300 else 3 if area_m2 < 1200 else 4
    out.append((x, z, int(round(w_m)), int(round(d_m)), floors, 1 if red else 0, int(round(ang))))
# --- town fabric: where built-up ground is dense, lay out a grid of houses (the map does not resolve single roofs) ---
town = (urban & ~roads & near_roads & ~water & ~roof)
CELL = 5                                   # 5 px = 16 m
nc = N // CELL
frac = town[:nc * CELL, :nc * CELL].reshape(nc, CELL, nc, CELL).mean(axis=(1, 3))
explicit = [(o[0], o[1], max(o[2], o[3]) / 2.0 + 3, max(o[2], o[3]) / 2.0 + 3) for o in out]
def hashf(i, j, salt):
    h = (i * 73856093) ^ (j * 19349663) ^ (salt * 83492791)
    h = (h ^ (h >> 13)) * 1274126177 & 0xFFFFFFFF
    return ((h ^ (h >> 16)) & 0xFFFF) / 65536.0
grid = []
for j in range(nc):
    for i in range(nc):
        if frac[j, i] < 0.55:
            continue
        cx16 = (i + 0.5) * CELL * M_PER_PX; cz16 = (j + 0.5) * CELL * M_PER_PX
        x = cx16 * 0.99902 - 8187.0 + (hashf(i, j, 1) - 0.5) * 3
        z = cz16 * 0.99983 - 8191.0 + (hashf(i, j, 2) - 0.5) * 3
        if any(abs(x - ex) < hw and abs(z - ez) < hd for ex, ez, hw, hd in explicit):
            continue
        w = int(8 + hashf(i, j, 3) * 5); dd = int(8 + hashf(i, j, 4) * 5)
        floors = 1 + int(hashf(i, j, 5) * 3)
        grid.append((x, z, w, dd, floors, 0, int(round(street_angle((i + 0.5) * CELL, (j + 0.5) * CELL)))))
print("town fabric houses:", len(grid))
out.extend(grid)
with open(os.path.join(D, "buildings.bin"), "wb") as f:
    f.write(struct.pack("<i", -len(out)))        # negative count = version 2 records (with a rotation angle in degrees)
    for rec in out:
        f.write(struct.pack("<ffBBBBb", *rec))
print("buildings:", len(out), "red roofs:", sum(1 for o in out if o[5]), "rotated:", sum(1 for o in out if abs(o[6]) >= 5))
