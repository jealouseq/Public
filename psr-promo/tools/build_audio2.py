#!/usr/bin/env python3
"""Soundtrack v2 = licensed music bed (Mixkit, no attribution) + cinematic SFX pinned to picture events.

  music : Mixkit "Rebecca on the Shore" (Film Score · Hopeful · Piano, Strings · Documentary · Human Drama)
  sfx   : Mixkit Sound Effects (free licence): heartbeat, shimmer, swooshes, riser, impacts, light sparkles
Every sound is declared in SFX[] with the picture event it belongs to (aesthetic-rules S2) and is aligned by its
own transient (`peak`), its end (`end`, for risers) or its start.
Run: python3 -I tools/build_audio2.py [--music 545_Rebecca_on_the_Shore] -> audio/mix_v2.wav
"""
import json, os, subprocess, sys, wave
import numpy as np

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SR = 48000
TL = json.loads(subprocess.check_output(["node", "-e", f"console.log(JSON.stringify(require('{ROOT}/src/timeline.js')))"]))
DUR = TL["total"]; N = int(DUR * SR)
C = TL["cues"]; P = TL["pings"]
MUSIC = "545_Rebecca_on_the_Shore"
NO_MUSIC = "--no-music" in sys.argv
for i, a in enumerate(sys.argv):
    if a == "--music": MUSIC = sys.argv[i + 1]

def decode(path, start=None, dur=None, af=None):
    cmd = ["ffmpeg", "-v", "error"]
    if start is not None: cmd += ["-ss", f"{start:.4f}"]
    if dur is not None: cmd += ["-t", f"{dur:.4f}"]
    cmd += ["-i", path]
    if af: cmd += ["-af", af]
    cmd += ["-ac", "2", "-ar", str(SR), "-f", "f32le", "-"]
    return np.frombuffer(subprocess.check_output(cmd), dtype="<f4").reshape(-1, 2).T.astype(np.float64)

# ------------------------------------------------------------------ music bed
rank = {r["name"].replace(".mp3", ""): r for r in json.load(open(os.path.join(ROOT, "audio/mixkit/ranking.json")))}
r = rank[MUSIC]; s_in = r["best"][1]; f = r["f"]
af = (f"atempo={f:.5f},atrim=0:{DUR + 0.5},afade=t=in:st=0:d=1.4,afade=t=out:st={DUR - 3.0}:d=3.0,"
      "acompressor=threshold=-26dB:ratio=2.4:attack=60:release=500:makeup=5dB")
music = decode(os.path.join(ROOT, f"audio/mixkit/{MUSIC}.mp3"), start=s_in, dur=DUR * f + 1.0, af=af)
music = np.pad(music, ((0, 0), (0, max(0, N - music.shape[1]))))[:, :N]
music *= 10 ** (-19.5 / 20) / (np.sqrt((music ** 2).mean()) + 1e-9)      # music bed at about -19.5 dBFS RMS
t = np.arange(N) / SR
# a quiet opening has to stay audible on a phone: +5 dB until 5 s, easing out by 10 s
k = np.clip((t - 5.0) / 5.0, 0, 1); k = k * k * (3 - 2 * k)
music *= 10 ** (5.0 * (1 - k) / 20)

# --------------------------------------------------------------- SFX (declarative table)
SFXDIR = os.path.join(ROOT, "audio/sfx_src")
SFX = [
    # (file, event time s, gain, align, note, reverse)   reverse=True turns a decaying hit into a rising swell
    ("heartbeat-single",       0.55, 0.55, "peak",  "waiting: first heartbeat in the night", False),
    ("heartbeat-single",       2.15, 0.50, "peak",  "waiting: second heartbeat", False),
    ("heartbeat-single",       3.75, 0.40, "peak",  "waiting: third, fading as light appears", False),
    ("shimmer-sparkle-sweep",  3.00, 0.34, "peak",  "sun breaks over the limb", False),
    ("swoosh-slow",            5.90, 0.40, "peak",  "camera lifts away from the horizon to the full globe", False),
    ("sparkle-touch",          C["zone"], 0.30, "start", "search-zone ring appears on the globe", False),
    ("sparkle-touch",          7.80, 0.16, "start", "second ring pulse", False),
    ("riser-cine",             C["impact"] - 0.04, 0.50, "end", "reverse swell: the dive builds towards the bloom", True),
    ("swoosh-slow",            C["impact"] - 0.20, 0.34, "peak", "dive accelerates through the clouds", False),
    ("impact-deep-whoosh",     C["impact"], 0.62, "peak", "clouds part: terrain revealed", False),
    ("sparkle-poof-hit",       C["impact"] + 0.10, 0.22, "peak", "light across the revealed land", False),
    ("sparkle-touch",          P[1], 0.17, "start", "sonar ping 2", False),
    ("sparkle-touch",          P[2], 0.15, "start", "sonar ping 3", False),
    ("sparkle-touch",          P[3], 0.15, "start", "sonar ping 4", False),
    ("sparkle-wand",           17.55, 0.18, "start", "first search points lock", False),
    ("sparkle-touch",          P[4], 0.15, "start", "sonar ping 5", False),
    ("swoosh-slow",            C["pullback"] + 0.6, 0.44, "peak", "camera pulls back from the terrain", False),
    ("stardust-swish",         TL["arcStart"], 0.30, "start", "lines of light leave for the cities", False),
    ("light-aura",             C["logo"] - 0.02, 0.40, "end", "reverse swell: the globe condenses into the emblem", True),
    ("impact-movie-epic",      C["logo"], 0.52, "peak", "emblem locks", False),
    ("sparkle",                C["logo"] + 0.10, 0.30, "peak", "light settles on the emblem", False),
    ("transition-soft",        C["wordmark"] - 0.10, 0.22, "peak", "wordmark appears", False),
    ("sparkle-touch",          C["cta"], 0.20, "start", "call to action", False),
    ("sparkle-touch",          C["cta"] + 1.6, 0.12, "start", "contact line", False),
]
def envelope_peak(x):
    m = np.abs(x).max(axis=0); w = int(0.012 * SR)
    sm = np.convolve(m, np.ones(w) / w, mode="same")
    return int(np.argmax(sm))
sfx = np.zeros((2, N))
for name, ev, g, align, note, rev in SFX:
    x = decode(os.path.join(SFXDIR, name + ".mp3"))
    if rev: x = x[:, ::-1].copy()
    off = {"start": 0, "peak": envelope_peak(x), "end": x.shape[1]}[align]
    i0 = int(round(ev * SR)) - off
    a, b = max(0, i0), min(N, i0 + x.shape[1])
    if b <= a: continue
    seg = x[:, a - i0:b - i0].copy()
    fo = min(seg.shape[1], int(0.25 * SR)); seg[:, -fo:] *= np.linspace(1, 0, fo)       # no clicks at sample end
    sfx[:, a:b] += seg * g

# music ducks slightly under the two big impacts so they read clearly
duck = np.ones(N)
for ev, depth, ln in ((C["impact"], 0.38, 0.9), (C["logo"], 0.38, 1.1)):
    i = int(ev * SR); a, b = max(0, i - int(0.08 * SR)), min(N, i + int(ln * SR))
    ramp = np.concatenate([np.linspace(1, 1 - depth, int(0.08 * SR) * 2 if False else int(0.08 * SR)), np.linspace(1 - depth, 1, b - a - int(0.08 * SR))])
    duck[a:b] *= ramp[:b - a]
music *= duck
if NO_MUSIC: music *= 0.0

mix = music + sfx
mix = np.tanh(mix * 1.05) / 1.05
mix *= np.minimum(1, (DUR - t) / 1.6) ** 1.2
mix /= max(1.0, np.abs(mix).max() / 0.93)
out = os.path.join(ROOT, "audio", "mix_v2_no_music.wav" if NO_MUSIC else "mix_v2.wav")
with wave.open(out, "wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes((mix.T * 32767).astype("<i2").tobytes())
print("wrote", out, f"music={MUSIC} start {s_in:.1f}s tempo {100 * (f - 1):+.2f}%", "peak", float(np.abs(mix).max()))
