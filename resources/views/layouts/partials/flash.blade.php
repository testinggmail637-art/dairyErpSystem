@php
    $flashTypes = [
        'success' => ['class' => 'success', 'icon' => 'bi-check-circle'],
        'error' => ['class' => 'danger', 'icon' => 'bi-x-circle'],
        'warning' => ['class' => 'warning', 'icon' => 'bi-exclamation-triangle'],
        'info' => ['class' => 'info', 'icon' => 'bi-info-circle'],
    ];
@endphp

@foreach ($flashTypes as $key => $type)
    @if (session()->has($key))
        <div class="alert alert-{{ $type['class'] }} alert-dismissible fade show d-flex gap-2" role="alert">
            <i class="bi {{ $type['icon'] }}"></i>
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show d-flex gap-2" role="alert">
        <i class="bi bi-x-circle"></i>
        <div>{{ __('Please correct the highlighted fields and try again.') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
@endif
