/* ZHIBEK · inner.js — внутренние страницы: галерея, паспорт, переход карточки котёнка, фильтр по ссылке */
(function () {
  'use strict';
  const ZH = window.ZH; if (!ZH || !window.gsap) return;
  const { $, $$ } = ZH; const gsap = window.gsap, ScrollTrigger = window.ScrollTrigger;
  const html = document.documentElement;

  /* ───── переход «карточка котёнка → страница котёнка» ─────
     Карточка остаётся на месте, остальной экран темнеет; на новой странице та же карточка перелетает в главное фото. */
  ZH.kittenTransition = function (link) {
    return new Promise((resolve) => {
      const media = $('.kcard-media', link);
      if (!media || ZH.reduce) { resolve(); return; }
      const mr = media.getBoundingClientRect();
      const clone = media.cloneNode(true);
      clone.classList.add('sx-card'); clone.style.cssText = 'left:' + mr.left + 'px;top:' + mr.top + 'px;width:' + mr.width + 'px;height:' + mr.height + 'px;';
      try { sessionStorage.setItem('zhSx', JSON.stringify({ r: [mr.left, mr.top, mr.width, mr.height], html: clone.innerHTML, bg: getComputedStyle(media).backgroundImage, type: media.dataset.type || 'cutout' })); } catch (e) {}
      const veil = document.createElement('div'); veil.className = 'sx-veil'; document.body.append(veil, clone);
      gsap.fromTo(veil, { opacity: 0 }, { opacity: 1, duration: 0.55, ease: 'power2.inOut' });
      gsap.to(clone, { scale: 1.035, duration: 0.6, ease: 'power2.out' });
      setTimeout(resolve, 640);
    });
  };
  (function arrive() {
    let data = null; try { data = JSON.parse(sessionStorage.getItem('zhSx') || 'null'); sessionStorage.removeItem('zhSx'); } catch (e) {}
    const target = $('.zg-main'); html.classList.remove('zh-silk-wait');
    if (!data || !target || ZH.reduce) return;
    const veil = document.createElement('div'); veil.className = 'sx-veil';
    const card = document.createElement('div'); card.className = 'sx-card kcard-media'; card.dataset.type = data.type; card.innerHTML = data.html;
    card.style.cssText = 'left:' + data.r[0] + 'px;top:' + data.r[1] + 'px;width:' + data.r[2] + 'px;height:' + data.r[3] + 'px;background-image:' + data.bg + ';';
    document.body.append(veil, card);
    gsap.set(target, { opacity: 0 });
    let done = false; const finish = () => { if (done) return; done = true; gsap.set(target, { opacity: 1 }); card.remove(); veil.remove(); };
    const go = () => {
      const t = target.getBoundingClientRect(), br = getComputedStyle(target).borderRadius;
      gsap.to(veil, { opacity: 0, duration: 0.9, ease: 'power2.inOut', delay: 0.15 });
      gsap.to(card, { left: t.left, top: t.top, width: t.width, height: t.height, borderRadius: br, duration: 1.05, ease: 'power3.inOut', onComplete: finish });
    };
    ZH.afterReveal(() => requestAnimationFrame(() => requestAnimationFrame(go)));
    setTimeout(finish, 3200);
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
