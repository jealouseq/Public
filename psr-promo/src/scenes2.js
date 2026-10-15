'use strict';
/* ==========================================================================
   Scenes v2 — «Рассвет»: night Earth with warm lights -> dawn sweeps in -> dive through
   parting clouds to real terrain -> lines of light home across Russia -> emblem.
   Palette: deep navy, amber lights, golden sun, warm white. Brand red only as an accent.
   ========================================================================== */
const R0 = { lon: 37.4, lat: 48.1 };           // centre of the search area; drawn without any borders
const R0v = llv(R0.lon, R0.lat);
const CITIES = [
  ['Москва', 37.62, 55.75], ['Санкт-Петербург', 30.31, 59.94], ['Казань', 49.11, 55.79], ['Екатеринбург', 60.6, 56.84],
  ['Новосибирск', 82.92, 55.03], ['Красноярск', 92.87, 56.01], ['Иркутск', 104.28, 52.29], ['Хабаровск', 135.08, 48.48],
  ['Владивосток', 131.89, 43.12], ['Краснодар', 38.97, 45.04], ['Ростов-на-Дону', 39.72, 47.23], ['Самара', 50.15, 53.2],
  ['Челябинск', 61.4, 55.16], ['Омск', 73.37, 54.99], ['Калининград', 20.5, 54.71], ['Мурманск', 33.08, 68.97],
  ['Якутск', 129.73, 62.03], ['Воронеж', 39.2, 51.67], ['Нижний Новгород', 44.0, 56.33], ['Волгоград', 44.5, 48.7],
  ['Уфа', 55.97, 54.74], ['Пермь', 56.25, 58.01], ['Тюмень', 65.53, 57.15], ['Архангельск', 40.5, 64.54],
].map((c) => ({ name: c[0], lon: c[1], lat: c[2], v: llv(c[1], c[2]) }));

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
    m[0] = end(h[0], h[1], d[0], d[1]); m[n - 1] = end(h[n - 2], h[n - 3], d[n - 2], d[n - 3]);
  }
  return (t) => {
    if (t <= ts[0]) return vs[0]; if (t >= ts[n - 1]) return vs[n - 1];
    let i = 0; while (t > ts[i + 1]) i++;
    const x = (t - ts[i]) / h[i], x2 = x * x, x3 = x2 * x;
    return (2 * x3 - 3 * x2 + 1) * vs[i] + (x3 - 2 * x2 + x) * h[i] * m[i] + (-2 * x3 + 3 * x2) * vs[i + 1] + (x3 - x2) * h[i] * m[i + 1];
  };
}

/* ------------------------------------------------------------- camera path */
let CAMPATH = null, SUNLON = null;
function layout(W, H) {
  const vert = H > W * 1.3, U = W / 1080;
  return { vert, U, emblemCy: H * (vert ? 0.36 : 0.30), R: 172 * U, base: H * (vert ? 0.87 : 0.84) };
}
function buildCameraPath(W, H) {
  const L = layout(W, H), kS = L.vert ? 0.3 : 1;
  const endShift = ((L.emblemCy - 0.2 * L.R) - H / 2) / H;
  const kf = [
    { t: 0.0, lon: -16, lat: 41, alt: 0.24, pitch: 63, heading: 76, fov: 50 },
    { t: 2.6, lon: -3, lat: 43, alt: 0.30, pitch: 59, heading: 76, fov: 50 },
    { t: 4.3, lon: 11, lat: 44, alt: 0.66, pitch: 44, heading: 72, fov: 48 },
    { t: 5.9, A: [29, 46], alt: 2.5, pitch: 0, heading: 12, fov: 45, shiftY: -0.075 * kS },
    { t: 7.8, A: [35.5, 47.4], alt: 1.55, pitch: 0, heading: 0, fov: 45, shiftY: -0.035 * kS },
    { t: 9.8, A: [R0.lon, R0.lat], alt: 0.80, pitch: 0, heading: 0, fov: 45, shiftY: 0 },
    { t: 11.9, A: [R0.lon, R0.lat], alt: 0.40, pitch: 0, heading: 0, fov: 45 },
    { t: 13.8, A: [R0.lon, R0.lat], alt: 0.17, pitch: 10, heading: 6, fov: 45 },
    { t: 16.2, A: [R0.lon, R0.lat], alt: 0.142, pitch: 30, heading: 22, fov: 45 },
    { t: 19.4, A: [R0.lon, R0.lat], alt: 0.130, pitch: 34, heading: 58, fov: 45 },
    { t: 20.2, A: [R0.lon, R0.lat], alt: 0.132, pitch: 34, heading: 64, fov: 45 },
    { t: 22.0, A: [47, 52], alt: 0.95, pitch: 12, heading: 34, fov: 45, shiftY: -0.03 * kS },
    { t: 23.9, A: [62, 56.5], alt: 2.7, pitch: 0, heading: 0, fov: 45, shiftY: -0.12 * kS },
    { t: 25.2, A: [64, 56.5], alt: 4.4, pitch: 0, heading: 0, fov: 45, shiftY: endShift * 0.9 },
    { t: 27.0, A: [64, 56.5], alt: 16, pitch: 0, heading: 0, fov: 45, shiftY: endShift },
    { t: 32.0, A: [64, 56.5], alt: 40, pitch: 0, heading: 0, fov: 45, shiftY: endShift },
  ].map((k) => { if (k.A) { const s = solveAim({ lon: k.A[0], lat: k.A[1] }, k.alt, k.pitch, k.heading); return { ...k, lon: s.lon, lat: s.lat }; } return k; });
  const T = kf.map((k) => k.t);
  const comp = (name, f = (x) => x, def = 0) => pchip(T, kf.map((k) => f(k[name] === undefined ? def : k[name])));
  CAMPATH = { lon: comp('lon'), lat: comp('lat'), alt: comp('alt', Math.log), pitch: comp('pitch'), heading: comp('heading'), roll: comp('roll'), fov: comp('fov', (x) => x, 45), shiftY: comp('shiftY'), shiftX: comp('shiftX') };
  // sun travels west as the planet turns: dawn reaches the region around 7-9 s
  SUNLON = pchip([0, 5.5, 8, 12, 16, 20, 26, 32], [135, 128, 121, 106, 92, 80, 66, 60]);
}
function cameraAt(t, W, H) {
  const p = CAMPATH;
  return { lon: p.lon(t), lat: p.lat(t), alt: Math.exp(p.alt(t)), pitch: p.pitch(t), heading: p.heading(t), roll: p.roll(t), fov: p.fov(t), shiftY: p.shiftY(t) * H, shiftX: p.shiftX(t) * W };
}
function sunAt(t, c) {
  const world = norm3(llv(SUNLON(t), 8));
  const w = 1 - smooth(3.4, 6.2, t);                 // opening: sun rises just above the limb of the camera's view
  if (w <= 0) return world;
  const u = llv(c.lon, c.lat), clon = c.lon * D2R, clat = c.lat * D2R;
  const E = [-Math.sin(clon), Math.cos(clon), 0], N = [-Math.sin(clat) * Math.cos(clon), -Math.sin(clat) * Math.sin(clon), Math.cos(clat)];
  const hd = (c.heading + 9) * D2R, el = (-35.7 + 2.9 * smooth(0.0, 3.0, t) + 0.4 * Math.sin(t * 0.6) + 2.0 * smooth(3.0, 6.0, t)) * D2R;
  const h = add3(mul3(N, Math.cos(hd)), mul3(E, Math.sin(hd)));
  const open = norm3(add3(mul3(u, Math.sin(el)), mul3(h, Math.cos(el))));
  return norm3(add3(mul3(open, w), mul3(world, 1 - w)));
}
function drawFlare(ctx, cam, sun, a, t) {
  if (a <= 0.003) return;
  const sz = dot3(sun, cam.fwd); if (sz < 0.15) return;
  const b = dot3(cam.pos, sun), rho = Math.sqrt(Math.max(0, dot3(cam.pos, cam.pos) - b * b));
  let vis = 1; if (b < 0) vis = smooth(0.992, 1.010, rho);
  if (vis <= 0.003) return;
  const x = cam.cx + (cam.f * dot3(sun, cam.right)) / sz, y = cam.cy - (cam.f * dot3(sun, cam.up)) / sz, W = cam.W, k = a * vis;
  ctx.save(); ctx.globalCompositeOperation = 'lighter';
  let g = ctx.createRadialGradient(x, y, 0, x, y, W * 0.38);
  g.addColorStop(0, `rgba(255,190,120,${0.42 * k})`); g.addColorStop(0.3, `rgba(255,140,80,${0.16 * k})`); g.addColorStop(1, 'rgba(255,120,60,0)');
  ctx.fillStyle = g; ctx.fillRect(0, 0, W, cam.H);
  g = ctx.createRadialGradient(x, y, 0, x, y, W * 0.045);
  g.addColorStop(0, `rgba(255,252,245,${0.98 * k})`); g.addColorStop(0.35, `rgba(255,222,170,${0.65 * k})`); g.addColorStop(1, 'rgba(255,170,100,0)');
  ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, W * 0.045, 0, TAU); ctx.fill();
  const sl = W * (0.58 + 0.03 * Math.sin(t * 1.3));
  g = ctx.createLinearGradient(x - sl, y, x + sl, y);
  g.addColorStop(0, 'rgba(120,170,255,0)'); g.addColorStop(0.5, `rgba(205,225,255,${0.5 * k})`); g.addColorStop(1, 'rgba(120,170,255,0)');
  ctx.fillStyle = g; ctx.fillRect(x - sl, y - 2.0, sl * 2, 4.0);
  const gx = cam.W / 2 - x, gy = cam.H / 2 - y;
  for (const [s, r, al, c] of [[0.5, 22, 0.14, '255,190,120'], [1.0, 54, 0.07, '150,190,255'], [1.5, 16, 0.14, '255,210,160']]) {
    const px = x + gx * s, py = y + gy * s, gr = ctx.createRadialGradient(px, py, 0, px, py, r);
    gr.addColorStop(0, `rgba(${c},${al * k})`); gr.addColorStop(1, `rgba(${c},0)`);
    ctx.fillStyle = gr; ctx.beginPath(); ctx.arc(px, py, r, 0, TAU); ctx.fill();
  }
  ctx.restore();
}

/* ----------------------------------------------------------- search points */
let SPTS = null;
function initSearchPoints() {
  const r = rng(2024); SPTS = [];
  for (let i = 0; i < 20; i++) {
    const a = r() * TAU, rad = 0.4 + Math.sqrt(r()) * 2.4;
    const lon = R0.lon + Math.cos(a) * rad * 1.45, lat = R0.lat + Math.sin(a) * rad;
    SPTS.push({ lon, lat, v: llv(lon, lat), t0: TL.lights[Math.min(TL.lights.length - 1, Math.floor(i * TL.lights.length / 20))] + r() * 0.3, lock: false });
  }
  SPTS.sort((p, q) => p.t0 - q.t0);
}

/* ------------------------------------------------------------------ state */
const RIPS = [];      // no radar-style rings: the search area is a soft glow and a warm light
function ripples(t) {
  const out = [];
  for (const e of RIPS) { const k = (t - e.t) / e.dur; if (k > 0 && k < 1) out.push({ r: 0.006 + e.rmax * easeOutCubic(k), a: (1 - k) * e.a }); }
  return out;
}
function shake(t, t0, amp, dur) { const k = t - t0; if (k < 0 || k > dur) return [0, 0]; const e = Math.exp(-k / (dur * 0.28)); return [Math.sin(k * 63) * amp * e, Math.cos(k * 49 + 1) * amp * e]; }
function sceneState(t, W, H) {
  const c = cameraAt(t, W, H), S = { cam: c };
  S.sun = sunAt(t, c);
  const worldFade = 1 - smooth(TL.logoIn + 0.2, TL.logoIn + 1.15, t);
  S.worldA = worldFade;
  S.exposure = (0.92 + 0.5 * (1 - smooth(0.22, 1.5, c.alt))) * (1 - 0.14 * smooth(12, 22, t));
  S.cloudA = 1 - 0.32 * smooth(13, 23, t);
  const open = smoother(9.2, 12.0, t), close = smooth(21.0, 24.0, t);
  S.cloudClear = 0.62 * open * (1 - 0.82 * close);
  S.lightsGain = 1 + 0.15 * (1 - smooth(3, 6, t));
  S.cloudGain = 1 - 0.40 * smooth(7.6, 9.2, t) * (1 - smooth(10.4, 11.8, t));       // the dive passes through bright clouds: keep them from clipping
  S.haloGain = 0.35 + 0.65 * smooth(0.25, 0.8, c.alt);
  const zOn = smooth(TL.zoneOn, TL.zoneOn + 1.1, t) * (1 - smooth(TL.reveal - 1.0, TL.reveal, t));
  const zBack = smooth(TL.arcStart - 0.4, TL.arcStart + 0.8, t) * (1 - smooth(TL.logoIn - 0.4, TL.logoIn + 0.8, t));
  S.zone = { c: R0v, R: 0.085 + 0.004 * Math.sin(t * 1.6), a: Math.max(zOn * 0.9, zBack * 0.55), spin: 0 };
  S.ripples = ripples(t);
  const k1 = shake(t, TL.cues.impact, 6, 0.5), k2 = shake(t, TL.cues.logo, 5, 0.45);
  c.shiftX += (k1[0] + k2[0]) * (W / 1080); c.shiftY += (k1[1] + k2[1]) * (W / 1080);
  return S;
}
const WIN = TL.text;
function scrimAlpha(t) {
  let a = 0;
  for (const k of Object.keys(WIN)) { const [i, o] = WIN[k]; a = Math.max(a, smooth(i - 0.2, i + 0.6, t) * (1 - smooth(o - 0.2, o + 0.5, t))); }
  return a;
}

/* ------------------------------------------------------------------ frame */
function drawScene(ctx, gctx, W, H, t, frame) {
  const U = W / 1080, L = layout(W, H);
  const S = sceneState(t, W, H), cam = makeCamera(S.cam, W, H);
  ctx.globalCompositeOperation = 'source-over';
  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#02040a'); bg.addColorStop(1, '#050a16');
  ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
  drawStars(ctx, cam, t, 0.5 + 0.5 * smooth(0, 0.8, t));
  // ---- the planet
  if (S.worldA > 0.002) {
    renderEarth2(gctx, W, H, cam, S);
    ctx.save(); ctx.globalAlpha = S.worldA; ctx.drawImage(gctx.canvas, 0, 0); ctx.restore();
  }
  // ---- light overlays (additive)
  ctx.save(); ctx.globalCompositeOperation = 'lighter';
  if (t > 13 && t < 20.6) drawSearchPoints(ctx, cam, t, W, H, U);
  if (t > TL.arcStart - 0.2 && t < TL.logoIn + 2.2) drawArcs(ctx, cam, t, U);
  ctx.restore();
  // the brand beacon on the region
  { const bA = smooth(TL.zoneOn + 0.3, TL.zoneOn + 1.3, t) * S.worldA * (1 - smooth(TL.logoIn - 0.2, TL.logoIn + 0.6, t));
    if (bA > 0.01) { const q = project(cam, R0v, [0, 0, 0], 1); if (q[2]) beacon(ctx, q[0], q[1], U * (t < 12 ? 0.8 : 1), t, bA); } }
  drawFlare(ctx, cam, S.sun, 1 - smooth(3.6, 5.6, t), t);
  bloomPass(ctx, W, H, 0.32, 5);
  // ---- legibility scrims
  const sa = scrimAlpha(t);
  if (sa > 0.01) {
    const g = ctx.createLinearGradient(0, H * 0.5, 0, H);
    g.addColorStop(0, 'rgba(3,6,14,0)'); g.addColorStop(0.5, `rgba(3,6,14,${0.55 * sa})`); g.addColorStop(1, `rgba(3,6,14,${0.88 * sa})`);
    ctx.fillStyle = g; ctx.fillRect(0, H * 0.5, W, H * 0.5);
  }
  const hudA = 1 - smooth(TL.logoIn - 0.2, TL.logoIn + 0.6, t);
  if (hudA > 0.01) {
    const g = ctx.createLinearGradient(0, 0, 0, H * 0.2);
    g.addColorStop(0, `rgba(3,6,14,${0.64 * hudA})`); g.addColorStop(1, 'rgba(3,6,14,0)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H * 0.2);
  }
  gradePass(ctx, W, H, frame, { sat: 1.04, con: 1.07, grain: 2.4 });
  vignette(ctx, W, H, 0.42);
  drawBrandBug(ctx, t, W, H, U, hudA);
  drawTexts(ctx, t, W, H, U, L);
  drawLogoBlock(ctx, t, W, H, U, L);
  const fade = 1 - smooth(TL.total - 0.6, TL.total, t);
  if (fade < 1) { ctx.fillStyle = `rgba(0,0,0,${1 - fade})`; ctx.fillRect(0, 0, W, H); }
}

function drawGraticule2(ctx, cam, stepDeg, alpha, b, lw) {
  if (alpha < 0.004) return;
  ctx.save(); ctx.lineWidth = lw; ctx.strokeStyle = `rgba(255,244,228,${alpha})`;
  const sample = Math.max(0.2, stepDeg / 8); ctx.beginPath();
  const run = (v) => { if (v[0] * cam.pos[0] + v[1] * cam.pos[1] + v[2] * cam.pos[2] <= 1) return false; project(cam, v, _p, 1); return !!_p[2]; };
  for (let lon = Math.ceil(b.lon0 / stepDeg) * stepDeg; lon <= b.lon1 + 1e-6; lon += stepDeg) {
    let pen = false;
    for (let lat = b.lat0; lat <= b.lat1 + 1e-6; lat += sample) { if (!run(llv(lon, lat))) { pen = false; continue; } if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; } }
  }
  for (let lat = Math.ceil(b.lat0 / stepDeg) * stepDeg; lat <= b.lat1 + 1e-6; lat += stepDeg) {
    let pen = false;
    for (let lon = b.lon0; lon <= b.lon1 + 1e-6; lon += sample) { if (!run(llv(lon, lat))) { pen = false; continue; } if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; } }
  }
  ctx.stroke(); ctx.restore();
}
function drawSearchPoints(ctx, cam, t, W, H, U) {
  const tp = [0, 0, 0];
  const gate = smooth(13.0, 13.8, t) * (1 - smooth(19.4, 20.4, t));
  for (const p of SPTS) {
    const k = easeOutCubic(seg(t, p.t0, p.t0 + 1.4)); if (k <= 0) continue;
    project(cam, p.v, tp, 1); if (!tp[2]) continue;
    const pulse = 0.78 + 0.22 * Math.sin(t * 2.1 + p.lon * 7);
    lightDot(ctx, tp[0], tp[1], (13 + 7 * k) * U, AMBER, 0.9 * k * gate * pulse);
  }
}
function drawArcs(ctx, cam, t, U) {
  const fadeOut = 1 - smooth(TL.logoIn + 0.1, TL.logoIn + 0.9, t);
  CITIES.forEach((c, i) => {
    const st = TL.arcStart + i * 0.1, prog = easeInOut(seg(t, st, st + 1.9));
    if (prog <= 0) return;
    ctx.globalCompositeOperation = 'source-over';
    drawArc(ctx, cam, R0v, c.v, prog, 0.1, [6, 10, 22], 4.6 * U, 0); ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'lighter';
    const head = drawArc(ctx, cam, R0v, c.v, prog, 0.1, GOLD, 2.2 * U * (cam.d > 6 ? 0.6 : 1), 0);
    ctx.globalAlpha = 1;
    if (head && prog < 1) lightDot(ctx, head[0], head[1], 18 * U, AMBER, 0.95 * fadeOut);
    if (prog >= 0.98) {
      const q = project(cam, c.v, [0, 0, 0], 1);
      if (q[2]) {
        const k = seg(t, st + 1.85, st + 3.0);
        lightDot(ctx, q[0], q[1], (14 + 6 * Math.sin(k * 3.14)) * U, AMBER, 0.95 * fadeOut);
      }
    }
  });
}

/* ---------------------------------------------------------------- brand bug */
// small, calm brand lock-up in the corner from the first frame (the feed preview must not be anonymous)
function drawBrandBug(ctx, t, W, H, U, a) {
  if (a <= 0.01) return;
  ctx.save(); ctx.globalAlpha = a * 0.95;
  const x = 62 * U, y = 74 * U, r = 17 * U;
  ctx.strokeStyle = '#ff2d44'; ctx.lineWidth = 2.6 * U; ctx.shadowColor = 'rgba(0,0,0,0.55)'; ctx.shadowBlur = 8;
  ctx.beginPath(); ctx.arc(x + r, y, r, 0, TAU); ctx.stroke();
  ctx.beginPath(); ctx.arc(x + r, y, r * 0.38, 0, TAU); ctx.stroke();
  for (let i = 0; i < 4; i++) { const c = Math.cos(i * PI / 2), s = Math.sin(i * PI / 2); ctx.beginPath(); ctx.moveTo(x + r + c * r * 0.62, y + s * r * 0.62); ctx.lineTo(x + r + c * r * 1.2, y + s * r * 1.2); ctx.stroke(); }
  ctx.restore();
  drawTextAt(ctx, 'ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ', x + r * 2 + 20 * U, y + 11 * U, { size: 32 * U, weight: 700, family: 'Montserrat', tracking: 2.4 * U, color: '#fff7ee', alpha: a * 0.96, glow: 'rgba(0,0,0,0.6)', glowR: 10 });
}

/* ------------------------------------------------------------------ texts */
const GOLDHEX = '#ffc978';
function drawTexts(ctx, t, W, H, U, L) {
  const cx = W / 2, base = L.base, T = TL.text, AC = { accent: GOLDHEX, accentGlow: 'rgba(255,170,70,0.45)', accentBlur: 0.35 };
  drawWords(ctx, t, { lines: [{ text: 'Когда нет вестей от бойца…' }], x: cx, y: base - 26 * U, align: 'center', size: 62 * U, weight: 700, t0: T.a1[0], per: 0.16, dur: 0.85, tOut: T.a1[1], outDur: 0.7 });
  drawWords(ctx, t, { lines: [{ text: 'самое трудное — *неизвестность*' }], x: cx, y: base + 52 * U, align: 'center', size: 55 * U, weight: 600, tracking: -0.2 * U, t0: T.a2[0], per: 0.15, dur: 0.85, tOut: T.a2[1], outDur: 0.7, accent: '#ff5063', accentGlow: 'rgba(255,40,60,0.35)', accentBlur: 0.3 });
  drawWords(ctx, t, { lines: [{ text: 'Профессиональный розыск', size: 58 * U, weight: 600 }, { text: '*в зоне СВО*', size: 98 * U, weight: 800 }], x: cx, y: base - 74 * U, align: 'center', size: 58 * U, weight: 700, lh: 1.62, t0: T.b1[0], per: 0.2, dur: 0.9, tOut: T.b1[1], outDur: 0.7, ...AC });
  drawWords(ctx, t, { lines: [{ text: 'Ищем *ответы* там,' }, { text: 'где они нужны семьям' }], x: cx, y: base - 40 * U, align: 'center', size: 64 * U, weight: 700, lh: 1.2, t0: T.c1[0], per: 0.14, dur: 0.85, tOut: T.c1[1], outDur: 0.7, ...AC });
  drawWords(ctx, t, { lines: [{ text: '*На связи с семьями*', size: 68 * U, weight: 800 }, { text: 'Вежливо и бережно', size: 58 * U, weight: 700, family: 'Manrope' }], x: cx, y: base - 34 * U, align: 'center', size: 70 * U, weight: 800, lh: 1.3, t0: T.d1[0], per: 0.11, dur: 0.8, tOut: T.d1[1], outDur: 0.5, ...AC });
}

/* ----------------------------------------------------------- logo + CTA card */
let MOTES = null;
function initMotes() { const r = rng(88); MOTES = []; for (let i = 0; i < 70; i++) MOTES.push({ x: r(), y: r(), s: 0.4 + r() * 1.3, sp: 0.01 + r() * 0.03, ph: r() * TAU }); }
function drawLogoBlock(ctx, t, W, H, U, L) {
  const t0 = TL.logoIn; if (t < t0 - 0.2) return;
  const cx = W / 2, R = L.R, cy = L.emblemCy, tf = t0 + 1.15;            // tf = the beat where the emblem locks (25.0 s)
  const bgA = smooth(t0 + 0.3, t0 + 1.4, t);
  if (bgA > 0.01) {
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(W, H) * 0.75);
    g.addColorStop(0, `rgba(22,26,40,${0.92 * bgA})`); g.addColorStop(0.5, `rgba(7,9,16,${0.96 * bgA})`); g.addColorStop(1, `rgba(3,4,8,${bgA})`);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    ctx.save(); ctx.globalCompositeOperation = 'lighter';
    for (const m of MOTES) {
      const y = ((m.y - t * m.sp) % 1 + 1) % 1, x = m.x + Math.sin(t * 0.4 + m.ph) * 0.01, tw = 0.5 + 0.5 * Math.sin(t * 1.3 + m.ph);
      lightDot(ctx, x * W, y * H, (3 + m.s * 5) * U, AMBER, 0.26 * tw * bgA);
    }
    ctx.restore();
  }
  // light gathers (2 slim rings) just before the beat
  const k = seg(t, t0 + 0.1, tf);
  if (k > 0 && k < 1) {
    ctx.save(); ctx.globalCompositeOperation = 'lighter';
    for (let i = 0; i < 2; i++) { const kk = clamp(k * 1.15 - i * 0.14), rr = R * (2.4 - 1.5 * easeOutCubic(kk)); thinRing(ctx, cx, cy - R * 0.2, rr, WARM, (1 - kk) * 0.5 * kk * 3, 2 * U); }
    ctx.restore();
  }
  // anticipation: a warm glow gathers where the emblem will appear (no empty black beat)
  const pre = smooth(t0 + 0.15, tf, t) * (t < tf ? 1 : 0);
  if (pre > 0.01) {
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 1.5);
    g.addColorStop(0, `rgba(255,200,120,${0.30 * pre})`); g.addColorStop(1, 'rgba(255,170,90,0)');
    ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); ctx.restore();
  }
  // the beat: one clean golden flash with a tight core
  const fl = Math.exp(-Math.pow((t - tf) / 0.08, 2)) + 0.3 * Math.exp(-Math.pow((t - tf - 0.12) / 0.2, 2));
  if (fl > 0.01) {
    const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 1.9);
    g.addColorStop(0, `rgba(255,238,196,${Math.min(0.95, 0.9 * fl)})`); g.addColorStop(0.3, `rgba(255,196,110,${0.5 * fl})`); g.addColorStop(1, 'rgba(255,160,70,0)');
    ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); ctx.restore();
  }
  const breathe = 1 + 0.010 * Math.sin(t * 1.6), pop = 1 + 0.06 * Math.exp(-Math.pow((t - tf - 0.1) / 0.16, 2));
  ctx.save(); ctx.translate(cx, cy); ctx.scale(breathe * pop, breathe * pop); ctx.translate(-cx, -cy);
  drawEmblem(ctx, cx, cy, R, t, { neutral: true, crisp: true, a: smooth(tf - 0.05, tf + 0.12, t), disc: smooth(tf - 0.05, tf + 0.35, t), ring: easeOutCubic(seg(t, tf, tf + 0.5)), cross: seg(t, tf + 0.05, tf + 0.6), text: seg(t, tf + 0.18, tf + 0.7), spin: Math.sin(t * 0.9) * 0.04 });
  ctx.restore();
  drawWords(ctx, t, { lines: [{ text: 'ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ' }], x: cx, y: cy + R + 90 * U, align: 'center', size: 50 * U, weight: 800, tracking: 3.0 * U, t0: TL.wordmark, per: 0.12, dur: 0.8 });
  drawWords(ctx, t, { lines: [{ text: '*Профессиональный розыск в зоне СВО*' }], x: cx, y: cy + R + 150 * U, align: 'center', size: 38 * U, weight: 600, family: 'Manrope', tracking: 0.4 * U, t0: TL.tagline, per: 0.07, dur: 0.7, blur: 6, accent: GOLDHEX, accentGlow: 'rgba(255,170,70,0.3)', accentBlur: 0.3 });
  const sp = easeOutCubic(seg(t, TL.cta - 0.3, TL.cta + 0.5));
  if (sp > 0) { ctx.save(); ctx.globalAlpha = sp; ctx.strokeStyle = 'rgba(255,201,120,0.85)'; ctx.lineWidth = 2 * U; ctx.beginPath(); ctx.moveTo(cx - 90 * U * sp, cy + R + 196 * U); ctx.lineTo(cx + 90 * U * sp, cy + R + 196 * U); ctx.stroke(); ctx.restore(); }
  drawWords(ctx, t, { lines: [{ text: 'Напишите нам — мы ответим' }, { text: 'и подскажем, с чего начать' }], x: cx, y: cy + R + 262 * U, align: 'center', size: 48 * U, weight: 700, family: 'Manrope', lh: 1.22, t0: TL.cta, per: 0.09, dur: 0.7 });
  const hk = easeOutCubic(seg(t, TL.contact, TL.contact + 0.7));
  if (hk > 0) {
    const yy = cy + R + 438 * U;
    drawTextAt(ctx, 'Контакты — в описании канала', cx, yy, { size: 48 * U, weight: 700, family: 'Manrope', color: GOLDHEX, align: 'center', alpha: hk, glow: 'rgba(255,170,70,0.4)', glowR: 14 });
  }
}
