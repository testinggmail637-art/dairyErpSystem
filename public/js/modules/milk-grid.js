/*
 * Milk entry grid: live totals per milk type and for the shift, and the reason box when a saved entry is changed.
 * Litres are added as whole hundredths (BigInt) — no float maths; the server recalculates everything.
 */
(function ($) {
    'use strict';

    // choosing another date reloads the grid for that date
    $('#milk-date-form').on('change', 'input[name="date"]', function () {
        if (this.value) this.form.submit();
    });

    const $grid = $('#milk-grid');
    if (!$grid.length) return;

    /** "8.5" → 850n; anything that is not a litre value → 0n. */
    function hundredths(value) {
        const m = /^(\d{1,6})(?:\.(\d{0,2}))?$/.exec(String(value || '').trim().replace(/,/g, ''));
        return m ? BigInt(m[1] + ((m[2] || '') + '00').slice(0, 2)) : 0n;
    }

    function litres(h) {
        const s = h.toString().padStart(3, '0');
        return App.formatMoney(s.slice(0, -2) + '.' + s.slice(-2), false);
    }

    function refresh() {
        const totals = { all: 0n };
        let changedSaved = false;

        $grid.find('input[data-milk-type]').each(function () {
            const h = hundredths(this.value);
            const type = this.dataset.milkType;
            totals[type] = (totals[type] || 0n) + h;
            totals.all += h;

            if (this.dataset.saved !== undefined && this.value.trim() !== '' && h !== hundredths(this.dataset.saved)) {
                changedSaved = true;
            }
        });

        $('[data-milk-total]').each(function () {
            this.textContent = litres(totals[this.dataset.milkTotal] || 0n);
        });
        $grid.find('[data-reason-box]').toggleClass('d-none', !changedSaved && !$grid.find('[name="reason"]').hasClass('is-invalid'));
    }

    $grid.on('input', 'input[data-milk-type]', refresh);

    // Enter moves to the next box (fast entry on phones and keyboards); the last Enter submits.
    $grid.on('keydown', 'input[data-milk-type]', function (e) {
        if (e.key !== 'Enter') return;
        const $boxes = $grid.find('input[data-milk-type]:not(:disabled)');
        const next = $boxes.get($boxes.index(this) + 1);
        if (next) {
            e.preventDefault();
            next.focus();
            next.select();
        }
    });

    refresh();
})(jQuery);
