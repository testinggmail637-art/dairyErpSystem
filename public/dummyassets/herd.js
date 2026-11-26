/* =========================================================
   Demo "our herd" page — scroll animation.
   Runs after script.js (uses its hasGSAP / reduce globals; counters come from script.js).
   ========================================================= */
(function herdPage() {
  const anim = typeof hasGSAP !== 'undefined' && hasGSAP && !reduce;
  const timeline = document.querySelector('[data-hd-timeline]');
  const items = timeline ? [...timeline.querySelectorAll('li')] : [];

  /* ---------- family: tap / Enter flips a card (hover also flips on desktop) ---------- */
  document.querySelectorAll('[data-hd-family] .hd-flip').forEach((card) => card.addEventListener('click', () => {
    const on = card.classList.toggle('is-flipped');
    card.setAttribute('aria-pressed', on ? 'true' : 'false');
  }));

  /* ---------- Gauri: each tap = she nods and says the next line ---------- */
  const gauri = document.querySelector('[data-hd-gauri]');
  if (gauri) {
    const facts = JSON.parse(gauri.dataset.facts || '[]');
    const bubble = gauri.querySelector('.hd-bubble');
    let n = 0;
    gauri.addEventListener('click', () => {
      if (!facts.length) return;
      bubble.textContent = facts[n++ % facts.length];
      bubble.classList.remove('is-pop'); gauri.classList.remove('is-talking');
      void bubble.offsetWidth;
      bubble.classList.add('is-pop'); gauri.classList.add('is-talking');
    });
    gauri.addEventListener('animationend', (e) => { if (e.animationName === 'hdNod') gauri.classList.remove('is-talking'); });
  }

  /* ---------- day: the sun rises on the left, peaks, sets on the right as the section scrolls ---------- */
  const day = document.querySelector('.hd-day');
  if (day && !reduce) {
    let ticking = false;
    const place = () => {
      const r = day.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, (innerHeight - r.top) / (innerHeight + r.height)));
      const arc = Math.sin(p * Math.PI);
      // keep the sun inside the visible part of the section: low at the sides, high in the middle
      const y = Math.min(r.height - 60, Math.max(60, -r.top + innerHeight * (0.82 - arc * 0.62)));
      day.style.setProperty('--sx', (8 + p * 84).toFixed(1) + '%');
      day.style.setProperty('--sy', y.toFixed(0) + 'px');
      day.style.setProperty('--glow', (0.12 + arc * 0.18).toFixed(3));
      ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(place); } }, { passive: true });
    place();
  }

  if (!anim) { items.forEach((li) => li.classList.add('is-lit')); return; }

  /* family cards deal in like a hand of cards */
  gsap.from('.hd-flip', { y: 80, rotate: (i) => (i - 1.5) * 6, opacity: 0, duration: 1, stagger: 0.1, ease: 'expo.out',
    scrollTrigger: { trigger: '[data-hd-family]', start: 'top 85%' } });

  /* hero: words rise, photo drifts slower than the page */
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .from('.hd-title .word', { yPercent: 110, duration: 1.3, stagger: 0.12 })
    .from('.hd-meta > *, .hd-hi, .hd-lead', { y: 30, opacity: 0, duration: 1, stagger: 0.08 }, '-=1')
    .from('.hd-stat', { y: 40, opacity: 0, duration: 1, stagger: 0.1 }, '-=0.8');
  gsap.to('.hd-hero-bg', { yPercent: 12, ease: 'none', scrollTrigger: { trigger: '.hd-hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.fromTo('.hd-visit-bg', { yPercent: -8 }, { yPercent: 8, ease: 'none', scrollTrigger: { trigger: '.hd-visit', start: 'top bottom', end: 'bottom top', scrub: true } });

  /* A2: beads pop in along the chain, the key bead lands last */
  gsap.from('.hd-bead:not(.hd-key)', { scale: 0, transformOrigin: 'center', transformBox: 'fill-box', duration: 0.5, stagger: 0.03, ease: 'back.out(3)',
    scrollTrigger: { trigger: '[data-hd-chains]', start: 'top 80%' } });
  gsap.from('.hd-key, .hd-key-label', { scale: 0, rotate: 180, transformOrigin: 'center', transformBox: 'fill-box', duration: 0.8, delay: 0.5, ease: 'back.out(2.5)',
    scrollTrigger: { trigger: '[data-hd-chains]', start: 'top 80%' } });

  /* breeds: the card underneath shrinks back a little as the next one covers it */
  const cards = [...document.querySelectorAll('.hd-card')];
  cards.forEach((card, i) => {
    const next = cards[i + 1];
    if (!next) return;
    gsap.to(card, { scale: 0.94, filter: 'brightness(.6)', ease: 'none',
      scrollTrigger: { trigger: next, start: 'top bottom', end: 'top 30%', scrub: true } });
  });

  /* day: the line fills and each time lights up as the line reaches it */
  if (timeline) {
    const fill = timeline.querySelector('.hd-line');
    ScrollTrigger.create({ trigger: timeline, start: 'top 65%', end: 'bottom 55%', scrub: true,
      onUpdate: (self) => {
        fill.style.setProperty('--p', self.progress.toFixed(3));
        items.forEach((li, i) => li.classList.toggle('is-lit', self.progress >= i / (items.length - 1) - 0.02));
      } });
    gsap.from(items, { x: 40, opacity: 0, duration: 0.8, stagger: 0.08, ease: 'expo.out', scrollTrigger: { trigger: timeline, start: 'top 80%' } });
  }

  /* care: cards rise one by one; calves: photos slide in from opposite sides */
  gsap.from('.hd-care-item', { y: 60, opacity: 0, duration: 1, stagger: 0.08, ease: 'expo.out', scrollTrigger: { trigger: '.hd-care-grid', start: 'top 80%' } });
  gsap.from('.hd-photos img:first-child', { x: -80, opacity: 0, rotate: -4, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '.hd-photos', start: 'top 80%' } });
  gsap.from('.hd-photos img:last-child', { x: 80, opacity: 0, rotate: 5, duration: 1.2, delay: 0.15, ease: 'expo.out', scrollTrigger: { trigger: '.hd-photos', start: 'top 80%' } });
})();
