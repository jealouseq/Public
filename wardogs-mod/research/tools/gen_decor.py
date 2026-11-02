#!/usr/bin/env python3
"""Generate the WARDOGS decor blocks (furniture, street furniture, camp gear) as box-built block models.

Writes into <assets/wardogs>: models/block/decor/<id>.json, blockstates/<id>.json (4 horizontal facings), models/item/<id>.json,
textures/block/decor_palette.png, and <data/wardogs>/decor/blocks.json (collision boxes, light level) read by the mod at startup.
A model is drawn in a 16-unit cube with its FRONT toward north (z = 0). Every box samples one colour cell of a shared palette.

usage: gen_decor.py <assets/wardogs> <data/wardogs>
"""
import json, os, sys
from PIL import Image

ASSETS, DATA = sys.argv[1], sys.argv[2]
COLORS = {
    "wood": (140, 100, 60), "darkwood": (90, 60, 40), "lightwood": (190, 150, 100), "white": (235, 235, 230), "grey": (130, 132, 136),
    "dgrey": (60, 62, 66), "black": (25, 25, 28), "steel": (170, 175, 180), "red": (170, 40, 40), "blue": (50, 90, 160),
    "green": (60, 110, 60), "olive": (85, 95, 55), "tan": (180, 160, 110), "khaki": (150, 140, 95), "yellow": (235, 200, 60),
    "orange": (220, 120, 40), "cream": (235, 220, 185), "brown": (100, 70, 50), "screen": (40, 70, 90), "glow": (255, 235, 160),
    "glass": (150, 190, 210), "fabric_red": (150, 60, 60), "fabric_blue": (70, 100, 140), "fabric_green": (90, 120, 80), "book_a": (150, 50, 40),
    "book_b": (50, 80, 130), "book_c": (60, 110, 70), "book_d": (190, 160, 60), "concrete": (150, 150, 145), "rust": (140, 70, 40),
    "pot": (150, 80, 50), "leaf": (60, 130, 60), "pink": (210, 130, 150), "sand": (200, 185, 140), "snow": (245, 248, 250),
}
PAL = list(COLORS)
CELLS = 8          # palette is 8 x 8 cells, 8 px each (64 x 64): big cells keep mipmaps from smearing neighbouring colours

def uv(c):
    i = PAL.index(c); cx, cy = i % CELLS, i // CELLS
    return [cx * 2 + 0.5, cy * 2 + 0.5, cx * 2 + 1.5, cy * 2 + 1.5]

class B:
    def __init__(self, name, light=0, solid=True, label=None):
        self.name, self.light, self.solid, self.label = name, light, solid, label or name.replace("_", " ").title()
        self.els = []; self.coll = []
    def box(self, x0, y0, z0, x1, y1, z1, c, emit=False, collide=True):
        face = {"uv": uv(c), "texture": "#0"}
        if emit:
            face["neoforge_data"] = {"block_light": 15, "sky_light": 15}
        self.els.append({"from": [x0, y0, z0], "to": [x1, y1, z1], "faces": {k: dict(face) for k in ("north", "south", "east", "west", "up", "down")}})
        if collide:
            self.coll.append([x0, y0, z0, x1, y1, z1])
        return self

BLOCKS = []
def blk(name, **kw):
    b = B(name, **kw); BLOCKS.append(b); return b

# ---- furniture ---------------------------------------------------------------------------------
def chair(name, seat):
    b = blk(name)
    for x, z in ((3, 3), (11, 3), (3, 11), (11, 11)):
        b.box(x, 0, z, x + 2, 6, z + 2, "darkwood")
    b.box(3, 6, 3, 13, 8, 13, seat); b.box(3, 8, 11, 13, 16, 13, "wood"); b.box(5, 10, 10.6, 11, 15, 11, seat, collide=False)
chair("chair", "wood"); chair("chair_cushion", "fabric_red")
b = blk("table")
b.box(0, 12, 0, 16, 14, 16, "lightwood")
for x, z in ((1, 1), (13, 1), (1, 13), (13, 13)):
    b.box(x, 0, z, x + 2, 12, z + 2, "wood")
b = blk("coffee_table")
b.box(1, 6, 3, 15, 8, 13, "lightwood")
for x, z in ((2, 4), (12, 4), (2, 10), (12, 10)):
    b.box(x, 0, z, x + 2, 6, z + 2, "darkwood")
def sofa(name, fab):
    b = blk(name)
    b.box(0, 2, 1, 16, 7, 15, fab); b.box(0, 7, 11, 16, 15, 15, fab); b.box(0, 7, 1, 3, 11, 11, fab); b.box(13, 7, 1, 16, 11, 11, fab)
    b.box(3, 7, 2, 13, 9, 11, fab, collide=False); b.box(0, 0, 1, 2, 2, 3, "darkwood"); b.box(14, 0, 1, 16, 2, 3, "darkwood")
    b.box(0, 0, 13, 2, 2, 15, "darkwood"); b.box(14, 0, 13, 16, 2, 15, "darkwood")
sofa("sofa_red", "fabric_red"); sofa("sofa_blue", "fabric_blue"); sofa("sofa_green", "fabric_green")
b = blk("armchair")
b.box(2, 2, 2, 14, 7, 14, "fabric_blue"); b.box(2, 7, 10, 14, 15, 14, "fabric_blue"); b.box(2, 7, 2, 4, 11, 10, "fabric_blue"); b.box(12, 7, 2, 14, 11, 10, "fabric_blue")
b.box(2, 0, 2, 4, 2, 4, "darkwood"); b.box(12, 0, 2, 14, 2, 4, "darkwood"); b.box(2, 0, 12, 4, 2, 14, "darkwood"); b.box(12, 0, 12, 14, 2, 14, "darkwood")
b = blk("wardrobe")
b.box(1, 0, 2, 15, 16, 15, "darkwood"); b.box(1.5, 1, 1.4, 7.7, 15, 2, "wood"); b.box(8.3, 1, 1.4, 14.5, 15, 2, "wood")
b.box(6.5, 7, 1, 7.3, 10, 1.4, "steel", collide=False); b.box(8.7, 7, 1, 9.5, 10, 1.4, "steel", collide=False)
b = blk("bookshelf_tall")
b.box(0, 0, 4, 16, 16, 16, "darkwood")
for i, (y, cols) in enumerate(((1, ("book_a", "book_b", "book_c", "book_d")), (6, ("book_d", "book_c", "book_a", "book_b")), (11, ("book_b", "book_a", "book_d", "book_c")))):
    for j, c in enumerate(cols):
        b.box(1 + j * 3.6, y, 3.4, 3.8 + j * 3.6, y + 4, 4, c, collide=False)
b = blk("dresser")
b.box(0, 0, 2, 16, 11, 15, "wood"); b.box(0.5, 1, 1.4, 15.5, 5, 2, "lightwood"); b.box(0.5, 6, 1.4, 15.5, 10, 2, "lightwood")
b.box(6, 2.5, 1, 10, 3.5, 1.4, "steel", collide=False); b.box(6, 7.5, 1, 10, 8.5, 1.4, "steel", collide=False)
b = blk("tv_set")
b.box(2, 0, 4, 14, 5, 12, "darkwood"); b.box(1, 5, 6, 15, 14, 9, "black"); b.box(2, 6, 5.6, 14, 13, 6, "screen", collide=False); b.box(6, 5, 7, 10, 6, 9, "dgrey")
b = blk("stove")
b.box(0, 0, 0, 16, 14, 16, "white"); b.box(1, 14, 1, 15, 15, 15, "dgrey"); b.box(3, 15, 3, 7, 15.6, 7, "black", collide=False); b.box(9, 15, 3, 13, 15.6, 7, "black", collide=False)
b.box(3, 15, 9, 7, 15.6, 13, "black", collide=False); b.box(9, 15, 9, 13, 15.6, 13, "black", collide=False); b.box(2, 2, -0.4, 14, 9, 0, "dgrey", collide=False); b.box(4, 10, -0.5, 12, 11, 0, "steel", collide=False)
b = blk("fridge")
b.box(2, 0, 2, 14, 16, 14, "white"); b.box(2, 10.6, 1.5, 14, 11, 2, "grey", collide=False); b.box(11.5, 12, 1, 12.5, 15, 1.6, "steel", collide=False); b.box(11.5, 3, 1, 12.5, 8, 1.6, "steel", collide=False)
b = blk("kitchen_counter")
b.box(0, 0, 0, 16, 14, 16, "cream"); b.box(0, 14, 0, 16, 16, 16, "dgrey"); b.box(0.5, 2, -0.4, 15.5, 12, 0, "lightwood", collide=False)
b = blk("sink")
b.box(0, 0, 0, 16, 14, 16, "cream"); b.box(0, 14, 0, 16, 16, 16, "dgrey"); b.box(3, 14.6, 4, 13, 16, 12, "steel"); b.box(7.4, 16, 12, 8.6, 20 if False else 16, 13, "steel"); b.box(7.4, 16, 12.5, 8.6, 16, 13, "steel", collide=False)
b = blk("lamp_table", light=13)
b.box(6, 0, 6, 10, 1.5, 10, "darkwood"); b.box(7.4, 1.5, 7.4, 8.6, 6, 8.6, "darkwood"); b.box(4, 6, 4, 12, 12, 12, "glow", emit=True)
b = blk("lamp_floor", light=14)
b.box(5, 0, 5, 11, 1, 11, "dgrey"); b.box(7.4, 1, 7.4, 8.6, 11, 8.6, "dgrey"); b.box(4.5, 11, 4.5, 11.5, 16, 11.5, "glow", emit=True)
b = blk("rug", solid=False)
b.box(0, 0, 0, 16, 0.6, 16, "fabric_red", collide=False); b.box(1.5, 0.6, 1.5, 14.5, 0.8, 14.5, "cream", collide=False); b.box(3, 0.8, 3, 13, 1, 13, "fabric_red", collide=False)
b = blk("potted_plant")
b.box(5, 0, 5, 11, 6, 11, "pot"); b.box(6, 6, 6, 10, 9, 10, "leaf", collide=False); b.box(4, 9, 4, 12, 14, 12, "leaf", collide=False)
b = blk("cot")
b.box(1, 2, 0, 15, 4, 16, "olive"); b.box(2, 4, 1, 14, 6, 15, "khaki"); b.box(3, 6, 1, 13, 7.5, 5, "white", collide=False)
for x, z in ((1, 0.5), (13, 0.5), (1, 13.5), (13, 13.5)):
    b.box(x, 0, z, x + 2, 2, z + 2, "dgrey")
b = blk("bed_bunk")
b.box(0, 0, 0, 2, 16, 2, "darkwood"); b.box(14, 0, 0, 16, 16, 2, "darkwood"); b.box(0, 0, 14, 2, 16, 16, "darkwood"); b.box(14, 0, 14, 16, 16, 16, "darkwood")
b.box(0, 3, 0, 16, 5, 16, "fabric_green"); b.box(0, 10, 0, 16, 12, 16, "fabric_green"); b.box(2, 5, 1, 14, 6, 6, "white", collide=False); b.box(2, 12, 1, 14, 13, 6, "white", collide=False)

# ---- street furniture ---------------------------------------------------------------------------
b = blk("street_pole")
b.box(6.5, 0, 6.5, 9.5, 1, 9.5, "dgrey"); b.box(7, 1, 7, 9, 16, 9, "grey")
b = blk("street_lamp", light=15)
b.box(7, 0, 7, 9, 8, 9, "grey"); b.box(7, 8, 3, 9, 10, 9, "grey"); b.box(5.5, 7, 0, 10.5, 8.2, 5, "dgrey"); b.box(6.2, 6, 0.6, 9.8, 7, 4.4, "glow", emit=True)
b = blk("bench_park")
b.box(0, 6, 3, 16, 8, 13, "lightwood"); b.box(0, 9, 11.4, 16, 15, 13, "lightwood"); b.box(0, 8, 11.4, 2, 9, 13, "dgrey", collide=False)
for x in (1, 13):
    b.box(x, 0, 4, x + 2, 6, 6, "dgrey"); b.box(x, 0, 10, x + 2, 6, 12, "dgrey"); b.box(x, 6, 3, x + 2, 15, 4.6, "dgrey", collide=False)
b = blk("picnic_table")
b.box(0, 9, 3, 16, 11, 13, "lightwood"); b.box(0, 5, 0, 16, 6.6, 3, "wood"); b.box(0, 5, 13, 16, 6.6, 16, "wood")
for x in (2, 12):
    b.box(x, 0, 2, x + 2, 9, 3.4, "darkwood"); b.box(x, 0, 12.6, x + 2, 9, 14, "darkwood")
b = blk("mailbox")
b.box(7.2, 0, 7.2, 8.8, 9, 8.8, "darkwood"); b.box(4, 9, 5, 12, 14, 11, "blue"); b.box(11.2, 12, 5.5, 12.4, 16, 6.4, "red", collide=False)
b = blk("trash_bin")
b.box(4, 0, 4, 12, 10, 12, "green"); b.box(3.4, 10, 3.4, 12.6, 11.4, 12.6, "dgrey")
b = blk("road_barrier")
b.box(0, 0, 5, 16, 3, 11, "concrete"); b.box(0, 3, 6, 16, 8, 10, "concrete"); b.box(0, 8, 6.6, 16, 11, 9.4, "white"); b.box(2, 8, 6, 5, 11, 10, "red", collide=False); b.box(8, 8, 6, 11, 11, 10, "red", collide=False)
b = blk("traffic_cone")
b.box(4, 0, 4, 12, 1.5, 12, "dgrey"); b.box(5.5, 1.5, 5.5, 10.5, 6, 10.5, "orange"); b.box(6.5, 6, 6.5, 9.5, 11, 9.5, "orange"); b.box(5.5, 4, 5.5, 10.5, 5, 10.5, "white", collide=False)
b = blk("road_sign")
b.box(7.2, 0, 7.2, 8.8, 15, 8.8, "grey"); b.box(2, 8, 6.4, 14, 16, 7.2, "yellow"); b.box(4, 10, 6, 12, 14, 6.4, "black", collide=False)
b = blk("firewood")
for i in range(4):
    b.box(0, 0, 4 * i, 16, 4, 4 * i + 3.8, "wood")
for i in range(3):
    b.box(0, 4, 4 * i + 2, 16, 8, 4 * i + 5.8, "darkwood")
b.box(2, 8, 4, 16, 11, 7.8, "wood", collide=False)
b = blk("hay_bale_round")
b.box(2, 0, 1, 14, 12, 15, "sand"); b.box(1, 1, 2, 15, 11, 14, "yellow", collide=False)
b = blk("beehive_box")
b.box(2, 0, 2, 14, 3, 14, "wood"); b.box(3, 3, 3, 13, 8, 13, "white"); b.box(3, 8, 3, 13, 12, 13, "yellow"); b.box(1.5, 12, 1.5, 14.5, 14, 14.5, "grey")
b = blk("water_trough")
b.box(0, 3, 3, 16, 8, 13, "wood"); b.box(1, 7, 4, 15, 7.9, 12, "glass", collide=False)
for x in (1, 13):
    b.box(x, 0, 4, x + 2, 3, 6, "darkwood"); b.box(x, 0, 10, x + 2, 3, 12, "darkwood")

# ---- camp / military -----------------------------------------------------------------------------
b = blk("sandbags")
b.box(0, 0, 1, 7.6, 4, 7, "tan"); b.box(8.4, 0, 1, 16, 4, 7, "tan"); b.box(0, 0, 9, 7.6, 4, 15, "tan"); b.box(8.4, 0, 9, 16, 4, 15, "tan")
b.box(2, 4, 2, 9, 8, 8, "sand"); b.box(9, 4, 2, 16, 8, 8, "tan"); b.box(0, 4, 9, 8, 8, 15, "tan"); b.box(8, 4, 9, 14, 8, 15, "sand")
b.box(2, 8, 3, 14, 12, 12, "sand")
b = blk("sandbag_wall")
b.box(0, 0, 3, 8, 5, 13, "tan"); b.box(8, 0, 3, 16, 5, 13, "sand"); b.box(0, 5, 4, 5, 10, 12, "sand"); b.box(5, 5, 4, 13, 10, 12, "tan"); b.box(11, 5, 4, 16, 10, 12, "sand")
b.box(0, 10, 5, 8, 15, 11, "tan"); b.box(8, 10, 5, 16, 15, 11, "sand")
b = blk("hesco_barrier")
b.box(0, 0, 2, 16, 16, 14, "khaki"); b.box(0, 0, 1.6, 16, 16, 2, "black", collide=False); b.box(0, 0, 14, 16, 16, 14.4, "black", collide=False)
for x in (0, 7.5, 15):
    b.box(x, 0, 1.4, x + 1, 16, 14.6, "dgrey", collide=False)
b = blk("ammo_crate")
b.box(2, 0, 3, 14, 8, 13, "olive"); b.box(1.5, 8, 2.5, 14.5, 9.5, 13.5, "olive"); b.box(5, 3, 2.6, 11, 6, 3, "yellow", collide=False); b.box(1.5, 8.5, 4, 14.5, 9.7, 5, "dgrey", collide=False)
b = blk("supply_crate")
b.box(1, 0, 1, 15, 14, 15, "wood")
for y in (0, 6.5, 12.5):
    b.box(0.6, y, 0.6, 15.4, y + 1.5, 15.4, "darkwood", collide=False)
b.box(6, 4, 0.4, 10, 9, 1, "white", collide=False)
b = blk("radio_table")
b.box(0, 11, 2, 16, 13, 14, "olive"); b.box(1, 0, 3, 3, 11, 5, "dgrey"); b.box(13, 0, 3, 15, 11, 5, "dgrey"); b.box(1, 0, 11, 3, 11, 13, "dgrey"); b.box(13, 0, 11, 15, 11, 13, "dgrey")
b.box(2, 13, 4, 10, 18 if False else 16, 11, "black"); b.box(3, 14, 3.6, 5, 15.5, 4, "green", collide=False); b.box(12, 13, 9, 13, 16, 10, "steel", collide=False)
b = blk("map_table")
b.box(0, 12, 0, 16, 14, 16, "wood"); b.box(1, 14, 1, 15, 14.4, 15, "khaki", collide=False); b.box(4, 14.4, 5, 7, 15, 9, "red", collide=False)
for x, z in ((1, 1), (13, 1), (1, 13), (13, 13)):
    b.box(x, 0, z, x + 2, 12, z + 2, "darkwood")
b = blk("generator")
b.box(1, 0, 3, 15, 9, 13, "olive"); b.box(1, 9, 4, 15, 11, 12, "dgrey"); b.box(11, 11, 5, 13, 16, 7, "black"); b.box(2, 3, 2.6, 6, 7, 3, "yellow", collide=False); b.box(0, 0, 2, 2, 4, 14, "black")
b = blk("oil_drum")
b.box(3, 0, 3, 13, 14, 13, "red"); b.box(2.6, 2, 2.6, 13.4, 3.4, 13.4, "dgrey", collide=False); b.box(2.6, 10, 2.6, 13.4, 11.4, 13.4, "dgrey", collide=False); b.box(6, 14, 6, 10, 15, 10, "steel")
b = blk("camp_stool")
b.box(3, 6, 3, 13, 8, 13, "olive")
for x, z in ((3, 3), (11, 3), (3, 11), (11, 11)):
    b.box(x, 0, z, x + 2, 6, z + 2, "dgrey")
b = blk("weapon_rack")
b.box(0, 0, 11, 16, 16, 14, "darkwood"); b.box(1, 10, 8, 15, 11, 11, "dgrey"); b.box(1, 5, 8, 15, 6, 11, "dgrey")
for x in (2, 6, 10):
    b.box(x, 6, 9, x + 1.5, 15, 10.5, "black", collide=False); b.box(x - 0.5, 4, 9, x + 2, 7, 10.5, "wood", collide=False)
b = blk("floodlight", light=15)
b.box(7, 0, 7, 9, 12, 9, "dgrey"); b.box(3, 12, 6, 13, 16, 10, "dgrey"); b.box(4, 12.6, 5.4, 12, 15.4, 6, "glow", emit=True)
b = blk("supply_pallet")
b.box(0, 0, 0, 16, 2, 16, "lightwood"); b.box(1, 2, 1, 15, 12, 15, "khaki"); b.box(1, 12, 1, 15, 13, 15, "olive")
b = blk("shipping_container_block")
b.box(0, 0, 0, 16, 16, 16, "red"); b.box(0, 0, 0, 1, 16, 1, "dgrey", collide=False)
for x in (3, 6, 9, 12):
    b.box(x, 1, -0.2, x + 1, 15, 0, "rust", collide=False)

def write():
    for sub in ("models/block/decor", "blockstates", "models/item", "textures/block"):
        os.makedirs(os.path.join(ASSETS, sub), exist_ok=True)
    os.makedirs(os.path.join(DATA, "decor"), exist_ok=True)
    info, lang = {}, {}
    for b in BLOCKS:
        name = "decor_" + b.name
        model = {"textures": {"0": "wardogs:block/decor_palette", "particle": "wardogs:block/decor_palette"}, "elements": b.els}
        json.dump(model, open(os.path.join(ASSETS, "models/block/decor", b.name + ".json"), "w"), separators=(",", ":"))
        variants = {f"facing={f}": {"model": f"wardogs:block/decor/{b.name}", "y": y} for f, y in (("north", 0), ("east", 90), ("south", 180), ("west", 270))}
        json.dump({"variants": variants}, open(os.path.join(ASSETS, "blockstates", name + ".json"), "w"))
        json.dump({"parent": f"wardogs:block/decor/{b.name}", "display": {
            "gui": {"rotation": [30, 225, 0], "scale": [0.62, 0.62, 0.62]}, "ground": {"translation": [0, 3, 0], "scale": [0.4, 0.4, 0.4]},
            "fixed": {"scale": [0.6, 0.6, 0.6]}, "thirdperson_righthand": {"scale": [0.4, 0.4, 0.4]},
            "firstperson_righthand": {"rotation": [0, 45, 0], "scale": [0.4, 0.4, 0.4]}}}, open(os.path.join(ASSETS, "models/item", name + ".json"), "w"))
        info[name] = {"light": b.light, "solid": b.solid, "boxes": b.coll or [[0, 0, 0, 16, 1, 16]]}
        lang["block.wardogs." + name] = b.label
    json.dump(info, open(os.path.join(DATA, "decor", "blocks.json"), "w"), indent=1)
    lp = os.path.join(ASSETS, "lang", "en_us.json")
    d = json.load(open(lp)); d.update(lang)
    json.dump(d, open(lp, "w"), indent=2, ensure_ascii=False)
    img = Image.new("RGBA", (CELLS * 8, CELLS * 8), (0, 0, 0, 0))
    for i, c in enumerate(PAL):
        cx, cy = i % CELLS, i // CELLS
        for x in range(8):
            for y in range(8):
                img.putpixel((cx * 8 + x, cy * 8 + y), COLORS[c] + (255,))
    img.save(os.path.join(ASSETS, "textures/block/decor_palette.png"))
    print(len(BLOCKS), "decor blocks;", len(PAL), "colours")
write()
