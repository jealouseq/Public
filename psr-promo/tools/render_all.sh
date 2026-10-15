#!/usr/bin/env bash
# full render of both formats (square master + vertical), sequentially
set -e
cd "$(dirname "$0")/.."
export NODE_PATH=${NODE_PATH:-/opt/node22/lib/node_modules}
rm -rf out/frames3 out/frames3_v
node tools/render.js seq --out out/frames3 --workers 4 --q 0.95
node tools/render.js seq --w 1080 --h 1920 --out out/frames3_v --workers 4 --q 0.95
echo ALLDONE
