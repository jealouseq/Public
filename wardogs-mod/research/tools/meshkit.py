"""Procedural mesh primitives (boxes, cylinders, ellipsoids, prisms, lofts) returning meshlib.Mesh with one colour each."""
import numpy as np
import meshlib as ml


def _mesh(verts, tris, color):
    return ml.Mesh(np.asarray(verts, float), np.asarray(tris, int), np.zeros(len(tris), int), ["m"], [tuple(color)])


def box(cx, cy, cz, sx, sy, sz, color):
    """Axis-aligned box centred at (cx,cy,cz) with full sizes sx,sy,sz."""
    x0, x1, y0, y1, z0, z1 = cx - sx / 2, cx + sx / 2, cy - sy / 2, cy + sy / 2, cz - sz / 2, cz + sz / 2
    v = [[x0, y0, z0], [x1, y0, z0], [x1, y1, z0], [x0, y1, z0], [x0, y0, z1], [x1, y0, z1], [x1, y1, z1], [x0, y1, z1]]
    t = [[0, 2, 1], [0, 3, 2], [4, 5, 6], [4, 6, 7], [0, 1, 5], [0, 5, 4], [3, 6, 2], [3, 7, 6], [0, 4, 7], [0, 7, 3], [1, 2, 6], [1, 6, 5]]
    return _mesh(v, t, color)


def cylinder(c, r, length, axis="x", segs=16, color=(0.1, 0.1, 0.1), r2=None):
    """Cylinder (or truncated cone when r2 is given) along an axis, centred at c."""
    ang = np.linspace(0, 2 * np.pi, segs, endpoint=False)
    r2 = r if r2 is None else r2
    ring0 = np.stack([np.cos(ang) * r, np.sin(ang) * r], 1)
    ring1 = np.stack([np.cos(ang) * r2, np.sin(ang) * r2], 1)
    h = length / 2
    verts = []
    for ring, z in ((ring0, -h), (ring1, h)):
        for p in ring:
            verts.append([p[0], p[1], z])
    verts.append([0, 0, -h]); verts.append([0, 0, h])
    tris = []
    n = segs
    for i in range(n):
        j = (i + 1) % n
        tris += [[i, j, n + j], [i, n + j, n + i]]
        tris += [[2 * n, j, i]]
        tris += [[2 * n + 1, n + i, n + j]]
    v = np.array(verts)
    # axis mapping: the local z axis becomes the requested axis
    if axis == "x":
        v = v[:, [2, 1, 0]]
        tris = [[a, c2, b] for a, b, c2 in tris]
    elif axis == "y":
        v = v[:, [0, 2, 1]]
        tris = [[a, c2, b] for a, b, c2 in tris]
    v = v + np.asarray(c, float)
    return _mesh(v, tris, color)


def ellipsoid(c, rx, ry, rz, color, seg=12, rings=8):
    verts, tris = [], []
    for i in range(rings + 1):
        th = np.pi * i / rings
        for j in range(seg):
            ph = 2 * np.pi * j / seg
            verts.append([rx * np.sin(th) * np.cos(ph), ry * np.cos(th), rz * np.sin(th) * np.sin(ph)])
    for i in range(rings):
        for j in range(seg):
            a = i * seg + j
            b = i * seg + (j + 1) % seg
            tris += [[a, a + seg, b], [b, a + seg, b + seg]]
    v = np.array(verts) + np.asarray(c, float)
    return _mesh(v, tris, color)


def prism(profile, axis_from, axis_to, color, plane="zy", taper=None):
    """Extrudes a 2D polygon (a side profile) across the third axis from axis_from to axis_to (simple convex/star polygons via fan).

    plane "zy": polygon points are (z, y) and the extrusion runs along x; taper=(sx0,sx1) scales ends toward the centre line."""
    pts = np.asarray(profile, float)
    n = len(pts)
    cen = pts.mean(0)
    verts, tris = [], []
    for k, w in enumerate((axis_from, axis_to)):
        for p in pts:
            if plane == "zy":
                verts.append([w, p[1], p[0]])
            elif plane == "xy":
                verts.append([p[0], p[1], w])
            else:
                verts.append([p[0], w, p[1]])
    # sides
    for i in range(n):
        j = (i + 1) % n
        tris += [[i, j, n + j], [i, n + j, n + i]]
    # caps by fan from the centroid
    c0 = len(verts)
    for k, w in enumerate((axis_from, axis_to)):
        if plane == "zy":
            verts.append([w, cen[1], cen[0]])
        elif plane == "xy":
            verts.append([cen[0], cen[1], w])
        else:
            verts.append([cen[0], w, cen[1]])
    for i in range(n):
        j = (i + 1) % n
        tris += [[c0, j, i], [c0 + 1, n + i, n + j]]
    m = _mesh(verts, tris, color)
    return fix_winding(m)


def fix_winding(m):
    """Flips triangles whose normal points toward the mesh centre (for closed convex-ish parts)."""
    v = m.verts
    c = v.mean(0)
    a, b, cc = (v[m.tris[:, i]] for i in range(3))
    n = np.cross(b - a, cc - a)
    cen = (a + b + cc) / 3
    flip = (n * (cen - c)).sum(1) < 0
    t = m.tris.copy()
    t[flip] = t[flip][:, [0, 2, 1]]
    return ml.Mesh(m.verts, t, m.mat, m.names, m.colors)


def translate(m, dx=0, dy=0, dz=0):
    return ml.Mesh(m.verts + np.array([dx, dy, dz]), m.tris, m.mat, m.names, m.colors)


def rotate_y(m, deg):
    a = np.radians(deg)
    c, s = np.cos(a), np.sin(a)
    R = np.array([[c, 0, s], [0, 1, 0], [-s, 0, c]])
    return ml.Mesh(m.verts @ R.T, m.tris, m.mat, m.names, m.colors)


def scale(m, sx=1, sy=None, sz=None):
    sy = sx if sy is None else sy
    sz = sx if sz is None else sz
    return ml.Mesh(m.verts * np.array([sx, sy, sz]), m.tris, m.mat, m.names, m.colors)


def recolor(m, color):
    return ml.Mesh(m.verts, m.tris, m.mat, m.names, [tuple(color)] * len(m.names))


def union(parts):
    """Merges parts, collapsing identical colours into one material."""
    m = ml.merge(parts)
    uniq = {}
    colors, remap = [], []
    for c in m.colors:
        k = tuple(np.round(c, 3))
        if k not in uniq:
            uniq[k] = len(colors)
            colors.append(k)
        remap.append(uniq[k])
    mat = np.array(remap)[m.mat]
    return ml.Mesh(m.verts, m.tris, mat, [f"c{i}" for i in range(len(colors))], colors)
