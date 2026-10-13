/* ZHIBEK 4 — весь клиентский код сайта. Без библиотек и без перехвата прокрутки. */
(() => {
  'use strict';
  const doc = document.documentElement;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const cfg = window.ZHIBEK || {};
  window.clearTimeout(window.__zhT);

  /* ---------- шапка ---------- */
  const header = $('[data-site-header]');
  if (header) {
    let ticking = false;
    const update = () => { header.classList.toggle('is-scrolled', window.scrollY > 8); ticking = false; };
    window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
  }

  /* ---------- меню ---------- */
  const menu = $('#menu');
  const toggle = $('.menu-toggle');
  if (menu && toggle) {
    menu.hidden = false;
    const setMenu = (open) => {
      doc.classList.toggle('menu-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
      if (open) { const first = $('a', menu); if (first) first.focus({ preventScroll: true }); } else if (document.activeElement && menu.contains(document.activeElement)) toggle.focus({ preventScroll: true });
    };
    toggle.addEventListener('click', () => setMenu(!doc.classList.contains('menu-open')));
    menu.addEventListener('click', (e) => { if (e.target.closest('a') || e.target.closest('[data-enquire]')) setMenu(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && doc.classList.contains('menu-open')) setMenu(false); });
    window.matchMedia('(min-width: 960px)').addEventListener('change', (e) => { if (e.matches) setMenu(false); });
  }

  /* ---------- появление блоков ---------- */
  const reveals = $$('[data-reveal]');
  if ('IntersectionObserver' in window && reveals.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-in'));
  }

  /* ---------- сообщения ---------- */
  const toastEl = $('.toast');
  let toastTimer;
  const toast = (text) => {
    if (!toastEl) return;
    toastEl.textContent = text; toastEl.classList.add('is-on');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => toastEl.classList.remove('is-on'), 3200);
  };

  /* ---------- заявка ---------- */
  const dlg = $('#enquiry');
  const form = $('#enquiry-form');
  const result = $('#form-result');
  if (dlg && form) {
    const openDialog = (topic) => {
      if (typeof dlg.showModal !== 'function') { window.location.href = (cfg.contact || '#'); return; }
      doc.classList.remove('menu-open');
      const sel = $('#f-breed');
      if (sel && topic) { const opt = Array.from(sel.options).find((o) => o.value === topic || o.textContent === topic); if (opt) sel.value = opt.value; }
      dlg.classList.remove('is-sent'); result.textContent = ''; result.className = 'form-result';
      dlg.showModal(); doc.classList.add('dialog-open');
      const nm = $('#f-name'); if (nm) setTimeout(() => nm.focus({ preventScroll: true }), 60);
    };
    document.addEventListener('click', (e) => {
      const t = e.target.closest('[data-enquire]');
      if (t) { e.preventDefault(); openDialog(t.getAttribute('data-enquire')); return; }
      if (e.target.closest('[data-close]') && dlg.open) { dlg.close(); return; }
      if (e.target === dlg) dlg.close();
    });
    dlg.addEventListener('close', () => doc.classList.remove('dialog-open'));

    const fail = (field, text) => { const wrap = field.closest('.field'); if (wrap) wrap.classList.add('has-error'); result.textContent = text; result.className = 'form-result is-error'; field.focus(); };
    form.addEventListener('input', (e) => { const w = e.target.closest('.field'); if (w) w.classList.remove('has-error'); });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      $$('.has-error', form).forEach((n) => n.classList.remove('has-error'));
      const name = form.elements.name, contact = form.elements.contact, city = form.elements.city, consent = form.elements.consent;
      if (name.value.trim().length < 2) return fail(name, 'Укажите имя.');
      const cv = contact.value.trim();
      const looksEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cv);
      const digits = cv.replace(/\D/g, '');
      if (!looksEmail && !(digits.length >= 7 && digits.length <= 15)) return fail(contact, 'Укажите телефон с кодом страны или email.');
      if (city.value.trim().length < 2) return fail(city, 'Укажите город.');
      if (!consent.checked) { result.textContent = 'Подтвердите согласие на обработку данных.'; result.className = 'form-result is-error'; consent.focus(); return; }
      const btn = $('button[type=submit]', form); btn.disabled = true; result.className = 'form-result'; result.textContent = 'Отправляем…';
      try {
        // Свежий nonce: на сайтах с кэшем страницы встроенный мог устареть.
        try {
          const n = await fetch(form.getAttribute('action') + '?action=zhibek_nonce', { credentials: 'same-origin' }).then((r) => r.json());
          if (n && n.success && n.data && n.data.nonce) form.elements.nonce.value = n.data.nonce;
        } catch (_) { /* остаётся nonce из страницы */ }
        const res = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        const json = await res.json().catch(() => null);
        if (json && json.success) {
          dlg.classList.add('is-sent'); form.reset();
        } else {
          result.textContent = (json && json.data && json.data.message) || 'Не удалось отправить заявку. Попробуйте ещё раз или напишите в мессенджер.';
          result.className = 'form-result is-error';
        }
      } catch (_) {
        result.textContent = 'Нет связи с сервером. Проверьте интернет и повторите.'; result.className = 'form-result is-error';
      } finally { btn.disabled = false; }
    });
  }

  /* ---------- фильтры котов: порода и возраст ---------- */
  const filterBox = $('[data-filters]');
  const grid = $('[data-cgrid]');
  if (filterBox && grid) {
    const cards = $$('.ccard', grid);
    const empty = $('[data-filter-empty]');
    const state = { breed: 'all', kind: 'all' };
    const apply = (pushUrl) => {
      let shown = 0;
      cards.forEach((c) => {
        const ok = (state.breed === 'all' || c.dataset.breed === state.breed) && (state.kind === 'all' || c.dataset.kind === state.kind);
        const was = !c.classList.contains('is-hidden');
        c.classList.toggle('is-hidden', !ok);
        if (ok) { shown++; if (!was) { c.style.animation = 'none'; void c.offsetWidth; c.style.animation = `rise .6s var(--ease) ${Math.min(shown, 8) * 40}ms both`; } }
      });
      $$('.filter', filterBox).forEach((f) => { const on = state[f.dataset.group] === f.dataset.value; f.classList.toggle('is-active', on); f.setAttribute('aria-pressed', String(on)); });
      if (empty) empty.hidden = shown > 0;
      if (pushUrl && window.history && history.replaceState) {
        const u = new URL(window.location.href);
        if (state.breed === 'all') u.searchParams.delete('breed'); else u.searchParams.set('breed', state.breed);
        history.replaceState(null, '', u);
      }
    };
    filterBox.addEventListener('click', (e) => {
      const f = e.target.closest('.filter'); if (!f) return;
      state[f.dataset.group] = f.dataset.value; apply(f.dataset.group === 'breed');
    });
    const want = new URL(window.location.href).searchParams.get('breed');
    if (want && $(`.filter[data-group="breed"][data-value="${CSS.escape(want)}"]`, filterBox)) { state.breed = want; apply(false); }
  }

  /* ---------- нить на странице «как забрать»: прогресс по прокрутке ---------- */
  const thread = $('[data-thread]');
  if (thread) {
    const steps = $$('.step', thread);
    let raf = 0;
    const draw = () => {
      raf = 0;
      const r = thread.getBoundingClientRect();
      const mark = window.innerHeight * 0.6;
      const p = Math.max(0, Math.min(1, (mark - r.top) / r.height));
      thread.style.setProperty('--p', p.toFixed(4));
      steps.forEach((st) => { const sr = st.getBoundingClientRect(); st.classList.toggle('is-passed', sr.top < mark); });
    };
    const queue = () => { if (!raf) raf = requestAnimationFrame(draw); };
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    draw();
  }

  /* ---------- горизонтальные ленты: перетаскивание мышью ---------- */
  $$('[data-drag]').forEach((rail) => {
    let down = false, sx = 0, sl = 0, moved = false;
    rail.addEventListener('pointerdown', (e) => { if (e.pointerType !== 'mouse') return; down = true; moved = false; sx = e.clientX; sl = rail.scrollLeft; });
    window.addEventListener('pointermove', (e) => {
      if (!down) return;
      const dx = e.clientX - sx;
      if (Math.abs(dx) > 4) { moved = true; rail.classList.add('is-drag'); }
      rail.scrollLeft = sl - dx;
    });
    window.addEventListener('pointerup', () => { down = false; rail.classList.remove('is-drag'); });
    rail.addEventListener('click', (e) => { if (moved) { e.preventDefault(); moved = false; } }, true);
  });

  /* ---------- частицы: кот собирается из точек и разлетается от курсора ---------- */
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  $$('[data-particles]').forEach((host) => {
    const canvas = $('canvas', host);
    if (!canvas || reduce || !canvas.getContext) return;
    const ctx = canvas.getContext('2d', { alpha: true });
    const small = window.matchMedia('(max-width: 700px)').matches;
    const S = small ? 420 : 620;
    canvas.width = S; canvas.height = S;
    const img = new Image();
    img.decoding = 'async';
    let P = null, N = 0, live = false, running = false, t0 = 0, raf = 0;
    const ptr = { x: -999, y: -999, on: false };
    const build = () => {
      const off = document.createElement('canvas'); off.width = S; off.height = S;
      const o = off.getContext('2d', { willReadFrequently: true });
      const k = Math.min(S / img.naturalWidth, S / img.naturalHeight) * 0.96;
      const w = img.naturalWidth * k, h = img.naturalHeight * k;
      o.drawImage(img, (S - w) / 2, S - h - S * 0.02, w, h);
      const d = o.getImageData(0, 0, S, S).data;
      const step = small ? 5 : 4;
      const pts = [];
      for (let y = 0; y < S; y += step) for (let x = 0; x < S; x += step) {
        const i = (y * S + x) * 4;
        if (d[i + 3] > 120) pts.push([x + (Math.random() - .5) * step, y + (Math.random() - .5) * step, d[i], d[i + 1], d[i + 2]]);
      }
      N = pts.length;
      P = { hx: new Float32Array(N), hy: new Float32Array(N), x: new Float32Array(N), y: new Float32Array(N), vx: new Float32Array(N), vy: new Float32Array(N), c: new Uint32Array(N), delay: new Float32Array(N) };
      for (let i = 0; i < N; i++) {
        const p = pts[i];
        P.hx[i] = p[0]; P.hy[i] = p[1];
        const a = Math.random() * Math.PI * 2, r = S * (0.5 + Math.random() * 0.7);
        P.x[i] = S / 2 + Math.cos(a) * r; P.y[i] = S / 2 + Math.sin(a) * r;
        const lift = (v) => Math.min(255, v * 0.7 + 95) | 0;
        P.c[i] = (255 << 24) | (lift(p[4]) << 16) | (lift(p[3]) << 8) | lift(p[2]);
        P.delay[i] = Math.random() * 0.9;
      }
      host.classList.add('is-live');
    };
    const frame = (now) => {
      raf = 0;
      if (!running) return;
      const t = (now - t0) / 1000;
      const buf = ctx.createImageData(S, S);
      const px = new Uint32Array(buf.data.buffer);
      const R = S * 0.13, R2 = R * R, dpr = S / (host.clientWidth || S);
      for (let i = 0; i < N; i++) {
        const e = Math.max(0, Math.min(1, (t - P.delay[i]) / 1.6));
        const k = 0.012 + 0.07 * e * e;
        let ax = (P.hx[i] - P.x[i]) * k, ay = (P.hy[i] - P.y[i]) * k;
        if (ptr.on) {
          const dx = P.x[i] - ptr.x, dy = P.y[i] - ptr.y, dd = dx * dx + dy * dy;
          if (dd < R2) { const f = (1 - dd / R2) * 3.4; const L = Math.sqrt(dd) + 0.001; ax += dx / L * f; ay += dy / L * f; }
        }
        P.vx[i] = (P.vx[i] + ax) * 0.86; P.vy[i] = (P.vy[i] + ay) * 0.86;
        P.x[i] += P.vx[i]; P.y[i] += P.vy[i];
        const x = P.x[i] | 0, y = P.y[i] | 0;
        if (x > 0 && x < S - 2 && y > 0 && y < S - 2) { const j = y * S + x; px[j] = P.c[i]; px[j + 1] = P.c[i]; px[j + S] = P.c[i]; px[j + S + 1] = P.c[i]; }
      }
      ctx.putImageData(buf, 0, 0);
      raf = requestAnimationFrame(frame);
    };
    const start = () => { if (running || !P) return; running = true; if (!t0) t0 = performance.now(); raf = requestAnimationFrame(frame); };
    const stop = () => { running = false; if (raf) cancelAnimationFrame(raf); raf = 0; };
    const setPtr = (e) => { const r = canvas.getBoundingClientRect(); ptr.x = (e.clientX - r.left) * (S / r.width); ptr.y = (e.clientY - r.top) * (S / r.height); ptr.on = true; };
    host.addEventListener('pointermove', setPtr);
    host.addEventListener('pointerdown', setPtr);
    host.addEventListener('pointerleave', () => { ptr.on = false; });
    host.addEventListener('pointerup', () => { if (window.matchMedia('(hover: none)').matches) ptr.on = false; });
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) {
        if (!img.src) { img.onload = () => { build(); live = true; if (en.target.dataset.on) start(); }; img.src = host.dataset.src; }
        host.dataset.on = '1'; if (live) { t0 = t0 || performance.now(); start(); }
      } else { host.dataset.on = ''; stop(); }
    }), { threshold: 0.15 });
    io.observe(host);
  });

  /* ---------- галерея на страницах породы и котёнка ---------- */
  $$('[data-gallery]').forEach((g) => {
    const dataEl = $('.zg-data', g);
    let items = [];
    try { items = JSON.parse(dataEl ? dataEl.textContent : '[]'); } catch (_) { items = []; }
    const main = $('.zg-main', g), img = main && $('img', main), thumbs = $$('[data-zg-thumb]', g);
    let cur = 0;
    const show = (i) => {
      if (!items[i] || !img) return;
      cur = i; img.src = items[i].preview; img.alt = items[i].alt || ''; main.href = items[i].src;
      main.dataset.type = items[i].cutout ? 'cutout' : 'photo';
      thumbs.forEach((t, k) => t.setAttribute('aria-pressed', String(k === i)));
    };
    thumbs.forEach((t) => t.addEventListener('click', () => show(Number(t.dataset.zgThumb))));
    if (main) main.addEventListener('click', (e) => {
      e.preventDefault();
      if (items[cur] && items[cur].cutout) return;
      let box = $('#lightbox');
      if (!box) {
        box = document.createElement('dialog'); box.id = 'lightbox'; box.className = 'lightbox';
        box.innerHTML = '<button class="lb-close" type="button" aria-label="Закрыть">×</button><img alt=""><button class="lb-nav lb-prev" type="button" aria-label="Назад">‹</button><button class="lb-nav lb-next" type="button" aria-label="Вперёд">›</button>';
        document.body.appendChild(box);
        box.addEventListener('click', (ev) => { if (ev.target === box || ev.target.closest('.lb-close')) box.close(); });
        box.addEventListener('close', () => doc.classList.remove('dialog-open'));
      }
      const lbImg = $('img', box);
      const go = (d) => { cur = (cur + d + items.length) % items.length; lbImg.src = items[cur].src; lbImg.alt = items[cur].alt || ''; show(cur); };
      lbImg.src = items[cur].src; lbImg.alt = items[cur].alt || '';
      const multi = items.length > 1;
      $$('.lb-nav', box).forEach((b) => { b.hidden = !multi; });
      $('.lb-prev', box).onclick = () => go(-1); $('.lb-next', box).onclick = () => go(1);
      box.onkeydown = (ev) => { if (ev.key === 'ArrowLeft') go(-1); if (ev.key === 'ArrowRight') go(1); };
      box.showModal(); doc.classList.add('dialog-open');
    });
  });

  /* ---------- сцены с котом: кадры меняются при прокрутке ---------- */
  const sceneReduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const saveData = !!(navigator.connection && navigator.connection.saveData);
  $$('[data-scene]').forEach((scene) => {
    const sticky = $('.scene-sticky', scene);
    const canvas = $('canvas', scene);
    const n = Number(scene.dataset.frames), base = scene.dataset.base;
    if (!sticky || !canvas || !n || !canvas.getContext || sceneReduce || saveData) { scene.classList.add('is-end'); return; }
    const ctx = canvas.getContext('2d');
    const frames = new Array(n + 1);
    let target = 1, shown = 0, started = false, raf = 0, near = false;
    const url = (i) => `${base}frame_${String(i).padStart(4, '0')}.webp`;
    const draw = () => {
      raf = 0;
      let i = target;
      if (!frames[i]) { // ближайший загруженный кадр
        let d = 1;
        while (d < n && !frames[i - d] && !frames[i + d]) d++;
        i = frames[i - d] ? i - d : (frames[i + d] ? i + d : 0);
      }
      if (i && i !== shown) { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(frames[i], 0, 0, canvas.width, canvas.height); shown = i; scene.classList.add('is-live'); }
    };
    const load = () => {
      if (started) return; started = true;
      // сначала каждый пятый кадр, затем остальные: прокрутка работает, пока догружается
      const order = []; for (let k = 0; k < 5; k++) for (let i = 1 + k; i <= n; i += 5) order.push(i);
      let at = 0, active = 0;
      const next = () => {
        while (active < 4 && at < order.length) {
          const i = order[at++]; active++;
          const im = new Image(); im.decoding = 'async';
          im.onload = () => { frames[i] = im; active--; if (!raf) raf = requestAnimationFrame(draw); next(); };
          im.onerror = () => { active--; next(); };
          im.src = url(i);
        }
      };
      next();
    };
    const update = () => {
      const r = scene.getBoundingClientRect();
      const span = Math.max(1, r.height - sticky.offsetHeight);
      const p = Math.max(0, Math.min(1, -r.top / span));
      sticky.style.setProperty('--p', p.toFixed(3));
      scene.classList.toggle('is-end', p > 0.6);
      target = Math.max(1, Math.min(n, 1 + Math.round(p * (n - 1))));
      if (!raf) raf = requestAnimationFrame(draw);
    };
    let ticking = false;
    const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(() => { ticking = false; if (near) update(); }); } };
    new IntersectionObserver((en) => { near = en[0].isIntersecting; if (near) { load(); update(); } }, { rootMargin: '120% 0px' }).observe(scene);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
  });

  doc.classList.add('is-ready');
})();
