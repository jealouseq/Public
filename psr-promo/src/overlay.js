'use strict';
/* ==========================================================================
   Overlay helpers: stars, vector lines on the globe, HUD primitives,
   typography with word-by-word reveal, logo emblem, post-processing.
   ========================================================================== */
const RED = [229, 18, 40], RED_HI = [255, 64, 78], RED_DEEP = [120, 8, 20];
const rgba = (c, a) => `rgba(${c[0]},${c[1]},${c[2]},${a})`;

/* ----------------------------------------------------------------- stars */
let STARS = null;
function initStars() {
  const r = rng(77), n = 1100; STARS = [];
  for (let i = 0; i < n; i++) {
    const z = r() * 2 - 1, a = r() * TAU, s = Math.sqrt(1 - z * z);
    const mag = Math.pow(r(), 3.2);
    STARS.push({ v: [s * Math.cos(a), s * Math.sin(a), z], m: mag, ph: r() * TAU, w: 0.6 + r() * 0.8, warm: r() });
  }
}
function drawStars(ctx, cam, t, alpha) {
  if (alpha <= 0.002) return;
  const { right, up, fwd, f, cx, cy, W, H } = cam;
  for (const s of STARS) {
    const zc = s.v[0] * fwd[0] + s.v[1] * fwd[1] + s.v[2] * fwd[2];
    if (zc < 0.08) continue;
    const x = cx + (f * (s.v[0] * right[0] + s.v[1] * right[1] + s.v[2] * right[2])) / zc;
    const y = cy - (f * (s.v[0] * up[0] + s.v[1] * up[1] + s.v[2] * up[2])) / zc;
    if (x < -4 || y < -4 || x > W + 4 || y > H + 4) continue;
    const tw = 0.78 + 0.22 * Math.sin(t * s.w * 2.2 + s.ph);
    const a = alpha * (0.18 + 0.82 * s.m) * tw;
    const sz = 0.7 + s.m * 1.7;
    ctx.fillStyle = s.warm > 0.7 ? `rgba(255,205,205,${a})` : `rgba(235,235,255,${a})`;
    ctx.fillRect(x - sz / 2, y - sz / 2, sz, sz);
  }
}

/* ---------------------------------------------------- vectors on the globe */
const _p = [0, 0, 0];
function strokeSet(ctx, cam, set, opt) {
  const { xyz, starts, count } = set, pos = cam.pos;
  const skip = opt.skip || 1, cull = opt.cull;
  ctx.beginPath();
  for (let i = 0; i < count; i++) {
    let pen = false;
    for (let k = starts[i]; k < starts[i + 1]; k += skip) {
      const px = xyz[k * 3], py = xyz[k * 3 + 1], pz = xyz[k * 3 + 2];
      if (px * pos[0] + py * pos[1] + pz * pos[2] <= 1.0) { pen = false; continue; }
      if (cull) { const c = px * cull.c[0] + py * cull.c[1] + pz * cull.c[2]; if (c < cull.cos) { pen = false; continue; } }
      project(cam, [px, py, pz], _p, 1);
      if (!_p[2]) { pen = false; continue; }
      if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; }
    }
  }
  ctx.lineWidth = opt.lw; ctx.strokeStyle = opt.color; ctx.stroke();
}
function polyGeo(ctx, cam, pts, r) { // pts: array of unit vectors
  let pen = false;
  ctx.beginPath();
  for (const p of pts) {
    project(cam, p, _p, r(p));
    if (!_p[2]) { pen = false; continue; }
    if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; }
  }
}
function drawGraticule(ctx, cam, stepDeg, alpha, bounds, lw) {
  if (alpha < 0.01) return;
  ctx.save(); ctx.lineWidth = lw || 1; ctx.strokeStyle = `rgba(255,70,84,${alpha})`;
  const b = bounds || { lon0: -180, lon1: 180, lat0: -80, lat1: 80 };
  const sample = Math.max(0.25, stepDeg / 8);
  const pts = [];
  ctx.beginPath();
  for (let lon = Math.ceil(b.lon0 / stepDeg) * stepDeg; lon <= b.lon1 + 1e-6; lon += stepDeg) {
    let pen = false;
    for (let lat = b.lat0; lat <= b.lat1 + 1e-6; lat += sample) {
      const v = llv(lon, lat);
      if (v[0] * cam.pos[0] + v[1] * cam.pos[1] + v[2] * cam.pos[2] <= 1) { pen = false; continue; }
      project(cam, v, _p, 1); if (!_p[2]) { pen = false; continue; }
      if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; }
    }
  }
  for (let lat = Math.ceil(b.lat0 / stepDeg) * stepDeg; lat <= b.lat1 + 1e-6; lat += stepDeg) {
    let pen = false;
    for (let lon = b.lon0; lon <= b.lon1 + 1e-6; lon += sample) {
      const v = llv(lon, lat);
      if (v[0] * cam.pos[0] + v[1] * cam.pos[1] + v[2] * cam.pos[2] <= 1) { pen = false; continue; }
      project(cam, v, _p, 1); if (!_p[2]) { pen = false; continue; }
      if (pen) ctx.lineTo(_p[0], _p[1]); else { ctx.moveTo(_p[0], _p[1]); pen = true; }
    }
  }
  ctx.stroke(); ctx.restore();
}
// geodesic arc between unit vectors a,b lifted above the surface
function arcPoints(a, b, n) {
  const pts = [];
  for (let i = 0; i <= n; i++) pts.push(slerp3(a, b, i / n));
  return pts;
}
function drawArc(ctx, cam, a, b, prog, lift, color, lw, headR) {
  const n = 64, pts = arcPoints(a, b, n);
  const ang = Math.acos(clamp(dot3(a, b), -1, 1));
  const h = lift * ang;
  const upto = Math.floor(prog * n);
  if (upto < 1) return null;
  let head = null;
  const seg = [];
  for (let i = 0; i <= upto; i++) {
    const s = i / n, r = 1 + h * Math.sin(Math.PI * s);
    const q = project(cam, pts[i], [0, 0, 0], r);
    seg.push({ x: q[0], y: q[1], v: q[2] && (dot3(pts[i], cam.pos) * r > 0.0 || r > 1.01) });
  }
  // gradient trail: draw in chunks so alpha rises towards the head
  const chunks = 8, per = Math.max(1, Math.ceil(upto / chunks));
  for (let c = 0; c < chunks; c++) {
    const i0 = c * per, i1 = Math.min(upto, (c + 1) * per);
    if (i0 >= upto) break;
    ctx.beginPath(); let pen = false;
    for (let i = i0; i <= i1; i++) {
      const q = seg[i]; if (!q.v) { pen = false; continue; }
      if (pen) ctx.lineTo(q.x, q.y); else { ctx.moveTo(q.x, q.y); pen = true; }
    }
    const aa = 0.15 + 0.85 * ((c + 1) / chunks);
    ctx.strokeStyle = rgba(color, aa); ctx.lineWidth = lw; ctx.stroke();
  }
  const hq = seg[upto];
  if (hq && hq.v) head = [hq.x, hq.y];
  return head;
}
function glowDot(ctx, x, y, r, c, a) {
  const g = ctx.createRadialGradient(x, y, 0, x, y, r);
  g.addColorStop(0, `rgba(255,235,235,${a})`);
  g.addColorStop(0.18, rgba(RED_HI, a * 0.9));
  g.addColorStop(0.55, rgba(c || RED, a * 0.28));
  g.addColorStop(1, rgba(c || RED, 0));
  ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, r, 0, TAU); ctx.fill();
}

/* ---------------------------------------------------------------- HUD bits */
function reticle(ctx, x, y, r, t, a, spin) {
  ctx.save(); ctx.translate(x, y); ctx.globalAlpha = a;
  ctx.strokeStyle = rgba(RED_HI, 0.9); ctx.lineWidth = 1.6;
  ctx.beginPath(); ctx.arc(0, 0, r, 0, TAU); ctx.stroke();
  ctx.lineWidth = 1; ctx.strokeStyle = rgba(RED_HI, 0.55);
  ctx.beginPath(); ctx.arc(0, 0, r * 0.62, 0, TAU); ctx.stroke();
  ctx.rotate((spin || 0) + t * 0.35);
  ctx.lineWidth = 3; ctx.strokeStyle = rgba(RED_HI, 0.95);
  for (let i = 0; i < 4; i++) { ctx.beginPath(); ctx.arc(0, 0, r * 1.12, i * PI / 2 + 0.25, i * PI / 2 + 0.25 + 0.55); ctx.stroke(); }
  ctx.rotate(-(spin || 0) - t * 0.35);
  ctx.lineWidth = 1.6; ctx.strokeStyle = rgba(RED_HI, 0.95);
  for (let i = 0; i < 4; i++) {
    const c = Math.cos(i * PI / 2), s = Math.sin(i * PI / 2);
    ctx.beginPath(); ctx.moveTo(c * r * 0.78, s * r * 0.78); ctx.lineTo(c * r * 1.3, s * r * 1.3); ctx.stroke();
  }
  ctx.restore();
}
function cornerBrackets(ctx, W, H, m, len, a, lw) {
  ctx.save(); ctx.globalAlpha = a; ctx.strokeStyle = rgba(RED_HI, 0.9); ctx.lineWidth = lw || 2.2;
  const pts = [[m, m, 1, 1], [W - m, m, -1, 1], [m, H - m, 1, -1], [W - m, H - m, -1, -1]];
  for (const [x, y, sx, sy] of pts) { ctx.beginPath(); ctx.moveTo(x, y + sy * len); ctx.lineTo(x, y); ctx.lineTo(x + sx * len, y); ctx.stroke(); }
  ctx.restore();
}

/* ------------------------------------------------------------- typography */
// spec: {lines:[{text, size, family, weight, color, tracking}], x, y, align, t0, per, dur, tOut, outDur, lh, shadow, upper}
// words prefixed with * are drawn in red. Each word fades/blurs/rises in with its own delay.
function measureWord(ctx, w, tracking) { ctx.letterSpacing = (tracking || 0) + 'px'; return ctx.measureText(w).width; }
function drawWords(ctx, t, spec) {
  const out = spec.tOut === undefined ? 1e9 : spec.tOut;
  const oa = 1 - easeInOut(seg(t, out, out + (spec.outDur || 0.5)));
  if (oa <= 0.001 || t < spec.t0) return;
  let wi = 0, y = spec.y;
  ctx.save(); ctx.textBaseline = 'alphabetic';
  for (const ln of spec.lines) {
    const size = ln.size || spec.size, fam = ln.family || spec.family || 'Montserrat', wt = ln.weight || spec.weight || 800;
    const tr = ln.tracking !== undefined ? ln.tracking : (spec.tracking || 0);
    ctx.font = `${wt} ${size}px ${fam}`;
    // tokens: *start of red span ... end of red span*  (spans may cover several words)
    let inRed = false;
    const toks = ln.text.split(' ').map((w) => {
      const sr = w.startsWith('*'), er = w.length > 1 && w.endsWith('*');
      const txt = w.replace(/^\*/, '').replace(/\*$/, '');
      const red = inRed || sr;
      if (sr && !er) inRed = true; if (er) inRed = false;
      return { txt, red };
    });
    const words = toks.map((k) => k.txt);
    const space = measureWord(ctx, ' ', tr) || size * 0.28;
    const widths = words.map((w) => measureWord(ctx, w, tr));
    const total = widths.reduce((a, b) => a + b, 0) + space * (words.length - 1);
    let x = spec.align === 'center' ? spec.x - total / 2 : spec.align === 'right' ? spec.x - total : spec.x;
    words.forEach((wd, i) => {
      const red = toks[i].red; const txt = toks[i].txt;
      const st = spec.t0 + wi * (spec.per || 0.12);
      const p = easeOutCubic(seg(t, st, st + (spec.dur || 0.7)));
      if (p > 0.001) {
        ctx.globalAlpha = p * oa;
        const dy = (1 - p) * size * 0.35, bl = (1 - p) * (spec.blur === undefined ? 10 : spec.blur);
        ctx.filter = bl > 0.4 ? `blur(${bl.toFixed(1)}px)` : 'none';
        ctx.letterSpacing = tr + 'px';
        if (spec.shadow !== false) { ctx.shadowColor = red ? (spec.accentGlow || 'rgba(255,30,50,0.65)') : (spec.shadowColor || 'rgba(0,0,0,0.75)'); ctx.shadowBlur = red ? size * (spec.accentBlur === undefined ? 0.5 : spec.accentBlur) : size * (spec.shadowBlur === undefined ? 0.3 : spec.shadowBlur); }
        ctx.fillStyle = red ? (spec.accent || '#ff3346') : (ln.color || spec.color || '#f6f1ee');
        ctx.fillText(txt, x, y + dy);
        ctx.filter = 'none'; ctx.shadowBlur = 0;
      }
      x += widths[i] + space; wi++;
    });
    y += (spec.lh || 1.2) * size;
  }
  ctx.restore();
}
function drawTextAt(ctx, txt, x, y, o) {
  ctx.save();
  ctx.font = `${o.weight || 600} ${o.size}px ${o.family || 'Manrope'}`;
  ctx.letterSpacing = (o.tracking || 0) + 'px';
  ctx.textAlign = o.align || 'left'; ctx.textBaseline = 'alphabetic';
  ctx.globalAlpha = o.alpha === undefined ? 1 : o.alpha;
  if (o.glow) { ctx.shadowColor = o.glow; ctx.shadowBlur = o.glowR || 18; }
  ctx.fillStyle = o.color || '#fff';
  ctx.fillText(txt, x, y);
  ctx.restore();
}

/* ------------------------------------------------------------- logo emblem */
// A faithful re-draw of the channel avatar: red crosshair in a dark-red disc with "ЦПР" lettering.
function drawEmblem(ctx, x, y, R, t, o) {
  // o: {a, ring (0..1 draw progress), cross (0..1), text (0..1), spin}
  ctx.save(); ctx.translate(x, y);
  const a = o.a === undefined ? 1 : o.a;
  // disc
  if (o.disc > 0) {
    const g = ctx.createRadialGradient(0, -R * 0.15, R * 0.05, 0, 0, R);
    if (o.neutral) { g.addColorStop(0, `rgba(34,38,52,${0.96 * o.disc})`); g.addColorStop(0.55, `rgba(16,18,28,${0.97 * o.disc})`); g.addColorStop(1, `rgba(6,8,14,${0.98 * o.disc})`); }
    else { g.addColorStop(0, `rgba(168,14,30,${0.95 * o.disc})`); g.addColorStop(0.5, `rgba(86,7,17,${0.96 * o.disc})`); g.addColorStop(1, `rgba(24,2,6,${0.98 * o.disc})`); }
    ctx.globalAlpha = a; ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, R, 0, TAU); ctx.fill();
    // slow radar sweep inside the disc
    if (o.disc > 0.6 && !o.neutral) {
      ctx.save(); ctx.beginPath(); ctx.arc(0, 0, R * 0.97, 0, TAU); ctx.clip();
      const cg = ctx.createConicGradient(t * 1.1, 0, 0);
      cg.addColorStop(0, 'rgba(255,50,66,0)'); cg.addColorStop(0.7, 'rgba(255,50,66,0)'); cg.addColorStop(0.985, 'rgba(255,60,76,0.34)'); cg.addColorStop(1, 'rgba(255,120,130,0.55)');
      ctx.globalAlpha = a * o.disc; ctx.fillStyle = cg; ctx.fillRect(-R, -R, R * 2, R * 2); ctx.restore();
    }
    // ember speckle texture
    const r2 = rng(5); ctx.globalAlpha = a * (o.neutral ? 0.18 : 0.55) * o.disc;
    for (let i = 0; i < 260; i++) {
      const aa = r2() * TAU, rr = Math.sqrt(r2()) * R * 0.96, s = 0.6 + r2() * 1.8;
      ctx.fillStyle = `rgba(255,${40 + (r2() * 60) | 0},${50 + (r2() * 50) | 0},${0.05 + r2() * 0.25})`;
      ctx.fillRect(Math.cos(aa) * rr, Math.sin(aa) * rr, s, s);
    }
  }
  ctx.globalAlpha = a;
  ctx.shadowColor = 'rgba(255,30,48,0.9)'; ctx.shadowBlur = R * 0.14;
  // outer ring
  ctx.strokeStyle = '#e4182c'; ctx.lineWidth = Math.max(2, R * 0.035);
  ctx.beginPath(); ctx.arc(0, 0, R, -PI / 2, -PI / 2 + TAU * clamp(o.ring === undefined ? 1 : o.ring)); ctx.stroke();
  // crosshair
  const cs = clamp(o.cross === undefined ? 1 : o.cross);
  if (cs > 0.001) {
    const cy = -R * 0.2, cr = R * 0.34 * (0.6 + 0.4 * easeOutBack(cs));
    ctx.save(); ctx.translate(0, cy); ctx.rotate((1 - easeOutCubic(cs)) * -1.2 + (o.spin || 0)); ctx.globalAlpha = a * clamp(cs * 2);
    ctx.strokeStyle = '#ff2b40'; ctx.lineWidth = Math.max(2, R * 0.028);
    ctx.beginPath(); ctx.arc(0, 0, cr, 0, TAU); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, 0, cr * 0.42, 0, TAU); ctx.stroke();
    ctx.lineWidth = Math.max(2.4, R * 0.034);
    for (let i = 0; i < 4; i++) {
      const c = Math.cos(i * PI / 2), s = Math.sin(i * PI / 2);
      ctx.beginPath(); ctx.moveTo(c * cr * 0.7, s * cr * 0.7); ctx.lineTo(c * cr * 1.28, s * cr * 1.28); ctx.stroke();
    }
    ctx.fillStyle = '#ff2b40'; ctx.beginPath(); ctx.arc(0, 0, cr * 0.13, 0, TAU); ctx.fill();
    ctx.restore();
  }
  // ЦПР lettering
  const ts = clamp(o.text === undefined ? 1 : o.text);
  if (ts > 0.001) {
    const sc = 1 + (1 - easeOutCubic(ts)) * 0.22;
    ctx.save(); ctx.translate(0, R * 0.52); ctx.scale(sc, sc); ctx.globalAlpha = a * easeOutCubic(ts);
    ctx.font = `400 ${R * 0.46}px "Russo One"`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.letterSpacing = (R * 0.01) + 'px';
    ctx.shadowBlur = R * 0.12;
    ctx.lineWidth = R * 0.03; ctx.strokeStyle = '#2a0308'; ctx.strokeText('ЦПР', 0, 0);
    ctx.fillStyle = '#d3162b'; ctx.fillText('ЦПР', 0, 0);
    ctx.restore();
  }
  ctx.restore();
}

/* ----------------------------------------------------------- post effects */
let _bl = null, _bl2 = null, _grain = null, _vig = null;
function bloomPass(ctx, W, H, strength, blur) {
  const w = Math.round(W / 4), h = Math.round(H / 4);
  if (!_bl) { _bl = document.createElement('canvas'); _bl2 = document.createElement('canvas'); }
  if (_bl.width !== w) { _bl.width = _bl2.width = w; _bl.height = _bl2.height = h; }
  const a = _bl.getContext('2d'), b = _bl2.getContext('2d');
  a.globalCompositeOperation = 'copy'; a.filter = 'contrast(1.35) brightness(0.9)'; a.drawImage(ctx.canvas, 0, 0, w, h); a.filter = 'none';
  b.globalCompositeOperation = 'copy'; b.filter = `blur(${blur}px)`; b.drawImage(_bl, 0, 0); b.filter = 'none';
  ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.globalAlpha = strength; ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(_bl2, 0, 0, W, H);
  b.filter = `blur(${blur * 2.6}px)`; b.drawImage(_bl, 0, 0); b.filter = 'none';
  ctx.globalAlpha = strength * 0.8; ctx.drawImage(_bl2, 0, 0, W, H);
  ctx.restore();
}
function vignette(ctx, W, H, a) {
  if (!_vig || _vig.W !== W || _vig.H !== H) {
    const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const g = x.createRadialGradient(W / 2, H / 2, Math.min(W, H) * 0.32, W / 2, H / 2, Math.hypot(W, H) * 0.56);
    g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,0.85)');
    x.fillStyle = g; x.fillRect(0, 0, W, H); _vig = c; _vig.W = W; _vig.H = H;
  }
  ctx.save(); ctx.globalAlpha = a; ctx.drawImage(_vig, 0, 0); ctx.restore();
}
function grain(ctx, W, H, frame, a) {
  if (!_grain) {
    const c = document.createElement('canvas'); c.width = c.height = 256; const x = c.getContext('2d'); const d = x.createImageData(256, 256);
    const r = rng(1234);
    for (let i = 0; i < 256 * 256; i++) { const v = (r() * 255) | 0; d.data[i * 4] = d.data[i * 4 + 1] = d.data[i * 4 + 2] = v; d.data[i * 4 + 3] = 255; }
    x.putImageData(d, 0, 0); _grain = c;
  }
  const r = rng(frame * 7919 + 13), ox = (r() * 256) | 0, oy = (r() * 256) | 0;
  ctx.save(); ctx.globalCompositeOperation = 'overlay'; ctx.globalAlpha = a;
  for (let y = -oy; y < H; y += 256) for (let x = -ox; x < W; x += 256) ctx.drawImage(_grain, x, y);
  ctx.restore();
}
