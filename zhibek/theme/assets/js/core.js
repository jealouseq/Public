/* ZHIBEK · core.js — общие поведения: скролл, шапка, меню, курсор, форма, избранное, фильтры, лайтбокс, переходы */
(function () {
  'use strict';
  const doc = document, html = doc.documentElement;
  const $ = (s, r) => (r || doc).querySelector(s);
  const $$ = (s, r) => Array.from((r || doc).querySelectorAll(s));
  const CFG = window.ZHIBEK || {};
  const gsap = window.gsap, ScrollTrigger = window.ScrollTrigger, Flip = window.Flip;
  const fine = matchMedia('(hover:hover) and (pointer:fine)').matches;
  const ZH = (window.ZH = { cfg: CFG, $, $$, fine, lenis: null, gl: window.ZhGL, ready: [] });
  Object.defineProperty(ZH, 'reduce', { get() { return html.classList.contains('reduce-motion'); } });
  ZH.onReady = (fn) => { if (ZH._ready) fn(); else ZH.ready.push(fn); };
  if (!gsap || !ScrollTrigger) { html.classList.remove('zh-silk-wait'); return; }
  gsap.registerPlugin(ScrollTrigger, Flip, window.DrawSVGPlugin);

  /* ───── предпочтение анимаций ───── */
  const motionBtn = $('[data-motion-toggle]');
  function paintMotionBtn() { if (motionBtn) { const off = ZH.reduce; motionBtn.setAttribute('aria-pressed', off ? 'true' : 'false'); motionBtn.textContent = off ? 'Анимации: выключены' : 'Анимации: включены'; } }
  paintMotionBtn();
  if (motionBtn) motionBtn.addEventListener('click', () => {
    const off = !ZH.reduce;
    try { localStorage.setItem('zhMotion', off ? 'off' : 'on'); } catch (e) {}
    html.classList.toggle('reduce-motion', off); paintMotionBtn();
    location.reload();
  });
  try { if (localStorage.getItem('zhMotion') === 'on' && !matchMedia('(prefers-reduced-motion: reduce)').matches) html.classList.remove('reduce-motion'); } catch (e) {}

  /* ───── плавная прокрутка ───── */
  if (!ZH.reduce && window.Lenis) {
    const lenis = new window.Lenis({ duration: 1.15, easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), smoothWheel: true, wheelMultiplier: 0.95 });
    ZH.lenis = lenis;
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
  }
  ScrollTrigger.config({ ignoreMobileResize: true });
  ZH.scrollTo = (target, opts) => {
    opts = Object.assign({ offset: 0, duration: 1.6 }, opts || {});
    if (ZH.lenis) ZH.lenis.scrollTo(target, { offset: opts.offset, duration: opts.duration, easing: (t) => 1 - Math.pow(1 - t, 4) });
    else { const el = typeof target === 'string' ? $(target) : target; if (el) window.scrollTo({ top: typeof el === 'number' ? el : el.getBoundingClientRect().top + scrollY + opts.offset, behavior: 'smooth' }); }
  };
  ZH.stopScroll = () => { if (ZH.lenis) ZH.lenis.stop(); else html.style.overflow = 'hidden'; };
  ZH.startScroll = () => { if (ZH.lenis) ZH.lenis.start(); else html.style.overflow = ''; };
  $$('[data-scrollto],a[href^="#"]').forEach((a) => {
    a.addEventListener('click', (e) => {
      const h = a.getAttribute('href'); if (!h || h.length < 2 || h[0] !== '#') return;
      const t = $(h); if (!t) return; e.preventDefault(); closeMenu(); ZH.scrollTo(t, { offset: 0 });
    });
  });

  /* ───── прогресс-линия и шапка ───── */
  const bar = $('.scroll-progress');
  const header = $('[data-site-header]');
  let lastY = 0;
  function onScroll() {
    const y = scrollY, max = Math.max(1, doc.documentElement.scrollHeight - innerHeight);
    if (bar) bar.style.setProperty('--p', (y / max).toFixed(4));
    if (header) {
      header.classList.toggle('is-stuck', y > 30);
      const dy = y - lastY;
      if (!ZH.menuOpen) { if (dy > 6 && y > 400) header.classList.add('is-hidden'); else if (dy < -4 || y < 200) header.classList.remove('is-hidden'); }
    }
    lastY = y;
  }
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
  // цвет шапки по фону секции под ней
  $$('[data-header]').forEach((sec) => {
    ScrollTrigger.create({ trigger: sec, start: 'top 48px', end: 'bottom 48px', onToggle: (self) => { if (self.isActive && header) header.classList.toggle('is-light', sec.dataset.header === 'light'); } });
  });
  if (header && $('[data-header]')) header.classList.toggle('is-light', $('[data-header]').dataset.header === 'light');

  /* ───── меню ───── */
  const menu = $('#menu'), toggle = $('.menu-toggle');
  function openMenu() {
    if (!menu) return; ZH.menuOpen = true; menu.hidden = false; menu.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => menu.classList.add('is-open')); toggle.setAttribute('aria-expanded', 'true'); toggle.setAttribute('aria-label', 'Закрыть меню'); ZH.stopScroll();
    header && header.classList.remove('is-hidden');
  }
  function closeMenu() {
    if (!menu || !ZH.menuOpen) return; ZH.menuOpen = false; menu.classList.remove('is-open'); menu.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Открыть меню'); ZH.startScroll();
    setTimeout(() => { if (!ZH.menuOpen) menu.hidden = true; }, 900);
  }
  if (toggle) toggle.addEventListener('click', () => (ZH.menuOpen ? closeMenu() : openMenu()));
  addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
  if (menu) menu.addEventListener('click', (e) => { if (e.target.closest('a')) closeMenu(); });

  /* ───── курсор ───── */
  const cur = $('.cursor');
  if (cur && fine && !ZH.reduce) {
    const dot = $('.cursor-dot', cur), ring = $('.cursor-ring', cur), label = $('.cursor-label', cur);
    const qx = gsap.quickTo(ring, 'x', { duration: 0.45, ease: 'power3' }), qy = gsap.quickTo(ring, 'y', { duration: 0.45, ease: 'power3' });
    const dx = gsap.quickTo(dot, 'x', { duration: 0.08 }), dy = gsap.quickTo(dot, 'y', { duration: 0.08 });
    addEventListener('pointermove', (e) => { qx(e.clientX); qy(e.clientY); dx(e.clientX); dy(e.clientY); }, { passive: true });
    addEventListener('pointerdown', () => cur.classList.add('is-down')); addEventListener('pointerup', () => cur.classList.remove('is-down'));
    doc.addEventListener('pointerover', (e) => {
      const t = e.target.closest ? e.target.closest('[data-cursor],a,button,summary,.pn') : null;
      const txt = t && t.getAttribute && t.getAttribute('data-cursor');
      cur.classList.toggle('has-label', !!txt); if (txt) label.textContent = txt;
      cur.classList.toggle('is-link', !!t && !txt);
    });
    doc.addEventListener('pointerleave', () => cur.classList.remove('has-label', 'is-link'));
  }

  /* ───── магнитные кнопки ───── */
  if (fine && !ZH.reduce) {
    $$('[data-magnetic]').forEach((el) => {
      const sx = gsap.quickTo(el, 'x', { duration: 0.6, ease: 'elastic.out(1,.6)' }), sy = gsap.quickTo(el, 'y', { duration: 0.6, ease: 'elastic.out(1,.6)' });
      el.addEventListener('pointermove', (e) => { const r = el.getBoundingClientRect(); sx((e.clientX - (r.left + r.width / 2)) * 0.28); sy((e.clientY - (r.top + r.height / 2)) * 0.4); });
      el.addEventListener('pointerleave', () => { sx(0); sy(0); });
    });
  }

  /* ───── окно заявки ───── */
  const dlg = $('#enquiry'), form = $('#enquiry-form'), result = $('#form-result');
  function openEnquiry(topic) {
    if (!dlg) return;
    if (topic && form) { const sel = $('select[name="breed"]', form); if (sel) { const o = Array.from(sel.options).find((x) => x.textContent === topic); if (o) sel.value = o.value; } }
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    ZH.stopScroll(); closeMenu();
    setTimeout(() => { const f = $('input[name="name"]', dlg); f && f.focus({ preventScroll: true }); }, 60);
  }
  function closeEnquiry() { if (dlg && dlg.open) { dlg.close(); } ZH.startScroll(); }
  ZH.openEnquiry = openEnquiry;
  doc.addEventListener('click', (e) => { const b = e.target.closest('[data-enquire]'); if (b) { e.preventDefault(); openEnquiry(b.getAttribute('data-enquire')); } });
  if (dlg) {
    dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.closest('[data-close]')) closeEnquiry(); });
    dlg.addEventListener('close', () => { ZH.startScroll(); });
    dlg.addEventListener('cancel', () => { ZH.startScroll(); });
  }
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault(); result.className = 'form-result'; result.textContent = '';
      const name = form.name.value.trim(), contact = form.contact.value.trim();
      $$('input', form).forEach((i) => i.removeAttribute('aria-invalid'));
      if (name.length < 2) { form.name.setAttribute('aria-invalid', 'true'); form.name.focus(); result.textContent = 'Укажите имя — минимум 2 символа.'; result.classList.add('is-error'); return; }
      if (!contact) { form.contact.setAttribute('aria-invalid', 'true'); form.contact.focus(); result.textContent = 'Оставьте телефон с кодом страны или email.'; result.classList.add('is-error'); return; }
      if (!form.consent.checked) { result.textContent = 'Подтвердите согласие на обработку данных.'; result.classList.add('is-error'); return; }
      const btn = $('button[type=submit]', form); btn.disabled = true; btn.classList.add('is-busy'); result.textContent = 'Отправляем…';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
        const data = await res.json();
        if (data && data.success) {
          dlg.classList.add('is-sent');
          const body = $('.enquiry-body', dlg);
          const done = doc.createElement('div'); done.className = 'enquiry-done';
          done.innerHTML = '<span class="eyebrow">Заявка принята</span><h2 class="display-s">Спасибо, ' + name.replace(/[<>&]/g, '') + '.</h2><p class="enquiry-lead">Ответим в течение дня. Если удобнее, напишите нам сразу в мессенджер.</p>';
          const links = doc.createElement('p'); links.className = 'enquiry-alt';
          [['WhatsApp', CFG.whatsapp], ['Telegram', CFG.telegram]].forEach(([n, u]) => { if (u) { const a = doc.createElement('a'); a.href = u; a.target = '_blank'; a.rel = 'noopener'; a.textContent = n; links.append(a, ' '); } });
          done.append(links); body.append(done); form.reset();
        } else { result.textContent = (data && data.data && data.data.message) || 'Не удалось отправить. Попробуйте ещё раз.'; result.classList.add('is-error'); }
      } catch (err) { result.textContent = 'Нет связи с сервером. Проверьте интернет и попробуйте снова.'; result.classList.add('is-error'); }
      btn.disabled = false; btn.classList.remove('is-busy');
    });
  }

  /* ───── избранные котята ───── */
  const KEY = 'zhSaved';
  const getSaved = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } };
  const setSaved = (a) => { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} };
  function paintSaved() {
    const s = getSaved().map(String);
    $$('[data-save]').forEach((b) => { const on = s.includes(b.dataset.save); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    $$('.saved-count').forEach((c) => { c.textContent = s.length; });
  }
  doc.addEventListener('click', (e) => {
    const b = e.target.closest('[data-save]'); if (!b) return; e.preventDefault();
    const id = String(b.dataset.save); let s = getSaved().map(String); s = s.includes(id) ? s.filter((x) => x !== id) : s.concat(id); setSaved(s); paintSaved();
    const f = $('.filter.is-active'); if (f && f.dataset.filter === 'saved') applyFilter('saved');
    gsap.fromTo(b, { scale: 1.35 }, { scale: 1, duration: 0.6, ease: 'elastic.out(1,.5)' });
  });
  addEventListener('storage', paintSaved); addEventListener('pageshow', paintSaved); paintSaved();

  /* ───── фильтр котят ───── */
  function applyFilter(val) {
    const saved = getSaved().map(String); let shown = 0;
    $$('.kcard').forEach((c) => {
      const ok = val === 'all' || (val === 'saved' ? saved.includes(String(c.querySelector('[data-save]').dataset.save)) : c.dataset.breed === val);
      c.classList.toggle('is-hidden', !ok); if (ok) shown++;
    });
    $$('.filter').forEach((f) => { const on = f.dataset.filter === val; f.classList.toggle('is-active', on); f.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    const empty = $('.kempty'); if (empty) empty.hidden = shown > 0;
    const vis = $$('.kcard:not(.is-hidden)');
    if (!ZH.reduce && vis.length) gsap.fromTo(vis, { y: 26, opacity: 0 }, { y: 0, opacity: 1, duration: 0.7, stagger: 0.06, ease: 'power3.out', overwrite: true });
    ScrollTrigger.refresh();
  }
  ZH.applyFilter = applyFilter;
  doc.addEventListener('click', (e) => { const f = e.target.closest('.filter'); if (f) applyFilter(f.dataset.filter); });

  /* ───── 3D-наклон карточек ───── */
  if (fine && !ZH.reduce) {
    doc.addEventListener('pointermove', (e) => {
      const l = e.target.closest ? e.target.closest('.kcard-link') : null; if (!l) return;
      const m = $('.kcard-media', l), r = m.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      l.classList.add('is-tilting'); m.style.setProperty('--ry', (x * 12).toFixed(2) + 'deg'); m.style.setProperty('--rx', (-y * 10).toFixed(2) + 'deg');
    }, { passive: true });
    doc.addEventListener('pointerout', (e) => {
      const l = e.target.closest ? e.target.closest('.kcard-link') : null; if (!l || (e.relatedTarget && l.contains(e.relatedTarget))) return;
      const m = $('.kcard-media', l); l.classList.remove('is-tilting'); m.style.setProperty('--ry', '0deg'); m.style.setProperty('--rx', '0deg');
    });
  }

  /* ───── появление блоков и заголовков ───── */
  function splitWords(el) {
    if (el.dataset.splitDone) return; el.dataset.splitDone = '1';
    const out = doc.createDocumentFragment();
    (function walk(node, target) {
      node.childNodes.forEach((n) => {
        if (n.nodeType === 3) {
          n.textContent.split(/(\s+)/).forEach((tok) => {
            if (!tok) return; if (/^\s+$/.test(tok)) { target.append(doc.createTextNode(' ')); return; }
            const w = doc.createElement('span'); w.className = 'sw'; const i = doc.createElement('span'); i.className = 'swi'; i.textContent = tok; w.append(i); target.append(w);
          });
        } else if (n.nodeType === 1) {
          if (n.tagName === 'BR') { target.append(doc.createElement('br')); return; }
          const c = n.cloneNode(false); walk(n, c); target.append(c);
        }
      });
    })(el, out);
    el.textContent = ''; el.append(out);
  }
  ZH.splitWords = splitWords;
  function initReveals() {
    if (ZH.reduce) return;
    $$('.display-xl:not([data-no-reveal]),.display-l:not([data-no-reveal]),.display-m,.display-s').forEach((h) => {
      if (h.closest('.hero,.wslide,.gintro,.gate')) return;
      splitWords(h);
      gsap.set($$('.swi', h), { yPercent: 115 });
      ScrollTrigger.create({ trigger: h, start: 'top 90%', once: true, onEnter: () => gsap.to($$('.swi', h), { yPercent: 0, duration: 1.2, ease: 'power4.out', stagger: 0.045 }) });
    });
    const blocks = '.eyebrow:not(.hero .eyebrow),.sec-head-text,.process-map>p,.lead:not(.hero .lead),.kit-item,.step,.jcard,.faq-list details,.kcard,.pnode-card,.cta-band-actions,.footer-col,.city-list,.filters,.collection-foot,.page-hero .lead';
    $$(blocks).forEach((el) => {
      if (el.closest('.gate,.worlds,.gallery-track')) return;
      gsap.set(el, { y: 36, opacity: 0 });
      ScrollTrigger.create({ trigger: el, start: 'top 92%', once: true, onEnter: () => gsap.to(el, { y: 0, opacity: 1, duration: 1.1, ease: 'power3.out', delay: (parseInt(getComputedStyle(el).getPropertyValue('--i')) || 0) * 0.05 }) });
    });
    // подвал: блик на огромной надписи
    const mega = $('.wm-mega');
    if (mega) ScrollTrigger.create({ trigger: '.site-footer', start: 'top bottom', end: 'bottom bottom', scrub: true, onUpdate: (s) => mega.style.setProperty('--sheen', (s.progress * 100).toFixed(1) + '%') });
  }
  ZH.initReveals = initReveals;

  /* ───── лайтбокс ───── */
  let lb = null;
  function openLightbox(items, start, fromEl) {
    if (lb || !items.length) return;
    lb = doc.createElement('div'); lb.className = 'lightbox'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true'); lb.setAttribute('aria-label', 'Просмотр фотографии');
    lb.innerHTML = '<button class="lb-close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M5 5l14 14M19 5L5 19"/></svg></button><button class="lb-prev" type="button" aria-label="Назад">←</button><button class="lb-next" type="button" aria-label="Вперёд">→</button><figure class="lb-fig"><img alt=""><figcaption></figcaption></figure><div class="lb-count"></div>';
    doc.body.append(lb); ZH.stopScroll();
    let i = start || 0; const img = $('img', lb), cap = $('figcaption', lb), count = $('.lb-count', lb);
    function show(n, anim) {
      i = (n + items.length) % items.length; const it = items[i];
      img.src = it.src; img.alt = it.alt || ''; cap.textContent = it.caption || it.alt || ''; count.textContent = items.length > 1 ? (i + 1) + ' / ' + items.length : '';
      lb.classList.toggle('single', items.length < 2);
      if (anim && !ZH.reduce) gsap.fromTo(img, { opacity: 0, x: anim * 40 }, { opacity: 1, x: 0, duration: 0.6, ease: 'power3.out' });
    }
    show(i, 0);
    if (!ZH.reduce) {
      gsap.fromTo(lb, { opacity: 0 }, { opacity: 1, duration: 0.5, ease: 'power2.out' });
      if (fromEl) {
        const src = fromEl.getBoundingClientRect(), tgt = () => img.getBoundingClientRect();
        img.onload = () => { const t = tgt(); gsap.fromTo(img, { x: src.left + src.width / 2 - (t.left + t.width / 2), y: src.top + src.height / 2 - (t.top + t.height / 2), scale: Math.min(src.width / t.width, src.height / t.height) }, { x: 0, y: 0, scale: 1, duration: 0.9, ease: 'power4.out' }); img.onload = null; };
      } else gsap.fromTo(img, { scale: 0.94, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.8, ease: 'power3.out' });
    }
    function close() { if (!lb) return; const node = lb; lb = null; document.removeEventListener('keydown', key); ZH.startScroll(); if (ZH.reduce) node.remove(); else gsap.to(node, { opacity: 0, duration: 0.35, onComplete: () => node.remove() }); }
    function key(e) { if (e.key === 'Escape') close(); else if (e.key === 'ArrowRight') show(i + 1, 1); else if (e.key === 'ArrowLeft') show(i - 1, -1); }
    document.addEventListener('keydown', key);
    lb.addEventListener('click', (e) => { if (e.target.closest('.lb-close') || e.target === lb || e.target.classList.contains('lb-fig')) close(); else if (e.target.closest('.lb-next')) show(i + 1, 1); else if (e.target.closest('.lb-prev')) show(i - 1, -1); });
    let sx = 0; lb.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', (e) => { const d = e.changedTouches[0].clientX - sx; if (Math.abs(d) > 50) show(i + (d < 0 ? 1 : -1), d < 0 ? 1 : -1); });
    $('.lb-close', lb).focus();
  }
  ZH.openLightbox = openLightbox;
  // кадры горизонтальной галереи
  doc.addEventListener('click', (e) => {
    const b = e.target.closest('[data-zoom]'); if (!b) return;
    const all = $$('[data-zoom]'); const items = all.map((x) => { const im = $('img', x); const fig = x.closest('figure'); const c = fig && $('figcaption', fig); return { src: im.currentSrc || im.src, alt: im.alt, caption: c ? c.textContent.replace(/\s+/g, ' ').trim() : im.alt }; });
    openLightbox(items, all.indexOf(b), $('img', b));
  });

  /* ───── переходы между страницами: шёлк ───── */
  const silkEl = $('.silk'), silkCanvas = silkEl && $('canvas', silkEl);
  let silk = null;
  if (silkCanvas && ZH.gl && ZH.gl.supported && !ZH.reduce) { try { silk = ZH.gl.Silk(silkCanvas); } catch (e) { silk = null; } }
  if (silkEl && !silk) silkEl.classList.add('no-gl');
  function isInternal(a) {
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noSilk !== undefined) return false;
    const href = a.getAttribute('href') || ''; if (!href || href[0] === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return false;
    let u; try { u = new URL(a.href, location.href); } catch (e) { return false; }
    if (u.origin !== location.origin) return false; if (u.pathname === location.pathname && u.search === location.search) return false;
    if (/\/wp-admin|\/wp-login|wp-json|\.(pdf|zip|jpg|png|webp)$/i.test(u.pathname)) return false;
    return true;
  }
  let leaving = false;
  doc.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a'); if (!a || !isInternal(a) || leaving) return;
    if (ZH.reduce) return;
    e.preventDefault(); leaving = true;
    const k = a.matches('.kcard-link[data-kitten]') ? a : null;
    if (k && ZH.kittenTransition) { ZH.kittenTransition(k).then(() => { location.href = a.href; }); return; }
    try { sessionStorage.setItem('zhSilk', '1'); } catch (x) {}
    closeMenu();
    if (silk) { silkEl.classList.add('is-on'); silk.cover(850).then(() => { location.href = a.href; }); }
    else { silkEl && silkEl.classList.add('is-on'); setTimeout(() => { location.href = a.href; }, 520); }
    setTimeout(() => { leaving = false; }, 4000);
  });
  addEventListener('pageshow', (e) => {
    leaving = false; try { sessionStorage.removeItem('zhSilk'); } catch (x) {}
    if (e.persisted && silkEl) { silkEl.classList.remove('is-on'); html.classList.remove('zh-silk-wait'); }
  });
  function reveal() {
    let go = false; try { go = sessionStorage.getItem('zhSilk') === '1'; sessionStorage.removeItem('zhSilk'); } catch (x) {}
    if (!go) { let sx = false; try { sx = !!sessionStorage.getItem('zhSx'); } catch (x) {} if (!sx) html.classList.remove('zh-silk-wait'); return Promise.resolve(); }
    if (silk) {
      silkEl.classList.add('is-on'); silk.hold(); html.classList.remove('zh-silk-wait');
      return new Promise((res) => setTimeout(() => silk.reveal(1000).then(() => { silkEl.classList.remove('is-on'); res(); }), 120));
    }
    if (silkEl) { silkEl.classList.add('is-on'); html.classList.remove('zh-silk-wait'); return new Promise((res) => setTimeout(() => { silkEl.classList.remove('is-on'); res(); }, 100)); }
    html.classList.remove('zh-silk-wait'); return Promise.resolve();
  }
  ZH.revealPage = reveal;

  /* ───── запуск ───── */
  function boot() {
    initReveals();
    ZH._ready = true; ZH.ready.splice(0).forEach((fn) => { try { fn(); } catch (e) { console.error(e); } });
    reveal().then(() => { html.classList.add('is-ready'); ZH.ready2 && ZH.ready2.splice(0).forEach((fn) => fn()); });
    ZH.pageRevealed = true;
  }
  ZH.afterReveal = (fn) => { if (html.classList.contains('is-ready')) fn(); else (ZH.ready2 = ZH.ready2 || []).push(fn); };
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot); else boot();
  addEventListener('load', () => { ScrollTrigger.refresh(); html.classList.remove('zh-silk-wait'); });
  doc.fonts && doc.fonts.ready.then(() => ScrollTrigger.refresh());
})();
