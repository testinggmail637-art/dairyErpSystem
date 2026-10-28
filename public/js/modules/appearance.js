/*
 * Settings → Appearance: live preview, colour pickers, presets and a contrast hint.
 * Mirrors App\Services\AppearanceService::cssVariables() closely enough for a preview; the server is the source of truth.
 */
(function ($) {
    'use strict';

    const $form = $('#appearance-form');
    const preview = document.getElementById('appearance-preview');
    if (!$form.length || !preview) return;

    const HEX = /^#[0-9A-Fa-f]{6}$/;
    const STATUS = ['primary', 'secondary', 'success', 'danger', 'warning'];

    /* ---------- colour maths (display only) ---------- */
    const rgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16));
    const toHex = (r, g, b) => '#' + [r, g, b].map((c) => Math.max(0, Math.min(255, Math.round(c))).toString(16).padStart(2, '0')).join('').toUpperCase();
    const mix = (hex, withHex, weight) => {
        const a = rgb(hex);
        const b = rgb(withHex);
        return toHex(...a.map((c, i) => c + (b[i] - c) * weight));
    };
    const luminance = (hex) => {
        const c = rgb(hex).map((v) => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    };
    const contrast = (a, b) => {
        const l1 = luminance(a);
        const l2 = luminance(b);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    };
    const readable = (bg) => (contrast('#FFFFFF', bg) >= contrast('#000000', bg) ? '#FFFFFF' : '#000000');
    const shade = (hex, amount) => mix(hex, '#000000', amount); // close to the server's −10 % lightness

    const value = (name) => String($form.find('[name="' + name + '"]').val() || '').trim();
    const color = (name, fallback) => {
        const v = value(name).toUpperCase();
        return HEX.test(v) ? v : fallback;
    };

    /* ---------- preview ---------- */
    function update() {
        const s = preview.style;
        const dark = (value('theme_mode') === 'dark')
            || (value('theme_mode') === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        preview.setAttribute('data-bs-theme', dark ? 'dark' : 'light');

        STATUS.forEach((name) => {
            const hex = color(name + '_color', getComputedStyle(document.documentElement).getPropertyValue('--bs-' + name).trim());
            if (!HEX.test(hex)) return;
            s.setProperty('--bs-' + name, hex);
            s.setProperty('--bs-' + name + '-rgb', rgb(hex).join(', '));
            s.setProperty('--app-' + name + '-hover', shade(hex, 0.15));
            s.setProperty('--app-' + name + '-active', shade(hex, 0.22));
            s.setProperty('--app-on-' + name, readable(hex));
            s.setProperty('--bs-' + name + '-text-emphasis', dark ? mix(hex, '#FFFFFF', 0.4) : mix(hex, '#000000', 0.6));
            s.setProperty('--bs-' + name + '-bg-subtle', dark ? mix(hex, '#000000', 0.8) : mix(hex, '#FFFFFF', 0.8));
            s.setProperty('--bs-' + name + '-border-subtle', dark ? mix(hex, '#000000', 0.4) : mix(hex, '#FFFFFF', 0.6));
        });

        s.setProperty('--bs-link-color', 'var(--bs-primary)');
        s.setProperty('--bs-link-color-rgb', 'var(--bs-primary-rgb)');
        s.setProperty('--sidebar-bg', color('sidebar_bg', '#000000'));
        s.setProperty('--sidebar-text', color('sidebar_text', '#FFFFFF'));

        if (dark) {
            s.removeProperty('--bs-body-bg');
            s.setProperty('--topbar-bg', 'var(--bs-tertiary-bg)');
            s.setProperty('--app-card-bg', 'var(--bs-tertiary-bg)');
            // Same rule as the server: a light sidebar follows the dark surfaces.
            if (readable(color('sidebar_bg', '#000000')) !== '#FFFFFF') {
                s.setProperty('--sidebar-bg', 'var(--bs-tertiary-bg)');
                s.setProperty('--sidebar-text', 'var(--bs-body-color)');
            }
        } else {
            s.setProperty('--bs-body-bg', color('body_bg', '#FFFFFF'));
            s.setProperty('--topbar-bg', color('topbar_bg', '#FFFFFF'));
            s.setProperty('--app-card-bg', color('card_bg', '#FFFFFF'));
        }

        s.setProperty('--bs-body-font-family', (window.AppearanceFonts || {})[value('font_family')] || '');
        s.setProperty('--bs-body-font-size', (parseInt(value('base_font_size'), 10) || 14) + 'px');
        const radius = Math.max(0, Math.min(12, parseInt(value('border_radius'), 10) || 0));
        s.setProperty('--bs-border-radius', radius + 'px');
        s.setProperty('--bs-border-radius-sm', Math.max(0, radius - 2) + 'px');
        s.setProperty('--bs-border-radius-lg', radius + 2 + 'px');
        const compact = value('table_density') === 'compact';
        s.setProperty('--app-table-cell-py', compact ? '0.3rem' : '0.55rem');
        s.setProperty('--app-table-cell-px', compact ? '0.4rem' : '0.6rem');

        $(preview).find('[data-preview-text="app_name"]').text(value('app_name') || 'Dairy ERP');

        contrastHint();
    }

    function contrastHint() {
        const problems = [];
        const check = (label, fg, bg) => {
            const ratio = contrast(fg, bg);
            if (ratio < 4.5) problems.push(label + ' (' + ratio.toFixed(2) + ':1)');
        };
        check('Sidebar text on sidebar', color('sidebar_text', '#FFFFFF'), color('sidebar_bg', '#000000'));
        const body = color('body_bg', '#FFFFFF');
        check('Page text on page background', readable(body) === '#FFFFFF' ? '#FFFFFF' : '#212529', body);
        const primary = color('primary_color', '#000000');
        check('Button text on primary', readable(primary), primary);

        const $hint = $('#contrast-hint');
        if (problems.length) {
            $hint.text('Hard to read (contrast below 4.5:1): ' + problems.join(', ') + '. You can still save.').removeClass('d-none');
        } else {
            $hint.addClass('d-none').text('');
        }
    }

    /* ---------- colour pickers <-> text fields ---------- */
    $form.on('input change', 'input[data-color-for]', function () {
        $form.find('[data-color="' + $(this).data('colorFor') + '"]').val(this.value.toUpperCase()).trigger('input');
    });
    $form.on('input', 'input[data-color]', function () {
        const v = this.value.trim().toUpperCase();
        if (HEX.test(v)) $form.find('[data-color-for="' + $(this).data('color') + '"]').val(v);
        $(this).toggleClass('is-invalid', v !== '' && !HEX.test(v));
    });

    /* ---------- presets ---------- */
    $('[data-preset]').each(function () {
        $(this).find('[data-swatch]').each(function () {
            this.style.backgroundColor = this.dataset.swatch;
        });
    }).on('click', function () {
        const preset = $(this).data('preset') || {};
        Object.keys(preset).forEach((key) => {
            $form.find('[data-color="' + key + '"]').val(preset[key]).trigger('input');
        });
        update();
    });

    $form.on('input change', 'input, select', update);
    update();
})(jQuery);
