#!/usr/bin/env bash
# frames + score.wav -> mp4 (master + compact Telegram version) + poster.
# Usage: bash tools/encode.sh [frames_dir] [name_suffix]      e.g. BR=2900k bash tools/encode.sh out/frames_v 1080x1920
set -euo pipefail
cd "$(dirname "$0")/.."
FR=${1:-out/frames}; SUF=${2:-1080x1080}; A=audio/score.wav; FPS=30
BR=${BR:-2150k}      # compact-version video bitrate (square: 2150k ~ 9.4 MB; vertical: use 2900k)
VF="scale=out_color_matrix=bt709:out_range=tv,format=yuv420p"
COL="-colorspace bt709 -color_primaries bt709 -color_trc bt709"

# loudness: bring the mix to -14 LUFS (typical for social feeds) with a true-peak safety limiter
I=$(ffmpeg -hide_banner -nostats -i "$A" -af ebur128 -f null - 2>&1 | awk '/I:/ {v=$2} END {print v}')
GAIN=$(awk -v i="$I" 'BEGIN{printf "%.2f", -14 - i}')
AF="volume=${GAIN}dB,alimiter=limit=0.89:level=disabled,aresample=48000"
echo "mix loudness ${I} LUFS -> gain ${GAIN} dB"

# 1) master quality
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -i "$A" -vf "$VF" $COL \
  -c:v libx264 -preset slow -crf 21 -profile:v high -level 4.1 -r $FPS -af "$AF" -c:a aac -b:a 192k \
  -movflags +faststart -shortest "out/cpr_promo_${SUF}.mp4"

# 2) compact version (< 10 MB: Telegram autoplays small videos in the feed without a tap), 2-pass
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -vf "$VF" $COL \
  -c:v libx264 -preset slower -b:v $BR -maxrate 3600k -bufsize 6000k -profile:v high -level 4.1 -r $FPS \
  -pass 1 -passlogfile out/x264 -an -f mp4 /dev/null
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -i "$A" -vf "$VF" $COL \
  -c:v libx264 -preset slower -b:v $BR -maxrate 3600k -bufsize 6000k -profile:v high -level 4.1 -r $FPS \
  -pass 2 -passlogfile out/x264 -af "$AF" -c:a aac -b:a 128k -movflags +faststart -shortest "out/cpr_promo_${SUF}_compact.mp4"
rm -f out/x264*

# 3) posters
ffmpeg -y -hide_banner -loglevel error -ss 28.9 -i "out/cpr_promo_${SUF}.mp4" -frames:v 1 -q:v 2 "out/poster_end_card_${SUF}.jpg"
ffmpeg -y -hide_banner -loglevel error -ss 12.6 -i "out/cpr_promo_${SUF}.mp4" -frames:v 1 -q:v 2 "out/poster_map_${SUF}.jpg"
ls -la out/*.mp4 out/*.jpg
