/* =========================================================
   Demo Blog — list: search + category chips + "more"; article: contents highlight, text size, copy link.
   Runs after script.js (uses its hasGSAP / reduce globals).
   ========================================================= */
(function blogPage() {
  /* ---------- list ---------- */
  const grid = document.querySelector('[data-bl-grid]');
  const q = document.querySelector('[data-bl-q]');
  if (grid && q) {
    const cards = [...grid.querySelectorAll('.bl-card')];
    const chips = [...document.querySelectorAll('[data-bl-cat]')];
    const more = document.querySelector('[data-bl-more]'), none = document.querySelector('[data-bl-none]');
    const per = +grid.dataset.perPage || 4;
    let cat = '', shown = per;
    const norm = (s) => s.toLowerCase().trim();
    const render = (animateFrom = -1) => {
      const term = norm(q.value);
      const match = cards.filter((c) => (!cat || c.dataset.cat === cat) && (!term || norm(c.dataset.text).includes(term)));
      cards.forEach((c) => c.classList.add('is-hidden'));
      match.forEach((c, i) => {
        c.classList.toggle('is-hidden', i >= shown);
        c.classList.remove('is-in');
        if (i >= animateFrom && animateFrom >= 0 && i < shown) { void c.offsetWidth; c.classList.add('is-in'); }
      });
      more.classList.toggle('d-none', match.length <= shown);
      none.classList.toggle('d-none', match.length > 0);
    };
    chips.forEach((b) => b.addEventListener('click', () => {
      chips.forEach((x) => x.setAttribute('aria-checked', String(x === b)));
      cat = b.dataset.blCat; shown = per; render(0);
    }));
    let timer;
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { shown = per; render(0); }, 150); });
    more.addEventListener('click', () => { const from = shown; shown += per; render(from); });
    render();

    // the rubber stamp lands, then the featured polaroid drops onto the page
    setTimeout(() => document.querySelector('.bl-stamp')?.classList.add('is-in'), 500);
    if (typeof hasGSAP !== 'undefined' && hasGSAP && !reduce) {
      gsap.from('.bl-sheet', { y: 80, rotate: -3, opacity: 0, duration: 1.1, ease: 'expo.out' });
      gsap.from('.bl-polaroid--big', { y: -120, rotate: 14, opacity: 0, duration: 1.2, ease: 'back.out(1.4)', delay: 0.35 });
    }
  }

  /* ---------- article ---------- */
  const body = document.querySelector('[data-ar-body]');
  if (!body) return;

  // highlighter on headings + pen underline on quotes, as they scroll into view
  const lit = document.querySelectorAll('[data-ar-lit]');
  if ('IntersectionObserver' in window) {
    const lo = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('is-lit'); lo.unobserve(e.target); } }), { rootMargin: '0px 0px -25% 0px' });
    lit.forEach((el) => lo.observe(el));
  } else lit.forEach((el) => el.classList.add('is-lit'));

  // milk bottle fills as the article is read; full at the end of the text
  const bottle = document.querySelector('[data-ar-bottle]');
  if (bottle) {
    const milk = bottle.querySelector('[data-ar-milk]'), pct = bottle.querySelector('[data-ar-pct]');
    let raf = 0, last = -1;
    const fill = () => {
      raf = 0;
      const r = body.getBoundingClientRect();
      const p = Math.round(Math.min(1, Math.max(0, (innerHeight * 0.6 - r.top) / r.height)) * 100);
      if (p === last) return;
      last = p;
      milk.setAttribute('y', 96 - (p / 100) * 84);
      pct.textContent = p + '%';
      bottle.classList.toggle('is-full', p >= 100);
    };
    addEventListener('scroll', () => { if (!raf) raf = requestAnimationFrame(fill); }, { passive: true });
    fill();
  }

  // text size (A− / A+), remembered for the visitor
  const sizes = [16, 18, 20, 22, 24, 26];
  const base = () => (window.matchMedia('(max-width: 575.98px)').matches ? 1 : 2);
  let idx = base();
  try { const s = localStorage.getItem('arSize'); if (s !== null && sizes[+s]) idx = +s; } catch (e) {}
  const btns = document.querySelectorAll('[data-ar-size]');
  const apply = () => {
    body.style.setProperty('--ar-size', sizes[idx] + 'px');
    btns.forEach((b) => { b.disabled = (+b.dataset.arSize < 0 && idx === 0) || (+b.dataset.arSize > 0 && idx === sizes.length - 1); });
  };
  btns.forEach((b) => b.addEventListener('click', () => {
    idx = Math.min(sizes.length - 1, Math.max(0, idx + +b.dataset.arSize));
    apply();
    try { localStorage.setItem('arSize', idx); } catch (e) {}
  }));
  apply();

  // contents: highlight the section being read
  const links = [...document.querySelectorAll('[data-ar-toc]')];
  const heads = links.map((a) => document.querySelector(a.getAttribute('href'))).filter(Boolean);
  if (heads.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        links.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === '#' + e.target.id));
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    heads.forEach((h) => io.observe(h));
  }

  // copy link (clipboard API — not blocked by the copy deterrent)
  const copy = document.querySelector('[data-ar-copy]');
  copy?.addEventListener('click', async () => {
    const label = copy.textContent;
    try { await navigator.clipboard.writeText(copy.dataset.url); copy.textContent = '✓ ' + copy.dataset.done; } catch (e) { window.prompt('', copy.dataset.url); }
    setTimeout(() => { copy.textContent = label; }, 2000);
  });
})();
