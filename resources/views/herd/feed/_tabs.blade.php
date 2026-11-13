{{-- Tabs of Herd → Feed. --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a href="{{ route('feed.index') }}" @class(['nav-link', 'active' => request()->routeIs('feed.index', 'feed.items.*')])>{{ __('Stock') }}</a></li>
    <li class="nav-item"><a href="{{ route('feed.transactions.index') }}" @class(['nav-link', 'active' => request()->routeIs('feed.transactions.*')])>{{ __('Entries') }}</a></li>
</ul>
