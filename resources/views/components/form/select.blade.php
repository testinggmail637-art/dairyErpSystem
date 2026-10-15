{{--
    Select; searchable (Select2) with the `select2` attribute. Options: [value => label].
    <x-form.select name="section_id" label="Section" :options="$sections" :selected="$expense->section_id"
                   placeholder="Choose section" select2 required />
    Multiple: name="roles[]" multiple :selected="['admin']"
    Remote search: select2 data-ajax-url="{{ route('vendors.search') }}" — endpoint returns {"results": [{"id", "text"}]}
--}}
@props([
    'name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'required' => false,
    'help' => null, 'id' => null, 'multiple' => false, 'select2' => false, 'groupClass' => 'mb-3',
])

@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $id ??= str_replace('.', '_', $errorKey);
    $current = old($errorKey, $selected);
    $currentValues = array_map('strval', array_filter((array) $current, fn ($v) => $v !== null && $v !== ''));
    $hasError = $errors->has($errorKey) || $errors->has($errorKey.'.*');
@endphp

<x-form.group :label="$label" :for="$id" :name="$name" :required="$required" :help="$help" :class="$groupClass">
    <select id="{{ $id }}" name="{{ $name }}" @required($required) @if ($multiple) multiple @endif
            @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif
            {{ $attributes->class(['form-select', 'js-select2' => $select2, 'is-invalid' => $hasError]) }}>
        @if ($placeholder && ! $multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $currentValues, true))>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-form.group>
