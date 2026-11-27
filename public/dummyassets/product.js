/* =========================================================
   Demo product detail — gallery, picker → WhatsApp text, journey line.
   Runs after script.js (uses its hasGSAP / reduce / finePointer globals).
   ========================================================= */
(function productPage() {
  const anim = typeof hasGSAP !== 'undefined' && hasGSAP && !reduce;

  /* ---------- gallery: thumbs, swipe, zoom under the cursor ---------- */
  const stage = document.querySelector('[data-pd-stage]');
  const thumbs = [...document.querySelectorAll('[data-pd-thumb]')];
  if (stage && thumbs.length) {
    const photos = [...stage.querySelectorAll('img')];
    let at = 0;
    const show = (n) => {
      at = (n + photos.length) % photos.length;
      photos.forEach((p, k) => p.classList.toggle('is-on', k === at));
      thumbs.forEach((t, k) => { t.classList.toggle('is-on', k === at); t.setAttribute('aria-pressed', k === at ? 'true' : 'false'); });
    };
    thumbs.forEach((t) => t.addEventListener('click', () => show(+t.dataset.pdThumb)));

    let x0 = null;
    stage.addEventListener('touchstart', (e) => (x0 = e.touches[0].clientX), { passive: true });
    stage.addEventListener('touchend', (e) => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) show(at + (dx < 0 ? 1 : -1));
      x0 = null;
    });

    if (typeof finePointer !== 'undefined' && finePointer && !reduce) {
      stage.addEventListener('mousemove', (e) => {
        const r = stage.getBoundingClientRect();
        photos[at].style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
      });
      stage.addEventListener('mouseenter', () => stage.classList.add('is-zoom'));
      stage.addEventListener('mouseleave', () => stage.classList.remove('is-zoom'));
    }
  }

  /* ---------- picker: size × qty · plan → summary + WhatsApp link ---------- */
  const form = document.querySelector('[data-pd-pick]');
  if (form) {
    const qty = form.querySelector('[name="qty"]');
    const summary = form.querySelector('[data-pd-summary]');
    const wa = form.querySelector('[data-pd-wa]');
    const base = wa.href.split('?')[0];
    const update = () => {
      const n = Math.min(10, Math.max(1, parseInt(qty.value, 10) || 1));
      qty.value = n;
      const size = form.querySelector('[name="size"]:checked').value;
      const plan = form.querySelector('[name="plan"]:checked').value;
      summary.textContent = `${size} × ${n} · ${plan}`;
      summary.classList.remove('is-bump'); void summary.offsetWidth; summary.classList.add('is-bump');
      const text = form.dataset.template.replace(':name', form.dataset.name).replace(':size', size).replace(':qty', n).replace(':plan', plan);
      wa.href = `${base}?text=${encodeURIComponent(text)}`;
    };
    form.querySelectorAll('[data-step]').forEach((b) => b.addEventListener('click', () => {
      qty.value = (parseInt(qty.value, 10) || 1) + +b.dataset.step;
      update();
    }));
    form.addEventListener('change', update);
  }

  /* ---------- journey: line fills with scroll, dots light up as it passes them ---------- */
  const journey = document.querySelector('[data-pd-journey]');
  if (journey) {
    const fill = journey.querySelector('[data-pd-fill]');
    const steps = [...journey.querySelectorAll('.pd-step')];
    const vertical = () => window.matchMedia('(max-width: 991.98px)').matches;
    let ticking = false;
    const paint = () => {
      ticking = false;
      const r = journey.getBoundingClientRect();
      const h = window.innerHeight;
      const p = reduce ? 1 : Math.min(1, Math.max(0, (h * 0.8 - r.top) / (r.height * 0.7)));
      fill.style.transform = vertical() ? `scaleY(${p})` : `scaleX(${p})`;
      steps.forEach((s, k) => s.classList.toggle('is-lit', p >= k / Math.max(1, steps.length - 1) - 0.02));
    };
    const ask = () => { if (!ticking) { ticking = true; requestAnimationFrame(paint); } };
    window.addEventListener('scroll', ask, { passive: true });
    window.addEventListener('resize', ask);
    paint();
  }

  if (!anim) return;

  /* ---------- entrance + reveals ---------- */
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .fromTo('.pd-stage', { clipPath: 'inset(100% 0 0 0 round 28px)' }, { clipPath: 'inset(0% 0 0 0 round 28px)', duration: 1.3, ease: 'expo.inOut' })
    .from('.pd-info > *', { x: 50, opacity: 0, duration: 1, stagger: 0.08 }, '-=0.9')
    .from('.pd-thumb', { y: 20, opacity: 0, duration: 0.6, stagger: 0.08 }, '-=0.8');
  gsap.from('.pd-step', { y: 40, opacity: 0, duration: 0.9, stagger: 0.12, ease: 'expo.out', scrollTrigger: { trigger: '.pd-steps', start: 'top 80%' } });
  gsap.from('.pd-label', { rotate: -3, y: 60, opacity: 0, duration: 1.1, ease: 'expo.out', scrollTrigger: { trigger: '.pd-label', start: 'top 85%' } });
  gsap.from('.pd-uses li, .pd-test', { y: 30, opacity: 0, duration: 0.8, stagger: 0.08, ease: 'expo.out', scrollTrigger: { trigger: '.pd-uses', start: 'top 80%' } });
  gsap.from('.pd-card', { y: 60, opacity: 0, duration: 1, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: '.pd-cards', start: 'top 85%' } });
})();
