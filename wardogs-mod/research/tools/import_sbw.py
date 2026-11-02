#!/usr/bin/env python3
"""Builds gun, vehicle and worn-armour models from 'Superb Warfare' (1.20.1, GPL-3.0; authors Atsuishio, Roki27, Light_Quanta and contributors).

The mod is GPL-3.0 licensed: its models and textures may only be redistributed together with this project if the whole project is licensed
GPL-3.0-or-later and the authors are credited - a decision for the project owner (the repository says "All Rights Reserved" today). Until then this
reads a copy you extracted yourself and writes into the git-ignored local-data/generated tree, like the other imports (see research/IMPORTS.md).

usage: import_sbw.py <extracted Superb Warfare jar dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys
import numpy as np
from PIL import Image
import geo_import as gi
import objlib

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(sys.argv[1], "assets/superbwarfare")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")

JUNK = ("camera", "flare", "smoke", "dogtag", "dummy", "muzzle", "effect", "fire", "propeller_", "rudder", "light_")

# our vehicle id -> dict(model, length (blocks, the whole mesh), skip (extra bone name parts), rotor=(bone name part, blades visual radius))
VEHICLES = {
    "flakpanzer_gepard": dict(model="lav_ad", length=7.4),
    "sph2": dict(model="plz_05", length=10.6),
    "kodiak_pickup": dict(model="sodayo_pick_up", length=4.8),
    "kodiak_m249": dict(model="sodayo_pick_up_hmg", length=4.8),
    "m113": dict(model="bradley", length=6.4),
    "ah6m": dict(model="ah_6", length=7.6, rotor="move_propeller"),
    "ah6r": dict(model="ah_6", length=7.6, rotor="move_propeller"),
    "havoc": dict(model="mi_28", length=9.6, rotor="move_propeller"),
}

# our weapon id -> (Superb Warfare gun, length in blocks)
GUNS = {
    "ak74": ("ak_12", 1.65), "galil": ("ak_47", 1.6), "fal": ("qbz_191", 1.65), "sks": ("sks", 1.75), "svd": ("svd", 1.85), "m14": ("mk_14", 1.75),
    "mosin": ("mosin_nagant", 1.95), "sv98": ("awm", 1.95), "mk22": ("m_98b", 2.0), "pkm": ("m_60", 1.85), "m500": ("m_870", 1.85),
    "ggx17": ("glock_17", 0.75), "ggx18": ("glock_18", 0.75), "mp5": ("mp_5", 1.2), "vityaz": ("mp_5", 1.2), "rpg7": ("rpg", 1.7),
}
# left to the other packs on purpose: their M4 / HK416 / M1911 / M249 / vector / M79 / Igla models come out cleaner there (the Superb Warfare
# ones keep detached stocks or loose parts of unused attachments)
TEXTURE_OF = {"glock_18": "glock_17", "m79": "m_79"}   # models that use another model's texture file
# Every gun model holds all its attachment variants and the arms: keep the default ones (Stock0, Magazine0, Barrel0, iron sights).
# Prefixes of bone names (lower case) that are dropped together with everything below them.
GUN_SKIP = ("camera", "scope", "grip1", "grip2", "grip3", "barrel1", "barrel2", "stock1", "stock2", "magazine1", "magazine2", "lefthand", "righthand",
            "shell", "bullet", "flare", "bipod", "mount", "hidden", "ammotype1", "exp_", "cross", "rail2", "effect", "tuoxin", "sling", "laser")
# guns whose own scope is part of the default look
GUN_KEEP_SCOPE = {"svd", "awm", "m_98b", "ntw_20", "mosin_nagant", "mk_14"}

DISPLAY = {
    "thirdperson_righthand": {"rotation": [0, -90, 0], "translation": [0, 3, 1], "scale": [0.7, 0.7, 0.7]},
    "firstperson_righthand": {"rotation": [0, 90, 0], "translation": [-3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "firstperson_lefthand": {"rotation": [0, 90, 0], "translation": [3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "gui": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
    "ground": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [0.5, 0.5, 0.5]},
    "fixed": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
}


def to_objdict(V, T, UV):
    """Per-corner arrays -> objlib mesh dict (vt stored v-up, as an OBJ is)."""
    return {"v": V, "vt": UV * np.array([1, -1]) + np.array([0, 1]), "tris": T, "tuv": np.arange(len(V)).reshape(-1, 3)[: len(T)] if False else T.copy(),
            "group": ["x"] * len(T)}


def build_vehicle(vid, c, rotors):
    name = c["model"]
    geo = os.path.join(SRC, "models/bedrock/vehicle", name + ".geo.json")
    tex_path = os.path.join(SRC, "textures/bedrock/vehicle", name + ".png")
    tex = Image.open(tex_path)
    skip = JUNK + (("move_propeller",) if "rotor" in c else ())
    V, T, UV, _ = gi.convert(geo, tex.size, skip, raw=True)
    # Bedrock: y up, front -z, +x is the vehicle's left. Game: front -z, x width. The x flip is a mirror, so the winding is reversed.
    W = V * np.array([-1, 1, 1])
    T = T[:, ::-1]
    lo, hi = W.min(0), W.max(0)
    s = c["length"] / (hi[2] - lo[2])
    centre = np.array([(lo[0] + hi[0]) / 2, lo[1], (lo[2] + hi[2]) / 2])
    W = (W - centre) * s
    ref = f"wardogs:item/imp/s_{name}"
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    shutil.copy(tex_path, os.path.join(OUT, "textures/item/imp", f"s_{name}.png"))
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    gi.write_obj(os.path.join(OUT, "models/vehicle", vid + ".obj"), W, T, UV)
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
               "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
              open(os.path.join(OUT, "models/vehicle", vid + ".json"), "w"))
    print(vid, name, len(T), "tris, size", (W.max(0) - W.min(0)).round(2))
    if "rotor" in c:
        d = json.load(open(geo))["minecraft:geometry"][0]
        hub_b = next(b for b in d["bones"] if c["rotor"] in b["name"])["pivot"]
        RV, RT, RUV, _ = gi.convert(geo, tex.size, (), raw=True, under=(c["rotor"],))
        RW = (RV * np.array([-1, 1, 1]) - centre) * s
        RT = RT[:, ::-1]
        hub = (np.array([-hub_b[0], hub_b[1], hub_b[2]]) - centre) * s
        RW = RW - hub
        gi.write_obj(os.path.join(OUT, "models/vehicle", vid + "_rotor.obj"), RW, RT, RUV)
        json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/vehicle/{vid}_rotor.obj", "flip_v": True, "automatic_culling": False,
                   "shade_quads": True, "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
                  open(os.path.join(OUT, "models/vehicle", vid + "_rotor.json"), "w"))
        rotors[vid] = [round(float(hub[0]), 3), round(float(hub[1]), 3), round(float(hub[2]), 3)]
        print("   rotor hub", rotors[vid], len(RT), "tris")


def build_gun(wid, gun, length, cache):
    geo = os.path.join(SRC, "geo", gun + ".geo.json")
    tex_path = os.path.join(SRC, "textures/item", TEXTURE_OF.get(gun, gun) + ".png")
    if not (os.path.exists(geo) and os.path.exists(tex_path)):
        print("missing", gun)
        return
    key = (gun, length)
    if key not in cache:
        skip = tuple(k for k in GUN_SKIP if not (k == "scope" and gun in GUN_KEEP_SCOPE))
        V, T, UV, _ = gi.convert(geo, Image.open(tex_path).size, skip)
        cache[key] = (gi.normalise(V, length), T, UV)
    V, T, UV = cache[key]
    os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    shutil.copy(tex_path, os.path.join(OUT, "textures/item/imp", f"s_{gun}.png"))
    gi.write_obj(os.path.join(OUT, "models/item", wid + ".obj"), V, T, UV)
    ref = f"wardogs:item/imp/s_{gun}"
    json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/item/{wid}.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
               "gui_light": "front", "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}, "display": DISPLAY},
              open(os.path.join(OUT, "models/item", wid + ".json"), "w"))


if __name__ == "__main__":
    only = set(sys.argv[3:])
    rotors_path = os.path.join(OUT, "models/vehicle/rotors.json")
    rotors = json.load(open(rotors_path)) if os.path.exists(rotors_path) else json.load(open(os.path.join(ROOT, "src/main/resources/assets/wardogs/models/vehicle/rotors.json")))
    for vid, c in VEHICLES.items():
        if not only or vid in only or "vehicles" in only:
            build_vehicle(vid, c, rotors)
    os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
    json.dump(rotors, open(rotors_path, "w"))
    cache = {}
    for wid, (gun, length) in GUNS.items():
        if not only or wid in only or "guns" in only:
            build_gun(wid, gun, length, cache)
    print("guns:", len(GUNS))
