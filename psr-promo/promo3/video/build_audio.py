#!/usr/bin/env python3
"""Sound for the promo: Mixkit music + room tone + a few quiet library foley sounds, laid with J/L-cuts. python3 -I promo3/video/build_audio.py"""
import numpy as np, subprocess, os, wave
ROOT=os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SFX="/root/.claude/plugins/synced/ef5eeff0-207f-407d-b811-d7b094f7352d_117dd908-cc09-43c7-8ee4-b609280c7504/74655670-1540-4e3d-8509-c2f446898c2c/assets/audio/sfx"
SR=48000; B=lambda k:0.42+0.6*k
T_END=B(48)+4.4; N=int(T_END*SR)
def dec(path,start=None,dur=None):
    cmd=["ffmpeg","-v","error"]
    if start is not None: cmd+=["-ss",str(start)]
    if dur is not None: cmd+=["-t",str(dur)]
    cmd+=["-i",path,"-ac","2","-ar",str(SR),"-f","f32le","-"]
    return np.frombuffer(subprocess.check_output(cmd),dtype="<f4").reshape(-1,2).T.astype(np.float64)
t=np.arange(N)/SR
# ---- music: quiet intro, lift at ~14.8 s (the cut to the telephone)
m=dec(os.path.join(ROOT,"audio/mixkit/606_Drawing_the_Sky.mp3"),0,T_END+0.5)[:, :N]
m*=10**(-18.5/20)/(np.sqrt((m**2).mean())+1e-9)
fi=np.clip(t/2.6,0,1); fo=np.clip((T_END-t)/3.8,0,1)
m*=(fi*fi*(3-2*fi))*(fo*fo*(3-2*fo))
# make room before the key line, then let it breathe back
dip=np.ones(N); a,b,c=B(40)+.2,B(40)+1.5,B(40)+3.0
dip[(t>=a)&(t<b)]=10**(-4.5/20); r=(t>=b)&(t<c); dip[r]=10**(-4.5/20)+(1-10**(-4.5/20))*np.clip((t[r]-b)/(c-b),0,1)
m*=dip
# ---- room tone: constant, no digital silence; gets slightly warmer from the telephone shot on
rng=np.random.default_rng(11)
def shaped(n,lo,hi):
    w=rng.standard_normal(n); S=np.fft.rfft(w); f=np.fft.rfftfreq(n,1/SR); S*=1/np.sqrt(np.maximum(f,20))
    S[f<lo]*=0.05; S[f>hi]*=np.exp(-(f[f>hi]-hi)/(hi*.5)); x=np.fft.irfft(S,n); return x/np.sqrt((x**2).mean())
room=shaped(N,50,900)
warm=shaped(N,50,1600); mix=np.clip((t-B(24))/3,0,1)*np.clip(1-(t-B(35))/2,0,1)
room=room*(1-mix)+warm*mix
room*=(1+.12*np.sin(2*np.pi*t/7.3)+.07*np.sin(2*np.pi*t/3.1+1))
room*=10**(-40/20)*np.minimum(1,t/1.5)*np.minimum(1,(T_END-t)/2.5)
room=np.vstack([room,np.roll(room,57)])
out=m+room
def peak(x):
    e=np.abs(x).max(axis=0); w=int(.01*SR); return int(np.argmax(np.convolve(e,np.ones(w)/w,mode="same")))
def put(rel,at,gain,align="start",dur=None,fade=.08,start=None):
    x=dec(os.path.join(SFX,rel+".mp3"),start,dur)
    off=peak(x) if align=="peak" else 0
    i0=int(round(at*SR))-off; a,b=max(0,i0),min(N,i0+x.shape[1])
    if b<=a: return
    seg=x[:,a-i0:b-i0].copy(); k=min(seg.shape[1],int(fade*SR)); seg[:,-k:]*=np.linspace(1,0,k); seg[:,:min(32,seg.shape[1])]*=np.linspace(0,1,min(32,seg.shape[1]))
    out[:,a:b]+=seg*gain
# ---- waiting: a clock in an empty room, stops when the message arrives
for k in range(5): put("counter/clock-tick-single",.9+k*1.0,0.034*(1+.06*(k%2)),"peak")
put("ui/ui-message-pop",B(8)+.95,0.30,"peak")
put("ui/switch-tap",B(8)+2.15+.14,0.10,"peak")
# ---- letter: paper sound leads the picture (J-cut)
put("paper/paper-slide",B(14)-.35,0.26,"start",fade=.25)
put("paper/paper-page-turn",B(14)+1.2,0.12,"start")
# ---- telephone: dial tone starts under the hands (J-cut), handset click on the cut
tt=np.arange(int(2.9*SR))/SR
tone=(np.sin(2*np.pi*425*tt)+.12*np.sin(2*np.pi*850*tt))*0.014
tone*=np.clip(tt/.35,0,1)*np.clip((2.9-tt)/.9,0,1)
i0=int((B(24)-.52)*SR); out[:,i0:i0+len(tone)]+=np.vstack([tone,tone])
put("ui/switch-click-quick",B(24),0.34,"peak")
# ---- tea
put("glass/glass-plate-slide",B(29)+.55,0.12,"start",fade=.3,dur=.9)
# ---- writing (J-cut)
put("text/pen-write-paper",B(35)-.25,0.30,"start",fade=.35,start=0)
out/=1.0
out=np.tanh(out*1.1)/1.1
pk=np.abs(out).max(); out*=min(1,0.9/pk)
os.makedirs(os.path.join(ROOT,"promo3/video"),exist_ok=True)
for name,x in (("mix.wav",out),):
    with wave.open(os.path.join(ROOT,"promo3/video",name),"wb") as w:
        w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes((x.T*32767).astype("<i2").tobytes())
print("wrote mix.wav", round(T_END,2),"s peak",round(float(np.abs(out).max()),3))
