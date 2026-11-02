#!/usr/bin/env python3
"""Voxelise CC0 low-poly gun OBJ files (Quaternius 'Ultimate Gun Pack') into Minecraft item models.

Every model becomes a set of axis-aligned boxes (greedy-merged voxels of one colour). All boxes sample a shared 16x16 palette texture
(textures/item/gun_palette.png), so no per-gun texture is needed. Source: https://opengameart.org/content/low-poly-guns-pack (CC0).

usage: obj_to_item_model.py <OBJ dir> <assets/wardogs dir> [length_units] [voxel_units]
"""
import json, math, os, sys
import numpy as np
from PIL import Image

SRC, OUT = sys.argv[1], sys.argv[2]
LENGTH = float(sys.argv[3]) if len(sys.argv) > 3 else 26.0   # gun length in 1/16 block units
VOX = float(sys.argv[4]) if len(sys.argv) > 4 else 0.5

# weapon id -> OBJ name (several weapons share a silhouette; the real game has no matching 3D assets we could reuse)
MAP = {
    "t21": "Bullpup_1", "ak74": "AssaultRifle_2", "galil": "AssaultRifle_3", "m4": "AssaultRifle2_1", "tcx_spr": "Bullpup_2",
    "fal": "AssaultRifle_4", "a91": "Bullpup_3", "kh2002": "Bullpup_2", "m17s": "AssaultRifle2_2",
    "amp9": "SubmachineGun_1", "bizon": "SubmachineGun_2", "vityaz": "SubmachineGun_3", "mp5": "SubmachineGun_4", "super45": "SubmachineGun_5",
    "sks": "AssaultRifle_5", "svd": "SniperRifle_1", "bmr308": "SniperRifle_2", "m14": "AssaultRifle2_3", "scout_td": "SniperRifle_3",
    "mosin": "SniperRifle_4", "sv98": "SniperRifle_5", "mk22": "SniperRifle_6", "amr50": "SniperRifle_6",
    "m249": "AssaultRifle2_4", "pkm": "AssaultRifle2_4", "evo3": "SubmachineGun_5", "mp43": "AssaultRifle_1",
    "m500": "Shotgun_1", "ggx17": "Pistol_1", "m1911": "Pistol_2", "ggx18": "Pistol_3", "deagle": "Pistol_4", "judge": "Revolver_1",
}

def srgb(c):
    return int(round(255 * min(1.0, max(0.0, c)) ** (1 / 2.2)))

def read_mtl(path):
    mats, cur = {}, None
    for line in open(path):
        p = line.split()
        if not p:
            continue
        if p[0] == "newmtl":
            cur = p[1]
        elif p[0] == "Kd" and cur:
            mats[cur] = tuple(srgb(float(x)) for x in p[1:4])
    return mats

def read_obj(path):
    mats = read_mtl(path[:-4] + ".mtl")
    verts, tris, cur = [], [], (128, 128, 128)
    for line in open(path):
        p = line.split()
        if not p:
            continue
        if p[0] == "v":
            verts.append([float(x) for x in p[1:4]])
        elif p[0] == "usemtl":
            cur = mats.get(p[1], (128, 128, 128))
        elif p[0] == "f":
            idx = [int(t.split("/")[0]) - 1 for t in p[1:]]
            for i in range(1, len(idx) - 1):
                tris.append((idx[0], idx[i], idx[i + 1], cur))
    return np.array(verts), tris

palette = {}
def colour_index(rgb):
    # lift very dark colours a little so the model stays readable under Minecraft lighting
    rgb = tuple(max(c, 52) for c in rgb)
    key = tuple(int(round(c / 6.0)) * 6 for c in rgb)
    if key not in palette:
        palette[key] = len(palette)
    return palette[key]

def voxelise(verts, tris):
    v = verts.copy()
    # orient: longest axis -> X; up = Y; barrel toward +X assumed from the source files
    ext = v.max(0) - v.min(0)
    scale = LENGTH / ext[0]
    v = (v - v.min(0)) * scale
    grid = {}
    for a, b, c, rgb in tris:
        pa, pb, pc = v[a], v[b], v[c]
        longest = max(np.linalg.norm(pb - pa), np.linalg.norm(pc - pb), np.linalg.norm(pa - pc))
        n = max(2, int(math.ceil(longest / (VOX * 0.5))))
        ci = colour_index(rgb)
        for i in range(n + 1):
            for j in range(n + 1 - i):
                s, t = i / n, j / n
                p = pa + (pb - pa) * s + (pc - pa) * t
                key = (int(p[0] // VOX), int(p[1] // VOX), int(p[2] // VOX))
                grid.setdefault(key, ci)
    return grid

def greedy(grid):
    boxes, used = [], set()
    for key in sorted(grid):
        if key in used:
            continue
        ci = grid[key]
        x, y, z = key
        dx = 1
        while (x + dx, y, z) in grid and grid[(x + dx, y, z)] == ci and (x + dx, y, z) not in used:
            dx += 1
        dz = 1
        while all((x + i, y, z + dz) in grid and grid[(x + i, y, z + dz)] == ci and (x + i, y, z + dz) not in used for i in range(dx)):
            dz += 1
        dy = 1
        while all((x + i, y + dy, z + k) in grid and grid[(x + i, y + dy, z + k)] == ci and (x + i, y + dy, z + k) not in used
                  for i in range(dx) for k in range(dz)):
            dy += 1
        for i in range(dx):
            for j in range(dy):
                for k in range(dz):
                    used.add((x + i, y + j, z + k))
        boxes.append((x, y, z, dx, dy, dz, ci))
    return boxes

def model(boxes, grid):
    xs = [b[0] for b in boxes]; ys = [b[1] for b in boxes]; zs = [b[2] for b in boxes]
    x0 = (min(xs) + max(b[0] + b[3] for b in boxes)) / 2.0
    z0 = (min(zs) + max(b[2] + b[5] for b in boxes)) / 2.0
    y0 = min(ys)
    els = []
    for x, y, z, dx, dy, dz, ci in boxes:
        u, w = (ci % 16) + 0.25, (ci // 16) + 0.25
        uv = [u, w, u + 0.5, w + 0.5]
        def free(cells):
            return any(c not in grid for c in cells)
        faces = {}
        if free([(x - 1, y + j, z + k) for j in range(dy) for k in range(dz)]): faces["west"] = {"uv": uv, "texture": "#0"}
        if free([(x + dx, y + j, z + k) for j in range(dy) for k in range(dz)]): faces["east"] = {"uv": uv, "texture": "#0"}
        if free([(x + i, y - 1, z + k) for i in range(dx) for k in range(dz)]): faces["down"] = {"uv": uv, "texture": "#0"}
        if free([(x + i, y + dy, z + k) for i in range(dx) for k in range(dz)]): faces["up"] = {"uv": uv, "texture": "#0"}
        if free([(x + i, y + j, z - 1) for i in range(dx) for j in range(dy)]): faces["north"] = {"uv": uv, "texture": "#0"}
        if free([(x + i, y + j, z + dz) for i in range(dx) for j in range(dy)]): faces["south"] = {"uv": uv, "texture": "#0"}
        if not faces:
            continue
        els.append({"from": [round((x - x0) * VOX + 8, 3), round((y - y0) * VOX + 5, 3), round((z - z0) * VOX + 8, 3)],
                    "to": [round((x + dx - x0) * VOX + 8, 3), round((y + dy - y0) * VOX + 5, 3), round((z + dz - z0) * VOX + 8, 3)],
                    "faces": faces})
    return els

DISPLAY = {
    "thirdperson_righthand": {"rotation": [0, -90, 0], "translation": [0, 3, 1], "scale": [0.7, 0.7, 0.7]},
    "firstperson_righthand": {"rotation": [0, 90, 0], "translation": [-3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "firstperson_lefthand": {"rotation": [0, 90, 0], "translation": [3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "gui": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
    "ground": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [0.5, 0.5, 0.5]},
    "fixed": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
}

os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
os.makedirs(os.path.join(OUT, "textures/item"), exist_ok=True)
total = 0
for wid, name in MAP.items():
    path = os.path.join(SRC, name + ".obj")
    verts, tris = read_obj(path)
    grid = voxelise(verts, tris)
    boxes = greedy(grid)
    els = model(boxes, grid)
    m = {"textures": {"0": "wardogs:item/gun_palette", "particle": "wardogs:item/gun_palette"}, "elements": els, "display": DISPLAY,
         "gui_light": "front"}
    json.dump(m, open(os.path.join(OUT, "models/item", wid + ".json"), "w"), separators=(",", ":"))
    total += len(els)
    print(wid, name, len(els), "boxes")
img = Image.new("RGBA", (16, 16), (0, 0, 0, 0))
assert len(palette) <= 256, len(palette)
for rgb, i in palette.items():
    img.putpixel((i % 16, i // 16), rgb + (255,))
img.save(os.path.join(OUT, "textures/item/gun_palette.png"))
print("palette colours:", len(palette), "total boxes:", total)
