/*
 * Animal form: stages offered by gender (males: calf/bull), opening value only for "existing at go-live",
 * lactation number only for females, and the mother box cleared when the animal type changes.
 * The server validates everything again.
 */
(function ($) {
    'use strict';

    const $form = $('#animal-form');
    if (!$form.length) return;

    const maleStages = ['calf', 'bull'];

    function toggle() {
        const male = $form.find('#gender').val() === 'male';
        const $stage = $form.find('#lifecycle_stage');

        $stage.find('option').each(function () {
            if (!this.value) return;
            const allowed = male ? maleStages.includes(this.value) : this.value !== 'bull';
            $(this).prop('disabled', !allowed).prop('hidden', !allowed);
        });
        if ($stage.find('option:selected').prop('disabled')) {
            $stage.val(male ? 'bull' : 'milking');
        }

        $form.find('[data-show-when="female"]').toggleClass('d-none', male);
        $form.find('[data-show-when="opening"]').toggleClass('d-none', $form.find('#source').val() !== 'opening');
    }

    $form.on('change', '#gender, #source', toggle);
    $form.on('change', '#animal_type', function () {
        $form.find('#mother_id').val(null).trigger('change');
    });
    toggle();
})(jQuery);
