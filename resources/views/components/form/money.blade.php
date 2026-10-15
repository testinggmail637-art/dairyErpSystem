{{--
    Money input: ₹ prefix, numeric keyboard, max 2 decimals, live Indian-format preview (₹1,25,000.00) under the field.
    The raw value (e.g. 125000.50) is submitted; validate on the server with 'decimal:0,2'.
    <x-form.money name="amount" label="Amount" :value="$expense->amount" required />
--}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'id' => null, 'groupClass' => 'mb-3', 'allowNegative' => false])

<x-form.input :name="$name" :label="$label" :value="$value" :required="$required" :help="$help" :id="$id" :group-class="$groupClass"
              type="text" prefix="₹" inputmode="decimal" autocomplete="off" data-money
              :pattern="$allowNegative ? '-?\d+(\.\d{1,2})?' : '\d+(\.\d{1,2})?'"
              :title="__('Amount in rupees, up to 2 decimals, e.g. 1250.50')"
              {{ $attributes->class(['text-end', 'text-money']) }} />
