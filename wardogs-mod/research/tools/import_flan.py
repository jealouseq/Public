#!/usr/bin/env python3
"""Builds vehicle and launcher models from the Flan's Mod 'Modern Warfare' content pack (1.12.2).

Third-party assets (the pack is not ours): this reads a copy you extracted yourself and writes into the git-ignored local-data/generated
tree only - the models and skins never enter git. See flan_import.py for how the Java model classes are replayed.

usage: import_flan.py <extracted pack dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys
import numpy as np
import flan_import as fi
import objlib
import meshkit as mk
import vehicles_proc as vp

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
PACK = sys.argv[1]
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")
MODELS = os.path.join(PACK, "com/flansmod/modernweapons/client/model")
SKINS = os.path.join(PACK, "assets/flansmod/skins")

EXCLUDE = {"bodyDoorOpenModel", "ammoModel", "slideModel", "defaultScopeModel", "attachmentModel", "defaultBarrelModel", "defaultStockModel",
           "defaultGripModel", "breakActionModel", "pumpModel", "minigunBarrelModel"}

# our vehicle id -> Flan model class, skin, scale (blocks per model unit), where the nose points in the flipped model (+1 = +x', -1 = -x'),
# optional main rotor (radius in blocks, blades) and a roof gun for the armed variants
VEHICLES = {
    "humvee": dict(cls="ModelHumvee", skin="skinhumvee", scale=0.68, front=-1),
    "humvee_m249": dict(cls="ModelHumvee", skin="skinhumvee", scale=0.68, front=-1, gun="m249"),
    "humvee_minigun": dict(cls="ModelHumvee", skin="skinhumvee", scale=0.68, front=-1, gun="minigun"),
    "l2a6": dict(cls="ModelLeopard2A6", skin="leopard2a6", scale=0.65, front=-1),
    "mh6": dict(cls="ModelLittleBirdMH6", skin="skinlittlebird", scale=0.85, front=1, rotor=(4.2, 4)),
    "ah6m": dict(cls="ModelLittleBird", skin="skinlittlebird", scale=1.0, front=1, rotor=(4.2, 4)),
    "ah6r": dict(cls="ModelLittleBird", skin="skinlittlebird", scale=1.0, front=1, rotor=(4.2, 4)),
    "z20_lakota": dict(cls="ModelBlackHawk", skin="skinblackhawk", scale=0.5, front=1, rotor=(4.3, 4)),
    "z20_lakota_miniguns": dict(cls="ModelBlackHawk", skin="skinblackhawk", scale=0.5, front=1, rotor=(4.3, 4)),
    "havoc": dict(cls="ModelHind", skin="hind", scale=0.42, front=1, rotor=(3.7, 5)),
}

# launchers: our weapon id -> Flan model, skin, length in blocks (muzzle toward +x like the other gun item models)
LAUNCHERS = {
    "rpg7": ("ModelRPG", "rpg", 1.7), "maaws": ("ModelAT4", "at4", 1.8), "verba": ("ModelStinger", "stinger", 1.8), "mgl40": ("ModelGL6", "gl6", 1.1),
}

DISPLAY = {
    "thirdperson_righthand": {"rotation": [0, -90, 0], "translation": [0, 3, 1], "scale": [0.7, 0.7, 0.7]},
    "firstperson_righthand": {"rotation": [0, 90, 0], "translation": [-3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "firstperson_lefthand": {"rotation": [0, 90, 0], "translation": [3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "gui": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
    "ground": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [0.5, 0.5, 0.5]},
    "fixed": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
}


def to_dict(V, T, UV):
    """Per-corner arrays (vertex i has uv i) -> objlib mesh dict."""
    return {"v": V, "vt": UV * np.array([1, -1]) + np.array([0, 1]), "tris": T, "tuv": T.copy(), "group": ["x"] * len(T)}


def skin_texture(name, ref_name):
    src = os.path.join(SKINS, name + ".png")
    dst = os.path.join(OUT, "textures/item/imp", ref_name + ".png")
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy(src, dst)
    return f"wardogs:item/imp/{ref_name}"


def proc_part_dict(mesh, uv):
    """A procedural meshlib.Mesh as an objlib mesh whose every corner points at one texel of the skin (a dark patch)."""
    return {"v": mesh.verts, "vt": np.array([uv]), "tris": mesh.tris, "tuv": np.zeros(mesh.tris.shape, int), "group": ["x"] * len(mesh.tris)}


def merge(a, b):
    off = len(a["v"])
    vt_off = len(a["vt"])
    return {"v": np.vstack([a["v"], b["v"]]), "vt": np.vstack([a["vt"], b["vt"]]), "tris": np.vstack([a["tris"], b["tris"] + off]),
            "tuv": np.vstack([a["tuv"], b["tuv"] + vt_off]), "group": a["group"] + b["group"]}


def dark_texel(skin_name):
    from PIL import Image
    from numpy.lib.stride_tricks import sliding_window_view
    a = np.asarray(Image.open(os.path.join(SKINS, skin_name + ".png")).convert("RGB"), float).mean(2)
    w = sliding_window_view(a, (3, 3)).mean((2, 3))
    y, x = np.unravel_index(np.argmin(w), w.shape)
    return (x + 1.5) / a.shape[1], 1 - (y + 1.5) / a.shape[0]


def build_vehicle(vid, c, rotors):
    name, arrays, fields = fi.parse(os.path.join(MODELS, c["cls"] + ".class"))
    names = [k for k in arrays if k not in EXCLUDE]
    V, T, UV = fi.build_mesh(fi.parts_of(arrays, names))
    # rotate so the nose points to -z: front +x' -> z = -x', x = z' ; front -x' -> z = x', x = -z'
    if c["front"] > 0:
        W = np.stack([V[:, 2], V[:, 1], -V[:, 0]], 1)
    else:
        W = np.stack([-V[:, 2], V[:, 1], V[:, 0]], 1)
    W = W * c["scale"]
    lo, hi = W.min(0), W.max(0)
    W = W - np.array([(lo[0] + hi[0]) / 2, lo[1], (lo[2] + hi[2]) / 2])
    mesh = to_dict(W, T, UV)
    if "gun" in c:
        top = W[:, 1].max()
        lo, hi = W.min(0), W.max(0)
        cab = [p for p in mesh["v"] if abs(p[0]) < 0.4]
        cab_top = max(p[1] for p in cab)
        hz = lo[2] + (hi[2] - lo[2]) * 0.52
        parts = [mk.cylinder((0, cab_top + 0.1, hz), 0.5, 0.14, "y", 14, vp.C["dark"]), mk.box(0, cab_top + 0.38, hz, 0.35, 0.5, 0.35, vp.C["dark"])]
        if c["gun"] == "minigun":
            for k in range(4):
                a = k * np.pi / 2
                parts.append(mk.cylinder((0.07 * np.cos(a), cab_top + 0.62 + 0.07 * np.sin(a), hz - 0.75), 0.025, 1.1, "z", 6, vp.C["black"]))
        else:
            parts += vp.barrel(hz - 0.8, cab_top + 0.65, 1.0, 0.04, vp.C["black"])
        gun = mk.union(parts)
        mesh = merge(mesh, proc_part_dict(gun, dark_texel(c["skin"])))
    ref = skin_texture(c["skin"], "v_" + c["skin"])
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    objlib.write(mesh, os.path.join(OUT, "models/vehicle", vid + ".obj"), "#base", smooth_angle=1.0)
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
               "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
              open(os.path.join(OUT, "models/vehicle", vid + ".json"), "w"))
    lo, hi = objlib.bbox(mesh)
    print(vid, len(mesh["tris"]), "tris, size", (hi - lo).round(2))
    if "rotor" in c:
        radius, blades = c["rotor"]
        top = mesh["v"][:, 1].max()
        body = [p for p in mesh["v"] if abs(p[0]) < 0.6 and abs(p[2]) < (hi[2] - lo[2]) * 0.3]
        hub_y = max(p[1] for p in body) + 0.25
        hub_z = float(np.mean([p[2] for p in body]))
        vp.SPIN.clear()
        vp.rotor(hub_y, radius, blades, vp.C["dark"], z=hub_z)
        hx, hy, hz2 = vp.SPIN_HUB[0]
        parts = [mk.translate(p, -hx, -hy, -hz2) for p in vp.SPIN]
        import meshlib as ml
        ml.write_obj(ml.compact(mk.union(parts)), os.path.join(OUT, "models/vehicle", vid + "_rotor.obj"), vid + "_rotor.mtl", "#base", uv_fit=True)
        json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}_rotor.obj", "flip_v": True, "automatic_culling": False,
                   "shade_quads": True, "textures": {"base": "wardogs:item/mesh_white", "particle": "wardogs:item/mesh_white"}},
                  open(os.path.join(OUT, "models/vehicle", vid + "_rotor.json"), "w"))
        rotors[vid] = [round(hx, 3), round(hy, 3), round(hz2, 3)]


def build_launcher(wid, cls, skin, length):
    name, arrays, fields = fi.parse(os.path.join(MODELS, cls + ".class"))
    names = [k for k in arrays if k not in EXCLUDE]
    V, T, UV = fi.build_mesh(fi.parts_of(arrays, names), flip=(1, -1, -1))   # muzzle toward +x
    lo, hi = V.min(0), V.max(0)
    sc = length / (hi[0] - lo[0])
    V = (V - np.array([(lo[0] + hi[0]) / 2, lo[1], (lo[2] + hi[2]) / 2])) * sc + np.array([0.5, 0.3125, 0.5])
    ref = skin_texture(skin, "w_" + skin)
    os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
    mesh = to_dict(V, T, UV)
    objlib.write(mesh, os.path.join(OUT, "models/item", wid + ".obj"), "#base", smooth_angle=1.0)
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/item/{wid}.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
               "gui_light": "front", "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}, "display": DISPLAY},
              open(os.path.join(OUT, "models/item", wid + ".json"), "w"))
    print(wid, len(T), "tris")


if __name__ == "__main__":
    rotors_path = os.path.join(ROOT, "src/main/resources/assets/wardogs/models/vehicle/rotors.json")
    rotors = json.load(open(rotors_path)) if os.path.exists(rotors_path) else {}
    for vid, c in VEHICLES.items():
        build_vehicle(vid, c, rotors)
    for wid, (cls, skin, length) in LAUNCHERS.items():
        build_launcher(wid, cls, skin, length)
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    json.dump(rotors, open(os.path.join(OUT, "models/vehicle/rotors.json"), "w"))
