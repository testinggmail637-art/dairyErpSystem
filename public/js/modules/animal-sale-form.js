/*
 * Animal sale form: add / remove animal rows (Select2 search of active animals) and live totals in integer paise.
 * The server validates and calculates everything again.
 */
(function ($) {
    'use strict';

    const $form = $('#sale-form');
    if (!$form.length) return;

    const $rows = $form.find('[data-rows]');
    const template = $form.find('[data-row-template]').html();
    let next = $rows.find('[data-row]').length;

    const paise = function (value) {
        const str = String(value || '').trim().replace(/,/g, '');
        if (!/^\d+(\.\d{0,2})?$/.test(str)) return 0n;
        const [int, dec = ''] = str.split('.');
        return BigInt(int) * 100n + BigInt((dec + '00').slice(0, 2));
    };
    const rupees = function (p) {
        const sign = p < 0n ? '-' : '';
        const a = p < 0n ? -p : p;
        return App.formatMoney(sign + (a / 100n).toString() + '.' + (a % 100n).toString().padStart(2, '0'));
    };

    function totals() {
        let price = 0n;
        $rows.find('[data-price]').each(function () { price += paise($(this).val()); });
        const costs = paise($form.find('[data-cost]').val());
        $form.find('[data-total="price"]').text(rupees(price));
        $form.find('[data-total="costs"]').text(rupees(costs));
        $form.find('[data-total="net"]').text(rupees(price - costs));
    }

    $form.on('click', '[data-add-row]', function () {
        const $row = $(template.replace(/__I__/g, String(next++)));
        $rows.append($row);
        App.initWidgets($row);
    });

    $form.on('click', '[data-remove-row]', function () {
        const $row = $(this).closest('[data-row]');
        if ($rows.find('[data-row]').length > 1) {
            $row.remove();
        } else {
            $row.find('input').val('');
            $row.find('select').val('').trigger('change');
        }
        totals();
    });

    $form.on('input', '[data-price], [data-cost]', totals);
    totals();
})(jQuery);
