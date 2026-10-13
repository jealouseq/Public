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

  /* ---------- фильтр котят ---------- */
  const filters = $$('.filter');
  if (filters.length) {
    const cards = $$('.kcard');
    const empty = $('.k-empty[data-filter-empty]');
    const apply = (key, pushUrl) => {
      let shown = 0;
      cards.forEach((c) => {
        const ok = key === 'all' || c.dataset.breed === key;
        c.classList.toggle('is-hidden', !ok);
        if (ok) { shown++; c.style.animation = 'none'; void c.offsetWidth; c.style.animation = `rise .6s var(--ease) ${Math.min(shown, 8) * 40}ms both`; }
      });
      filters.forEach((f) => { const on = f.dataset.filter === key; f.classList.toggle('is-active', on); f.setAttribute('aria-pressed', String(on)); });
      if (empty) empty.hidden = shown > 0;
      if (pushUrl && window.history && history.replaceState) {
        const f = filters.find((x) => x.dataset.filter === key);
        const u = new URL(window.location.href);
        if (key === 'all' || !f || !f.dataset.slug) u.searchParams.delete('breed'); else u.searchParams.set('breed', f.dataset.slug);
        history.replaceState(null, '', u);
      }
    };
    filters.forEach((f) => f.addEventListener('click', () => apply(f.dataset.filter, true)));
    const want = new URL(window.location.href).searchParams.get('breed');
    const pre = want && filters.find((f) => f.dataset.slug === want);
    if (pre) apply(pre.dataset.filter, false);
  }

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

  doc.classList.add('is-ready');
})();
