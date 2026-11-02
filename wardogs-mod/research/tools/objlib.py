"""Reads textured OBJ files (with groups, vt, vn) into arrays and writes them back for the neoforge:obj loader. Third-party model files are
read from a copy you extracted yourself; outputs go to the git-ignored local-data/generated tree."""
import os
import numpy as np


def read(path):
    """-> dict(v: (n,3), vt: (m,2), tris: (k,3) vertex idx, tuv: (k,3) uv idx, group: list of group name per tri)"""
    V, VT = [], []
    tris, tuv, grp = [], [], []
    cur = "default"
    for line in open(path, errors="ignore"):
        if line.startswith("v "):
            V.append([float(x) for x in line.split()[1:4]])
        elif line.startswith("vt "):
            VT.append([float(x) for x in line.split()[1:3]])
        elif line.startswith(("o ", "g ")):
            cur = line[2:].strip()
        elif line.startswith("f "):
            pts = []
            for tok in line.split()[1:]:
                p = tok.split("/")
                vi = int(p[0]); vi = vi - 1 if vi > 0 else len(V) + vi
                ti = (int(p[1]) - 1 if int(p[1]) > 0 else len(VT) + int(p[1])) if len(p) > 1 and p[1] else -1
                pts.append((vi, ti))
            for k in range(1, len(pts) - 1):  # fan triangulation
                tris.append([pts[0][0], pts[k][0], pts[k + 1][0]])
                tuv.append([pts[0][1], pts[k][1], pts[k + 1][1]])
                grp.append(cur)
    return {"v": np.array(V), "vt": np.array(VT) if VT else np.zeros((0, 2)), "tris": np.array(tris), "tuv": np.array(tuv), "group": grp}


def select(m, keep):
    """Keeps the triangles whose group name satisfies keep(name)."""
    idx = [i for i, g in enumerate(m["group"]) if keep(g)]
    return {"v": m["v"], "vt": m["vt"], "tris": m["tris"][idx], "tuv": m["tuv"][idx], "group": [m["group"][i] for i in idx]}


def transform(m, fn):
    return {**m, "v": fn(m["v"])}


def bbox(m):
    P = m["v"][np.unique(m["tris"])]
    return P.min(0), P.max(0)


def write(m, path, tex_ref="#base", mtl=None, smooth_angle=45.0):
    """Writes OBJ + MTL; vertex normals are smoothed per corner within smooth_angle degrees (faces share no normals across hard edges)."""
    mtl = mtl or os.path.splitext(os.path.basename(path))[0] + ".mtl"
    V, T = m["v"], m["tris"]
    P = V[T]
    fn = np.cross(P[:, 1] - P[:, 0], P[:, 2] - P[:, 0])
    ln = np.linalg.norm(fn, axis=1, keepdims=True)
    fn = fn / np.maximum(ln, 1e-12)
    # per-vertex accumulation of face normals, then per corner keep only neighbours within the angle
    cos_t = np.cos(np.radians(smooth_angle))
    by_vertex = {}
    for f in range(len(T)):
        for k in range(3):
            by_vertex.setdefault(T[f, k], []).append(f)
    lines = [f"mtllib {mtl}", "o model", "usemtl m0"]
    lines += ["v %.5f %.5f %.5f" % tuple(v) for v in V]
    lines += ["vt %.5f %.5f" % (u, v) for u, v in m["vt"]]  # the pack is v-up like any OBJ; flip_v in the model json turns it to the game's top-down
    norms, nid, faces = [], {}, []
    for f in range(len(T)):
        corner = []
        for k in range(3):
            acc = np.zeros(3)
            for g in by_vertex[T[f, k]]:
                if fn[g] @ fn[f] >= cos_t:
                    acc += fn[g] * max(ln[g, 0], 1e-9)
            n = acc / (np.linalg.norm(acc) + 1e-12)
            key = tuple(np.round(n, 3))
            if key not in nid:
                nid[key] = len(norms) + 1
                norms.append(key)
            ti = m["tuv"][f, k]
            corner.append("%d/%s/%d" % (T[f, k] + 1, (ti + 1) if ti >= 0 else "", nid[key]))
        faces.append("f " + " ".join(corner))
    lines += ["vn %.3f %.3f %.3f" % n for n in norms] + faces
    open(path, "w").write("\n".join(lines) + "\n")
    open(os.path.join(os.path.dirname(path), mtl), "w").write(f"newmtl m0\nKd 1 1 1\nKa 0 0 0\nKs 0 0 0\nd 1\nmap_Kd {tex_ref}\n")
