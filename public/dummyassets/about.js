/* =========================================================
   Demo About page — journey track + scroll animation.
   Runs after script.js (uses its hasGSAP / reduce globals; manifesto words come from script.js).
   ========================================================= */
(function aboutPage() {
  const anim = typeof hasGSAP !== 'undefined' && hasGSAP && !reduce;

  /* ---------- journey: arrows, drag with the mouse, progress bar ---------- */
  const track = document.querySelector('[data-ab-track]');
  const bar = document.querySelector('[data-ab-progress]');
  if (track) {
    const step = () => (track.querySelector('.ab-step')?.offsetWidth || 300) + 22;
    const progress = () => {
      const max = track.scrollWidth - track.clientWidth;
      if (bar) bar.style.setProperty('--p', max > 0 ? (0.15 + 0.85 * track.scrollLeft / max).toFixed(3) : 1);
    };
    document.querySelector('[data-ab-prev]')?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    document.querySelector('[data-ab-next]')?.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
    track.addEventListener('scroll', progress, { passive: true });
    progress();

    // drag to scroll on desktop (touch already swipes natively)
    let down = false, startX = 0, startLeft = 0, moved = false;
    track.addEventListener('pointerdown', (e) => {
      if (e.pointerType !== 'mouse') return;
      down = true; moved = false; startX = e.clientX; startLeft = track.scrollLeft;
    });
    addEventListener('pointermove', (e) => {
      if (!down) return;
      const dx = e.clientX - startX;
      if (Math.abs(dx) > 4) { moved = true; track.classList.add('is-dragging'); }
      track.scrollLeft = startLeft - dx;
    });
    addEventListener('pointerup', () => {
      if (!down) return;
      down = false; track.classList.remove('is-dragging');
      if (moved) track.scrollTo({ left: Math.round(track.scrollLeft / step()) * step(), behavior: 'smooth' });
    });
  }

  /* ---------- "now at the goshala": India time (IST) → what is happening right now ---------- */
  const now = document.querySelector('[data-ab-now]');
  if (now) {
    const schedule = JSON.parse(now.dataset.schedule || '[]');
    const clock = now.querySelector('[data-ab-clock]'), status = now.querySelector('[data-ab-status]');
    let last = '';
    const tick = () => {
      const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
      const h = +parts.find((x) => x.type === 'hour').value, m = +parts.find((x) => x.type === 'minute').value;
      clock.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')} IST`;
      const t = h + m / 60;
      const text = schedule.reduce((cur, [from, what]) => (t >= from ? what : cur), schedule[0]?.[1] || '');
      if (text !== last) { status.textContent = text; status.classList.remove('is-new'); void status.offsetWidth; status.classList.add('is-new'); last = text; }
    };
    tick();
    setInterval(tick, 30000);
  }

  /* ---------- memory wall: drag polaroids around (desktop); the last one touched comes to the front ---------- */
  const wall = document.querySelector('[data-ab-wall]');
  if (wall && matchMedia('(min-width: 992px)').matches) {
    let z = 10;
    wall.querySelectorAll('.ab-polaroid').forEach((card) => {
      let sx = 0, sy = 0, ox = 0, oy = 0, dragging = false;
      card.addEventListener('pointerdown', (e) => {
        dragging = true; card.setPointerCapture(e.pointerId); card.classList.add('is-drag'); card.style.zIndex = ++z;
        sx = e.clientX; sy = e.clientY; ox = card.offsetLeft; oy = card.offsetTop;
      });
      card.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const maxX = wall.clientWidth - card.offsetWidth, maxY = wall.clientHeight - card.offsetHeight;
        card.style.left = Math.max(0, Math.min(maxX, ox + e.clientX - sx)) + 'px';
        card.style.top = Math.max(0, Math.min(maxY, oy + e.clientY - sy)) + 'px';
      });
      const drop = () => {
        if (!dragging) return;
        dragging = false; card.classList.remove('is-drag');
        card.style.setProperty('--r', (Math.random() * 12 - 6).toFixed(1) + 'deg'); // lands at a new slight angle
      };
      card.addEventListener('pointerup', drop);
      card.addEventListener('pointercancel', drop);
    });
  }

  if (!anim) return;

  /* polaroids drop onto the wall one by one */
  gsap.from('.ab-polaroid', { y: -120, opacity: 0, rotate: (i) => (i % 2 ? 20 : -20), duration: 0.9, stagger: 0.1, ease: 'back.out(1.4)',
    scrollTrigger: { trigger: '[data-ab-wall]', start: 'top 80%' }, clearProps: 'transform' });

  /* hero: words rise; photo drifts inside its frame */
  gsap.timeline({ defaults: { ease: 'expo.out' } })
    .from('.ab-title .word', { yPercent: 110, duration: 1.3, stagger: 0.12 })
    .from('.ab-meta > *, .ab-hi, .ab-lead', { y: 30, opacity: 0, duration: 1, stagger: 0.08 }, '-=1')
    .fromTo('.ab-hero-photo', { clipPath: 'inset(100% 0 0 0 round 30px)' }, { clipPath: 'inset(0% 0 0 0 round 30px)', duration: 1.4, ease: 'expo.inOut' }, 0.2);
  gsap.to('.ab-hero-photo img', { yPercent: -12, ease: 'none', scrollTrigger: { trigger: '.ab-hero', start: 'top top', end: 'bottom top', scrub: true } });

  /* letter: lifts in, lines appear one by one, signature writes itself, stamp lands */
  const letter = document.querySelector('[data-ab-letter]');
  if (letter) {
    const st = { trigger: letter, start: 'top 75%' };
    gsap.from(letter, { y: 80, rotate: 3, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: st });
    gsap.from(letter.querySelectorAll('p.hi'), { opacity: 0, y: 12, duration: 0.7, stagger: 0.18, delay: 0.3, ease: 'power2.out', scrollTrigger: st });
    gsap.fromTo('.ab-sign path', { strokeDashoffset: 600 }, { strokeDashoffset: 0, duration: 1.8, delay: 1.2, ease: 'power1.inOut', scrollTrigger: st });
    gsap.from('.ab-stamp', { scale: 2.4, opacity: 0, rotate: -20, duration: 0.5, delay: 1.6, ease: 'back.out(2.2)', scrollTrigger: st });
  }

  /* journey cards slide in from the right */
  gsap.from('.ab-step', { x: 120, opacity: 0, duration: 1, stagger: 0.1, ease: 'expo.out', scrollTrigger: { trigger: '.ab-track', start: 'top 85%' } });

  /* values: big numbers count their way in */
  gsap.from('.ab-value-list li', { y: 60, opacity: 0, duration: 1, stagger: 0.12, ease: 'expo.out', scrollTrigger: { trigger: '.ab-value-list', start: 'top 80%' } });

  /* team: avatars pop */
  gsap.from('.ab-person', { y: 50, opacity: 0, duration: 0.9, stagger: 0.08, ease: 'expo.out', scrollTrigger: { trigger: '.ab-team-grid', start: 'top 85%' } });
  gsap.from('.ab-avatar', { scale: 0, duration: 0.7, stagger: 0.08, delay: 0.2, ease: 'back.out(2.5)', scrollTrigger: { trigger: '.ab-team-grid', start: 'top 85%' } });

  /* place: the road draws itself, the card slides up */
  gsap.fromTo('.ab-road', { strokeDashoffset: 400 }, { strokeDashoffset: 0, ease: 'none', scrollTrigger: { trigger: '.ab-map', start: 'top bottom', end: 'bottom top', scrub: true } });
  gsap.from('.ab-map-card', { y: 60, opacity: 0, duration: 1.1, ease: 'expo.out', scrollTrigger: { trigger: '.ab-map', start: 'top 75%' } });
  gsap.from('.ab-pin', { scale: 0, duration: 0.8, delay: 0.3, ease: 'back.out(3)', scrollTrigger: { trigger: '.ab-map', start: 'top 75%' } });
})();
