'use strict';
/* Static Telegram post: "Рассвет" planet backdrop + typography. Params via window.renderPost(opts). */
const Q = new URLSearchParams(location.search);
const W = +Q.get('w') || 1080, H = +Q.get('h') || 1350;
const canvas = document.getElementById('c'); canvas.width = W; canvas.height = H;
const ctx = canvas.getContext('2d', { alpha: false, willReadFrequently: true });
const gcan = document.createElement('canvas'); gcan.width = W; gcan.height = H;
const gctx = gcan.getContext('2d');
window.READY = false;
(async () => {
  await Promise.all([
    document.fonts.load('800 40px Montserrat', 'Йцукен ЦПР'), document.fonts.load('700 40px Montserrat', 'Йцукен'), document.fonts.load('600 40px Montserrat', 'Йцукен'),
    document.fonts.load('500 30px Manrope', 'Йцукен'), document.fonts.load('600 30px Manrope', 'Йцукен'), document.fonts.load('700 30px Manrope', 'Йцукен'),
  ]);
  await loadData(); await loadData2();
  initStars(); initSearchPoints(); initMotes(); buildCameraPath(W, H);
  window.READY = true;
})();

// planet + sky only (no copy); o.t picks the moment of the film's opening shot, o.dy shifts the horizon, o.cam overrides camera fields
function backdrop(o) {
  const t = o.t, S = sceneState(t, W, H);
  Object.assign(S.cam, o.cam || {});
  S.cam.shiftY += (o.dy || 0) * H; S.cam.shiftX = (o.dx || 0) * W;
  if (o.sunEl !== undefined) {              // sun placed relative to the camera's sub-point: elevation (deg) above the local horizontal, azimuth = heading + offset
    const c = S.cam, u = llv(c.lon, c.lat), clon = c.lon * D2R, clat = c.lat * D2R;
    const E = [-Math.sin(clon), Math.cos(clon), 0], N = [-Math.sin(clat) * Math.cos(clon), -Math.sin(clat) * Math.sin(clon), Math.cos(clat)];
    const hd = (c.heading + (o.sunHd || 0)) * D2R, el = o.sunEl * D2R, h = add3(mul3(N, Math.cos(hd)), mul3(E, Math.sin(hd)));
    S.sun = norm3(add3(mul3(u, Math.sin(el)), mul3(h, Math.cos(el))));
  }
  const cam = makeCamera(S.cam, W, H);
  const bg = ctx.createLinearGradient(0, 0, 0, H); bg.addColorStop(0, '#02040a'); bg.addColorStop(1, '#050a16');
  ctx.globalCompositeOperation = 'source-over'; ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
  drawStars(ctx, cam, t, 1);
  renderEarth2(gctx, W, H, cam, S); ctx.drawImage(gctx.canvas, 0, 0);
  drawFlare(ctx, cam, S.sun, o.flare === undefined ? 1 : o.flare, t);
  bloomPass(ctx, W, H, 0.32, 5);
}
window.renderBackdrop = (o, q) => { backdrop(o); gradePass(ctx, W, H, 1, { sat: 1.04, con: 1.07, grain: 2.4 }); vignette(ctx, W, H, 0.42); return canvas.toDataURL('image/jpeg', q || 0.95); };

/* ------------------------------------------------------------- the post */
const POST = {
  eyebrow: 'ПАМЯТКА ДЛЯ СЕМЬИ',
  h1: ['Если нет вестей —', '*с чего начать*'],
  steps: ['Сохраните переписку и номера', 'Соберите сведения о бойце', 'Обратитесь в часть и военкомат', 'Не публикуйте личные данные', 'Берегите себя и не оставайтесь одни'],
};
function glowText(txt, x, y, o) {            // text with a soft dark halo (legibility over bright planet) + optional coloured glow
  ctx.save();
  ctx.font = `${o.weight} ${o.size}px ${o.family || 'Montserrat'}`; ctx.letterSpacing = (o.tracking || 0) + 'px'; ctx.textAlign = o.align || 'left'; ctx.textBaseline = 'alphabetic';
  ctx.shadowColor = 'rgba(2,4,10,0.75)'; ctx.shadowBlur = o.halo === undefined ? 22 : o.halo; ctx.shadowOffsetY = 2; ctx.fillStyle = o.color || '#fff'; ctx.globalAlpha = o.alpha === undefined ? 1 : o.alpha; ctx.fillText(txt, x, y);
  if (o.glow) { ctx.shadowColor = o.glow; ctx.shadowBlur = o.glowR || 26; ctx.shadowOffsetY = 0; ctx.globalAlpha *= 0.8; ctx.fillText(txt, x, y); }
  ctx.restore();
}
window.renderPost = (o, q) => {
  o = Object.assign({ t: 3.6, dy: -0.04 }, o || {});
  const U = W / 1080, M = 76 * U;
  backdrop(o);
  // legibility: soft scrim over the planet for the list, a light one at the top
  let g = ctx.createLinearGradient(0, H * 0.58, 0, H);
  g.addColorStop(0, 'rgba(3,6,14,0)'); g.addColorStop(0.28, 'rgba(3,6,14,0.55)'); g.addColorStop(1, 'rgba(3,6,14,0.90)');
  ctx.fillStyle = g; ctx.fillRect(0, H * 0.58, W, H * 0.42);
  g = ctx.createLinearGradient(0, 0, 0, H * 0.16); g.addColorStop(0, 'rgba(3,6,14,0.62)'); g.addColorStop(1, 'rgba(3,6,14,0)');
  ctx.fillStyle = g; ctx.fillRect(0, 0, W, H * 0.16);
  gradePass(ctx, W, H, 7, { sat: 1.04, con: 1.07, grain: 2.4 });
  vignette(ctx, W, H, 0.38);
  drawBrandBug(ctx, 0, W, H, U, 1);
  // ---- headline block
  const yE = o.yE || 252 * U;
  ctx.save(); ctx.strokeStyle = GOLDHEX; ctx.lineWidth = 2.4 * U; ctx.globalAlpha = 0.9; ctx.beginPath(); ctx.moveTo(M, yE - 10 * U); ctx.lineTo(M + 56 * U, yE - 10 * U); ctx.stroke(); ctx.restore();
  glowText(POST.eyebrow, M + 78 * U, yE, { size: 27 * U, weight: 700, tracking: 6.5 * U, color: GOLDHEX, family: 'Montserrat', halo: 14 });
  glowText(POST.h1[0], M - 3 * U, yE + 118 * U, { size: 90 * U, weight: 800, tracking: -1.2 * U, color: '#fff7ee' });
  glowText('с чего начать', M - 3 * U, yE + 230 * U, { size: 108 * U, weight: 800, tracking: -1.5 * U, color: GOLDHEX, glow: 'rgba(255,170,70,0.45)', glowR: 30 * U });
  // ---- steps
  const rows = POST.steps.length, y0 = o.y0 || H - 82 * U - (rows - 1) * 84 * U, pitch = 84 * U;
  for (let i = 0; i < rows; i++) {
    const y = y0 + i * pitch, cx = M + 27 * U;
    if (i) { ctx.save(); ctx.strokeStyle = 'rgba(255,240,220,0.13)'; ctx.lineWidth = 1.5 * U; ctx.beginPath(); ctx.moveTo(M, y - 54 * U); ctx.lineTo(W - M, y - 54 * U); ctx.stroke(); ctx.restore(); }
    ctx.save();
    const rg = ctx.createRadialGradient(cx, y - 11 * U, 0, cx, y - 11 * U, 40 * U); rg.addColorStop(0, 'rgba(255,190,110,0.22)'); rg.addColorStop(1, 'rgba(255,190,110,0)');
    ctx.fillStyle = rg; ctx.beginPath(); ctx.arc(cx, y - 11 * U, 40 * U, 0, TAU); ctx.fill();
    ctx.strokeStyle = 'rgba(255,201,120,0.95)'; ctx.lineWidth = 2.2 * U; ctx.beginPath(); ctx.arc(cx, y - 11 * U, 27 * U, 0, TAU); ctx.stroke();
    ctx.restore();
    glowText(String(i + 1), cx, y - 1 * U, { size: 28 * U, weight: 700, color: GOLDHEX, align: 'center', halo: 0 });
    glowText(POST.steps[i], M + 76 * U, y, { size: 37 * U, weight: 600, family: 'Manrope', color: '#fff4e8', halo: 16 });
  }
  return canvas.toDataURL('image/jpeg', q || 0.95);
};
