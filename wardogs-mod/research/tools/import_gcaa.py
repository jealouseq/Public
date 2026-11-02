#!/usr/bin/env python3
"""Builds item models of our guns from the GeckoLib gun models of 'Guns Craft - Adventurer's Arsenal' (gcaa, 1.20.1, All Rights Reserved).

That mod's assets are third-party: this script only reads a copy that you extracted yourself and writes into the git-ignored
local-data/generated tree, which build.gradle adds to the resources when it exists. Nothing from the mod is committed.

usage: import_gcaa.py <extracted gcaa jar dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys
from PIL import Image
import geo_import as gi

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(sys.argv[1], "assets/gcaa/model_assets/guns")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")

# our weapon id -> (gcaa model, length of the gun in blocks)
MAP = {
    "ak74": ("ak12", 1.65), "galil": ("akm", 1.6), "m4": ("m4a1", 1.6), "fal": ("mcx_spear", 1.65), "t21": ("aug_a3", 1.45),
    "m17s": ("aug_a3", 1.45), "a91": ("rpk_16", 1.7), "kh2002": ("mk47", 1.65), "tcx_spr": ("hk_g28", 1.7),
    "sks": ("sks", 1.75), "svd": ("hk_g28", 1.85), "bmr308": ("mk47", 1.75), "m14": ("mcx_spear", 1.75),
    "scout_td": ("fn_ballista", 1.9), "mosin": ("awp", 1.95), "sv98": ("awp", 1.95), "mk22": ("fn_ballista", 2.0), "amr50": ("fn_ballista", 2.1),
    "m249": ("m249", 1.8), "pkm": ("m60e4", 1.85), "evo3": ("rpk_16", 1.7),
    "mp43": ("beretta_686", 1.85), "m500": ("m870", 1.85),
    "ggx17": ("g19", 0.75), "ggx18": ("fn57", 0.8), "m1911": ("fn57", 0.8), "judge": ("python_357", 0.95),
    "amp9": ("vector_45", 1.1), "bizon": ("annihilator", 1.15), "vityaz": ("mp5", 1.2), "mp5": ("mp5", 1.2), "super45": ("vector_45", 1.1),
}
SKIP = gi.SKIP_DEFAULT + ("exp_", "drum", "shell", "fired")

DISPLAY = {
    "thirdperson_righthand": {"rotation": [0, -90, 0], "translation": [0, 3, 1], "scale": [0.7, 0.7, 0.7]},
    "firstperson_righthand": {"rotation": [0, 90, 0], "translation": [-3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "firstperson_lefthand": {"rotation": [0, 90, 0], "translation": [3, 3, -2], "scale": [0.5, 0.5, 0.5]},
    "gui": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
    "ground": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [0.5, 0.5, 0.5]},
    "fixed": {"rotation": [0, 0, 0], "translation": [0, 0, 0], "scale": [0.58, 0.58, 0.58]},
}


def main():
    os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    cache = {}
    for wid, (gun, length) in MAP.items():
        geo = os.path.join(SRC, gun, gun + ".geo.json")
        tex = os.path.join(SRC, gun, gun + ".png")
        if not os.path.exists(geo):
            print("missing", gun)
            continue
        key = (gun, length)
        if key not in cache:
            V, T, UV, _ = gi.convert(geo, Image.open(tex).size, SKIP)
            cache[key] = (gi.normalise(V, length), T, UV)
        V, T, UV = cache[key]
        gi.write_obj(os.path.join(OUT, "models/item", wid + ".obj"), V, T, UV)
        shutil.copy(tex, os.path.join(OUT, "textures/item/imp", gun + ".png"))
        ref = f"wardogs:item/imp/{gun}"
        json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/item/{wid}.obj", "flip_v": True, "automatic_culling": False,
                   "shade_quads": True, "gui_light": "front", "render_type": "minecraft:cutout",
                   "textures": {"base": ref, "particle": ref}, "display": DISPLAY}, open(os.path.join(OUT, "models/item", wid + ".json"), "w"))
    print("guns written:", len(MAP), "->", OUT)


if __name__ == "__main__":
    main()
