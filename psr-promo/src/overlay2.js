'use strict';
/* ==========================================================================
   Overlay v2: warm/cool palette, colour grade, refined HUD bits.
   (stars, strokeSet, drawArc, drawWords, drawTextAt, bloomPass, vignette, grain come from overlay.js)
   ========================================================================== */
const AMBER = [255, 178, 96], GOLD = [255, 214, 150], WARM = [255, 243, 226], BRAND = [229, 18, 40], ICE = [150, 196, 255];
const col = (c, a) => `rgba(${c[0]},${c[1]},${c[2]},${a})`;

function lightDot(ctx, x, y, r, c, a) {          // soft light: hot white core, coloured falloff
  const g = ctx.createRadialGradient(x, y, 0, x, y, r);
  g.addColorStop(0, `rgba(255,250,240,${a})`);
  g.addColorStop(0.16, col(GOLD, a * 0.85));
  g.addColorStop(0.5, col(c || AMBER, a * 0.28));
  g.addColorStop(1, col(c || AMBER, 0));
  ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, r, 0, TAU); ctx.fill();
}
function thinRing(ctx, x, y, r, c, a, lw) { ctx.strokeStyle = col(c, a); ctx.lineWidth = lw; ctx.beginPath(); ctx.arc(x, y, r, 0, TAU); ctx.stroke(); }

// the brand marker: calm red dot with white ring and a slow pulse
function beacon(ctx, x, y, U, t, a) {
  if (a <= 0.005) return;
  ctx.save(); ctx.globalAlpha = a;
  for (let i = 0; i < 2; i++) {
    const ph = (((t * 0.55 + i * 0.5) % 1) + 1) % 1;
    thinRing(ctx, x, y, (10 + ph * 46) * U, BRAND, (1 - ph) * 0.75, 2 * U);
  }
  const g = ctx.createRadialGradient(x, y, 0, x, y, 30 * U);
  g.addColorStop(0, 'rgba(255,60,76,0.55)'); g.addColorStop(1, 'rgba(255,40,56,0)');
  ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, 30 * U, 0, TAU); ctx.fill();
  thinRing(ctx, x, y, 14 * U, WARM, 0.95, 2.2 * U);
  ctx.fillStyle = '#ff2d44'; ctx.beginPath(); ctx.arc(x, y, 6.2 * U, 0, TAU); ctx.fill();
  ctx.restore();
}
function brackets(ctx, x, y, s, c, a, lw) {
  ctx.save(); ctx.strokeStyle = col(c, a); ctx.lineWidth = lw; ctx.lineCap = 'round';
  const k = s * 0.45;
  for (const [sx, sy] of [[-1, -1], [1, -1], [-1, 1], [1, 1]]) {
    ctx.beginPath(); ctx.moveTo(x + sx * s, y + sy * (s - k)); ctx.lineTo(x + sx * s, y + sy * s); ctx.lineTo(x + sx * (s - k), y + sy * s); ctx.stroke();
  }
  ctx.restore();
}
function cornerFrame(ctx, W, H, m, len, a, lw) {
  ctx.save(); ctx.globalAlpha = a; ctx.strokeStyle = col(WARM, 0.8); ctx.lineWidth = lw; ctx.lineCap = 'round';
  for (const [x, y, sx, sy] of [[m, m, 1, 1], [W - m, m, -1, 1], [m, H - m, 1, -1], [W - m, H - m, -1, -1]]) {
    ctx.beginPath(); ctx.moveTo(x, y + sy * len); ctx.lineTo(x, y); ctx.lineTo(x + sx * len, y); ctx.stroke();
  }
  ctx.restore();
}

/* -------------------------------------------------------------- colour grade */
// filmic finishing: lifted blacks (matte), cool shadows / warm highlights, gentle saturation, dithering
function gradePass(ctx, W, H, frame, o) {
  const img = ctx.getImageData(0, 0, W, H), d = img.data;
  const r = rng(frame * 2654435761 + 11);
  const sat = o.sat === undefined ? 1.08 : o.sat, lift = o.lift === undefined ? 0.012 : o.lift, con = o.con === undefined ? 1.06 : o.con;
  const sh = o.shadow || [0.00, 0.012, 0.034], hi = o.high || [0.030, 0.012, -0.020];
  const grainA = o.grain === undefined ? 1.6 : o.grain;
  for (let i = 0; i < d.length; i += 4) {
    let R = d[i] / 255, G = d[i + 1] / 255, B = d[i + 2] / 255;
    const L = 0.2126 * R + 0.7152 * G + 0.0722 * B;
    // contrast around mid grey, saturation
    R = (R - 0.5) * con + 0.5; G = (G - 0.5) * con + 0.5; B = (B - 0.5) * con + 0.5;
    const L2 = 0.2126 * R + 0.7152 * G + 0.0722 * B;
    R = L2 + (R - L2) * sat; G = L2 + (G - L2) * sat; B = L2 + (B - L2) * sat;
    // split-tone
    const ws = 1 - Math.min(1, L * 3.2), wh = Math.max(0, L * 1.6 - 0.45);
    R += sh[0] * ws + hi[0] * wh; G += sh[1] * ws + hi[1] * wh; B += sh[2] * ws + hi[2] * wh;
    R += lift * (1 - R); G += lift * (1 - G); B += lift * (1 - B);
    const n = (r() - 0.5) * grainA / 255 * 2;
    d[i] = Math.max(0, Math.min(255, (R + n) * 255)); d[i + 1] = Math.max(0, Math.min(255, (G + n) * 255)); d[i + 2] = Math.max(0, Math.min(255, (B + n) * 255));
  }
  ctx.putImageData(img, 0, 0);
}
