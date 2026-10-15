{{--
    Text-like input with label, old() value, error state, help and optional prefix/suffix.
    <x-form.input name="name" label="Name" :value="$farmer->name" required maxlength="100" />
    <x-form.input name="rate" label="Rate" suffix="₹/L" inputmode="decimal" />
--}}
@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false,
    'help' => null, 'prefix' => null, 'suffix' => null, 'id' => null, 'groupClass' => 'mb-3',
])

@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $id ??= str_replace('.', '_', $errorKey);
    $hasError = $errors->has($errorKey);
    $current = $type === 'password' ? null : old($errorKey, $value);
@endphp

<x-form.group :label="$label" :for="$id" :name="$name" :required="$required" :help="$help" :class="$groupClass">
    @if ($prefix || $suffix)<div class="input-group {{ $hasError ? 'has-validation' : '' }}">@endif
        @if ($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" @if ($current !== null) value="{{ $current }}" @endif
               @required($required)
               {{ $attributes->class(['form-control', 'is-invalid' => $hasError]) }}>
        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
    @if ($prefix || $suffix)</div>@endif
</x-form.group>
