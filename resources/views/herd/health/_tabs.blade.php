{{-- Tabs of Herd → Health. --}}
<ul class="nav nav-tabs mb-3">
    @foreach ([
        'health.treatments.index' => [__('Treatments'), 'health.treatments.*'],
        'health.vaccinations.index' => [__('Vaccinations'), 'health.vaccinations.*'],
        'health.vaccine-types.index' => [__('Vaccines'), 'health.vaccine-types.*'],
    ] as $route => [$label, $active])
        <li class="nav-item"><a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($active)])>{{ $label }}</a></li>
    @endforeach
</ul>
