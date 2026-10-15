{{--
    On/off switch that always submits a value (0 when off).
    <x-form.switch name="is_active" label="Active" :checked="$row->is_active" />
--}}
@props(['name', 'label', 'checked' => false, 'help' => null, 'id' => null, 'groupClass' => 'mb-3'])

@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $id ??= str_replace('.', '_', $errorKey);
@endphp

<div class="{{ $groupClass }}">
    <div class="form-check form-switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked((bool) old($errorKey, $checked))
               {{ $attributes->class(['form-check-input', 'is-invalid' => $errors->has($errorKey)]) }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
        @error($errorKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
