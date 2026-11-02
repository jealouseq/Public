#!/usr/bin/env python3
"""Converts Bedrock / GeckoLib geometry (*.geo.json, format 1.12.0) to a textured OBJ for NeoForge's neoforge:obj loader.

Third-party assets: run this on files you are allowed to use; the output belongs in the git-ignored local-data/generated tree, not in git.

  geo_import.py <model.geo.json> <texture.png> <out.obj> [--skip substr,...] [--only substr,...] [--preview out.png]

Conventions: the geometry's forward is -z and +y is up; the output has the muzzle toward +x (the game's gun item convention), the model
is centred on x and z and rests on y = 0, in model units / 16 = blocks. Texture coordinates follow the cube UV unfolding (box UV or per-face UV).
"""
import json, sys, math, os
import numpy as np
from PIL import Image

SIGN = -1  # Bedrock rotates the other way round in this coordinate frame (checked on curved magazines, which fall apart otherwise)
SKIP_DEFAULT = ("arm", "camera", "left_", "right_", "la", "ra", "ls", "rs", "ras", "las", "lss", "rss")


def rot_matrix(rx, ry, rz):
    """Bedrock bone rotation (degrees): X first, then Y, then Z (matrix Rz.Ry.Rx)."""
    a, b, c = (math.radians(SIGN * q) for q in (rx, ry, rz))
    ca, sa, cb, sb, cc, sc = math.cos(a), math.sin(a), math.cos(b), math.sin(b), math.cos(c), math.sin(c)
    Rx = np.array([[1, 0, 0], [0, ca, -sa], [0, sa, ca]])
    Ry = np.array([[cb, 0, sb], [0, 1, 0], [-sb, 0, cb]])
    Rz = np.array([[cc, -sc, 0], [sc, cc, 0], [0, 0, 1]])
    return Rz @ Ry @ Rx


def box_faces(o, s, inflate):
    """The 6 faces of a cube as (name, 4 corner positions counter-clockwise seen from outside); x is mirrored later with the whole model."""
    x0, y0, z0 = o[0] - inflate, o[1] - inflate, o[2] - inflate
    x1, y1, z1 = o[0] + s[0] + inflate, o[1] + s[1] + inflate, o[2] + s[2] + inflate
    return {
        "north": [(x1, y0, z0), (x0, y0, z0), (x0, y1, z0), (x1, y1, z0)],   # -z
        "south": [(x0, y0, z1), (x1, y0, z1), (x1, y1, z1), (x0, y1, z1)],   # +z
        "east": [(x1, y0, z1), (x1, y0, z0), (x1, y1, z0), (x1, y1, z1)],    # +x
        "west": [(x0, y0, z0), (x0, y0, z1), (x0, y1, z1), (x0, y1, z0)],    # -x
        "up": [(x0, y1, z1), (x1, y1, z1), (x1, y1, z0), (x0, y1, z0)],
        "down": [(x0, y0, z0), (x1, y0, z0), (x1, y0, z1), (x0, y0, z1)],
    }


def face_uvs(cube, tw, th):
    """UV rectangles (u, v, w, h) in pixels for every face of a cube."""
    s = cube["size"]
    uv = cube.get("uv")
    if isinstance(uv, dict):
        out = {}
        for name, f in uv.items():
            out[name] = (f["uv"][0], f["uv"][1], f.get("uv_size", [0, 0])[0], f.get("uv_size", [0, 0])[1])
        return out
    u, v = uv
    sx, sy, sz = s
    return {
        "north": (u + sz, v + sz, sx, sy), "east": (u, v + sz, sz, sy), "south": (u + sz + sx + sz, v + sz, sx, sy),
        "west": (u + sz + sx, v + sz, sz, sy), "up": (u + sz, v, sx, sz), "down": (u + sz + sx, v, sx, sz),
    }


def convert(geo_path, tex_size, skip=SKIP_DEFAULT, only=None, raw=False, under=None):
    d = json.load(open(geo_path))
    g = d["minecraft:geometry"][0]
    tw = g["description"].get("texture_width", tex_size[0])
    th = g["description"].get("texture_height", tex_size[1])
    bones = {b["name"]: b for b in g["bones"]}

    def chain(name):
        out = []
        while name:
            out.append(bones[name])
            name = bones[name].get("parent")
        return out[::-1]

    def skipped(name):
        n = name.lower()
        if under:   # keep only the bones that sit below (or are) a bone whose name contains one of these
            return False
        if only:
            return not any(o in n for o in only)
        return any(n == k or n.startswith(k) for k in skip)

    V, T, UV = [], [], []
    for name, b in bones.items():
        if not b.get("cubes") or any(skipped(x["name"]) for x in chain(name)):
            continue
        if under and not any(any(u in x["name"].lower() for u in under) for x in chain(name)):
            continue
        for cube in b["cubes"]:
            o, s = cube["origin"], cube["size"]
            inflate = cube.get("inflate", 0)
            fuv = face_uvs(cube, tw, th)
            faces = box_faces(o, s, inflate)
            cpiv = np.array(cube.get("pivot", [0, 0, 0]), float)
            crot = cube.get("rotation")
            for fname, corners in faces.items():
                if fname not in fuv:
                    continue
                pts = np.array(corners, float)
                if crot:
                    pts = (rot_matrix(*crot) @ (pts - cpiv).T).T + cpiv
                for bone in reversed(chain(name)):  # innermost first
                    r = bone.get("rotation")
                    if r:
                        piv = np.array(bone.get("pivot", [0, 0, 0]), float)
                        pts = (rot_matrix(*r) @ (pts - piv).T).T + piv
                u0, v0, w, h = fuv[fname]
                if w == 0 or h == 0:
                    continue
                # corners are (bottom-left, bottom-right, top-right, top-left) as seen from outside
                if fname == "down":  # the bottom face is stored v-flipped in the unfolding
                    cuv = [(u0 + w, v0 + h), (u0, v0 + h), (u0, v0), (u0 + w, v0)]
                elif fname == "up":
                    cuv = [(u0, v0 + h), (u0 + w, v0 + h), (u0 + w, v0), (u0, v0)]
                else:
                    cuv = [(u0 + w, v0 + h), (u0, v0 + h), (u0, v0), (u0 + w, v0)] if fname in ("north", "west") else \
                          [(u0, v0 + h), (u0 + w, v0 + h), (u0 + w, v0), (u0, v0)]
                base = len(V)
                V.extend(pts.tolist())
                UV.extend([(a / tw, b_ / th) for a, b_ in cuv])
                T.append((base, base + 1, base + 2))
                T.append((base, base + 2, base + 3))
    V = np.array(V, float)
    if len(V) == 0:
        raise SystemExit("no geometry left after filtering")
    if raw:  # the geometry as authored: y up, the front toward -z
        return V, np.array(T), np.array(UV), (tw, th)
    # geometry (forward -z, x mirrored by the renderer) -> game gun item (muzzle +x): x' = -z, z' = -x, y' = y. That mapping is a mirror, so
    # the winding of every triangle is reversed to keep the faces (and the normals computed from them) pointing out.
    out = np.stack([-V[:, 2], V[:, 1], -V[:, 0]], 1)
    T = np.array(T)[:, ::-1]
    return out, T, np.array(UV), (tw, th)


def normalise(V, length=1.625):
    lo, hi = V.min(0), V.max(0)
    sc = length / (hi[0] - lo[0])
    return (V - np.array([(lo[0] + hi[0]) / 2, lo[1], (lo[2] + hi[2]) / 2])) * sc + np.array([0.5, 0.3125, 0.5])


def write_obj(path, V, T, UV, tex_ref="#base", mtl_name=None):
    mtl_name = mtl_name or os.path.splitext(os.path.basename(path))[0] + ".mtl"
    lines = [f"mtllib {mtl_name}", "o model", "usemtl m0"]
    for v in V:
        lines.append("v %.5f %.5f %.5f" % tuple(v))
    for u, vv in UV:
        lines.append("vt %.5f %.5f" % (u, 1 - vv))
    # flat normals per triangle
    nl = []
    faces = []
    for t in T:
        a, b, c = V[t[0]], V[t[1]], V[t[2]]
        n = np.cross(b - a, c - a)
        n = n / (np.linalg.norm(n) + 1e-12)
        nl.append("vn %.4f %.4f %.4f" % tuple(n))
        k = len(nl)
        faces.append("f %d/%d/%d %d/%d/%d %d/%d/%d" % (t[0] + 1, t[0] + 1, k, t[1] + 1, t[1] + 1, k, t[2] + 1, t[2] + 1, k))
    lines += nl + faces
    open(path, "w").write("\n".join(lines) + "\n")
    open(os.path.join(os.path.dirname(path), mtl_name), "w").write(f"newmtl m0\nKd 1 1 1\nKa 0 0 0\nKs 0 0 0\nd 1\nmap_Kd {tex_ref}\n")


def preview(V, T, UV, tex, path, size=520):
    """Textured orthographic side view (muzzle right) + top view, painter's algorithm."""
    tex = tex.convert("RGBA")
    tw, th = tex.size
    arr = np.asarray(tex)
    img = Image.new("RGB", (size * 2, size // 2 + 40), (200, 208, 218))
    px = img.load()
    lo, hi = V.min(0), V.max(0)
    for k, (ua, va) in enumerate([(0, 1), (0, 2)]):
        scale = (size * 0.92) / (hi[0] - lo[0])
        order = np.argsort([V[t][:, 2 if k == 0 else 1].mean() * (1 if k == 0 else -1) for t in T])
        for ti in order:
            t = T[ti]
            P = V[t]
            n = np.cross(P[1] - P[0], P[2] - P[0])
            if (k == 0 and n[2] < 0) or (k == 1 and n[1] > 0):
                continue
            xs = (P[:, 0] - lo[0]) * scale + k * size + size * 0.04
            ys = (hi[1] - P[:, 1]) * scale + 20 if k == 0 else (P[:, 2] - lo[2]) * scale + 20
            uv = UV[t]
            x0, x1 = int(max(0, xs.min())), int(min(img.width - 1, xs.max() + 1))
            y0, y1 = int(max(0, ys.min())), int(min(img.height - 1, ys.max() + 1))
            den = (ys[1] - ys[2]) * (xs[0] - xs[2]) + (xs[2] - xs[1]) * (ys[0] - ys[2])
            if abs(den) < 1e-9:
                continue
            for y in range(y0, y1 + 1):
                for x in range(x0, x1 + 1):
                    l0 = ((ys[1] - ys[2]) * (x - xs[2]) + (xs[2] - xs[1]) * (y - ys[2])) / den
                    l1 = ((ys[2] - ys[0]) * (x - xs[2]) + (xs[0] - xs[2]) * (y - ys[2])) / den
                    l2 = 1 - l0 - l1
                    if l0 < -0.02 or l1 < -0.02 or l2 < -0.02:
                        continue
                    u = (l0 * uv[0][0] + l1 * uv[1][0] + l2 * uv[2][0]) * tw
                    v = (l0 * uv[0][1] + l1 * uv[1][1] + l2 * uv[2][1]) * th
                    r, g, b, a = arr[min(th - 1, max(0, int(v))), min(tw - 1, max(0, int(u)))]
                    if a > 128:
                        px[x, y] = (int(r), int(g), int(b))
    img.save(path)


if __name__ == "__main__":
    a = sys.argv[1:]
    geo, tex, out = a[0], a[1], a[2]
    skip = SKIP_DEFAULT
    only = None
    prev = None
    i = 3
    while i < len(a):
        if a[i] == "--skip":
            skip = tuple(a[i + 1].split(",")); i += 2
        elif a[i] == "--only":
            only = tuple(a[i + 1].split(",")); i += 2
        elif a[i] == "--preview":
            prev = a[i + 1]; i += 2
        else:
            i += 1
    V, T, UV, size = convert(geo, Image.open(tex).size, skip, only)
    V = normalise(V)
    write_obj(out, V, T, UV)
    print(out, len(V) // 4, "faces")
    if prev:
        preview(V, T, UV, Image.open(tex), prev)
