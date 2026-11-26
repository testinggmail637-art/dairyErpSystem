/* =========================================================
   Demo products page — filter + scroll animation.
   Runs after script.js (uses its hasGSAP / reduce globals).
   ========================================================= */
(function productsPage() {
  const rows = [...document.querySelectorAll('.pp-row')];
  const bar = document.querySelector('[data-pp-filter]');
  const count = document.querySelector('[data-pp-count]');
  const anim = typeof hasGSAP !== 'undefined' && hasGSAP && !reduce;

  /* ---------- filter chips ---------- */
  if (bar) bar.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-cat]');
    if (!chip) return;
    const cat = chip.dataset.cat;
    bar.querySelectorAll('[data-cat]').forEach((c) => {
      c.classList.toggle('is-on', c === chip);
      c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
    });
    let shown = 0;
    rows.forEach((row) => {
      const show = cat === 'all' || row.dataset.cat === cat;
      row.classList.toggle('is-hidden', !show);
      row.classList.remove('is-entering');
      if (show) { shown++; void row.offsetWidth; row.classList.add('is-entering'); }
    });
    if (count) count.textContent = shown;
    // jump back to the top of the list so the result is in view, then re-measure scroll animations
    const list = document.getElementById('products');
    if (list && list.getBoundingClientRect().top < 0) list.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
    if (anim) requestAnimationFrame(() => ScrollTrigger.refresh());
  });

  /* ---------- side navigator: show while the list is on screen, mark the product in view ---------- */
  const dots = document.querySelector('[data-pp-dots]');
  if (dots && 'IntersectionObserver' in window) {
    const links = [...dots.querySelectorAll('a')];
    new IntersectionObserver(([en]) => dots.classList.toggle('is-on', en.isIntersecting), { rootMargin: '-30% 0px -30% 0px' })
      .observe(document.getElementById('products'));
    const io = new IntersectionObserver((entries) => entries.forEach((en) => {
      if (en.isIntersecting) links.forEach((l) => l.classList.toggle('active', l.dataset.target === en.target.id));
    }), { rootMargin: '-45% 0px -50% 0px' });
    rows.forEach((r) => io.observe(r));
    if (bar) bar.addEventListener('click', () => links.forEach((l) => {
      l.classList.toggle('is-off', document.getElementById(l.dataset.target).classList.contains('is-hidden'));
    }));
  }

  /* ---------- photos tilt gently toward the cursor ---------- */
  if (typeof finePointer !== 'undefined' && finePointer && !reduce) {
    document.querySelectorAll('.pp-media').forEach((m) => {
      m.addEventListener('mousemove', (e) => {
        const r = m.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        m.style.transform = `perspective(1100px) rotateY(${x * 6}deg) rotateX(${-y * 6}deg)`;
      });
      m.addEventListener('mouseleave', () => (m.style.transform = ''));
    });
  }

  if (!anim) return;

  /* ---------- hero: words rise, Hindi line + notice follow ---------- */
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .from('.pp-title .word', { yPercent: 110, duration: 1.3, stagger: 0.1 })
    .from('.pp-meta > *', { y: 20, opacity: 0, duration: 0.8, stagger: 0.08 }, '-=1')
    .from('.pp-hi, .pp-lead, .pp-notice', { y: 30, opacity: 0, duration: 1, stagger: 0.1 }, '-=0.9');
  gsap.to('.pp-ghost', { yPercent: -30, ease: 'none', scrollTrigger: { trigger: '.pp-hero', start: 'top top', end: 'bottom top', scrub: true } });

  /* ---------- rows: photo opens from below and settles, text slides in from its side ---------- */
  rows.forEach((row) => {
    const media = row.querySelector('.pp-media');
    const img = media.querySelector('img');
    const fromRight = row.classList.contains('pp-flip') ? -60 : 60;
    gsap.fromTo(media, { clipPath: 'inset(100% 0 0 0 round 28px)' }, { clipPath: 'inset(0% 0 0 0 round 28px)', duration: 1.3, ease: 'expo.inOut',
      scrollTrigger: { trigger: row, start: 'top 80%' } });
    gsap.fromTo(img, { scale: 1.25 }, { scale: 1, ease: 'none', scrollTrigger: { trigger: row, start: 'top bottom', end: 'bottom top', scrub: true } });
    gsap.from(row.querySelectorAll('.pp-info > *'), { x: fromRight, opacity: 0, duration: 1, stagger: 0.08, ease: 'expo.out',
      scrollTrigger: { trigger: row, start: 'top 75%' } });
    gsap.from(row.querySelectorAll('.pp-points li'), { x: 20, opacity: 0, duration: 0.6, stagger: 0.1, ease: 'power2.out',
      scrollTrigger: { trigger: row, start: 'top 60%' } });
    gsap.fromTo(row.querySelector('.pp-num'), { yPercent: 40 }, { yPercent: -20, ease: 'none', scrollTrigger: { trigger: row, start: 'top bottom', end: 'bottom top', scrub: true } });
  });

  /* ---------- stamps: cards rise, then "नहीं" slams on ---------- */
  gsap.from('.pp-stamps li', { y: 60, opacity: 0, duration: 1, stagger: 0.12, ease: 'expo.out', scrollTrigger: { trigger: '.pp-stamps', start: 'top 80%' } });
  gsap.from('.pp-no', { scale: 2.4, opacity: 0, rotate: -25, duration: 0.5, stagger: 0.12, delay: 0.35, ease: 'back.out(2.2)',
    scrollTrigger: { trigger: '.pp-stamps', start: 'top 80%' } });
})();
