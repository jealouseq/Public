'use strict';
/* ==========================================================================
   Earth v2 — photoreal day/night globe (NASA Blue Marble + Black Marble +
   real VIIRS clouds + regional 500 m imagery + SRTM relief).
   Depends on engine.js (math, camera, mips, DATA).
   ========================================================================== */
const D2 = { ready: false };
async function loadData2() {
  const get = (u) => fetch(u).then((r) => r.arrayBuffer());
  const [meta, day, clouds, region] = await Promise.all([fetch('/data/meta2.json').then((r) => r.json()), get('/data/day_rgb.u8'), get('/data/clouds.u8'), get('/data/region_rgb.u8')]);
  D2.meta = meta;
  D2.day = buildMipsRGB(new Uint8Array(day), meta.day.w, meta.day.h, 5);
  D2.clouds = buildMips(new Uint8Array(clouds), meta.clouds.w, meta.clouds.h, false);
  D2.region = buildMipsRGB(new Uint8Array(region), meta.region.w, meta.region.h, 4);
  D2.ready = true;
  // tone curve LUT: linear light 0..6 -> display 0..255 (filmic shoulder + sRGB-ish gamma)
  D2.lut = new Uint8Array(4097);
  for (let i = 0; i <= 4096; i++) {
    const x = (i / 4096) * 6;
    const y = (x * (1 + x / 7.5)) / (1 + x * 1.0);           // soft shoulder
    D2.lut[i] = Math.round(255 * Math.pow(clamp(y, 0, 1), 1 / 2.2));
  }
}
function buildMipsRGB(base, W, H, n) {
  const lv = [{ a: base, w: W, h: H }];
  for (let l = 1; l < n; l++) {
    const p = lv[l - 1], w = p.w >> 1, h = p.h >> 1, a = new Uint8Array(w * h * 3);
    for (let y = 0; y < h; y++) {
      for (let x = 0; x < w; x++) {
        const i = ((2 * y) * p.w + 2 * x) * 3, j = (y * w + x) * 3, s = p.w * 3;
        a[j] = (p.a[i] + p.a[i + 3] + p.a[i + s] + p.a[i + s + 3]) >> 2;
        a[j + 1] = (p.a[i + 1] + p.a[i + 4] + p.a[i + s + 1] + p.a[i + s + 4]) >> 2;
        a[j + 2] = (p.a[i + 2] + p.a[i + 5] + p.a[i + s + 2] + p.a[i + s + 5]) >> 2;
      }
    }
    lv.push({ a, w, h });
  }
  return lv;
}
const _c1 = new Float64Array(3), _c2 = new Float64Array(3);
function bilRGB(L, u01, v01, out, wrap) {
  const w = L.w, h = L.h, a = L.a;
  const u = u01 * w - 0.5, v = clamp(v01 * h - 0.5, 0, h - 1.001);
  let x0 = Math.floor(u), y0 = Math.floor(v);
  const fx = u - x0, fy = v - y0;
  let x1 = x0 + 1;
  if (wrap) { if (x0 < 0) x0 += w; if (x0 >= w) x0 -= w; if (x1 >= w) x1 = 0; }
  else { if (x0 < 0) x0 = 0; if (x1 > w - 1) x1 = w - 1; if (x0 > w - 1) x0 = w - 1; }
  const i00 = (y0 * w + x0) * 3, i10 = (y0 * w + x1) * 3, i11 = (y0 * w + w + x1) * 3;
  const j01 = ((y0 + 1) * w + x0) * 3;
  const w00 = (1 - fx) * (1 - fy), w10 = fx * (1 - fy), w01 = (1 - fx) * fy, w11 = fx * fy;
  out[0] = a[i00] * w00 + a[i10] * w10 + a[j01] * w01 + a[i11] * w11;
  out[1] = a[i00 + 1] * w00 + a[i10 + 1] * w10 + a[j01 + 1] * w01 + a[i11 + 1] * w11;
  out[2] = a[i00 + 2] * w00 + a[i10 + 2] * w10 + a[j01 + 2] * w01 + a[i11 + 2] * w11;
}
function sampleRGBLod(levels, lod, u01, v01, out, wrap) {
  const l0 = Math.min(levels.length - 1, Math.floor(lod)), l1 = Math.min(levels.length - 1, l0 + 1), t = lod - Math.floor(lod);
  bilRGB(levels[l0], u01, v01, out, wrap);
  if (l1 !== l0 && t > 0.03) {
    bilRGB(levels[l1], u01, v01, _c2, wrap);
    out[0] += (_c2[0] - out[0]) * t; out[1] += (_c2[1] - out[1]) * t; out[2] += (_c2[2] - out[2]) * t;
  }
}
const TONE = (x) => (x <= 0 ? 0 : x >= 6 ? 255 : D2.lut[(x * (4096 / 6)) | 0]);

/*
  S: sun (unit vec), zone {c,R,a,spin}, ripples [{r,a}], cloudA (0..1), lightsGain, exposure, haloGain
*/
function renderEarth2(ctxG, W, H, cam, S) {
  if (!_globeImg || _globeImg.width !== W || _globeImg.height !== H) _globeImg = ctxG.createImageData(W, H);
  const img = _globeImg, buf = new Uint32Array(img.data.buffer);
  buf.fill(0);
  const { pos, fwd, right, up, f, cx, cy, d } = cam;
  const c0 = pos[0] * pos[0] + pos[1] * pos[1] + pos[2] * pos[2] - 1;
  const lm = DATA.lightsMips, dayM = D2.day, cloudM = D2.clouds, regM = D2.region, R = D2.meta.region;
  const demA = DATA.dem, dm = DATA.meta.dem;
  const nW = D2.meta.day.w;
  const angpp = Math.max(1e-7, (d - 1) / f);
  const lodBase = clamp(Math.log2(angpp / (TAU / nW)) + 0.25, 0, 4.0);
  const regTexRad = (R.lon1 - R.lon0) / R.w * D2R;
  const lodReg0 = Math.log2(angpp / regTexRad) + 0.15;
  const regionStrength = 1 - smooth(5.5e-4, 1.3e-3, angpp);          // use the sharper regional texture when close enough
  const reliefStrength = 1 - smooth(2.2e-4, 7.5e-4, angpp);          // hillshade only in the deep zoom
  const kmPerPx = (d - 1) * 6371 / f;
  const sv = S.sun, Lx = sv[0], Ly = sv[1], Lz = sv[2];
  const cloudAlt = 1.0045, cloudK = cloudAlt * cloudAlt - 1;
  const cloudStrength = (S.cloudA === undefined ? 1 : S.cloudA) * 0.86 * smooth(0.2, 0.62, d - 1);
  const clear = S.cloudClear === undefined ? 0 : S.cloudClear;            // radius (rad) of the 'parted clouds' hole around the region
  const lightsGain = S.lightsGain === undefined ? 1 : S.lightsGain;
  const expo = S.exposure === undefined ? 1 : S.exposure;
  const haloGain = S.haloGain === undefined ? 1 : S.haloGain;
  const zone = S.zone, rip = S.ripples || [];
  const C = zone ? zone.c : [0, 0, 1];
  const clon0 = Math.atan2(C[1], C[0]), clat0 = Math.asin(C[2]);
  // sun in local east/north/up at the region centre (for hillshade)
  const E0 = [-Math.sin(clon0), Math.cos(clon0), 0], N0 = [-Math.sin(clat0) * Math.cos(clon0), -Math.sin(clat0) * Math.sin(clon0), Math.cos(clat0)];
  const sE = sv[0] * E0[0] + sv[1] * E0[1] + sv[2] * E0[2], sN = sv[0] * N0[0] + sv[1] * N0[1] + sv[2] * N0[2], sU = sv[0] * C[0] + sv[1] * C[1] + sv[2] * C[2];
  const haloR = d > 1.3 ? f * Math.tan(Math.asin(Math.min(0.999, 1.45 / d))) : 1e9;
  const x0 = haloR > 1e8 ? 0 : Math.max(0, Math.floor(cx - haloR * 1.6 - 4)), x1 = haloR > 1e8 ? W : Math.min(W, Math.ceil(cx + haloR * 1.6 + 4));
  const c = _c1;
  for (let y = 0; y < H; y++) {
    const sy = (cy - (y + 0.5)) / f;
    for (let x = x0; x < x1; x++) {
      const sx = (x + 0.5 - cx) / f;
      const dx = fwd[0] + sx * right[0] + sy * up[0], dy = fwd[1] + sx * right[1] + sy * up[1], dz = fwd[2] + sx * right[2] + sy * up[2];
      const a = dx * dx + dy * dy + dz * dz;
      const b = pos[0] * dx + pos[1] * dy + pos[2] * dz;
      const disc = b * b - a * c0;
      let oR = 0, oG = 0, oB = 0, oA = 0;
      if (disc > 0) {
        const sq = Math.sqrt(disc), t = (-b - sq) / a;
        const px = pos[0] + t * dx, py = pos[1] + t * dy, pz = pos[2] + t * dz;
        const invLen = 1 / Math.sqrt(a);
        const ndv = clamp(-(px * dx + py * dy + pz * dz) * invLen, 0.0005, 1);
        const lat = Math.asin(clamp(pz, -1, 1)), lon = Math.atan2(py, px);
        const u01 = (lon + PI) / TAU, v01 = (PI / 2 - lat) / PI;
        const graz = clamp(-Math.log2(ndv) * 0.9, 0, 2.5);
        const lod = lodBase + graz;
        // ---------- albedo (global Blue Marble, then sharper regional crop)
        sampleRGBLod(dayM, lod, u01, v01, c, true);
        let ar = c[0] * 0.003921569, ag = c[1] * 0.003921569, ab = c[2] * 0.003921569;
        let relief = 1;
        if (regionStrength > 0.002) {
          const rlon = lon / D2R, rlat = lat / D2R;
          const ru = (rlon - R.lon0) / (R.lon1 - R.lon0), rv = (R.lat1 - rlat) / (R.lat1 - R.lat0);
          if (ru > 0 && ru < 1 && rv > 0 && rv < 1) {
            const ed = smooth(0, 0.05, Math.min(ru, 1 - ru)) * smooth(0, 0.08, Math.min(rv, 1 - rv)) * regionStrength;
            if (ed > 0.002) {
              sampleRGBLod(regM, clamp(lodReg0 + graz, 0, 3), ru, rv, c, false);
              {
                let qr = c[0] * 0.003921569, qg = c[1] * 0.003921569, qb = c[2] * 0.003921569;
                const ql = 0.3 * qr + 0.59 * qg + 0.11 * qb, sat = 1.42;
                qr = ql + (qr - ql) * sat; qg = ql + (qg - ql) * sat; qb = ql + (qb - ql) * sat;
                qr = Math.pow(Math.max(qr, 0), 0.82) * 1.06; qg = Math.pow(Math.max(qg, 0), 0.82) * 1.04; qb = Math.pow(Math.max(qb, 0), 0.84);
                ar += (qr - ar) * ed; ag += (qg - ag) * ed; ab += (qb - ab) * ed;
              }
              if (reliefStrength > 0.002) {
                const du = (rlon - dm.lon0) / dm.step, dv = (dm.lat1 - rlat) / dm.step;
                const e0 = demBil(demA, dm.w, dm.h, du, dv);
                if (e0 !== null && e0 > 0.3) {
                  const exx = demBil(demA, dm.w, dm.h, du + 1.2, dv), emx = demBil(demA, dm.w, dm.h, du - 1.2, dv);
                  const eyy = demBil(demA, dm.w, dm.h, du, dv + 1.2), emy = demBil(demA, dm.w, dm.h, du, dv - 1.2);
                  if (exx !== null && emx !== null && eyy !== null && emy !== null) {
                    const cl = Math.cos(lat);
                    const dzdx = (exx - emx) / (2.4 * dm.step * 111320 * cl), dzdy = (emy - eyy) / (2.4 * dm.step * 110574);
                    const nx = -dzdx * 9, ny = -dzdy * 9, nzz = 1, inv = 1 / Math.sqrt(nx * nx + ny * ny + 1);
                    const sl = Math.max(0.28, sU);
                    const hs = ((nx * sE + ny * sN + nzz * sU) * inv) / sl;
                    const dedge = smooth(0, 380, Math.min(du, dm.w - du, dv, dm.h - dv));
                    relief = 1 + (clamp(hs, 0.35, 1.9) - 1) * reliefStrength * ed * dedge * 1.1;
                  }
                }
              }
            }
          }
        }
        // sRGB -> linear
        let lr = ar * ar * (0.45 + 0.55 * ar), lg = ag * ag * (0.45 + 0.55 * ag), lb = ab * ab * (0.45 + 0.55 * ab);
        lr *= relief; lg *= relief; lb *= relief;
        // ---------- lighting
        const diff = px * Lx + py * Ly + pz * Lz;
        const dayF = smooth(-0.07, 0.14, diff);
        const lit = diff > 0 ? Math.pow(diff, 0.72) : 0;
        const warm = Math.exp(-Math.pow((diff - 0.03) / 0.13, 2));
        const sunE = 0.16 + 1.15 * lit;
        // surface colour: sun-lit albedo with a golden twilight tint
        let r = lr * sunE * (1 + 0.55 * warm), g = lg * sunE * (1 + 0.05 * warm), bl = lb * sunE * (1 - 0.35 * warm);
        // night side: deep blue moonlight + city lights
        const night = 1 - dayF;
        const moon = 0.035 * night;
        const lumA = 0.3 * lr + 0.55 * lg + 0.15 * lb;
        r += (0.30 * lumA + 0.002) * moon * 6; g += (0.52 * lumA + 0.004) * moon * 6; bl += (1.0 * lumA + 0.010) * moon * 6;
        const Lt = sampleMip(lm, lod, u01, v01);
        if (Lt > 0.01 && night > 0.02) {
          const L = Math.min(1.5, Lt * lightsGain * 1.25) * night;
          const L2 = L * L;
          r += 1.55 * L + 0.5 * L2; g += 0.82 * L + 0.55 * L2 * L; bl += 0.28 * L2 + 0.45 * L2 * L2;
        }
        // ---------- ocean sun glint
        const oceanM = smooth(0.012, 0.07, ab - ar) * (1 - smooth(0.22, 0.45, lumA * 2.6));
        if (oceanM > 0.01 && dayF > 0.05) {
          const vx = -dx * invLen, vy = -dy * invLen, vz = -dz * invLen, nv = px * vx + py * vy + pz * vz;
          const rx = 2 * nv * px - vx, ry = 2 * nv * py - vy, rz = 2 * nv * pz - vz;
          const rs = rx * Lx + ry * Ly + rz * Lz;
          if (rs > 0.8) { const sp = Math.pow(rs, 140) * 2.6 + Math.pow(rs, 18) * 0.22; r += 1.0 * sp * oceanM * dayF; g += 0.86 * sp * oceanM * dayF; bl += 0.62 * sp * oceanM * dayF; }
        }
        // ---------- clouds (real VIIRS layer on a shell above the surface -> parallax) + their shadows
        if (cloudStrength > 0.01) {
          let hole = 1;
          if (clear > 0.001) { const ca2 = Math.acos(clamp(px * C[0] + py * C[1] + pz * C[2], -1, 1)); hole = smoother(clear * 0.12, clear, ca2); }
          const tc = (-b - Math.sqrt(Math.max(0, b * b - a * (c0 - cloudK)))) / a;
          const qx = pos[0] + tc * dx, qy = pos[1] + tc * dy, qz = pos[2] + tc * dz, ql = 1 / Math.sqrt(qx * qx + qy * qy + qz * qz);
          const nxq = qx * ql, nyq = qy * ql, nzq = qz * ql;
          const clat = Math.asin(clamp(nzq, -1, 1)), clon = Math.atan2(nyq, nxq);
          const cu = (clon + PI) / TAU, cv = (PI / 2 - clat) / PI;
          let Cc = sampleMip(cloudM, lod + 0.4, cu, cv) * cloudStrength * hole;
          // shadow on the ground: the cloud that shades this pixel sits displaced towards the sun
          if (dayF > 0.2 && cloudStrength > 0.3) {
            const dsun = nxq * Lx + nyq * Ly + nzq * Lz;
            const se = -Math.sin(clon) * Lx + Math.cos(clon) * Ly;
            const sn = -Math.sin(clat) * Math.cos(clon) * Lx - Math.sin(clat) * Math.sin(clon) * Ly + Math.cos(clat) * Lz;
            const kk = Math.min(0.03, 0.0045 * Math.sqrt(Math.max(0, 1 - dsun * dsun)) / Math.max(0.15, dsun));
            const sh = sampleMip(cloudM, lod + 0.4, u01 + (se / Math.max(0.2, Math.cos(clat))) * kk / TAU, v01 - (sn * kk) / PI) * cloudStrength * hole;
            const k = 1 - 0.40 * sh * dayF; r *= k; g *= k; bl *= k;
          }
          if (Cc > 0.01) {
            const cd = nxq * Lx + nyq * Ly + nzq * Lz;
            const cl2 = smooth(-0.14, 0.38, cd), cw = Math.exp(-Math.pow((cd - 0.02) / 0.12, 2));
            let kr = 0.05 + 1.12 * cl2 + 0.9 * cw, kg = 0.05 + 1.12 * cl2 + 0.38 * cw, kb = 0.06 + 1.12 * cl2 + 0.06 * cw;
            // night clouds: cold, dark, with a faint glow from the cities beneath
            const nc = 1 - cl2;
            kr += 0.004 * nc; kg += 0.007 * nc; kb += 0.014 * nc;
            const ca = Math.min(1, Cc * 0.95);
            r = r * (1 - ca) + kr * ca; g = g * (1 - ca) + kg * ca; bl = bl * (1 - ca) + kb * ca;
          }
        }
        // ---------- atmosphere: Rayleigh blue on the lit limb, amber at the terminator
        const rim = Math.pow(1 - ndv, 2.5);
        const rimLit = smooth(-0.22, 0.45, diff);
        r += (0.20 * rimLit + 1.0 * warm * 0.5) * rim * 1.25; g += (0.50 * rimLit + 0.52 * warm * 0.5) * rim * 1.25; bl += (1.15 * rimLit + 0.22 * warm * 0.5) * rim * 1.25;
        r += 0.018 * rim; g += 0.05 * rim; bl += 0.14 * rim * (1 - rimLit * 0.4);
        // ---------- search-zone ring and ripples: soft golden-white
        if (zone) {
          const cosA = px * C[0] + py * C[1] + pz * C[2];
          if (cosA > 0.6) {
            const ang = Math.acos(clamp(cosA, -1, 1));
            if (zone.a > 0.001) {
              const ring = Math.exp(-Math.pow((ang - zone.R) / (zone.R * 0.018 + 0.0006), 2));
              const inside = 1 - smooth(zone.R * 0.7, zone.R, ang);
              const brg = Math.atan2(px * E0[0] + py * E0[1] + pz * E0[2], px * N0[0] + py * N0[1] + pz * N0[2]);
              const dash = 0.5 + 0.5 * Math.sign(Math.sin(brg * 48 + (zone.spin || 0)));
              r += 0.55 * ring * (0.4 + 0.6 * dash) * zone.a; g += 0.50 * ring * (0.4 + 0.6 * dash) * zone.a; bl += 0.36 * ring * (0.4 + 0.6 * dash) * zone.a;
            }
            for (let i = 0; i < rip.length; i++) {
              const q = rip[i], rr = Math.exp(-Math.pow((ang - q.r) / (0.0022 + q.r * 0.011), 2)) * q.a;
              r += 0.65 * rr; g += 0.58 * rr; bl += 0.42 * rr;
            }
          }
        }
        oR = TONE(r * expo); oG = TONE(g * expo); oB = TONE(bl * expo); oA = 255;
      } else {
        // ---------- atmosphere halo around the planet
        const rho2 = (pos[0] * pos[0] + pos[1] * pos[1] + pos[2] * pos[2]) - (b * b) / a;
        if (rho2 < 2.1 && d > 1.05) {
          const rho = Math.sqrt(Math.max(1, rho2)), hgt = rho - 1;
          let I = 0.95 * Math.exp(-hgt / 0.0095) + 0.36 * Math.exp(-hgt / 0.034) + 0.11 * Math.exp(-hgt / 0.12);
          const k = -b / a, bx = pos[0] + k * dx, by = pos[1] + k * dy, bz = pos[2] + k * dz, il = 1 / Math.max(1e-6, Math.hypot(bx, by, bz));
          const dB = (bx * Lx + by * Ly + bz * Lz) * il;
          const sunSide = smooth(-0.40, 0.45, dB), wB = Math.exp(-Math.pow((dB - 0.02) / 0.2, 2));
          I *= (0.07 + 1.0 * sunSide) * haloGain;
          if (I > 0.003) {
            // blue when the sun is high, amber when it grazes the limb, deep blue-violet on the night side
            const hr = 0.16 + 0.95 * wB, hg = 0.42 + 0.20 * wB * 0.8, hb = 1.0 - 0.55 * wB;
            const al = clamp(I, 0, 1);
            oA = al * 255;
            oR = TONE((hr * (0.7 + I * 0.9)) * expo * 0.9); oG = TONE((hg * (0.7 + I * 0.9)) * expo * 0.9); oB = TONE((hb * (0.8 + I * 0.8)) * expo * 0.9);
          }
        }
      }
      if (oA > 0) buf[y * W + x] = (oA << 24) | ((oB | 0) << 16) | ((oG | 0) << 8) | (oR | 0);
    }
  }
  ctxG.putImageData(img, 0, 0);
}
