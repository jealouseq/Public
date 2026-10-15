'use strict';
/* ==========================================================================
   Scene: camera path, per-frame state, composition of all layers.
   ========================================================================== */
const R0 = { lon: 37.4, lat: 48.1 };           // centre of the search area (eastern Ukraine, neutral: no borders drawn)
const R0v = llv(R0.lon, R0.lat);
const CITIES = [ // destinations of the "connection" arcs (Russian cities: families live everywhere)
  ['Москва', 37.62, 55.75], ['Санкт-Петербург', 30.31, 59.94], ['Казань', 49.11, 55.79], ['Екатеринбург', 60.6, 56.84],
  ['Новосибирск', 82.92, 55.03], ['Красноярск', 92.87, 56.01], ['Иркутск', 104.28, 52.29], ['Хабаровск', 135.08, 48.48],
  ['Владивосток', 131.89, 43.12], ['Краснодар', 38.97, 45.04], ['Ростов-на-Дону', 39.72, 47.23], ['Самара', 50.15, 53.2],
  ['Челябинск', 61.4, 55.16], ['Омск', 73.37, 54.99], ['Калининград', 20.5, 54.71], ['Мурманск', 33.08, 68.97],
  ['Якутск', 129.73, 62.03], ['Воронеж', 39.2, 51.67], ['Нижний Новгород', 44.0, 56.33], ['Волгоград', 44.5, 48.7],
  ['Уфа', 55.97, 54.74], ['Пермь', 56.25, 58.01], ['Тюмень', 65.53, 57.15], ['Архангельск', 40.5, 64.54],
].map((c) => ({ name: c[0], lon: c[1], lat: c[2], v: llv(c[1], c[2]) }));

/* ------------------------------------------------- monotone cubic (PCHIP) */
function pchip(ts, vs) {
  const n = ts.length, h = [], d = [], m = new Array(n);
  for (let i = 0; i < n - 1; i++) { h[i] = ts[i + 1] - ts[i]; d[i] = (vs[i + 1] - vs[i]) / h[i]; }
  if (n === 2) { m[0] = m[1] = d[0]; }
  else {
    for (let i = 1; i < n - 1; i++) {
      if (d[i - 1] * d[i] <= 0) m[i] = 0;
      else { const w1 = 2 * h[i] + h[i - 1], w2 = h[i] + 2 * h[i - 1]; m[i] = (w1 + w2) / (w1 / d[i - 1] + w2 / d[i]); }
    }
    const end = (h0, h1, d0, d1) => { let v = ((2 * h0 + h1) * d0 - h0 * d1) / (h0 + h1); if (Math.sign(v) !== Math.sign(d0)) v = 0; else if (Math.sign(d0) !== Math.sign(d1) && Math.abs(v) > 3 * Math.abs(d0)) v = 3 * d0; return v; };
    m[0] = end(h[0], h[1], d[0], d[1]);
    m[n - 1] = end(h[n - 2], h[n - 3], d[n - 2], d[n - 3]);
  }
  return (t) => {
    if (t <= ts[0]) return vs[0];
    if (t >= ts[n - 1]) return vs[n - 1];
    let i = 0; while (t > ts[i + 1]) i++;
    const x = (t - ts[i]) / h[i], x2 = x * x, x3 = x2 * x;
    return (2 * x3 - 3 * x2 + 1) * vs[i] + (x3 - 2 * x2 + x) * h[i] * m[i] + (-2 * x3 + 3 * x2) * vs[i + 1] + (x3 - x2) * h[i] * m[i + 1];
  };
}

/* ------------------------------------------------------------- camera path */
let CAMPATH = null;
function buildCameraPath(W, H) {
  const vert = H > W * 1.3, U = W / 1080;
  const kS = vert ? 0.3 : 1;
  const endShift = ((H * (vert ? 0.36 : 0.30) - 0.2 * 172 * U) - H / 2) / H;
  const kf = [
    // orbital horizon shot over Europe at night
    { t: 0.0, lon: -16, lat: 41, alt: 0.24, pitch: 63, heading: 76, fov: 50 },
    { t: 2.6, lon: -3, lat: 43, alt: 0.30, pitch: 59, heading: 76, fov: 50 },
    { t: 4.3, lon: 11, lat: 44, alt: 0.66, pitch: 44, heading: 72, fov: 48 },
    // pull up and look straight down at the globe
    { t: 5.9, A: [29, 46], alt: 2.5, pitch: 0, heading: 12, fov: 45, shiftY: -0.075 * kS },
    { t: 7.8, A: [35.5, 47.4], alt: 1.55, pitch: 0, heading: 0, fov: 45, shiftY: -0.035 * kS },
    // dive
    { t: 9.8, A: [R0.lon, R0.lat], alt: 0.80, pitch: 0, heading: 0, fov: 45, shiftY: 0 },
    { t: 11.9, A: [R0.lon, R0.lat], alt: 0.40, pitch: 0, heading: 0, fov: 45 },
    { t: 13.8, A: [R0.lon, R0.lat], alt: 0.17, pitch: 0, heading: 0, fov: 45 },
    // tactical map: slow tilt + orbit to show relief
    { t: 16.2, A: [R0.lon, R0.lat], alt: 0.142, pitch: 14, heading: 18, fov: 45 },
    { t: 19.4, A: [R0.lon, R0.lat], alt: 0.128, pitch: 28, heading: 58, fov: 45 },
    { t: 20.2, A: [R0.lon, R0.lat], alt: 0.130, pitch: 29, heading: 64, fov: 45 },
    // pull back over Russia
    { t: 22.0, A: [47, 52], alt: 0.95, pitch: 12, heading: 34, fov: 45, shiftY: -0.03 * kS },
    { t: 23.9, A: [62, 56.5], alt: 2.7, pitch: 0, heading: 0, fov: 45, shiftY: -0.12 * kS },
    { t: 25.2, A: [64, 56.5], alt: 4.4, pitch: 0, heading: 0, fov: 45, shiftY: endShift * 0.9 },
    { t: 27.0, A: [64, 56.5], alt: 16, pitch: 0, heading: 0, fov: 45, shiftY: endShift },
    { t: 32.0, A: [64, 56.5], alt: 40, pitch: 0, heading: 0, fov: 45, shiftY: endShift },
  ].map((k) => {
    let o = k;
    if (k.A) { const s = solveAim({ lon: k.A[0], lat: k.A[1] }, k.alt, k.pitch, k.heading); o = { ...k, lon: s.lon, lat: s.lat }; }
    return o;
  });
  const T = kf.map((k) => k.t);
  const comp = (name, f = (x) => x, def = 0) => pchip(T, kf.map((k) => f(k[name] === undefined ? def : k[name])));
  CAMPATH = {
    lon: comp('lon'), lat: comp('lat'), alt: comp('alt', Math.log), pitch: comp('pitch'), heading: comp('heading'),
    roll: comp('roll'), fov: comp('fov', (x) => x, 45), shiftY: comp('shiftY'), shiftX: comp('shiftX'),
  };
}
function cameraAt(t, W, H) {
  const p = CAMPATH;
  return {
    lon: p.lon(t), lat: p.lat(t), alt: Math.exp(p.alt(t)), pitch: p.pitch(t), heading: p.heading(t),
    roll: p.roll(t), fov: p.fov(t), shiftY: p.shiftY(t) * H, shiftX: p.shiftX(t) * W,
  };
}

/* ------------------------------------------------------------ sun & flare */
const SUN_WORLD = norm3(llv(62, 18));
function sunAt(t, c) {
  const w = 1 - smooth(2.4, 5.0, t);
  if (w <= 0) return SUN_WORLD;
  const u = llv(c.lon, c.lat), clon = c.lon * D2R, clat = c.lat * D2R;
  const E = [-Math.sin(clon), Math.cos(clon), 0], N = [-Math.sin(clat) * Math.cos(clon), -Math.sin(clat) * Math.sin(clon), Math.cos(clat)];
  const hd = (c.heading + 9) * D2R, el = (-33.2 + 2.2 * Math.sin(t * 0.55)) * D2R;
  const h = add3(mul3(N, Math.cos(hd)), mul3(E, Math.sin(hd)));
  const open = norm3(add3(mul3(u, Math.sin(el)), mul3(h, Math.cos(el))));
  return norm3(add3(mul3(open, w), mul3(SUN_WORLD, 1 - w)));
}
function drawFlare(ctx, cam, sun, a, t) {
  if (a <= 0.003) return;
  const sz = dot3(sun, cam.fwd);
  if (sz < 0.15) return;
  const b = dot3(cam.pos, sun), rho2 = dot3(cam.pos, cam.pos) - b * b, rho = Math.sqrt(Math.max(0, rho2));
  let vis = 1;
  if (b < 0) vis = smooth(0.99, 1.012, rho);   // sun behind the planet: fades as it dips behind the limb
  if (vis <= 0.003) return;
  const x = cam.cx + (cam.f * dot3(sun, cam.right)) / sz, y = cam.cy - (cam.f * dot3(sun, cam.up)) / sz;
  const W = cam.W, k = a * vis;
  ctx.save(); ctx.globalCompositeOperation = 'lighter';
  let g = ctx.createRadialGradient(x, y, 0, x, y, W * 0.34);
  g.addColorStop(0, `rgba(255,120,110,${0.5 * k})`); g.addColorStop(0.25, `rgba(255,40,56,${0.22 * k})`); g.addColorStop(1, 'rgba(255,20,40,0)');
  ctx.fillStyle = g; ctx.fillRect(0, 0, W, cam.H);
  g = ctx.createRadialGradient(x, y, 0, x, y, W * 0.05);
  g.addColorStop(0, `rgba(255,245,240,${0.95 * k})`); g.addColorStop(0.35, `rgba(255,120,120,${0.6 * k})`); g.addColorStop(1, 'rgba(255,40,60,0)');
  ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, W * 0.05, 0, TAU); ctx.fill();
  // anamorphic streak
  const sl = W * (0.62 + 0.04 * Math.sin(t * 1.3));
  g = ctx.createLinearGradient(x - sl, y, x + sl, y);
  g.addColorStop(0, 'rgba(255,30,50,0)'); g.addColorStop(0.5, `rgba(255,150,150,${0.55 * k})`); g.addColorStop(1, 'rgba(255,30,50,0)');
  ctx.fillStyle = g; ctx.fillRect(x - sl, y - 2.2, sl * 2, 4.4);
  g = ctx.createLinearGradient(x - sl * 0.5, y, x + sl * 0.5, y);
  g.addColorStop(0, 'rgba(255,30,50,0)'); g.addColorStop(0.5, `rgba(255,60,80,${0.28 * k})`); g.addColorStop(1, 'rgba(255,30,50,0)');
  ctx.fillStyle = g; ctx.fillRect(x - sl * 0.5, y - 14, sl, 28);
  // ghosts along the axis through the frame centre
  const gx = cam.W / 2 - x, gy = cam.H / 2 - y;
  for (const [s, r, al] of [[0.55, 26, 0.18], [1.0, 60, 0.10], [1.45, 18, 0.2], [2.0, 90, 0.07]]) {
    const px = x + gx * s, py = y + gy * s, gr = ctx.createRadialGradient(px, py, 0, px, py, r);
    gr.addColorStop(0, `rgba(255,70,80,${al * k})`); gr.addColorStop(1, 'rgba(255,40,50,0)');
    ctx.fillStyle = gr; ctx.beginPath(); ctx.arc(px, py, r, 0, TAU); ctx.fill();
  }
  ctx.restore();
}

/* ---------------------------------------------------------- search points */
let SPTS = null;
function initSearchPoints() {
  const r = rng(2024); SPTS = [];
  for (let i = 0; i < 22; i++) {
    const a = r() * TAU, rad = 0.35 + Math.sqrt(r()) * 2.6; // degrees from centre
    const lon = R0.lon + Math.cos(a) * rad * 1.45, lat = R0.lat + Math.sin(a) * rad;
    SPTS.push({ lon, lat, v: llv(lon, lat), t0: 13.2 + r() * 4.6, lock: i % 7 === 3 });
  }
  SPTS.sort((p, q) => p.t0 - q.t0);
}

/* -------------------------------------------------------------- state */
const RIPS = [
  { t: 6.3, dur: 2.4, rmax: 0.17, a: 0.8 }, { t: 7.7, dur: 2.4, rmax: 0.15, a: 0.5 },
  ...TL.pings.map((p) => ({ t: p, dur: 2.4, rmax: 0.2, a: 0.8 })),
  { t: 20.9, dur: 2.4, rmax: 0.15, a: 0.6 },
];
function ripples(t) {
  const out = [];
  for (const e of RIPS) {
    const k = (t - e.t) / e.dur;
    if (k > 0 && k < 1) out.push({ r: 0.006 + e.rmax * easeOutCubic(k), a: (1 - k) * e.a });
  }
  return out;
}
function shake(t, t0, amp, dur) {
  const k = t - t0; if (k < 0 || k > dur) return [0, 0];
  const e = Math.exp(-k / (dur * 0.28));
  return [Math.sin(k * 63) * amp * e, Math.cos(k * 49 + 1) * amp * e];
}
function sceneState(t, W, H) {
  const c = cameraAt(t, W, H);
  const S = { cam: c };
  S.sun = sunAt(t, c);
  S.tint = (0.62 + 0.38 * smooth(0.0, 1.2, t)) * (1 - smooth(TL.logoIn + 0.8, TL.logoIn + 2.8, t));
  // search zone ring on the globe (before the dive)
  const zOn = smooth(TL.zoneOn, TL.zoneOn + 1.1, t) * (1 - smooth(TL.revealStart - 0.9, TL.revealStart + 0.1, t));
  const zBack = smooth(TL.arcStart - 0.4, TL.arcStart + 0.8, t) * (1 - smooth(TL.logoIn - 0.4, TL.logoIn + 0.8, t));
  S.zone = { c: R0v, R: 0.074 + 0.004 * Math.sin(t * 2.0), a: Math.max(zOn, zBack * 0.8), spin: t * 0.5 };
  // terrain reveal around the region
  const rv = easeOutQuart(seg(t, TL.revealStart, TL.revealEnd));
  const shrink = 1 - 0.78 * smooth(TL.deepEnd - 0.2, TL.deepEnd + 2.6, t);          // terrain closes back into the zone ring
  const keep = 1 - smooth(1.3, 2.5, c.alt);
  const closing = 0.45 * smooth(TL.deepEnd - 0.2, TL.deepEnd + 0.6, t) * (1 - smooth(TL.deepEnd + 2.4, TL.deepEnd + 3.2, t));
  S.reveal = { R: (0.012 + 0.34 * rv) * shrink, a: t >= TL.revealStart ? Math.max(0, keep) : 0, front: ((1 - rv * rv) * (t < TL.revealEnd ? 1 : 0)) + closing };
  S.ripples = ripples(t);
  const k1 = shake(t, TL.cues.impact, 15, 0.55), k2 = shake(t, TL.cues.logo, 8, 0.45);
  c.shiftX += (k1[0] + k2[0]) * (W / 1080); c.shiftY += (k1[1] + k2[1]) * (W / 1080);
  const sw = smooth(13.6, 14.4, t) * (1 - smooth(19.0, 20.0, t));
  S.sweep = { ang: (t - 13.6) * 1.55, arc: 1.25, R: 0.19, a: sw };
  S.sunGlow = 1;
  S.lightsGain = 1 + 0.35 * smooth(7, 10, t);
  return S;
}

/* --------------------------------------------------------- text windows */
const WIN = TL.text;
function scrimAlpha(t) {
  let a = 0;
  for (const k of Object.keys(WIN)) { const [i, o] = WIN[k]; a = Math.max(a, smooth(i - 0.2, i + 0.6, t) * (1 - smooth(o - 0.2, o + 0.5, t))); }
  a = Math.max(a, smooth(TL.logoIn + 1.2, TL.logoIn + 2.4, t) * 0.8);
  return a;
}

/* ------------------------------------------------------------ the frame */
function drawScene(ctx, gctx, W, H, t, frame) {
  const U = W / 1080;
  const S = sceneState(t, W, H);
  const cam = makeCamera(S.cam, W, H);
  // background
  ctx.globalCompositeOperation = 'source-over';
  ctx.fillStyle = '#040305'; ctx.fillRect(0, 0, W, H);
  const hz = ctx.createRadialGradient(cam.cx, cam.cy, 0, cam.cx, cam.cy, W * 0.9);
  hz.addColorStop(0, `rgba(70,6,14,${0.30 * S.tint})`); hz.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = hz; ctx.fillRect(0, 0, W, H);
  const starA = (0.5 + 0.5 * smooth(0, 0.8, t)) * (1 - smooth(22.5, 24.5, t) * 0.0) * (t < 20 ? 1 : 1);
  drawStars(ctx, cam, t, starA * (1 - smooth(0.2, 0.9, S.reveal.a * smooth(0.0, 0.2, 1 - S.cam.alt)) * 0));
  // ---- globe
  const globeA = 1 - smooth(TL.logoIn + 0.5, TL.logoIn + 2.1, t);
  if (globeA > 0.002 && S.tint > 0.002) {
    renderGlobe(gctx, W, H, cam, S);
    ctx.save(); ctx.globalAlpha = globeA; ctx.drawImage(gctx.canvas, 0, 0); ctx.restore();
  }
  // ---- vector overlays (additive)
  ctx.save(); ctx.globalCompositeOperation = 'lighter';
  const far = cam.d > 2.0;
  // coastline glow while zoomed out
  const coastA = (1 - smooth(1.12, 1.5, cam.d)) * 0 + smooth(0.2, 1.2, t) * (1 - smooth(1.15, 1.55, cam.d)) + 0;
  const coastAlpha = smooth(0.3, 1.5, t) * (1 - smooth(1.35, 1.75, cam.d)) * 0.75;
  if (coastAlpha > 0.01) strokeSet(ctx, cam, DATA.coast, { lw: 1.1 * U, color: `rgba(255,66,82,${coastAlpha})`, skip: cam.d > 3 ? 3 : cam.d > 1.8 ? 2 : 1 });
  // graticule
  const grA = smooth(0.8, 2.0, t) * (1 - smooth(1.18, 1.6, cam.d)) * 0.16;
  if (grA > 0.005) drawGraticule(ctx, cam, 10, grA, null, 1 * U);
  const gz = smooth(10.5, 12.5, t) * (1 - smooth(20.4, 21.6, t));
  if (gz > 0.01) {
    const b = { lon0: R0.lon - 9, lon1: R0.lon + 9, lat0: R0.lat - 6, lat1: R0.lat + 6 };
    drawGraticule(ctx, cam, 1, 0.2 * gz, b, 1 * U);
    drawGraticule(ctx, cam, 0.25, 0.05 * gz, { lon0: R0.lon - 2.5, lon1: R0.lon + 2.5, lat0: R0.lat - 1.7, lat1: R0.lat + 1.7 }, 1 * U);
  }
  // rivers, lakes, cities inside the revealed zone
  if (S.reveal.a > 0.01) {
    const cull = { c: R0v, cos: Math.cos(Math.min(1.2, S.reveal.R)) };
    const ra = S.reveal.a * 0.9;
    strokeSet(ctx, cam, DATA.rivers, { lw: 1.5 * U, color: `rgba(255,96,86,${0.62 * ra})`, cull });
    strokeSet(ctx, cam, DATA.lakes, { lw: 1.3 * U, color: `rgba(255,86,80,${0.5 * ra})`, cull });
    const tp = [0, 0, 0];
    for (const p of DATA.places) {
      if (p.pop < 90000) continue;
      const c = dot3(p.xyz, R0v); if (c < cull.cos) continue;
      project(cam, p.xyz, tp, 1); if (!tp[2] || tp[0] < -30 || tp[1] < -30 || tp[0] > W + 30 || tp[1] > H + 30) continue;
      const sz = (5 + Math.log10(p.pop / 90000) * 11) * U * clamp(0.5 + 1 / (cam.d - 0.95) * 0.12, 0.6, 2.2) * 0.6;
      const pulse = 0.75 + 0.25 * Math.sin(t * 2.2 + p.lon * 3);
      glowDot(ctx, tp[0], tp[1], sz * 3.2, RED, 0.85 * ra * pulse);
    }
  }
  // ---- search points
  if (t > 13 && t < 20.6) drawSearchPoints(ctx, cam, t, W, H, U);
  // ---- arcs to cities
  if (t > TL.arcStart - 0.2 && t < TL.logoIn + 2.2) drawArcs(ctx, cam, t, U);
  // region marker reticle on globe
  if (S.zone.a > 0.02 && t < TL.revealStart) {
    const tp = project(cam, R0v, [0, 0, 0], 1);
    if (tp[2]) reticle(ctx, tp[0], tp[1], 26 * U * (1 + 0.04 * Math.sin(t * 5)), t, S.zone.a * 0.95, 0);
  }
  ctx.restore();
  // ---- sun flare (opening only)
  drawFlare(ctx, cam, S.sun, (0.62 + 0.38 * smooth(0.0, 1.0, t)) * (1 - smooth(3.3, 5.2, t)), t);
  // ---- bloom
  bloomPass(ctx, W, H, 0.55 - 0.2 * smooth(11.5, 13, t) * (1 - smooth(20.5, 22, t)), 5);
  // ---- scrim for text legibility
  const sa = scrimAlpha(t);
  if (sa > 0.01) {
    const g = ctx.createLinearGradient(0, H * 0.52, 0, H);
    g.addColorStop(0, 'rgba(2,1,3,0)'); g.addColorStop(0.55, `rgba(2,1,3,${0.62 * sa})`); g.addColorStop(1, `rgba(2,1,3,${0.9 * sa})`);
    ctx.fillStyle = g; ctx.fillRect(0, H * 0.52, W, H * 0.48);
  }
  vignette(ctx, W, H, 0.55);
  drawHUD(ctx, cam, t, W, H, U);
  drawTexts(ctx, t, W, H, U);
  drawLogoBlock(ctx, t, W, H, U, cam);
  grain(ctx, W, H, frame, 0.07);
  // fade in / out
  const fade = 1 - smooth(TL.total - 0.6, TL.total, t);
  const fin = 1; // first frame is the in-feed poster: start on the picture, no fade from black
  const k = Math.min(fade, 0.0001 + fin);
  if (k < 1) { ctx.fillStyle = `rgba(0,0,0,${1 - k})`; ctx.fillRect(0, 0, W, H); }
}

function drawSearchPoints(ctx, cam, t, W, H, U) {
  const tp = [0, 0, 0], tc = project(cam, R0v, [0, 0, 0], 1);
  const gate = smooth(13.0, 13.8, t) * (1 - smooth(19.6, 20.5, t));
  for (const p of SPTS) {
    const k = seg(t, p.t0, p.t0 + 0.9); if (k <= 0) continue;
    project(cam, p.v, tp, 1); if (!tp[2]) continue;
    // link line from the centre
    const lk = easeOutCubic(seg(t, p.t0 + 0.3, p.t0 + 1.5));
    if (lk > 0 && tc[2]) {
      ctx.strokeStyle = `rgba(255,70,84,${0.4 * gate})`; ctx.lineWidth = 1.2 * U;
      ctx.beginPath(); ctx.moveTo(tc[0], tc[1]); ctx.lineTo(tc[0] + (tp[0] - tc[0]) * lk, tc[1] + (tp[1] - tc[1]) * lk); ctx.stroke();
    }
    const ping = seg(t, p.t0, p.t0 + 1.3);
    ctx.strokeStyle = `rgba(255,90,100,${(1 - ping) * 0.9 * gate})`; ctx.lineWidth = 1.6 * U;
    ctx.beginPath(); ctx.arc(tp[0], tp[1], (6 + ping * 34) * U, 0, TAU); ctx.stroke();
    glowDot(ctx, tp[0], tp[1], 15 * U, RED, k * gate);
    if (p.lock && t > 17.4) {
      const lq = easeOutBack(seg(t, 17.4 + (p.t0 - 13) * 0.05, 18.1 + (p.t0 - 13) * 0.05)), s = (20 - 8 * lq) * U;
      ctx.strokeStyle = `rgba(255,235,235,${0.95 * gate * clamp(lq * 2)})`; ctx.lineWidth = 2 * U;
      for (const [sx, sy] of [[-1, -1], [1, -1], [-1, 1], [1, 1]]) {
        ctx.beginPath(); ctx.moveTo(tp[0] + sx * s, tp[1] + sy * (s - 9 * U)); ctx.lineTo(tp[0] + sx * s, tp[1] + sy * s); ctx.lineTo(tp[0] + sx * (s - 9 * U), tp[1] + sy * s); ctx.stroke();
      }
    }
  }
  // centre reticle
  if (tc[2]) reticle(ctx, tc[0], tc[1], 34 * U, t, gate * 0.9, 0);
}
function drawArcs(ctx, cam, t, U) {
  const fadeOut = 1 - smooth(TL.logoIn + 0.5, TL.logoIn + 2.2, t);
  CITIES.forEach((c, i) => {
    const st = TL.arcStart + i * 0.1, prog = easeInOut(seg(t, st, st + 1.9));
    if (prog <= 0) return;
    const head = drawArc(ctx, cam, R0v, c.v, prog, 0.1, RED_HI, 2.1 * U * (cam.d > 6 ? 0.6 : 1), 0);
    ctx.globalAlpha = 1;
    if (head && prog < 1) glowDot(ctx, head[0], head[1], 15 * U, RED, 0.95 * fadeOut);
    if (prog >= 0.98) {
      const q = project(cam, c.v, [0, 0, 0], 1);
      if (q[2]) {
        const k = seg(t, st + 1.85, st + 2.9);
        glowDot(ctx, q[0], q[1], 10 * U, RED, 0.9 * fadeOut);
        ctx.strokeStyle = `rgba(255,90,100,${(1 - k) * 0.8 * fadeOut})`; ctx.lineWidth = 1.5 * U;
        ctx.beginPath(); ctx.arc(q[0], q[1], (5 + k * 26) * U, 0, TAU); ctx.stroke();
      }
    }
  });
}

/* ------------------------------------------------------------ HUD overlay */
function drawHUD(ctx, cam, t, W, H, U) {
  const a = smooth(TL.zoneOn + 0.2, TL.zoneOn + 1.2, t) * (1 - smooth(TL.logoIn - 0.6, TL.logoIn + 0.4, t));
  if (a <= 0.01) return;
  cornerBrackets(ctx, W, H, 36 * U, 46 * U, a * 0.85, 2.2 * U);
  const blink = 0.55 + 0.45 * Math.sin(t * 6);
  ctx.save(); ctx.globalAlpha = a;
  ctx.fillStyle = `rgba(255,50,66,${blink})`; ctx.beginPath(); ctx.arc(78 * U, 86 * U, 6 * U, 0, TAU); ctx.fill();
  drawTextAt(ctx, 'ЗОНА СВО', 98 * U, 95 * U, { size: 28 * U, weight: 800, family: 'Montserrat', tracking: 5 * U, color: '#fff', glow: 'rgba(255,30,50,0.7)', glowR: 14 });
  drawTextAt(ctx, `${R0.lat.toFixed(2)}° с.ш.  ${R0.lon.toFixed(2)}° в.д.`, 78 * U, 128 * U, { size: 21 * U, weight: 500, family: '"JetBrains Mono"', tracking: 1 * U, color: 'rgba(255,170,175,0.9)' });
  drawTextAt(ctx, 'ПОИСК ВЕДЁТСЯ', W - 78 * U, 95 * U, { size: 24 * U, weight: 700, family: 'Montserrat', tracking: 4 * U, color: '#ffd9dc', align: 'right', glow: 'rgba(255,30,50,0.6)', glowR: 12 });
  const dots = '●'.repeat(1 + (Math.floor(t * 2.5) % 3));
  drawTextAt(ctx, dots, W - 78 * U, 128 * U, { size: 16 * U, weight: 500, family: 'Manrope', tracking: 6 * U, color: 'rgba(255,60,76,0.95)', align: 'right' });
  // scale bar in the tactical map
  const sb = smooth(13.4, 14.4, t) * (1 - smooth(19.6, 20.4, t));
  if (sb > 0.01) {
    const kmpp = ((cam.d - 1) * 6371) / cam.f * 1.0; // km per pixel at the aim point
    const km = kmpp < 0.9 ? 50 : 100, len = km / kmpp;
    ctx.globalAlpha = a * sb; ctx.strokeStyle = 'rgba(255,200,205,0.9)'; ctx.lineWidth = 2 * U;
    const x0 = 64 * U, y0 = H - 66 * U;
    ctx.beginPath(); ctx.moveTo(x0, y0 - 8 * U); ctx.lineTo(x0, y0); ctx.lineTo(x0 + len, y0); ctx.lineTo(x0 + len, y0 - 8 * U); ctx.stroke();
    drawTextAt(ctx, `${km} КМ`, x0 + len + 14 * U, y0 + 6 * U, { size: 20 * U, weight: 500, family: '"JetBrains Mono"', color: 'rgba(255,200,205,0.95)', alpha: a * sb });
  }
  ctx.restore();
}

/* ------------------------------------------------------------------ texts */
function drawTexts(ctx, t, W, H, U) {
  const cx = W / 2, base = H * (H > W * 1.3 ? 0.87 : 0.84);
  const T = TL.text;
  drawWords(ctx, t, { lines: [{ text: 'Когда нет вестей от бойца…' }], x: cx, y: base - 22 * U, align: 'center', size: 54 * U, weight: 700, t0: T.a1[0], per: 0.16, dur: 0.8, tOut: T.a1[1], outDur: 0.6 });
  drawWords(ctx, t, { lines: [{ text: 'самое трудное — *неизвестность*' }], x: cx, y: base + 50 * U, align: 'center', size: 50 * U, weight: 700, t0: T.a2[0], per: 0.16, dur: 0.8, tOut: T.a2[1], outDur: 0.6 });
  drawWords(ctx, t, { lines: [{ text: 'Профессиональный розыск', size: 56 * U }, { text: '*в зоне СВО*', size: 88 * U }], x: cx, y: base - 52 * U, align: 'center', size: 62 * U, weight: 800, lh: 1.16, t0: T.b1[0], per: 0.18, dur: 0.8, tOut: T.b1[1], outDur: 0.6, tracking: 0.5 * U });
  drawWords(ctx, t, { lines: [{ text: 'Находим *ответы* там,' }, { text: 'где другие опускают руки' }], x: cx, y: base - 36 * U, align: 'center', size: 60 * U, weight: 800, lh: 1.2, t0: T.c1[0], per: 0.16, dur: 0.8, tOut: T.c1[1], outDur: 0.6 });
  drawWords(ctx, t, { lines: [{ text: '*Всегда на связи*', size: 78 * U, weight: 800 }, { text: 'Вежливо и бережно к каждой семье', size: 44 * U, weight: 600, family: 'Manrope' }], x: cx, y: base - 30 * U, align: 'center', size: 70 * U, weight: 800, lh: 1.3, t0: T.d1[0], per: 0.2, dur: 0.8, tOut: T.d1[1], outDur: 0.5 });
}

/* ------------------------------------------------------- logo + end card */
function drawLogoBlock(ctx, t, W, H, U, cam) {
  const t0 = TL.logoIn;
  if (t < t0 - 0.2) return;
  const cx = W / 2, R = 172 * U, cy = H * (H > W * 1.3 ? 0.36 : 0.30);
  // converging rings: light gathers into the emblem
  const k = seg(t, t0, t0 + 1.2);
  if (k > 0 && k < 1) {
    ctx.save(); ctx.globalCompositeOperation = 'lighter';
    for (let i = 0; i < 3; i++) {
      const kk = clamp(k * 1.2 - i * 0.12), rr = R * (2.6 - 1.6 * easeOutCubic(kk));
      ctx.strokeStyle = `rgba(255,70,84,${(1 - kk) * 0.7 * kk * 3})`; ctx.lineWidth = 2 * U;
      ctx.beginPath(); ctx.arc(cx, cy - R * 0.2, rr, 0, TAU); ctx.stroke();
    }
    ctx.restore();
  }
  // flash when the emblem locks
  const fl = Math.exp(-Math.pow((t - (t0 + 1.15)) / 0.12, 2));
  if (fl > 0.01) {
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 2.2);
    g.addColorStop(0, `rgba(255,120,120,${0.55 * fl})`); g.addColorStop(1, 'rgba(255,30,40,0)');
    ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); ctx.restore();
  }
  const breathe = 1 + 0.012 * Math.sin(t * 1.6);
  ctx.save(); ctx.translate(cx, cy); ctx.scale(breathe, breathe); ctx.translate(-cx, -cy);
  drawEmblem(ctx, cx, cy, R, t, {
    a: smooth(t0 + 0.3, t0 + 1.0, t), disc: smooth(t0 + 0.8, t0 + 1.9, t),
    ring: easeOutCubic(seg(t, t0 + 0.25, t0 + 1.6)), cross: seg(t, t0 + 0.7, t0 + 1.7), text: seg(t, t0 + 1.1, t0 + 1.8), spin: Math.sin(t * 0.9) * 0.05,
  });
  ctx.restore();
  // slow sonar pings from the emblem
  if (t > t0 + 1.9) {
    ctx.save(); ctx.globalCompositeOperation = 'lighter';
    for (let i = 0; i < 3; i++) {
      const ph = (((t - (t0 + 1.9) - i * 0.95) % 2.85) + 2.85) % 2.85 / 2.85;
      ctx.strokeStyle = `rgba(255,60,76,${(1 - ph) * 0.38 * smooth(t0 + 1.9, t0 + 2.6, t)})`; ctx.lineWidth = 2 * U;
      ctx.beginPath(); ctx.arc(cx, cy, R * (1.02 + 0.85 * easeOutCubic(ph)), 0, TAU); ctx.stroke();
    }
    ctx.restore();
  }
  // wordmark
  drawWords(ctx, t, { lines: [{ text: 'ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ' }], x: cx, y: cy + R + 84 * U, align: 'center', size: 47 * U, weight: 800, tracking: 3.2 * U, t0: TL.wordmark, per: 0.13, dur: 0.8, shadow: true });
  drawWords(ctx, t, { lines: [{ text: '*Профессиональный розыск в зоне СВО*' }], x: cx, y: cy + R + 142 * U, align: 'center', size: 30 * U, weight: 700, family: 'Manrope', tracking: 0.6 * U, t0: TL.tagline, per: 0.07, dur: 0.7, blur: 6 });
  // thin separator
  const sp = easeOutCubic(seg(t, TL.cta - 0.3, TL.cta + 0.6));
  if (sp > 0) { ctx.save(); ctx.globalAlpha = sp; ctx.strokeStyle = 'rgba(255,60,76,0.8)'; ctx.lineWidth = 2 * U; ctx.beginPath(); ctx.moveTo(cx - 90 * U * sp, cy + R + 188 * U); ctx.lineTo(cx + 90 * U * sp, cy + R + 188 * U); ctx.stroke(); ctx.restore(); }
  drawWords(ctx, t, { lines: [{ text: 'Напишите нам — мы ответим' }, { text: 'и подскажем, с чего начать' }], x: cx, y: cy + R + 246 * U, align: 'center', size: 40 * U, weight: 700, family: 'Manrope', lh: 1.28, t0: TL.cta, per: 0.1, dur: 0.7 });
  // contact hint with animated arrow
  const hk = easeOutCubic(seg(t, TL.cta + 1.5, TL.cta + 2.3));
  if (hk > 0) {
    const yy = cy + R + 408 * U;
    drawTextAt(ctx, 'Контакты — в описании канала', cx, yy, { size: 31 * U, weight: 700, family: 'Manrope', color: '#ff3346', align: 'center', alpha: hk, glow: 'rgba(255,30,50,0.55)', glowR: 16 });
    const bob = Math.sin(t * 4) * 5 * U;
    ctx.save(); ctx.globalAlpha = hk; ctx.strokeStyle = '#ff3346'; ctx.lineWidth = 3.4 * U; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.moveTo(cx - 13 * U, yy + 34 * U + bob); ctx.lineTo(cx, yy + 48 * U + bob); ctx.lineTo(cx + 13 * U, yy + 34 * U + bob); ctx.stroke();
    ctx.restore();
  }
}
