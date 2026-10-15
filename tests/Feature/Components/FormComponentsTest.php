<?php

use Illuminate\Support\Carbon;

/** Old form input as after a failed validation redirect (old() reads it from the request's session). */
function withOldInput(array $input): void
{
    $session = app('session')->driver();
    $session->put('_old_input', $input);
    request()->setLaravelSession($session);
}

it('renders an input with label, required mark, value and help', function () {
    $this->blade('<x-form.input name="name" label="Name" value="Ramu" required maxlength="100" help="Full name" />')
        ->assertSee('for="name"', false)
        ->assertSee('Name')
        ->assertSee('aria-hidden="true">*</span>', false)
        ->assertSee('value="Ramu"', false)
        ->assertSee('maxlength="100"', false)
        ->assertSee('required', false)
        ->assertSee('Full name');
});

it('shows the validation error and the old value, using dot keys for array names', function () {
    withOldInput(['settings' => ['bill_due_days' => '120']]);

    $this->withViewErrors(['settings.bill_due_days' => 'Too many days.'])
        ->blade('<x-form.input name="settings[bill_due_days]" label="Bill due" value="7" />')
        ->assertSee('id="settings_bill_due_days"', false)
        ->assertSee('is-invalid', false)
        ->assertSee('Too many days.')
        ->assertSee('value="120"', false);
});

it('never echoes a password back', function () {
    withOldInput(['password' => 'secret123']);

    $this->blade('<x-form.input name="password" type="password" label="Password" value="x" />')
        ->assertDontSee('secret123')
        ->assertDontSee('value="x"', false);
});

it('renders a money input with ₹ prefix, decimal keyboard and 2-decimal pattern', function () {
    $this->blade('<x-form.money name="amount" label="Amount" value="1250.50" required />')
        ->assertSee('<span class="input-group-text">₹</span>', false)
        ->assertSee('inputmode="decimal"', false)
        ->assertSee('data-money', false)
        ->assertSee('value="1250.50"', false)
        ->assertSee('pattern="\d+(\.\d{1,2})?"', false)
        ->assertSee('text-money', false);
});

it('renders a quantity input with its unit', function () {
    $this->blade('<x-form.quantity name="fat" label="Fat" unit="%" />')
        ->assertSee('<span class="input-group-text">%</span>', false)
        ->assertSee('inputmode="decimal"', false);
});

it('renders a date input that submits Y-m-d and shows DD-MM-YYYY', function () {
    $this->blade('<x-form.date name="date" label="Date" :value="$d" max="today" />', ['d' => Carbon::parse('2026-10-15')])
        ->assertSee('value="2026-10-15"', false)
        ->assertSee('js-date', false)
        ->assertSee('data-max-date="today"', false)
        ->assertSee('placeholder="DD-MM-YYYY"', false)
        ->assertDontSee('data-min-date', false);
});

it('renders a select with placeholder, selected value and Select2 class', function () {
    $this->blade(
        '<x-form.select name="mode" label="Mode" :options="$o" selected="2" placeholder="Choose" select2 required />',
        ['o' => [1 => 'Cash', 2 => 'UPI']],
    )
        ->assertSee('<option value="">Choose</option>', false)
        ->assertSee('<option value="2" selected>UPI</option>', false)
        ->assertSee('<option value="1" >Cash</option>', false)
        ->assertSee('js-select2', false)
        ->assertSee('data-placeholder="Choose"', false);
});

it('selects several values in a multiple select', function () {
    $this->blade('<x-form.select name="roles[]" :options="$o" :selected="[\'a\', \'c\']" multiple />', ['o' => ['a' => 'A', 'b' => 'B', 'c' => 'C']])
        ->assertSee('id="roles"', false)
        ->assertSee('<option value="a" selected>A</option>', false)
        ->assertSee('<option value="c" selected>C</option>', false)
        ->assertDontSee('<option value="b" selected>', false);
});

it('renders a switch that always submits a value', function () {
    $this->blade('<x-form.switch name="is_active" label="Active" :checked="true" />')
        ->assertSee('<input type="hidden" name="is_active" value="0">', false)
        ->assertSee('value="1" checked', false);
});

it('renders the reason field required with min length', function () {
    $this->blade('<x-form.reason />')
        ->assertSee('name="reason"', false)
        ->assertSee('minlength="3"', false)
        ->assertSee('required', false);
});

it('renders a confirm form with method spoofing and an optional reason prompt', function () {
    $this->blade('<x-confirm-form action="/roles/5" message="Delete this role?" reason />')
        ->assertSee('action="/roles/5"', false)
        ->assertSee('data-confirm="Delete this role?"', false)
        ->assertSee('data-confirm-reason', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('name="_token"', false);

    $this->blade('<x-confirm-form action="/bills/1/finalize" method="POST" message="Finalize?" label="Finalize" icon="bi-lock" />')
        ->assertDontSee('_method', false)
        ->assertDontSee('data-confirm-reason', false)
        ->assertSee('Finalize');
});

it('renders a server-side data table with its column config', function () {
    $this->blade('<x-data-table id="t" url="/data" :order="[[0, \'desc\']]" :columns="$c" />', ['c' => [
        ['data' => 'date', 'title' => 'Date'],
        ['data' => 'amount', 'title' => 'Amount', 'className' => 'text-end', 'orderable' => false],
    ]])
        ->assertSee('id="t"', false)
        ->assertSee('js-datatable', false)
        ->assertSee('data-url="/data"', false)
        ->assertSee('<th class="text-end">Amount</th>', false)
        ->assertSee('data-columns=\'[{"data":"date"},{"data":"amount","className":"text-end","orderable":false}]\'', false)
        ->assertSee('data-order=\'[[0,"desc"]]\'', false);
});

it('displays money in Indian format, negatives in the danger color', function () {
    $this->blade('<x-money value="125000" />')->assertSee('₹1,25,000.00');
    $this->blade('<x-money value="-50" />')->assertSee('text-danger', false)->assertSee('-₹50.00');
});
