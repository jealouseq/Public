#!/usr/bin/env python3
"""Generates the custom tree materials of Bakurani: painterly foliage and bark textures, blockstates, models, item models, tags, names.

usage: gen_nature.py <assets/wardogs> <data dir>   (data dir = src/main/resources/data)
Species: fir, pine, birch, aspen (gold), oak, beech (copper). Foliage uses cutout alpha; bark has side and end textures.
"""
import json, os, sys
import numpy as np
from PIL import Image, ImageDraw

ASSETS, DATA = sys.argv[1], sys.argv[2]
rng = np.random.default_rng(7)


def hexc(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def vnoise(w, h, scale, seed=0):
    r = np.random.default_rng(seed)
    gw, gh = max(2, w // scale + 2), max(2, h // scale + 2)
    g = r.random((gh, gw))
    img = Image.fromarray((g * 255).astype(np.uint8)).resize((w, h), Image.BICUBIC)
    return np.asarray(img, float) / 255


def fbm(w, h, base=8, octaves=3, seed=0):
    out = np.zeros((h, w))
    amp, tot = 1.0, 0
    for o in range(octaves):
        out += amp * vnoise(w, h, max(1, base // (2 ** o)), seed + o)
        tot += amp
        amp *= 0.5
    return out / tot


def lum(c):
    return 0.3 * c[0] + 0.59 * c[1] + 0.11 * c[2]


def tweak(c, k, warm):
    """Variant colour: brighter by k and shifted toward yellow by warm."""
    return (min(255, c[0] * (1 + k) + warm), min(255, c[1] * (1 + k) + warm * 0.6), max(0, c[2] * (1 + k) - warm * 0.4))


def foliage(name, size, palette, strokes, stroke_len, droop, hole, seed, leafy=False, variant=0):
    """A cut-out foliage texture. Light and dark areas come from smooth noise, so the colour clumps like sun-lit and shaded sprays rather
    than speckling; needles are short strokes that follow a slowly changing direction, leaves are small shaded stamps."""
    r = np.random.default_rng(seed + 1000 * variant)
    pal = sorted((hexc(c) for c in palette), key=lum)
    if variant:
        pal = [tweak(c, 0.13 * variant, 10 * variant) for c in pal]
    n_col = fbm(size, size, max(4, size // 3), 3, seed + 7 * variant)
    ang_f = fbm(size, size, max(4, size // 2), 2, seed + 55)
    img = np.zeros((size, size, 4), np.uint8)
    base = np.array(pal[0], float)
    for c in range(3):
        img[..., c] = np.clip(base[c] * (0.72 + 0.35 * n_col), 0, 255)
    img[..., 3] = 255
    im = Image.fromarray(img, "RGBA")
    d = ImageDraw.Draw(im)
    for _ in range(strokes * 2 if leafy else strokes):
        x, y = int(r.integers(0, size)), int(r.integers(0, size))
        t = n_col[y, x] * 0.85 + 0.15 * r.random()
        col = tuple(int(v) for v in pal[min(len(pal) - 1, int(t * len(pal)))]) + (255,)
        if leafy:
            hi = tuple(min(255, int(v * 1.18)) for v in col[:3]) + (255,)
            lo = tuple(int(v * 0.72) for v in col[:3]) + (255,)
            shape = [(0, 0), (1, 0), (0, 1), (1, 1)]
            shape.remove(shape[int(r.integers(0, 4))])
            for ox in (0, size, -size):
                for oy in (0, size, -size):
                    for (dx_, dy_) in shape:
                        c2 = hi if dy_ == 0 and dx_ == 0 else lo if dy_ == 1 and dx_ == 1 else col
                        d.point([x + dx_ + ox, y + dy_ + oy], fill=c2)
        else:
            a = np.radians(90 + (ang_f[y, x] - 0.5) * 2.2 * droop + r.normal(0, droop * 0.35))
            L = stroke_len * (0.6 + 0.8 * r.random())
            ex, ey = x + np.cos(a) * L, y + np.sin(a) * L
            for ox in (0, size, -size):
                for oy in (0, size, -size):
                    d.line([x + ox, y + oy, ex + ox, ey + oy], fill=col, width=1)
    arr = np.asarray(im).copy()
    g = fbm(size, size, max(2, size // 6), 2, seed + 99 + variant)
    arr[g > (1 - hole), 3] = 0
    Image.fromarray(arr, "RGBA").save(os.path.join(ASSETS, "textures/block", name + ".png"))


def bark(name, size, palette, kind, seed):
    r = np.random.default_rng(seed)
    cols = [np.array(hexc(c), float) for c in palette]
    if kind == "vertical":       # spruce / oak style: furrows running up the trunk
        n = np.zeros((size, size))
        for k in range(3):
            xs = vnoise(size, 1, 2, seed + k)[0]
            n += np.repeat(xs[None, :], size, 0) * (0.6 ** k)
        n += 0.35 * fbm(size, size, 6, 2, seed + 5)
        n = (n - n.min()) / (n.max() - n.min())
    elif kind == "birch":
        n = 0.3 + 0.2 * fbm(size, size, 6, 2, seed)
    else:
        n = fbm(size, size, 5, 3, seed)
    img = np.zeros((size, size, 3))
    for c in range(3):
        lo, mid, hi = cols[0][c], cols[1][c], cols[2][c]
        img[..., c] = np.where(n < 0.5, lo + (mid - lo) * n * 2, mid + (hi - mid) * (n - 0.5) * 2)
    im = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8), "RGB")
    if kind == "birch":
        d = ImageDraw.Draw(im)
        for _ in range(size // 2):
            x, y = r.integers(0, size), r.integers(0, size)
            w = int(r.integers(2, size // 3))
            d.line([x, y, x + w, y], fill=(40, 40, 42), width=1 if r.random() < 0.7 else 2)
        for _ in range(size // 4):
            x, y = r.integers(0, size), r.integers(0, size)
            d.rectangle([x, y, x + 1, y + 2], fill=(150, 146, 138))
    im.save(os.path.join(ASSETS, "textures/block", name + ".png"))


def rings(name, size, light, dark, seed):
    yy, xx = np.mgrid[0:size, 0:size]
    d = np.hypot(xx - size / 2 + 0.5, yy - size / 2 + 0.5)
    n = 0.5 + 0.5 * np.sin(d * 1.7 + 0.8 * fbm(size, size, 6, 2, seed))
    img = np.zeros((size, size, 3))
    for c in range(3):
        img[..., c] = hexc(dark)[c] + (hexc(light)[c] - hexc(dark)[c]) * n
    Image.fromarray(img.astype(np.uint8), "RGB").save(os.path.join(ASSETS, "textures/block", name + ".png"))


SPECIES = {
    "fir": dict(size=64, palette=["#243f26", "#1b3320", "#2f5230", "#3f6a3a", "#567e48"], strokes=520, L=6, droop=28, hole=0.22, seed=11, leafy=False,
                bark=("vertical", ["#2c2019", "#4a3626", "#6a5038"], "#7a5a3a", "#3a2a1a"), label="Fir Needles", log="Fir Bark"),
    "pine": dict(size=64, palette=["#2d4a24", "#223a1c", "#3d6030", "#587c3c", "#7a9a52"], strokes=420, L=9, droop=18, hole=0.30, seed=21, leafy=False,
                 bark=("vertical", ["#5a3a26", "#9a5a35", "#c47a44"], "#c98a55", "#6a4126"), label="Pine Needles", log="Pine Bark"),
    "birch": dict(size=32, palette=["#5d7d27", "#76973a", "#8fb04a", "#a3c05a", "#bccf72"], strokes=170, L=0, droop=0, hole=0.20, seed=31, leafy=True,
                  bark=("birch", ["#d8d5cc", "#ece9e1", "#c2beb3"], "#e8e3d6", "#a49a86"), label="Birch Leaves", log="Birch Bark"),
    "aspen": dict(size=32, palette=["#a8761c", "#c79227", "#dcae3c", "#e8c050", "#f1d36e"], strokes=170, L=0, droop=0, hole=0.20, seed=41, leafy=True,
                  bark=("birch", ["#cfcabd", "#e3dfd2", "#b8b3a5"], "#e8e3d6", "#a49a86"), label="Aspen Leaves", log="Aspen Bark"),
    "oak": dict(size=32, palette=["#35541c", "#456a25", "#5a8030", "#6e9640", "#86ab52"], strokes=190, L=0, droop=0, hole=0.16, seed=51, leafy=True,
                bark=("vertical", ["#3d352c", "#6a5f52", "#8a7d6c"], "#8a7a62", "#4b3f30"), label="Oak Leaves", log="Oak Bark"),
    "beech": dict(size=32, palette=["#7e3418", "#a04c20", "#bd6828", "#d4823a", "#e49c52"], strokes=190, L=0, droop=0, hole=0.16, seed=61, leafy=True,
                  bark=("vertical", ["#4a4a48", "#777570", "#9a9892"], "#8a7a62", "#4b3f30"), label="Beech Leaves", log="Beech Bark"),
}


def write_json(path, obj):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    json.dump(obj, open(path, "w"), indent=1)


def main():
    os.makedirs(os.path.join(ASSETS, "textures/block"), exist_ok=True)
    lang = {}
    leaves, logs = [], []
    for sp, c in SPECIES.items():
        foliage(sp + "_foliage", c["size"], c["palette"], c["strokes"], c["L"], c["droop"], c["hole"], c["seed"], c["leafy"])
        foliage(sp + "_foliage_b", c["size"], c["palette"], c["strokes"], c["L"], c["droop"], c["hole"], c["seed"], c["leafy"], variant=1)
        kind, pal, rl, rd = c["bark"]
        bark(sp + "_bark", 32, pal, kind, c["seed"] + 3)
        rings(sp + "_bark_top", 32, rl, rd, c["seed"] + 4)
        # foliage block: cube with cutout
        for suffix in ("", "_b"):
            write_json(os.path.join(ASSETS, "models/block", sp + "_foliage" + suffix + ".json"),
                       {"parent": "minecraft:block/cube_all", "render_type": "minecraft:cutout_mipped", "textures": {"all": f"wardogs:block/{sp}_foliage{suffix}"}})
        write_json(os.path.join(ASSETS, "blockstates", sp + "_foliage.json"),
                   {"variants": {"": [{"model": f"wardogs:block/{sp}_foliage{sx}", "y": a} for sx in ("", "_b") for a in (0, 90, 180, 270)]}})
        write_json(os.path.join(ASSETS, "models/item", sp + "_foliage.json"), {"parent": f"wardogs:block/{sp}_foliage"})
        # bark: pillar
        write_json(os.path.join(ASSETS, "models/block", sp + "_bark.json"),
                   {"parent": "minecraft:block/cube_column", "textures": {"end": f"wardogs:block/{sp}_bark_top", "side": f"wardogs:block/{sp}_bark"}})
        write_json(os.path.join(ASSETS, "models/block", sp + "_bark_horizontal.json"),
                   {"parent": "minecraft:block/cube_column_horizontal", "textures": {"end": f"wardogs:block/{sp}_bark_top", "side": f"wardogs:block/{sp}_bark"}})
        write_json(os.path.join(ASSETS, "blockstates", sp + "_bark.json"),
                   {"variants": {"axis=y": {"model": f"wardogs:block/{sp}_bark"},
                                 "axis=z": {"model": f"wardogs:block/{sp}_bark_horizontal", "x": 90},
                                 "axis=x": {"model": f"wardogs:block/{sp}_bark_horizontal", "x": 90, "y": 90}}})
        write_json(os.path.join(ASSETS, "models/item", sp + "_bark.json"), {"parent": f"wardogs:block/{sp}_bark"})
        loot = os.path.join(DATA, "wardogs/loot_table/blocks")
        write_json(os.path.join(loot, sp + "_bark.json"),
                   {"type": "minecraft:block", "pools": [{"rolls": 1, "entries": [{"type": "minecraft:item", "name": f"wardogs:{sp}_bark"}],
                                                          "conditions": [{"condition": "minecraft:survives_explosion"}]}]})
        write_json(os.path.join(loot, sp + "_foliage.json"),
                   {"type": "minecraft:block", "pools": [{"rolls": 1, "entries": [{"type": "minecraft:item", "name": f"wardogs:{sp}_foliage"}],
                                                          "conditions": [{"condition": "minecraft:any_of", "terms": [
                                                              {"condition": "minecraft:match_tool", "predicate": {"items": "minecraft:shears"}},
                                                              {"condition": "minecraft:match_tool", "predicate": {"predicates": {"minecraft:enchantments": [
                                                                  {"enchantments": "minecraft:silk_touch", "levels": {"min": 1}}]}}}]}]},
                                                         {"rolls": 1, "entries": [{"type": "minecraft:item", "name": "minecraft:stick",
                                                                                   "functions": [{"function": "minecraft:set_count", "count": {"type": "minecraft:uniform", "min": 1, "max": 2}}]}],
                                                          "conditions": [{"condition": "minecraft:random_chance", "chance": 0.05}]}]})
        lang[f"block.wardogs.{sp}_foliage"] = c["label"]
        lang[f"block.wardogs.{sp}_bark"] = c["log"]
        leaves.append(f"wardogs:{sp}_foliage")
        logs.append(f"wardogs:{sp}_bark")
    lp = os.path.join(ASSETS, "lang", "en_us.json")
    d = json.load(open(lp)); d.update(lang)
    json.dump(d, open(lp, "w"), indent=2, ensure_ascii=False)
    write_json(os.path.join(DATA, "minecraft/tags/block/leaves.json"), {"replace": False, "values": leaves})
    write_json(os.path.join(DATA, "minecraft/tags/block/logs.json"), {"replace": False, "values": logs})
    write_json(os.path.join(DATA, "minecraft/tags/block/mineable/axe.json"), {"replace": False, "values": logs})
    write_json(os.path.join(DATA, "minecraft/tags/block/mineable/hoe.json"), {"replace": False, "values": leaves})
    write_json(os.path.join(DATA, "minecraft/tags/item/leaves.json"), {"replace": False, "values": leaves})
    write_json(os.path.join(DATA, "minecraft/tags/item/logs.json"), {"replace": False, "values": logs})
    print(len(SPECIES), "species")


main()
