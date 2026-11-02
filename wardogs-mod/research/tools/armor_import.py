#!/usr/bin/env python3
"""Reads the Blockbench-style armour models of 'TacCraft' (1.12.2, MCreator) and rebuilds helmets and vests as textured OBJ meshes for the
worn-armour layer. Third-party assets: reads a copy you extracted yourself, writes into the git-ignored local-data/generated tree.

Each model class builds ModelRenderer bones (setRotationPoint, setRotationAngle, addChild) with ModelBox cubes; the replay below follows the
constructor with a small stack machine. The meshes stay in the models' own space (y down, 1/16 block units) so that the layer can draw them in
the frame of the player's head or body part exactly like vanilla draws a cube.

usage: armor_import.py <extracted TacCraft dir> [out dir = local-data/generated/assets/wardogs]
"""
import json, os, re, shutil, subprocess, sys
import numpy as np
from PIL import Image
import flan_import as fi
import geo_import as gi

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
PACK = sys.argv[1]
OUT = sys.argv[2] if len(sys.argv) > 2 else os.path.join(ROOT, "local-data/generated/assets/wardogs")
ITEM_DIR = os.path.join(PACK, "net/mcreator/arm/item")

# our armour id -> (model class file, texture file, which player part carries it)
ARMOR = {
    "helmet_l1": ("ItemOldArmyHelmet$Modelbucket", "bucketgreen", "head"),
    "helmet_l2": ("ItemLightHelmet$Modellvl2", "helmlvl2hd", "head"),
    "helmet_l3": ("ItemCombatHelmet$ModelTacHelm", "tachelm512", "head"),
    "helmet_l4": ("ItemHeavyHelmet$ModelHevHelm", "heavhelm", "head"),
    "vest_l1": ("ItemOldFlakVest$ModelFlak", "flaktext", "body"),
    "vest_l2": ("ItemLightPlateCarrier$Modellvl2torso", "torso", "body"),
    "vest_l3": ("ItemCombatVest$Modellvl3Torso", "lvl3tors", "body"),
    "vest_l4": ("ItemHeavyVest$ModelHevVest", "heavvest", "body"),
}


class Bone:
    def __init__(self):
        self.rp = np.zeros(3)
        self.rot = np.zeros(3)
        self.boxes = []      # (texU, texV, x, y, z, w, h, d, delta, mirror)
        self.children = []


def parse(path):
    text = subprocess.run(["javap", "-p", "-c", "-constants", path], capture_output=True, text=True).stdout
    m = re.search(r"public \S+\(\);\s+Code:(.*?)(?:\n\n|\Z)", text, re.S)
    code = m.group(1)
    bones, stack, tex = {}, [], [64, 32]
    for line in code.splitlines():
        mm = re.match(r"\s*\d+:\s+(\S+)\s*(.*)", line)
        if not mm:
            continue
        op, rest = mm.group(1), mm.group(2)
        cm = re.search(r"//\s*(.*)$", rest)
        com = cm.group(1) if cm else ""
        if op.startswith("aload"):
            stack.append(("this",))
        elif op.startswith("iconst_"):
            stack.append(-1 if op.endswith("m1") else int(op[-1]))
        elif op.startswith("fconst_"):
            stack.append(float(op[-1]))
        elif op in ("bipush", "sipush"):
            stack.append(int(rest.split()[0]))
        elif op in ("ldc", "ldc_w"):
            f = re.search(r"(?:float|int)\s+(-?[0-9.]+(?:E[+-]?[0-9]+)?)", com)
            stack.append(float(f.group(1)) if f else 0)
        elif op == "new":
            stack.append(("new",))
        elif op == "dup":
            stack.append(stack[-1])
        elif op == "getfield":
            f = re.search(r"Field (\S+?):(\S+)", com)
            stack.pop() if stack else None
            if f and f.group(2).startswith("Lnet/minecraft/client/model/ModelRenderer"):
                stack.append(("bone", bones.get(f.group(1))))
            elif f and f.group(1).endswith("field_78804_l"):
                stack.append(("cubes",))
            else:
                stack.append(0)
        elif op == "putfield":
            f = re.search(r"Field (\S+?):(\S+)", com)
            val = stack.pop(); tgt = stack.pop()
            name = f.group(1) if f else ""
            if name.endswith("field_78090_t"):
                tex[0] = int(val)
            elif name.endswith("field_78089_u"):
                tex[1] = int(val)
            elif isinstance(val, tuple) and val[0] == "newbone":
                bones[name] = val[1]
        elif op == "invokespecial":
            sig = re.search(r"Method (\S+?)\.\"<init>\":\((.*?)\)V", com)
            if sig and sig.group(1).endswith("ModelRenderer"):
                n = len(re.findall(r"\[?[BCDFIJSZ]|L[^;]*;", sig.group(2)))
                for _ in range(n + 1):
                    stack.pop()
                stack[-1] = ("newbone", Bone())
            elif sig and sig.group(1).endswith("ModelBox"):
                args = [stack.pop() for _ in range(11)][::-1]  # renderer, u, v, x, y, z, w, h, d, delta, mirror
                stack.pop()
                stack[-1] = ("box", tuple(args[1:]))
            else:
                n = len(re.findall(r"\[?[BCDFIJSZ]|L[^;]*;", sig.group(2))) if sig else 0
                for _ in range(n + 1):
                    stack.pop()
        elif op == "invokeinterface":
            if "List.add" in com:
                box = stack.pop(); cubes = stack.pop()
                bone = None
                # the owner bone is the one whose cubeList was pushed: tracked through the last getfield of a bone before the cubes
                stack.append(1)
                if isinstance(box, tuple) and box[0] == "box":
                    LAST_BONE[0].boxes.append(box[1])
        elif op == "invokevirtual":
            sig = re.search(r"Method (?:(\S+?)\.)?([^.:\s]+):\((.*?)\)(\S+)", com)
            if not sig:
                continue
            cls, mname, params, ret = sig.groups()
            n = len(re.findall(r"\[?[BCDFIJSZ]|\[?L[^;]*;", params))
            args = [stack.pop() for _ in range(n)][::-1]
            tgt = stack.pop()
            if mname == "func_78793_a" and isinstance(tgt, tuple) and tgt[0] == "bone":
                tgt[1].rp = np.array([float(a) for a in args])
            elif mname == "func_78792_a" and isinstance(tgt, tuple) and tgt[0] == "bone" and isinstance(args[0], tuple) and args[0][0] == "bone":
                tgt[1].children.append(args[0][1])
            elif mname == "setRotationAngle" and isinstance(args[0], tuple) and args[0][0] == "bone":
                args[0][1].rot = np.array([float(a) for a in args[1:]])
            if not ret.startswith("V"):
                stack.append(None)
        elif op == "pop":
            stack.pop() if stack else None
        # remember which bone owns the cubeList being filled
        if op == "getfield" and stack and isinstance(stack[-1], tuple) and stack[-1][0] == "bone":
            LAST_BONE[0] = stack[-1][1]
    return bones, tex


LAST_BONE = [None]


def roots(bones):
    kids = {id(c) for b in bones.values() for c in b.children}
    return [b for b in bones.values() if id(b) not in kids]


def build(bones, tex):
    parts = []

    def walk(b, R, t):
        Rb = fi.rot_z(b.rot[2]) @ fi.rot_y(b.rot[1]) @ fi.rot_x(b.rot[0])   # vanilla ModelRenderer: Z, then Y, then X
        Rt = R @ Rb
        tt = t + R @ b.rp
        for (u, v, x, y, z, w, h, d, delta, mirror) in b.boxes:
            p = fi.Part(int(u), int(v), tex[0], tex[1])
            p.boxes.append(("box", float(x), float(y), float(z), int(w), int(h), int(d), float(delta), None, None))
            p.R, p.t, p.mirror = Rt, tt, bool(mirror)
            parts.append(p)
        for c in b.children:
            walk(c, Rt, tt)

    for r in roots(bones):
        walk(r, np.eye(3), np.zeros(3))
    return fi.build_mesh(parts, units=1.0 / 16, flip=(1, 1, 1))


def main():
    os.makedirs(os.path.join(OUT, "models/armor"), exist_ok=True)
    os.makedirs(os.path.join(OUT, "textures/item/imp"), exist_ok=True)
    for aid, (cls, tex_name, slot) in ARMOR.items():
        path = os.path.join(ITEM_DIR, cls + ".class")
        bones, tex = parse(path)
        V, T, UV = build(bones, tex)
        ref = f"wardogs:item/imp/a_{tex_name}"
        shutil.copy(os.path.join(PACK, "assets/arm/textures", tex_name + ".png"), os.path.join(OUT, "textures/item/imp", f"a_{tex_name}.png"))
        # the item renderer centres a model on (0.5, 0.5, 0.5): the layer undoes it, so the mesh stays in model space
        gi.write_obj(os.path.join(OUT, "models/armor", aid + ".obj"), V, T, UV)
        json.dump({"loader": "neoforge:obj", "model": f"wardogs:models/armor/{aid}.obj", "flip_v": True, "automatic_culling": False, "shade_quads": True,
                   "render_type": "minecraft:cutout", "textures": {"base": ref, "particle": ref}},
                  open(os.path.join(OUT, "models/armor", aid + ".json"), "w"))
        lo, hi = V.min(0), V.max(0)
        print(aid, cls, len(T), "tris", "bbox", (lo * 16).round(1), (hi * 16).round(1))


if __name__ == "__main__":
    main()
