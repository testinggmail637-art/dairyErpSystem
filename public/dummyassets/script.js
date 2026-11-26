/* =========================================================
   Yashadhya Organics — interactions & scroll animation
   Needs: Bootstrap 5, GSAP 3 + ScrollTrigger, Lenis (all via CDN)
   ========================================================= */

/* ---------- EDIT THESE ---------- */
const CONFIG = {
  whatsapp: '91XXXXXXXXXX',      // WhatsApp number, country code first, no +
  prices: { a2: 0, buf: 0 },     // ₹ per litre — 0 shows "[price]"
};

const PRODUCTS = [
  { glyph: 'दू', hi: 'A2 देसी गाय का दूध', en: 'A2 Desi Cow Milk', price: '₹[price]',
    tabs: ['From our Gir, Sahiwal and Tharparkar cows. Milked by hand at 5:30, tested at 6:00, at your door before 7 — in a glass bottle.',
           'Graze 4:30 → hand milking 5:30 → fat/SNF + adulteration test 6:00 → cooled to 4°C → glass bottle → your door.',
           'Start from 0.5 L. Daily or alternate days. Pause anytime, one bill a month.'] },
  { glyph: 'द', hi: 'मटका दही', en: 'Matka Curd', price: '₹[price]',
    tabs: ['Thick curd set the traditional way in a clay matka, from the same morning\'s milk. 500 g.',
           '[Add: setting time, starter, how long it keeps]',
           'Add it to your daily milk plan, or order on its own.'] },
  { glyph: 'घ', hi: 'बिलोना घी', en: 'Bilona Ghee', price: 'On request',
    tabs: ['Hand-churned bilona ghee — curd first, then butter, then slow-cooked ghee. 500 ml.',
           '[Add: litres of milk per kg, batch size, aroma notes]',
           'Price on request — ask on WhatsApp.'] },
  { glyph: 'प', hi: 'ताज़ा पनीर', en: 'Fresh Paneer', price: '₹[price]',
    tabs: ['Soft paneer made every day from our own milk. 250 g.',
           '[Add: how it is made, shelf life]',
           'Add to any morning delivery.'] },
  { glyph: 'भ', hi: 'भैंस का दूध', en: 'Buffalo Milk', price: '₹[price]',
    tabs: ['From our Murrah buffaloes — thick, creamy, perfect for chai, dahi and sweets. 1 L.',
           'Same morning routine: hand milking, 6 AM test, 4°C, glass bottle.',
           'Popular with sweet shops — ask about bulk supply.'] },
];

/* ---------- helpers ---------- */
const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const hasGSAP = typeof window.gsap !== 'undefined' && typeof window.ScrollTrigger !== 'undefined';
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
if (hasGSAP) gsap.registerPlugin(ScrollTrigger);

/* ---------- live countdown + date ---------- */
function tickClock() {
  const now = new Date();
  const next = new Date(now); next.setHours(7, 0, 0, 0);
  if (next <= now) next.setDate(next.getDate() + 1);
  const mins = Math.round((next - now) / 60000);
  const txt = String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
  $$('.js-countdown').forEach((el) => (el.textContent = txt));
  $$('.js-today').forEach((el) => (el.textContent = now.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase()));
}
tickClock(); setInterval(tickClock, 30000);

/* ---------- smooth scroll (Lenis) ---------- */
let lenis = null;
if (!reduce && typeof window.Lenis !== 'undefined') {
  lenis = new Lenis({ duration: 1.15, smoothWheel: true });
  if (hasGSAP) {
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
  } else {
    const raf = (t) => { lenis.raf(t); requestAnimationFrame(raf); };
    requestAnimationFrame(raf);
  }
}
// anchor links go through Lenis
$$('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
  const id = a.getAttribute('href');
  if (id.length < 2) return;
  const target = $(id);
  if (!target) return;
  e.preventDefault();
  const nav = $('#navMenu');
  if (nav && nav.classList.contains('show') && window.bootstrap) bootstrap.Collapse.getOrCreateInstance(nav).hide();
  if (lenis) lenis.scrollTo(target, { offset: -60 }); else target.scrollIntoView({ behavior: 'smooth' });
}));

/* ---------- mobile hero: move the sun into the visible strip ----------
   The hero SVG is cropped to the middle on a phone, so the desktop sun (x 1020) sat half off-screen behind
   the title. Runs before the intro/scroll animations read its position. */
if (matchMedia('(max-width: 767.98px)').matches) {
  document.querySelectorAll('.hero .sun, .hero .sun-glow').forEach((c) => {
    c.setAttribute('cx', '860'); c.setAttribute('cy', '190');
    c.setAttribute('r', c.classList.contains('sun') ? '62' : '105');
  });
}

/* ---------- loader → hero intro ---------- */
function heroIntro() {
  if (!hasGSAP || reduce) return;
  const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
  tl.from('.hero-title .word', { yPercent: 110, duration: 1.3, stagger: 0.12 })
    .from('.hero-meta > *', { y: 20, opacity: 0, duration: 0.8, stagger: 0.08 }, '-=1')
    .from('.hero-hi', { y: 40, opacity: 0, duration: 1.1 }, '-=0.9')
    .from('.hero-sub', { y: 30, opacity: 0, duration: 1 }, '-=0.8')
    .from('.hero-cta > *', { y: 30, opacity: 0, duration: 0.9, stagger: 0.1 }, '-=0.8')
    .from('.vertical-hi', { y: 80, opacity: 0, duration: 1.4 }, '-=1.2')
    .from('.hero .sun, .hero .sun-glow', { attr: { cy: 820 }, duration: 2.4, ease: 'power3.out' }, 0);
}

(function loader() {
  const el = $('#loader');
  if (!el) return heroIntro();
  let seen = false;
  try { seen = !!sessionStorage.getItem('ylSeen'); sessionStorage.setItem('ylSeen', '1'); } catch (e) {}
  if (seen) { el.remove(); return heroIntro(); }
  document.body.classList.add('is-loading');
  if (lenis) lenis.stop();
  let p = 0, done = false;
  const pct = $('#loaderPct'), bar = $('#loaderBar');
  const finish = () => {
    if (done) return; done = true;
    clearInterval(iv);
    pct.textContent = 100; bar.style.width = '100%';
    const close = () => {
      el.remove(); document.body.classList.remove('is-loading');
      if (lenis) lenis.start();
      if (hasGSAP) ScrollTrigger.refresh();
      heroIntro();
    };
    if (hasGSAP && !reduce) gsap.to(el, { yPercent: -100, duration: 0.8, ease: 'expo.inOut', delay: 0.1, onComplete: close });
    else close();
  };
  const iv = setInterval(() => {
    p = Math.min(100, p + 2 + Math.ceil(Math.random() * 6));
    pct.textContent = p; bar.style.width = p + '%';
    if (p >= 100) finish();
  }, 45);
  $('#loaderSkip').addEventListener('click', finish);
  setTimeout(finish, 2500); // safety
})();

/* ---------- MOD.02 swipe cards: "3 / 8" counter ---------- */
(function cmpSwipe() {
  const track = document.querySelector('.cmp-track'), pos = document.getElementById('cmpPos');
  if (!track || !pos) return;
  const rows = track.children;
  track.addEventListener('scroll', () => {
    const i = Math.round(track.scrollLeft / (rows[0].offsetWidth + 12));
    pos.textContent = `${Math.min(rows.length, i + 1)} / ${rows.length}`;
  }, { passive: true });
})();

/* ---------- mobile action bar: hide while scrolling down, back on scroll up or when scrolling stops ---------- */
(function actionBarAutoHide() {
  const bar = document.querySelector('.action-bar');
  if (!bar) return;
  // while typing (chat, enquiry form) the bar would cover the input → keep it away
  let typing = false;
  addEventListener('focusin', (e) => { if (e.target.matches('input, textarea, select')) { typing = true; bar.classList.add('ab-hide'); } });
  addEventListener('focusout', (e) => { if (e.target.matches('input, textarea, select')) { typing = false; bar.classList.remove('ab-hide'); } });
  let lastY = scrollY, ticking = false, idle;
  addEventListener('scroll', () => {
    // reader stopped on a section → bring the bar back
    clearTimeout(idle);
    idle = setTimeout(() => { if (!typing) bar.classList.remove('ab-hide'); }, 600);
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const y = scrollY, diff = y - lastY;
      if (Math.abs(diff) > 6) { bar.classList.toggle('ab-hide', typing || (diff > 0 && y > 300)); lastY = y; }
      ticking = false;
    });
  }, { passive: true });
})();

/* ---------- navbar, progress, to-top ---------- */
(function chrome() {
  const nav = $('#mainNav'), prog = $('#progress'), top = $('.to-top');
  let last = 0;
  const onScroll = () => {
    const y = window.scrollY;
    const max = document.documentElement.scrollHeight - innerHeight;
    prog.style.transform = `scaleX(${max > 0 ? y / max : 0})`;
    nav.classList.toggle('scrolled', y > 40);
    nav.classList.toggle('hide', y > 400 && y > last && !$('#navMenu').classList.contains('show'));
    top.classList.toggle('show', y > 900);
    last = y;
  };
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // active chapter link
  const links = $$('.navbar-nav .nav-link');
  const io = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (en.isIntersecting) links.forEach((l) => l.classList.toggle('active', l.getAttribute('href') === '#' + en.target.id));
  }), { rootMargin: '-45% 0px -50% 0px' });
  links.forEach((l) => { const s = $(l.getAttribute('href')); if (s) io.observe(s); });
})();

/* ---------- custom cursor + magnetic buttons ---------- */
if (finePointer && !reduce) {
  const c = $('#cursor');
  let cx = innerWidth / 2, cy = innerHeight / 2, tx = cx, ty = cy;
  addEventListener('mousemove', (e) => { tx = e.clientX; ty = e.clientY; });
  (function loop() { cx += (tx - cx) * 0.2; cy += (ty - cy) * 0.2; c.style.transform = `translate(${cx}px, ${cy}px) translate(-50%,-50%)`; requestAnimationFrame(loop); })();
  $$('a, button, .p-card, label.btn').forEach((el) => {
    el.addEventListener('mouseenter', () => c.classList.add('big'));
    el.addEventListener('mouseleave', () => c.classList.remove('big'));
  });
  $$('.magnetic').forEach((b) => {
    b.addEventListener('mousemove', (e) => {
      const r = b.getBoundingClientRect();
      b.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.25}px, ${(e.clientY - r.top - r.height / 2) * 0.35}px)`;
    });
    b.addEventListener('mouseleave', () => (b.style.transform = ''));
  });
} else { const c = $('#cursor'); if (c) c.remove(); }

/* ---------- hero mouse parallax ---------- */
if (finePointer && !reduce && hasGSAP) {
  const layers = [['.layer-back', 12], ['.layer-mid', 24], ['.layer-front', 48], ['.vertical-hi', 30]];
  const setters = layers.map(([s]) => [gsap.quickTo(s, 'x', { duration: 0.8, ease: 'power3' }), gsap.quickTo(s, 'y', { duration: 0.8, ease: 'power3' })]);
  $('.hero').addEventListener('mousemove', (e) => {
    const px = e.clientX / innerWidth - 0.5, py = e.clientY / innerHeight - 0.5;
    layers.forEach(([, d], i) => { setters[i][0](-px * d); setters[i][1](-py * d * 0.5); });
  });
}

/* ---------- product modal (data → DOM) ---------- */
let currentProduct = 0;
function fillProduct(i) {
  currentProduct = i;
  const p = PRODUCTS[i];
  $('#pmNum').textContent = String(i + 1).padStart(2, '0');
  $('#pmGlyph').textContent = p.glyph;
  $('#pmHi').textContent = p.hi;
  $('#pmTitle').textContent = p.en;
  $('#pmPrice').textContent = p.price;
  p.tabs.forEach((t, k) => ($('#pmT' + k).textContent = t));
  const first = $('.hud-tabs .nav-link');
  if (first && window.bootstrap) bootstrap.Tab.getOrCreateInstance(first).show();
  if (hasGSAP && !reduce) gsap.from('#productModal .modal-body > .row > *', { y: 24, opacity: 0, duration: 0.6, stagger: 0.08, ease: 'expo.out' });
}
$$('[data-product]').forEach((b) => b.addEventListener('click', () => fillProduct(+b.dataset.product)));
$('#pmNext').addEventListener('click', () => fillProduct((currentProduct + 1) % PRODUCTS.length));
const pm = $('#productModal');
pm.addEventListener('show.bs.modal', () => lenis && lenis.stop());
pm.addEventListener('hidden.bs.modal', () => lenis && lenis.start());

/* ---------- 3D tilt on product cards ---------- */
if (finePointer && !reduce) {
  $$('.tilt').forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const r = card.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
      card.style.transform = `perspective(900px) rotateY(${x * 12}deg) rotateX(${-y * 12}deg) translateY(-8px)`;
    });
    card.addEventListener('mouseleave', () => (card.style.transform = ''));
  });
}

/* ---------- plan calculator ---------- */
function updatePlan() {
  const milk = $('input[name="milk"]:checked').value;
  const qty = parseFloat($('input[name="qty"]:checked').value);
  const days = parseInt($('input[name="freq"]:checked').value, 10);
  const litres = qty * days;
  const price = CONFIG.prices[milk] || 0;
  $('#planDel').textContent = days;
  $('#planLine').textContent = `${milk === 'a2' ? 'A2 Desi Cow milk' : 'Murrah Buffalo milk'} · ${qty} L · ${days === 30 ? 'every morning' : 'alternate mornings'}`;
  $('#planBill').textContent = price > 0 ? '₹' + Math.round(litres * price).toLocaleString('en-IN') : `₹[price] × ${litres} L`;
  const out = $('#planL');
  if (hasGSAP && !reduce) {
    const o = { v: parseFloat(out.textContent) || 0 };
    gsap.to(o, { v: litres, duration: 0.6, ease: 'power2.out', onUpdate: () => (out.textContent = Number.isInteger(litres) ? Math.round(o.v) : o.v.toFixed(1)) });
    gsap.fromTo('.plan-out', { scale: 0.98 }, { scale: 1, duration: 0.5, ease: 'back.out(3)' });
  } else out.textContent = litres;
}
$$('#ch5 input[type="radio"]').forEach((r) => r.addEventListener('change', updatePlan));
updatePlan();

/* ---------- Gauri chat ---------- */
function gauriAnswer(q) {
  const t = q.toLowerCase();
  const has = (re) => re.test(t);
  if (has(/a2|ए2|ए-2/)) return 'A2 एक प्रोटीन है जो गिर, साहीवाल और थारपारकर जैसी देसी गायों के दूध में होता है। बहुत लोगों को ये पचाने में हल्का लगता है।';
  if (has(/price|rate|cost|daam|dam|kitne ka|kitna ka|दाम|कीमत|रेट|₹|पैसे/)) return 'दूध का दाम: [₹ प्रति लीटर]। घी, पनीर, दही के दाम: [जोड़ें]। "दूध मंगवाएं" बटन दबाइए, टीम उसी दिन जवाब देगी।';
  if (has(/deliver|delivery|time|kab|kitne baje|subah|area|zone|डिलीवरी|बजे|सुबह|इलाक|कहाँ|kaha/)) return 'हर सुबह 7 बजे से पहले, [इलाका 1], [इलाका 2] और [इलाका 3] में। रोज़ या एक दिन छोड़कर — जैसा आप चाहें।';
  if (has(/pause|stop|band|ruk|travel|bahar|रोक|बंद|छुट्टी/)) return 'जब चाहें रोक सकते हैं — बस [कब तक: रात X बजे तक] कॉल या WhatsApp कर दें। महीने के आख़िर में एक ही बिल।';
  if (has(/bottle|glass|plastic|बोतल|काँच|कांच|थैली/)) return 'सिर्फ़ काँच की बोतल, प्लास्टिक की थैली नहीं। खाली बोतल बाहर रख दें, अगली सुबह हम ले जाते हैं।';
  if (has(/test|pure|shuddh|milawat|water|paani|powder|टेस्ट|जाँच|जांच|शुद्ध|मिलावट|पानी|पाउडर/)) return 'सुबह 6 बजे हर बैच की जाँच: फैट/SNF, पानी, पाउडर, प्रिज़र्वेटिव और हार्मोन। फिर 4°C पर ठंडा। रिपोर्ट कभी भी माँगिए।';
  if (has(/visit|farm|goshala|gaushala|dekh|गौशाला|फार्म|देख/)) return 'हाँ! गौशाला आप कभी भी देखने आ सकते हैं — आने से पहले +91 [फ़ोन] पर बता दें।';
  if (has(/ghee|paneer|dahi|curd|घी|पनीर|दही|मटका/)) return 'मटका दही (500 g), बिलोना घी (500 ml, हाथ से बिलोया) और ताज़ा पनीर (250 g, रोज़ बनता है) — सब हमारे अपने दूध से।';
  if (has(/pay|upi|bill|bil|paisa|भुगतान|बिल|पेमेंट/)) return 'महीने के आख़िर में एक बिल। UPI, कैश या [और तरीके] — जैसा आपको आसान लगे।';
  if (has(/trial|sample|try|pehle|ट्रायल|सैंपल|पहले/)) return 'पहले आधा लीटर से शुरू कर सकते हैं। पसंद आए तो रोज़ का प्लान बना लीजिए।';
  if (has(/^(hi|hello|namaste|hey)|नमस्ते|राम राम|हेलो/)) return 'नमस्ते! 🙏 दूध, डिलीवरी, दाम या गौशाला — कुछ भी पूछिए।';
  return null; // unknown → WhatsApp button
}
(function chat() {
  const log = $('#chatLog'), count = $('#msgCount');
  const add = (text, who) => {
    const d = document.createElement('div');
    d.className = 'msg ' + who; d.textContent = text;
    log.appendChild(d); log.scrollTop = log.scrollHeight;
    count.textContent = log.querySelectorAll('.msg:not(.typing)').length;
    return d;
  };
  const ask = (q) => {
    if (!q.trim()) return;
    add(q, 'me');
    const typing = add('', 'her typing');
    typing.innerHTML = '<i></i><i></i><i></i>'; typing.setAttribute('aria-label', 'Gauri is typing');
    const answer = gauriAnswer(q);
    // longer answers "type" a little longer, so it feels natural
    const wait = Math.min(1400, 500 + (answer ? answer.length * 6 : 400));
    setTimeout(() => {
      typing.remove();
      if (answer) return void add(answer, 'her');
      const d = add('ये मैं पक्का नहीं बता सकती — टीम से सीधे पूछ लीजिए, उसी दिन जवाब मिलेगा।', 'her');
      const wa = document.createElement('a');
      wa.className = 'msg-wa'; wa.target = '_blank'; wa.rel = 'noopener'; wa.textContent = 'WhatsApp पर पूछें →';
      wa.href = `https://wa.me/${CONFIG.whatsapp}?text=${encodeURIComponent('नमस्ते, मेरा सवाल: ' + q)}`;
      d.appendChild(document.createElement('br')); d.appendChild(wa);
      log.scrollTop = log.scrollHeight;
    }, wait);
  };
  $$('#chips .chip').forEach((c) => c.addEventListener('click', () => ask(c.textContent)));
  $('#chatForm').addEventListener('submit', (e) => { e.preventDefault(); const i = $('#chatInput'); ask(i.value); i.value = ''; });

  // Gauri's eyes follow the cursor
  const eyes = $('.gauri .eyes');
  if (eyes && finePointer && !reduce) addEventListener('mousemove', (e) => {
    const r = $('.gauri').getBoundingClientRect();
    const dx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / 400));
    const dy = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2)) / 400));
    if (hasGSAP) gsap.to(eyes, { x: dx * 5, y: dy * 4, duration: 0.4, overwrite: 'auto' });
    else eyes.setAttribute('transform', `translate(${dx * 5} ${dy * 4})`);
  });
})();

/* ---------- enquiry → WhatsApp ---------- */
$('#enquiryForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const f = e.target;
  if (!f.checkValidity()) { f.classList.add('was-validated'); return; }
  const msg = `Namaste Yashadhya! Name: ${$('#eName').value}, Mobile: ${$('#ePhone').value}, Interest: ${$('#eInt').value}`;
  $('#enquiryOk').classList.remove('d-none');
  window.open(`https://wa.me/${CONFIG.whatsapp}?text=${encodeURIComponent(msg)}`, '_blank', 'noopener');
});

/* =========================================================
   SCROLL ANIMATIONS (GSAP + ScrollTrigger)
   ========================================================= */
function splitWords(el) {
  el.innerHTML = el.textContent.trim().split(/\s+/).map((w) => `<span class="w">${w}</span>`).join(' ');
  return $$('.w', el);
}

function countUp(el) {
  const end = parseFloat(el.dataset.count), dec = parseInt(el.dataset.decimals || '0', 10);
  if (!hasGSAP || reduce) { el.textContent = end.toFixed(dec); return; }
  const o = { v: 0 };
  gsap.to(o, { v: end, duration: 1.6, ease: 'power2.out', onUpdate: () => (el.textContent = o.v.toFixed(dec)),
    scrollTrigger: { trigger: el, start: 'top 85%', once: true } });
}
$$('[data-count]').forEach(countUp);

if (!hasGSAP || reduce) {
  // no animation: make sure everything is visible
  $$('[data-split-words]').forEach((el) => splitWords(el).forEach((w) => (w.style.opacity = 1)));
  $$('.checks li').forEach((li) => { li.style.opacity = 1; li.style.transform = 'none'; });
  $$('.cmp-row').forEach((r) => r.style.setProperty('--strike', 1));
  const fill = $('#milkFill'); if (fill) fill.setAttribute('transform', 'translate(0,60)');
  const fp = $('#fillPct'); if (fp) fp.textContent = 100;
} else {
  /* hero: content drifts and fades as you scroll away, sun sinks back */
  gsap.to('.hero-inner', { yPercent: -25, opacity: 0, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.hero .sun, .hero .sun-glow', { attr: { cy: 760 }, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  gsap.to('.layer-front', { yPercent: 10, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });

  /* marquee: always moving, speeds up + flips direction with scroll */
  (function marquee() {
    const track = $('.marquee-track');
    const tween = gsap.to(track, { xPercent: -50, duration: 28, ease: 'none', repeat: -1 });
    ScrollTrigger.create({
      onUpdate: (self) => {
        const v = self.getVelocity() / 300;
        gsap.to(tween, { timeScale: (self.direction === 1 ? 1 : -1) * Math.min(6, 1 + Math.abs(v)), duration: 0.3, overwrite: true });
        gsap.to(tween, { timeScale: self.direction === 1 ? 1 : -1, duration: 1.2, delay: 0.3, overwrite: false });
      },
    });
  })();

  /* promise: words light up one by one */
  $$('[data-split-words]').forEach((el) => {
    const words = splitWords(el);
    gsap.to(words, { opacity: 1, stagger: 0.08, ease: 'none', scrollTrigger: { trigger: el, start: 'top 80%', end: 'bottom 45%', scrub: true } });
  });
  gsap.from('.stat', { y: 60, opacity: 0, stagger: 0.12, duration: 1, ease: 'expo.out', scrollTrigger: { trigger: '.stats', start: 'top 85%' } });

  /* generic reveals */
  $$('.reveal').forEach((el) => gsap.from(el, { y: 80, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: el, start: 'top 85%' } }));
  $$('.chapter-num').forEach((el) => gsap.fromTo(el, { xPercent: -15 }, { xPercent: 10, ease: 'none', scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true } }));

  /* CH1: pinned horizontal scroll; sky shifts night → day, sun rises, clock runs */
  (function morning() {
    const track = $('.morning-track'), pin = $('.morning-pin');
    const dist = () => Math.max(0, track.scrollWidth - innerWidth + 48);
    const clock = $('#morningClock');
    const tl = gsap.timeline({
      scrollTrigger: {
        trigger: pin, start: 'top top', end: () => '+=' + (dist() + innerHeight * 0.6), pin: true, scrub: 1, invalidateOnRefresh: true,
        onUpdate: (self) => {
          const mins = Math.round(270 + self.progress * 150); // 4:30 → 7:00
          clock.textContent = String(Math.floor(mins / 60)).padStart(2, '0') + ':' + String(mins % 60).padStart(2, '0');
        },
      },
    });
    tl.to(track, { x: () => -dist(), ease: 'none' }, 0)
      .to('.morning-sky', { keyframes: [{ backgroundColor: '#6B2E3A' }, { backgroundColor: '#C9652F' }, { backgroundColor: '#E8A646' }], ease: 'none' }, 0)
      .fromTo('.morning-sun', { top: '75%', scale: 0.7 }, { top: '6%', scale: 1.2, ease: 'none' }, 0)
      .from('.m-card', { y: 80, rotate: 3, opacity: 0.3, stagger: 0.12, duration: 0.3, ease: 'power2.out' }, 0);
  })();

  /* CH2: product cards rise in */
  gsap.from('.products > div', { y: 120, opacity: 0, rotate: 2, stagger: 0.1, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '.products', start: 'top 85%' } });

  /* CH3: breed rows slide from alternating sides */
  $$('.breed-row').forEach((row, i) => gsap.from(row.querySelector('.breed-en'), {
    xPercent: i % 2 ? 30 : -30, opacity: 0, duration: 1.3, ease: 'expo.out', scrollTrigger: { trigger: row, start: 'top 88%' } }));
  $$('.breed-row').forEach((row) => gsap.from(row.querySelectorAll('.breed-hi, .breed-info'), {
    y: 30, opacity: 0, stagger: 0.1, duration: 1, ease: 'expo.out', scrollTrigger: { trigger: row, start: 'top 88%' } }));

  /* CH4: bottle fills with milk as you scroll, checks tick in, packet milk gets struck through */
  (function proof() {
    const fill = $('#milkFill'), pct = $('#fillPct');
    const o = { y: 380 };
    gsap.to(o, { y: 60, ease: 'none', scrollTrigger: { trigger: '.bottle', start: 'top 85%', end: 'bottom 35%', scrub: 1 },
      onUpdate: () => { fill.setAttribute('transform', `translate(0,${o.y})`); pct.textContent = Math.round(((380 - o.y) / 320) * 100); } });
    gsap.to('#milkFill path', { x: -100, duration: 2, repeat: -1, ease: 'none' }); // gentle wave
    gsap.to('.checks li', { opacity: 1, x: 0, stagger: 0.15, duration: 0.6, ease: 'power2.out', scrollTrigger: { trigger: '.checks', start: 'top 85%' } });
    $$('.cmp-row').forEach((row) => gsap.fromTo(row, { '--strike': 0 }, { '--strike': 1, ease: 'none', scrollTrigger: { trigger: row, start: 'top 85%', end: 'top 55%', scrub: true } }));
    gsap.from('.cmp-row .us', { x: 40, opacity: 0, stagger: 0.06, duration: 0.8, ease: 'expo.out', scrollTrigger: { trigger: '.compare', start: 'top 80%' } });
  })();

  /* CH5 + chat + reviews */
  gsap.from('#ch5 .hud, .plan-out', { y: 80, opacity: 0, stagger: 0.15, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '#ch5 .row', start: 'top 85%' } });
  gsap.timeline({ scrollTrigger: { trigger: '#gauri', start: 'top 70%' } })
    .from('.gauri', { scale: 0, rotate: -20, duration: 1.2, ease: 'back.out(2)' })
    .to('.gauri', { y: -10, duration: 1.8, ease: 'sine.inOut', repeat: -1, yoyo: true });
  gsap.from('.chat', { x: 80, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '#gauri', start: 'top 70%' } });
  gsap.from('.review', { y: 80, opacity: 0, rotate: (i) => (i - 1) * 4, stagger: 0.12, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '.reviews', start: 'top 75%' } });
  gsap.from('.faq .accordion-item', { y: 40, opacity: 0, stagger: 0.08, duration: 0.9, ease: 'expo.out', scrollTrigger: { trigger: '.faq', start: 'top 85%' } });

  /* contact + giant footer word */
  gsap.from('.epi-title', { yPercent: 40, opacity: 0, duration: 1.4, ease: 'expo.out', scrollTrigger: { trigger: '#contact', start: 'top 75%' } });
  gsap.from('.enquiry', { y: 80, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '#contact', start: 'top 75%' } });
  gsap.fromTo('.footer-word', { xPercent: 12, letterSpacing: '0.2em' }, { xPercent: 0, letterSpacing: '0em', ease: 'none', scrollTrigger: { trigger: '.footer-word', start: 'top bottom', end: 'bottom bottom', scrub: true } });

  addEventListener('load', () => ScrollTrigger.refresh());
}

/* =========================================================
   EXTRA ANIMATION LAYER
   ========================================================= */

/* nav links: text rolls up on hover */
$$('.navbar-nav .nav-link').forEach((a) => {
  const t = a.textContent.trim();
  a.setAttribute('aria-label', t);
  a.innerHTML = `<span class="roll" aria-hidden="true"><span>${t}</span><span>${t}</span></span>`;
});

/* ripple on every button click */
document.addEventListener('click', (e) => {
  const b = e.target.closest('.btn, .chip, .action-bar a');
  if (!b || reduce) return;
  const r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
  const rip = document.createElement('span');
  rip.className = 'ripple';
  Object.assign(rip.style, { width: s + 'px', height: s + 'px', left: e.clientX - r.left - s / 2 + 'px', top: e.clientY - r.top - s / 2 + 'px' });
  if (getComputedStyle(b).position === 'static') b.style.position = 'relative';
  b.style.overflow = 'hidden';
  b.appendChild(rip);
  setTimeout(() => rip.remove(), 650);
});

/* cursor leaves a trail of falling milk drops */
if (finePointer && !reduce) {
  let lastDrop = 0;
  addEventListener('mousemove', (e) => {
    const now = performance.now();
    if (now - lastDrop < 45) return;
    lastDrop = now;
    const d = document.createElement('span');
    d.className = 'drop';
    d.style.left = e.clientX - 4 + 'px'; d.style.top = e.clientY - 4 + 'px';
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 900);
  });
}

/* hero: floating milk bubbles on canvas, pushed away by the mouse */
(function particles() {
  const cv = $('#particles');
  if (!cv || reduce) return;
  const ctx = cv.getContext('2d');
  const hero = $('.hero');
  let w, h, dpr, mouse = { x: -999, y: -999 }, running = true;
  const N = innerWidth < 768 ? 35 : 80;
  const size = () => { dpr = Math.min(2, devicePixelRatio || 1); w = hero.clientWidth; h = hero.clientHeight; cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
  size(); addEventListener('resize', size);
  const ps = Array.from({ length: N }, () => ({ x: Math.random() * w, y: Math.random() * h, r: 1 + Math.random() * 3.2, vy: 0.15 + Math.random() * 0.5, vx: 0, a: 0.15 + Math.random() * 0.5, ph: Math.random() * 6.28 }));
  hero.addEventListener('mousemove', (e) => { const r = hero.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; });
  hero.addEventListener('mouseleave', () => (mouse.x = mouse.y = -999));
  new IntersectionObserver(([en]) => { running = en.isIntersecting; if (running) requestAnimationFrame(draw); }).observe(hero);
  function draw(t) {
    if (!running) return;
    ctx.clearRect(0, 0, w, h);
    for (const p of ps) {
      const dx = p.x - mouse.x, dy = p.y - mouse.y, dist = Math.hypot(dx, dy);
      if (dist < 140) { p.vx += (dx / dist) * 0.6; p.y += (dy / dist) * 1.2; }
      p.vx *= 0.92;
      p.x += p.vx + Math.sin(t / 1400 + p.ph) * 0.3;
      p.y -= p.vy;
      if (p.y < -10) { p.y = h + 10; p.x = Math.random() * w; }
      if (p.x < -10) p.x = w + 10; if (p.x > w + 10) p.x = -10;
      ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283);
      ctx.fillStyle = `rgba(255,253,247,${p.a})`; ctx.fill();
    }
    requestAnimationFrame(draw);
  }
  requestAnimationFrame(draw);
})();

/* photo slots wipe open + stat underlines draw when they enter view */
(function inView() {
  const io = new IntersectionObserver((ens) => ens.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }), { threshold: 0.3 });
  $$('.photo-slot, .stat').forEach((el) => io.observe(el));
})();

/* text scramble ("decoding") on HUD / chapter labels */
function scramble(el) {
  const final = el.textContent, chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#%&*/<>';
  let frame = 0; const total = 22;
  const iv = setInterval(() => {
    frame++;
    const done = Math.floor((frame / total) * final.length);
    el.textContent = final.split('').map((c, i) => (i < done || c === ' ' ? c : chars[(Math.random() * chars.length) | 0])).join('');
    if (frame >= total) { clearInterval(iv); el.textContent = final; }
  }, 35);
}
if (!reduce) {
  const targets = $$('.chapter-head .small-label, .hud .small-label, .morning-clock, .plan-out .small-label').filter((el) => el.childElementCount === 0 && el.textContent.trim());
  const io = new IntersectionObserver((ens) => ens.forEach((en) => { if (en.isIntersecting) { scramble(en.target); io.unobserve(en.target); } }), { threshold: 0.6 });
  targets.forEach((el) => io.observe(el));
}

/* split Latin text into letters (Devanagari stays whole so ligatures don't break) */
function splitChars(el) {
  const chars = [];
  el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
  [...el.childNodes].forEach((n) => {
    if (n.nodeType !== 3 || !n.textContent.trim()) return;
    const wrap = document.createElement('span');
    wrap.className = 'ch-wrap'; wrap.setAttribute('aria-hidden', 'true');
    n.textContent.split('').forEach((c) => {
      const s = document.createElement('span');
      s.className = 'ch'; s.textContent = c === ' ' ? ' ' : c;
      wrap.appendChild(s); chars.push(s);
    });
    n.replaceWith(wrap);
  });
  return chars;
}

if (hasGSAP && !reduce) {
  /* chapter titles: letters flip up one by one */
  $$('.chapter-title, .epi-title').forEach((title) => {
    const chars = splitChars(title);
    gsap.from(chars, { yPercent: 110, rotateX: -90, opacity: 0, stagger: 0.025, duration: 0.9, ease: 'back.out(1.6)', scrollTrigger: { trigger: title, start: 'top 85%' } });
    const hi = title.querySelectorAll('.hi');
    if (hi.length) gsap.from(hi, { clipPath: 'inset(0 100% 0 0)', duration: 1.2, delay: 0.3, ease: 'expo.inOut', scrollTrigger: { trigger: title, start: 'top 85%' } });
  });

  /* footer word: letters bounce in */
  const fw = $('.footer-word');
  if (fw) gsap.from(splitChars(fw), { yPercent: 120, stagger: 0.05, duration: 1, ease: 'bounce.out', scrollTrigger: { trigger: fw, start: 'top 95%' } });

  /* dark → cream: the promise section opens as a growing circle from the sun */
  gsap.fromTo('.promise', { clipPath: 'circle(6% at 72% 0%)' }, { clipPath: 'circle(150% at 72% 0%)', ease: 'none',
    scrollTrigger: { trigger: '.promise', start: 'top bottom', end: 'top 15%', scrub: true } });

  /* wave dividers keep rolling + stretch with scroll */
  $$('.wave-path').forEach((p) => {
    gsap.to(p, { attr: { d: 'M0 60 C 240 100 480 20 720 60 C 960 100 1200 20 1440 60 L 1440 120 L 0 120 Z' }, duration: 3, ease: 'sine.inOut', repeat: -1, yoyo: true });
    gsap.fromTo(p.closest('svg'), { scaleY: 0.3 }, { scaleY: 1.4, transformOrigin: 'bottom', ease: 'none', scrollTrigger: { trigger: p, start: 'top bottom', end: 'bottom top', scrub: true } });
  });

  /* big type leans with scroll speed */
  const skewers = $$('.chapter-title, .breed-en, .epi-title, .marquee-track');
  const skewTo = skewers.map((el) => gsap.quickTo(el, 'skewY', { duration: 0.5, ease: 'power3' }));
  ScrollTrigger.create({ onUpdate: (self) => { const s = gsap.utils.clamp(-6, 6, self.getVelocity() / -400); skewTo.forEach((fn) => fn(s)); } });

  /* Gauri blinks every few seconds */
  const eyes = $('.gauri .eyes');
  if (eyes) gsap.timeline({ repeat: -1, repeatDelay: 3 }).to(eyes, { scaleY: 0.1, svgOrigin: '200 145', duration: 0.08 }).to(eyes, { scaleY: 1, duration: 0.12 });

  /* HUD panels tilt toward the cursor */
  if (finePointer) $$('#ch4 .hud, #ch5 .hud, .chat').forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const r = card.getBoundingClientRect();
      gsap.to(card, { rotateY: ((e.clientX - r.left) / r.width - 0.5) * 6, rotateX: -((e.clientY - r.top) / r.height - 0.5) * 6, transformPerspective: 1000, duration: 0.5 });
    });
    card.addEventListener('mouseleave', () => gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.7 }));
  });

  ScrollTrigger.refresh();
}

/* =========================================================
   IMAGES + LOGO
   ========================================================= */
/* if images/logo.png exists, put it on the bottle label */
(function bottleLogo() {
  const test = new Image();
  test.onload = () => {
    $('.bottle').classList.add('has-logo');
    if (hasGSAP && !reduce) gsap.from('.label-logo', { scale: 0, rotate: -180, svgOrigin: '100 250', duration: 1.2, ease: 'back.out(1.8)',
      scrollTrigger: { trigger: '.bottle', start: 'top 70%' } });
  };
  test.onerror = () => { const l = $('.label-logo'); if (l) l.remove(); };
  test.src = document.querySelector('.label-logo')?.getAttribute('href') || 'images/logo.png';
})();

if (hasGSAP && !reduce) {
  /* bottle label slaps on as the bottle fills */
  gsap.from('.bottle-label', { scale: 0.4, opacity: 0, rotate: -12, duration: 1, ease: 'back.out(2)', scrollTrigger: { trigger: '.bottle', start: 'top 60%' } });
  /* breed images: pill opens from the left as the row enters, photo drifts on scroll */
  $$('.breed-thumb').forEach((t) => gsap.from(t, { clipPath: 'inset(0 100% 0 0 round 999px)', duration: 1.2, ease: 'expo.inOut',
    scrollTrigger: { trigger: t.closest('.breed-row'), start: 'top 88%' } }));
  $$('.breed-thumb img').forEach((img) => gsap.fromTo(img, { yPercent: -6 }, { yPercent: 6, ease: 'none',
    scrollTrigger: { trigger: img.closest('.breed-row'), start: 'top bottom', end: 'bottom top', scrub: true } }));
}
