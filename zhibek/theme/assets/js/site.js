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
    if (!sticky || !canvas || !n || !canvas.getContext || sceneReduce || saveData) {
      scene.classList.add('is-end');
      const last = $$('.scene-note', scene); if (last.length) last[last.length - 1].classList.add('is-on');
      $$('.scene-steps li', scene).forEach((el) => el.classList.add('is-on'));
      return;
    }
    const ctx = canvas.getContext('2d');
    const frames = new Array(n + 1);
    let target = 1, shown = 0, started = false, raf = 0, near = false;
    // Подписи и полоски шагов меняются вместе с позой кота: границы поз заданы номерами кадров.
    const breaks = (scene.dataset.breaks || '').split(',').map(Number).filter(Boolean);
    const notes = $$('.scene-note', scene), bars = $$('.scene-steps li', scene);
    const setStep = (step) => { notes.forEach((el, i) => el.classList.toggle('is-on', i === step - 1)); bars.forEach((el, i) => el.classList.toggle('is-on', i < step)); };
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
      const pin = parseFloat(getComputedStyle(sticky).top) || 0; // панель закрепляется на этом расстоянии от верха окна
      const p = Math.max(0, Math.min(1, (pin - r.top) / span));
      sticky.style.setProperty('--p', p.toFixed(3));
      scene.classList.toggle('is-end', p > 0.6);
      target = Math.max(1, Math.min(n, 1 + Math.round(p * (n - 1))));
      if (notes.length) setStep(1 + breaks.filter((b) => target >= b).length);
      if (!raf) raf = requestAnimationFrame(draw);
    };
    let ticking = false;
    const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(() => { ticking = false; if (near) update(); }); } };
    new IntersectionObserver((en) => { near = en[0].isIntersecting; if (near) { load(); update(); } }, { rootMargin: '120% 0px' }).observe(scene);
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
  });

  // Содержание страницы и статьи: строится из заголовков h2.
  $$('[data-toc]').forEach((toc) => {
    const prose = $('.prose', toc.parentElement), list = $('.toc-list', toc);
    const heads = prose ? $$('h2', prose) : [];
    if (heads.length < 3 || !list) return;
    heads.forEach((h, i) => {
      if (!h.id) h.id = 'part-' + (i + 1);
      const li = document.createElement('li'), a = document.createElement('a');
      a.href = '#' + h.id; a.textContent = h.textContent; li.appendChild(a); list.appendChild(li);
    });
    toc.hidden = false;
    if (!('IntersectionObserver' in window)) return;
    const links = $$('a', list);
    const io = new IntersectionObserver((en) => {
      en.forEach((e) => { if (e.isIntersecting) links.forEach((l) => l.classList.toggle('is-active', l.getAttribute('href') === '#' + e.target.id)); });
    }, { rootMargin: '-15% 0px -75% 0px' });
    heads.forEach((h) => io.observe(h));
  });

  // Карта доставки: город на карте, его связи и кнопка города подсвечиваются вместе, по нажатию выбор закрепляется.
  $$('[data-map]').forEach((box) => {
    const items = $$('[data-city]', box), links = $$('.kz-link', box);
    let pinned = '';
    const show = (name) => {
      items.forEach((el) => el.classList.toggle('is-hot', !!name && el.dataset.city === name));
      links.forEach((el) => el.classList.toggle('is-hot', !!name && (el.dataset.a === name || el.dataset.b === name)));
    };
    items.filter((el) => el.matches('button, .kz-city')).forEach((el) => {
      const name = el.dataset.city;
      el.addEventListener('mouseenter', () => show(name));
      el.addEventListener('mouseleave', () => show(pinned));
      el.addEventListener('focus', () => show(name));
      el.addEventListener('blur', () => show(pinned));
      el.addEventListener('click', () => { pinned = pinned === name ? '' : name; show(pinned); });
    });
  });

  // Карточки характеров: переворачиваются при наведении мыши, по кнопке и по касанию; скрытая сторона недоступна для фокуса.
  const canHover = window.matchMedia('(hover: hover)').matches;
  $$('[data-char]').forEach((card) => {
    const btn = $('.char-flip', card), front = $('.char-front', card), back = $('.char-back', card), name = btn ? btn.dataset.label : '';
    if (!btn || !front || !back) return;
    let pinned = false; // карточка «закреплена» кнопкой или касанием и не возвращается, когда мышь уходит
    const set = (on, pin) => {
      pinned = on && !!pin;
      card.classList.toggle('is-flipped', on); btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('aria-label', (on ? 'Вернуться к оценкам: ' : 'Подробнее: ') + name);
      front.inert = on; back.inert = !on;
    };
    set(false);
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const on = card.classList.contains('is-flipped');
      if (on && !pinned && canHover && e.detail > 0) { pinned = true; return; } // мышь: карточка уже перевёрнута наведением, клик закрепляет её
      set(!on, !on);
    });
    if (canHover) {
      card.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' && !card.classList.contains('is-flipped')) set(true); });
      card.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse' && !pinned && !card.contains(document.activeElement)) set(false); });
    } else {
      card.addEventListener('click', (e) => { if (!e.target.closest('a, button')) set(!card.classList.contains('is-flipped'), true); });
    }
    card.addEventListener('keydown', (e) => { if (e.key === 'Escape' && card.classList.contains('is-flipped')) { set(false); btn.focus(); } });
  });

  doc.classList.add('is-ready');
})();
