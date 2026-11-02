#!/usr/bin/env python3
"""Bake land cover and building footprints for Bakurani from the base map (local-data/bakurani/base_5120.webp).

Outputs in local-data/bakurani/:
  cover_4096_u8.bin   one byte per 4 m cell: 0 none, 1 water, 2 forest canopy, 3 paved/urban, 4 field
  buildings.bin       int32 -(1000000 + count) (version 3), then records '<ffBBBBh': centre x, centre z (Minecraft coordinates, metres), width,
                      depth (m), floors (1..4), kind (0 grey/concrete, 1 red roof), rotation in degrees (-180..180, door side toward the road)

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
# --- villages: houses along the road network, facing the road ------------------------------------------------------------
# The map does not resolve single roofs, but where the road net is dense people live: houses stand 14-36 m from a road, door toward it,
# with a jitter and a minimum spacing; remote highways get none, farm tracks a few.
cell = 4.0                                   # metres per cell of the road grid
road_any = clean
dist, (iy, ix) = ndimage.distance_transform_edt(~road_any, return_indices=True)
dens = ndimage.gaussian_filter(road_any.astype(np.float32), 22)       # share of road cells within ~90 m
water_cov = np.array(Image.fromarray((water.astype(np.uint8) * 255)).resize((4096, 4096), Image.NEAREST)) > 0
near_water = ndimage.binary_dilation(water_cov, iterations=4)
# keep only houses of the detection that are not in a river bed (dark rocks beside water read as roofs)
def near_water_world(x, z):
    px = int((x + 8187.0) / 0.99902 / 4.0); py = int((z + 8191.0) / 0.99983 / 4.0)
    return 0 <= px < 4096 and 0 <= py < 4096 and near_water[py, px]
out = [o for o in out if not near_water_world(o[0], o[1])]
# slope from the elevation grid (2048 px = 8 m)
H = np.fromfile(os.path.join(D, "height_2048_u16.bin"), np.uint16).reshape(2048, 2048).astype(np.float32)
elev = 0.25993 * H - 1008.34
gyh, gxh = np.gradient(ndimage.gaussian_filter(elev, 1.0), 8.0)
slope = np.hypot(gxh, gyh)                                            # metres per metre
mat = np.fromfile(os.path.join(D, "material_2048_u8.bin"), np.uint8).reshape(2048, 2048)
def hashf(i, j, salt):
    h = (i * 73856093) ^ (j * 19349663) ^ (salt * 83492791)
    h = (h ^ (h >> 13)) * 1274126177 & 0xFFFFFFFF
    return ((h ^ (h >> 16)) & 0xFFFF) / 65536.0
placed = {}                                   # spatial hash of centres for the spacing test (metres)
def free(x, z, r):
    gx, gz = int(x // 24), int(z // 24)
    for a in (-1, 0, 1):
        for b in (-1, 0, 1):
            for (px, pz, pr) in placed.get((gx + a, gz + b), ()):
                if (px - x) ** 2 + (pz - z) ** 2 < (pr + r) ** 2:
                    return False
    return True
def claim(x, z, r):
    placed.setdefault((int(x // 24), int(z // 24)), []).append((x, z, r))
for o in out:
    claim(o[0], o[1], max(o[2], o[3]) / 2.0 + 4)
villagers = []
STEP = 5                                      # candidate grid: every 5 cells = 20 m
for j in range(0, 4096 - STEP, STEP):
    for i in range(0, 4096 - STEP, STEP):
        ci = i + int(hashf(i, j, 1) * STEP); cj = j + int(hashf(i, j, 2) * STEP)
        d = dist[cj, ci]
        if d < 3.2 or d > 9.0:
            continue
        density = dens[cj, ci]
        if density < 0.07 or hashf(i, j, 3) > min(0.85, density * 4.5):
            continue
        if near_water[cj, ci] or cover[cj, ci] == 1:
            continue
        # world position (metres) and checks on the finer grids
        wx = (ci + 0.5) * cell * 0.99902 - 8187.0
        wz = (cj + 0.5) * cell * 0.99983 - 8191.0
        hx = min(2047, int((ci + 0.5) * 4.0 / 8.0)); hz = min(2047, int((cj + 0.5) * 4.0 / 8.0))
        if slope[hz, hx] > 0.32 or mat[hz, hx] == 9:
            continue
        # direction toward the nearest road cell
        ny, nx = iy[cj, ci] - cj, ix[cj, ci] - ci
        nl = float(np.hypot(nx, ny))
        if nl < 1e-6:
            continue
        nxw, nzw = nx / nl, ny / nl           # unit vector from the house to the road (east, south)
        theta = int(round(np.degrees(np.arctan2(-nxw, nzw)) / 45.0)) * 45   # the door side (-sin, cos) points at the road; snapped to 45 degrees so walls step cleanly
        r = hashf(i, j, 4)
        w = 8 + int(hashf(i, j, 5) * 6); dd = 7 + int(hashf(i, j, 6) * 5)
        if not free(wx, wz, max(w, dd) / 2.0 + 4):
            continue
        claim(wx, wz, max(w, dd) / 2.0 + 4)
        floors = 1 + (hashf(i, j, 7) < 0.45) + (hashf(i, j, 8) < 0.06)
        villagers.append((wx, wz, w, dd, floors, 1 if hashf(i, j, 9) < 0.55 else 0, theta))
print("village houses:", len(villagers))
out.extend(villagers)
with open(os.path.join(D, "buildings.bin"), "wb") as f:
    f.write(struct.pack("<i", -(1000000 + len(out))))   # below -1000000 = version 3 records (rotation as a signed 16-bit angle in degrees)
    for rec in out:
        f.write(struct.pack("<ffBBBBh", *rec))
print("buildings:", len(out), "red roofs:", sum(1 for o in out if o[5]), "rotated:", sum(1 for o in out if abs(o[6]) >= 5))
