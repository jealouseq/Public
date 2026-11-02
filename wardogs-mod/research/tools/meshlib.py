"""Small mesh helpers shared by the model tools: OBJ reading/writing, material colours, smooth normals and a software preview renderer.

Meshes are plain numpy arrays: verts (n,3), tris (m,3) vertex indices, mat (m,) material index, names (list of material names),
colors (list of linear/sRGB rgb floats per material).
"""
import re
import numpy as np
from PIL import Image, ImageDraw


class Mesh:
    def __init__(self, verts, tris, mat, names, colors):
        self.verts = np.asarray(verts, float)
        self.tris = np.asarray(tris, int)
        self.mat = np.asarray(mat, int)
        self.names = list(names)
        self.colors = [tuple(c) for c in colors]

    def copy(self):
        return Mesh(self.verts.copy(), self.tris.copy(), self.mat.copy(), self.names, self.colors)

    def bbox(self):
        return self.verts.min(0), self.verts.max(0)

    def face_normals(self):
        a, b, c = (self.verts[self.tris[:, i]] for i in range(3))
        n = np.cross(b - a, c - a)
        l = np.linalg.norm(n, axis=1, keepdims=True)
        return n / np.maximum(l, 1e-12), l[:, 0] / 2


def srgb(c):
    c = np.clip(np.asarray(c, float), 0, 1)
    return tuple(c ** (1 / 2.2))


def read_obj(path, gamma=True):
    """Reads an OBJ with usemtl groups and an MTL next to it (Kd only). Quads and polygons are fanned into triangles."""
    import os
    verts, tris, mats = [], [], []
    names, colors = [], []
    index = {}
    mtl = {}
    d = os.path.dirname(path)
    cur = None
    for line in open(path, errors="replace"):
        p = line.strip().split(None, 1)
        if not p:
            continue
        if p[0] == "mtllib":
            mp = os.path.join(d, p[1].strip())
            if os.path.exists(mp):
                name = None
                for l2 in open(mp, errors="replace"):
                    q = l2.strip().split(None, 1)
                    if not q:
                        continue
                    if q[0] == "newmtl":
                        name = q[1].strip()
                    elif q[0] == "Kd" and name:
                        c = [float(x) for x in q[1].split()[:3]]
                        mtl[name] = srgb(c) if gamma else tuple(c)
        elif p[0] == "v":
            verts.append([float(x) for x in p[1].split()[:3]])
        elif p[0] == "usemtl":
            n = p[1].strip()
            if n not in index:
                index[n] = len(names)
                names.append(n)
                colors.append(mtl.get(n, (0.6, 0.6, 0.6)))
            cur = index[n]
        elif p[0] == "f":
            ids = [int(t.split("/")[0]) for t in p[1].split()]
            ids = [i - 1 if i > 0 else len(verts) + i for i in ids]
            if cur is None:
                index["default"] = len(names); names.append("default"); colors.append((0.6, 0.6, 0.6)); cur = index["default"]
            for k in range(1, len(ids) - 1):
                tris.append([ids[0], ids[k], ids[k + 1]])
                mats.append(cur)
    return Mesh(verts, tris, mats, names, colors)


def merge(meshes):
    verts, tris, mat, names, colors = [], [], [], [], []
    off = 0
    for m in meshes:
        verts.append(m.verts)
        tris.append(m.tris + off)
        base = len(names)
        mat.append(m.mat + base)
        names += m.names
        colors += m.colors
        off += len(m.verts)
    return Mesh(np.vstack(verts), np.vstack(tris), np.concatenate(mat), names, colors)


def compact(m):
    """Drops unused vertices and degenerate triangles."""
    a, b, c = (m.tris[:, i] for i in range(3))
    keep = (a != b) & (b != c) & (a != c)
    t = m.tris[keep]
    u, inv = np.unique(t.ravel(), return_inverse=True)
    return Mesh(m.verts[u], inv.reshape(-1, 3), m.mat[keep], m.names, m.colors)


def decimate(m, target):
    """Reduces the triangle count to about target, per material so colour borders stay put (quadric simplification)."""
    if len(m.tris) <= target:
        return m
    import fast_simplification
    parts = []
    for mi in range(len(m.names)):
        sel = m.tris[m.mat == mi]
        if len(sel) == 0:
            continue
        sub_target = max(12, int(target * len(sel) / len(m.tris)))
        u, inv = np.unique(sel.ravel(), return_inverse=True)
        v = m.verts[u]
        t = inv.reshape(-1, 3)
        if len(t) > sub_target:
            red = 1.0 - sub_target / len(t)
            try:
                v2, t2 = fast_simplification.simplify(v.astype(np.float32), t.astype(np.int32), target_reduction=red, agg=5)
            except Exception:
                v2, t2 = v, t
        else:
            v2, t2 = v, t
        parts.append(Mesh(v2, t2, np.full(len(t2), mi), m.names, m.colors))
    return compact(merge_same(parts, m))


def merge_same(parts, m):
    verts, tris, mat = [], [], []
    off = 0
    for p in parts:
        verts.append(p.verts); tris.append(p.tris + off); mat.append(p.mat); off += len(p.verts)
    return Mesh(np.vstack(verts), np.vstack(tris), np.concatenate(mat), m.names, m.colors)


def corner_normals(m, angle=40.0):
    """Per-corner normals: the average of the adjacent faces that lie within `angle` degrees of the corner's own face, by position."""
    fn, area = m.face_normals()
    key = np.round(m.verts * 1e4).astype(np.int64)
    _, vid = np.unique(key, axis=0, return_inverse=True)
    vid = vid.ravel()
    corner_v = vid[m.tris]                                  # (m,3) welded vertex ids
    n_v = vid.max() + 1
    faces_of = [[] for _ in range(n_v)]
    for f in range(len(m.tris)):
        for k in range(3):
            faces_of[corner_v[f, k]].append(f)
    cos_t = np.cos(np.radians(angle))
    out = np.zeros((len(m.tris), 3, 3))
    for f in range(len(m.tris)):
        for k in range(3):
            fs = faces_of[corner_v[f, k]]
            sel = [g for g in fs if fn[g] @ fn[f] >= cos_t]
            nn = (fn[sel] * area[sel, None]).sum(0)
            l = np.linalg.norm(nn)
            out[f, k] = nn / l if l > 1e-12 else fn[f]
    return out


def write_obj(m, obj_path, mtl_name, texture_ref, smooth=True, angle=40.0):
    """Writes OBJ + MTL for NeoForge's neoforge:obj loader: one material per colour with Kd = colour and a shared white texture."""
    import os
    cn = corner_normals(m, angle) if smooth else None
    fn, _ = m.face_normals()
    lines = [f"mtllib {mtl_name}", "o model"]
    for v in m.verts:
        lines.append("v %.5f %.5f %.5f" % tuple(v))
    nlist = []
    nid = {}
    def norm_index(n):
        k = tuple(np.round(n, 3))
        if k not in nid:
            nid[k] = len(nlist) + 1
            nlist.append(k)
        return nid[k]
    face_lines = {}
    for f in range(len(m.tris)):
        idx = []
        for k in range(3):
            n = cn[f, k] if smooth else fn[f]
            idx.append("%d//%d" % (m.tris[f, k] + 1, norm_index(n)))
        face_lines.setdefault(m.mat[f], []).append("f " + " ".join(idx))
    for n in nlist:
        lines.append("vn %.3f %.3f %.3f" % n)
    for mi, fl in face_lines.items():
        lines.append("usemtl m%d" % mi)
        lines += fl
    open(obj_path, "w").write("\n".join(lines) + "\n")
    mtl = []
    for mi in sorted(face_lines):
        r, g, b = m.colors[mi]
        mtl += [f"newmtl m{mi}", "Kd %.4f %.4f %.4f" % (r, g, b), "Ka 0 0 0", "Ks 0 0 0", "d 1", f"map_Kd {texture_ref}", ""]
    open(os.path.join(os.path.dirname(obj_path), mtl_name), "w").write("\n".join(mtl))


def preview(m, path, size=420, views=("side", "top", "front"), bg=(214, 224, 235)):
    """Orthographic software render (painter's algorithm, headlight shading) of three views side by side."""
    imgs = []
    lo, hi = m.bbox()
    ext = (hi - lo)
    cen = (hi + lo) / 2
    scale = (size * 0.86) / max(ext.max(), 1e-9)
    fn, _ = m.face_normals()
    light = np.array([0.4, 0.8, 0.5]); light /= np.linalg.norm(light)
    cols = np.array([m.colors[i] for i in m.mat])
    for view in views:
        # camera axes: u right, v up, w toward the viewer
        if view == "side":    u, v, w = np.array([0, 0, 1.0]), np.array([0, 1.0, 0]), np.array([1.0, 0, 0])
        elif view == "top":   u, v, w = np.array([1.0, 0, 0]), np.array([0, 0, -1.0]), np.array([0, 1.0, 0])
        else:                 u, v, w = np.array([1.0, 0, 0]), np.array([0, 1.0, 0]), np.array([0, 0, 1.0])
        P = (m.verts - cen)
        x = P @ u * scale + size / 2
        y = size / 2 - P @ v * scale
        z = P @ w
        tz = z[m.tris].mean(1)
        order = np.argsort(tz)
        img = Image.new("RGB", (size, size), bg)
        d = ImageDraw.Draw(img)
        for f in order:
            if fn[f] @ w < -0.0:
                pass
            shade = 0.45 + 0.55 * max(0.0, fn[f] @ light) if True else 1
            c = tuple(int(255 * min(1, cols[f][i] * shade * 1.15)) for i in range(3))
            pts = [(x[m.tris[f, k]], y[m.tris[f, k]]) for k in range(3)]
            d.polygon(pts, fill=c)
        imgs.append(img)
    out = Image.new("RGB", (size * len(imgs), size), bg)
    for i, im in enumerate(imgs):
        out.paste(im, (i * size, 0))
    out.save(path)


def reorient(m, spec, scale=1.0, flip_z=False):
    """Remaps axes: spec like "x,z,-y" gives the new x, y, z axes in terms of the old ones. Winding is kept consistent."""
    idx = {"x": 0, "y": 1, "z": 2}
    cols, sign = [], 1
    for tok in spec.split(","):
        tok = tok.strip()
        neg = tok.startswith("-")
        cols.append((idx[tok.lstrip("-")], -1.0 if neg else 1.0))
        sign *= -1 if neg else 1
    v = np.stack([m.verts[:, c] * s for c, s in cols], axis=1) * scale
    # an odd number of mirrored axes plus an odd permutation flips handedness: detect through the determinant
    perm = [c for c, _ in cols]
    det = np.linalg.det(np.array([[(1.0 if j == c else 0.0) * sg for j in range(3)] for c, sg in cols]))
    t = m.tris if det > 0 else m.tris[:, [0, 2, 1]]
    return Mesh(v, t, m.mat, m.names, m.colors)


def place(m, length_axis="z", length=None, ground=True, center_xz=True):
    """Scales so the extent along the chosen axis equals `length` (if given), puts the model on y=0 and centres it in x and z."""
    lo, hi = m.bbox()
    ext = hi - lo
    sc = 1.0 if length is None else length / ext["xyz".index(length_axis)]
    v = (m.verts - np.array([(lo[0] + hi[0]) / 2 if center_xz else 0, lo[1] if ground else 0, (lo[2] + hi[2]) / 2 if center_xz else 0])) * sc
    return Mesh(v, m.tris, m.mat, m.names, m.colors)
