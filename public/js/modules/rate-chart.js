/*
 * Rate charts: show the fields of the chosen method, add / remove slab rows (form), and the rate preview calculator
 * (detail page). All rates are worked out on the server; this file only shows them.
 */
(function ($) {
    'use strict';

    // ---- form ----
    const $form = $('#rate-chart-form');
    if ($form.length) {
        const toggle = function () {
            const method = $form.find('#method').val();
            $form.find('[data-method]').each(function () {
                $(this).toggleClass('d-none', $(this).data('method').split(' ').indexOf(method) === -1);
            });
        };
        $form.on('change', '#method', toggle);
        toggle();

        const $body = $('#slab-table tbody');
        const renumber = function () {
            $body.find('[data-slab-row]').each(function (i) {
                $(this).find('input').each(function () {
                    this.name = this.name.replace(/slabs\[\d+\]/, 'slabs[' + i + ']');
                });
            });
        };
        $form.on('click', '[data-add-slab]', function () {
            const $last = $body.find('[data-slab-row]').last();
            const $row = $last.clone();
            $row.find('input').val('').removeClass('is-invalid');
            $row.find('.invalid-feedback').remove();
            // next row starts where the last one ended
            $row.find('input[name$="[fat_from]"]').val($last.find('input[name$="[fat_to]"]').val());
            $body.append($row);
            renumber();
            $row.find('input').eq(1).trigger('focus');
        });
        $form.on('click', '[data-remove-slab]', function () {
            if ($body.find('[data-slab-row]').length > 1) {
                $(this).closest('[data-slab-row]').remove();
                renumber();
            } else {
                $(this).closest('[data-slab-row]').find('input').val('');
            }
        });
    }

    // ---- preview calculator ----
    const $preview = $('#rate-preview');
    if ($preview.length) {
        let timer = null;
        const show = function (key, text) {
            $('[data-preview="' + key + '"]').text(text);
        };
        const run = function () {
            $.getJSON($preview.data('url'), $preview.serialize())
                .done(function (r) {
                    show('rate', r.rate_text);
                    show('amount', r.amount_text || '—');
                    $('[data-preview="message"]').text(r.below_standard ? $preview.data('below') : '')
                        .toggleClass('text-warning', r.below_standard);
                })
                .fail(function (xhr) {
                    show('rate', '—');
                    show('amount', '—');
                    const r = xhr.responseJSON || {};
                    $('[data-preview="message"]').text(r.error || Object.values(r.errors || {}).flat().join(' ')).addClass('text-danger');
                });
        };
        $preview.on('input', 'input', function () {
            $('[data-preview="message"]').removeClass('text-danger text-warning');
            clearTimeout(timer);
            timer = setTimeout(run, 250);
        });
        $preview.on('submit', function (e) {
            e.preventDefault();
            run();
        });
        run();
    }
})(jQuery);
