/* Public website (Phase 7A). Plain JS, no libraries: theme toggle, header shadow, scroll reveal,
   counters, timelines, product slider, compare slider, demo enquiry form. */
(function () {
    'use strict';

    var root = document.documentElement;
    var COOKIE = 'w_theme';
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Day / night ---------- */
    function setTheme(theme) {
        root.dataset.theme = theme;
        root.dataset.bsTheme = theme;
        root.dataset.themePref = theme;
        document.cookie = COOKIE + '=' + theme + '; path=/; max-age=31536000; SameSite=Lax';
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.content = getComputedStyle(root).getPropertyValue('--w-primary').trim();
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-theme-toggle]')) {
            setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        }
    });

    /* ---------- Header shadow ---------- */
    var header = document.querySelector('.w-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---------- Counters ---------- */
    function runCounter(el) {
        var to = parseInt(el.dataset.count, 10) || 0;
        var from = parseInt(el.dataset.from || '0', 10);
        if (reduceMotion) { el.textContent = to; return; }
        var start = null;
        var duration = 1600;
        el.textContent = from;
        function step(ts) {
            if (start === null) { start = ts; }
            var t = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = Math.round(from + (to - from) * eased);
            if (t < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    }

    /* ---------- Compare slider ---------- */
    function setCompare(box, value) {
        box.style.setProperty('--pos', value + '%');
    }

    function hintCompare(box) {
        if (reduceMotion) { return; }
        var frames = [50, 30, 70, 50];
        var i = 0;
        var range = box.querySelector('.w-compare-range');
        (function next() {
            if (i >= frames.length || box.dataset.touched) { return; }
            box.style.transition = 'none';
            animateTo(box, i === 0 ? 50 : frames[i - 1], frames[i], 600, function () {
                range.value = frames[i];
                i++;
                setTimeout(next, 120);
            });
        })();
    }

    function animateTo(box, from, to, duration, done) {
        var start = null;
        function step(ts) {
            if (box.dataset.touched) { return; }
            if (start === null) { start = ts; }
            var t = Math.min((ts - start) / duration, 1);
            var eased = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
            setCompare(box, from + (to - from) * eased);
            if (t < 1) { requestAnimationFrame(step); } else { done(); }
        }
        requestAnimationFrame(step);
    }

    document.querySelectorAll('[data-compare]').forEach(function (box) {
        var range = box.querySelector('.w-compare-range');
        range.addEventListener('input', function () {
            box.dataset.touched = '1';
            setCompare(box, range.value);
        });
    });

    /* ---------- Scroll reveal (reveal blocks, timelines, counters, compare hint) ---------- */
    function onVisible(el) {
        el.classList.add('is-visible');
        el.querySelectorAll('[data-count]').forEach(runCounter);
        if (el.matches('[data-compare]')) { hintCompare(el); }
    }

    var targets = document.querySelectorAll('[data-reveal], [data-timeline]');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        targets.forEach(function (el) { el.classList.add('is-visible'); });
        document.querySelectorAll('[data-count]').forEach(function (el) { el.textContent = el.dataset.count; });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    onVisible(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        targets.forEach(function (el) { io.observe(el); });
    }

    /* ---------- Product showcase slider ---------- */
    document.querySelectorAll('[data-slider]').forEach(function (slider) {
        var slides = slider.querySelectorAll('.w-slide');
        var tabs = slider.querySelectorAll('[data-slide-to]');
        var delay = 6000;
        var current = 0;
        var timer = null;
        slider.style.setProperty('--w-autoplay', delay + 'ms');

        function go(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (s, i) {
                var on = i === current;
                s.classList.toggle('is-active', on);
                s.setAttribute('aria-hidden', on ? 'false' : 'true');
            });
            tabs.forEach(function (t, i) {
                var on = i === current;
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) {
                    void t.offsetWidth; // restart the progress bar animation
                    t.classList.add('is-active');
                    // Scroll only the tab row (never the page) so the active tab stays in view on phones.
                    var row = t.parentElement;
                    row.scrollTo({ left: t.offsetLeft - (row.clientWidth - t.offsetWidth) / 2, behavior: reduceMotion ? 'auto' : 'smooth' });
                }
            });
            restart();
        }

        function restart() {
            clearTimeout(timer);
            if (!reduceMotion && !slider.classList.contains('is-paused')) {
                timer = setTimeout(function () { go(current + 1); }, delay);
            }
        }

        function pause(on) {
            slider.classList.toggle('is-paused', on);
            if (on) { clearTimeout(timer); } else { restart(); }
        }

        tabs.forEach(function (t) {
            t.addEventListener('click', function () { go(parseInt(t.dataset.slideTo, 10)); });
        });
        slider.querySelector('[data-slide-prev]').addEventListener('click', function () { go(current - 1); });
        slider.querySelector('[data-slide-next]').addEventListener('click', function () { go(current + 1); });
        slider.addEventListener('mouseenter', function () { pause(true); });
        slider.addEventListener('mouseleave', function () { pause(false); });
        slider.addEventListener('focusin', function () { pause(true); });
        slider.addEventListener('focusout', function () { pause(false); });

        // Swipe on phones.
        var x0 = null;
        slider.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            if (x0 === null) { return; }
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 50) { go(current + (dx < 0 ? 1 : -1)); }
            x0 = null;
        }, { passive: true });

        if (reduceMotion) { slider.classList.add('is-paused'); }
        restart();
    });

    /* ---------- Enquiry forms are UI only until 7A.9 (leads) ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-demo-form]');
        if (!form) { return; }
        e.preventDefault();
        var msg = form.querySelector('.w-form-msg');
        if (msg) { msg.hidden = false; }
    });
})();
