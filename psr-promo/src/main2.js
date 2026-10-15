'use strict';
const Q = new URLSearchParams(location.search);
const W = +Q.get('w') || 1080, H = +Q.get('h') || 1080;
const canvas = document.getElementById('c'); canvas.width = W; canvas.height = H;
const ctx = canvas.getContext('2d', { alpha: false, willReadFrequently: true });
const gcan = document.createElement('canvas'); gcan.width = W; gcan.height = H;
const gctx = gcan.getContext('2d');
window.READY = false;
(async () => {
  await Promise.all([
    document.fonts.load('800 40px Montserrat', 'Йцукен ЦПР'), document.fonts.load('700 40px Montserrat', 'Йцукен'), document.fonts.load('600 40px Montserrat', 'Йцукен'),
    document.fonts.load('600 30px Manrope', 'Йцукен'), document.fonts.load('700 30px Manrope', 'Йцукен'),
    document.fonts.load('500 20px "JetBrains Mono"', 'Йцукен 0123'), document.fonts.load('400 40px "Russo One"', 'ЦПР'),
  ]);
  await loadData(); await loadData2();
  initStars(); initSearchPoints(); initMotes(); buildCameraPath(W, H);
  window.READY = true;
})();
// temporal supersampling (motion blur) only where the camera moves fast
const BLUR_WINDOWS = [[9.2, 12.8], [19.8, 24.2]];
const blurSamples = (t) => (BLUR_WINDOWS.some(([a, b]) => t >= a && t <= b) ? 5 : 1);
window.renderFrame = (i, quality) => {
  const t = i / TL.fps, n = blurSamples(t);
  if (n === 1) { drawScene(ctx, gctx, W, H, t, i); return canvas.toDataURL('image/jpeg', quality || 0.93); }
  const acc = new Float32Array(W * H * 4), shutter = 0.8 / TL.fps;      // ~290 degree shutter
  for (let k = 0; k < n; k++) {
    drawScene(ctx, gctx, W, H, t + ((k + 0.5) / n - 0.5) * shutter, i);
    const d = ctx.getImageData(0, 0, W, H).data;
    for (let j = 0; j < d.length; j++) acc[j] += d[j];
  }
  const out = ctx.createImageData(W, H);
  for (let j = 0; j < acc.length; j++) out.data[j] = acc[j] / n;
  ctx.putImageData(out, 0, 0);
  return canvas.toDataURL('image/jpeg', quality || 0.93);
};
