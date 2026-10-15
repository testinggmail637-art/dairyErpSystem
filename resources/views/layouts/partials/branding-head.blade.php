{{-- Appearance (spec 4.11): favicon, local fonts and the CSS variables that drive every color/font/radius.
     $appearance, $appearanceCss and $themeMode come from the view composer in AppServiceProvider. --}}
<link rel="icon" href="{{ branding_url($appearance['favicon_path']) ?? asset('favicon.ico') }}">
<link rel="stylesheet" href="{{ asset_v('vendor/fonts/fonts.css') }}">
<style id="appearance-vars">
{!! $appearanceCss !!}
</style>
@if ($themeMode === 'auto')
    <script>
        // Theme "auto": follow the device setting.
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        }
    </script>
@endif
