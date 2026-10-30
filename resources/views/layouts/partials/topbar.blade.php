<header class="app-topbar">
    <button type="button" class="btn btn-link app-topbar-icon d-lg-none" data-bs-toggle="offcanvas"
            data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="{{ __('Open menu') }}">
        <i class="bi bi-list"></i>
    </button>

    {{-- Phones: the sidebar is a drawer, so the brand sits here. Mobile logo, else the logo, else the app name. --}}
    <a href="{{ route('home') }}" class="app-topbar-brand d-lg-none" data-testid="topbar-brand">
        @if ($appearance['logo_mobile_path'] || $appearance['logo_path'])
            <img src="{{ branding_url($appearance['logo_mobile_path'] ?: $appearance['logo_path']) }}" alt="{{ $appearance['app_name'] }}" class="app-topbar-logo">
        @else
            <span>{{ $appearance['app_name'] }}</span>
        @endif
    </a>

    @include('layouts.partials.farm-switcher')

    <div class="ms-auto d-flex align-items-center gap-1">
        {{-- Notifications --}}
        <div class="dropdown">
            <button type="button" class="btn btn-link app-topbar-icon position-relative" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="{{ __('Notifications') }}">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger app-badge-count"
                          data-testid="unread-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end app-notification-menu p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong>{{ __('Notifications') }}</strong>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Mark all read') }}</button>
                        </form>
                    @endif
                </div>
                @forelse ($recentNotifications as $notification)
                    <a href="{{ route('notifications.open', $notification->id) }}"
                       class="dropdown-item d-flex gap-2 py-2 text-wrap {{ $notification->read_at ? '' : 'fw-semibold' }}">
                        <i class="bi {{ $notification->data['icon'] ?? 'bi-info-circle' }} text-primary"></i>
                        <span>
                            {{ $notification->data['message'] ?? '' }}
                            <small class="d-block text-body-secondary fw-normal">{{ $notification->created_at->diffForHumans() }}</small>
                        </span>
                    </a>
                @empty
                    <div class="px-3 py-4 text-center text-body-secondary small">{{ __('No notifications.') }}</div>
                @endforelse
                <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small py-2 border-top">
                    {{ __('View all') }}
                </a>
            </div>
        </div>

        {{-- User menu --}}
        <div class="dropdown">
            <button type="button" class="btn btn-link app-topbar-user dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>{{ __('My profile') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
