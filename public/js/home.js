/* Home page (7A.3b) — plain JS like website.js. Theme, header and scroll reveal stay in website.js. */
(function () {
    'use strict';

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (s, el) { return (el || document).querySelector(s); };
    var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };

    /* Smooth scroll (Lenis, free) — off for reduced motion. */
    if (!reduce && window.Lenis) {
        var lenis = new window.Lenis({ duration: 1.1 });
        (function raf(t) { lenis.raf(t); requestAnimationFrame(raf); })(0);
        document.addEventListener('click', function (e) {
            var a = e.target.closest('a[href^="#"]');
            if (!a || a.getAttribute('href').length < 2) { return; }
            var target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); lenis.scrollTo(target, { offset: -80 }); }
        });
    }

    /* Swipe helper: only a clear sideways swipe, never a page scroll. */
    function swipe(el, onLeft, onRight) {
        if (!el) { return; }
        var x0 = null, y0 = null;
        el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
        el.addEventListener('touchend', function (e) {
            if (x0 === null) { return; }
            var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
            x0 = y0 = null;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) { (dx < 0 ? onLeft : onRight)(); }
        }, { passive: true });
    }

    /* 4. Breeds coverflow */
    var cf = $('[data-coverflow]');
    if (cf) {
        var cards = $$('.h-cf-card', cf), cur = 1;
        var renderCF = function () {
            var small = window.innerWidth < 768, gap = small ? 150 : 270;
            cards.forEach(function (c) {
                var d = +c.dataset.i - cur, a = Math.abs(d);
                c.style.transform = d === 0 ? 'translateZ(80px)' : 'translateX(' + (d * gap) + 'px) translateZ(-160px) rotateY(' + (d < 0 ? 42 : -42) + 'deg)';
                c.style.zIndex = 10 - a;
                c.style.opacity = a > 1 ? (small ? 0 : 0.45) : (a === 1 ? 0.85 : 1);
                c.style.filter = a ? 'saturate(.75)' : 'none';
                c.setAttribute('aria-current', d === 0 ? 'true' : 'false');
            });
            $('[data-cf-name]').textContent = cards[cur].dataset.name;
            $('[data-cf-tag]').textContent = cards[cur].dataset.tag;
            $('[data-cf-line]').textContent = cards[cur].dataset.line;
        };
        var go = function (i) { cur = (i + cards.length) % cards.length; renderCF(); };
        cards.forEach(function (c) { c.addEventListener('click', function () { go(+c.dataset.i); }); });
        $('[data-cf-prev]').addEventListener('click', function () { go(cur - 1); });
        $('[data-cf-next]').addEventListener('click', function () { go(cur + 1); });
        swipe(cf, function () { go(cur + 1); }, function () { go(cur - 1); });
        var rt;
        window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(renderCF, 120); });
        renderCF();
    }

    /* 5. Farm-to-glass photo stack */
    var stack = $('[data-stack]');
    if (stack) {
        var layers = $$('.h-stack-card', stack), steps = $$('[data-steps] .h-step'), st = 0;
        var renderStack = function () {
            var n = layers.length;
            layers.forEach(function (l) {
                var d = (+l.dataset.i - st + n) % n;
                l.style.transform = 'translate3d(' + (d * 22) + 'px,' + (-d * 36) + 'px,' + (-d * 120) + 'px)';
                l.style.zIndex = 10 - d;
                l.style.opacity = [1, 0.75, 0.5, 0.3][d];
            });
            steps.forEach(function (s) {
                var on = +s.dataset.i === st;
                s.classList.toggle('is-active', on);
                s.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        };
        steps.forEach(function (s) { s.addEventListener('click', function () { st = +s.dataset.i; renderStack(); }); });
        swipe(stack, function () { st = (st + 1) % layers.length; renderStack(); }, function () { st = (st - 1 + layers.length) % layers.length; renderStack(); });
        renderStack();
    }

    /* 6. Product cards: native scroll + arrows */
    var track = $('[data-prod-track]');
    if (track) {
        var by = function (dir) {
            var card = $('.h-prod', track);
            track.scrollBy({ left: dir * (card ? card.getBoundingClientRect().width + 28 : 300), behavior: reduce ? 'auto' : 'smooth' });
        };
        $('[data-prod-prev]').addEventListener('click', function () { by(-1); });
        $('[data-prod-next]').addEventListener('click', function () { by(1); });
    }

    /* 7. Delivery steps + plan chips */
    var dsteps = $('[data-dsteps]');
    if (dsteps) {
        var dbtns = $$('.h-dstep', dsteps), chips = $('[data-chips]'), img = $('[data-dimg]'), badge = $('[data-dbadge]');
        dbtns.forEach(function (b) {
            b.addEventListener('click', function () {
                var i = +b.dataset.i;
                dbtns.forEach(function (x) { x.classList.toggle('is-active', x === b); });
                img.style.opacity = 0;
                setTimeout(function () {
                    img.srcset = b.dataset.srcset;
                    img.src = b.dataset.src;
                    img.alt = $('b', b).textContent;
                    img.style.opacity = 1;
                }, reduce ? 0 : 200);
                badge.textContent = dsteps.dataset.stepWord + ' 0' + (i + 1) + ' / 0' + dbtns.length;
                chips.hidden = i !== 1;
            });
        });
        chips.addEventListener('click', function (e) {
            var c = e.target.closest('.h-chip');
            if (!c) { return; }
            $$('.h-chip', chips).forEach(function (x) { x.classList.toggle('is-on', x === c); x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
        });
    }

    /* 9. Reviews marquee: repeat cards until a row is wider than the screen, then double it for a seamless loop */
    $$('[data-marquee]').forEach(function (row) {
        var base = row.innerHTML, set = base, guard = 0;
        while (row.scrollWidth < window.innerWidth + 200 && guard++ < 6) { set += base; row.innerHTML = set; }
        row.innerHTML = set + set.replace(/<figure /g, '<figure aria-hidden="true" ');
    });

    /* 10. FAQ: question → chat answer */
    var faq = $('[data-faq]'), chat = $('[data-chat]');
    if (faq && chat) {
        faq.addEventListener('click', function (e) {
            var q = e.target.closest('.h-faq-q');
            if (!q) { return; }
            $$('.h-faq-q', faq).forEach(function (x) { x.classList.toggle('is-on', x === q); x.setAttribute('aria-pressed', x === q ? 'true' : 'false'); });
            chat.innerHTML = '';
            [['h-me', q.textContent], ['h-them', q.dataset.a]].forEach(function (m) {
                var div = document.createElement('div');
                div.className = 'h-bubble ' + m[0];
                div.textContent = m[1];
                chat.appendChild(div);
            });
        });
    }

    /* 11. Enquiry form: check fields; saving as an ERP lead comes in 7A.9 */
    var form = $('[data-home-form]');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var name = form.elements.name, phone = form.elements.phone;
            var nameOk = name.value.trim().length > 1, phoneOk = /^[0-9]{10}$/.test(phone.value.trim());
            name.setAttribute('aria-invalid', nameOk ? 'false' : 'true');
            phone.setAttribute('aria-invalid', phoneOk ? 'false' : 'true');
            $('[data-form-msg]', form).textContent = nameOk && phoneOk ? form.dataset.ok : form.dataset.bad;
            if (nameOk && phoneOk) { form.reset(); }
        });
    }
})();
