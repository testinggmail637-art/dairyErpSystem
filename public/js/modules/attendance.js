/*
 * Attendance bulk screen: "All present / All holiday" sets every row; changing the date opens that day.
 */
(function ($) {
    'use strict';

    const $form = $('#attendance-form');
    if (!$form.length) return;

    $form.on('click', '[data-mark-all]', function () {
        $form.find('input[data-status="' + $(this).data('markAll') + '"]').prop('checked', true);
    });

    $('#attendance-date-form').on('change', 'input[name="date"]', function () {
        if (this.value) $(this).closest('form').trigger('submit');
    });
})(jQuery);
