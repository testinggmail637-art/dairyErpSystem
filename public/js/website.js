/* Public website (Phase 7A): day / night toggle and header shadow. Plain JS, no libraries. */
(function () {
    'use strict';

    var root = document.documentElement;
    var COOKIE = 'w_theme';

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

    var header = document.querySelector('.w-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
