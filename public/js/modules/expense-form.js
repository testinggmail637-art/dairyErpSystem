/*
 * Expense form: paid/credit toggle and the "add new vendor" modal.
 * The server validates everything again (credit needs a vendor, paid needs a payment mode).
 */
(function ($) {
    'use strict';

    const $form = $('#expense-form');
    if (!$form.length) return;

    function toggle() {
        const credit = $form.find('input[name="is_credit"]:checked').val() === '1';
        const hasVendor = !!$form.find('#vendor_id').val();

        $form.find('[data-show-when="paid"]').toggleClass('d-none', credit);
        $form.find('[data-show-when="paid-or-no-vendor"]').toggleClass('d-none', credit || hasVendor);
        $form.find('#payment_mode_id').prop('required', !credit);
        $form.find('#vendor_id').prop('required', credit);
    }

    $form.on('change', 'input[name="is_credit"], #vendor_id', toggle);
    toggle();

    // Add new vendor (modal) → create via AJAX, then select it in the vendor box.
    const $quick = $('#vendor-quick-form');
    $quick.on('submit', function (e) {
        e.preventDefault();
        const $errors = $quick.find('[data-errors]').addClass('d-none').empty();
        const $button = $quick.find('[type="submit"]').prop('disabled', true);

        $.post($quick.attr('action'), $quick.serialize())
            .done(function (vendor) {
                const option = new Option(vendor.text, vendor.id, true, true);
                $('#vendor_id').append(option).trigger('change');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('vendor-modal')).hide();
                $quick[0].reset();
            })
            .fail(function (xhr) {
                const errors = (xhr.responseJSON && xhr.responseJSON.errors) || { name: ['Could not add the vendor.'] };
                $errors.text(Object.values(errors).flat().join(' ')).removeClass('d-none');
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });
})(jQuery);
