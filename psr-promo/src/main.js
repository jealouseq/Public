'use strict';
const Q = new URLSearchParams(location.search);
const W = +Q.get('w') || 1080, H = +Q.get('h') || 1080;
const canvas = document.getElementById('c'); canvas.width = W; canvas.height = H;
const ctx = canvas.getContext('2d', { alpha: false });
const gcan = document.createElement('canvas'); gcan.width = W; gcan.height = H;
const gctx = gcan.getContext('2d');
window.READY = false;
(async () => {
  await Promise.all([
    document.fonts.load('800 40px Montserrat', 'Йцукен ЦПР'), document.fonts.load('700 40px Montserrat', 'Йцукен'),
    document.fonts.load('600 30px Manrope', 'Йцукен'), document.fonts.load('700 30px Manrope', 'Йцукен'),
    document.fonts.load('500 20px "JetBrains Mono"', 'Йцукен 0123'), document.fonts.load('400 40px "Russo One"', 'ЦПР'),
  ]);
  await loadData();
  initStars(); initSearchPoints(); buildCameraPath(W, H);
  window.READY = true;
})();
window.renderFrame = (i, quality) => {
  const t = i / TL.fps;
  drawScene(ctx, gctx, W, H, t, i);
  return canvas.toDataURL('image/jpeg', quality || 0.93);
};
