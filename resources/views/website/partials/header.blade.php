@php
    use App\Support\Website;

    $main = ['home', 'products', 'home-delivery', 'our-herd', 'quality', 'about', 'contact'];
    $more = ['bulk-orders', 'farmers', 'blog', 'faq'];
    $other = app()->getLocale() === 'hi' ? 'en' : 'hi';
    $logo = Website::get('logo');
    $logoDark = Website::get('logo_dark');
@endphp
<header class="w-header" id="top">
    <nav class="w-nav" aria-label="{{ __('website.action.menu') }}">
        <div class="container d-flex align-items-center gap-2">
            <a class="w-brand me-auto me-xl-4" href="{{ Website::route('home') }}" aria-label="{{ Website::get('site_name') }}">
                <img class="w-logo w-logo-light" src="{{ asset($logo.'-400.webp') }}" srcset="{{ asset($logo.'-400.webp') }} 400w, {{ asset($logo.'-640.webp') }} 640w" sizes="(min-width: 992px) 200px, 160px" width="400" height="123" alt="{{ Website::get('site_name') }}">
                <img class="w-logo w-logo-dark" src="{{ asset($logoDark.'-400.webp') }}" srcset="{{ asset($logoDark.'-400.webp') }} 400w, {{ asset($logoDark.'-640.webp') }} 640w" sizes="(min-width: 992px) 200px, 160px" width="400" height="123" alt="{{ Website::get('site_name') }}">
            </a>

            <ul class="w-menu d-none d-xl-flex">
                @foreach ($main as $item)
                    <li><a href="{{ Website::route($item) }}" @class(['active' => $page === $item]) @if ($page === $item) aria-current="page" @endif>{{ __("website.nav.$item") }}</a></li>
                @endforeach
                <li class="dropdown">
                    <button type="button" class="w-menu-more" data-bs-toggle="dropdown" aria-expanded="false">{{ __('website.nav.more') }} @include('website.icons.chevron-down')</button>
                    <ul class="dropdown-menu dropdown-menu-end w-dropdown">
                        @foreach ($more as $item)
                            <li><a class="dropdown-item @if ($page === $item) active @endif" href="{{ Website::route($item) }}">{{ __("website.nav.$item") }}</a></li>
                        @endforeach
                    </ul>
                </li>
            </ul>

            <a class="w-icon-btn w-lang" href="{{ $alternates[$other] }}" hreflang="{{ $other }}" lang="{{ $other }}" title="{{ __('website.action.language') }}" aria-label="{{ __('website.action.language') }}">{{ __('website.action.language_short') }}</a>
            <button type="button" class="w-icon-btn" data-theme-toggle title="{{ __('website.action.theme') }}" aria-label="{{ __('website.action.theme') }}">
                <span class="w-when-light">@include('website.icons.moon-stars-fill')</span>
                <span class="w-when-dark">@include('website.icons.sun-fill')</span>
            </button>
            <a class="w-btn w-btn-accent d-none d-md-inline-flex" href="{{ Website::route('contact') }}#enquiry">{{ __('website.action.enquiry') }}</a>
            <button type="button" class="w-icon-btn d-xl-none" data-bs-toggle="offcanvas" data-bs-target="#w-offcanvas" aria-controls="w-offcanvas" aria-label="{{ __('website.action.menu') }}">@include('website.icons.list')</button>
        </div>
    </nav>
</header>

<div class="offcanvas offcanvas-end w-offcanvas" tabindex="-1" id="w-offcanvas" aria-label="{{ __('website.action.menu') }}">
    <div class="offcanvas-header">
        <img class="w-logo w-logo-light" src="{{ asset($logo.'-400.webp') }}" width="400" height="123" alt="" loading="lazy">
        <img class="w-logo w-logo-dark" src="{{ asset($logoDark.'-400.webp') }}" width="400" height="123" alt="" loading="lazy">
        <button type="button" class="w-icon-btn ms-auto" data-bs-dismiss="offcanvas" aria-label="{{ __('website.action.close') }}">@include('website.icons.x-lg')</button>
    </div>
    <div class="offcanvas-body">
        <ul class="w-offcanvas-menu">
            @foreach (array_merge($main, $more) as $item)
                <li><a href="{{ Website::route($item) }}" @class(['active' => $page === $item]) @if ($page === $item) aria-current="page" @endif>{{ __("website.nav.$item") }} @include('website.icons.arrow-right')</a></li>
            @endforeach
        </ul>
        <div class="d-grid gap-2 mt-4">
            <a class="w-btn w-btn-accent" href="{{ Website::route('contact') }}#enquiry">{{ __('website.action.enquiry') }}</a>
            <a class="w-btn w-btn-outline" href="{{ $alternates[$other] }}" hreflang="{{ $other }}" lang="{{ $other }}">@include('website.icons.translate') {{ __('website.action.language') }}</a>
        </div>
        <div class="d-flex gap-3 mt-4 w-offcanvas-social">@include('website.partials.social')</div>
    </div>
</div>
