{{-- Static on large screens, off-canvas drawer below lg. $menu comes from App\Support\Menu (view composer). --}}
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="app-sidebar-header">
        <a href="{{ route('home') }}" class="app-brand" id="appSidebarLabel">
            <i class="bi bi-droplet-half"></i>
            <span>{{ config('app.name') }}</span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#appSidebar" aria-label="{{ __('Close menu') }}"></button>
    </div>

    <nav class="app-sidebar-nav" aria-label="{{ __('Main menu') }}">
        <ul class="nav flex-column flex-nowrap">
            @forelse ($menu as $index => $entry)
                @if (isset($entry['items']))
                    <li class="nav-item">
                        <button type="button" class="nav-link app-nav-group {{ $entry['active'] ? '' : 'collapsed' }}"
                                data-bs-toggle="collapse" data-bs-target="#menu-group-{{ $index }}"
                                aria-expanded="{{ $entry['active'] ? 'true' : 'false' }}">
                            <i class="bi {{ $entry['icon'] }}"></i>
                            <span>{{ $entry['label'] }}</span>
                            <i class="bi bi-chevron-down app-nav-caret"></i>
                        </button>
                        {{-- Collapse on a plain wrapper: on the flex .nav list itself the items wrap sideways while animating. --}}
                        <div class="collapse {{ $entry['active'] ? 'show' : '' }}" id="menu-group-{{ $index }}">
                            <ul class="nav flex-column flex-nowrap">
                                @foreach ($entry['items'] as $item)
                                    <li class="nav-item">
                                        <a href="{{ $item['url'] }}" class="nav-link app-nav-sub {{ $item['active'] ? 'active' : '' }}"
                                           @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @else
                    <li class="nav-item">
                        <a href="{{ $entry['url'] }}" class="nav-link {{ $entry['active'] ? 'active' : '' }}"
                           @if ($entry['active']) aria-current="page" @endif>
                            <i class="bi {{ $entry['icon'] }}"></i>
                            <span>{{ $entry['label'] }}</span>
                        </a>
                    </li>
                @endif
            @empty
                <li class="nav-item app-nav-empty">{{ __('No modules available for your role yet.') }}</li>
            @endforelse
        </ul>
    </nav>
</aside>
