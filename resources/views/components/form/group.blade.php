{{--
    Label + control + validation error + help text. Used by the other form components; use it directly for custom controls.
    <x-form.group label="Amount" for="amount" name="amount" required help="…"> … </x-form.group>
--}}
@props(['label' => null, 'for' => null, 'name' => null, 'required' => false, 'help' => null])

@php($errors ??= new \Illuminate\Support\ViewErrorBag)

@php($errorKey = $name ? str_replace(['[]', '[', ']'], ['', '.', ''], $name) : null)

<div {{ $attributes->class(['mb-3' => ! $attributes->has('class')]) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="form-label">
            {{ $label }}@if ($required) <span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($errorKey)
        @error($errorKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @endif

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
