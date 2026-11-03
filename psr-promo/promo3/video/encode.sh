#!/usr/bin/env bash
# bash promo3/video/encode.sh FRAMES_DIR NAME [BR]   -> promo3/out/NAME.mp4 (master) and NAME_compact.mp4
set -euo pipefail
cd "$(dirname "$0")/../.."
FR=$1; NAME=$2; BR=${3:-2300k}; A=promo3/video/mix.wav; FPS=30
VF="scale=out_color_matrix=bt709:out_range=tv,format=yuv420p"; COL="-colorspace bt709 -color_primaries bt709 -color_trc bt709"
I=$(ffmpeg -hide_banner -nostats -i "$A" -af ebur128 -f null - 2>&1 | awk '/I:/ {v=$2} END {print v}')
GAIN=$(awk -v i="$I" 'BEGIN{printf "%.2f", -14.5 - i}'); AF="volume=${GAIN}dB,alimiter=limit=0.7:level=disabled,aresample=48000"
echo "mix ${I} LUFS -> gain ${GAIN} dB"
mkdir -p promo3/out
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -i "$A" -vf "$VF" $COL -c:v libx264 -preset slow -crf 17 -profile:v high -level 4.2 -r $FPS -af "$AF" -c:a aac -b:a 192k -movflags +faststart -shortest "promo3/out/${NAME}.mp4"
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -vf "$VF" $COL -c:v libx264 -preset slower -b:v $BR -maxrate 3800k -bufsize 6400k -profile:v high -level 4.2 -r $FPS -pass 1 -passlogfile promo3/out/x264 -an -f mp4 /dev/null
ffmpeg -y -hide_banner -loglevel error -framerate $FPS -i "$FR/f_%05d.jpg" -i "$A" -vf "$VF" $COL -c:v libx264 -preset slower -b:v $BR -maxrate 3800k -bufsize 6400k -profile:v high -level 4.2 -r $FPS -pass 2 -passlogfile promo3/out/x264 -af "$AF" -c:a aac -b:a 192k -movflags +faststart -shortest "promo3/out/${NAME}_compact.mp4"
rm -f promo3/out/x264*
ls -la promo3/out/${NAME}*.mp4
