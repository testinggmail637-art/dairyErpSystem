{{--
    Quantity input (litres / kg / Fat % / SNF %): numeric keyboard, max 2 decimals, unit suffix.
    Validate on the server with 'decimal:0,2'.
    <x-form.quantity name="quantity" label="Milk" unit="L" required />
--}}
@props(['name', 'label' => null, 'value' => null, 'unit' => 'L', 'required' => false, 'help' => null, 'id' => null, 'groupClass' => 'mb-3'])

<x-form.input :name="$name" :label="$label" :value="$value" :required="$required" :help="$help" :id="$id" :group-class="$groupClass"
              type="text" :suffix="$unit" inputmode="decimal" autocomplete="off" pattern="\d+(\.\d{1,2})?"
              :title="__('Number with up to 2 decimals, e.g. 12.50')"
              {{ $attributes->class(['text-end', 'text-qty']) }} />
