#!/usr/bin/env python3
"""Reads Flan's Mod (1.12.2) model classes - Techne / TMT models compiled into Java - and rebuilds them as textured triangle meshes.

Third-party assets: this only reads a copy of a content pack that you extracted yourself; its output belongs in the git-ignored
local-data/generated tree, never in git. javap (JDK) is used to disassemble the classes; no model code is run.

A small stack machine replays the constructor: new ModelRendererTurbo(this, u, v, texW, texH) creates a part, addBox / addShapeBox /
addTrapezoid add boxes to it, setRotationPoint and the rotateAngle fields place it. Boxes use the vanilla ModelBox UV layout.
"""
import math
import re
import subprocess
import sys

import numpy as np

NUM = r"-?[0-9.]+(?:E[+-]?[0-9]+)?"


def disassemble(class_path):
    out = subprocess.run(["javap", "-p", "-c", "-constants", class_path], capture_output=True, text=True).stdout
    return out


class Part:
    def __init__(self, u, v, tw, th):
        self.u, self.v, self.tw, self.th = u, v, tw, th
        self.boxes = []          # (kind, x, y, z, w, h, d, scale, offsets[8][3] or None, extra)
        self.rp = [0.0, 0.0, 0.0]
        self.rot = [0.0, 0.0, 0.0]   # rotateAngleX, Y, Z in radians
        self.tex_flip = False


def parse(class_path):
    """-> (class name, {array field name: [Part or None, ...]}, {'textureX': n, 'textureY': n})"""
    text = disassemble(class_path)
    arrays = {}
    fields = {}
    name = re.search(r"class (\S+)", text)
    name = name.group(1) if name else class_path
    # only the constructor (first Code block) builds the model
    m = re.search(r"public \S+\(\);\s+Code:(.*?)(?:\n\n|\Z)", text, re.S)
    code = m.group(1) if m else text
    stack = []

    def pop():
        return stack.pop() if stack else None

    for line in code.splitlines():
        mm = re.match(r"\s*\d+:\s+(\S+)\s*(.*)", line)
        if not mm:
            continue
        op, rest = mm.group(1), mm.group(2)
        cm = re.search(r"//\s*(.*)$", rest)
        comment = cm.group(1) if cm else ""
        if op in ("aload_0", "aload_1", "aload_2", "aload_3", "aload"):
            stack.append(("this",))
        elif op.startswith("iconst_"):
            stack.append(-1 if op.endswith("m1") else int(op[-1]))
        elif op.startswith("fconst_"):
            stack.append(float(op[-1]))
        elif op in ("bipush", "sipush"):
            stack.append(int(rest.split()[0]))
        elif op in ("ldc", "ldc_w", "ldc2_w"):
            fm = re.search(r"(?:float|int|double)\s+(" + NUM + r")", comment)
            if fm:
                stack.append(float(fm.group(1)) if "float" in comment or "double" in comment else int(float(fm.group(1))))
            else:
                stack.append(0)
        elif op == "getfield":
            fm = re.search(r"Field (\S+?):(\S+)", comment)
            if not fm:
                pop(); stack.append(None); continue
            fname, ftype = fm.group(1), fm.group(2)
            pop()
            if ftype.startswith("[L"):
                stack.append(("array", fname))
            elif fname in fields:
                stack.append(fields[fname])
            else:
                stack.append(0)
        elif op == "putfield":
            fm = re.search(r"Field (\S+?):(\S+)", comment)
            val = pop()
            tgt = pop()
            fname = fm.group(1) if fm else ""
            short = fname.split(".")[-1]
            if isinstance(tgt, tuple) and tgt[0] == "part":
                part = tgt[1]
                if short in ("field_78795_f", "rotateAngleX"):
                    part.rot[0] = float(val)
                elif short in ("field_78796_g", "rotateAngleY"):
                    part.rot[1] = float(val)
                elif short in ("field_78808_h", "rotateAngleZ"):
                    part.rot[2] = float(val)
            elif ftype_is_array(fm):
                if isinstance(val, tuple) and val[0] == "newarray":
                    arrays[short] = val[1]
            else:
                fields[short] = val
        elif op == "anewarray":
            n = pop()
            stack.append(("newarray", [None] * int(n)))
        elif op == "new":
            stack.append(("newobj",))
        elif op == "dup":
            stack.append(stack[-1] if stack else None)
        elif op == "aaload":
            idx = pop()
            arr = pop()
            if isinstance(arr, tuple) and arr[0] == "array":
                lst = arrays.get(arr[1])
                if lst is not None and 0 <= idx < len(lst):
                    stack.append(("part", lst[idx], arr[1], idx))
                else:
                    stack.append(None)
            else:
                stack.append(None)
        elif op == "aastore":
            val = pop(); idx = pop(); arr = pop()
            if isinstance(arr, tuple) and arr[0] == "newarray" and isinstance(val, tuple) and val[0] in ("part", "vec"):
                arr[1][idx] = val[1]
            elif isinstance(arr, tuple) and arr[0] == "array" and isinstance(val, tuple) and val[0] in ("part", "vec"):
                arrays.setdefault(arr[1], [None] * (idx + 1))
                lst = arrays[arr[1]]
                while len(lst) <= idx:
                    lst.append(None)
                lst[idx] = val[1]
        elif op == "invokespecial":
            sig = re.search(r"Method (\S+?)\.\"<init>\":\((.*?)\)V", comment)
            if sig and "ModelRendererTurbo" in sig.group(1):
                args = [pop() for _ in range(5)][::-1]  # this, u, v, tw, th
                obj = pop()  # the dup'd reference
                part = Part(int(args[1]), int(args[2]), int(args[3]), int(args[4]))
                # the reference below is the original 'new' placeholder: replace it by the part
                if stack and isinstance(stack[-1], tuple) and stack[-1][0] == "newobj":
                    stack[-1] = ("part", part, None, None)
                else:
                    stack.append(("part", part, None, None))
            elif sig and "Vector3f" in sig.group(1) and sig.group(2) == "FFF":
                args = [pop() for _ in range(3)][::-1]
                pop()
                if stack and isinstance(stack[-1], tuple) and stack[-1][0] == "newobj":
                    stack[-1] = ("vec", tuple(float(a) for a in args))
                else:
                    stack.append(("vec", tuple(float(a) for a in args)))
            else:
                n = len(re.findall(r"\[?[BCDFIJSZ]|L[^;]*;", sig.group(2))) if sig else 0
                for _ in range(n + 1):
                    pop()
        elif op == "invokevirtual":
            sig = re.search(r"Method (\S+?)\.(\S+?):\((.*?)\)(\S+)", comment)
            if not sig:
                continue
            cls, mname, params, ret = sig.groups()
            nparams = len(re.findall(r"\[?[BCDFIJSZ]|\[?L[^;]*;", params))
            args = [pop() for _ in range(nparams)][::-1]
            tgt = pop()
            if "ModelRendererTurbo" in cls and isinstance(tgt, tuple) and tgt[0] == "part":
                p = tgt[1]
                if mname == "addShapeBox":
                    offs = np.array(args[7:31], float).reshape(8, 3)
                    p.boxes.append(("box", *map(float, args[0:3]), *map(int, args[3:6]), float(args[6]), offs, None))
                elif mname in ("func_78790_a",):
                    p.boxes.append(("box", *map(float, args[0:3]), *map(int, args[3:6]), float(args[6]), None, None))
                elif mname == "addBox" and params.startswith("FFFIII"):
                    p.boxes.append(("box", *map(float, args[0:3]), *map(int, args[3:6]), 0.0, None, None))
                elif mname == "addTrapezoid":
                    p.boxes.append(("trap", *map(float, args[0:3]), *map(int, args[3:6]), float(args[6]), None, (float(args[7]), int(args[8]))))
                elif mname == "func_78793_a":
                    p.rp = [float(a) for a in args]
                if ret.startswith("L") and mname == "addBox":
                    stack.append(tgt)
            else:
                if not ret.startswith("V"):
                    stack.append(None)
        elif op in ("pop",):
            pop()
    return name, arrays, fields


def ftype_is_array(fm):
    return bool(fm) and fm.group(2).startswith("[L")


# ---------------------------------------------------------------------------------------------------------------------------------

# The 24 offsets of addShapeBox come in this corner order (found by testing every order on all models of two packs: only this one gives
# no self-intersecting face): the four corners of the y-min face, then the four of the y-max face, each as (x0,z0) (x1,z0) (x1,z1) (x0,z1).
OFFSET_CORNERS = [(0, 0, 0), (1, 0, 0), (1, 0, 1), (0, 0, 1), (0, 1, 0), (1, 1, 0), (1, 1, 1), (0, 1, 1)]
# corner numbering used by build_mesh: 0 (x,y,z) 1 (X,y,z) 2 (X,Y,z) 3 (x,Y,z) 4 (x,y,Z) 5 (X,y,Z) 6 (X,Y,Z) 7 (x,Y,Z)
CORNER_BITS = [(0, 0, 0), (1, 0, 0), (1, 1, 0), (0, 1, 0), (0, 0, 1), (1, 0, 1), (1, 1, 1), (0, 1, 1)]


def box_corners(kind, x, y, z, w, h, d, scale, offs, extra):
    lo = np.array([x - scale, y - scale, z - scale], float)
    hi = np.array([x + w + scale, y + h + scale, z + d + scale], float)
    off = {}
    if offs is not None:
        for i, bits in enumerate(OFFSET_CORNERS):
            off[bits] = offs[i]
    c = np.zeros((8, 3))
    for i, bits in enumerate(CORNER_BITS):
        for a in range(3):
            o = off.get(bits, (0, 0, 0))[a]
            c[i, a] = hi[a] + o if bits[a] else lo[a] - o
    if kind == "trap" and extra is not None:
        # a trapezoid: one pair of faces shrinks toward the other (approximated by pulling the far face in on both axes)
        scale_b, direction = extra
        # direction 4/5 = top/bottom, 2/3 = front/back, 0/1 = right/left in TMT; the narrowing is applied to the matching face
        axis_pairs = {0: (0, [1, 2, 5, 6][::1]), 1: (0, [0, 3, 4, 7]), 2: (2, [4, 5, 6, 7]), 3: (2, [0, 1, 2, 3]), 4: (1, [3, 2, 6, 7]), 5: (1, [0, 1, 4, 5])}
        ax, face = axis_pairs.get(direction, (1, [0, 1, 4, 5]))
        centre = c.mean(0)
        for i in face:
            for a in range(3):
                if a != ax:
                    c[i, a] = centre[a] + (c[i, a] - centre[a]) * max(0.0, 1 - 2 * scale_b / max(1e-6, abs(hi[a] - lo[a])))
    return c


def rot_y(a):
    c, s = math.cos(a), math.sin(a)
    return np.array([[c, 0, s], [0, 1, 0], [-s, 0, c]])


def rot_z(a):
    c, s = math.cos(a), math.sin(a)
    return np.array([[c, -s, 0], [s, c, 0], [0, 0, 1]])


def rot_x(a):
    c, s = math.cos(a), math.sin(a)
    return np.array([[1, 0, 0], [0, c, -s], [0, s, c]])


def build_mesh(parts, units=1.0 / 16, flip=(-1, -1, 1)):
    """parts: list of Part. -> vertices (n,3) in blocks, triangles (k,3), uv (n,2) in 0..1 (v down), normals via winding.
    Corner numbering follows TMT: 0 (x,y,z) 1 (x+w,y,z) 2 (x+w,y+h,z) 3 (x,y+h,z) 4..7 the same at z+d."""
    V, T, UV = [], [], []
    for p in parts:
        if not isinstance(p, Part):
            continue
        R = getattr(p, "R", None)
        if R is None:
            R = rot_y(p.rot[1]) @ rot_z(p.rot[2]) @ rot_x(p.rot[0])
        t = getattr(p, "t", None)
        t = np.array(p.rp if t is None else t, float)
        for kind, x, y, z, w, h, d, scale, offs, extra in p.boxes:
            if w <= 0 or h <= 0 or d <= 0:
                continue
            c = box_corners(kind, x, y, z, w, h, d, scale, offs, extra)
            if getattr(p, "mirror", False):  # vanilla ModelBox mirror: min and max x swap places (the textures of the two sides swap with them)
                c[:, 0] = 2 * (x + w / 2.0) - c[:, 0]
            c = (R @ c.T).T + t
            c = c * np.array(flip) * units
            u, v, tw, th = p.u, p.v, p.tw, p.th
            # vanilla ModelBox quads: vertex order and texture rectangle (u1, v1, u2, v2) in pixels
            quads = [
                ((5, 1, 2, 6), (u + d + w, v + d, u + d + w + d, v + d + h)),          # +x
                ((0, 4, 7, 3), (u, v + d, u + d, v + d + h)),                           # -x
                ((5, 4, 0, 1), (u + d, v, u + d + w, v + d)),                           # y min
                ((2, 3, 7, 6), (u + d + w, v + d, u + d + w + w, v)),                   # y max
                ((1, 0, 3, 2), (u + d, v + d, u + d + w, v + d + h)),                   # z min
                ((4, 5, 6, 7), (u + d + w + d, v + d, u + d + w + d + w, v + d + h)),   # z max
            ]
            centre = c.mean(0) * np.array(flip) * units if False else (c.mean(0))
            for idx, (u1, v1, u2, v2) in quads:
                base = len(V)
                V.extend(c[list(idx)].tolist())
                # TexturedQuad: vertex 0 -> (u2, v1), 1 -> (u1, v1), 2 -> (u1, v2), 3 -> (u2, v2)
                UV.extend([(u2 / tw, v1 / th), (u1 / tw, v1 / th), (u1 / tw, v2 / th), (u2 / tw, v2 / th)])
                P = np.array(V[base:base + 4])
                n = np.cross(P[1] - P[0], P[2] - P[0])
                fc = P.mean(0)
                if n @ (fc - centre) < 0:   # make every face wind counter-clockwise seen from outside
                    V[base:base + 4] = V[base:base + 4][::-1]
                    UV[base:base + 4] = UV[base:base + 4][::-1]
                T.append((base, base + 1, base + 2))
                T.append((base, base + 2, base + 3))
    return np.array(V), np.array(T), np.array(UV)


def parts_of(arrays, names):
    out = []
    for n in names:
        out.extend(p for p in arrays.get(n, []) if isinstance(p, Part))
    return out


if __name__ == "__main__":
    name, arrays, fields = parse(sys.argv[1])
    print(name, {k: len(v) for k, v in arrays.items()}, fields)
