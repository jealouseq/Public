#!/usr/bin/env python3
"""Builds the OBJ meshes of the WARDOGS vehicles and weapons from CC0 / CC-BY sources and procedural parts.

Outputs (usage: build_models.py <assets/wardogs>):
  models/vehicle/<id>.obj + .mtl + .json    mesh in blocks, origin on the ground at the vehicle's centre, FRONT toward -z
  models/item/<id>.obj + .mtl + .json       guns in the hand/GUI position used by the voxel models before
  textures/item/mesh_white.png              the one white texture every mesh material tints with its Kd colour

The sources are downloaded on first use into local-data/mesh_cache (OpenGameArt, all CC0 unless noted in research/MODS_AND_ASSETS.md).
"""
import json, os, subprocess, sys, zipfile, urllib.request
import numpy as np
from PIL import Image

sys.path.insert(0, os.path.dirname(__file__))
import meshlib as ml
import meshkit as mk

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
CACHE = os.path.join(ROOT, "local-data", "mesh_cache")
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, "src/main/resources/assets/wardogs")
ONLY = set(sys.argv[2:])
os.makedirs(CACHE, exist_ok=True)


def fetch(url, name):
    p = os.path.join(CACHE, name)
    if not os.path.exists(p):
        print("download", url)
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=120) as r, open(p, "wb") as f:
            f.write(r.read())
    return p


def convert_fbx(path, name):
    out = os.path.join(CACHE, name + ".obj")
    if not os.path.exists(out):
        subprocess.run(["assimp", "export", path, out], check=True, capture_output=True)
    return out


def oga(file):
    return fetch("https://opengameart.org/sites/default/files/" + file, file)


def unzip(path, sub):
    d = os.path.join(CACHE, sub)
    if not os.path.isdir(d):
        zipfile.ZipFile(path).extractall(d)
    return d


def finish_vehicle(vid, m, front="-z"):
    """Writes the mesh of one vehicle and returns (width, height, length) in blocks."""
    m = ml.compact(m)
    lo, hi = m.bbox()
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    obj = os.path.join(OUT, "models/vehicle", vid + ".obj")
    ml.write_obj(m, obj, vid + ".mtl", "#base")
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}.obj", "flip_v": True, "automatic_culling": False,
               "shade_quads": True, "textures": {"base": "wardogs:item/mesh_white", "particle": "wardogs:item/mesh_white"}},
              open(os.path.join(OUT, "models/vehicle", vid + ".json"), "w"))
    dims = (round(hi[0] - lo[0], 2), round(hi[1] - lo[1], 2), round(hi[2] - lo[2], 2))
    print(f"{vid}: {len(m.tris)} tris, {dims}")
    return dims


def paint(m, rules, default=None):
    """Recolours materials by name: rules is a list of (substring, (r, g, b)); the first match wins."""
    colors = []
    for n, c in zip(m.names, m.colors):
        col = default if default is not None else c
        for sub, rc in rules:
            if sub.lower() in n.lower():
                col = rc
                break
        colors.append(col)
    return ml.Mesh(m.verts, m.tris, m.mat, m.names, colors)


def hexc(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) / 255 for i in (0, 2, 4))


OLIVE, TAN, BLACK, GLASS, STEEL = hexc("#5b6648"), hexc("#a89868"), hexc("#1d1f22"), hexc("#7da2b8"), hexc("#8a9096")

DIMS = {}


def vehicle(vid):
    def deco(fn):
        if ONLY and vid not in ONLY:
            return fn
        DIMS[vid] = finish_vehicle(vid, fn())
        return fn
    return deco


# ---- real meshes ---------------------------------------------------------------------------------------------------

def gaz67_mesh():
    z = unzip(oga("gaz-67_lowpoly_1.zip"), "gaz67")
    m = ml.read_obj(convert_fbx(os.path.join(z, "GAZ 67.fbx"), "gaz67"))
    m = ml.compact(m)
    return m


@vehicle("gaz67")
def _gaz67():
    m = gaz67_mesh()
    m = ml.decimate(m, 5200)
    m = ml.place(m, "z", 4.0)
    return m


# ---- guns: Quaternius "Ultimate Gun Pack" (CC0), real meshes in the hand position of the old voxel models -----------------------

GUN_MAP = {
    "t21": "Bullpup_1", "ak74": "AssaultRifle_2", "galil": "AssaultRifle_3", "m4": "AssaultRifle2_1", "tcx_spr": "Bullpup_2",
    "fal": "AssaultRifle_4", "a91": "Bullpup_3", "kh2002": "Bullpup_2", "m17s": "AssaultRifle2_2",
    "amp9": "SubmachineGun_1", "bizon": "SubmachineGun_2", "vityaz": "SubmachineGun_3", "mp5": "SubmachineGun_4", "super45": "SubmachineGun_5",
    "sks": "AssaultRifle_5", "svd": "SniperRifle_1", "bmr308": "SniperRifle_2", "m14": "AssaultRifle2_3", "scout_td": "SniperRifle_3",
    "mosin": "SniperRifle_4", "sv98": "SniperRifle_5", "mk22": "SniperRifle_6", "amr50": "SniperRifle_6",
    "m249": "AssaultRifle2_4", "pkm": "AssaultRifle2_4", "evo3": "SubmachineGun_5", "mp43": "AssaultRifle_1",
    "m500": "Shotgun_1", "ggx17": "Pistol_1", "m1911": "Pistol_2", "ggx18": "Pistol_3", "deagle": "Pistol_4", "judge": "Revolver_1",
}
GUN_DISPLAY = {
    "thirdperson_righthand": {"rotation": [0, -90, 0], "translation": [0, 3, 1], "scale": [0.7, 0.7, 0.7]},
    "firstperson_righthand": {"rotation": [0, 90, 0], "translation": [-3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "firstperson_lefthand": {"rotation": [0, 90, 0], "translation": [3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "gui": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
    "ground": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [0.5, 0.5, 0.5]},
    "fixed": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
}


def lift(c, k=0.16):
    return tuple(k + (1 - k) * x for x in c)


def build_guns():
    z = unzip(oga("ultimate_gun_pack_by_quaternius.zip"), "gunpack")
    base = None
    for root, _, files in os.walk(z):
        if "AssaultRifle_1.obj" in files and os.path.basename(root).upper() == "OBJ":
            base = root
    cache = {}
    for wid, src in GUN_MAP.items():
        if ONLY and wid not in ONLY and "guns" not in ONLY:
            continue
        if src not in cache:
            m = ml.compact(ml.read_obj(os.path.join(base, src + ".obj")))
            m = ml.Mesh(m.verts, m.tris, m.mat, m.names, [lift(c) for c in m.colors])
            lo, hi = m.bbox()
            sc = 1.625 / (hi[0] - lo[0])
            v = (m.verts - np.array([(lo[0] + hi[0]) / 2, lo[1], (lo[2] + hi[2]) / 2])) * sc + np.array([0.5, 0.3125, 0.5])
            cache[src] = ml.Mesh(v, m.tris, m.mat, m.names, m.colors)
        m = cache[src]
        os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
        ml.write_obj(m, os.path.join(OUT, "models/item", wid + ".obj"), wid + ".mtl", "#base", smooth=True, angle=50)
        json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/item/{wid}.obj", "flip_v": True, "automatic_culling": False,
                   "shade_quads": True, "gui_light": "front", "textures": {"base": "wardogs:item/mesh_white", "particle": "wardogs:item/mesh_white"},
                   "display": GUN_DISPLAY}, open(os.path.join(OUT, "models/item", wid + ".json"), "w"))
    print("guns:", len(GUN_MAP))


if __name__ == "__main__":
    if not ONLY or "guns" in ONLY or set(GUN_MAP) & ONLY:
        build_guns()
    os.makedirs(os.path.join(OUT, "textures/item"), exist_ok=True)
    img = Image.new("RGBA", (16, 16), (255, 255, 255, 255))
    img.save(os.path.join(OUT, "textures/item/mesh_white.png"))
    json.dump(DIMS, open(os.path.join(CACHE, "vehicle_dims.json"), "w"), indent=1)
