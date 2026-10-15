#!/usr/bin/env python3
"""Prepare compact data files for the renderer.

Sources (all open / public domain):
  * NASA Black Marble 2016 (night lights)         -> data/lights.u8, data/ground.u8
  * AWS Terrain Tiles (Terrarium, SRTM-derived)   -> data/dem.i16  (equirectangular, region only)
  * Natural Earth 10m (rivers, lakes, places)     -> data/vectors.json
  * Natural Earth 50m land via world-atlas        -> data/vectors.json (coast)
Run:  python3 -I tools/prep_assets.py
"""
import json, math, os, sys
import numpy as np
from PIL import Image

Image.MAX_IMAGE_PIXELS = None
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = os.path.join(ROOT, "data")
os.makedirs(DATA, exist_ok=True)

# ---------------------------------------------------------------- night texture
def prep_night():
    im = Image.open(os.path.join(ROOT, "assets/nasa/black_marble_2016_3km.jpg")).convert("RGB")
    W, H = 10800, 5400
    im = im.resize((W, H), Image.BOX)
    a = np.asarray(im, dtype=np.float32)
    r, g, b = a[..., 0], a[..., 1], a[..., 2]
    # city lights are warm (R,G high); land/ice are blue-violet (B dominant)
    warm = np.clip(r - 0.55 * b, 0, None)
    lights = np.clip((warm - 8.0) / 170.0, 0, 1) ** 0.85
    ground = np.clip((b - 14.0) / 120.0, 0, 1)
    (lights * 255).astype(np.uint8).tofile(os.path.join(DATA, "lights.u8"))
    (ground * 255).astype(np.uint8).tofile(os.path.join(DATA, "ground.u8"))
    print("night texture", W, H, "lights p99.9 =", np.percentile(lights, 99.9), "ground mean =", ground.mean())
    return W, H

# ---------------------------------------------------------------- DEM mosaic
LON0, LON1, LAT0, LAT1, STEP = 22.0, 54.0, 40.0, 56.0, 0.008
def prep_dem():
    z, x0, x1, y0, y1 = map(int, open(os.path.join(ROOT, "raw/tile_meta.txt")).read().split())
    nx, ny = x1 - x0 + 1, y1 - y0 + 1
    mos = np.zeros((ny * 256, nx * 256), dtype=np.float32)
    for x in range(x0, x1 + 1):
        for y in range(y0, y1 + 1):
            t = np.asarray(Image.open(os.path.join(ROOT, f"raw/tiles/{z}_{x}_{y}.png")).convert("RGB"), dtype=np.float32)
            mos[(y - y0) * 256:(y - y0 + 1) * 256, (x - x0) * 256:(x - x0 + 1) * 256] = t[..., 0] * 256 + t[..., 1] + t[..., 2] / 256 - 32768
    W = int(round((LON1 - LON0) / STEP)); H = int(round((LAT1 - LAT0) / STEP))
    lon = LON0 + (np.arange(W) + 0.5) * STEP
    lat = LAT1 - (np.arange(H) + 0.5) * STEP
    n = 256 * 2 ** z
    px = (lon + 180) / 360 * n - x0 * 256
    latr = np.radians(lat)
    py = (1 - np.arcsinh(np.tan(latr)) / math.pi) / 2 * n - y0 * 256
    fx, fy = px[None, :], py[:, None]
    ix, iy = np.floor(fx).astype(int), np.floor(fy).astype(int)
    dx, dy = fx - ix, fy - iy
    ix = np.clip(ix, 0, mos.shape[1] - 2); iy = np.clip(iy, 0, mos.shape[0] - 2)
    v = (mos[iy, ix] * (1 - dx) * (1 - dy) + mos[iy, ix + 1] * dx * (1 - dy) +
         mos[iy + 1, ix] * (1 - dx) * dy + mos[iy + 1, ix + 1] * dx * dy)
    np.clip(v, -4000, 4000).astype(np.int16).tofile(os.path.join(DATA, "dem.i16"))
    print("dem", W, H, "elev min/max", v.min(), v.max())
    # soft shoreline mask (land/sea edge) so the renderer can draw a crisp glowing coast at deep zoom
    from PIL import ImageFilter
    m = Image.fromarray(np.where(v > 0.5, 255, 0).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.1))
    mg = np.asarray(m, dtype=np.float32) / 255.0
    gy, gx = np.gradient(mg)
    e = np.clip(np.hypot(gx, gy) * 3.2, 0, 1)
    (e * 255).astype(np.uint8).tofile(os.path.join(DATA, "edge.u8"))
    return W, H

# ---------------------------------------------------------------- vectors
BB = (18.0, 36.0, 60.0, 60.0)
def inbb(pt): return BB[0] <= pt[0] <= BB[2] and BB[1] <= pt[1] <= BB[3]
def rnd(line): return [[round(p[0], 3), round(p[1], 3)] for p in line]

def prep_vectors():
    out = {"rivers": [], "lakes": [], "places": [], "coast": []}
    rv = json.load(open(os.path.join(ROOT, "assets/ne/rivers_10m.geojson")))
    for f in rv["features"]:
        g = f["geometry"]
        if not g: continue
        lines = g["coordinates"] if g["type"] == "MultiLineString" else [g["coordinates"]]
        for ln in lines:
            if any(inbb(p) for p in ln): out["rivers"].append(rnd(ln))
    lk = json.load(open(os.path.join(ROOT, "assets/ne/lakes_10m.geojson")))
    for f in lk["features"]:
        g = f["geometry"]
        if not g: continue
        polys = g["coordinates"] if g["type"] == "MultiPolygon" else [g["coordinates"]]
        for poly in polys:
            ring = poly[0]
            if any(inbb(p) for p in ring): out["lakes"].append(rnd(ring))
    pl = json.load(open(os.path.join(ROOT, "assets/ne/places_10m.geojson")))
    for f in pl["features"]:
        lon, lat = f["geometry"]["coordinates"]
        pop = f["properties"].get("pop_max") or 0
        if BB[0] <= lon <= BB[2] and BB[1] <= lat <= BB[3] and pop >= 40000:
            out["places"].append([round(lon, 3), round(lat, 3), int(pop)])
    # global coastline (50m land) from world-atlas topology
    topo = json.load(open(os.path.join(ROOT, "node_modules/world-atlas/land-50m.json")))
    sx, sy = topo["transform"]["scale"]; tx_, ty_ = topo["transform"]["translate"]
    arcs = []
    for arc in topo["arcs"]:
        x = y = 0; pts = []
        for dx, dy in arc:
            x += dx; y += dy; pts.append([x * sx + tx_, y * sy + ty_])
        arcs.append(pts)
    def arc_pts(i): return arcs[i] if i >= 0 else arcs[~i][::-1]
    geom = topo["objects"]["land"]
    geoms = geom["geometries"] if geom["type"] == "GeometryCollection" else [geom]
    for g in geoms:
        polys = g["arcs"] if g["type"] == "MultiPolygon" else [g["arcs"]]
        for poly in polys:
            for ring in poly:
                pts = []
                for i in ring:
                    seg = arc_pts(i)
                    pts.extend(seg if not pts else seg[1:])
                if len(pts) > 6: out["coast"].append(rnd(pts))
    for k, v in out.items(): print(k, len(v), sum(len(x) for x in v) if k != "places" else "")
    json.dump(out, open(os.path.join(DATA, "vectors.json"), "w"), separators=(",", ":"))
    print("vectors.json", os.path.getsize(os.path.join(DATA, "vectors.json")) // 1024, "KB")

if __name__ == "__main__":
    prep_night(); prep_dem(); prep_vectors()
    meta = {"night": {"w": 10800, "h": 5400}, "dem": {"lon0": LON0, "lon1": LON1, "lat0": LAT0, "lat1": LAT1, "step": STEP,
            "w": int(round((LON1 - LON0) / STEP)), "h": int(round((LAT1 - LAT0) / STEP))}}
    json.dump(meta, open(os.path.join(DATA, "meta.json"), "w"))
    print("ok")
