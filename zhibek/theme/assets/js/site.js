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

    const fail = (field, text) => { const wrap = field.closest('.field'); if (wrap) wrap.classList.add('has-error'); field.setAttribute('aria-invalid', 'true'); field.setAttribute('aria-describedby', 'form-result'); result.textContent = text; result.className = 'form-result is-error'; field.focus({ preventScroll: true }); field.scrollIntoView({ block: 'nearest' }); };
    form.addEventListener('input', (e) => { const w = e.target.closest('.field'); if (w) w.classList.remove('has-error'); e.target.removeAttribute('aria-invalid'); });
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      $$('.has-error', form).forEach((n) => n.classList.remove('has-error')); $$('[aria-invalid]', form).forEach((n) => n.removeAttribute('aria-invalid'));
      const name = form.elements.name, contact = form.elements.contact, city = form.elements.city, consent = form.elements.consent;
      if (name.value.trim().length < 2) return fail(name, 'Укажите имя.');
      const cv = contact.value.trim();
      const looksEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cv);
      const digits = cv.replace(/\D/g, '');
      if (!looksEmail && !(digits.length >= 7 && digits.length <= 15)) return fail(contact, 'Укажите телефон с кодом страны или email.');
      if (city.value.trim().length < 2) return fail(city, 'Укажите город.');
      if (!consent.checked) { result.textContent = 'Подтвердите согласие на обработку данных.'; result.className = 'form-result is-error'; consent.setAttribute('aria-invalid', 'true'); consent.focus({ preventScroll: true }); consent.scrollIntoView({ block: 'nearest' }); return; }
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

  /* ---------- документы: просмотр скана ---------- */
  const docsEl = $('[data-docs]');
  if (docsEl) {
    let list = [];
    try { list = JSON.parse(docsEl.dataset.items || '[]'); } catch (err) { list = []; }
    let box = null, cur = 0;
    const paint = () => {
      const it = list[cur]; if (!it) return;
      const im = $('img', box); im.src = it.src; im.alt = it.alt || it.title;
      $('.lb-cap', box).innerHTML = '';
      const b = document.createElement('b'); b.textContent = it.title; $('.lb-cap', box).appendChild(b);
      if (it.text) { const s = document.createElement('span'); s.textContent = it.text; $('.lb-cap', box).appendChild(s); }
    };
    const go = (d) => { cur = (cur + d + list.length) % list.length; paint(); };
    docsEl.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-doc]'); if (!btn || typeof HTMLDialogElement === 'undefined') return;
      cur = Number(btn.dataset.doc) || 0;
      if (!box) {
        box = document.createElement('dialog'); box.className = 'lightbox is-doc'; box.setAttribute('aria-label', 'Документ');
        box.innerHTML = '<button class="lb-close" type="button" aria-label="Закрыть">×</button><img alt=""><div class="lb-cap" aria-live="polite"></div><button class="lb-nav lb-prev" type="button" aria-label="Предыдущий документ">‹</button><button class="lb-nav lb-next" type="button" aria-label="Следующий документ">›</button>';
        document.body.appendChild(box);
        box.addEventListener('click', (ev) => { if (ev.target === box || ev.target.closest('.lb-close')) box.close(); if (ev.target.closest('.lb-prev')) go(-1); if (ev.target.closest('.lb-next')) go(1); });
        box.addEventListener('keydown', (ev) => { if (ev.key === 'ArrowLeft') go(-1); if (ev.key === 'ArrowRight') go(1); });
        box.addEventListener('close', () => { doc.classList.remove('dialog-open'); const back = $('[data-doc="' + cur + '"]', docsEl); if (back) back.focus({ preventScroll: true }); });
      }
      $$('.lb-nav', box).forEach((n) => { n.hidden = list.length < 2; });
      paint(); box.showModal(); doc.classList.add('dialog-open');
    });
  }

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
    const items = $$('[data-city]', box), links = [];
    // Карта: клик или касание в любом месте страны ставит метку «Довезём и сюда».
    const svg = $('.kzmap', box), outline = $('.kz-line', box), drop = $('.kz-drop', box), dropLabel = $('.kz-drop-label', box), hint = $('.map-hint', box);
    if (svg && outline && drop && typeof outline.isPointInFill === 'function' && svg.getScreenCTM) {
      const vb = svg.viewBox.baseVal;
      const toSvg = (e) => { const pt = svg.createSVGPoint(); pt.x = e.clientX; pt.y = e.clientY; return pt.matrixTransform(svg.getScreenCTM().inverse()); };
      svg.addEventListener('pointermove', (e) => { if (e.pointerType === 'mouse') svg.classList.toggle('is-over', outline.isPointInFill(toSvg(e))); });
      svg.addEventListener('pointerleave', () => svg.classList.remove('is-over'));
      svg.addEventListener('click', (e) => {
        if (e.target.closest('.kz-city')) return;
        const pt = toSvg(e); if (!outline.isPointInFill(pt)) return;
        drop.setAttribute('transform', 'translate(' + pt.x.toFixed(1) + ' ' + pt.y.toFixed(1) + ')');
        drop.classList.remove('is-on'); void drop.getBoundingClientRect(); drop.classList.add('is-on');
        if (dropLabel) { dropLabel.style.left = (pt.x / vb.width * 100).toFixed(2) + '%'; dropLabel.style.top = (pt.y / vb.height * 100).toFixed(2) + '%'; dropLabel.classList.add('is-on'); }
        if (hint) hint.classList.add('is-off');
      });
    }
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

  // Герой: переключатель пород. Все три слайда уже в разметке, меняются цвет сцены и видимый слайд.
  const heroTabs = $('[data-hero-tabs]'), heroStage = $('[data-hero-stage]');
  if (heroTabs && heroStage) {
    const tabs = $$('.hero-tab', heroTabs), slides = $$('.hs-slide', heroStage);
    const pick = (i) => {
      tabs.forEach((t, k) => { const on = k === i; t.classList.toggle('is-on', on); t.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      slides.forEach((sl, k) => { const on = k === i; sl.classList.toggle('is-on', on); sl.inert = !on; });
      heroStage.style.setProperty('--bg', slides[i].dataset.color);
    };
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => pick(i));
      t.addEventListener('mouseenter', () => { if (window.matchMedia('(hover: hover) and (min-width: 960px)').matches) pick(i); });
    });
  }

  // Подбор породы: человек отмечает, что для него важно, и видит ближайшую по оценкам породу.
  $$('[data-picker]').forEach((box) => {
    let breeds = []; try { breeds = JSON.parse(box.dataset.breeds || '[]'); } catch (e) { return; }
    if (!breeds.length) return;
    const rows = $$('.prow', box), state = {};
    const empty = $('.pr-empty', box), card = $('.pr-card', box), bars = $('.pr-bars', box), reset = $('.picker-reset', box);
    const word = box.dataset.match || 'совпадение';
    const render = () => {
      const chosen = Object.keys(state);
      rows.forEach((row) => {
        const v = state[row.dataset.label] || 0;
        $$('button', row).forEach((b) => { const n = Number(b.dataset.v); b.classList.toggle('is-fill', n <= v); b.setAttribute('aria-checked', n === v ? 'true' : 'false'); });
      });
      reset.hidden = !chosen.length;
      if (!chosen.length) { empty.hidden = false; card.hidden = true; bars.hidden = true; return; }
      const res = breeds.map((b) => {
        const dist = chosen.reduce((sum, l) => sum + Math.abs((b.scores[l] || 3) - state[l]), 0);
        return { b, pct: Math.round(100 * (1 - dist / (4 * chosen.length))) };
      }).sort((x, y) => y.pct - x.pct);
      const best = res[0].b, act = state['Активность'];
      const pose = act ? (act <= 2 ? 'lie' : act >= 4 ? 'stretch' : 'sit') : 'sit';
      empty.hidden = true; card.hidden = false; bars.hidden = false;
      card.style.setProperty('--bg', best.color); card.href = best.url;
      $('.pr-name', card).textContent = best.name;
      $('.pr-fit', card).textContent = best.fit ? '«' + best.fit + '»' : '';
      const img = $('.pr-cat', card); img.src = best.img[pose] || best.img.sit; img.alt = best.name;
      bars.innerHTML = '';
      res.forEach((r) => {
        const li = document.createElement('li'); li.style.setProperty('--bc', r.b.color);
        li.innerHTML = '<span></span><span class="bar"><span></span></span><b></b>';
        li.firstChild.textContent = r.b.name; li.lastChild.textContent = r.pct + '%';
        li.querySelector('.bar span').style.width = r.pct + '%'; li.setAttribute('title', r.pct + '% ' + word);
        bars.appendChild(li);
      });
    };
    // Клавиатура: одна точка остановки на строку, стрелки меняют значение.
    const syncTab = (row) => { const v = state[row.dataset.label] || 0; $$('button', row).forEach((b, i) => { b.tabIndex = (v ? Number(b.dataset.v) === v : i === 0) ? 0 : -1; }); };
    rows.forEach((row) => {
      syncTab(row);
      row.addEventListener('keydown', (e) => {
        const k = e.key === 'ArrowRight' || e.key === 'ArrowUp' ? 1 : e.key === 'ArrowLeft' || e.key === 'ArrowDown' ? -1 : 0;
        if (!k) return;
        e.preventDefault();
        const cur = state[row.dataset.label] || (k > 0 ? 0 : 6), next = Math.max(1, Math.min(5, cur + k));
        state[row.dataset.label] = next; render(); syncTab(row);
        const nb = $$('button', row)[next - 1]; if (nb) nb.focus();
      });
    });
    rows.forEach((row) => {
      $$('button', row).forEach((b) => b.addEventListener('click', () => {
        const l = row.dataset.label, v = Number(b.dataset.v);
        if (state[l] === v) delete state[l]; else state[l] = v;
        render(); syncTab(row);
      }));
    });
    reset.addEventListener('click', () => { Object.keys(state).forEach((k) => delete state[k]); render(); rows.forEach(syncTab); });
    render();
  });

  // Как забрать: поза кота и цвет сцены меняются вместе с шагом (при прокрутке, наведении и фокусе).
  $$('[data-proc]').forEach((box) => {
    const stage = $('.proc-stage', box), slides = $$('.ps-slide', box), steps = $$('.pstep', box);
    if (!stage || !steps.length) return;
    const set = (i) => {
      steps.forEach((st, k) => st.classList.toggle('is-on', k === i));
      slides.forEach((sl, k) => sl.classList.toggle('is-on', k === i));
      if (slides[i]) stage.style.setProperty('--bg', slides[i].dataset.color);
    };
    set(0);
    steps.forEach((st, i) => { st.addEventListener('mouseenter', () => { if (window.matchMedia('(hover: hover)').matches) set(i); }); st.addEventListener('click', () => set(i)); });
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((en) => { en.forEach((e) => { if (e.isIntersecting) set(Number(e.target.dataset.i)); }); }, { rootMargin: '-42% 0px -48% 0px' });
      steps.forEach((st) => io.observe(st));
    }
  });

  doc.classList.add('is-ready');
})();
