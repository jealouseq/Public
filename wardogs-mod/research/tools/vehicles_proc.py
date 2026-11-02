"""Procedural low-poly vehicles built from smooth primitives (prisms, cylinders, ellipsoids) on top of meshkit.

Conventions: x = width, y = up, z = length, FRONT toward -z, origin on the ground at the vehicle's centre. Sizes are in blocks (metres).
"""
import numpy as np
import meshkit as mk
import meshlib as ml


def hexc(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) / 255 for i in (0, 2, 4))


C = dict(olive=hexc("#59633f"), olive2=hexc("#4b5436"), tan=hexc("#a8976a"), tan2=hexc("#93845c"), black=hexc("#1c1d20"), rubber=hexc("#222326"),
         steel=hexc("#7d8388"), dark=hexc("#3b3e42"), glass=hexc("#6c8da0"), red=hexc("#b3342b"), white=hexc("#dedbd0"), orange=hexc("#d8782a"),
         brown=hexc("#5e4630"), green=hexc("#3f5a3a"), grey=hexc("#8b8d8f"), lamp=hexc("#f0e1a0"), camo=hexc("#5a6244"), sand=hexc("#b8a878"),
         navy=hexc("#2e3d52"), rust=hexc("#8a4a2a"))


def wheel(x, y, z, r, w, hub=True):
    parts = [mk.cylinder((x, y, z), r, w, "x", 18, C["rubber"])]
    if hub:
        side = 1 if x > 0 else -1
        parts.append(mk.cylinder((x + side * w / 2, y, z), r * 0.55, 0.04, "x", 14, C["steel"]))
        parts.append(mk.cylinder((x + side * (w / 2 + 0.02), y, z), r * 0.22, 0.04, "x", 10, C["dark"]))
    return parts


def prof(points, x0, x1, color):
    """A side-profile prism: points are (z, y); extruded between x0 and x1."""
    return mk.prism(points, x0, x1, color, "zy")


def tracks(L, W, track_w, h, color_track=None, wheels=7, sprocket=0.5, skirt=False, base_y=0.0):
    """Two track assemblies: rubber belt (rounded profile prism), road wheels, drive sprocket and idler."""
    parts = []
    col = color_track or C["rubber"]
    for sgn in (-1, 1):
        x = sgn * (W / 2 - track_w / 2)
        r = h / 2
        zf, zb = -L / 2, L / 2
        # belt profile: a stadium made of a polygon (front/back rounded by 3 points each)
        pts = [(zf + r * 0.55, base_y + h), (zb - r * 0.55, base_y + h), (zb - r * 0.1, base_y + h * 0.78), (zb, base_y + h * 0.5),
               (zb - r * 0.15, base_y + h * 0.2), (zb - r * 0.55, base_y), (zf + r * 0.55, base_y), (zf + r * 0.15, base_y + h * 0.2),
               (zf, base_y + h * 0.5), (zf + r * 0.1, base_y + h * 0.78)]
        parts.append(prof(pts, x - track_w / 2, x + track_w / 2, col))
        # road wheels
        n = wheels
        z0 = zf + r * 1.25
        z1 = zb - r * 1.25
        for i in range(n):
            z = z0 + (z1 - z0) * i / max(1, n - 1)
            parts.append(mk.cylinder((x + sgn * track_w * 0.52, base_y + r * 0.62, z), r * 0.52, 0.06, "x", 12, C["dark"]))
        # sprocket (rear) and idler (front)
        parts.append(mk.cylinder((x + sgn * track_w * 0.52, base_y + r * 0.95, zb - r * 0.75), r * sprocket * 0.9, 0.07, "x", 12, C["steel"]))
        parts.append(mk.cylinder((x + sgn * track_w * 0.52, base_y + r * 0.95, zf + r * 0.75), r * sprocket * 0.9, 0.07, "x", 12, C["steel"]))
        if skirt:
            parts.append(mk.box(x + sgn * (track_w / 2 + 0.05), base_y + h * 0.78, 0, 0.1, h * 0.42, L * 0.86, C["olive2"]))
    return parts


def barrel(z_start, y, length, r, color, brake=False, extractor=0.0, x=0.0):
    """A gun barrel pointing forward (-z) starting at z_start."""
    parts = [mk.cylinder((x, y, z_start - length / 2), r, length, "z", 12, color)]
    if extractor:
        parts.append(mk.cylinder((x, y, z_start - length * 0.62), r * 1.55, length * 0.16, "z", 12, color))
    if brake:
        parts.append(mk.cylinder((x, y, z_start - length - 0.12), r * 1.7, 0.34, "z", 10, color))
    parts.append(mk.cylinder((x, y, z_start - length - (0.18 if brake else 0)), r * 0.55, 0.02, "z", 8, C["black"]))
    return parts


def turret_poly(pts_xz, y0, y1, color, shrink=1.0):
    """A turret as a vertical prism over a plan polygon in (x, z)."""
    p = np.asarray(pts_xz, float) * shrink
    return mk.prism(p, y0, y1, color, "xz")


# ---------------------------------------------------------------------------------------------------------------------------------
# tracked vehicles
# ---------------------------------------------------------------------------------------------------------------------------------

def mbt(kind="l2a6"):
    """Main battle tank. kind: l2a6 (angular wedge turret, flat deck) or t72b3 (round dome turret, side skirts, ERA)."""
    L, W = 7.4, 3.4
    col = C["olive"] if kind == "t72b3" else C["tan2"]
    col2 = C["olive2"] if kind == "t72b3" else C["tan"]
    track_w = 0.62
    parts = tracks(L, W, track_w, 1.05, wheels=7 if kind == "t72b3" else 7, skirt=False)
    # hull: sloped glacis, flat deck, sloped rear
    hull = [(-L / 2 + 0.2, 0.72), (-L / 2 + 0.9, 1.52), (-L / 2 + 2.2, 1.62), (L / 2 - 1.0, 1.62), (L / 2 - 0.1, 1.35), (L / 2 - 0.05, 0.75), (L / 2 - 0.5, 0.55), (-L / 2 + 0.6, 0.55)]
    parts.append(prof(hull, -W / 2 + track_w * 0.95, W / 2 - track_w * 0.95, col))
    # side fenders/skirts
    for sgn in (-1, 1):
        parts.append(mk.box(sgn * (W / 2 - 0.34), 1.28, 0.1, 0.62, 0.12, L * 0.78, col2))
        if kind == "t72b3":
            for k in range(8):  # ERA bricks on the skirt
                parts.append(mk.box(sgn * (W / 2 - 0.05), 1.12, -L * 0.34 + k * 0.62, 0.12, 0.5, 0.5, C["dark"]))
        else:
            parts.append(mk.box(sgn * (W / 2 - 0.05), 0.95, 0.1, 0.1, 0.62, L * 0.7, col2))
    ty = 1.62
    if kind == "t72b3":
        # round dome turret (octagon plan) with a thicker front arc
        pl = [(-1.0, -1.35), (1.0, -1.35), (1.55, -0.55), (1.55, 0.7), (1.0, 1.25), (-1.0, 1.25), (-1.55, 0.7), (-1.55, -0.55)]
        parts.append(turret_poly(pl, ty, ty + 0.72, col, 1.0))
        pl2 = [(-0.85, -1.15), (0.85, -1.15), (1.3, -0.5), (1.3, 0.5), (0.85, 1.0), (-0.85, 1.0), (-1.3, 0.5), (-1.3, -0.5)]
        parts.append(turret_poly(pl2, ty + 0.72, ty + 0.92, col, 1.0))
        parts.append(mk.cylinder((0.55, ty + 1.05, 0.15), 0.3, 0.25, "y", 12, col2))  # commander cupola
        parts.append(mk.cylinder((-0.55, ty + 0.98, 0.35), 0.28, 0.12, "y", 12, col2))  # loader hatch
        parts += barrel(-1.35, ty + 0.5, 3.3, 0.14, C["dark"], extractor=1.0)
        parts.append(mk.box(0, ty + 0.5, -1.4, 0.55, 0.45, 0.35, col2))  # gun mantlet
        parts.append(mk.cylinder((-0.6, ty + 1.2, 0.1), 0.03, 0.1, "y", 6, C["dark"]))
        parts.append(mk.cylinder((0.0, ty + 1.45, 0.95), 0.03, 1.1, "y", 6, C["black"]))  # antenna
        parts.append(mk.box(0, ty + 0.55, 1.45, 1.1, 0.4, 0.5, col))  # turret stowage bustle
    else:
        # angular wedge turret with a flat roof and a long smooth gun
        pl = [(-0.95, -1.45), (0.95, -1.45), (1.55, -0.65), (1.5, 1.35), (0.9, 1.55), (-0.9, 1.55), (-1.5, 1.35), (-1.55, -0.65)]
        parts.append(turret_poly(pl, ty, ty + 0.78, col, 1.0))
        parts.append(mk.box(0, ty + 0.55, -1.55, 0.6, 0.5, 0.3, col2))
        parts += barrel(-1.7, ty + 0.55, 3.5, 0.125, C["dark"], extractor=1.0)
        parts.append(mk.cylinder((0.6, ty + 0.98, 0.2), 0.28, 0.2, "y", 12, col2))
        parts.append(mk.cylinder((-0.6, ty + 0.9, 0.6), 0.26, 0.1, "y", 12, col2))
        for sgn in (-1, 1):  # smoke dischargers
            for k in range(4):
                parts.append(mk.cylinder((sgn * 1.58, ty + 0.55, -0.4 + k * 0.28), 0.05, 0.35, "x", 6, C["dark"]))
        parts.append(mk.box(0, ty + 0.5, 1.7, 1.4, 0.5, 0.6, col))
    return mk.union(parts)


def gepard():
    L, W = 7.2, 3.3
    col = C["olive"]
    parts = tracks(L, W, 0.62, 1.05, wheels=6)
    hull = [(-L / 2 + 0.3, 0.72), (-L / 2 + 1.0, 1.5), (L / 2 - 1.0, 1.5), (L / 2 - 0.1, 1.3), (L / 2 - 0.05, 0.75), (-L / 2 + 0.6, 0.55)]
    parts.append(prof(hull, -W / 2 + 0.6, W / 2 - 0.6, col))
    # large flat-sided turret
    parts.append(mk.box(0, 2.15, 0.5, 2.5, 1.15, 2.7, col))
    parts.append(prof([(-0.8, 1.6), (-0.5, 2.7), (1.7, 2.7), (1.85, 1.6)], -1.25, 1.25, C["olive2"]))
    # twin 35 mm cannons with muzzle sleeves
    for sgn in (-1, 1):
        parts += barrel(-0.8, 2.25, 2.6, 0.1, C["dark"], x=sgn * 1.45, brake=False)
        parts.append(mk.box(sgn * 1.45, 2.25, -0.85, 0.38, 0.38, 0.8, C["olive2"]))
        parts.append(mk.cylinder((sgn * 1.45, 2.25, -3.1), 0.15, 0.4, "z", 10, C["dark"]))
    # search radar (rear mast) and tracking radar (front)
    parts.append(mk.cylinder((0, 3.0, 1.9), 0.08, 0.7, "y", 8, C["dark"]))
    parts.append(prof([(1.55, 3.3), (1.95, 3.9), (2.3, 3.9), (2.3, 3.3)], -0.95, 0.95, C["grey"]))
    parts.append(mk.cylinder((0, 3.45, -0.2), 0.45, 0.14, "z", 14, C["grey"]))
    parts.append(mk.cylinder((0, 3.0, -0.2), 0.08, 0.7, "y", 8, C["dark"]))
    return mk.union(parts)


def sph2():
    L, W = 7.8, 3.4
    col = C["tan2"]
    parts = tracks(L, W, 0.64, 1.1, wheels=7)
    hull = [(-L / 2 + 0.2, 0.72), (-L / 2 + 0.9, 1.55), (L / 2 - 0.3, 1.6), (L / 2, 1.2), (L / 2 - 0.1, 0.75), (-L / 2 + 0.6, 0.55)]
    parts.append(prof(hull, -W / 2 + 0.6, W / 2 - 0.6, col))
    # big rear turret with the gun cradle
    pl = [(-1.15, -1.0), (1.15, -1.0), (1.55, -0.3), (1.55, 2.0), (1.1, 2.5), (-1.1, 2.5), (-1.55, 2.0), (-1.55, -0.3)]
    parts.append(turret_poly(pl, 1.6, 2.75, col))
    parts.append(mk.box(0, 2.2, -1.1, 0.8, 0.7, 0.5, C["tan"]))
    parts += barrel(-1.35, 2.3, 4.0, 0.17, C["dark"], brake=True, extractor=1.0)
    parts.append(mk.cylinder((0.8, 2.95, 1.6), 0.28, 0.14, "y", 12, C["tan"]))  # hatch
    parts.append(mk.box(0, 1.9, 2.75, 2.6, 0.5, 0.35, C["olive2"]))  # rear spade
    parts.append(mk.cylinder((-1.0, 3.4, 2.0), 0.03, 1.4, "y", 6, C["black"]))
    return mk.union(parts)


def m113():
    L, W = 5.2, 2.7
    col = C["olive"]
    parts = tracks(L, W, 0.46, 0.8, wheels=5, base_y=0.0)
    hull = [(-L / 2 + 0.1, 0.6), (-L / 2 + 0.75, 1.45), (-L / 2 + 1.15, 2.05), (L / 2 - 0.1, 2.05), (L / 2, 1.9), (L / 2, 0.6), (-L / 2 + 0.3, 0.45)]
    parts.append(prof(hull, -W / 2 + 0.25, W / 2 - 0.25, col))
    parts.append(prof([(-L / 2 + 0.75, 1.45), (-L / 2 + 1.15, 2.05), (-L / 2 + 1.25, 2.05)], -W / 2 + 0.3, W / 2 - 0.3, C["olive2"]))
    # commander cupola with a heavy machine gun, rear ramp lines, antenna
    parts.append(mk.cylinder((0.55, 2.3, 0.2), 0.36, 0.34, "y", 12, C["olive2"]))
    parts += barrel(-0.0, 2.5, 1.4, 0.04, C["black"], x=0.55)
    parts.append(mk.box(-0.6, 2.2, 0.9, 0.7, 0.28, 0.8, C["olive2"]))
    parts.append(mk.cylinder((-1.0, 2.9, 1.8), 0.025, 1.6, "y", 6, C["black"]))
    return mk.union(parts)


# ---------------------------------------------------------------------------------------------------------------------------------
# wheeled vehicles
# ---------------------------------------------------------------------------------------------------------------------------------

def cabin(zf, zr, y0, y1, w, slope_f, slope_r, paint, glass=True, pillar=0.11):
    """A greenhouse: trapezoid side profile with sloped windscreen and rear glass; glass panes inset by the pillar size."""
    prof_pts = [(zf, y0), (zf + slope_f, y1), (zr - slope_r, y1), (zr, y0)]
    parts = [prof(prof_pts, -w / 2, w / 2, paint)]
    if glass:
        gp = [(zf + pillar * 1.4, y0 + 0.08), (zf + slope_f + pillar * 0.4, y1 - 0.07), (zr - slope_r - pillar * 0.4, y1 - 0.07), (zr - pillar * 1.4, y0 + 0.08)]
        parts.append(prof(gp, -w / 2 - 0.015, w / 2 + 0.015, C["glass"]))
        # a centre pillar splits the side glass in two
        zc = (zf + zr) / 2
        parts.append(mk.box(0, (y0 + y1) / 2, zc, w + 0.05, y1 - y0, pillar, paint))
    return parts


def wheeled(L, W, wb, wr, ww, ride, body_h, hood_frac, cab_len, cab_h, paint, glass=True, trim=None, extra=None, rear_axles=1, track_w=None,
            bumper=True, roof_rack=False):
    """A car-like vehicle: ladder frame, hood/body, greenhouse, wheels (rear_axles >= 1), lights, bumpers."""
    paint2 = trim or C["dark"]
    parts = []
    tw = track_w or (W - ww * 0.9)
    zf = -L / 2
    axle_f = zf + L * 0.18
    axles = [axle_f] + [axle_f + wb * (1 + 0.5 * i) if False else axle_f + wb - i * 0 for i in range(1)]
    zs = [axle_f]
    for i in range(rear_axles):
        zs.append(axle_f + wb + i * wr * 2.1)
    for z in zs:
        for sgn in (-1, 1):
            parts += wheel(sgn * tw / 2, wr, z, wr, ww)
    y0 = ride
    # lower body (tub) with a sloped hood
    hood_z = zf + L * hood_frac
    body = [(zf + 0.15, y0 + body_h * 0.45), (zf, y0 + body_h * 0.7), (zf + 0.4, y0 + body_h * 1.0), (hood_z, y0 + body_h * 1.05), (L / 2 - 0.05, y0 + body_h * 1.05),
            (L / 2, y0 + body_h * 0.75), (L / 2, y0 + body_h * 0.25), (L / 2 - 0.4, y0), (zf + 0.5, y0)]
    parts.append(prof(body, -W / 2, W / 2, paint))
    # wheel arches as dark boxes over the wheels
    for z in zs:
        for sgn in (-1, 1):
            parts.append(mk.box(sgn * (W / 2 + 0.01), wr * 1.05, z, 0.06, wr * 1.1, wr * 2.2, C["black"]))
    cab_top = y0 + body_h * 1.05 + cab_h
    parts += cabin(hood_z - 0.05, hood_z + cab_len, y0 + body_h * 1.05, cab_top, W - 0.14, 0.55, 0.3, paint, glass)
    if bumper:
        parts.append(mk.box(0, y0 + body_h * 0.35, zf - 0.04, W + 0.1, 0.2, 0.18, paint2))
        parts.append(mk.box(0, y0 + body_h * 0.35, L / 2 + 0.04, W + 0.1, 0.2, 0.18, paint2))
    for sgn in (-1, 1):  # headlights and tail lights
        parts.append(mk.cylinder((sgn * (W / 2 - 0.35), y0 + body_h * 0.78, zf + 0.02), 0.13, 0.08, "z", 10, C["lamp"]))
        parts.append(mk.box(sgn * (W / 2 - 0.15), y0 + body_h * 0.7, L / 2 + 0.02, 0.2, 0.14, 0.05, C["red"]))
        parts.append(mk.box(sgn * (W / 2 + 0.1), y0 + body_h * 1.1 + cab_h * 0.55, hood_z + 0.1, 0.12, 0.2, 0.08, paint2))  # mirror
    parts.append(mk.box(0, y0 + body_h * 0.72, zf + 0.02, W * 0.5, 0.17, 0.05, C["black"]))  # grille
    if roof_rack:
        parts.append(mk.box(0, cab_top + 0.06, hood_z + cab_len * 0.4, W * 0.7, 0.06, cab_len * 0.7, C["dark"]))
    if extra:
        parts += extra(zf, hood_z, cab_top, y0, body_h, L, W)
    return mk.union(parts)


def humvee(gun=None):
    """HMMWV: wide, low, flat-sided with a flat hood and a cargo area. gun: None, 'm249' (roof ring gun) or 'minigun'."""
    L, W = 4.6, 2.2
    paint = C["tan"]
    def extra(zf, hz, cab_top, y0, bh, L_, W_):
        parts = []
        # open cargo bed and rear spare wheel
        parts.append(mk.cylinder((0, y0 + bh * 1.4, L_ / 2 + 0.2), 0.42, 0.28, "z", 14, C["rubber"]))
        if gun:
            parts.append(mk.cylinder((0.0, cab_top + 0.1, hz + 0.9), 0.5, 0.14, "y", 14, C["dark"]))  # gun ring
            parts.append(mk.box(0, cab_top + 0.38, hz + 0.9, 0.35, 0.5, 0.35, C["dark"]))
            if gun == "minigun":
                for k in range(4):
                    a = k * np.pi / 2
                    parts.append(mk.cylinder((0.07 * np.cos(a), cab_top + 0.62 + 0.07 * np.sin(a), hz + 0.0), 0.025, 1.1, "z", 6, C["black"]))
            else:
                parts += barrel(hz + 0.75, cab_top + 0.65, 1.0, 0.04, C["black"])
        return parts
    return wheeled(L, W, 2.7, 0.46, 0.38, 0.28, 0.85, 0.30, 2.0, 0.58, paint, extra=extra)


def suv(kind="kodiak", gun=False):
    """Civilian-style SUV or pickup in dark brown; kind: kodiak, pickup."""
    L, W = (4.0, 1.9) if kind == "kodiak" else (4.6, 1.9)
    paint = C["brown"]
    def extra(zf, hz, cab_top, y0, bh, L_, W_):
        parts = []
        if kind == "pickup":
            parts.append(mk.box(0, y0 + bh * 1.1 + 0.1, L_ / 2 - 0.95, W_ - 0.2, 0.4, 1.7, C["dark"]))
            parts.append(mk.box(0, y0 + bh * 1.1 + 0.34, L_ / 2 - 0.95, W_ - 0.1, 0.06, 1.75, paint))
        if gun:
            parts.append(mk.cylinder((0, cab_top + 0.1, hz + 0.6), 0.34, 0.12, "y", 12, C["dark"]))
            parts += barrel(hz + 0.5, cab_top + 0.45, 0.95, 0.04, C["black"])
        return parts
    return wheeled(L, W, 2.6 if kind == "kodiak" else 2.85, 0.42, 0.3, 0.22, 0.9, 0.32, 1.6 if kind == "kodiak" else 1.3, 0.62, paint, roof_rack=True, extra=extra)


def atv():
    L, W = 2.0, 1.2
    parts = []
    for sgn in (-1, 1):
        for z in (-0.62, 0.62):
            parts += wheel(sgn * 0.52, 0.3, z, 0.3, 0.26)
    parts.append(prof([(-0.85, 0.35), (-0.7, 0.8), (-0.2, 0.85), (0.3, 0.78), (0.95, 0.72), (1.0, 0.45), (0.6, 0.35)], -0.34, 0.34, C["green"]))
    parts.append(prof([(-0.05, 0.85), (0.1, 1.0), (0.75, 0.98), (0.85, 0.82)], -0.2, 0.2, C["dark"]))  # seat
    parts.append(mk.cylinder((0, 1.05, -0.45), 0.02, 0.9, "x", 8, C["steel"]))  # handlebar
    parts.append(mk.cylinder((0, 0.92, -0.45), 0.03, 0.3, "y", 8, C["steel"]))
    parts.append(mk.box(0, 0.75, -0.95, 0.5, 0.18, 0.12, C["dark"]))  # front rack
    parts.append(mk.cylinder((0.0, 0.8, -0.85), 0.11, 0.06, "z", 10, C["lamp"]))
    return mk.union(parts)


def buggy():
    """Open dune buggy: tube frame, bucket seats, big rear tyres."""
    parts = []
    for sgn in (-1, 1):
        parts += wheel(sgn * 0.78, 0.38, -0.95, 0.38, 0.3)
        parts += wheel(sgn * 0.82, 0.44, 0.95, 0.44, 0.42)
    parts.append(prof([(-1.4, 0.4), (-1.15, 0.78), (0.7, 0.8), (1.25, 0.7), (1.3, 0.45), (0.9, 0.4)], -0.55, 0.55, C["orange"]))
    for sgn in (-1, 1):  # roll cage tubes
        for z0, z1 in ((-0.25, 0.35), (0.35, 0.95)):
            parts.append(mk.cylinder((sgn * 0.5, 1.45, (z0 + z1) / 2), 0.03, z1 - z0, "z", 6, C["steel"]))
        parts.append(mk.cylinder((sgn * 0.5, 1.1, -0.25), 0.03, 0.7, "y", 6, C["steel"]))
        parts.append(mk.cylinder((sgn * 0.5, 1.1, 0.95), 0.03, 0.7, "y", 6, C["steel"]))
    parts.append(mk.cylinder((0, 1.45, 0.35), 0.03, 1.0, "x", 6, C["steel"]))
    for sgn in (-0.25, 0.25):
        parts.append(prof([(-0.1, 0.8), (0.0, 1.15), (0.28, 1.1), (0.34, 0.8)], sgn - 0.18, sgn + 0.18, C["dark"]))
    parts.append(mk.cylinder((0, 0.95, -0.55), 0.17, 0.04, "z", 10, C["dark"]))
    return mk.union(parts)


def ural(cover=False, gun=False):
    """Ural-4320-style 6x6 cargo truck with a long hood; cover adds a canvas tilt, gun a roof-mounted MG."""
    L, W = 7.6, 2.5
    paint = C["olive"]
    parts = []
    tw = W - 0.5
    for z in (-2.55, 0.95, 2.35):
        for sgn in (-1, 1):
            parts += wheel(sgn * tw / 2, 0.58, z, 0.58, 0.42)
    parts.append(mk.box(0, 0.88, 0.0, 1.0, 0.18, 6.8, C["dark"]))  # frame
    # cab: long hood and a boxy cabin
    parts.append(prof([(-3.6, 1.05), (-3.7, 1.5), (-3.3, 1.78), (-2.0, 1.85), (-1.9, 1.05)], -W / 2 + 0.1, W / 2 - 0.1, paint))  # hood
    parts.append(mk.box(0, 1.55, -3.74, 1.4, 0.35, 0.06, C["black"]))
    parts += cabin(-2.0, -0.35, 1.05, 2.9, W - 0.2, 0.22, 0.08, paint, True, 0.14)
    parts.append(mk.box(0, 1.4, -1.2, W - 0.2, 0.7, 1.65, paint))
    for sgn in (-1, 1):
        parts.append(mk.cylinder((sgn * 0.8, 1.45, -3.74), 0.14, 0.08, "z", 10, C["lamp"]))
        parts.append(mk.box(sgn * (W / 2 + 0.1), 2.3, -1.9, 0.14, 0.45, 0.1, C["dark"]))
        parts.append(mk.box(sgn * (W / 2 - 0.2), 1.1, -3.7, 0.5, 0.25, 0.2, C["dark"]))  # bumper ends
    # cargo bed
    parts.append(mk.box(0, 1.2, 2.0, W - 0.1, 0.18, 4.3, C["dark"]))
    for sgn in (-1, 1):
        parts.append(mk.box(sgn * (W / 2 - 0.08), 1.55, 2.0, 0.08, 0.55, 4.3, paint))
    parts.append(mk.box(0, 1.55, 4.15, W - 0.1, 0.55, 0.08, paint))
    parts.append(mk.box(0, 1.55, -0.1, W - 0.1, 0.55, 0.08, paint))
    if cover:
        for k in range(6):  # canvas tilt on bows
            z = 0.1 + k * 0.76
            parts.append(prof([(z, 1.75), (z + 0.1, 2.7), (z + 0.2, 2.95), (z + 0.34, 2.7), (z + 0.44, 1.75)], -W / 2 + 0.1, W / 2 - 0.1, C["camo"]))
        parts.append(prof([(0.1, 1.75), (0.15, 2.6), (4.35, 2.6), (4.4, 1.75)], -W / 2 + 0.12, W / 2 - 0.12, C["camo"]))
        parts.append(prof([(0.1, 2.6), (0.3, 2.95), (4.2, 2.95), (4.4, 2.6)], -W / 2 + 0.3, W / 2 - 0.3, C["camo"]))
    else:
        parts.append(mk.cylinder((0, 1.6, 4.0), 0.4, 0.2, "z", 14, C["rubber"]))  # spare wheel
    if gun:
        parts.append(mk.cylinder((0, 2.95, -0.9), 0.4, 0.12, "y", 14, C["dark"]))
        parts.append(mk.box(0, 3.2, -0.9, 0.35, 0.45, 0.35, C["dark"]))
        parts += barrel(-1.05, 3.4, 1.0, 0.04, C["black"])
    return mk.union(parts)


# ---------------------------------------------------------------------------------------------------------------------------------
# helicopters
# ---------------------------------------------------------------------------------------------------------------------------------

SPIN = []      # parts of the main rotor: kept out of the body so the game can turn them (see build_models.veh)
SPIN_HUB = []  # (x, y, z) of the rotor hub


def rotor(y, radius, blades, color, z=0.0, thick=0.05):
    """The main rotor. Its parts go to SPIN (hub at (0, y, z)) instead of the body; nothing is returned for the body."""
    parts = [mk.cylinder((0, y - 0.18, z), 0.09, 0.4, "y", 8, C["dark"]), mk.cylinder((0, y, z), 0.18, 0.12, "y", 10, C["dark"])]
    for k in range(blades):
        a = k * 360 / blades
        b = mk.box(0, y + 0.03, z - radius / 2 + 0.2, 0.28, thick, radius, color)
        parts.append(_rot_about(b, 0, z, a))
    SPIN.extend(parts)
    SPIN_HUB[:] = [(0.0, y, z)]
    return []


def _rot_about(m, cx, cz, deg):
    a = np.radians(deg)
    c, s = np.cos(a), np.sin(a)
    v = m.verts.copy()
    x, zz = v[:, 0] - cx, v[:, 2] - cz
    v[:, 0] = cx + x * c + zz * s
    v[:, 2] = cz - x * s + zz * c
    return ml.Mesh(v, m.tris, m.mat, m.names, m.colors)


def skids(y, z0, z1, w, color):
    parts = []
    for sgn in (-1, 1):
        parts.append(mk.cylinder((sgn * w / 2, y, (z0 + z1) / 2), 0.045, z1 - z0, "z", 8, color))
        for z in (z0 + 0.35 * (z1 - z0), z0 + 0.7 * (z1 - z0)):
            parts.append(mk.cylinder((sgn * w / 2 * 0.95, y + 0.35, z), 0.04, 0.7, "y", 6, color))
    return parts


def littlebird(variant="mh6"):
    """MH-6 / AH-6 Little Bird: egg-shaped cabin, long thin tail boom, skids and a small rotor. variant: mh6, ah6m (miniguns), ah6r (rocket pods)."""
    body = C["navy"] if variant == "mh6" else C["olive"]
    parts = []
    parts.append(mk.ellipsoid((0, 1.35, -0.4), 0.95, 0.85, 1.55, body, 14, 10))
    parts.append(mk.ellipsoid((0, 1.5, -1.25), 0.78, 0.62, 0.7, C["glass"], 12, 8))   # canopy glass
    parts.append(mk.ellipsoid((0, 1.3, 0.6), 0.8, 0.6, 1.0, body, 12, 8))
    parts.append(mk.cylinder((0, 1.5, 3.0), 0.2, 4.0, "z", 10, body, r2=0.08))       # tail boom
    parts.append(prof([(3.7, 1.5), (4.15, 2.3), (4.35, 2.3), (4.4, 1.5)], -0.04, 0.04, body))  # fin
    parts.append(mk.box(0, 1.62, 4.1, 1.2, 0.05, 0.3, body))                           # horizontal stabiliser
    parts.append(mk.cylinder((0.1, 1.95, 4.2), 0.42, 0.05, "x", 10, C["dark"]))        # tail rotor
    parts += rotor(2.35, 4.2, 4, C["dark"], z=-0.2)
    parts += skids(0.1, -1.4, 1.4, 1.7, C["dark"])
    parts.append(mk.box(0, 0.95, -0.1, 1.2, 0.15, 2.0, C["dark"]))
    if variant == "ah6m":
        for sgn in (-1, 1):
            parts.append(mk.box(sgn * 1.2, 0.95, -0.2, 0.3, 0.3, 0.9, C["dark"]))
            for k in range(3):
                a = k * 2.1
                parts.append(mk.cylinder((sgn * 1.2 + 0.07 * np.cos(a), 0.95 + 0.07 * np.sin(a), -1.1), 0.02, 0.7, "z", 6, C["black"]))
            parts.append(mk.cylinder((sgn * 1.0, 1.15, -0.15), 0.04, 0.8, "x", 6, C["dark"]))
    if variant == "ah6r":
        for sgn in (-1, 1):
            parts.append(mk.cylinder((sgn * 1.25, 1.0, -0.2), 0.22, 1.4, "z", 10, C["dark"]))
            for k in range(7):
                a = k * 2 * np.pi / 7
                parts.append(mk.cylinder((sgn * 1.25 + 0.11 * np.cos(a), 1.0 + 0.11 * np.sin(a), -0.95), 0.035, 0.12, "z", 6, C["red"]))
            parts.append(mk.cylinder((sgn * 1.0, 1.1, -0.2), 0.04, 0.5, "x", 6, C["dark"]))
    return mk.union(parts)


def utility_heli(variant="z20"):
    """UH-60-style utility helicopter (z20, z20 + miniguns) or a Mi-28-style attack helicopter (havoc)."""
    parts = []
    if variant == "havoc":
        body = C["black"]
        parts.append(mk.ellipsoid((0, 1.55, -0.2), 0.8, 0.85, 2.7, body, 14, 10))
        parts.append(mk.ellipsoid((0, 1.75, -2.1), 0.6, 0.5, 0.9, C["glass"], 12, 8))
        parts.append(mk.ellipsoid((0, 1.95, -0.2), 0.62, 0.5, 1.4, C["dark"], 12, 8))
        parts.append(mk.cylinder((0, 1.8, 3.4), 0.3, 4.0, "z", 10, body, r2=0.1))
        parts.append(prof([(4.6, 1.8), (5.1, 2.9), (5.35, 2.9), (5.4, 1.8)], -0.045, 0.045, body))
        parts.append(mk.box(0, 1.95, 5.0, 1.8, 0.05, 0.4, body))
        parts.append(mk.cylinder((0.15, 2.3, 5.25), 0.55, 0.05, "x", 10, C["dark"]))
        parts += rotor(2.95, 6.2, 5, C["dark"], z=0.0)
        for sgn in (-1, 1):  # stub wings with rocket pods, landing gear
            parts.append(mk.box(sgn * 1.45, 1.35, 0.3, 1.6, 0.08, 1.1, C["dark"]))
            parts.append(mk.cylinder((sgn * 1.9, 1.15, 0.3), 0.24, 1.5, "z", 10, C["dark"]))
            parts.append(mk.cylinder((sgn * 0.9, 0.5, -1.2), 0.04, 1.0, "y", 6, C["dark"]))
            parts.append(mk.cylinder((sgn * 0.6, 0.18, 0.9), 0.17, 0.12, "x", 8, C["rubber"]))
        parts.append(mk.cylinder((0, 0.95, -2.55), 0.1, 0.9, "z", 8, C["dark"]))  # chin cannon
        parts.append(mk.cylinder((0, 0.4, -1.4), 0.04, 1.0, "y", 6, C["dark"]))
        parts.append(mk.cylinder((0, 0.1, -1.4), 0.15, 0.12, "x", 8, C["rubber"]))
        return mk.union(parts)
    body = C["olive"] if variant == "z20" else C["olive2"]
    parts.append(mk.ellipsoid((0, 1.75, -0.4), 1.15, 1.05, 3.0, body, 16, 10))
    parts.append(mk.ellipsoid((0, 2.0, -2.5), 0.95, 0.7, 0.75, C["glass"], 12, 8))
    parts.append(mk.box(0, 1.75, 0.6, 2.2, 1.1, 2.3, body))
    parts.append(mk.cylinder((0, 2.1, 4.0), 0.45, 4.2, "z", 12, body, r2=0.15))
    parts.append(prof([(5.3, 2.1), (5.9, 3.5), (6.2, 3.5), (6.1, 2.1)], -0.05, 0.05, body))
    parts.append(mk.box(0, 2.3, 5.9, 1.8, 0.06, 0.4, body))
    parts.append(mk.cylinder((0.18, 3.0, 6.0), 0.65, 0.06, "x", 10, C["dark"]))
    parts += rotor(3.35, 7.5, 4, C["dark"], z=0.0)
    for sgn in (-1, 1):
        parts.append(mk.cylinder((sgn * 1.0, 0.55, -0.9), 0.04, 1.1, "y", 6, C["dark"]))
        parts.append(mk.cylinder((sgn * 1.0, 0.55, 1.4), 0.04, 1.1, "y", 6, C["dark"]))
        parts.append(mk.cylinder((sgn * 1.0, 0.18, -0.9), 0.2, 0.16, "x", 8, C["rubber"]))
        parts.append(mk.cylinder((sgn * 1.0, 0.18, 1.4), 0.2, 0.16, "x", 8, C["rubber"]))
    if variant == "z20_miniguns":
        for sgn in (-1, 1):
            parts.append(mk.cylinder((sgn * 1.55, 1.7, 0.1), 0.03, 1.0, "x", 6, C["dark"]))
            for k in range(4):
                a = k * np.pi / 2
                parts.append(mk.cylinder((sgn * 2.0 + 0.06 * np.cos(a), 1.65 + 0.06 * np.sin(a), -0.7), 0.02, 0.9, "z", 6, C["black"]))
            parts.append(mk.box(sgn * 1.85, 1.65, 0.0, 0.3, 0.3, 0.7, C["dark"]))
    return mk.union(parts)
