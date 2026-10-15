#!/usr/bin/env bash
# Re-download every raw source used by the video (all open / public domain) and rebuild data/.
# Usage:  bash tools/fetch_assets.sh   (from psr-promo/)
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p assets/nasa assets/ne raw/tiles fonts data audio out

npm i --silent d3-geo@3.1.1 topojson-client@3.1.0 world-atlas@2.0.2 gsap@3.12.5   # world-atlas: 50m coastline

# NASA Black Marble 2016 (night lights, public domain) - https://visibleearth.nasa.gov/
curl -sSL -o assets/nasa/black_marble_2016_3km.jpg \
  "https://eoimages.gsfc.nasa.gov/images/imagerecords/144000/144898/BlackMarble_2016_3km.jpg"

# Natural Earth 10m vectors (public domain) - https://www.naturalearthdata.com/
NE="https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson"
curl -sSL -o assets/ne/rivers_10m.geojson  "$NE/ne_10m_rivers_lake_centerlines.geojson"
curl -sSL -o assets/ne/lakes_10m.geojson   "$NE/ne_10m_lakes.geojson"
curl -sSL -o assets/ne/places_10m.geojson  "$NE/ne_10m_populated_places_simple.geojson"

# AWS Terrain Tiles (Terrarium, SRTM-derived elevation) z=7 for lon 22..54, lat 40..56
python3 -I - <<'EOF'
import math
Z = 7
tx = lambda lon: int(math.floor((lon + 180) / 360 * 2 ** Z))
ty = lambda lat: int(math.floor((1 - math.log(math.tan(math.radians(lat)) + 1 / math.cos(math.radians(lat))) / math.pi) / 2 * 2 ** Z))
x0, x1, y0, y1 = tx(22), tx(54), ty(56), ty(40)
open("raw/tile_meta.txt", "w").write(f"{Z} {x0} {x1} {y0} {y1}\n")
with open("raw/tile_list.txt", "w") as f:
    for x in range(x0, x1 + 1):
        for y in range(y0, y1 + 1):
            f.write(f"https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{Z}/{x}/{y}.png raw/tiles/{Z}_{x}_{y}.png\n")
EOF
xargs -P 10 -L 1 sh -c 'curl -sS -m 40 --retry 3 -o "$1" "$0"' < raw/tile_list.txt

python3 -I tools/prep_assets.py     # -> data/*.u8, dem.i16, edge.u8, vectors.json
echo "assets ready (fonts are committed in fonts/ - Montserrat, Manrope, JetBrains Mono, Russo One; all SIL OFL)"
