{{-- Display an amount: <x-money :value="$bill->total" /> → ₹1,25,000.00 (right-aligned digits, negative in danger color). --}}
@props(['value' => null, 'symbol' => true])

@php($text = inr($value, $symbol))

<span {{ $attributes->class(['text-money', 'text-danger' => str_starts_with($text, '-')]) }}>{{ $text }}</span>
