#!/usr/bin/env python3
"""Rank candidate tracks for the 32 s ad.

For every track: estimate the beat grid, then look for a 32 s window whose
  * strong beats land on the two big visual moments (11.667 s terrain bloom, 25.0 s emblem lock;
    13.333 s apart = k beats, so a tiny tempo change makes both hit the grid),
  * loudness arc follows the film (quiet start, lift to the bloom, calmer scanning, climb to the logo).
Prints the best window per track.  python3 -I tools/pick_music.py audio/candidates/*.mp3
"""
import json, subprocess, sys, os
import numpy as np

CUE1, CUE2, DUR = 11.667, 25.0, 32.0
HERE = os.path.dirname(os.path.abspath(__file__))
try: cat = {p["filename"]: p for p in json.load(open(os.path.join(HERE, "..", "audio", "candidates", "pieces.json")))}
except Exception: cat = {}

ARC_T = np.array([0, 1.5, 6.0, 9.4, 11.55, 11.667, 13, 19.5, 20.0, 22, 24.8, 25.0, 28, 32])
ARC_G = np.array([0.35, 0.5, 0.62, 0.72, 0.78, 1.0, 0.82, 0.86, 0.78, 0.84, 0.9, 1.0, 0.92, 0.85])
ARC_DB = 20 * np.log10(ARC_G)
SR = 22050; HOP = 256; NFFT = 1024

def load(path):
    raw = subprocess.check_output(["ffmpeg", "-v", "error", "-i", path, "-ac", "1", "-ar", str(SR), "-f", "f32le", "-"])
    return np.frombuffer(raw, dtype="<f4")

def onset_env(x):
    n = 1 + (len(x) - NFFT) // HOP
    idx = np.arange(NFFT)[None, :] + HOP * np.arange(n)[:, None]
    spec = np.abs(np.fft.rfft(x[idx] * np.hanning(NFFT), axis=1))
    lm = np.log1p(spec * 20)
    flux = np.maximum(0, lm[1:] - lm[:-1]).sum(axis=1)
    flux = np.concatenate([[0], flux])
    flux -= np.convolve(flux, np.ones(43) / 43, mode="same")   # local mean removal
    return np.maximum(flux, 0)

def beat_grid(env, bpm0):
    fps = SR / HOP; best = (-1, None, None)
    rng_bpm = np.arange(bpm0 * 0.94, bpm0 * 1.06, 0.1) if bpm0 else np.arange(66, 132, 0.25)
    for bpm in rng_bpm:
        P = 60.0 / bpm * fps
        for ph in np.arange(0, P, 0.5):
            pos = np.arange(ph, len(env) - 1, P)
            s = np.interp(pos, np.arange(len(env)), env).sum() / len(pos)
            s *= 1 - 0.003 * max(0, bpm - 100)
            if s > best[0]: best = (s, bpm, ph)
    _, bpm, ph = best
    P = 60.0 / bpm * fps
    beats = np.arange(ph, len(env) - 1, P) / fps
    return bpm, beats

def rms_db(x, win=0.5):
    n = int(win * SR); m = len(x) // n
    r = np.sqrt((x[:m * n].reshape(m, n) ** 2).mean(axis=1) + 1e-12)
    return 20 * np.log10(r), win

def analyse(path):
    name = os.path.basename(path); meta = cat.get(name, {"bpm": None, "title": os.path.splitext(name)[0]})
    x = load(path); dur = len(x) / SR
    env = onset_env(x); bpm, beats = beat_grid(env, float(meta["bpm"]) if meta["bpm"] else None)
    P = 60.0 / bpm
    k = max(1, round((CUE2 - CUE1) / P))
    P_target = (CUE2 - CUE1) / k
    f = P / P_target                      # tempo factor (atempo): >1 = faster
    db, w = rms_db(x)
    envn = env / (np.percentile(env, 99) + 1e-9)
    fps = SR / HOP
    res = []
    for bi, b in enumerate(beats):
        s_in = b - CUE1 * f
        if s_in < 0 or s_in + DUR * f > dur: continue
        # onset strength at both cue beats (strong beats get a bonus)
        def strength(t):
            i = int(t * fps); return envn[max(0, i - 3):i + 4].max()
        o1, o2 = strength(b), strength(b + k * P)
        # loudness arc over the window (output time axis)
        tt = np.arange(0.25, DUR, 0.5)
        src = (s_in + tt * f) / w
        seg = np.interp(src, np.arange(len(db)), db)
        seg_s = np.convolve(seg, np.ones(3) / 3, mode="same")
        tgt = np.interp(tt, ARC_T, ARC_DB)
        c = np.corrcoef(seg_s, tgt)[0, 1]
        span = seg_s.max() - seg_s.min()
        # the first 4 s should be clearly quieter than the climaxes, and the end shouldn't die
        q = seg_s[:8].mean() - seg_s.mean()
        score = 0.55 * c + 0.12 * min(o1, 1.5) + 0.12 * min(o2, 1.5) - 3.0 * abs(f - 1) + 0.05 * min(span, 12) / 12 - 0.01 * max(0, q + 2)
        res.append((score, s_in, c, o1, o2, span, seg_s))
    res.sort(key=lambda r: -r[0])
    return dict(name=name, title=meta["title"], bpm=bpm, f=f, k=k, dur=dur, best=res[0] if res else None)

if __name__ == "__main__":
    rows = []
    for p in sys.argv[1:]:
        try: rows.append(analyse(p))
        except Exception as e: print("skip", p, e)
    rows = [r for r in rows if r["best"]]
    rows.sort(key=lambda r: -r["best"][0])
    print(f"{'track':34s} {'bpm':>6s} {'tempo':>7s} {'start':>7s} {'arc r':>6s} {'beat1':>6s} {'beat2':>6s} {'score':>6s}")
    for r in rows:
        sc, s_in, c, o1, o2, span, seg = r["best"]
        print(f"{r['title'][:34]:34s} {r['bpm']:6.1f} {100*(r['f']-1):+6.1f}% {s_in:6.1f}s {c:6.2f} {o1:6.2f} {o2:6.2f} {sc:6.2f}")
    json.dump([{k: (v if k != 'best' else [float(x) for x in v[:6]]) for k, v in r.items()} for r in rows], open(os.path.join(HERE, "..", "audio", os.environ.get("RANK_OUT", "candidates/ranking.json")), "w"), indent=1, default=float)
