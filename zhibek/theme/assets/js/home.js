/* ZHIBEK · home.js — сцены главной страницы */
(function () {
  'use strict';
  const ZH = window.ZH; if (!ZH || !window.gsap) return;
  const { $, $$, fine } = ZH; const gsap = window.gsap, ScrollTrigger = window.ScrollTrigger;
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const smooth = (a, b, v) => { const t = clamp((v - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };
  const small = () => innerWidth < 900;
  const html = document.documentElement;

  const pointer = { x: 0, y: 0, nx: 0, ny: 0, t: 0, moved: false };
  addEventListener('pointermove', (e) => { pointer.x = e.clientX; pointer.y = e.clientY; pointer.nx = (e.clientX / innerWidth) * 2 - 1; pointer.ny = -((e.clientY / innerHeight) * 2 - 1); pointer.t = performance.now(); pointer.moved = true; }, { passive: true });

  /* ═════ 01 · ВРАТА + ГЕРОЙ ═════ */
  function initGate() {
    const gate = $('[data-gate]'); if (!gate) return;
    const pin = $('.gate-pin', gate), hero = $('[data-hero]', gate), cat = $('[data-hero-cat]', gate), word = $('[data-hero-word]', gate), wm = $('.wm-hero', gate);
    const dust = $('[data-dust]', gate);
    if (ZH.reduce) { gate.classList.add('is-static', 'is-open', 'is-done'); return; }

    // пылинки в луче света
    if (dust) {
      for (let i = 0; i < 38; i++) {
        const d = document.createElement('i'); dust.append(d);
        gsap.set(d, { left: gsap.utils.random(38, 96, 0.1) + '%', top: gsap.utils.random(6, 94, 0.1) + '%', scale: gsap.utils.random(0.5, 1.7) });
        gsap.to(d, { y: gsap.utils.random(-120, -40), x: gsap.utils.random(-50, 50), opacity: gsap.utils.random(0.25, 0.9), duration: gsap.utils.random(3.5, 8), repeat: -1, yoyo: true, ease: 'sine.inOut', delay: gsap.utils.random(0, 4) });
      }
    }
    gsap.set(pin, { '--ajar': 0, '--open': 0 });
    // часть открытия по времени: створки приоткрываются сами
    ZH.afterReveal(() => {
      const already = scrollY > 40;
      gsap.to(pin, { '--ajar': 1, duration: already ? 0.01 : 2.6, ease: 'power3.inOut', delay: already ? 0 : 0.35 });
    });
    // часть открытия по скроллу
    const markOpen = () => { const open = parseFloat(gsap.getProperty(pin, '--open')) || 0; gate.classList.toggle('is-open', open > 0.55); gate.classList.toggle('is-done', open > 0.995); };
    const tl = gsap.timeline({ defaults: { ease: 'none' }, onUpdate: markOpen, scrollTrigger: { trigger: gate, start: 'top top', end: () => '+=' + Math.round(innerHeight * (small() ? 1.5 : 2.0)), pin: pin, scrub: 0.7, anticipatePin: 1, invalidateOnRefresh: true } });
    tl.to(pin, { '--open': 1, duration: 0.62, ease: 'power2.inOut' }, 0);
    tl.to(pin, { '--gx': '96%', duration: 0.7 }, 0);
    tl.to('.doors-copy', { opacity: 0, y: -26, duration: 0.14 }, 0);
    tl.to(wm, { '--sheen': '72%', duration: 1 }, 0);
    tl.to(cat, { yPercent: -2, duration: 1 }, 0);
    tl.to('.hero-halo', { opacity: 1.0, scale: 1.08, duration: 1 }, 0);
    tl.to({}, { duration: 0.38 });
    ScrollTrigger.addEventListener('refresh', markOpen);
    // светлое пятно за курсором, лёгкий параллакс
    const lightX = gsap.quickSetter(hero, '--lx', '%'), lightY = gsap.quickSetter(hero, '--ly', '%');
    const px = { x: 64, y: 44 };
    const catX = gsap.quickTo(cat, 'x', { duration: 1.2, ease: 'power3' }), catY = gsap.quickTo(cat, 'y', { duration: 1.2, ease: 'power3' });
    const wordX = gsap.quickTo(word, 'x', { duration: 1.6, ease: 'power3' }), wordY = gsap.quickTo(word, 'y', { duration: 1.6, ease: 'power3' });
    let visible = true;
    ScrollTrigger.create({ trigger: gate, start: 'top bottom', end: 'bottom top', onToggle: (s) => { visible = s.isActive; } });
    gsap.ticker.add((time) => {
      if (!visible) return;
      const recent = pointer.moved && performance.now() - pointer.t < 3200;
      let tx, ty;
      if (recent && fine) { tx = (pointer.x / innerWidth) * 100; ty = (pointer.y / innerHeight) * 100; }
      else { tx = 64 + Math.sin(time * 0.45) * 9; ty = 44 + Math.cos(time * 0.37) * 8; }
      px.x += (tx - px.x) * 0.06; px.y += (ty - px.y) * 0.06; lightX(px.x); lightY(px.y);
      if (fine && recent) { catX(-pointer.nx * 16); catY(pointer.ny * -9); wordX(pointer.nx * 12); wordY(pointer.ny * -6); }
      else if (!fine) { const a = Math.sin(time * 0.5); catX(a * 6); wordX(-a * 5); }
    });
  }

  /* ═════ 02 · ВЗГЛЯД (WebGL) ═════ */
  function initEye() {
    const sec = $('[data-eye]'); if (!sec) return;
    const canvas = $('[data-eye-canvas]', sec), pinEl = $('.eye-pin', sec);
    const colors = (sec.dataset.colors || '#d9a238').split(',');
    const lines = $$('[data-eye-line]', sec), words = $$('[data-eye-word]', sec), dots = $$('[data-eye-dot]', sec);
    let eye = null;
    if (ZH.gl && ZH.gl.supported && !ZH.reduce) { try { eye = ZH.gl.Eye(canvas, colors); if (small()) eye.state.dprCap = 1.2; eye.resize(); } catch (e) { console.warn('eye', e); eye = null; } }
    if (!eye) {
      sec.classList.add('no-gl'); lines.forEach((l, i) => gsap.set(l, { opacity: 1, position: 'relative', left: 'auto', right: 'auto', bottom: 'auto', margin: '24px var(--gut)' }));
      $('.eye-pin', sec).style.height = 'auto'; $('.eye-pin', sec).style.padding = '14vh 0'; $('.eye-copy', sec).style.position = 'relative'; return;
    }
    const st = { z: 0, c: 0, p: 0.1, open: 0, fade: 0 };
    const apply = () => {
      canvas.style.opacity = Math.min(1, st.open * 3.5).toFixed(3);
      eye.set({ zoom: st.z, color: st.c, pupil: clamp(0.08 + st.z * 0.5, 0, 0.9) * (1 - 0.0) + st.p * 0, open: st.open, fade: st.fade });
      words.forEach((w, i) => w.classList.toggle('is-on', Math.abs(st.c - i) < 0.62));
      const act = Math.round(clamp(st.c, 0, colors.length - 1)); dots.forEach((d, i) => d.classList.toggle('is-on', i === act));
    };
    ScrollTrigger.create({ trigger: sec, start: 'top bottom', end: 'bottom top', onToggle: (s) => (s.isActive ? eye.start() : eye.stop()) });
    addEventListener('resize', () => eye.resize());
    addEventListener('pointermove', () => { if (fine) eye.pointer(pointer.nx, pointer.ny); }, { passive: true });
    const tl = gsap.timeline({ defaults: { ease: 'none' }, scrollTrigger: { trigger: sec, start: 'top top', end: () => '+=' + Math.round(innerHeight * 3.4), pin: pinEl, scrub: 0.8, anticipatePin: 1, invalidateOnRefresh: true }, onUpdate: apply });
    ScrollTrigger.create({ trigger: sec, start: 'top 80%', once: true, onEnter: () => gsap.to(st, { open: 1, duration: 2.0, ease: 'power2.inOut', onUpdate: apply }) });
    tl.to(st, { z: 0.34, duration: 0.34 }, 0.04);
    tl.to(st, { c: 1, duration: 0.18, ease: 'power1.inOut' }, 0.3);
    tl.to(st, { z: 0.62, duration: 0.2 }, 0.38);
    tl.to(st, { c: Math.min(2, colors.length - 1), duration: 0.18, ease: 'power1.inOut' }, 0.52);
    tl.to(st, { z: 0.8, duration: 0.18 }, 0.62);
    tl.to(st, { z: 1, fade: 1, duration: 0.2, ease: 'power1.in' }, 0.8);
    // фразы
    const L = lines;
    if (L[0]) tl.fromTo(L[0], { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.08, ease: 'power2.out' }, 0.05).to(L[0], { opacity: 0, y: -40, duration: 0.07 }, 0.24);
    if (L[1]) tl.fromTo(L[1], { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.08, ease: 'power2.out' }, 0.29).to(L[1], { opacity: 0, y: -40, duration: 0.07 }, 0.72);
    if (L[2]) tl.fromTo(L[2], { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.08, ease: 'power2.out' }, 0.76).to(L[2], { opacity: 0, duration: 0.06 }, 0.92);
    apply();
  }

  /* ═════ 03 · ТРИ МИРА ═════ */
  function initWorlds() {
    const sec = $('[data-worlds]'); if (!sec) return;
    const n = parseInt(sec.dataset.count, 10) || 1;
    const bgs = $$('[data-wbg]', sec), cats = $$('[data-wcat]', sec), slides = $$('[data-wslide]', sec), navs = $$('[data-wgo]', sec), pinEl = $('.worlds-pin', sec);
    const rosCanvas = $('[data-rosette]', sec), shade = $('[data-shade]', sec);
    // буквы названий
    slides.forEach((s) => {
      const h = $('.wname', s); const txt = h.textContent; h.setAttribute('aria-label', txt); h.textContent = '';
      const ln = document.createElement('span'); ln.className = 'ln'; ln.setAttribute('aria-hidden', 'true');
      txt.split('').forEach((ch) => { const c = document.createElement('span'); c.className = 'ch'; c.style.display = 'inline-block'; c.textContent = ch === ' ' ? ' ' : ch; ln.append(c); });
      h.append(ln);
    });
    let active = -1;
    function activate(i, animate) {
      if (i === active) return; active = i;
      slides.forEach((s, k) => { s.classList.toggle('is-active', k === i); });
      navs.forEach((b, k) => { b.classList.toggle('is-active', k === i); b.setAttribute('aria-current', k === i ? 'true' : 'false'); });
      const s = slides[i]; if (!s) return;
      pinEl.style.setProperty('--accent', getComputedStyle(s).getPropertyValue('--accent'));
      if (animate && !ZH.reduce) {
        const chars = $$('.ch', s);
        gsap.fromTo(chars, { yPercent: 110, rotate: 5 }, { yPercent: 0, rotate: 0, duration: 1.15, ease: 'power4.out', stagger: 0.04, overwrite: true });
        gsap.fromTo($$('.wslide-top, .wsub, .wfacts>div, .wnotes p, .wlinks', s), { y: 28, opacity: 0 }, { y: 0, opacity: 1, duration: 0.9, ease: 'power3.out', stagger: 0.07, delay: 0.15, overwrite: true });
      }
    }
    let ros = null;
    if (rosCanvas && ZH.gl && ZH.gl.supported && !ZH.reduce && $('.wbg-wild', sec)) { try { ros = ZH.gl.Rosette(rosCanvas); } catch (e) { ros = null; } }
    const wildIdx = bgs.findIndex((b) => b.classList.contains('wbg-wild'));
    let rosOn = false;
    function render(p) {
      const x = clamp(p, 0, 0.9999) * n;
      let best = 0, bestW = -1;
      for (let i = 0; i < n; i++) {
        const d = Math.abs(x - (i + 0.5));
        let w = 1 - smooth(0.5 - 0.1, 0.5 + 0.1, d);
        if ((i === 0 && x < 0.5) || (i === n - 1 && x > n - 0.5)) w = 1;
        if (w > bestW) { bestW = w; best = i; }
        const off = (i + 0.5 - x);
        if (bgs[i]) { bgs[i].style.opacity = w.toFixed(3); bgs[i].style.transform = 'scale(' + (1 + (1 - w) * 0.05).toFixed(3) + ') translate3d(' + (off * -2).toFixed(2) + '%,0,0)'; }
        if (cats[i]) gsap.set(cats[i], { opacity: clamp(w * 1.15, 0, 1), xPercent: -50, x: off * innerWidth * (small() ? 0.5 : 0.16), scale: 1 + (1 - w) * 0.08, filter: 'blur(' + ((1 - w) * 8).toFixed(1) + 'px)' });
        if (slides[i]) {
          const o = clamp((w - 0.45) / 0.45, 0, 1); slides[i].style.opacity = o.toFixed(3); slides[i].style.visibility = o > 0.01 ? 'visible' : 'hidden';
          slides[i].style.transform = 'translate3d(' + (off * -60).toFixed(1) + 'px,0,0)';
        }
      }
      activate(best, true);
      if (ros) {
        const wv = wildIdx >= 0 ? parseFloat(bgs[wildIdx].style.opacity || 0) : 0;
        rosCanvas.style.opacity = (wv * 0.9).toFixed(3); if (shade) shade.style.opacity = wv.toFixed(3);
        const want = wv > 0.02; if (want !== rosOn) { rosOn = want; want ? ros.start() : ros.stop(); }
        ros.set({ zoom: clamp((x - wildIdx) , 0, 1), shx: x * 0.6 });
      }
    }
    if (ZH.reduce) {
      sec.classList.add('is-static');
      const go = (i) => { render((i + 0.5) / n); };
      go(0); navs.forEach((b, i) => b.addEventListener('click', () => go(i))); return;
    }
    const total = () => Math.round(innerHeight * (n * 1.05));
    const trig = ScrollTrigger.create({ trigger: sec, start: 'top top', end: () => '+=' + total(), pin: pinEl, scrub: true, anticipatePin: 1, invalidateOnRefresh: true, onUpdate: (self) => render(self.progress), onRefresh: (self) => render(self.progress) });
    ScrollTrigger.create({ trigger: sec, start: 'top bottom', end: 'bottom top', onToggle: (s) => { if (!s.isActive && ros) { ros.stop(); rosOn = false; } } });
    navs.forEach((b, i) => b.addEventListener('click', () => { const pos = trig.start + ((i + 0.5) / n) * (trig.end - trig.start); ZH.scrollTo(pos, { duration: 1.8 }); }));
    if (ros) { addEventListener('resize', () => ros.resize()); addEventListener('pointermove', () => ros.light(pointer.nx, pointer.ny), { passive: true }); ros.resize(); }
    render(0);
  }

  /* ═════ 04 · ГОРИЗОНТАЛЬНАЯ ГАЛЕРЕЯ ═════ */
  function initGallery() {
    const sec = $('[data-hgallery]'); if (!sec) return;
    const track = $('[data-track]', sec), bar = $('.gallery-bar span', sec), pinEl = $('.gallery-pin', sec);
    if (ZH.reduce) { sec.classList.add('is-static'); return; }
    const dist = () => Math.max(0, track.scrollWidth - innerWidth);
    const tween = gsap.to(track, { x: () => -dist(), ease: 'none', scrollTrigger: { trigger: sec, start: 'top top', end: () => '+=' + Math.round(dist() * 1.05), pin: pinEl, scrub: 0.7, anticipatePin: 1, invalidateOnRefresh: true, onUpdate: (s) => bar && bar.style.setProperty('--p', s.progress.toFixed(4)) } });
    $$('.gframe-wide .gframe-btn img', sec).forEach((img) => {
      gsap.fromTo(img, { xPercent: 5, scale: 1.14 }, { xPercent: -5, scale: 1.14, ease: 'none', scrollTrigger: { trigger: img.closest('.gframe'), containerAnimation: tween, start: 'left right', end: 'right left', scrub: true } });
    });
    $$('.gportrait img', sec).forEach((img) => {
      gsap.fromTo(img, { x: 40 }, { x: -40, ease: 'none', scrollTrigger: { trigger: img.closest('.gframe'), containerAnimation: tween, start: 'left right', end: 'right left', scrub: true } });
    });
    $$('.gframe', sec).forEach((f) => { gsap.from(f, { opacity: 0, y: 50, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: f, containerAnimation: tween, start: 'left 96%', toggleActions: 'play none none none' } }); });
  }

  /* ═════ 05 · РОДОСЛОВНАЯ ═════ */
  function initPedigree() {
    const sec = $('[data-pedigree]'); if (!sec) return;
    const svg = $('.pedigree-svg', sec); if (!svg) return;
    const nodes = $$('.pn', svg), edges = $$('.pe', svg);
    if (innerWidth < 700) { svg.setAttribute('viewBox', '0 0 640 580'); $$('.pn-g3, .pe[data-g="3"]', svg).forEach((x) => (x.style.display = 'none')); nodes.splice(0, nodes.length, ...nodes.filter((n) => +n.dataset.g < 3)); edges.splice(0, edges.length, ...edges.filter((e) => +e.dataset.g < 3)); }
    const card = $('[data-pcard]', sec), nm = $('[data-pcard-name]', sec), rel = $('[data-pcard-rel]', sec), meta = $('[data-pcard-meta]', sec);
    function chain(id) { const [g, i] = id.split('-').map(Number); const out = [id]; let gg = g, ii = i; while (gg > 0) { gg--; ii = Math.floor(ii / 2); out.push(gg + '-' + ii); } return out; }
    let current = null;
    function select(node) {
      if (!node) return; current = node;
      nodes.forEach((n) => n.classList.remove('is-active', 'is-path')); edges.forEach((e) => e.classList.remove('is-path'));
      const ids = chain(node.dataset.id);
      nodes.forEach((n) => { if (ids.includes(n.dataset.id)) n.classList.add('is-path'); });
      node.classList.add('is-active');
      edges.forEach((e) => { if (ids.includes(e.dataset.from) && ids.includes(e.dataset.to)) e.classList.add('is-path'); });
      nm.textContent = node.dataset.name; rel.textContent = node.dataset.rel; meta.textContent = node.dataset.meta;
      if (!ZH.reduce) gsap.fromTo([nm, rel, meta], { y: 14, opacity: 0 }, { y: 0, opacity: 1, duration: 0.5, stagger: 0.05, ease: 'power3.out', overwrite: true });
    }
    nodes.forEach((n) => { ['mouseenter', 'focus', 'click'].forEach((ev) => n.addEventListener(ev, () => select(n))); n.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(n); } }); });
    select(nodes[0]);
    if (ZH.reduce) { gsap.set(nodes, { opacity: 1 }); edges.forEach((e) => (e.style.strokeDashoffset = 0)); return; }
    const byGen = [0, 1, 2, 3].map((g) => ({ nodes: nodes.filter((n) => +n.dataset.g === g), edges: edges.filter((e) => +e.dataset.g === g) }));
    const tl = gsap.timeline({ paused: true });
    byGen.forEach((gen, g) => {
      if (!gen.nodes.length) return;
      tl.to(gen.nodes, { opacity: 1, duration: 0.7, stagger: 0.05, ease: 'power2.out' }, g * 0.55);
      if (gen.edges.length) tl.to(gen.edges, { strokeDashoffset: 0, duration: 1.1, stagger: 0.05, ease: 'power2.inOut' }, g * 0.55 - 0.2);
    });
    ScrollTrigger.create({ trigger: svg, start: 'top 75%', once: true, onEnter: () => tl.play() });
  }

  /* ═════ 07 · КАРТА ═════ */
  function initMap() {
    const wrap = $('[data-map]'); if (!wrap) return;
    const line = $('.kz-line', wrap), dots = $('.kz-dots', wrap), cities = $$('.kz-city', wrap), chips = $$('[data-city-chip]', wrap);
    const hot = (name, on) => { cities.forEach((c) => c.classList.toggle('is-hot', on && c.dataset.city === name)); chips.forEach((c) => c.classList.toggle('is-hot', on && c.dataset.cityChip === name)); };
    chips.forEach((c) => { c.addEventListener('mouseenter', () => hot(c.dataset.cityChip, true)); c.addEventListener('mouseleave', () => hot('', false)); c.addEventListener('focus', () => hot(c.dataset.cityChip, true)); c.addEventListener('blur', () => hot('', false)); });
    cities.forEach((c) => { c.addEventListener('mouseenter', () => hot(c.dataset.city, true)); c.addEventListener('mouseleave', () => hot('', false)); });
    if (ZH.reduce) { if (line) line.style.strokeDashoffset = 0; return; }
    gsap.set(cities, { opacity: 0 }); if (dots) gsap.set(dots, { opacity: 0 });
    const tl = gsap.timeline({ paused: true });
    if (line) tl.to(line, { strokeDashoffset: 0, duration: 2.4, ease: 'power2.inOut' }, 0);
    if (dots) tl.to(dots, { opacity: 1, duration: 1.6, ease: 'power1.out' }, 0.4);
    tl.to(cities, { opacity: (i, el) => (el.classList.contains('is-on') ? 1 : 0.3), duration: 0.7, stagger: 0.07, ease: 'power2.out' }, 1.2);
    ScrollTrigger.create({ trigger: wrap, start: 'top 70%', once: true, onEnter: () => tl.play() });
  }

  /* ═════ 09 · FAQ ═════ */
  function initFaq() {
    $$('.faq-list details').forEach((d) => {
      const s = $('summary', d), p = $('p', d);
      s.addEventListener('click', (e) => {
        e.preventDefault();
        if (ZH.reduce) { d.open = !d.open; return; }
        if (d.open) { gsap.to(p, { height: 0, opacity: 0, paddingBottom: 0, duration: 0.5, ease: 'power3.inOut', onComplete: () => { d.open = false; gsap.set(p, { clearProps: 'all' }); ScrollTrigger.refresh(); } }); }
        else { d.open = true; gsap.fromTo(p, { height: 0, opacity: 0 }, { height: 'auto', opacity: 1, duration: 0.7, ease: 'power3.out', onComplete: () => { gsap.set(p, { clearProps: 'height' }); ScrollTrigger.refresh(); } }); }
      });
    });
  }

  /* ═════ 10 · ФИНАЛ: КОШКА ИЗ ЧАСТИЦ ═════ */
  function initFinale() {
    const sec = $('[data-finale]'); if (!sec) return;
    const canvas = $('[data-particles]', sec); if (!canvas) return;
    const fallback = () => { const im = document.createElement('img'); im.src = canvas.dataset.src; im.alt = ''; im.className = 'finale-fallback'; im.setAttribute('aria-hidden', 'true'); sec.prepend(im); canvas.remove(); };
    if (!ZH.gl || !ZH.gl.supported || ZH.reduce) { fallback(); return; }
    let part; try { part = ZH.gl.Particles(canvas, canvas.dataset.src, { count: small() ? 4200 : 9800, align: small() ? 0.5 : 0.77, height: small() ? 0.62 : 0.92 }); } catch (e) { fallback(); return; }
    ScrollTrigger.create({ trigger: sec, start: 'top bottom', end: 'bottom top', onToggle: (s) => (s.isActive ? part.start() : part.stop()) });
    ScrollTrigger.create({ trigger: sec, start: 'top 85%', end: 'top 25%', scrub: 0.9, onUpdate: (s) => part.set({ assemble: s.progress }) });
    sec.addEventListener('pointermove', (e) => { const r = canvas.getBoundingClientRect(); part.pointer(e.clientX - r.left, e.clientY - r.top); });
    sec.addEventListener('pointerleave', () => part.pointer(-9999, -9999));
    addEventListener('resize', () => part.resize());
  }

  /* ═════ рейка глав ═════ */
  function initRail() {
    const secs = $$('[data-chapter]'); if (secs.length < 3 || small()) return;
    const rail = document.createElement('nav'); rail.className = 'rail'; rail.setAttribute('aria-label', 'Главы страницы');
    const seen = new Set();
    secs.forEach((s) => {
      const name = s.dataset.chapter; if (seen.has(name)) return; seen.add(name);
      const b = document.createElement('button'); b.type = 'button'; b.innerHTML = '<i></i><span>' + name + '</span>'; b.addEventListener('click', () => ZH.scrollTo(s, { duration: 1.8 })); rail.append(b); s._rail = b;
      ScrollTrigger.create({ trigger: s, start: 'top 55%', end: 'bottom 55%', onToggle: (self) => { if (self.isActive) { $$('button', rail).forEach((x) => x.classList.remove('is-on')); b.classList.add('is-on'); } } });
    });
    document.body.append(rail);
    ScrollTrigger.create({ trigger: 'main', start: 'top -20%', end: 'bottom 80%', onToggle: (s) => rail.classList.toggle('is-visible', s.isActive) });
  }

  ZH.onReady(() => {
    initGate(); initEye(); initWorlds(); initGallery(); initPedigree(); initMap(); initFaq(); initFinale(); initRail();
    ScrollTrigger.refresh();
  });
})();
