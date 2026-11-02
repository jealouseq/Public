#!/usr/bin/env python3
"""Builds vehicle models from the 'Immersive Vehicle - LAV' pack (1.16.5, All Rights Reserved): the T-72B3, the MT-LB and the Bradley M3.

Third-party assets: this reads a copy you extracted yourself and writes into the git-ignored local-data/generated tree only.
The pack's OBJ files have no tracks (the game generates them), so a dark track belt is built around the road wheels here.

usage: import_lav.py <extracted LAV jar dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys
import numpy as np
from PIL import Image
import objlib

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(sys.argv[1], "assets/lav")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")

# our vehicle id -> (pack model, texture, overall length wanted in blocks including the gun, wheel-object prefixes for the track belts)
MODELS = {
    "t72b3": ("t_72b3", "t_72b3", 9.6),
    "m113": ("mtlb_grob", "mtlb_grob_tan", 6.2),
}


def dark_uv(tex):
    """UV of the darkest 3x3 patch of the texture (for the generated tracks)."""
    a = np.asarray(tex.convert("RGB"), float).mean(2)
    k = np.ones((3, 3)) / 9
    from numpy.lib.stride_tricks import sliding_window_view
    w = sliding_window_view(a, (3, 3)).mean((2, 3))
    y, x = np.unravel_index(np.argmin(w), w.shape)
    return (x + 1.5) / tex.size[0], 1 - (y + 1.5) / tex.size[1]


def belt(P_side, extra=0.07, chamfer=0.3):
    """A track belt (vertices, triangles) around the wheels given as points: a chamfered stadium in the z-y plane extruded along x."""
    x0, x1 = P_side[:, 0].min(), P_side[:, 0].max()
    y0, y1 = P_side[:, 1].min() - extra, P_side[:, 1].max() + extra
    z0, z1 = P_side[:, 2].min() - extra, P_side[:, 2].max() + extra
    c = chamfer
    prof = [(z0, y0 + c), (z0 + c, y0), (z1 - c, y0), (z1, y0 + c), (z1, y1 - c), (z1 - c, y1), (z0 + c, y1), (z0, y1 - c)]
    n = len(prof)
    V = [(x0, y, z) for z, y in prof] + [(x1, y, z) for z, y in prof]
    T = []
    for i in range(n):
        j = (i + 1) % n
        T += [(i, j, n + j), (i, n + j, n + i)]
    for i in range(1, n - 1):
        T += [(0, i + 1, i), (n, n + i, n + i + 1)]
    V = np.array(V)
    T = np.array(T)
    cen = V.mean(0)
    for k in range(len(T)):  # outward winding
        a, b, cc = V[T[k]]
        if np.cross(b - a, cc - a) @ ((a + b + cc) / 3 - cen) < 0:
            T[k] = T[k][::-1]
    return V, T


def build(vid, name, tex_name, length):
    m = objlib.read(os.path.join(SRC, "objmodels/vehicles", name + ".obj"))
    tex = Image.open(os.path.join(SRC, "textures/vehicles", tex_name + ".png"))
    wheel = lambda g, side: g.startswith(side + "w") or g.startswith(side + "cogs")
    sides = {}
    for side in ("l", "r"):
        idx = [i for i, g in enumerate(m["group"]) if wheel(g, side)]
        sides[side] = m["v"][m["tris"][idx].reshape(-1)] if idx else None
    body = objlib.select(m, lambda g: not g.startswith("&"))
    V, T, TUV = body["v"], body["tris"], body["tuv"]
    vt = m["vt"]
    # tracks
    dark = dark_uv(tex)
    vt = np.vstack([vt, [dark]])
    di = len(vt) - 1
    Vs, Ts, Us = [V], [T], [TUV]
    off = len(V)
    for side, P in sides.items():
        if P is None:
            continue
        bv, bt = belt(P)
        Vs.append(bv)
        Ts.append(bt + off)
        Us.append(np.full(bt.shape, di))
        off += len(bv)
    mesh = {"v": np.vstack(Vs), "vt": vt, "tris": np.vstack(Ts), "tuv": np.vstack(Us), "group": ["x"] * sum(len(t) for t in Ts)}
    # the pack's vehicles face +z; ours face -z. Centre on the hull, rest on the ground, scale to the wanted length.
    lo, hi = objlib.bbox(mesh)
    s = length / (hi[2] - lo[2])
    cx, cz = (lo[0] + hi[0]) / 2, (lo[2] + hi[2]) / 2
    mesh = objlib.transform(mesh, lambda v: np.stack([-(v[:, 0] - cx) * s, (v[:, 1] - lo[1]) * s, -(v[:, 2] - cz) * s], 1))
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    ref = f"wardogs:item/imp/v_{tex_name}"
    shutil.copy(os.path.join(SRC, "textures/vehicles", tex_name + ".png"), os.path.join(OUT, "textures/item/imp", f"v_{tex_name}.png"))
    objlib.write(mesh, os.path.join(OUT, "models/vehicle", vid + ".obj"), "#base", smooth_angle=35)
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}.obj", "flip_v": True, "automatic_culling": False,
               "shade_quads": True, "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
              open(os.path.join(OUT, "models/vehicle", vid + ".json"), "w"))
    lo, hi = objlib.bbox(mesh)
    print(vid, len(mesh["tris"]), "tris, size", (hi - lo).round(2), "scale", round(s, 3))


if __name__ == "__main__":
    for vid, (name, tex, length) in MODELS.items():
        build(vid, name, tex, length)
