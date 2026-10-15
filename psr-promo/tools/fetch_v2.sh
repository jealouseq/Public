#!/usr/bin/env bash
# v2 imagery: NASA GIBS (public domain). Run from psr-promo/assets/v2
set -u
G="https://gibs.earthdata.nasa.gov/wms/epsg4326/best/wms.cgi?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&CRS=EPSG:4326&FORMAT=image/jpeg"
for d in 2026-09-14 2026-07-12 2026-08-20; do
  curl -sSL -m 250 -o "viirs_$d.jpg" "$G&LAYERS=VIIRS_SNPP_CorrectedReflectance_TrueColor&TIME=$d&BBOX=-90,-180,90,180&WIDTH=8192&HEIGHT=4096" && echo "viirs $d ok" &
done
curl -sSL -m 250 -o weld_test.jpg "$G&LAYERS=Landsat_WELD_CorrectedReflectance_TrueColor_Global_Annual&TIME=2010-01-01&BBOX=46.5,35,49.5,40&WIDTH=2000&HEIGHT=1200" && echo "weld ok" &
curl -sSL -m 250 -o bmng_region_test.jpg "$G&LAYERS=BlueMarble_NextGeneration&BBOX=40,22,56,54&WIDTH=6400&HEIGHT=3200" && echo "bmng region ok" &
wait
