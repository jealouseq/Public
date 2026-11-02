#!/usr/bin/env python3
"""Imports block models of community mods as decor blocks of this mod: Survivor's Arsenal 'Loot & Decor' (sa_decor, All Rights Reserved) and
Macaw's Roads and Streets (mcwroads, All Rights Reserved).

Third-party assets: reads copies you extracted yourself (local-data/imports/sa-decor, local-data/imports/mcw-roads) and writes into the
git-ignored local-data/generated tree - blocks.json entries (collision boxes), blockstates, block and item models, textures, names. The mod
registers them (DecorBlocks reads data/wardogs/decor/blocks_imported.json when it exists) and the world generator uses them where present.

usage: import_blocks.py [out dir = local-data/generated]
"""
import json, os, re, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, "local-data/generated")
IMP = os.path.join(ROOT, "local-data/imports")
PACKS = {
    "sa": ("sa_decor", os.path.join(IMP, "sa-decor/x_sa_decor-forge-1.20.1-1.0.3/assets/sa_decor")),
    "mcw": ("mcwroads", os.path.join(IMP, "mcw-roads/assets/mcwroads")),
}

# pack -> (model path below models/block, id, light)
SA = ["military_crate", "big_military_crate", "wooden_supply_crate", "big_wooden_crate", "ammo_box", "medicinal_crate", "food_crate", "hesco_barrier",
      "concrete_barrier", "concrete_barrier_big", "construction_barrier", "highway_guardrail", "fire_hydrant", "traffic_cone", "oil_barrel", "oil_barrel_red",
      "oil_barrel_blue", "gas_barrel", "sand_bag", "sand_bag_double", "dumpster", "garbage_bin", "trash_bin", "barricade", "big_barricade", "barbed_wire",
      "danger_sign", "military_bed", "military_radio", "metal_rack", "locker", "big_metal_locker", "gun_cabinet", "fuel_pump", "vending_machine", "park_bench",
      "decor_bench", "planks_pallet", "tire_block", "wood_plank_pile", "safe", "sleeping_bag", "folding_chair", "urban_fence", "telephone", "generator"]
MCW = ["roads/asphalt", "roads/asphalt_center_line", "roads/asphalt_yellow_center_line", "roads/asphalt_double_center_line", "roads/asphalt_broken_center_line",
       "roads/asphalt_yellow_broken_center_line", "roads/asphalt_crosswalk", "roads/asphalt_shoulder_line", "roads/asphalt_arrow", "roads/asphalt_manhole",
       "road_signs/roadsign_rod", "red_circle_signs/no_entry", "red_circle_signs/no_heavy_goods_vehicles", "triangle/cattle", "blue_circle_signs/ahead_only",
       "reflector_guide_post/white_reflector_guide_post", "reflector_guide_post/red_reflector_guide_post"]

DISPLAY = {"gui": {"rotation": [30, 225, 0], "scale": [0.55, 0.55, 0.55]}, "ground": {"translation": [0, 3, 0], "scale": [0.4, 0.4, 0.4]},
           "fixed": {"scale": [0.5, 0.5, 0.5]}, "thirdperson_righthand": {"scale": [0.4, 0.4, 0.4]},
           "firstperson_righthand": {"rotation": [0, 45, 0], "scale": [0.4, 0.4, 0.4]}}


def find_model(assets, rel):
    """models/block/<rel>.json or models/block/<name>/<name>.json, or any json of that name below models/block"""
    base = os.path.join(assets, "models/block")
    for cand in (rel + ".json", rel + "/" + os.path.basename(rel) + ".json"):
        if os.path.exists(os.path.join(base, cand)):
            return os.path.join(base, cand)
    name = os.path.basename(rel) + ".json"
    for dp, _, fs in os.walk(base):
        if name in fs:
            return os.path.join(dp, name)
    return None


def load(assets, ns, ref_or_path):
    """A model with the parents of its own pack merged in (child textures win, child elements replace the parent's)."""
    path = ref_or_path if os.path.isabs(ref_or_path) else os.path.join(assets, "models", ref_or_path.split(":", 1)[-1] + ".json")
    m = json.load(open(path))
    parent = m.get("parent")
    if parent and parent.startswith(ns + ":"):
        p = load(assets, ns, parent)
        merged = dict(p)
        merged["textures"] = {**p.get("textures", {}), **m.get("textures", {})}
        if "elements" in m:
            merged["elements"] = m["elements"]
        for k in ("render_type", "display", "texture_size", "ambientocclusion"):
            if k in m:
                merged[k] = m[k]
        merged.pop("parent", None) if "parent" not in p or p["parent"].startswith(ns + ":") else None
        return merged
    return m


def boxes_of(model):
    out = []
    for e in model.get("elements", []):
        f, t = e["from"], e["to"]
        b = [max(0, min(f[0], t[0])), max(0, min(f[1], t[1])), max(0, min(f[2], t[2])),
             min(16, max(f[0], t[0])), min(16, max(f[1], t[1])), min(16, max(f[2], t[2]))]
        if b[3] - b[0] > 0.05 and b[4] - b[1] > 0.05 and b[5] - b[2] > 0.05:
            out.append([round(v, 3) for v in b])
    if len(out) > 6 or not out:   # many tiny parts: one box around all of them keeps the collision cheap
        if out:
            out = [[min(b[0] for b in out), min(b[1] for b in out), min(b[2] for b in out), max(b[3] for b in out), max(b[4] for b in out), max(b[5] for b in out)]]
        else:
            out = [[0, 0, 0, 16, 16, 16]]
    return out


def import_pack(key, names, blocks, lang):
    ns, assets = PACKS[key]
    if not os.path.isdir(assets):
        print("missing pack", key)
        return
    for rel in names:
        path = find_model(assets, rel)
        if not path:
            print("  no model:", key, rel)
            continue
        model = load(assets, ns, path)
        if not model.get("elements") and not model.get("parent"):
            print("  no elements:", key, rel)
            continue
        short = re.sub(r"[^a-z0-9_]", "_", os.path.basename(rel).lower())
        bid = f"imp_{key}_{short}"
        # textures: copy the png and point the model at the copy
        tex = {}
        for k, v in model.get("textures", {}).items():
            if isinstance(v, str) and v.startswith(ns + ":"):
                sub = v.split(":", 1)[1]                          # block/lootcrates/military_crate
                src = os.path.join(assets, "textures", sub + ".png")
                dst_rel = "block/imp/" + key + "/" + sub.split("/", 1)[-1]
                if os.path.exists(src):
                    dst = os.path.join(OUT, "assets/wardogs/textures", dst_rel + ".png")
                    os.makedirs(os.path.dirname(dst), exist_ok=True)
                    shutil.copy(src, dst)
                    if os.path.exists(src + ".mcmeta"):
                        shutil.copy(src + ".mcmeta", dst + ".mcmeta")
                    tex[k] = "wardogs:" + dst_rel
                else:
                    tex[k] = "minecraft:block/white_concrete"
            else:
                tex[k] = v
        model["textures"] = tex
        model.pop("display", None)
        model.pop("credit", None)
        model.pop("format_version", None)
        model.pop("groups", None)
        mdir = os.path.join(OUT, "assets/wardogs/models/block/imp")
        os.makedirs(mdir, exist_ok=True)
        json.dump(model, open(os.path.join(mdir, bid + ".json"), "w"))
        json.dump({"variants": {f"facing={d}": {"model": f"wardogs:block/imp/{bid}", "y": y} for d, y in
                                (("north", 0), ("east", 90), ("south", 180), ("west", 270))}},
                  open(os.path.join(OUT, "assets/wardogs/blockstates", bid + ".json") if os.path.isdir(os.path.join(OUT, "assets/wardogs/blockstates"))
                       else _mk(os.path.join(OUT, "assets/wardogs/blockstates"), bid + ".json"), "w"))
        idir = os.path.join(OUT, "assets/wardogs/models/item")
        os.makedirs(idir, exist_ok=True)
        json.dump({"parent": f"wardogs:block/imp/{bid}", "display": DISPLAY}, open(os.path.join(idir, bid + ".json"), "w"))
        blocks[bid] = {"light": 0, "solid": True, "boxes": boxes_of(model)}
        lang[f"block.wardogs.{bid}"] = " ".join(w.capitalize() for w in short.split("_"))


def _mk(d, f):
    os.makedirs(d, exist_ok=True)
    return os.path.join(d, f)


def main():
    blocks, lang = {}, {}
    import_pack("sa", SA, blocks, lang)
    import_pack("mcw", MCW, blocks, lang)
    d = os.path.join(OUT, "data/wardogs/decor")
    os.makedirs(d, exist_ok=True)
    json.dump(blocks, open(os.path.join(d, "blocks_imported.json"), "w"), indent=1)
    lp = os.path.join(ROOT, "src/main/resources/assets/wardogs/lang/en_us.json")
    merged = json.load(open(lp))
    merged.update(lang)
    ldir = os.path.join(OUT, "assets/wardogs/lang")
    os.makedirs(ldir, exist_ok=True)
    json.dump(merged, open(os.path.join(ldir, "en_us.json"), "w"), indent=2, ensure_ascii=False)
    print(len(blocks), "blocks imported")


if __name__ == "__main__":
    main()
