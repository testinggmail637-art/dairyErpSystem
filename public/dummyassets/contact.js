/* =========================================================
   Demo Contact page — open/closed status, one-tap WhatsApp message, area check.
   Runs after script.js (uses its hasGSAP / reduce globals).
   ========================================================= */
(function contactPage() {
  const waLink = (no, text) => `https://wa.me/${no}?text=${encodeURIComponent(text)}`;

  /* ---------- open / closed (India time, 6 AM – 8 PM) ---------- */
  const status = document.querySelector('[data-ct-status]');
  if (status) {
    const tick = () => {
      const h = +new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Kolkata', hour: '2-digit', hour12: false }).format(new Date());
      const open = h >= 6 && h < 20;
      status.classList.toggle('is-open', open);
      status.querySelector('[data-ct-status-text]').textContent = open ? status.dataset.open : status.dataset.closed;
    };
    tick();
    setInterval(tick, 60000);
  }

  /* ---------- one-tap message: chip + optional name / area → WhatsApp text ---------- */
  const box = document.querySelector('[data-ct-composer]');
  if (box) {
    const chips = [...document.querySelectorAll('.ct-composer .ct-chip')];
    const name = document.getElementById('ctName'), area = document.getElementById('ctArea'), note = document.getElementById('ctNote');
    const preview = box.querySelector('[data-ct-preview]'), send = box.querySelector('[data-ct-send]');
    let msg = chips[0]?.dataset.msg || '';
    const build = () => {
      const lines = [box.dataset.greeting];
      if (name.value.trim()) lines.push(box.dataset.nameLine.replace(':name', name.value.trim()));
      lines.push(msg);
      if (note.value.trim()) lines.push(note.value.trim());
      if (area.value.trim()) lines.push(box.dataset.areaLine.replace(':area', area.value.trim()));
      const text = lines.join('\n');
      preview.textContent = text;
      send.href = waLink(box.dataset.wa, text);
    };
    const pop = () => { preview.classList.remove('is-new'); void preview.offsetWidth; preview.classList.add('is-new'); };
    chips.forEach((c) => c.addEventListener('click', () => {
      chips.forEach((x) => x.setAttribute('aria-checked', String(x === c)));
      msg = c.dataset.msg; build(); pop();
    }));
    // arrow keys move between chips (radiogroup)
    document.querySelector('.ct-chips')?.addEventListener('keydown', (e) => {
      const i = chips.indexOf(document.activeElement);
      if (i < 0 || !['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].includes(e.key)) return;
      e.preventDefault();
      const next = chips[(i + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : -1) + chips.length) % chips.length];
      next.focus(); next.click();
    });
    [name, area, note].forEach((el) => el.addEventListener('input', build));
    build();

    /* voice typing (Hindi) — shown only where the browser supports it (Chrome / Android) */
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    const mic = document.querySelector('[data-ct-mic]');
    if (SR && mic) {
      mic.classList.remove('d-none');
      const label = mic.querySelector('[data-ct-mic-text]');
      let rec = null;
      const stop = () => { mic.setAttribute('aria-pressed', 'false'); label.textContent = mic.dataset.off; rec = null; };
      mic.addEventListener('click', () => {
        if (rec) { rec.stop(); return; }
        rec = new SR();
        rec.lang = 'hi-IN'; rec.interimResults = false; rec.maxAlternatives = 1;
        const before = note.value.trim();
        rec.onresult = (e) => {
          const said = [...e.results].map((r) => r[0].transcript).join(' ').trim();
          note.value = ((before ? before + ' ' : '') + said).slice(0, 300);
          build(); pop();
        };
        rec.onend = stop; rec.onerror = stop;
        mic.setAttribute('aria-pressed', 'true'); label.textContent = mic.dataset.on;
        rec.start();
      });
    }
  }

  /* ---------- delivery area check (names / PIN codes from lang/hi/demo/contact.php) ---------- */
  const form = document.querySelector('[data-ct-area]');
  if (form) {
    const zones = JSON.parse(form.dataset.zones || '[]');
    const input = document.getElementById('ctAreaQ');
    const result = document.querySelector('[data-ct-area-result]');
    const text = result.querySelector('[data-ct-area-text]'), cta = result.querySelector('[data-ct-area-cta]');
    const norm = (s) => s.toLowerCase().replace(/[\s.,-]+/g, '');
    const check = (q) => {
      const n = norm(q);
      if (!n) return;
      const hit = zones.find((z) => [z.name, ...z.pins].some((v) => { const m = norm(v); return m && (m.includes(n) || n.includes(m)); }));
      const shown = hit ? hit.name : q.trim();
      const yes = !!hit;
      text.textContent = (yes ? form.dataset.yes : form.dataset.no).replace(':area', shown);
      cta.textContent = (yes ? cta.dataset.yes : cta.dataset.no) + ' →';
      cta.href = waLink(form.dataset.wa, yes ? `नमस्ते यशाध्य! मैं ${shown} में रहता/रहती हूँ, मुझे रोज़ दूध चाहिए।` : `नमस्ते यशाध्य! मैं ${shown} में रहता/रहती हूँ। डिलीवरी शुरू हो तो मुझे बताइए।`);
      result.classList.remove('d-none', 'is-yes', 'is-no');
      result.classList.add(yes ? 'is-yes' : 'is-no');
    };
    form.addEventListener('submit', (e) => { e.preventDefault(); check(input.value); });
    document.querySelectorAll('[data-ct-zone]').forEach((b) => b.addEventListener('click', () => { input.value = b.dataset.ctZone; check(input.value); }));
  }

  /* ---------- save our number: builds a .vcf contact card in the browser ---------- */
  const save = document.querySelector('[data-ct-save]');
  save?.addEventListener('click', () => {
    const d = save.dataset;
    const card = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:Yashadhya Organics (दूध)', 'ORG:Yashadhya Organics',
      `TEL;TYPE=CELL:${d.tel}`, `EMAIL:${d.mail}`, `ADR;TYPE=WORK:;;${d.addr.replace(/,/g, '\\,')};;;;`, `URL:${location.origin}`, 'END:VCARD'].join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([card], { type: 'text/vcard;charset=utf-8' }));
    a.download = 'yashadhya-organics.vcf';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    save.classList.add('is-done');
  });

  /* ---------- gaushala visit: next 7 days (India time) + slot + people → WhatsApp ---------- */
  const book = document.querySelector('[data-ct-book]');
  if (book) {
    const daysBox = book.querySelector('[data-ct-days]'), go = book.querySelector('[data-ct-book-go]');
    const out = book.querySelector('[data-ct-people]');
    const slots = [...book.querySelectorAll('[data-ct-slot]')];
    const ist = (d, o) => new Intl.DateTimeFormat('hi-IN', { timeZone: 'Asia/Kolkata', ...o }).format(d);
    const days = Array.from({ length: 7 }, (_, i) => new Date(Date.now() + i * 864e5));
    let day = 0, slot = slots[0]?.dataset.ctSlot || '', people = 2;
    const dayLabel = (i) => (i === 0 ? book.dataset.today : i === 1 ? book.dataset.tomorrow : ist(days[i], { weekday: 'short' }));
    daysBox.innerHTML = days.map((d, i) => `<button type="button" class="ct-day-chip hi" role="radio" aria-checked="${i === 0}" data-i="${i}"><small>${dayLabel(i)}</small><b>${ist(d, { day: 'numeric' })}</b><small>${ist(d, { month: 'short' })}</small></button>`).join('');
    const update = () => {
      const when = `${dayLabel(day)}, ${ist(days[day], { day: 'numeric', month: 'long' })}`;
      go.href = waLink(book.dataset.wa, book.dataset.msg.replace(':day', when).replace(':slot', slot).replace(':people', people));
      out.textContent = people;
    };
    const pick = (list, el) => list.forEach((x) => x.setAttribute('aria-checked', String(x === el)));
    const dayBtns = [...daysBox.children];
    dayBtns.forEach((b) => b.addEventListener('click', () => { pick(dayBtns, b); day = +b.dataset.i; update(); }));
    slots.forEach((b) => b.addEventListener('click', () => { pick(slots, b); slot = b.dataset.ctSlot; update(); }));
    book.querySelectorAll('[data-ct-step]').forEach((b) => b.addEventListener('click', () => { people = Math.min(20, Math.max(1, people + +b.dataset.ctStep)); update(); }));
    update();
  }

  /* ---------- entrance animation ---------- */
  if (typeof hasGSAP !== 'undefined' && hasGSAP && !reduce) {
    gsap.from('.ct-title .word', { yPercent: 110, duration: 1.1, ease: 'expo.out', stagger: 0.08 });
    gsap.from('.ct-way', { y: 60, opacity: 0, duration: 1, ease: 'expo.out', stagger: 0.08, delay: 0.25 });
    gsap.from('.ct-phone', { y: 80, rotate: 3, opacity: 0, duration: 1.2, ease: 'expo.out', scrollTrigger: { trigger: '.ct-composer', start: 'top 70%' } });
  }
})();
