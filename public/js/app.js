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

        // Round half up to 2 decimals with BigInt (same result as the PHP side, no float errors).
        const thousandths = BigInt((intPart || '0') + (decPart + '000').slice(0, 3));
        const cents = ((thousandths + 5n) / 10n).toString().padStart(3, '0');
        intPart = cents.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';
        decPart = cents.slice(-2);
        const isZero = /^0+$/.test(intPart + decPart);

        const last3 = intPart.slice(-3);
        const rest = intPart.slice(0, -3);
        const grouped = rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;

        return (negative && !isZero ? '-' : '') + (withSymbol ? '₹' : '') + grouped + '.' + decPart;
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
                const options = {
                    theme: 'bootstrap-5',
                    width: '100%',
                    allowClear: !$el.prop('required'),
                    placeholder: $el.data('placeholder') || '',
                    dropdownParent: $modal.length ? $modal : $(document.body),
                };
                // data-ajax-url: search on the server; endpoint gets ?q=…&page=… and returns {results: [{id, text}], pagination: {more}}.
                if ($el.data('ajaxUrl')) {
                    options.minimumInputLength = 1;
                    options.ajax = {
                        url: $el.data('ajaxUrl'),
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({ q: params.term, page: params.page || 1 }),
                    };
                }
                $el.select2(options);
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

        // <x-form.money>: live "₹1,25,000.00" preview under the field (display only; the raw value is submitted).
        $root.find('input[data-money]').each(function () {
            const $input = $(this);
            if ($input.data('moneyReady')) return;
            $input.data('moneyReady', true);

            const $preview = $('<div class="form-text text-money app-money-preview" aria-live="polite"></div>');
            $input.closest('.input-group').length ? $input.closest('.input-group').after($preview) : $input.after($preview);

            const update = () => {
                const raw = String($input.val() || '').trim();
                $preview.text(/^-?\d*(\.\d+)?$/.test(raw) && raw !== '' && raw !== '-' ? App.formatMoney(raw) : '');
            };
            $input.on('input', update);
            update();
        });

        // <x-data-table>: server-side when data-url is set, otherwise client-side over the existing rows.
        if ($.fn.dataTable) {
            $root.find('table.js-datatable').each(function () {
                if ($.fn.dataTable.isDataTable(this)) return;
                const $table = $(this);
                const options = {
                    columns: $table.data('columns') || undefined,
                    order: $table.data('order') || [],
                };
                if ($table.data('pageLength')) options.pageLength = $table.data('pageLength');
                if ($table.data('url')) {
                    options.serverSide = true;
                    options.ajax = { url: $table.data('url') };
                }
                $table.DataTable(options);
            });
        }
    };

    /** Reload a server-side <x-data-table> after an AJAX change: App.reloadTable('#expenses-table'). */
    App.reloadTable = function (selector) {
        if ($.fn.dataTable && $.fn.dataTable.isDataTable(selector)) {
            $(selector).DataTable().ajax.reload(null, false);
        }
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

        // <form data-confirm="Delete this?">: ask before submitting. Bound before the double-submit guard.
        // Add data-confirm-reason to also ask for a reason (min 3 chars), sent as a hidden "reason" field.
        $(document).on('submit', 'form[data-confirm]', function (e) {
            const form = this;
            if (form.dataset.confirmed === '1') return;

            e.preventDefault();
            e.stopImmediatePropagation();

            const askReason = form.hasAttribute('data-confirm-reason');

            Swal.fire({
                text: form.dataset.confirm,
                icon: 'warning',
                input: askReason ? 'textarea' : undefined,
                inputLabel: askReason ? 'Reason (saved in the audit log)' : undefined,
                inputAttributes: askReason ? { maxlength: 500 } : undefined,
                inputValidator: askReason
                    ? (value) => ((value || '').trim().length < 3 ? 'Please enter a reason (at least 3 characters).' : null)
                    : undefined,
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-outline-secondary ms-2', input: 'form-control' },
            }).then(function (result) {
                if (!result.isConfirmed) return;

                if (askReason) {
                    let input = form.querySelector('input[name="reason"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'reason';
                        form.appendChild(input);
                    }
                    input.value = result.value.trim();
                }

                form.dataset.confirmed = '1';
                form.requestSubmit();
            });
        });

        // Prevent double submits on normal (non-AJAX) forms.
        $(document).on('submit', 'form:not([data-allow-resubmit])', function () {
            const $btn = $(this).find('[type="submit"]');
            setTimeout(() => $btn.prop('disabled', true), 0);
        });
    });
})(jQuery);
