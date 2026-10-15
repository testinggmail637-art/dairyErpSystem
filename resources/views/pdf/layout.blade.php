{{--
    Shared PDF layout (bills, farmer statements, receipts…). Dompdf does not support CSS variables, so the
    Settings → Appearance values ($appearance, from the view composer) are written into the styles here.
    Child views: @extends('pdf.layout') · @section('title') · @section('content').
--}}
@php
    $accent = $appearance['pdf_accent_color'];
    $onAccent = \App\Support\Color::readableTextOn($accent);
    $logoPath = $appearance['logo_path'] ? \Illuminate\Support\Facades\Storage::disk('public')->path($appearance['logo_path']) : null;
    $logoSrc = $logoPath && is_file($logoPath)
        ? (str_ends_with($logoPath, '.svg') ? $logoPath : 'data:'.mime_content_type($logoPath).';base64,'.base64_encode(file_get_contents($logoPath)))
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title') · {{ $appearance['app_name'] }}</title>
    <style>
        @page { margin: 110px 36px 70px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; } /* DejaVu has the ₹ glyph */
        header { position: fixed; top: -90px; left: 0; right: 0; height: 80px; border-bottom: 3px solid {{ $accent }}; }
        header table { width: 100%; border-collapse: collapse; }
        header .logo { max-height: 60px; max-width: 160px; }
        header .org { font-size: 15px; font-weight: bold; color: {{ $accent }}; }
        header .org-sub { font-size: 9.5px; color: #555; white-space: pre-line; }
        header .doc-title { text-align: right; font-size: 14px; font-weight: bold; }
        footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 40px; border-top: 1px solid #ccc;
                 font-size: 8.5px; color: #555; }
        footer .page:after { content: counter(page); }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: {{ $accent }}; color: {{ $onAccent }}; text-align: left; padding: 5px 6px; font-size: 9.5px; }
        table.data td { border-bottom: 1px solid #e3e3e3; padding: 5px 6px; }
        table.data tfoot td { font-weight: bold; border-top: 2px solid {{ $accent }}; }
        .text-end, table.data th.text-end, table.data td.text-end { text-align: right; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                @if ($logoSrc)
                    <td style="width: 170px; vertical-align: middle;"><img src="{{ $logoSrc }}" class="logo" alt=""></td>
                @endif
                <td style="vertical-align: middle;">
                    @php([$firstLine, $rest] = array_pad(explode("\n", $appearance['print_header_text'], 2), 2, ''))
                    <div class="org">{{ $firstLine }}</div>
                    @if (trim($rest) !== '')<div class="org-sub">{{ trim($rest) }}</div>@endif
                </td>
                <td class="doc-title" style="vertical-align: middle;">@yield('title')</td>
            </tr>
        </table>
    </header>

    <footer>
        <table style="width: 100%;">
            <tr>
                <td style="white-space: pre-line;">{{ $appearance['print_footer_text'] }}</td>
                <td class="text-end" style="width: 120px;">{{ fmt_datetime(now()) }} · {{ __('Page') }} <span class="page"></span></td>
            </tr>
        </table>
    </footer>

    <main>
        @yield('content')
    </main>
</body>
</html>
