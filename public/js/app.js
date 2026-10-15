/*
 * Dairy ERP — global JavaScript (loaded on every page after jQuery and the vendor libraries).
 * Page-specific code lives in public/js/modules/<module>.js, included via @push('scripts').
 *
 * Money and quantities are never calculated with JS floats for anything that is saved;
 * the server recalculates every amount. JS only formats values and shows live previews.
 */
(function ($) {
    'use strict';

    const App = (window.App = window.App || {});

    /* ---------- AJAX: send the CSRF token with every request ---------- */
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });

    /* ---------- Formatting ---------- */

    /**
     * Indian digit grouping on a decimal string, without float conversion.
     * App.formatMoney('125000') → '₹1,25,000.00'; App.formatMoney('-50.5', false) → '-50.50'
     */
    App.formatMoney = function (value, withSymbol = true) {
        let str = String(value ?? '0').trim().replace(/,/g, '');
        if (!/^-?\d*(\.\d*)?$/.test(str) || str === '' || str === '-') {
            str = '0';
        }
        const negative = str.startsWith('-');
        if (negative) str = str.slice(1);

        let [intPart, decPart = ''] = str.split('.');

        intPart = intPart.replace(/^0+(?=\d)/, '') || '0';
        decPart = (decPart + '00').slice(0, 2);

        const last3 = intPart.slice(-3);
        const rest = intPart.slice(0, -3);
        const grouped = rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;

        return (negative ? '-' : '') + (withSymbol ? '₹' : '') + grouped + '.' + decPart;
    };

    /** 'YYYY-MM-DD' → 'DD-MM-YYYY' (display format everywhere). */
    App.formatDate = function (isoDate) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(isoDate || ''));
        return m ? `${m[3]}-${m[2]}-${m[1]}` : '';
    };

    /** RFC 4122 v4 UUID for client_uuid idempotency fields. */
    App.uuid = function () {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = (crypto.getRandomValues(new Uint8Array(1))[0] & 15) >> 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    };

    /* ---------- Widgets ---------- */

    /** Initialise Select2, Flatpickr and client_uuid fields inside a container (document or a modal). */
    App.initWidgets = function (container) {
        const $root = $(container || document);

        if ($.fn.select2) {
            $root.find('select.js-select2').each(function () {
                const $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) return;
                const $modal = $el.closest('.modal');
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: !$el.prop('required'),
                    placeholder: $el.data('placeholder') || '',
                    dropdownParent: $modal.length ? $modal : $(document.body),
                });
            });
        }

        if (window.flatpickr) {
            $root.find('input.js-date').each(function () {
                if (this._flatpickr) return;
                flatpickr(this, {
                    dateFormat: 'Y-m-d', // value sent to the server
                    altInput: true,
                    altFormat: 'd-m-Y', // what the user sees
                    allowInput: true,
                    maxDate: this.dataset.maxDate || null,
                    minDate: this.dataset.minDate || null,
                });
            });
        }

        $root.find('input[data-client-uuid]').each(function () {
            if (!this.value) this.value = App.uuid();
        });
    };

    /* ---------- DataTables defaults ---------- */
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            processing: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            autoWidth: false,
            language: { emptyTable: 'No records found.' },
        });
    }

    /* ---------- Boot ---------- */
    $(function () {
        App.initWidgets(document);

        // Prevent double submits on normal (non-AJAX) forms.
        $(document).on('submit', 'form:not([data-allow-resubmit])', function () {
            const $btn = $(this).find('[type="submit"]');
            setTimeout(() => $btn.prop('disabled', true), 0);
        });
    });
})(jQuery);
