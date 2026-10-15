{{-- <x-form.textarea name="address" label="Address" :value="$farm->address" rows="3" maxlength="500" /> --}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'id' => null, 'rows' => 3, 'groupClass' => 'mb-3'])

@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $id ??= str_replace('.', '_', $errorKey);
@endphp

<x-form.group :label="$label" :for="$id" :name="$name" :required="$required" :help="$help" :class="$groupClass">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($errorKey)]) }}>{{ old($errorKey, $value) }}</textarea>
</x-form.group>
