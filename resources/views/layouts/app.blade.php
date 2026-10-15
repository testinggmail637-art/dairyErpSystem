<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2-bootstrap-5-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datatables/css/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    @stack('styles')
</head>
<body class="app-body">
    <a href="#main-content" class="visually-hidden-focusable">{{ __('Skip to content') }}</a>

    @include('layouts.partials.sidebar')

    <div class="app-main">
        @include('layouts.partials.topbar')

        <main id="main-content" class="app-content container-fluid">
            @hasSection('page-header')
                @yield('page-header')
            @else
                <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h1 class="h4 mb-0">@yield('title')</h1>
                    <div class="d-flex flex-wrap gap-2">@yield('page-actions')</div>
                </div>
            @endif

            @include('layouts.partials.flash')

            @yield('content')
        </main>
    </div>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset_v('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
