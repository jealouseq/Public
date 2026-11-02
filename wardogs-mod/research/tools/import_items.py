#!/usr/bin/env python3
"""Replaces the box models of grenades, mines, charges and the detonator by the item models of 'Superb Warfare' (GPL-3.0, see import_sbw.py).
Their item models are ordinary JSON models (elements + textures): copied with the textures into the git-ignored local-data/generated tree.

usage: import_items.py <extracted Superb Warfare dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(sys.argv[1], "assets/superbwarfare")
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")

# our item id -> model of the other mod (models/item/<name>.json)
ITEMS = {"c4": "c4_bomb", "claymore": "claymore_mine", "remote_detonator": "detonator",
         "smoke_white": "m18_smoke_grenade", "smoke_black": "m18_smoke_grenade", "at_mine": "blu_43_mine"}


def grenade():
    """The frag grenade only exists as a Bedrock geometry: converted like a gun, short and held upright-ish."""
    import geo_import as gi
    from PIL import Image
    geo = os.path.join(SRC, "models/bedrock/item/hand_grenade.geo.json")
    tex = os.path.join(SRC, "textures/bedrock/item/hand_grenade.png")
    if not os.path.exists(geo):
        return 0
    V, T, UV, _ = gi.convert(geo, Image.open(tex).size, ("camera", "pin_", "lefthand", "righthand"))
    V = gi.normalise(V, 0.55)
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    shutil.copy(tex, os.path.join(OUT, "textures/item/imp/sbw_hand_grenade_geo.png"))
    gi.write_obj(os.path.join(OUT, "models/item/m67_frag.obj"), V, T, UV)
    ref = "wardogs:item/imp/sbw_hand_grenade_geo"
    json.dump({"loader": "neoforge:obj", "model": "wardogs:models/item/m67_frag.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
               "gui_light": "front", "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
              open(os.path.join(OUT, "models/item/m67_frag.json"), "w"))
    return 1


def main():
    os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
    n = grenade()
    for mine, theirs in ITEMS.items():
        path = os.path.join(SRC, "models/item", theirs + ".json")
        if not os.path.exists(path):
            print("missing", theirs)
            continue
        m = json.load(open(path))
        if not m.get("elements") and not m.get("parent", "").endswith("generated"):
            print("not a plain model:", theirs, m.get("parent"))
            continue
        tex = {}
        for k, v in m.get("textures", {}).items():
            if isinstance(v, str) and v.startswith("superbwarfare:"):
                rel = v.split(":", 1)[1]
                src = os.path.join(SRC, "textures", rel + ".png")
                if os.path.exists(src):
                    dst_rel = "item/imp/sbw_" + rel.split("/")[-1]
                    shutil.copy(src, os.path.join(OUT, "textures", dst_rel + ".png")) if os.path.isdir(os.path.join(OUT, "textures")) else (os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True), shutil.copy(src, os.path.join(OUT, "textures", dst_rel + ".png")))
                    tex[k] = "wardogs:" + dst_rel
                    continue
            tex[k] = v
        m["textures"] = tex
        for k in ("credit", "format_version", "groups"):
            m.pop(k, None)
        json.dump(m, open(os.path.join(OUT, "models/item", mine + ".json"), "w"))
        n += 1
    print("items replaced:", n)


if __name__ == "__main__":
    main()
