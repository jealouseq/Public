#!/usr/bin/env python3
"""Box-built item models for grenades, mines, charges, signals and armour (assets/wardogs/models/item/<id>.json).

Uses one palette texture (textures/item/gear_palette.png). Models sit in the 16-unit cube; shapes are simple stand-ins.
"""
import json, os, sys
from PIL import Image

OUT = sys.argv[1]
COLORS = {"olive": (84, 96, 56), "dark": (36, 38, 42), "steel": (150, 154, 160), "tan": (176, 150, 100), "red": (190, 40, 40),
          "black": (30, 30, 32), "white": (232, 232, 226), "gray": (112, 116, 120), "green": (60, 110, 60), "yellow": (230, 190, 50),
          "orange": (220, 120, 40), "blue": (60, 110, 200), "brown": (110, 80, 52), "glass": (120, 170, 200), "wood": (130, 96, 60),
          "khaki": (150, 140, 90), "navy": (40, 50, 80), "visor": (60, 80, 100)}
palette = {}
def idx(n):
    palette.setdefault(n, len(palette)); return palette[n]

class M:
    def __init__(self): self.els = []
    def box(self, x0, y0, z0, x1, y1, z1, color):
        i = idx(color); u, v = (i % 16) + 0.25, (i // 16) + 0.25
        f = {"uv": [u, v, u + 0.5, v + 0.5], "texture": "#0"}
        self.els.append({"from": [x0, y0, z0], "to": [x1, y1, z1], "faces": {k: dict(f) for k in ("north", "south", "east", "west", "up", "down")}})
    def save(self, name, display):
        json.dump({"textures": {"0": "wardogs:item/gear_palette", "particle": "wardogs:item/gear_palette"}, "elements": self.els, "display": display, "gui_light": "front"},
                  open(os.path.join(OUT, "models/item", name + ".json"), "w"), separators=(",", ":"))

def disp(scale=0.6, gui=(30, 225, 0), gscale=0.75):
    return {"gui": {"rotation": list(gui), "translation": [0, 0, 0], "scale": [gscale] * 3},
            "firstperson_righthand": {"rotation": [0, 0, 0], "translation": [2, 1, 2], "scale": [scale] * 3},
            "thirdperson_righthand": {"rotation": [0, 0, 0], "translation": [0, 2, 0], "scale": [scale] * 3},
            "ground": {"translation": [0, 3, 0], "scale": [0.5] * 3}, "fixed": {"rotation": [0, 180, 0], "scale": [0.8] * 3}}

def frag(c="olive"):
    m = M(); m.box(5, 2, 5, 11, 9, 11, c); m.box(6, 9, 6, 10, 11, 10, "steel"); m.box(4.5, 3, 4.5, 11.5, 4, 11.5, "dark"); m.box(4.5, 6, 4.5, 11.5, 7, 11.5, "dark")
    m.box(10, 10, 7, 11, 14, 9, "steel"); m.box(6, 11, 7, 7.5, 12.5, 9, "yellow"); return m
def canister(c, cap="steel"):
    m = M(); m.box(5, 1, 5, 11, 11, 11, c); m.box(5.5, 11, 5.5, 10.5, 13, 10.5, cap); m.box(7, 13, 7, 9, 14.5, 9, "dark"); return m
def molotov():
    m = M(); m.box(5, 1, 5, 11, 9, 11, "glass"); m.box(5.5, 1.5, 5.5, 10.5, 6, 10.5, "orange"); m.box(7, 9, 7, 9, 13, 9, "glass"); m.box(7.2, 13, 7.2, 8.8, 15, 8.8, "white"); return m
def c4():
    m = M(); m.box(2, 3, 5, 14, 6.5, 11, "tan"); m.box(3, 6.5, 6.5, 6, 8.5, 9.5, "black"); m.box(10, 6.5, 6.5, 12, 7.5, 9.5, "red"); m.box(6, 6.5, 7.7, 10, 7, 8.3, "dark"); return m
def claymore():
    m = M(); m.box(2, 4, 7, 14, 11, 9, "olive"); m.box(3, 5, 6.5, 13, 10, 7, "gray"); m.box(4, 2, 7.2, 5, 4, 7.8, "dark"); m.box(11, 2, 7.2, 12, 4, 7.8, "dark"); m.box(7.2, 11, 7.5, 8.8, 12.5, 8.5, "black"); return m
def mine():
    m = M(); m.box(3, 1, 3, 13, 4, 13, "olive"); m.box(5, 4, 5, 11, 5, 11, "dark"); m.box(7, 5, 7, 9, 6.2, 9, "steel"); m.box(3.5, 1.5, 3.5, 12.5, 2.5, 12.5, "dark"); return m
def ied():
    m = M(); m.box(5, 2, 5, 11, 9, 11, "gray"); m.box(5, 9, 5, 11, 10, 11, "steel"); m.box(7, 10, 7, 9, 12, 9, "black"); m.box(11, 4, 7, 14, 5, 8, "red"); m.box(11, 6, 8, 14, 7, 9, "blue"); return m
def detonator():
    m = M(); m.box(4, 2, 3, 12, 6, 13, "black"); m.box(5, 6, 4, 7, 8, 6, "red"); m.box(9, 6, 4, 11, 7, 6, "green"); m.box(10.5, 6, 11, 11.5, 15, 12, "steel"); m.box(5, 6, 9, 8, 7, 12, "gray"); return m
def flare(c):
    m = M(); m.box(6.5, 1, 6.5, 9.5, 12, 9.5, c); m.box(6, 12, 6, 10, 13.5, 10, "dark"); m.box(7.2, 13.5, 7.2, 8.8, 15, 8.8, "white"); return m

def helmet(c, visor=False, heavy=False):
    m = M()
    m.box(3, 5, 3, 13, 11, 13, c); m.box(4, 11, 4, 12, 12.5, 12, c); m.box(2, 4, 3, 14, 5.2, 13, c)   # shell, crown, brim
    m.box(3, 2, 3, 13, 5, 4, c); m.box(3, 2, 12, 13, 5, 13, c); m.box(3, 2, 3, 4, 5, 13, c); m.box(12, 2, 3, 13, 5, 13, c)   # neck/ear guard
    m.box(4, 6, 2.2, 12, 9, 3, "visor" if visor else "dark")
    if heavy: m.box(6, 12.5, 6, 10, 14, 10, "steel")
    return m
def vest(c, plates):
    m = M()
    m.box(3, 2, 5, 13, 12, 7, c); m.box(3, 2, 9, 13, 12, 11, c); m.box(3, 12, 5, 5.5, 14, 11, c); m.box(10.5, 12, 5, 13, 14, 11, c)
    m.box(3, 2, 7, 4, 12, 9, c); m.box(12, 2, 7, 13, 12, 9, c)
    if plates:
        m.box(4.5, 4, 4.4, 11.5, 11, 5, "steel"); m.box(4.5, 4, 11, 11.5, 11, 11.6, "steel")
    m.box(4, 3, 4.2, 6, 5.5, 5, "dark"); m.box(10, 3, 4.2, 12, 5.5, 5, "dark")
    return m

os.makedirs(os.path.join(OUT, "models/item"), exist_ok=True)
D = disp()
items = {"m67_frag": frag(), "molotov": molotov(), "c4": c4(), "ied": ied(), "remote_detonator": detonator(), "at_mine": mine(), "claymore": claymore(),
         "smoke_white": canister("white"), "smoke_black": canister("black"),
         "signal_alert": flare("red"), "signal_damaged_vehicle": flare("orange"), "signal_friendly": flare("green"), "signal_hostile": flare("red"),
         "signal_landing_zone": flare("yellow"), "signal_supply_request": flare("blue"),
         "helmet_l1": helmet("olive"), "helmet_l2": helmet("khaki"), "helmet_l3": helmet("dark", visor=True), "helmet_l4": helmet("navy", visor=True, heavy=True),
         "vest_l1": vest("olive", False), "vest_l2": vest("khaki", False), "vest_l3": vest("dark", True), "vest_l4": vest("navy", True)}
for n, m in items.items():
    m.save(n, D)
img = Image.new("RGBA", (16, 16), (0, 0, 0, 0))
for n, i in palette.items():
    img.putpixel((i % 16, i // 16), COLORS[n] + (255,))
os.makedirs(os.path.join(OUT, "textures/item"), exist_ok=True)
img.save(os.path.join(OUT, "textures/item/gear_palette.png"))
print(len(items), "models;", len(palette), "colours")
