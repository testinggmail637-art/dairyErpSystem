/*
 * Animal purchase form: add / remove animal rows, breed list by animal type, stages by gender, live totals.
 * Totals use integer paise (BigInt), never floats. The server validates and calculates everything again.
 */
(function ($) {
    'use strict';

    const $form = $('#purchase-form');
    if (!$form.length) return;

    const $rows = $form.find('[data-rows]');
    const template = $form.find('[data-row-template]').html();
    const stages = $form.data('stages') || {};
    let next = $rows.find('[data-row]').length;

    const paise = function (value) {
        const str = String(value || '').trim().replace(/,/g, '');
        if (!/^\d+(\.\d{0,2})?$/.test(str)) return 0n;
        const [int, dec = ''] = str.split('.');
        return BigInt(int) * 100n + BigInt((dec + '00').slice(0, 2));
    };
    const rupees = (p) => App.formatMoney((p / 100n).toString() + '.' + (p % 100n).toString().padStart(2, '0'));

    function totals() {
        let price = 0n;
        let costs = 0n;
        $rows.find('[data-price]').each(function () { price += paise($(this).val()); });
        $form.find('[data-cost]').each(function () { costs += paise($(this).val()); });
        $form.find('[data-total="price"]').text(rupees(price));
        $form.find('[data-total="costs"]').text(rupees(costs));
        $form.find('[data-total="all"]').text(rupees(price + costs));
    }

    // breeds of the row's animal type only; stages allowed for the row's gender only
    function syncRow($row) {
        const type = $row.find('[data-type]').val();
        const $breed = $row.find('[data-breed]');
        $breed.find('option[data-type]').each(function () {
            const ok = $(this).data('type') === type;
            $(this).prop('hidden', !ok).prop('disabled', !ok);
        });
        if ($breed.find('option:selected').prop('disabled')) $breed.val('');

        const allowed = stages[$row.find('[data-gender]').val()] || [];
        const $stage = $row.find('[data-stage]');
        $stage.find('option').each(function () {
            const ok = allowed.includes(this.value);
            $(this).prop('hidden', !ok).prop('disabled', !ok);
        });
        if ($stage.find('option:selected').prop('disabled')) $stage.val(allowed[0] || '');
    }

    $form.on('click', '[data-add-row]', function () {
        const $row = $(template.replace(/__I__/g, String(next++)));
        $rows.append($row);
        syncRow($row);
        $row.find('input').first().trigger('focus');
    });

    $form.on('click', '[data-remove-row]', function () {
        if ($rows.find('[data-row]').length > 1) {
            $(this).closest('[data-row]').remove();
        } else {
            $(this).closest('[data-row]').find('input').val('');
        }
        totals();
    });

    $form.on('change', '[data-type], [data-gender]', function () { syncRow($(this).closest('[data-row]')); });
    $form.on('input', '[data-price], [data-cost]', totals);

    $rows.find('[data-row]').each(function () { syncRow($(this)); });
    totals();
})(jQuery);
