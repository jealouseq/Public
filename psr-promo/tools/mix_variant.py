#!/usr/bin/env python3
"""Cut a licensed track to the film (window + tiny tempo change from ranking.json), layer our sound design, write a wav.
   python3 -I tools/mix_variant.py "Hero Down" audio/variants/A_hero_down.wav
"""
import json, os, subprocess, sys, wave
import numpy as np

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
title, out = sys.argv[1], sys.argv[2]
rank = {r["title"]: r for r in json.load(open(os.path.join(ROOT, "audio/candidates/ranking.json")))}
r = rank[title]; s_in = r["best"][1]; f = r["f"]
src = os.path.join(ROOT, "audio/candidates", r["name"])
tmp = out + ".music.wav"
af = f"atempo={f:.5f},atrim=0:32,afade=t=in:st=0:d=1.2,afade=t=out:st=29.6:d=2.4"
subprocess.check_call(["ffmpeg", "-v", "error", "-y", "-ss", f"{s_in:.3f}", "-t", f"{32 * f + 1:.3f}", "-i", src, "-af", af, "-ar", "48000", "-ac", "2", tmp])

def rd(p):
    w = wave.open(p); x = np.frombuffer(w.readframes(w.getnframes()), dtype="<i2").reshape(-1, w.getnchannels()).astype(np.float64) / 32768
    return x.T
m = rd(tmp); sfx = rd(os.path.join(ROOT, "audio/sfx.wav"))
N = 32 * 48000
m = np.pad(m, ((0, 0), (0, max(0, N - m.shape[1]))))[:, :N]; sfx = sfx[:, :N]
rms = np.sqrt((m ** 2).mean()); m *= 10 ** (-19 / 20) / rms            # music bed at -19 dBFS RMS
# the quiet opening must still be audible on a phone speaker: +6.5 dB until 5 s, easing to 0 dB by 10 s
t = np.arange(N) / 48000
k = np.clip((t - 5.0) / 5.0, 0, 1); k = k * k * (3 - 2 * k)
m *= 10 ** (6.5 * (1 - k) / 20)
mix = m + sfx * 0.85
mix = np.tanh(mix * 1.1) / 1.1
mix /= max(1.0, np.abs(mix).max() / 0.89)
with wave.open(out, "wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(48000); w.writeframes((mix.T * 32767).astype("<i2").tobytes())
os.remove(tmp)
print(f"{title}: start {s_in:.2f}s tempo {100 * (f - 1):+.2f}%  ->  {out}")
