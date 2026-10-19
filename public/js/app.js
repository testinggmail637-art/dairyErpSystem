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
                // data-ajax-with="#animal_type,#gender": also send those fields' current values (by their name).
                if ($el.data('ajaxUrl')) {
                    options.minimumInputLength = 1;
                    options.ajax = {
                        url: $el.data('ajaxUrl'),
                        dataType: 'json',
                        delay: 250,
                        data: (params) => {
                            const data = { q: params.term, page: params.page || 1 };
                            String($el.data('ajaxWith') || '').split(',').filter(Boolean).forEach((selector) => {
                                const $field = $(selector.trim());
                                if ($field.length && $field.attr('name')) data[$field.attr('name')] = $field.val();
                            });
                            return data;
                        },
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

    /**
     * Chart.js colours from the Settings → Appearance CSS variables (never hard-coded):
     * new Chart(el, { data: { datasets: [{ backgroundColor: App.chartColors()[0] }] } })
     */
    App.chartColors = function () {
        const css = getComputedStyle(document.documentElement);
        return ['primary', 'success', 'warning', 'danger', 'secondary']
            .map((name) => css.getPropertyValue('--bs-' + name).trim())
            .filter(Boolean);
    };

    if (window.Chart) {
        const css = getComputedStyle(document.documentElement);
        Chart.defaults.font.family = css.getPropertyValue('--bs-body-font-family').trim() || Chart.defaults.font.family;
        Chart.defaults.color = css.getPropertyValue('--bs-body-color').trim() || Chart.defaults.color;
        Chart.defaults.borderColor = css.getPropertyValue('--bs-border-color').trim() || Chart.defaults.borderColor;
    }

    /**
     * Dependent dropdowns: <select data-children-of="#category_id" data-children-url="/expenses/categories/__ID__/children">
     * reloads its options ({"id": "name"} JSON) whenever the parent select changes; empty parent → only the placeholder.
     */
    $(document).on('change', 'select', function () {
        const parentId = this.id;
        if (!parentId) return;
        const value = this.value;
        $('select[data-children-of="#' + parentId + '"]').each(function () {
            const $child = $(this);
            const placeholder = $child.find('option[value=""]').first().text() || '';
            $child.empty().append(new Option(placeholder, ''));
            if (!value) {
                $child.prop('disabled', true).trigger('change');
                return;
            }
            $.getJSON($child.data('childrenUrl').replace('__ID__', encodeURIComponent(value))).done(function (items) {
                Object.entries(items).forEach(([id, name]) => $child.append(new Option(name, id)));
                $child.prop('disabled', Object.keys(items).length === 0);
            });
        });
    });

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

    /* ---------- AJAX lists: filter / search / paginate without reloading the page ----------
     * <form method="GET" data-ajax-list="#results"> + <div id="results" data-ajax-results> around the
     * @fragment('results') of the view. The controller returns only that fragment when it sees X-Ajax-List.
     * The URL is kept in sync (history.pushState, empty filters dropped) so refresh, Back and shared links work.
     */
    App.ajaxList = (function () {
        let pending = null;

        /** "?a=1&b=&c=x" → "?a=1&c=x" (and no "page" when filters change). */
        function cleanUrl(action, params, keepPage) {
            const url = new URL(action, window.location.href);
            const query = new URLSearchParams();
            params.forEach(([key, value]) => {
                if (String(value).trim() !== '' && (keepPage || key !== 'page')) query.append(key, value);
            });
            url.search = query.toString();
            return url.toString();
        }

        function load(target, url, push = true) {
            const $target = $(target);
            if (!$target.length) {
                window.location.href = url;
                return;
            }
            if (pending) pending.abort();
            $target.addClass('app-loading').attr('aria-busy', 'true');

            pending = $.ajax({ url: url, headers: { 'X-Ajax-List': '1', Accept: 'text/html' }, dataType: 'html' })
                .done(function (html, _status, xhr) {
                    // A redirect (e.g. invalid filter) cannot be shown as a fragment: let the browser follow it.
                    if (xhr.getResponseHeader('X-Ajax-List-Fragment') !== '1') {
                        window.location.href = url;
                        return;
                    }
                    $target.html(html);
                    App.initWidgets($target);
                    if (push) history.pushState({ ajaxList: target }, '', url);
                })
                .fail(function (xhr) {
                    if (xhr.statusText !== 'abort') window.location.href = url;
                })
                .always(function () {
                    $target.removeClass('app-loading').removeAttr('aria-busy');
                });
        }

        function submit(form, keepPage) {
            const params = $(form).serializeArray().map((f) => [f.name, f.value]);
            load($(form).data('ajaxList'), cleanUrl(form.action, params, keepPage));
        }

        $(document).on('submit', 'form[data-ajax-list]', function (e) {
            e.preventDefault();
            submit(this, false);
        });

        // Selects and dates filter straight away; search boxes after a short pause while typing.
        let typingTimer = null;
        $(document).on('change', 'form[data-ajax-list] select, form[data-ajax-list] input.js-date', function () {
            submit(this.form, false);
        });
        $(document).on('input', 'form[data-ajax-list] input[type="search"]', function () {
            const form = this.form;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => submit(form, false), 400);
        });

        $(document).on('click', 'form[data-ajax-list] [data-ajax-clear]', function (e) {
            e.preventDefault();
            const form = $(this).closest('form')[0];
            form.reset();
            $(form).find('input:not([type=hidden]):not([type=submit]), select').val('').trigger('change.select2');
            $(form).find('input[type=hidden][data-clearable]').remove(); // e.g. "expenses of import #5"
            $(form).find('input.js-date').each(function () {
                if (this._flatpickr) this._flatpickr.clear(false);
            });
            load($(form).data('ajaxList'), this.href);
        });

        $(document).on('click', '[data-ajax-results] .pagination a', function (e) {
            e.preventDefault();
            const target = '#' + $(this).closest('[data-ajax-results]').attr('id');
            load(target, this.href);
            const top = $(target).offset();
            if (top && top.top < window.scrollY) window.scrollTo({ top: Math.max(0, top.top - 80), behavior: 'smooth' });
        });

        window.addEventListener('popstate', function (event) {
            if (event.state && event.state.ajaxList) {
                load(event.state.ajaxList, window.location.href, false);
            }
        });

        // Remember the first page too, so Back from a filtered list returns to it.
        $(function () {
            const $list = $('form[data-ajax-list]').first();
            if ($list.length) history.replaceState({ ajaxList: $list.data('ajaxList') }, '', window.location.href);
        });

        return { load: load };
    })();

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
