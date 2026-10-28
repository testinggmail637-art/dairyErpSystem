/**
 * Milk stock sheet: picking a date opens that day's sheet.
 */
$(function () {
    $('#stock-date-form').on('change', 'input[name="date"]', function () {
        if (this.value) this.form.submit();
    });
});
