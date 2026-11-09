/*
 * Collection entry (spec Phase 4) — keyboard only: farmer code → Enter → litres → Enter → fat → Enter → SNF → Enter
 * saves; Esc clears. The rate and amount come from the server (collections.quote); saving posts JSON and reloads the
 * shift table. Below-standard milk asks: accept at the rate, or reject.
 */
(function ($) {
    'use strict';

    const $form = $('#collection-form');
    if (!$form.length) return;

    const t = $form.data('texts') || {};
    const $code = $('#farmer_code');
    const $farmerId = $form.find('[name="farmer_id"]');
    const $name = $form.find('[data-farmer-name]');
    const $choices = $form.find('[data-farmer-choices]');
    const $error = $form.find('[data-form-error]');
    const $below = $form.find('[data-below]');
    let quoteTimer = null;
    let saving = false;

    const showError = function (message) {
        if (message) $form.find('[data-form-success]').addClass('d-none');
        $error.text(message || '').toggleClass('d-none', !message);
    };

    const setQuote = function (rate, amount, chart) {
        $form.find('[data-quote="rate"]').text(rate || '—');
        $form.find('[data-quote="amount"]').text(amount || '—');
        $form.find('[data-quote="chart"]').text(chart || '');
    };

    const pickFarmer = function (farmer) {
        $farmerId.val(farmer.id);
        $code.val(farmer.text.split(' · ')[0]);
        $name.html('<span class="fw-semibold text-success"></span>').find('span').text(farmer.text);
        $('#milk_type').val(farmer.milk_type);
        $choices.addClass('d-none').empty();
        showError('');
        $('#quantity_litre').trigger('focus').select();
        quote();
    };

    const findFarmer = function () {
        const q = $.trim($code.val());
        if (!q) return;
        $.getJSON($form.data('search-url'), { q: q }).done(function (r) {
            const list = r.results || [];
            const exact = list.find(function (f) { return f.text.split(' · ')[0].toUpperCase() === q.toUpperCase(); });
            if (exact || list.length === 1) {
                pickFarmer(exact || list[0]);
            } else if (list.length === 0) {
                $farmerId.val('');
                $name.html('<span class="text-danger"></span>').find('span').text(t.notFound);
            } else {
                $choices.empty().removeClass('d-none');
                list.slice(0, 8).forEach(function (f) {
                    $('<button type="button" class="list-group-item list-group-item-action py-1"></button>').text(f.text)
                        .on('click', function () { pickFarmer(f); }).appendTo($choices);
                });
                $choices.find('button').first().trigger('focus');
            }
        });
    };

    const quote = function () {
        clearTimeout(quoteTimer);
        if (!$farmerId.val()) return;
        quoteTimer = setTimeout(function () {
            $.getJSON($form.data('quote-url'), {
                farmer_id: $farmerId.val(), milk_type: $('#milk_type').val(), collection_date: $form.find('[name="collection_date"]').val(),
                quantity_litre: $('#quantity_litre').val() || 0, fat: $('#fat').val(), snf: $('#snf').val(),
            }).done(function (r) {
                setQuote(r.rate_text, r.amount_text, r.chart + (r.below_standard ? ' · ' + t.below : ''));
                showError('');
            }).fail(function (xhr) {
                setQuote('', '', '');
                const r = xhr.responseJSON || {};
                showError(r.message);
            });
        }, 200);
    };

    const reset = function () {
        $form[0].reset();
        $farmerId.val('');
        $form.find('[name="accept_below"]').val('0');
        $form.find('[name="quality_status"]').val('accepted');
        $form.find('[data-client-uuid]').val(App.uuid());
        $form.find('[data-reject-box]').addClass('d-none');
        $below.addClass('d-none');
        $name.empty();
        $choices.addClass('d-none').empty();
        setQuote('', '', '');
        showError('');
        $code.trigger('focus');
    };

    const reloadTable = function () {
        $.ajax({ url: $form.data('results-url'), headers: { 'X-Ajax-List': '1', Accept: 'text/html' }, dataType: 'html' })
            .done(function (html) { $('#collection-results').html(html); });
    };

    const save = function () {
        if (saving) return;
        saving = true;
        $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), headers: { Accept: 'application/json' } })
            .done(function (r) {
                reset();
                $form.find('[data-form-success]').text(r.message).removeClass('d-none');
                reloadTable();
            })
            .fail(function (xhr) {
                const r = xhr.responseJSON || {};
                if (r.below_standard) {
                    $below.removeClass('d-none').find('[data-below-text]').text(r.message);
                    $below.find('[data-accept-below]').trigger('focus');
                    return;
                }
                showError(r.errors ? Object.values(r.errors).flat().join(' ') : (r.message || t.error));
            })
            .always(function () { saving = false; });
    };

    // Enter moves to the next field; on the last one it saves
    $form.on('keydown', 'input[data-next]', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        if (this.id === 'farmer_code') {
            findFarmer();
            return;
        }
        const next = $(this).data('next');
        next === 'submit' ? save() : $(next).trigger('focus').select();
    });
    $form.on('keydown', function (e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            reset();
        }
    });
    $code.on('input', function () {
        $farmerId.val('');
        $name.empty();
        setQuote('', '', '');
    });
    $form.on('input change', '#quantity_litre, #fat, #snf, #milk_type', quote);
    $form.on('submit', function (e) {
        e.preventDefault();
        save();
    });
    $form.on('reset', function (e) {
        e.preventDefault();
        reset();
    });

    // below standard → accept at the rate, or reject
    $form.on('click', '[data-accept-below]', function () {
        $form.find('[name="accept_below"]').val('1');
        save();
    });
    $form.on('click', '[data-reject]', function () {
        $form.find('[name="quality_status"]').val('rejected');
        $form.find('[name="rejection_reason"]').val($(this).data('reason'));
        save();
    });
    $form.on('click', '[data-show-reject]', function () {
        $form.find('[data-reject-box]').removeClass('d-none').find('input').trigger('focus');
    });
    $form.on('click', '[data-confirm-reject]', function () {
        $form.find('[name="quality_status"]').val('rejected');
        save();
    });
})(jQuery);
