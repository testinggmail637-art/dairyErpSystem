{{--
    Date picker (Flatpickr): shows DD-MM-YYYY, submits YYYY-MM-DD. Validate with 'date_format:Y-m-d'.
    <x-form.date name="date" label="Date" :value="$entry->date" max="today" required />
--}}
@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'id' => null, 'min' => null, 'max' => null, 'groupClass' => 'mb-3'])

@php
    $iso = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
@endphp

<x-form.input :name="$name" :label="$label" :value="$iso" :required="$required" :help="$help" :id="$id" :group-class="$groupClass"
              type="text" placeholder="DD-MM-YYYY" autocomplete="off"
              :data-min-date="$min" :data-max-date="$max"
              {{ $attributes->class(['js-date']) }} />
