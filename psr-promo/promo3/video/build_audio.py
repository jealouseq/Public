#!/usr/bin/env python3
"""Sound for the film: silence-first. Room tone + clock while waiting, soft keystrokes synced to the typed drafts,
a heartbeat when the draft is erased, music enters only at the turn. python3 -I promo3/video/build_audio.py"""
import numpy as np, subprocess, os, wave, json
ROOT=os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SFX="/root/.claude/plugins/synced/ef5eeff0-207f-407d-b811-d7b094f7352d_117dd908-cc09-43c7-8ee4-b609280c7504/74655670-1540-4e3d-8509-c2f446898c2c/assets/audio/sfx"
SR=48000
TL=json.loads(subprocess.check_output(["node","-e","console.log(JSON.stringify(require('%s/promo3/video/tl.js')))"%ROOT]))
T_END=TL["T_END"]; N=int(T_END*SR); t=np.arange(N)/SR; S=TL["S"]
def dec(path,start=None,dur=None):
    cmd=["ffmpeg","-v","error"]
    if start is not None: cmd+=["-ss",str(start)]
    if dur is not None: cmd+=["-t",str(dur)]
    cmd+=["-i",path,"-ac","2","-ar",str(SR),"-f","f32le","-"]
    return np.frombuffer(subprocess.check_output(cmd),dtype="<f4").reshape(-1,2).T.astype(np.float64)
rng=np.random.default_rng(5)
# ---- music: starts so that its energy lift (file 14.75 s) lands on the turn (landline shot)
turn=TL["turn"]; off=max(0.0,turn-14.75)
mus=dec(os.path.join(ROOT,"audio/mixkit/606_Drawing_the_Sky.mp3"),0,T_END-off+1)
mus*=10**(-18.0/20)/(np.sqrt((mus**2).mean())+1e-9)
m=np.zeros((2,N)); i0=int(off*SR); L=min(mus.shape[1],N-i0); m[:,i0:i0+L]=mus[:,:L]
fi=np.clip((t-off)/3.0,0,1); fo=np.clip((T_END-t)/4.0,0,1); m*=(fi*fi*(3-2*fi))*(fo*fo*(3-2*fo))
a,b,c=S[6]+.2,S[6]+1.4,S[6]+3.2   # make room for the last line
dip=np.ones(N); dip[(t>=a)&(t<b)]=10**(-4.5/20); r=(t>=b)&(t<c); dip[r]=10**(-4.5/20)+(1-10**(-4.5/20))*np.clip((t[r]-b)/(c-b),0,1); m*=dip
# ---- room tone (always there, never digital silence)
def shaped(n,lo,hi):
    w=rng.standard_normal(n); Sx=np.fft.rfft(w); f=np.fft.rfftfreq(n,1/SR); Sx*=1/np.sqrt(np.maximum(f,20)); Sx[f<lo]*=.05; Sx[f>hi]*=np.exp(-(f[f>hi]-hi)/(hi*.5)); x=np.fft.irfft(Sx,n); return x/np.sqrt((x**2).mean())
room=shaped(N,45,800); room*=(1+.12*np.sin(2*np.pi*t/7.3)+.07*np.sin(2*np.pi*t/3.1+1))
room*=10**(-33/20)*np.minimum(1,t/1.2)*np.minimum(1,(T_END-t)/2.5)
out=m+np.vstack([room,np.roll(room,61)])
def peak(x):
    e=np.abs(x).max(axis=0); w=int(.01*SR); return int(np.argmax(np.convolve(e,np.ones(w)/w,mode="same")))
def put(rel,at,gain,align="start",dur=None,fade=.08,start=None,rev=False):
    x=dec(os.path.join(SFX,rel+".mp3"),start,dur)
    if rev: x=x[:,::-1].copy(); align="end"
    off_=peak(x) if align=="peak" else (x.shape[1] if align=="end" else 0)
    i0=int(round(at*SR))-off_; a_,b_=max(0,i0),min(N,i0+x.shape[1])
    if b_<=a_: return
    seg=x[:,a_-i0:b_-i0].copy(); k=min(seg.shape[1],int(fade*SR)); seg[:,-k:]*=np.linspace(1,0,k); seg[:,:min(32,seg.shape[1])]*=np.linspace(0,1,min(32,seg.shape[1])); out[:,a_:b_]+=seg*gain
def tap(at,freq,gain,pan=0.0):
    n=int(.06*SR); tt=np.arange(n)/SR
    nz=rng.standard_normal(n); nz=np.convolve(nz,np.ones(6)/6,mode="same")   # soft, dull click
    x=(nz*.6+np.sin(2*np.pi*freq*tt)*.9)*np.exp(-tt/.0065)
    i0=int(at*SR); seg=np.vstack([x*(1-pan),x*(1+pan)])*gain
    out[:,i0:i0+n]+=seg[:,:max(0,min(n,N-i0))] if i0+n<=N else seg[:,:N-i0]
# ---- waiting: a clock, stops with the first "delivered" tick of the last message
clock_stop=TL["phoneSend"]
k=0
while True:
    tt_=.9+k*1.0+(.015 if k%2 else 0)
    if tt_>clock_stop-.2: break
    put("counter/clock-tick-single",tt_,0.05*(1+.07*(k%3)),"peak"); k+=1
# ---- keystrokes of the typed drafts + send pops
for ky in TL["KEYS"]:
    if ky["k"]=="type": tap(ky["t"],rng.uniform(520,820),rng.uniform(.02,.03),rng.uniform(-.1,.1))
    elif ky["k"]=="erase": tap(ky["t"],rng.uniform(380,520),rng.uniform(.014,.02))
    elif ky["k"]=="send": put("ui/ui-message-pop",ky["t"],0.19,"peak")
# ---- the erased "Мне страшно": two slow heartbeats, then the room goes quiet
e0=TL["eraseStart"]
put("crowd/heartbeat-single",e0+.25,0.20,"peak",fade=.3); put("crowd/heartbeat-single",e0+1.0,0.14,"peak",fade=.3)
# ---- swell into the turn (reversed light aura), lands on the cut
put("light/light-aura",turn-.03,0.20,rev=True,fade=.02)
put("impact/impact-deep-whoosh",turn+.05,0.05,"peak",fade=.6)
# ---- landline: dial tone starts under the previous shot (J-cut), handset click on the cut
tt=np.arange(int(3.2*SR))/SR; tone=(np.sin(2*np.pi*425*tt)+.12*np.sin(2*np.pi*850*tt))*0.012; tone*=np.clip(tt/.4,0,1)*np.clip((3.2-tt)/1.0,0,1)
i0=int((turn-.55)*SR); out[:,i0:i0+len(tone)]+=np.vstack([tone,tone])
put("ui/switch-click-quick",turn,0.30,"peak")
# ---- writing (J-cut)
put("text/pen-write-paper",S[5]-.3,0.26,"start",fade=.4,start=0)
out=np.tanh(out*1.1)/1.1; pk=np.abs(out).max(); out*=min(1,.9/pk)
with wave.open(os.path.join(ROOT,"promo3/video/mix.wav"),"wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes((out.T*32767).astype("<i2").tobytes())
print("wrote mix.wav",round(T_END,2),"s; music starts",round(off,2),"turn",turn,"peak",round(float(np.abs(out).max()),3))
