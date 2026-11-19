{{-- Responsive WebP: $name (path without -<width>.webp), $widths, $sizes, $alt, optional $class, $ratio ([w, h]), $eager. --}}
@php
    $ratio ??= null;
    $eager ??= false;
    $srcset = collect($widths)->map(fn ($w) => asset("{$name}-{$w}.webp")." {$w}w")->implode(', ');
@endphp
<img src="{{ asset($name.'-'.$widths[0].'.webp') }}" srcset="{{ $srcset }}" sizes="{{ $sizes }}" alt="{{ $alt }}"
     @if ($ratio) width="{{ $ratio[0] }}" height="{{ $ratio[1] }}" @endif
     class="{{ $class ?? '' }}" decoding="async" @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
