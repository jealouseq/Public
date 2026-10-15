'use strict';
/* ==========================================================================
   Engine: math helpers, data loading, perspective camera, per-pixel globe.
   Everything is deterministic: frame = f(t).
   ========================================================================== */
const PI = Math.PI, TAU = PI * 2, D2R = PI / 180;
const clamp = (x, a = 0, b = 1) => (x < a ? a : x > b ? b : x);
const lerp = (a, b, t) => a + (b - a) * t;
const smooth = (a, b, x) => { const t = clamp((x - a) / (b - a)); return t * t * (3 - 2 * t); };
const smoother = (a, b, x) => { const t = clamp((x - a) / (b - a)); return t * t * t * (t * (t * 6 - 15) + 10); };
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeInOutQuint = (t) => (t < 0.5 ? 16 * t * t * t * t * t : 1 - Math.pow(-2 * t + 2, 5) / 2);
const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
const easeOutQuart = (t) => 1 - Math.pow(1 - t, 4);
const easeOutExpo = (t) => (t >= 1 ? 1 : 1 - Math.pow(2, -10 * t));
const easeInCubic = (t) => t * t * t;
const easeOutBack = (t) => { const c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); };
const seg = (t, a, b) => clamp((t - a) / (b - a)); // 0..1 progress of t within [a,b]

// seeded RNG (mulberry32) so every worker produces identical frames
function rng(seed) {
  let a = seed >>> 0;
  return () => {
    a |= 0; a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const llv = (lon, lat) => { const c = Math.cos(lat * D2R); return [c * Math.cos(lon * D2R), c * Math.sin(lon * D2R), Math.sin(lat * D2R)]; };
const dot3 = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const cross3 = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const norm3 = (a) => { const l = Math.hypot(a[0], a[1], a[2]) || 1; return [a[0] / l, a[1] / l, a[2] / l]; };
const sub3 = (a, b) => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const add3 = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const mul3 = (a, s) => [a[0] * s, a[1] * s, a[2] * s];
function slerp3(a, b, t) {
  const d = clamp(dot3(a, b), -1, 1), om = Math.acos(d);
  if (om < 1e-6) return a;
  const s = Math.sin(om);
  return add3(mul3(a, Math.sin((1 - t) * om) / s), mul3(b, Math.sin(t * om) / s));
}

/* ------------------------------------------------------------------ data */
const DATA = { ready: false };
async function loadData() {
  const get = (u) => fetch(u).then((r) => r.arrayBuffer());
  const [meta, vec, lights, ground, dem, edge] = await Promise.all([
    fetch('/data/meta.json').then((r) => r.json()),
    fetch('/data/vectors.json').then((r) => r.json()),
    get('/data/lights.u8'), get('/data/ground.u8'), get('/data/dem.i16'), get('/data/edge.u8'),
  ]);
  DATA.meta = meta; DATA.vec = vec;
  DATA.lightsMips = buildMips(new Uint8Array(lights), meta.night.w, meta.night.h, true);
  DATA.groundMips = buildMips(new Uint8Array(ground), meta.night.w, meta.night.h, false);
  DATA.dem = new Int16Array(dem); DATA.edge = new Uint8Array(edge);
  prepVectors();
  DATA.ready = true;
}
function buildMips(base, W, H, keepPeaks) {
  const lv = [{ a: base, w: W, h: H }];
  for (let l = 1; l < 5; l++) {
    const p = lv[l - 1], w = p.w >> 1, h = p.h >> 1, a = new Uint8Array(w * h);
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const i = 2 * y * p.w + 2 * x;
        const v0 = p.a[i], v1 = p.a[i + 1], v2 = p.a[i + p.w], v3 = p.a[i + p.w + 1];
        const avg = (v0 + v1 + v2 + v3) >> 2;
        a[y * w + x] = keepPeaks ? Math.max(avg, 0.72 * Math.max(v0, v1, v2, v3)) : avg;
      }
    }
    lv.push({ a, w, h });
  }
  return lv;
}
// vectors -> flat unit-vector arrays for fast per-frame projection
function flatten(rings) {
  let n = 0; for (const r of rings) n += r.length;
  const xyz = new Float32Array(n * 3), starts = new Int32Array(rings.length + 1);
  let k = 0;
  rings.forEach((r, i) => {
    starts[i] = k;
    for (const p of r) { const v = llv(p[0], p[1]); xyz[k * 3] = v[0]; xyz[k * 3 + 1] = v[1]; xyz[k * 3 + 2] = v[2]; k++; }
  });
  starts[rings.length] = k;
  return { xyz, starts, count: rings.length };
}
function prepVectors() {
  const v = DATA.vec;
  DATA.coast = flatten(v.coast);
  DATA.rivers = flatten(v.rivers);
  DATA.lakes = flatten(v.lakes);
  DATA.places = v.places.map((p) => ({ lon: p[0], lat: p[1], pop: p[2], xyz: llv(p[0], p[1]) }));
}

/* ----------------------------------------------------------------- camera */
/*
  Orbit-camera: sub-satellite point (lon,lat), altitude `alt` above the surface in Earth radii,
  `pitch` = angle from nadir (0 = looking straight down, 90 = looking at the horizontal),
  `heading` = compass direction the camera looks towards, `roll` in degrees.
*/
function makeCamera(c, W, H) {
  const u = llv(c.lon, c.lat);
  const clon = c.lon * D2R, clat = c.lat * D2R;
  const E = [-Math.sin(clon), Math.cos(clon), 0];
  const N = [-Math.sin(clat) * Math.cos(clon), -Math.sin(clat) * Math.sin(clon), Math.cos(clat)];
  const hd = (c.heading || 0) * D2R, p = (c.pitch || 0) * D2R;
  const h = add3(mul3(N, Math.cos(hd)), mul3(E, Math.sin(hd)));
  const pos = mul3(u, 1 + c.alt);
  const fwd = add3(mul3(u, -Math.cos(p)), mul3(h, Math.sin(p)));
  let up = add3(mul3(u, Math.sin(p)), mul3(h, Math.cos(p)));
  let right = cross3(fwd, up);
  if (c.roll) {
    const cs = Math.cos(c.roll * D2R), sn = Math.sin(c.roll * D2R);
    const r2 = add3(mul3(right, cs), mul3(up, sn));
    const u2 = add3(mul3(up, cs), mul3(right, -sn));
    right = r2; up = u2;
  }
  const f = (W / 2) / Math.tan((c.fov * D2R) / 2);
  return { pos, fwd, right, up, f, cx: W / 2 + (c.shiftX || 0), cy: H / 2 + (c.shiftY || 0), d: 1 + c.alt, W, H };
}
// Find the camera sub-point so that the view axis hits the surface exactly at aim point A.
function solveAim(A, alt, pitch, heading) {
  let lon = A.lon, lat = A.lat;
  for (let i = 0; i < 10; i++) {
    const cam = makeCamera({ lon, lat, alt, pitch, heading, roll: 0, fov: 45 }, 100, 100);
    const b = dot3(cam.pos, cam.fwd), c0 = dot3(cam.pos, cam.pos) - 1, disc = b * b - c0;
    if (disc <= 0) break;
    const t = -b - Math.sqrt(disc), hx = cam.pos[0] + t * cam.fwd[0], hy = cam.pos[1] + t * cam.fwd[1], hz = cam.pos[2] + t * cam.fwd[2];
    const hlon = Math.atan2(hy, hx) / D2R, hlat = Math.asin(clamp(hz, -1, 1)) / D2R;
    let dl = A.lon - hlon; if (dl > 180) dl -= 360; if (dl < -180) dl += 360;
    lon += dl; lat += A.lat - hlat;
  }
  return { lon, lat };
}
// segment camera->P (P lifted) blocked by the planet?
function occluded(cam, P) {
  const vx = P[0] - cam.pos[0], vy = P[1] - cam.pos[1], vz = P[2] - cam.pos[2];
  const a = vx * vx + vy * vy + vz * vz, b = cam.pos[0] * vx + cam.pos[1] * vy + cam.pos[2] * vz;
  const c0 = cam.pos[0] * cam.pos[0] + cam.pos[1] * cam.pos[1] + cam.pos[2] * cam.pos[2] - 1;
  const disc = b * b - a * c0;
  if (disc <= 0) return false;
  const t1 = (-b - Math.sqrt(disc)) / a;
  return t1 > 0 && t1 < 0.9995;
}
// project unit-sphere point (optionally lifted by radius r) -> [x, y, visible]
function project(cam, p, out, r = 1) {
  const x = p[0] * r - cam.pos[0], y = p[1] * r - cam.pos[1], z = p[2] * r - cam.pos[2];
  const zc = x * cam.fwd[0] + y * cam.fwd[1] + z * cam.fwd[2];
  out[0] = cam.cx + (cam.f * (x * cam.right[0] + y * cam.right[1] + z * cam.right[2])) / zc;
  out[1] = cam.cy - (cam.f * (x * cam.up[0] + y * cam.up[1] + z * cam.up[2])) / zc;
  let vis = zc > 0.02;
  if (vis) {
    if (r > 1.0001) vis = !occluded(cam, [p[0] * r, p[1] * r, p[2] * r]);
    else vis = (p[0] * cam.pos[0] + p[1] * cam.pos[1] + p[2] * cam.pos[2]) > 1.0;
  }
  out[2] = vis ? 1 : 0;
  return out;
}

/* ------------------------------------------------------------- the globe */
const SUN = norm3(llv(-35, 22));
let _globeImg = null;

function sampleMip(levels, lod, u01, v01) {
  const l0 = Math.min(levels.length - 1, Math.floor(lod)), l1 = Math.min(levels.length - 1, l0 + 1), t = lod - Math.floor(lod);
  const a = bil(levels[l0], u01, v01);
  if (l1 === l0 || t < 0.02) return a;
  return a + (bil(levels[l1], u01, v01) - a) * t;
}
function bil(L, u01, v01) {
  const w = L.w, h = L.h, a = L.a;
  const u = u01 * w - 0.5, v = clamp(v01 * h - 0.5, 0, h - 1.001);
  let x0 = Math.floor(u), y0 = Math.floor(v);
  const fx = u - x0, fy = v - y0;
  if (x0 < 0) x0 += w; if (x0 >= w) x0 -= w;
  let x1 = x0 + 1; if (x1 >= w) x1 = 0;
  const r0 = y0 * w, r1 = r0 + w;
  const t0 = a[r0 + x0] + (a[r0 + x1] - a[r0 + x0]) * fx;
  const t1 = a[r1 + x0] + (a[r1 + x1] - a[r1 + x0]) * fx;
  return (t0 + (t1 - t0) * fy) * 0.003921569;
}
function demBil(arr, W, H, u, v) {
  if (u < 1 || v < 1 || u > W - 2 || v > H - 2) return null;
  const x0 = Math.floor(u), y0 = Math.floor(v), fx = u - x0, fy = v - y0, i = y0 * W + x0;
  const t0 = arr[i] + (arr[i + 1] - arr[i]) * fx, t1 = arr[i + W] + (arr[i + W + 1] - arr[i + W]) * fx;
  return t0 + (t1 - t0) * fy;
}

/*
  S fields used here:
    cam, sunGlow, zone {c:[x,y,z], R, a}, ripples [{r,a}], sweep {ang, arc, R, a}, reveal {R, a} (deep terrain), tint
*/
function renderGlobe(ctxG, W, H, cam, S) {
  if (!_globeImg || _globeImg.width !== W || _globeImg.height !== H) _globeImg = ctxG.createImageData(W, H);
  const img = _globeImg, buf = new Uint32Array(img.data.buffer);
  buf.fill(0);
  const { pos, fwd, right, up, f, cx, cy, d } = cam;
  const c0 = pos[0] * pos[0] + pos[1] * pos[1] + pos[2] * pos[2] - 1;
  const lm = DATA.lightsMips, gm = DATA.groundMips, demA = DATA.dem, edgeA = DATA.edge, dm = DATA.meta.dem;
  const nW = DATA.meta.night.w;
  // texel footprint (lod) at the surface point nearest the camera
  const angpp = Math.max(1e-6, (d - 1) / f);
  const lodBase = clamp(Math.log2(angpp / (TAU / nW)) + 0.2, 0, 4.0);
  const kmPerPx = Math.max(0.05, (d - 1) * 6371 / f);
  const texPerPx = kmPerPx / 0.7; // dem texels per screen pixel
  const sunV = S.sun || SUN;
  const Lx = sunV[0], Ly = sunV[1], Lz = sunV[2];
  const zone = S.zone, rip = S.ripples || [], sweep = S.sweep, reveal = S.reveal;
  const C = zone ? zone.c : [0, 0, 1];
  // local frame at region centre (for bearing)
  const clon = Math.atan2(C[1], C[0]), clat = Math.asin(C[2]);
  const E = [-Math.sin(clon), Math.cos(clon), 0];
  const N = [-Math.sin(clat) * Math.cos(clon), -Math.sin(clat) * Math.sin(clon), Math.cos(clat)];
  const sunGlow = S.sunGlow === undefined ? 1 : S.sunGlow;
  const tint = S.tint === undefined ? 1 : S.tint;
  const lightsGain = S.lightsGain === undefined ? 1 : S.lightsGain;
  // screen-space bounding box where anything non-trivial can be
  const sphereR = f / Math.sqrt(Math.max(1.0001, d * d - 1));
  const haloR = d > 1.3 ? f * Math.tan(Math.asin(Math.min(0.999, 1.45 / d))) : 1e9;
  const x0 = haloR > 1e8 ? 0 : Math.max(0, Math.floor(cx - haloR * 1.6 - 4)), x1 = haloR > 1e8 ? W : Math.min(W, Math.ceil(cx + haloR * 1.6 + 4));
  const y0 = 0, y1 = H;
  const demOn = reveal && reveal.a > 0.001;
  const dLat = 1 / dm.step, kLon = D2R;
  for (let y = y0; y < y1; y++) {
    const sy = (cy - (y + 0.5)) / f;
    for (let x = x0; x < x1; x++) {
      const sx = (x + 0.5 - cx) / f;
      const dx = fwd[0] + sx * right[0] + sy * up[0];
      const dy = fwd[1] + sx * right[1] + sy * up[1];
      const dz = fwd[2] + sx * right[2] + sy * up[2];
      const a = dx * dx + dy * dy + dz * dz;
      const b = pos[0] * dx + pos[1] * dy + pos[2] * dz;
      const disc = b * b - a * c0;
      let R = 0, G = 0, B = 0, A = 0;
      if (disc > 0) {
        const t = (-b - Math.sqrt(disc)) / a;
        const px = pos[0] + t * dx, py = pos[1] + t * dy, pz = pos[2] + t * dz;
        const invLen = 1 / Math.sqrt(a);
        const ndv = clamp(-(px * dx + py * dy + pz * dz) * invLen, 0.0005, 1);
        const lat = Math.asin(clamp(pz, -1, 1)), lon = Math.atan2(py, px);
        const u01 = (lon + PI) / TAU, v01 = (PI / 2 - lat) / PI;
        const lod = lodBase + clamp(-Math.log2(ndv) * 0.9, 0, 2.5);
        const lights = sampleMip(lm, lod, u01, v01);
        const ground = sampleMip(gm, lod, u01, v01);
        const diff = clamp(px * Lx + py * Ly + pz * Lz, 0, 1);
        const landM = smooth(0.03, 0.30, ground);
        const ice = smooth(0.55, 0.95, ground);
        // ---- base body: ocean / land / ice in deep burgundy-black
        let r = 5 + 9 * diff, g = 4 + 3 * diff, bl = 8 + 8 * diff;
        const lr = 26 + 62 * diff + ice * 60, lg = 9 + 14 * diff + ice * 34, lb = 14 + 20 * diff + ice * 40;
        r += (lr - r) * landM; g += (lg - g) * landM; bl += (lb - bl) * landM;
        // ---- city lights: red-orange to hot white-ish core
        const L = Math.min(1.4, lights * lightsGain * 1.25);
        const L2 = L * L;
        r += 255 * Math.min(1, L * 1.1);
        g += 120 * L + 150 * L2 * L;
        bl += 55 * L2 + 120 * L2 * L2;
        // ---- fresnel rim (limb) in red
        const rim = Math.pow(1 - ndv, 3.0);
        const rimLit = 0.35 + 0.65 * diff;
        r += 255 * rim * 0.85 * rimLit * sunGlow; g += 38 * rim * rimLit * sunGlow; bl += 52 * rim * rimLit * sunGlow;
        // ---- deep terrain (real elevation) revealed around the region
        if (demOn) {
          const lonD = lon / D2R, latD = lat / D2R;
          const du = (lonD - dm.lon0) / dm.step, dv = (dm.lat1 - latD) / dm.step;
          const e0 = demBil(demA, dm.w, dm.h, du, dv);
          if (e0 !== null) {
            const cosA = px * C[0] + py * C[1] + pz * C[2];
            const ang = Math.acos(clamp(cosA, -1, 1));
            const w = reveal.a * (1 - smooth(reveal.R - 0.012, reveal.R + 0.004, ang));
            // soft fade at dem borders
            const edgeW = smooth(0, 420, Math.min(du, dm.w - du, dv, dm.h - dv));
            const wd = w * edgeW;
            if (wd > 0.003 || reveal.a > 0.001) {
              const k = Math.max(1, texPerPx);
              const ex1 = demBil(demA, dm.w, dm.h, du + 1.5, dv), ex0 = demBil(demA, dm.w, dm.h, du - 1.5, dv);
              const ey1 = demBil(demA, dm.w, dm.h, du, dv + 1.5), ey0 = demBil(demA, dm.w, dm.h, du, dv - 1.5);
              let tr = 0, tg = 0, tb = 0;
              if (ex1 !== null && ex0 !== null && ey1 !== null && ey0 !== null) {
                const cl = Math.cos(latD * D2R);
                const dzdx = (ex1 - ex0) / (3 * dm.step * 111320 * cl), dzdy = (ey0 - ey1) / (3 * dm.step * 110574);
                const ex = 7.0; // vertical exaggeration for the gentle steppe relief
                const nx = -dzdx * ex, ny = -dzdy * ex, nzz = 1;
                const inv = 1 / Math.sqrt(nx * nx + ny * ny + nzz * nzz);
                const hs = clamp((nx * -0.55 + ny * 0.55 + nzz * 0.62) * inv, 0, 1); // light from north-west
                if (e0 > 0.3) {
                  const h01 = clamp(e0 / 420, 0, 1);
                  const base = 0.55 + 0.45 * hs;
                  tr = (20 + 56 * h01) * base * 1.1 + 110 * Math.pow(hs, 6) * 0.3;
                  tg = (4 + 9 * h01) * base;
                  tb = (8 + 11 * h01) * base;
                  // contour lines (every 25 m, bold every 100 m)
                  const gradPx = Math.hypot(ex1 - ex0, ey1 - ey0) / 3 * k;
                  if (gradPx > 0.08) {
                    const fe = e0 / 25, dist = Math.abs(fe - Math.round(fe)) * 25;
                    const wpx = Math.max(0.5, 0.75 * gradPx);
                    const ca = clamp(1 - dist / wpx, 0, 1);
                    const major = (Math.round(fe) % 4 === 0) ? 1 : 0.42;
                    tr += 160 * ca * major; tg += 22 * ca * major; tb += 30 * ca * major;
                  }
                } else {
                  tr = 5; tg = 4; tb = 9 + clamp(-e0 / 60, 0, 6);
                }
                const ed = demBilU8(edgeA, dm.w, dm.h, du, dv);
                tr += 255 * ed * 0.95; tg += 70 * ed * 0.9; tb += 78 * ed * 0.9;
              }
              r += (tr - r) * wd; g += (tg - g) * wd; bl += (tb - bl) * wd;
              // bright reveal front
              const fr = Math.exp(-Math.pow((ang - reveal.R) / 0.0035, 2)) * reveal.a * (reveal.front === undefined ? 1 : reveal.front);
              r += 210 * fr; g += 70 * fr; bl += 80 * fr;
            }
          }
        }
        // ---- zone glow, rings, ripples, sweep
        if (zone) {
          const cosA = px * C[0] + py * C[1] + pz * C[2];
          if (cosA > 0.6) {
            const ang = Math.acos(clamp(cosA, -1, 1));
            if (zone.a > 0.001) {
              const inside = 1 - smooth(zone.R * 0.55, zone.R, ang);
              const ring = Math.exp(-Math.pow((ang - zone.R) / (zone.R * 0.022 + 0.0007), 2));
              // dashed ring
              const ex = px * E[0] + py * E[1] + pz * E[2], ny2 = px * N[0] + py * N[1] + pz * N[2];
              const brg = Math.atan2(ex, ny2);
              const dash = 0.55 + 0.45 * Math.sign(Math.sin(brg * 36 + (zone.spin || 0)));
              r += (95 * inside * 0.55 + 255 * ring * dash) * zone.a; g += (6 * inside + 44 * ring * dash) * zone.a; bl += (14 * inside + 60 * ring * dash) * zone.a;
            }
            for (let i = 0; i < rip.length; i++) {
              const q = rip[i], rr = Math.exp(-Math.pow((ang - q.r) / (0.0028 + q.r * 0.014), 2)) * q.a;
              r += 170 * rr; g += 45 * rr; bl += 55 * rr;
            }
            if (sweep && sweep.a > 0.001 && ang < sweep.R) {
              const ex = px * E[0] + py * E[1] + pz * E[2], ny2 = px * N[0] + py * N[1] + pz * N[2];
              let dd = (sweep.ang - Math.atan2(ex, ny2)) % TAU; if (dd < 0) dd += TAU;
              if (dd < sweep.arc) {
                const s = (Math.pow(1 - dd / sweep.arc, 3.0) * 0.36 + (dd < 0.014 ? 0.7 : 0)) * sweep.a * (0.35 + 0.65 * (1 - ang / sweep.R));
                r += 255 * s; g += 45 * s; bl += 55 * s;
              }
            }
          }
        }
        R = r * tint; G = g * tint; B = bl * tint; A = 255;
      } else {
        // ---- atmosphere halo (ray misses the planet)
        const rho2 = (pos[0] * pos[0] + pos[1] * pos[1] + pos[2] * pos[2]) - (b * b) / a;
        if (rho2 < 2.1 && d > 1.05) {
          const rho = Math.sqrt(Math.max(1, rho2));
          const hgt = rho - 1;
          let I = 0.95 * Math.exp(-hgt / 0.012) + 0.34 * Math.exp(-hgt / 0.045) + 0.12 * Math.exp(-hgt / 0.16);
          // brighter on the sun side
          const bx = pos[0] + (-b / a) * dx, by = pos[1] + (-b / a) * dy, bz = pos[2] + (-b / a) * dz;
          const il = 1 / Math.max(1e-6, Math.hypot(bx, by, bz));
          const sd = clamp((bx * Lx + by * Ly + bz * Lz) * il * 0.5 + 0.5, 0, 1);
          I *= (0.25 + 0.95 * sd * sd) * sunGlow;
          if (I > 0.004) {
            A = clamp(I, 0, 1) * 255;
            R = 255; G = 52 + 140 * clamp(I - 0.6, 0, 1); B = 62 + 120 * clamp(I - 0.7, 0, 1);
            R *= tint; G *= tint; B *= tint;
          }
        }
      }
      if (A > 0) {
        R = R > 255 ? 255 : R; G = G > 255 ? 255 : G; B = B > 255 ? 255 : B;
        buf[y * W + x] = (A << 24) | ((B | 0) << 16) | ((G | 0) << 8) | (R | 0);
      }
    }
  }
  ctxG.putImageData(img, 0, 0);
}
function demBilU8(arr, W, H, u, v) {
  const x0 = Math.floor(u), y0 = Math.floor(v), fx = u - x0, fy = v - y0, i = y0 * W + x0;
  const t0 = arr[i] + (arr[i + 1] - arr[i]) * fx, t1 = arr[i + W] + (arr[i + W + 1] - arr[i + W]) * fx;
  return (t0 + (t1 - t0) * fy) * 0.003921569;
}
