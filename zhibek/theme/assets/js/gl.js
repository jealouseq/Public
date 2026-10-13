/* ZHIBEK · gl.js — WebGL2-эффекты без внешних библиотек:
   Eye (процедурный кошачий глаз), Rosette (бенгальский «мех»), Particles (кошка из частиц), Silk (шёлковый переход). */
(function () {
  'use strict';
  const ZhGL = (window.ZhGL = {});

  ZhGL.supported = (function () {
    try { return !!document.createElement('canvas').getContext('webgl2'); } catch (e) { return false; }
  })();

  const VS = `#version 300 es
in vec2 aPos; out vec2 vUv;
void main(){ vUv = aPos * .5 + .5; gl_Position = vec4(aPos, 0., 1.); }`;

  function ctx(canvas, opts) {
    return canvas.getContext('webgl2', Object.assign({ antialias: false, alpha: true, premultipliedAlpha: true, powerPreference: 'high-performance' }, opts || {}));
  }
  function sh(gl, type, src) {
    const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { const log = gl.getShaderInfoLog(s); gl.deleteShader(s); throw new Error('Shader: ' + log); }
    return s;
  }
  function prog(gl, vs, fs) {
    const p = gl.createProgram(); gl.attachShader(p, sh(gl, gl.VERTEX_SHADER, vs)); gl.attachShader(p, sh(gl, gl.FRAGMENT_SHADER, fs));
    gl.bindAttribLocation(p, 0, 'aPos'); gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error('Link: ' + gl.getProgramInfoLog(p));
    return p;
  }
  function quad(gl) {
    const vao = gl.createVertexArray(); gl.bindVertexArray(vao);
    const b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
    return vao;
  }
  function uniforms(gl, p, names) { const u = {}; names.forEach((n) => { u[n] = gl.getUniformLocation(p, n); }); return u; }
  function hex(c) {
    c = String(c).replace('#', ''); if (c.length === 3) c = c.split('').map((x) => x + x).join('');
    const n = parseInt(c, 16); return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
  }
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const lerp = (a, b, t) => a + (b - a) * t;

  /* ───────────────────────── Глаз ───────────────────────── */
  const EYE_FS = `#version 300 es
precision highp float;
in vec2 vUv; out vec4 outColor;
uniform vec2 uRes; uniform float uTime, uZoom, uOpen, uPupil, uFade;
uniform vec2 uMouse; uniform vec3 uColA, uColB, uColC;

float hash13(vec3 p){ p = fract(p*.1031); p += dot(p, p.zyx+31.32); return fract((p.x+p.y)*p.z); }
float vnoise(vec3 x){
  vec3 i=floor(x), f=fract(x); f=f*f*f*(f*(f*6.-15.)+10.);
  return mix(mix(mix(hash13(i),hash13(i+vec3(1,0,0)),f.x), mix(hash13(i+vec3(0,1,0)),hash13(i+vec3(1,1,0)),f.x),f.y),
             mix(mix(hash13(i+vec3(0,0,1)),hash13(i+vec3(1,0,1)),f.x), mix(hash13(i+vec3(0,1,1)),hash13(i+vec3(1,1,1)),f.x),f.y), f.z);
}
float fbm(vec3 p){ float a=.5,s=0.; for(int i=0;i<5;i++){ s+=a*vnoise(p); p=p*2.03+vec3(1.7,9.2,3.1); a*=.5; } return s; }
float sdBox(vec2 p, vec2 b){ vec2 d=abs(p)-b; return length(max(d,0.))+min(max(d.x,d.y),0.); }

void main(){
  vec2 p = (vUv-.5)*uRes/(.5*min(uRes.x,uRes.y));
  float z = clamp(uZoom,0.,1.); z = z*z*(3.-2.*z);
  float S = exp(mix(0., log(14.), z));
  vec2 e = p/S;
  float px = 2./(S*min(uRes.x,uRes.y));
  vec2 c = uMouse*vec2(.08,.055)*(1.-.5*z);
  e += vec2(sin(uTime*.31)*.004, cos(uTime*.27)*.003);

  /* веки: миндалевидный разрез */
  float W = 1.62, H = .64*uOpen;
  float ax = clamp(abs(e.x)/W,0.,1.);
  float shape = 1.-pow(ax,2.0);
  float up = H*shape, lo = -H*shape*.86;
  float dl = min(up-e.y, e.y-lo);
  dl = min(dl, (1.-ax)*W*.5);
  float lid = smoothstep(-px*1.5, px*1.5, dl);

  /* радужка */
  vec2 q = e - c;
  float R = .56;
  float r = length(q)/R;
  float a = atan(q.y,q.x);
  float zig = (fbm(vec3(cos(a)*3.,sin(a)*3.,2.))-.5)*.14;
  // волокна: быстро меняются по углу, умеренно по радиусу
  float f1 = fbm(vec3(cos(a)*11., sin(a)*11., r*3.4));
  float f2 = fbm(vec3(cos(a)*27., sin(a)*27., r*5.6+7.));
  float f3 = fbm(vec3(cos(a)*5.,  sin(a)*5.,  r*1.8+3.));
  float crypt = fbm(vec3(q/R*4.2, 1.7));
  float zone = smoothstep(.05,.95,r);
  vec3 col = mix(uColC*1.0, uColA*1.0, smoothstep(.10,.50,r));
  col = mix(col, uColB*1.45, smoothstep(.50,.98,r));
  float f4 = fbm(vec3(cos(a)*64., sin(a)*64., r*15.+2.));
  float st = fbm(vec3(q/R*24., 11.));
  col *= .42 + .9*f1 + .5*(f2-.5) + .35*(f3-.5);
  col *= 1. + (.55*(f4-.5)*smoothstep(.12,.7,r) + .35*(st-.5))*smoothstep(0.,.5,z);
  float ringD = abs(r - (.40+zig));
  col = mix(col, uColC*1.7, .55*exp(-pow(ringD/.05,2.))*smoothstep(.2,.8,f1));   // воротничок
  col *= 1. - .6*smoothstep(.55,.82,crypt)*smoothstep(.25,.6,r)*(1.-smoothstep(.7,.95,r));   // крипты
  col *= 1. - .93*smoothstep(.80,1.,r+zig*.3);                                    // лимбальное кольцо
  // зрачок-щель: у суженного — заострённый, у расширенного — округлый
  float pw = R*mix(.06,.38,uPupil), ph = R*mix(1.0,.86,uPupil);
  float yy = clamp(abs(q.y)/ph, 0., 1.);
  float wy = pw*pow(max(1.-yy*yy,0.), mix(1.05,.55,uPupil));
  float sd = abs(q.x) - wy;
  float pupil = (abs(q.y) < ph) ? (1.-smoothstep(-px*1.2, px*1.2, sd)) : 0.;
  float nearP = exp(-max(sd,0.)/(R*.20));
  col += uColC*.5*nearP*(1.-pupil);
  col = mix(col, vec3(.004,.003,.003), pupil);
  float irisMask = smoothstep(1.+px/R*1.5, 1.-px/R*1.5, r);

  vec3 corner = mix(vec3(.30,.16,.12), uColB*.6, .35);
  vec3 eyeball = mix(corner, col, irisMask);

  /* тень верхнего века и объём шара */
  float shadow = smoothstep(0., .34, dl);
  eyeball *= mix(.2, 1., shadow);
  eyeball *= .72 + .28*smoothstep(1.45,.1,length(e/vec2(1.85,1.)));

  /* блики роговицы */
  vec2 hp = c + vec2(-.2,.24) + uMouse*.014;
  float h1 = 1.-smoothstep(0.,.03, sdBox(e-hp, vec2(.07,.045)) - .012);
  float h2 = 1.-smoothstep(0.,.04, length((e-(c+vec2(.22,-.27)))*vec2(1.,1.5)) - .03);
  float rimRef = exp(-pow((r-.92)/.05,2.))*smoothstep(-.3,.8,dot(normalize(q+1e-4), normalize(vec2(-.5,.85))))*.2;
  eyeball += vec3(1.,.97,.92)*(h1*.9 + h2*.35) + uColC*rimRef;

  /* мех вокруг глаза */
  float furN = fbm(vec3(e.x*9., e.y*46., 3.));
  float furN2 = fbm(vec3(e.x*2.4+e.y*1.4, e.y*14., 9.));
  vec3 fur = mix(vec3(.04,.024,.014), uColB*.7+vec3(.08,.05,.03), furN)*(.5+1.0*furN2);
  fur += vec3(.6,.4,.24)*pow(furN,4.)*.22;
  float rim = exp(-pow(dl/(px*3.+.007),2.));
  vec3 lidRim = vec3(.8,.5,.36)*rim*.6;
  float skinShade = .3 + .7*smoothstep(.0,.8,1.-clamp(dl*-2.+.5,0.,1.)) ;
  vec3 outc = mix(fur*(.55+.45*smoothstep(1.4,.2,length(e*vec2(.6,1.2)))) + lidRim, eyeball, lid);

  float vig = smoothstep(1.9,.4,length(p*vec2(.78,1.)));
  outc *= .32 + .68*vig;
  outc = outc/(1.+outc*.3);
  outc *= 1.-clamp(uFade,0.,1.);
  outColor = vec4(outc,1.);
}`;

  ZhGL.Eye = function (canvas, colors) {
    const gl = ctx(canvas, { alpha: false });
    if (!gl) throw new Error('no webgl2');
    const p = prog(gl, VS, EYE_FS); const vao = quad(gl);
    const u = uniforms(gl, p, ['uRes', 'uTime', 'uZoom', 'uOpen', 'uPupil', 'uFade', 'uMouse', 'uColA', 'uColB', 'uColC']);
    const cols = (colors && colors.length ? colors : ['#d9a238']).map(hex);
    const st = { zoom: 0, open: 1, pupil: 0.1, fade: 0, color: 0, mx: 0, my: 0, tx: 0, ty: 0, running: false, raf: 0, t0: performance.now(), dirty: true, dprCap: 1.5, w: 0, h: 0 };
    const cur = [0, 0, 0];
    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, st.dprCap), r = canvas.getBoundingClientRect();
      const w = Math.max(2, Math.round(r.width * dpr)), h = Math.max(2, Math.round(r.height * dpr));
      if (w !== st.w || h !== st.h) { canvas.width = w; canvas.height = h; st.w = w; st.h = h; gl.viewport(0, 0, w, h); }
      st.dirty = true;
    }
    function colorAt(f) {
      const n = cols.length; f = clamp(f, 0, n - 1); const i = Math.min(n - 2, Math.floor(f)); const t = n === 1 ? 0 : f - i;
      const a = cols[i], b = cols[Math.min(n - 1, i + 1)];
      for (let k = 0; k < 3; k++) cur[k] = lerp(a[k], b[k], t * t * (3 - 2 * t));
      return cur;
    }
    function draw() {
      st.mx += (st.tx - st.mx) * 0.07; st.my += (st.ty - st.my) * 0.07;
      const A = colorAt(st.color);
      const B = [A[0] * A[0] * 0.46, A[1] * A[1] * 0.46, A[2] * A[2] * 0.46];
      const C = [lerp(A[0], 1.0, 0.34), lerp(A[1], 0.9, 0.3), lerp(A[2], 0.55, 0.22)];
      gl.useProgram(p); gl.bindVertexArray(vao);
      gl.uniform2f(u.uRes, st.w, st.h); gl.uniform1f(u.uTime, (performance.now() - st.t0) / 1000);
      gl.uniform1f(u.uZoom, st.zoom); gl.uniform1f(u.uOpen, st.open); gl.uniform1f(u.uPupil, st.pupil); gl.uniform1f(u.uFade, st.fade);
      gl.uniform2f(u.uMouse, st.mx, st.my); gl.uniform3f(u.uColA, A[0], A[1], A[2]); gl.uniform3f(u.uColB, B[0], B[1], B[2]); gl.uniform3f(u.uColC, C[0], C[1], C[2]);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function loop() { if (!st.running) return; draw(); st.raf = requestAnimationFrame(loop); }
    resize();
    return {
      state: st, resize, draw,
      set(o) { Object.assign(st, o); if (!st.running) draw(); },
      pointer(x, y) { st.tx = clamp(x, -1, 1); st.ty = clamp(y, -1, 1); },
      start() { if (st.running) return; st.running = true; st.raf = requestAnimationFrame(loop); },
      stop() { st.running = false; cancelAnimationFrame(st.raf); },
    };
  };

  /* ───────────────────── Бенгальские розетки ───────────────────── */
  const ROS_FS = `#version 300 es
precision highp float;
in vec2 vUv; out vec4 outColor;
uniform vec2 uRes; uniform float uTime, uZoom; uniform vec2 uLight, uShift;
vec2 h22(vec2 p){ p=vec2(dot(p,vec2(127.1,311.7)),dot(p,vec2(269.5,183.3))); return fract(sin(p)*43758.5453); }
float h21(vec2 p){ return fract(sin(dot(p,vec2(12.9898,78.233)))*43758.5453); }
float n2(vec2 p){ vec2 i=floor(p),f=fract(p); f=f*f*(3.-2.*f); return mix(mix(h21(i),h21(i+vec2(1,0)),f.x),mix(h21(i+vec2(0,1)),h21(i+vec2(1,1)),f.x),f.y); }
float fbm(vec2 p){ float a=.5,s=0.; for(int i=0;i<4;i++){ s+=a*n2(p); p=p*2.02+vec2(3.1,1.7); a*=.5; } return s; }
// возвращает: x = кольцо, y = центр, z = случайное значение ячейки
vec3 rosette(vec2 uv){
  vec2 g=floor(uv), f=fract(uv);
  float best=1e9; vec2 id=vec2(0.); vec2 bh=vec2(0.);
  for(int j=-1;j<=1;j++) for(int i=-1;i<=1;i++){
    vec2 o=vec2(float(i),float(j)); vec2 h=h22(g+o);
    vec2 d=o+.12+.76*h-f;
    float ang=h.x*6.2831; mat2 R=mat2(cos(ang),-sin(ang),sin(ang),cos(ang));
    d=R*d; d.x*=.78+.7*h.y;
    float dist=length(d);
    if(dist<best){ best=dist; id=g+o; bh=h; }
  }
  float warp=(fbm(uv*6.+id*3.)-.5)*.2;
  float d=best+warp;
  float ring=smoothstep(.17+bh.x*.04,.23+bh.x*.04,d)*(1.-smoothstep(.31+bh.y*.05,.40+bh.y*.05,d));
  ring*=smoothstep(.2,.5,fbm(uv*9.+id)+.35);
  float center=1.-smoothstep(.12,.22,d);
  return vec3(ring,center,bh.x);
}
void main(){
  vec2 uv=(vUv-.5)*vec2(uRes.x/uRes.y,1.);
  float zoom=1.+uZoom*1.8;
  vec2 P=(uv/zoom)*9.5+uShift+vec2(uTime*.01,0.);
  vec3 r=rosette(P);
  float fur=fbm(vec2(P.x*14.,P.y*4.))*.65+fbm(vec2(P.x*34.,P.y*9.))*.35;
  float h=r.x*.9+fur*.07;
  vec3 n=normalize(vec3(-dFdx(h)*38.,-dFdy(h)*38.,1.));
  vec3 L=normalize(vec3(uLight.x*.8,uLight.y*.8,.7));
  float diff=clamp(dot(n,L),0.,1.);
  float spec=pow(clamp(dot(reflect(-L,n),vec3(0,0,1)),0.,1.),22.)*.55;
  vec3 gold=mix(vec3(.58,.36,.09),vec3(.92,.66,.22),fur);
  vec3 rust=mix(vec3(.34,.17,.05),vec3(.5,.28,.08),fur);
  vec3 base=mix(gold,rust,r.y*.75);
  vec3 col=mix(base,vec3(.09,.052,.02),r.x);
  col*=.45+.9*diff;
  col+=vec3(1.,.8,.45)*spec*(1.-r.x*.5);
  float vig=smoothstep(1.25,.1,length(uv*vec2(.8,1.)));
  col*=.18+.82*vig;
  outColor=vec4(col,1.);
}`;

  ZhGL.Rosette = function (canvas) {
    const gl = ctx(canvas, { alpha: false }); if (!gl) throw new Error('no webgl2');
    const p = prog(gl, VS, ROS_FS); const vao = quad(gl);
    const u = uniforms(gl, p, ['uRes', 'uTime', 'uZoom', 'uLight', 'uShift']);
    const st = { zoom: 0, lx: 0.3, ly: 0.4, tlx: 0.3, tly: 0.4, shx: 0, shy: 0, running: false, raf: 0, w: 0, h: 0, t0: performance.now(), dprCap: 1 };
    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, st.dprCap), r = canvas.getBoundingClientRect();
      const w = Math.max(2, Math.round(r.width * dpr * 0.75)), h = Math.max(2, Math.round(r.height * dpr * 0.75));
      if (w !== st.w || h !== st.h) { canvas.width = w; canvas.height = h; st.w = w; st.h = h; gl.viewport(0, 0, w, h); }
    }
    function draw() {
      st.lx += (st.tlx - st.lx) * 0.06; st.ly += (st.tly - st.ly) * 0.06;
      gl.useProgram(p); gl.bindVertexArray(vao);
      gl.uniform2f(u.uRes, st.w, st.h); gl.uniform1f(u.uTime, (performance.now() - st.t0) / 1000); gl.uniform1f(u.uZoom, st.zoom);
      gl.uniform2f(u.uLight, st.lx, st.ly); gl.uniform2f(u.uShift, st.shx, st.shy);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function loop() { if (!st.running) return; draw(); st.raf = requestAnimationFrame(loop); }
    resize();
    return {
      state: st, resize, draw,
      set(o) { Object.assign(st, o); },
      light(x, y) { st.tlx = x; st.tly = y; },
      start() { if (st.running) return; st.running = true; st.raf = requestAnimationFrame(loop); },
      stop() { st.running = false; cancelAnimationFrame(st.raf); },
    };
  };

  /* ───────────────────── Частицы: кошка из пыли ───────────────────── */
  const PT_VS = `#version 300 es
in vec2 aPos; in vec3 aCol; in float aSize;
uniform vec2 uRes; uniform float uDpr; out vec3 vCol; out float vA;
void main(){
  vec2 ndc = vec2(aPos.x/uRes.x*2.-1., 1.-aPos.y/uRes.y*2.);
  gl_Position = vec4(ndc,0.,1.); gl_PointSize = aSize*uDpr; vCol=aCol; vA=1.;
}`;
  const PT_FS = `#version 300 es
precision mediump float; in vec3 vCol; in float vA; out vec4 outColor;
void main(){ vec2 d=gl_PointCoord-.5; float r=length(d)*2.; float a=smoothstep(1.,.25,r); outColor=vec4(vCol*a*1.15,a*.9); }`;

  ZhGL.Particles = function (canvas, src, opts) {
    opts = Object.assign({ count: 7000, align: 0.7, height: 0.9 }, opts || {});
    const gl = ctx(canvas); if (!gl) throw new Error('no webgl2');
    const p = prog(gl, PT_VS, PT_FS);
    const loc = { pos: gl.getAttribLocation(p, 'aPos'), col: gl.getAttribLocation(p, 'aCol'), size: gl.getAttribLocation(p, 'aSize') };
    const uRes = gl.getUniformLocation(p, 'uRes'), uDpr = gl.getUniformLocation(p, 'uDpr');
    const st = { assemble: 0, running: false, raf: 0, w: 0, h: 0, dpr: 1, mx: -9999, my: -9999, ready: false, n: 0 };
    let tx, ty, sx, sy, x, y, vx, vy, col, size, seed, posBuf, colBuf, sizeBuf, vao, posArr, tgtN = [];
    function build(img) {
      const cw = 150, ch = Math.round(150 * (img.naturalHeight / img.naturalWidth));
      const c = document.createElement('canvas'); c.width = cw; c.height = ch; const cx = c.getContext('2d', { willReadFrequently: true });
      cx.drawImage(img, 0, 0, cw, ch); const data = cx.getImageData(0, 0, cw, ch).data;
      const pts = [];
      const step = Math.max(1, Math.sqrt((cw * ch * 0.62) / opts.count));
      for (let yy = 0; yy < ch; yy += step) for (let xx = 0; xx < cw; xx += step) {
        const i = ((yy | 0) * cw + (xx | 0)) * 4; if (data[i + 3] > 140) pts.push([(xx + (Math.random() - 0.5) * step) / cw, (yy + (Math.random() - 0.5) * step) / ch, Math.min(1, data[i] / 255 * 1.55 + 0.06), Math.min(1, data[i + 1] / 255 * 1.55 + 0.05), Math.min(1, data[i + 2] / 255 * 1.55 + 0.03)]);
      }
      tgtN = pts; const n = pts.length; st.n = n;
      tx = new Float32Array(n); ty = new Float32Array(n); sx = new Float32Array(n); sy = new Float32Array(n); x = new Float32Array(n); y = new Float32Array(n);
      vx = new Float32Array(n); vy = new Float32Array(n); col = new Float32Array(n * 3); size = new Float32Array(n); seed = new Float32Array(n); posArr = new Float32Array(n * 2);
      for (let i = 0; i < n; i++) {
        col[i * 3] = pts[i][2]; col[i * 3 + 1] = pts[i][3]; col[i * 3 + 2] = pts[i][4]; size[i] = 2.1 + Math.random() * 2.7; seed[i] = Math.random() * 6.283;
      }
      vao = gl.createVertexArray(); gl.bindVertexArray(vao);
      posBuf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, posBuf); gl.bufferData(gl.ARRAY_BUFFER, posArr, gl.DYNAMIC_DRAW); gl.enableVertexAttribArray(loc.pos); gl.vertexAttribPointer(loc.pos, 2, gl.FLOAT, false, 0, 0);
      colBuf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, colBuf); gl.bufferData(gl.ARRAY_BUFFER, col, gl.STATIC_DRAW); gl.enableVertexAttribArray(loc.col); gl.vertexAttribPointer(loc.col, 3, gl.FLOAT, false, 0, 0);
      sizeBuf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, sizeBuf); gl.bufferData(gl.ARRAY_BUFFER, size, gl.STATIC_DRAW); gl.enableVertexAttribArray(loc.size); gl.vertexAttribPointer(loc.size, 1, gl.FLOAT, false, 0, 0);
      layout(true); st.ready = true;
    }
    function layout(first) {
      const n = st.n; if (!n) return;
      const H = st.h * opts.height, aspect = (tgtN.length ? 0.7082 : 1), W = H * aspect;
      const left = st.w * opts.align - W / 2, top = st.h - H + st.h * 0.02;
      for (let i = 0; i < n; i++) {
        tx[i] = left + tgtN[i][0] * W; ty[i] = top + tgtN[i][1] * H;
        if (first || !sx[i]) { const a = Math.random() * 6.283, rad = Math.random(); sx[i] = st.w * (0.5 + (Math.random() - 0.5) * 1.3); sy[i] = st.h * (Math.random() * 1.2 - 0.1); x[i] = sx[i]; y[i] = sy[i]; }
      }
    }
    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, 2), r = canvas.getBoundingClientRect();
      const w = Math.max(2, Math.round(r.width)), h = Math.max(2, Math.round(r.height));
      st.dpr = dpr; canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
      if (w !== st.w || h !== st.h) { st.w = w; st.h = h; layout(false); }
      gl.viewport(0, 0, canvas.width, canvas.height);
    }
    let lastT = 0;
    function step(t) {
      const dt = lastT ? Math.min(3, (t - lastT) / 16.67) : 1; lastT = t;
      const kSpring = 0.045 * dt, kDamp = Math.pow(0.86, dt);
      const n = st.n, A = st.assemble, E = A * A * (3 - 2 * A);
      const R = Math.min(st.w, st.h) * 0.16, R2 = R * R;
      for (let i = 0; i < n; i++) {
        const drift = (1 - E) * 14 + 1.3;
        const gx = lerp(sx[i], tx[i], E) + Math.sin(t * 0.0006 + seed[i]) * drift;
        const gy = lerp(sy[i], ty[i], E) + Math.cos(t * 0.0005 + seed[i] * 1.7) * drift;
        let fx = (gx - x[i]) * kSpring, fy = (gy - y[i]) * kSpring;
        const dx = x[i] - st.mx, dy = y[i] - st.my, d2 = dx * dx + dy * dy;
        if (d2 < R2) { const d = Math.sqrt(d2) + 0.001, k = (1 - d / R); const f = k * k * 3.2 * dt; fx += (dx / d) * f; fy += (dy / d) * f; }
        vx[i] = (vx[i] + fx) * kDamp; vy[i] = (vy[i] + fy) * kDamp;
        x[i] += vx[i] * dt; y[i] += vy[i] * dt; posArr[i * 2] = x[i]; posArr[i * 2 + 1] = y[i];
      }
    }
    function draw(t) {
      if (!st.ready) return;
      step(t);
      gl.useProgram(p); gl.bindVertexArray(vao);
      gl.bindBuffer(gl.ARRAY_BUFFER, posBuf); gl.bufferSubData(gl.ARRAY_BUFFER, 0, posArr);
      gl.uniform2f(uRes, st.w, st.h); gl.uniform1f(uDpr, st.dpr);
      gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT);
      gl.enable(gl.BLEND); gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
      gl.drawArrays(gl.POINTS, 0, st.n);
    }
    function loop(t) { if (!st.running) return; draw(t); st.raf = requestAnimationFrame(loop); }
    const img = new Image(); img.crossOrigin = 'anonymous';
    img.onload = () => { resize(); build(img); if (st.running) { /* уже идёт */ } };
    img.src = src;
    resize();
    return {
      state: st, resize,
      set(o) { Object.assign(st, o); },
      pointer(px, py) { st.mx = px; st.my = py; },
      start() { if (st.running) return; st.running = true; lastT = 0; st.raf = requestAnimationFrame(loop); },
      stop() { st.running = false; cancelAnimationFrame(st.raf); },
    };
  };

  /* ───────────────────── Шёлк: переход между страницами ───────────────────── */
  const SILK_FS = `#version 300 es
precision highp float;
in vec2 vUv; out vec4 outColor;
uniform vec2 uRes; uniform float uTime, uP;
float h21(vec2 p){ return fract(sin(dot(p,vec2(12.9898,78.233)))*43758.5453); }
float n2(vec2 p){ vec2 i=floor(p),f=fract(p); f=f*f*(3.-2.*f); return mix(mix(h21(i),h21(i+vec2(1,0)),f.x),mix(h21(i+vec2(0,1)),h21(i+vec2(1,1)),f.x),f.y); }
float edge(float prog, vec2 uv, float t){
  float w = .11*sin(uv.y*3.3 + t*.8) + .05*sin(uv.y*7.1 - t*1.1) + .012*sin(uv.y*15. + t*1.9);
  return mix(-.35, 1.45, prog) + w*(.4+.6*sin(prog*3.14159));
}
void main(){
  vec2 uv = vUv; uv.x *= uRes.x/uRes.y; float asp = uRes.x/uRes.y;
  float lead = clamp(uP,0.,1.), trail = clamp(uP-1.,0.,1.);
  lead = lead*lead*(3.-2.*lead); trail = trail*trail*(3.-2.*trail);
  float x = vUv.x;
  float eL = edge(lead, vUv, uTime), eT = edge(trail, vUv, uTime+3.);
  float inL = smoothstep(eL, eL-.09, x);          // за передней кромкой — ткань
  float inT = smoothstep(eT-.09, eT, x);          // правее задней кромки — ткань
  float cover = (uP<1.) ? inL : (trail>0. ? inT : 1.);
  if (uP<=0.001) cover = 0.;
  /* складки */
  float fold = sin(vUv.x*asp*4.6 + 2.2*sin(vUv.y*2.4 + uTime*.45) + 1.6*n2(vec2(vUv.x*asp*2.,vUv.y*3.)+uTime*.08));
  float fold2 = sin(vUv.x*asp*13. + 2.4*n2(vUv*vec2(asp*4.,6.)) + uTime*.25);
  float sheen = pow(.5+.5*fold, 5.)*.5 + pow(.5+.5*fold2, 9.)*.22;
  vec3 base = mix(vec3(.045,.032,.02), vec3(.16,.11,.06), .5+.5*fold);
  vec3 gold = vec3(.98,.82,.52);
  vec3 col = base + gold*sheen*.55;
  /* кромки: тонкая золотая линия и мягкий вуаль-градиент */
  float lineL = exp(-pow((x-eL)/.006,2.))*step(uP,1.0)*lead*(1.-step(.999,lead));
  float lineT = exp(-pow((x-eT)/.006,2.))*step(1.0,uP)*(1.-step(.999,trail));
  col += gold*(lineL+lineT)*.9;
  float veil = (uP<1.) ? smoothstep(eL-.35,eL,x)*.0+1. : 1.;
  float a = cover*veil;
  outColor = vec4(col*a, a);
}`;

  ZhGL.Silk = function (canvas) {
    const gl = ctx(canvas); if (!gl) throw new Error('no webgl2');
    const p = prog(gl, VS, SILK_FS); const vao = quad(gl);
    const u = uniforms(gl, p, ['uRes', 'uTime', 'uP']);
    const st = { p: 0, raf: 0, running: false, t0: performance.now(), w: 0, h: 0 };
    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, 1.5), r = canvas.getBoundingClientRect();
      const w = Math.max(2, Math.round(r.width * dpr)), h = Math.max(2, Math.round(r.height * dpr));
      if (w !== st.w || h !== st.h) { canvas.width = w; canvas.height = h; st.w = w; st.h = h; gl.viewport(0, 0, w, h); }
    }
    function draw() {
      gl.useProgram(p); gl.bindVertexArray(vao);
      gl.uniform2f(u.uRes, st.w, st.h); gl.uniform1f(u.uTime, (performance.now() - st.t0) / 1000); gl.uniform1f(u.uP, st.p);
      gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT); gl.enable(gl.BLEND); gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }
    function animate(from, to, ms) {
      return new Promise((res) => {
        resize(); const t0 = performance.now(); st.running = true;
        (function f(now) {
          const k = clamp((now - t0) / ms, 0, 1); st.p = lerp(from, to, k); draw();
          if (k < 1) st.raf = requestAnimationFrame(f); else { st.running = false; res(); }
        })(t0);
      });
    }
    return {
      state: st, resize,
      cover(ms) { return animate(0, 1, ms || 900); },
      reveal(ms) { return animate(1, 2, ms || 1000); },
      hold() { resize(); st.p = 1; draw(); },
      progress(v) { resize(); st.p = v; draw(); },
      clear() { st.p = 0; gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT); },
    };
  };
})();
