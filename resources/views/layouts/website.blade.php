@php
    use App\Support\Website;

    $locale = app()->getLocale();
    $themePref = Website::theme(request()->cookie(Website::THEME_COOKIE));
    $theme = $themePref === 'auto' ? 'light' : $themePref;
    $colors = Website::settings()['colors'];
    $siteName = Website::get('site_name');
    $alternates = Website::alternateUrls();
    $page = request()->route()?->defaults['page'] ?? 'home';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" data-theme="{{ $theme }}" data-bs-theme="{{ $theme }}" data-theme-pref="{{ $themePref }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim($__env->yieldContent('title')) !== '' ? $__env->yieldContent('title').' · '.$siteName : $siteName.' — '.Website::get('tagline') }}</title>
    <meta name="description" content="@yield('description', __('website.meta_description'))">
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach ($alternates as $altLocale => $altUrl)
        <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[Website::DEFAULT_LOCALE] }}">
    <meta name="theme-color" content="{{ $colors[$theme]['primary'] }}">
    {{-- Lets CSS hide scroll-reveal content only when JS runs (no JS = everything visible). --}}
    <script>document.documentElement.classList.add('w-js');</script>
    @if ($themePref === 'auto')
        {{-- Follow the device theme before first paint (no flash) until the visitor picks one. --}}
        <script>if (matchMedia('(prefers-color-scheme: dark)').matches) { document.documentElement.dataset.theme = 'dark'; document.documentElement.dataset.bsTheme = 'dark'; }</script>
    @endif

    <link rel="icon" href="{{ asset(Website::get('favicon')) }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset(Website::get('icon_192')) }}">
    <link rel="preload" href="{{ asset('vendor/fonts/mukta/mukta-'.($locale === 'hi' ? 'devanagari' : 'latin').'-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/website.css') }}">
    <style>
        :root, [data-theme="light"] { @foreach ($colors['light'] as $k => $v) --w-{{ str_replace('_', '-', $k) }}: {{ $v }}; @endforeach }
        [data-theme="dark"] { @foreach ($colors['dark'] as $k => $v) --w-{{ str_replace('_', '-', $k) }}: {{ $v }}; @endforeach }
    </style>
    @stack('head')
</head>
<body class="w-body" data-page="{{ $page }}">
    <a href="#main" class="visually-hidden-focusable w-skip">{{ __('website.skip') }}</a>

    @include('website.partials.header', ['page' => $page, 'alternates' => $alternates])

    <main id="main">
        @yield('content')
    </main>

    @include('website.partials.footer')
    @include('website.partials.contact-bar')

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset_v('js/website.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
