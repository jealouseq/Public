#!/usr/bin/env python3
"""Original soundtrack + sound design for the ad, synthesised with numpy (no samples, no licences).

D minor, 72 BPM. Structure follows src/timeline.js so every hit lands on a visual event:
  0-6.7 s   Dm       drone + shimmer, sparse bells        (orbital shot, "Когда нет вестей...")
  6.7-11.7  Bbmaj7   rising arpeggio + riser              (globe -> region)
  11.667    IMPACT   terrain blooms
  11.7-20   Gm7      scanning pulse, sonar pings          (tactical map)
  20-25     Bb       pull-back whoosh, hopeful            (arcs to cities)
  25.0      BOOM     emblem locks
  25-32     D major  warm resolution                      (logo + call to action)
Run: python3 -I tools/build_audio.py  ->  audio/score.wav (48 kHz, stereo)
"""
import json, math, os, subprocess, wave
import numpy as np

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SR = 48000
TL = json.loads(subprocess.check_output(["node", "-e", f"console.log(JSON.stringify(require('{ROOT}/src/timeline.js')))"]))
DUR = TL["total"]; N = int(DUR * SR)
BEAT = 60.0 / TL["bpm"]
rng = np.random.default_rng(7)

def midi(m): return 440.0 * 2 ** ((m - 69) / 12.0)
NOTE = {"C": 0, "D": 2, "E": 4, "F": 5, "G": 7, "A": 9, "B": 11}
def n(name):  # 'D3' 'Bb2' 'F#3'
    base = NOTE[name[0]]; i = 1
    while name[i] in "#b":
        base += 1 if name[i] == "#" else -1; i += 1
    return midi(base + 12 * (int(name[i:]) + 1))

dry = np.zeros((2, N), dtype=np.float64)
wet = np.zeros((2, N), dtype=np.float64)      # reverb send bus

def put(sig, start, gain=1.0, pan=0.0, send=0.3):
    """Mix a mono (or stereo 2xN) signal at `start` seconds. pan -1..1."""
    i0 = int(start * SR)
    if i0 >= N: return
    if sig.ndim == 1:
        a = (pan + 1) * math.pi / 4
        sig = np.stack([sig * math.cos(a), sig * math.sin(a)]) * math.sqrt(2)
    L = min(sig.shape[1], N - i0)
    if L <= 0: return
    dry[:, i0:i0 + L] += sig[:, :L] * gain
    wet[:, i0:i0 + L] += sig[:, :L] * gain * send

def tt(d): return np.arange(int(d * SR)) / SR
def env_adsr(d, a, r, curve=2.0):
    t = tt(d); e = np.ones_like(t)
    if a > 0: e = np.minimum(e, (t / a) ** curve)
    if r > 0: e = np.minimum(e, (np.clip(d - t, 0, r) / r) ** curve)
    return e

def fft_filter(x, fn):
    X = np.fft.rfft(x); f = np.fft.rfftfreq(len(x), 1 / SR)
    return np.fft.irfft(X * fn(f), len(x))
def lowpass(x, fc, order=2): return fft_filter(x, lambda f: 1 / np.sqrt(1 + (f / fc) ** (2 * order)))
def highpass(x, fc, order=2): return fft_filter(x, lambda f: 1 / np.sqrt(1 + (fc / np.maximum(f, 1e-3)) ** (2 * order)))
def bandpass(x, lo, hi): return highpass(lowpass(x, hi), lo)

def swept_noise(d, f0, f1, q=1.2, seed=0):
    """Noise through a band-pass whose centre sweeps f0 -> f1 (STFT overlap-add)."""
    r = np.random.default_rng(seed); x = r.standard_normal(int(d * SR))
    H = 1024; hop = H // 2; win = np.hanning(H); out = np.zeros(len(x) + H)
    nfr = (len(x) - H) // hop + 1; f = np.fft.rfftfreq(H, 1 / SR)
    for k in range(nfr):
        p = k / max(1, nfr - 1)
        fc = f0 * (f1 / f0) ** p
        g = np.exp(-0.5 * (np.log2(np.maximum(f, 1.0) / fc) * q * 2.2) ** 2)
        seg = np.fft.irfft(np.fft.rfft(x[k * hop:k * hop + H] * win) * g, H)
        out[k * hop:k * hop + H] += seg * win
    out = out[:len(x)]
    return out / (np.max(np.abs(out)) + 1e-9)

def saw_stack(freq, t, cutoff=1400.0, detune_cents=0.0, nh=40, phase=0.0, pm=None):
    f = freq * 2 ** (detune_cents / 1200)
    y = np.zeros_like(t)
    for k in range(1, nh + 1):
        fk = f * k
        if fk > 9000: break
        arg = 2 * math.pi * fk * t + phase * k
        if pm is not None: arg = arg + pm * k
        y += np.sin(arg) / k / (1 + (fk / cutoff) ** 4)
    return y

def pad(freqs, start, d, att=2.0, rel=2.5, gain=0.1, cutoff=1300, pan_spread=0.7, send=0.45, vib=0.0):
    t = tt(d); e = env_adsr(d, att, rel)
    for i, f in enumerate(freqs):
        for side, cents in ((-1, -5), (1, 5)):
            ph = rng.uniform(0, 6.28)
            pm = (vib * 0.0035 * f / 5.2) * np.sin(2 * math.pi * 5.2 * t + ph) if vib else None
            v = saw_stack(f, t, cutoff, cents, phase=ph, pm=pm)
            put(v * e, start, gain / math.sqrt(len(freqs)) * 0.7, pan=side * pan_spread, send=send)

def bell(freq, start, vel=1.0, tau=1.5, pan=0.0, send=0.65, bright=2.2):
    d = min(DUR - start, tau * 5)
    if d <= 0: return
    t = tt(d)
    idx = bright * np.exp(-t / (tau * 0.35))
    car = np.sin(2 * math.pi * freq * t + idx * np.sin(2 * math.pi * freq * 2.0 * t))
    shim = 0.25 * np.sin(2 * math.pi * freq * 3.01 * t) * np.exp(-t / (tau * 0.3))
    e = np.minimum(1, t / 0.004) * np.exp(-t / tau)
    put((car + shim) * e, start, 0.36 * vel, pan, send)

def pluck(freq, start, vel=1.0, pan=0.0, send=0.7):
    d = 1.4; t = tt(d)
    e = np.minimum(1, t / 0.003) * np.exp(-t / 0.28)
    y = np.sin(2 * math.pi * freq * t + 1.4 * np.exp(-t / 0.1) * np.sin(2 * math.pi * freq * t)) * e
    put(y, start, 0.2 * vel, pan, send)

def thud(start, vel=1.0, f0=110, f1=42, decay=0.32):
    d = 0.9; t = tt(d)
    ph = 2 * math.pi * (f1 * t + (f0 - f1) * 0.07 * (1 - np.exp(-t / 0.07)))
    y = np.sin(ph) * np.exp(-t / decay) * np.minimum(1, t / 0.002)
    y = np.tanh(y * 1.6)
    put(y, start, 0.34 * vel, 0, 0.12)

def boom(start, vel=1.0, tail=3.2):
    d = tail; t = tt(d)
    ph = 2 * math.pi * (30 * t + 38 * 0.9 * (1 - np.exp(-t / 0.9)))
    y = np.sin(ph) * np.exp(-t / 1.1) * np.minimum(1, t / 0.004)
    y += 0.35 * np.sin(2 * ph) * np.exp(-t / 0.5)
    nz = lowpass(rng.standard_normal(len(t)), 900) * np.exp(-t / 0.25)
    nz2 = highpass(rng.standard_normal(len(t)), 5000) * np.exp(-t / 0.9) * 0.18
    put(np.tanh(y * 1.4) * 0.55 + nz * 0.6 + nz2 * 1.3, start, 0.8 * vel, 0, 0.5)

def riser(start, d, gain=0.5, f0=300, f1=7000, seed=1):
    x = swept_noise(d, f0, f1, 0.9, seed) * (tt(d) / d) ** 2.2
    tone = np.sin(2 * math.pi * np.cumsum(np.linspace(180, 1500, len(x))) / SR) * (tt(d) / d) ** 3 * 0.25
    put(x * 0.8 + tone, start, gain, 0, 0.35)

def whoosh_down(start, d, gain=0.5, f0=6000, f1=250, seed=2):
    x = swept_noise(d, f0, f1, 0.8, seed)
    t = tt(d); e = np.sin(np.pi * np.clip(t / d, 0, 1) ** 0.6) ** 1.5
    put(x * e, start, gain, 0, 0.4)

def ping(start, f=1650, vel=1.0, pan=0.0):
    d = 3.0; t = tt(d)
    y = np.sin(2 * math.pi * f * t + 0.6 * np.exp(-t / 0.05) * np.sin(2 * math.pi * f * 2.01 * t)) * np.exp(-t / 0.55)
    y += 0.18 * np.sin(2 * math.pi * f * 1.5 * t) * np.exp(-t / 0.3)
    y *= np.minimum(1, t / 0.002)
    put(y, start, 0.16 * vel, pan, 0.9)

def tick(start, vel=1.0, pan=0.0):
    d = 0.12; t = tt(d)
    y = highpass(rng.standard_normal(len(t)), 5500) * np.exp(-t / 0.018)
    put(y, start, 0.085 * vel, pan, 0.2)

def sub(note, start, d, gain=0.34, glide=0.5):
    t = tt(d); f = note
    y = np.sin(2 * math.pi * f * t) + 0.45 * np.sin(2 * math.pi * 2 * f * t) + 0.18 * np.sin(2 * math.pi * 3 * f * t)
    put(y * env_adsr(d, glide, glide), start, gain, 0, 0.08)

# --------------------------------------------------------------- arrangement
b = lambda k: k * BEAT          # beat -> seconds
CH = {  # chord: (bass, pad voices)
    "Dm":    ("D1", ["D3", "F3", "A3", "D4"]),
    "Bbmaj7": ("Bb0", ["Bb2", "D3", "F3", "A3"]),
    "Gm7":   ("G1", ["G2", "Bb2", "D3", "F3"]),
    "Bb":    ("Bb1", ["Bb2", "F3", "Bb3", "D4"]),
    "D":     ("D1", ["D3", "F#3", "A3", "D4", "A4"]),
}
SEGS = [("Dm", 0.0, 6.667), ("Bbmaj7", 6.667, 11.667), ("Gm7", 11.667, 20.0), ("Bb", 20.0, 25.0), ("D", 25.0, DUR)]
for name, s0, s1 in SEGS:
    bass, voices = CH[name]; d = s1 - s0
    sub(n(bass if bass[-1] != "0" else "Bb1"), s0, d + 0.4, gain=0.085 if name != "D" else 0.10, glide=0.6)
    pad([n(v) for v in voices], s0, d + 1.5, att=2.2 if s0 == 0 else 1.1, rel=2.4, gain=0.34 if name != "D" else 0.38,
        cutoff=1500 if name in ("Dm", "Gm7") else 2300, vib=1.0)
# airy octave layer swells
pad([n("A4"), n("D5"), n("F5")], 0.8, 5.0, att=2.4, rel=2.0, gain=0.09, cutoff=3000, send=0.7)
pad([n("F4"), n("A4"), n("D5"), n("F5")], 7.0, 5.2, att=2.2, rel=1.2, gain=0.14, cutoff=3200, send=0.7, vib=1.4)
pad([n("D4"), n("G4"), n("Bb4"), n("D5")], 12.6, 7.4, att=3.0, rel=2.0, gain=0.12, cutoff=3000, send=0.7, vib=1.4)
pad([n("F4"), n("Bb4"), n("D5"), n("F5")], 21.0, 4.4, att=1.8, rel=0.8, gain=0.14, cutoff=3200, send=0.7, vib=1.2)
pad([n("A4"), n("D5"), n("F#5"), n("A5")], 25.0, 7.5, att=0.9, rel=2.5, gain=0.17, cutoff=3800, send=0.75, vib=1.2)

# melody (felt bells)
mel = [(1.667, "A4", 0.8), (3.333, "D5", 0.9), (5.0, "F5", 1.0),
       (6.667, "Bb4", 0.7), (7.5, "D5", 0.7), (8.333, "F5", 0.8), (9.167, "A5", 0.8), (10.0, "C6", 0.9),
       (20.8, "F5", 0.9), (22.5, "D5", 0.9), (24.2, "Bb4", 0.8),
       (25.0, "D5", 1.0), (26.667, "A5", 0.8), (28.333, "F#5", 0.8), (30.0, "D6", 0.7)]
for t0, nm, v in mel: bell(n(nm), t0, v, tau=1.9, pan=0.15 * (1 if int(t0 * 10) % 2 else -1))
# low octave doubling on the heavy beats
for t0, nm, v in [(3.333, "D4", .6), (8.333, "Bb3", .5), (25.0, "D4", .9), (30.0, "D4", .6)]: bell(n(nm), t0, v, tau=2.6, send=0.5, bright=1.2)

# scanning arpeggio under the tactical map (8th notes), quiet and wide
seq = ["G4", "D5", "Bb4", "F5", "D5", "Bb4", "G4", "D5"]
k = 0; t0 = 12.5 + 0.0
while t0 < 19.7:
    nm = seq[k % 8]; accent = 1.0 if k % 4 == 0 else 0.55
    pluck(n(nm), t0, accent * (0.5 + 0.5 * min(1, (t0 - 12.5) / 2.0)), pan=math.sin(k * 0.9) * 0.6)
    t0 += BEAT / 2; k += 1

# heartbeat pulse (lub-dub) in the second act and a soft hat grid
t0 = b(11)
while t0 < 20.0:
    thud(t0, 0.55 if t0 < 11.6 else 0.9); thud(t0 + BEAT * 0.38, 0.45 if t0 < 11.6 else 0.6, f0=95)
    t0 += BEAT * 2
tk = 12.0
while tk < 19.9: tick(tk, 1.0 if int((tk - 12.0) / (BEAT / 2)) % 2 == 0 else 0.55, pan=0.3); tk += BEAT / 2
# fading pulse into the logo
for kk in range(3): thud(22.5 + kk * BEAT * 2, 0.35 - kk * 0.08)

# sound design tied to the picture
riser(TL["cues"]["flare"] - 1.3, 2.4, gain=0.22, f0=1500, f1=9000, seed=3)               # sun flare shimmer
ping(TL["cues"]["zone"], f=1320, vel=1.1)                                                # zone ring appears
riser(TL["cues"]["riser"], TL["cues"]["impact"] - TL["cues"]["riser"], gain=0.55, seed=4)  # dive
boom(TL["cues"]["impact"], 1.0)                                                          # terrain bloom
for i, tp in enumerate(TL["pings"]): ping(tp + 0.02, f=1650 + 90 * (i % 3), vel=1.0 if i else 0.0, pan=(-0.4, 0.4, -0.2, 0.5, 0.0)[i % 5])
whoosh_down(TL["cues"]["pullback"], 2.8, gain=0.55)                                      # pull-back
for i in range(24): tick(TL["arcStart"] + i * 0.1, 1.4, pan=(i % 5 - 2) * 0.3)           # arcs launching
riser(23.0, 2.0, gain=0.4, f0=400, f1=8000, seed=9)                                     # globe condenses to emblem
boom(TL["cues"]["logo"], 1.0)                                                            # emblem locks
ping(TL["cues"]["wordmark"], f=1980, vel=0.7); ping(TL["cues"]["cta"], f=1760, vel=0.6)

# ------------------------------------------------------------------- reverb
def make_ir(rt60=3.2, seed=11):
    r = np.random.default_rng(seed); L = int(rt60 * 1.2 * SR); t = np.arange(L) / SR
    out = []
    for ch in range(2):
        x = r.standard_normal(L) * np.exp(-t / (rt60 / 6.9))
        x[: int(0.018 * SR)] *= np.linspace(0, 1, int(0.018 * SR))
        x = lowpass(x, 5200) * 1.0
        # darker tail
        tail = lowpass(x, 1800); w = np.clip(t / 1.8, 0, 1); x = x * (1 - w) + tail * w
        out.append(x)
    ir = np.stack(out); return ir / np.sqrt((ir ** 2).sum(axis=1, keepdims=True)) * 0.55
IR = make_ir()
def convolve(x, ir):
    L = len(x) + ir.shape[1]; Nf = 1 << (L - 1).bit_length()
    X = np.fft.rfft(x, Nf)
    return np.stack([np.fft.irfft(X * np.fft.rfft(ir[c], Nf), Nf)[:len(x)] for c in range(2)])
mono_send = (wet[0] + wet[1]) * 0.5
rev = convolve(mono_send, IR)
mix = dry + rev * 0.85

# master: DC/rumble filter, gentle saturation, fades
mix = np.stack([highpass(mix[c], 28, 2) for c in range(2)])
mix = np.tanh(mix * 1.15) / 1.15
t = np.arange(N) / SR
arc_t = [0, 1.5, 6.0, 9.4, 11.55, 11.667, 13, 19.5, 20.0, 22, 24.8, 25.0, 28, 32]
arc_g = [0.35, 0.5, 0.62, 0.72, 0.78, 1.0, 0.82, 0.86, 0.78, 0.84, 0.9, 1.0, 0.92, 0.85]
mix *= np.interp(t, arc_t, arc_g)
mix *= np.minimum(1, t / 0.8) ** 1.5 * np.minimum(1, (DUR - t) / 1.4) ** 1.2
mix /= np.max(np.abs(mix)) / 0.89
os.makedirs(os.path.join(ROOT, "audio"), exist_ok=True)
out = os.path.join(ROOT, "audio", "score.wav")
with wave.open(out, "wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((mix.T * 32767).astype("<i2").tobytes())
print("wrote", out, f"{DUR:.1f}s", "peak", float(np.max(np.abs(mix))))
