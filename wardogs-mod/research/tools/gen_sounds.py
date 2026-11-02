#!/usr/bin/env python3
"""Synthesise the weapon shot sounds (no recordings, no third-party audio): noise crack + filtered body + low thump + a reverberant tail,
shaped per weapon class, written as OGG Vorbis through ffmpeg.

usage: gen_sounds.py <assets/wardogs dir>
"""
import json, os, subprocess, sys, tempfile
import numpy as np
from scipy.io import wavfile
from scipy.signal import lfilter

OUT = sys.argv[1]
SR = 44100
rng = np.random.default_rng(20260211)

def lowpass(x, fc):
    a = np.exp(-2 * np.pi * fc / SR)
    return lfilter([1 - a], [1, -a], x)

def highpass(x, fc):
    return x - lowpass(x, fc)

def env(n, tau):
    t = np.arange(n) / SR
    return np.exp(-t / tau)

def shot(dur, crack, crack_tau, body_fc, body_tau, thump_hz, thump_tau, thump_gain, tail_gain, tail_tau, tail_fc=900, boom=0.0):
    n = int(SR * dur)
    t = np.arange(n) / SR
    noise = lambda: rng.standard_normal(n)
    y = crack * highpass(noise(), 2500) * env(n, crack_tau)
    y += 0.9 * lowpass(noise(), body_fc) * env(n, body_tau)
    f = thump_hz * (1 + 1.8 * np.exp(-t / 0.02))         # a falling sine: the muzzle thump
    y += thump_gain * np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, thump_tau)
    y += tail_gain * lowpass(noise(), tail_fc) * (1 - np.exp(-t / 0.004)) * env(n, tail_tau)   # room / distance tail
    if boom:
        y += boom * lowpass(noise(), 220) * env(n, 0.25)
    y[:int(0.0008 * SR)] *= np.linspace(0, 1, int(0.0008 * SR))
    y = np.tanh(1.6 * y)                                    # a little saturation
    return y / np.max(np.abs(y)) * 0.89

def click(f1, f2, dur=0.12, noise_gain=0.5):
    n = int(SR * dur); t = np.arange(n) / SR
    y = noise_gain * highpass(rng.standard_normal(n), 3000) * env(n, 0.003)
    y += np.sin(2 * np.pi * f1 * t) * env(n, 0.012) * 0.8 + np.sin(2 * np.pi * f2 * t) * env(n, 0.008) * 0.5
    return np.tanh(1.5 * y) / 1.2 * 0.8

def explosion():
    n = int(SR * 3.0); t = np.arange(n) / SR
    y = 1.0 * lowpass(rng.standard_normal(n), 1400) * env(n, 0.45)
    y += 1.2 * np.sin(2 * np.pi * np.cumsum(55 * (1 + 2.5 * np.exp(-t / 0.08))) / SR) * env(n, 0.55)
    y += 0.8 * highpass(rng.standard_normal(n), 2000) * env(n, 0.03)
    y += 0.5 * lowpass(rng.standard_normal(n), 500) * (1 - np.exp(-t / 0.01)) * env(n, 1.2)
    y[:int(0.001 * SR)] *= np.linspace(0, 1, int(0.001 * SR))
    y = np.tanh(1.5 * y)
    return y / np.max(np.abs(y)) * 0.9

def impact(fc, tau, thump_hz=0, thump_gain=0.0, ring_hz=0, ring_gain=0.0, dur=0.3):
    n = int(SR * dur); t = np.arange(n) / SR
    y = lowpass(rng.standard_normal(n), fc) * env(n, tau)
    if thump_hz:
        y += thump_gain * np.sin(2 * np.pi * thump_hz * t) * env(n, tau * 1.5)
    if ring_hz:
        y += ring_gain * (np.sin(2 * np.pi * ring_hz * t) + 0.5 * np.sin(2 * np.pi * ring_hz * 2.76 * t)) * env(n, 0.06)
    y[:int(0.0005 * SR)] *= np.linspace(0, 1, int(0.0005 * SR))
    return np.tanh(1.4 * y) / np.max(np.abs(np.tanh(1.4 * y))) * 0.85

SOUNDS = {
    "impact_block": lambda: impact(2500, 0.018, dur=0.25),
    "impact_flesh": lambda: impact(900, 0.035, thump_hz=110, thump_gain=0.9, dur=0.3),
    "impact_metal": lambda: impact(5000, 0.012, ring_hz=1450, ring_gain=0.7, dur=0.45),
    "dry_fire": lambda: click(2200, 3500, 0.08, 0.3),
    "reload_out": lambda: click(900, 1700, 0.18, 0.6),
    "reload_in": lambda: np.concatenate([click(600, 1200, 0.1, 0.5), np.zeros(int(SR * 0.05)), click(1400, 2600, 0.1, 0.6)]),
    "explosion": explosion,
    "shot_pistol": lambda: shot(0.45, 1.1, 0.010, 3200, 0.035, 150, 0.04, 0.5, 0.10, 0.18),
    "shot_smg": lambda: shot(0.35, 1.0, 0.008, 3600, 0.028, 170, 0.03, 0.4, 0.07, 0.12),
    "shot_rifle": lambda: shot(0.7, 1.2, 0.012, 2600, 0.050, 120, 0.06, 0.7, 0.20, 0.30),
    "shot_lmg": lambda: shot(0.7, 1.1, 0.013, 2000, 0.065, 95, 0.08, 0.9, 0.22, 0.30, boom=0.2),
    "shot_sniper": lambda: shot(1.4, 1.4, 0.016, 2200, 0.080, 80, 0.12, 1.1, 0.45, 0.75, tail_fc=700, boom=0.35),
    "shot_shotgun": lambda: shot(0.9, 1.0, 0.020, 1500, 0.090, 85, 0.10, 1.0, 0.25, 0.40, boom=0.3),
    "shot_launcher": lambda: shot(1.6, 0.5, 0.030, 900, 0.20, 60, 0.30, 1.0, 0.60, 0.90, tail_fc=500, boom=0.6),
}

dest = os.path.join(OUT, "sounds", "shots")  # (reload, dry-fire and explosion sounds live here too)
os.makedirs(dest, exist_ok=True)
events = {}
for name, fn in SOUNDS.items():
    y = fn()
    with tempfile.TemporaryDirectory() as tmp:
        wav = os.path.join(tmp, name + ".wav")
        wavfile.write(wav, SR, (y * 32767).astype(np.int16))
        subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", wav, "-ac", "1", "-c:a", "libvorbis", "-q:a", "4", os.path.join(dest, name + ".ogg")], check=True)
    events[name] = {"sounds": [{"name": "wardogs:shots/" + name, "stream": False}], "subtitle": "subtitles.wardogs." + name}
json.dump(events, open(os.path.join(OUT, "sounds.json"), "w"), indent=1)
print(len(SOUNDS), "sounds written")
