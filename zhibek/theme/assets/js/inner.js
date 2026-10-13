/* ZHIBEK · inner.js — внутренние страницы: галерея, паспорт, переход карточки котёнка, фильтр по ссылке */
(function () {
  'use strict';
  const ZH = window.ZH; if (!ZH || !window.gsap) return;
  const { $, $$ } = ZH; const gsap = window.gsap, ScrollTrigger = window.ScrollTrigger;
  const html = document.documentElement;

  /* ───── переход «карточка котёнка → страница котёнка» ───── */
  ZH.kittenTransition = function (link) {
    return new Promise((resolve) => {
      const img = $('.kcard-img', link), media = $('.kcard-media', link);
      if (!img || ZH.reduce) { resolve(); return; }
      const r = img.getBoundingClientRect(), mr = media.getBoundingClientRect();
      try { sessionStorage.setItem('zhSx', JSON.stringify({ src: img.currentSrc || img.src, r: [r.left, r.top, r.width, r.height], m: [mr.left, mr.top, mr.width, mr.height], bg: getComputedStyle(media).backgroundImage })); } catch (e) {}
      const veil = document.createElement('div'); veil.className = 'sx-veil';
      const card = document.createElement('div'); card.className = 'sx-card'; Object.assign(card.style, { left: mr.left + 'px', top: mr.top + 'px', width: mr.width + 'px', height: mr.height + 'px', backgroundImage: getComputedStyle(media).backgroundImage, borderRadius: getComputedStyle(media).borderRadius });
      const im = document.createElement('img'); im.src = img.currentSrc || img.src; im.alt = ''; Object.assign(im.style, { left: r.left - mr.left + 'px', top: r.top - mr.top + 'px', width: r.width + 'px', height: r.height + 'px' });
      card.append(im); document.body.append(veil, card);
      gsap.fromTo(veil, { opacity: 0 }, { opacity: 1, duration: 0.55, ease: 'power2.inOut' });
      gsap.to(card, { left: 0, top: 0, width: innerWidth, height: innerHeight, borderRadius: 0, duration: 0.7, ease: 'power3.inOut', delay: 0.05 });
      gsap.to(im, { opacity: 0, duration: 0.3, delay: 0.45 });
      setTimeout(resolve, 760);
    });
  };
  // прибытие: «карточка» раскрыта на весь экран и сворачивается к главному фото страницы
  (function arrive() {
    let data = null; try { data = JSON.parse(sessionStorage.getItem('zhSx') || 'null'); sessionStorage.removeItem('zhSx'); } catch (e) {}
    const target = $('.zg-main'); html.classList.remove('zh-silk-wait');
    if (!data || !target || ZH.reduce) return;
    const card = document.createElement('div'); card.className = 'sx-card sx-arrive'; Object.assign(card.style, { left: '0px', top: '0px', width: innerWidth + 'px', height: innerHeight + 'px', backgroundImage: data.bg, borderRadius: '0px' });
    document.body.append(card);
    gsap.set(target, { opacity: 0 });
    const go = () => {
      const t = target.getBoundingClientRect();
      gsap.to(card, { left: t.left, top: t.top, width: t.width, height: t.height, borderRadius: getComputedStyle(target).borderRadius, duration: 1.0, ease: 'power3.inOut', onComplete: () => { gsap.set(target, { opacity: 1 }); card.remove(); } });
    };
    requestAnimationFrame(() => requestAnimationFrame(go));
    setTimeout(() => { gsap.set(target, { opacity: 1 }); card && card.remove(); }, 2500);
  })();

  /* ───── галерея на странице котёнка/породы ───── */
  $$('[data-gallery]').forEach((g) => {
    let items = []; try { items = JSON.parse($('.zg-data', g).textContent); } catch (e) {}
    const main = $('.zg-main', g), img = $('img', main), thumbs = $$('[data-zg-thumb]', g); let cur = 0;
    function show(i) {
      cur = i; const it = items[i]; if (!it) return;
      thumbs.forEach((t, k) => t.setAttribute('aria-pressed', k === i ? 'true' : 'false'));
      main.dataset.type = it.cutout ? 'cutout' : 'photo';
      gsap.to(img, { opacity: 0, duration: 0.2, onComplete: () => { img.src = it.preview; img.alt = it.alt || ''; img.onload = () => gsap.to(img, { opacity: 1, duration: 0.45 }); } });
      main.href = it.src;
    }
    thumbs.forEach((t, k) => t.addEventListener('click', () => show(k)));
    main.addEventListener('click', (e) => { e.preventDefault(); ZH.openLightbox(items.map((x) => ({ src: x.src, alt: x.alt, caption: x.caption })), cur, img); });
  });

  /* ───── паспорт ───── */
  const pp = $('[data-passport]');
  if (pp) {
    const book = $('[data-passport-book]', pp), btn = $('[data-passport-toggle]', pp);
    let open = false;
    const set = (v, dur) => { open = v; btn.setAttribute('aria-expanded', v ? 'true' : 'false'); $('span', btn).textContent = v ? 'Закрыть паспорт' : 'Открыть паспорт'; if (ZH.reduce) gsap.set(book, { '--pp': v ? 1 : 0 }); else gsap.to(book, { '--pp': v ? 1 : 0, duration: dur || 1.5, ease: 'power3.inOut' }); };
    btn.addEventListener('click', () => set(!open));
    book.addEventListener('click', () => set(!open));
    ScrollTrigger.create({ trigger: pp, start: 'top 55%', once: true, onEnter: () => setTimeout(() => set(true, 1.8), 350) });
  }

  /* ───── фильтр по ссылке ?breed=slug ───── */
  const slug = new URLSearchParams(location.search).get('breed');
  if (slug) { const f = $('.filter[data-slug="' + slug.replace(/[^a-z0-9_-]/gi, '') + '"]'); if (f) ZH.onReady(() => ZH.applyFilter(f.dataset.filter)); }

  /* ───── параллакс котика на страницах пород ───── */
  if (ZH.fine && !ZH.reduce) {
    $$('.bhero-cat img, .bpanel-cat img').forEach((el) => {
      const qx = gsap.quickTo(el, 'x', { duration: 1.2, ease: 'power3' });
      addEventListener('pointermove', (e) => qx((e.clientX / innerWidth - 0.5) * -22), { passive: true });
    });
  }
  $$('.page-hero .display-l, .bhero .display-xl, .khero .display-xl, .notfound .display-xl').forEach((h) => {
    if (ZH.reduce) return; ZH.splitWords(h); const w = $$('.swi', h); gsap.set(w, { yPercent: 115 });
    ZH.afterReveal(() => gsap.to(w, { yPercent: 0, duration: 1.3, ease: 'power4.out', stagger: 0.07, delay: 0.1 }));
  });
})();
