/*
 * Party payment form: show the purchase list for "paid" and the sale list for "received".
 * The server ignores the one that does not fit the direction.
 */
(function ($) {
    'use strict';

    const $form = $('#party-payment-form');
    if (!$form.length) return;

    function toggle() {
        const direction = $form.find('input[name="direction"]:checked').val();
        $form.find('[data-show-when]').each(function () {
            $(this).toggleClass('d-none', $(this).data('showWhen') !== direction);
        });
    }

    $form.on('change', 'input[name="direction"]', toggle);
    toggle();
})(jQuery);
