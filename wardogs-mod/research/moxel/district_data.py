#!/usr/bin/env python3
"""Turns the researched Bakurani rasters (local-data/bakurani) into compact JavaScript for Moxel's run_script: one strip of the terrain
(heights, surface types), the buildings and the trees of a district. 1 block = 1 m, y = 16 + elevation * 0.5, like the Minecraft mod.

usage: district_data.py <x0> <x1> <z0> <z1>   (writes /tmp/moxel/strip_<i>.js for every 32-row strip, buildings.json, trees.json)
"""
import json, os, re, struct, sys
import numpy as np

D = os.path.join(os.path.dirname(os.path.abspath(__file__)), "../../local-data/bakurani/")
h = np.fromfile(D + "height_2048_u16.bin", dtype="<u2").reshape(2048, 2048).astype(float)
mat = np.fromfile(D + "material_2048_u8.bin", dtype=np.uint8).reshape(2048, 2048)
roads = np.fromfile(D + "roads_clean_4096_u8.bin", dtype=np.uint8).reshape(4096, 4096)
cover = np.fromfile(D + "cover_4096_u8.bin", dtype=np.uint8).reshape(4096, 4096)


def px(x): return (x + 8187) / 0.99902
def py(z): return (8191 + z) / 0.99983


def elev(x, z):
    fx = px(x) / 16384 * 2048 - 0.5
    fz = py(z) / 16384 * 2048 - 0.5
    x0 = np.floor(fx).astype(int); z0 = np.floor(fz).astype(int); tx = fx - x0; tz = fz - z0
    g = lambda a, b: h[np.clip(b, 0, 2047), np.clip(a, 0, 2047)]
    raw = g(x0, z0) * (1 - tx) * (1 - tz) + g(x0 + 1, z0) * tx * (1 - tz) + g(x0, z0 + 1) * (1 - tx) * tz + g(x0 + 1, z0 + 1) * tx * tz
    return 0.25993 * raw


def hashf(a, b, salt):
    v = ((int(a) * 73856093) ^ (int(b) * 19349663) ^ (salt * 83492791)) & 0xFFFFFFFF
    v = ((v ^ (v >> 13)) * 1274126177) & 0xFFFFFFFF
    return ((v ^ (v >> 16)) & 0xFFFF) / 65536.0


TYPES = "0123456789"
STEP = 64   # rows per run_script call
# 0 grass_block 1 podzol 2 coarse_dirt 3 gravel 4 gray_concrete 5 dirt_path 6 water 7 stone 8 andesite 9 moss_block


def build(x0, x1, z0, z1):
    X, Z = np.meshgrid(np.arange(x0, x1), np.arange(z0, z1))
    Y = 16 + np.round(elev(X, Z) * 0.5).astype(int)
    ci = np.clip((py(Z) / 16384 * 4096).astype(int), 0, 4095); cj = np.clip((px(X) / 16384 * 4096).astype(int), 0, 4095)
    cv = cover[ci, cj]
    rd = roads[ci, cj]
    mi = mat[np.clip((py(Z) / 16384 * 2048).astype(int), 0, 2047), np.clip((px(X) / 16384 * 2048).astype(int), 0, 2047)]
    b = open(D + "buildings.bin", "rb").read()
    n = -struct.unpack("<i", b[:4])[0] - 1000000
    recs = np.array([struct.unpack("<ffBBBBh", b[4 + 14 * i:18 + 14 * i]) for i in range(n)])
    sel = recs[(recs[:, 0] > x0 - 10) & (recs[:, 0] < x1 + 10) & (recs[:, 1] > z0 - 10) & (recs[:, 1] < z1 + 10)]
    near = np.zeros(X.shape, bool)
    for r in sel:
        rad = np.hypot(r[2], r[3]) / 2 + 5
        near |= np.hypot(X - r[0], Z - r[1]) <= rad
    T = np.zeros(X.shape, np.uint8)
    for iz in range(X.shape[0]):
        for ix in range(X.shape[1]):
            x, z = X[iz, ix], Z[iz, ix]
            r = hashf(x, z, 7)
            t = 0
            if cv[iz, ix] == 1:
                t = 6
            elif rd[iz, ix] >= 2:
                t = 4
            elif rd[iz, ix] == 1:
                t = 5
            elif cv[iz, ix] == 2:
                t = 1 if r < 0.62 else 0 if r < 0.82 else 2 if r < 0.93 else 9
            elif cv[iz, ix] == 3:
                t = (8 if r < 0.65 else 3 if r < 0.85 else 7) if near[iz, ix] else (0 if r < 0.9 else 2)
            elif mi[iz, ix] == 5:
                t = 7 if r < 0.7 else 8
            else:
                t = 0 if r < 0.92 else 2
            T[iz, ix] = t
    # roads: gravel shoulders where grass touches a road
    road = (T == 4) | (T == 5)
    sh = np.zeros_like(road)
    sh[1:] |= road[:-1]; sh[:-1] |= road[1:]; sh[:, 1:] |= road[:, :-1]; sh[:, :-1] |= road[:, 1:]
    for iz, ix in zip(*np.where(sh & ~road & (T != 6))):
        if hashf(X[iz, ix], Z[iz, ix], 11) < 0.55 and T[iz, ix] in (0, 1, 2):
            T[iz, ix] = 3
    return X, Y, T, sel, cv


def rle_height(body):
    """Letters a..e (delta -2..2) and [abs] jumps; a run of 2..27 equal letters becomes letter + count digits."""
    out, i = [], 0
    toks = re.findall(r"\[\d+\]|.", body)
    while i < len(toks):
        t = toks[i]
        j = i
        while j < len(toks) and toks[j] == t and t[0] != "[":
            j += 1
        n = j - i
        if t[0] == "[" or n == 1:
            out.append(t)
            i += 1
        else:
            out.append(t + str(n))
            i = j
    return "".join(out)


def rle_types(ts):
    """Run of 2..26 equal type digits becomes digit + letter ('a' = 2 ... 'y' = 26)."""
    out, i = [], 0
    while i < len(ts):
        j = i
        while j < len(ts) and ts[j] == ts[i] and j - i < 26:
            j += 1
        n = j - i
        out.append(ts[i] if n == 1 else ts[i] + chr(ord("a") + n - 2))
        i = j
    return "".join(out)


def encode_rows(Y, T):
    """Per row: start height, deltas clamped to -2..2 as letters a..e (jumps in brackets), then the surface types, both run-length coded."""
    rows = []
    for iz in range(Y.shape[0]):
        prev = int(Y[iz, 0])
        body = ""
        for ix in range(1, Y.shape[1]):
            d = int(Y[iz, ix]) - prev
            if abs(d) <= 2:
                body += "abcde"[d + 2]
                prev += d
            else:
                body += "[" + str(int(Y[iz, ix])) + "]"
                prev = int(Y[iz, ix])
        rows.append(str(int(Y[iz, 0])) + ":" + rle_height(body) + "|" + rle_types("".join(TYPES[int(t)] for t in T[iz])))
    return rows


# Moxel run_script helper, saved once on the map (save_as "dd") and then loaded with use:["dd"]
HELPER = r'''
const TOP=['grass_block','podzol','coarse_dirt','gravel','gray_concrete','dirt_path','water','stone','andesite','moss_block'];
function unrleH(b){ let o=[],i=0; while(i<b.length){ const c=b[i]; if(c==='['){ const j=b.indexOf(']',i); if(j<0) break; o.push(b.slice(i,j+1)); i=j+1; continue; } i++; let n=''; while(i<b.length && b[i]>='0' && b[i]<='9'){ n+=b[i]; i++; } const k=n?parseInt(n,10):1; for(let q=0;q<k;q++) o.push(c); } return o; }
function unrleT(s){ let o=[],i=0; while(i<s.length){ const t=s.charCodeAt(i)-48; i++; let k=1; if(i<s.length && s[i]>='a'){ k=s.charCodeAt(i)-95; i++; } for(let q=0;q<k;q++) o.push(t); } return o; }
function strip(X0,Z0,ROWS){
  for (let r=0;r<ROWS.length;r++){
    const [hs,ts]=ROWS[r].split('|'); const ci=hs.indexOf(':'); let prev=parseInt(hs.slice(0,ci),10);
    const hb=unrleH(hs.slice(ci+1)), tt=unrleT(ts), z=Z0+r;
    for (let k=0;k<tt.length;k++){
      if(k>0){ const c=hb[k-1]||'c'; if(c[0]==='[') prev=parseInt(c.slice(1,-1),10); else { const d='abcde'.indexOf(c); prev+= d<0?0:d-2; } }
      if(prev<44) prev=44; if(prev>66) prev=66;
      const yy=prev, t=Math.min(9,Math.max(0,tt[k]||0)), xx=X0+k;
      if(t===6){ set(xx,yy-3,z,'gravel'); fill(xx,yy-2,z,xx,yy,z,'water'); }
      else if(t===7){ fill(xx,yy-3,z,xx,yy-1,z,'stone'); set(xx,yy,z,'stone'); }
      else { fill(xx,yy-3,z,xx,yy-1,z, t===4?'stone':'dirt'); set(xx,yy,z,TOP[t]); }
    }
  }
  return ROWS.length;
}
'''

if __name__ == "__main__":
    x0, x1, z0, z1 = map(int, sys.argv[1:5])
    os.makedirs("/tmp/moxel", exist_ok=True)
    X, Y, T, sel, cv = build(x0, x1, z0, z1)
    Z = np.meshgrid(np.arange(x0, x1), np.arange(z0, z1))[1]
    rows = encode_rows(Y, T)
    open("/tmp/moxel/helper.js", "w").write(HELPER)
    k = 0
    for s in range(0, len(rows), STEP):
        code = "return strip(%d,%d,%s);" % (x0, z0 + s, json.dumps(rows[s:s + STEP]))
        open(f"/tmp/moxel/strip_{k}.js", "w").write(code)
        k += 1
    json.dump([[float(a) for a in r] for r in sel], open("/tmp/moxel/buildings.json", "w"))
    # tree sites on forest cover (sparse), deterministic
    trees = []
    for iz in range(0, X.shape[0], 3):
        for ix in range(0, X.shape[1], 3):
            if cv[iz, ix] == 2 and T[iz, ix] in (0, 1, 2, 9) and hashf(X[iz, ix], Z[iz, ix], 3) < 0.11:
                trees.append([int(X[iz, ix]), int(Y[iz, ix]), int(Z[iz, ix]), int(hashf(X[iz, ix], Z[iz, ix], 5) * 100)])
    json.dump(trees, open("/tmp/moxel/trees.json", "w"))
    print("strips", k, "buildings", len(sel), "trees", len(trees), "y range", int(Y.min()), int(Y.max()))
