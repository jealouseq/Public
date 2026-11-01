#!/usr/bin/env python3
"""Decode the Bakurani height raster (RG 16-bit) into a compact uint16 file and metre elevations.

Inputs (kept locally, not in git): local-data/bakurani/height_2048_rg16.webp
Output: local-data/bakurani/height_2048_u16.bin  (little endian uint16, row-major, 2048x2048, north at row 0)

Facts established from the community datasets (see research/DATA_SOURCES.md):
  elevation_m = 0.25993 * raw16 - 1008.34   [raw16 = R*256 + G of the webp; fitted on 557 markers, residual std 9 m]
  (the dem-manifest's own .bin uses a different raw range: 0.0170098 * raw - 1008.2656, same offset)
  each texel covers 7.96875 m; 2048 texels = 16320 m; image north is up.
"""
import sys, struct
from PIL import Image

src = sys.argv[1] if len(sys.argv) > 1 else "local-data/bakurani/height_2048_rg16.webp"
dst = sys.argv[2] if len(sys.argv) > 2 else "local-data/bakurani/height_2048_u16.bin"
im = Image.open(src).convert("RGB")
w, h = im.size
px = im.load()
with open(dst, "wb") as f:
    for y in range(h):
        f.write(struct.pack("<%dH" % w, *[(px[x, y][0] << 8) | px[x, y][1] for x in range(w)]))
print("wrote", dst, w, "x", h)
