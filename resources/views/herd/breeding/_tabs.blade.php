{{-- Tabs of Herd → Breeding. --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a href="{{ route('breeding.index') }}" @class(['nav-link', 'active' => request()->routeIs('breeding.index', 'breeding.create', 'breeding.edit')])>{{ __('Events') }}</a></li>
    <li class="nav-item"><a href="{{ route('breeding.calvings.index') }}" @class(['nav-link', 'active' => request()->routeIs('breeding.calvings.*')])>{{ __('Calvings') }}</a></li>
</ul>
