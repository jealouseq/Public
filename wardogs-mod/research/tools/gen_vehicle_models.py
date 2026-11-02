#!/usr/bin/env python3
"""Generate box-built Minecraft models for the WARDOGS vehicles (assets/wardogs/models/vehicle/<id>.json + a palette texture).

Each model is drawn in a 16x16x16 unit cube (x = width, y = height, z = length, FRONT at z = 0) and stretched by the renderer to the
vehicle's real width/height/length. The shapes are simple stand-ins (wheeled car, truck, tank, anti-aircraft tank, howitzer, helicopter),
all made of coloured boxes that sample one shared palette texture (textures/item/vehicle_palette.png).
"""
import json, os, sys
from PIL import Image

OUT = sys.argv[1]  # assets/wardogs

COLORS = {  # name -> rgb
    "dark": (38, 40, 44), "tyre": (24, 24, 26), "glass": (120, 170, 200), "steel": (96, 100, 106), "light": (230, 230, 220),
    "red": (190, 40, 40), "lamp": (240, 220, 120), "wood": (120, 90, 55),
    "green": (84, 110, 56), "olive": (92, 100, 62), "orange": (214, 120, 40), "brown": (110, 80, 52), "tan": (170, 140, 96),
    "gray": (118, 120, 124), "black": (40, 42, 46), "lblue": (96, 160, 210), "cyan": (40, 150, 160), "sand": (176, 150, 100),
}
palette = {}
def idx(name):
    if name not in palette:
        palette[name] = len(palette)
    return palette[name]

class M:
    def __init__(self):
        self.els = []
    def box(self, x0, y0, z0, x1, y1, z1, color):
        i = idx(color)
        u, v = (i % 16) + 0.25, (i // 16) + 0.25
        uv = [u, v, u + 0.5, v + 0.5]
        face = {"uv": uv, "texture": "#0"}
        self.els.append({"from": [x0, y0, z0], "to": [x1, y1, z1], "faces": {k: dict(face) for k in ("north", "south", "east", "west", "up", "down")}})
    def wheels(self, zs, r=2.2, x_in=0.0, color="tyre", y0=0.0):
        for z in zs:
            self.box(x_in, y0, z - r / 2, x_in + 1.6, y0 + r, z + r / 2, color)
            self.box(16 - x_in - 1.6, y0, z - r / 2, 16 - x_in, y0 + r, z + r / 2, color)
    def save(self, path):
        json.dump({"textures": {"0": "wardogs:item/vehicle_palette", "particle": "wardogs:item/vehicle_palette"}, "elements": self.els},
                  open(path, "w"), separators=(",", ":"))

def atv(c):
    m = M()
    m.wheels([3.5, 12.5], r=4.5, x_in=0.5, y0=0)
    m.box(4, 3, 1, 12, 7, 15, c)
    m.box(4.5, 7, 6, 11.5, 8.5, 12, "dark")        # seat
    m.box(2, 8, 2.5, 14, 9, 3.5, "steel")          # handlebars
    m.box(4, 5, 0, 12, 7, 1.2, "lamp")
    return m

def buggy(c):
    m = M()
    m.wheels([3, 13], r=4.5, x_in=0, y0=0)
    m.box(3, 3, 1, 13, 6, 15, c)
    for x in (3, 12.5):                                   # roll cage
        m.box(x, 6, 4, x + 0.6, 12, 4.6, "steel"); m.box(x, 6, 11, x + 0.6, 12, 11.6, "steel")
    m.box(3, 12, 4, 13, 12.6, 11.6, "steel")
    m.box(4.5, 6, 6, 11.5, 7.5, 10, "dark")
    return m

def suv(c, gun=None, pickup=False):
    m = M()
    m.wheels([3.2, 12.5], r=4.0, x_in=0)
    m.box(1, 3.5, 0, 15, 8, 16, c)                       # body
    m.box(1.4, 8, 5.5 if not pickup else 5, 14.6, 12.5, 12 if not pickup else 8.5, c)       # cabin
    m.box(1.2, 8.6, 5.6, 1.5, 11.8, 11.8, "glass"); m.box(14.5, 8.6, 5.6, 14.8, 11.8, 11.8, "glass")
    m.box(2, 8.6, 5.3 if not pickup else 4.8, 14, 11.8, 5.6 if not pickup else 5.1, "glass")  # windscreen
    m.box(1.2, 4.5, 0, 14.8, 6, 0.4, "lamp")
    m.box(1, 3.5, 0, 15, 4.3, 1, "dark")
    if pickup:
        m.box(1.4, 8, 9, 14.6, 9.4, 16, c)
    if gun:
        m.box(7, 12.5, 8, 9, 14, 10, "dark")
        m.box(7.5, 13.2, 3, 8.5, 14, 8, "dark")           # barrel pointing forward
    return m

def humvee(c, gun=None):
    m = M()
    m.wheels([3.2, 12.8], r=4.6, x_in=0)
    m.box(0.5, 4, 0, 15.5, 8.5, 16, c)
    m.box(1.5, 8.5, 5.5, 14.5, 12.5, 12.5, c)
    m.box(1.2, 9, 5.8, 1.6, 12, 12.2, "glass"); m.box(14.4, 9, 5.8, 14.8, 12, 12.2, "glass")
    m.box(2, 9, 5.2, 14, 12, 5.6, "glass")
    m.box(1, 5, 0, 15, 6.5, 0.4, "lamp")
    m.box(0.5, 4, 0, 15.5, 4.8, 1.2, "dark")
    if gun:
        m.box(6.5, 12.5, 8, 9.5, 15, 11, "dark")
        m.box(7.5, 13.4, 3, 8.5, 14.4, 8, "dark")
    return m

def truck(c, gun=None, covered=True):
    m = M()
    m.wheels([2.5, 9.5, 13.5], r=3.8, x_in=0)
    m.box(1.5, 3, 0, 14.5, 9, 5.5, c)                       # cab
    m.box(1.5, 9, 1.2, 14.5, 13, 5.5, c)
    m.box(2, 9.6, 0.8, 14, 12.6, 1.3, "glass")
    m.box(1.3, 9.6, 2, 1.6, 12.2, 5, "glass"); m.box(14.4, 9.6, 2, 14.7, 12.2, 5, "glass")
    m.box(1, 3, 5.5, 15, 5, 16, "dark")                    # chassis
    if covered:
        m.box(1.3, 5, 6, 14.7, 14, 16, "olive" if c != "olive" else "green")
    else:
        m.box(1.3, 5, 6, 14.7, 7.5, 16, c)
    if gun:
        m.box(6.5, 13, 3, 9.5, 15.5, 5.5, "dark"); m.box(7.5, 14, 0.5, 8.5, 15, 3, "dark")
    m.box(1.5, 4, 0, 14.5, 5.5, 0.5, "lamp")
    return m

def tank(c, big=True):
    m = M()
    m.box(0.5, 1, 0, 3.5, 5, 16, "dark"); m.box(12.5, 1, 0, 15.5, 5, 16, "dark")        # tracks
    for z in range(1, 16, 3):
        m.box(0.2, 0.2, z, 0.5, 2, z + 1.8, "steel"); m.box(15.5, 0.2, z, 15.8, 2, z + 1.8, "steel")
    m.box(3.5, 2.2, 0, 12.5, 5.5, 16, c)                    # hull
    m.box(3.5, 5.5, 0, 12.5, 6.4, 3, c)                     # glacis slope
    m.box(2.5, 5.5, 4.5, 13.5, 10.5, 12.5, c)               # turret
    m.box(3.5, 10.5, 6, 8, 12, 9.5, "dark")                 # commander hatch
    m.box(7.2, 6.8, 0, 8.8, 8.8, 4.5, "steel")             # gun barrel (points to the front)
    m.box(6.8, 6.6, 3.5, 9.2, 9.0, 5, "dark")              # mantlet
    return m

def gepard(c):
    m = M()
    m.box(0.5, 1, 0, 3.5, 5, 16, "dark"); m.box(12.5, 1, 0, 15.5, 5, 16, "dark")
    m.box(3.5, 2.2, 0, 12.5, 5.5, 16, c)
    m.box(2, 5.5, 4, 14, 11, 12.5, c)                       # big turret
    m.box(3.2, 6.5, 0.3, 4.4, 7.7, 4, "steel"); m.box(11.6, 6.5, 0.3, 12.8, 7.7, 4, "steel")   # twin 35mm guns
    m.box(3.2, 9.0, 0.3, 4.4, 10.2, 4, "steel"); m.box(11.6, 9.0, 0.3, 12.8, 10.2, 4, "steel")
    m.box(6, 11, 9, 10, 15, 9.8, "gray")                    # radar dish
    m.box(7.5, 11, 9.8, 8.5, 12, 11, "dark")
    return m

def sph(c):
    m = M()
    m.box(0.5, 1, 0, 3.5, 5, 16, "dark"); m.box(12.5, 1, 0, 15.5, 5, 16, "dark")
    m.box(3.5, 2.2, 0, 12.5, 5.5, 16, c)
    m.box(2, 5.5, 5, 14, 12, 15.5, c)                       # big rear turret
    m.box(7, 8.5, 0, 9, 10.5, 5.2, "steel")                 # long howitzer barrel
    m.box(6.2, 8, 3.8, 9.8, 11, 5.6, "dark")
    m.box(5, 12, 9, 11, 13, 13, "dark")
    return m

def heli(c, big=False, rockets=False):
    m = M()
    m.box(4.5, 5, 0, 11.5, 11, 9 if not big else 10, c)     # cabin
    m.box(5, 6, 0, 11, 9.5, 2.5, "glass")                   # nose glass
    m.box(4.2, 7, 2.5, 4.5, 10, 8, "glass"); m.box(11.5, 7, 2.5, 11.8, 10, 8, "glass")
    m.box(6.8, 6.5, 9, 9.2, 9.5, 16, c)                     # tail boom
    m.box(7.7, 9.5, 14.5, 8.3, 13.5, 16, c)                 # fin
    m.box(5.5, 8.8, 15, 10.5, 9.3, 16, c)                   # stabiliser
    m.box(7.2, 11, 3.5, 8.8, 12, 5.5, "dark")               # rotor mast
    m.box(0.5, 12, 4.2, 15.5, 12.4, 4.8, "dark")            # main rotor blades
    m.box(7.7, 11.8, 0.5, 8.3, 12.4, 8.5, "dark") if False else None
    m.box(7.8, 8.2, 15.7, 8.2, 10.2, 16.2, "dark")          # tail rotor
    for x in (4.5, 11):
        m.box(x, 1, 1.5, x + 0.5, 5, 2.2, "steel"); m.box(x, 1, 7, x + 0.5, 5, 7.7, "steel")
        m.box(x - 0.1, 0.6, 0, x + 0.6, 1.2, 10, "steel")    # skids
    if rockets:
        m.box(2.2, 5.5, 2.5, 4.5, 7.5, 7.5, "gray"); m.box(11.5, 5.5, 2.5, 13.8, 7.5, 7.5, "gray")
    return m

MODELS = {
    "bobcat": lambda: atv("green"), "dune_buggy": lambda: buggy("orange"),
    "kodiak": lambda: suv("brown"), "kodiak_m249": lambda: suv("brown", gun=True), "kodiak_pickup": lambda: suv("brown", pickup=True),
    "wolf_110": lambda: suv("olive"), "wolf_110_m2": lambda: suv("olive", gun=True),
    "humvee": lambda: humvee("tan"), "humvee_m249": lambda: humvee("tan", gun=True), "humvee_minigun": lambda: humvee("tan", gun=True),
    "ural": lambda: truck("gray"), "ural_defender": lambda: truck("olive", covered=False), "ural_defender_m249": lambda: truck("olive", gun=True, covered=False),
    "l2a6": lambda: tank("black"), "t72b3": lambda: tank("green"), "flakpanzer_gepard": lambda: gepard("gray"), "sph2": lambda: sph("brown"),
    "mh6": lambda: heli("lblue"), "ah6m": lambda: heli("red", rockets=False), "ah6r": lambda: heli("red", rockets=True),
    "z20_lakota": lambda: heli("cyan", big=True), "z20_lakota_miniguns": lambda: heli("cyan", big=True), "havoc": lambda: heli("black", rockets=True),
}

os.makedirs(os.path.join(OUT, "models/vehicle"), exist_ok=True)
os.makedirs(os.path.join(OUT, "textures/item"), exist_ok=True)
for vid, fn in MODELS.items():
    m = fn()
    m.save(os.path.join(OUT, "models/vehicle", vid + ".json"))
img = Image.new("RGBA", (16, 16), (0, 0, 0, 0))
for name, i in palette.items():
    img.putpixel((i % 16, i // 16), COLORS[name] + (255,))
img.save(os.path.join(OUT, "textures/item/vehicle_palette.png"))
print(len(MODELS), "vehicle models,", len(palette), "palette colours")
