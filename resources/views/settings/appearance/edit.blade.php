@extends('layouts.app')

@section('title', __('Appearance'))

@php
    $colorFields = [
        'primary_color' => __('Primary (buttons, links, active menu)'),
        'secondary_color' => __('Secondary'),
        'success_color' => __('Success (paid, delivered)'),
        'danger_color' => __('Danger (errors, delete)'),
        'warning_color' => __('Warning (low stock, due)'),
        'sidebar_bg' => __('Sidebar background'),
        'sidebar_text' => __('Sidebar text'),
        'topbar_bg' => __('Top bar background'),
        'body_bg' => __('Page background'),
        'card_bg' => __('Card background (panels, tables, forms)'),
    ];
    $uploads = [
        'logo' => [__('Logo'), 'logo_path', 'PNG, JPG, SVG or WEBP, max 2 MB (resized to 400 px wide)', 'image/png,image/jpeg,image/svg+xml,image/webp'],
        'logo_dark' => [__('Logo for dark backgrounds'), 'logo_dark_path', 'Optional. Used on a dark sidebar and in the dark theme; falls back to the logo', 'image/png,image/jpeg,image/svg+xml,image/webp'],
        'logo_mobile' => [__('Mobile logo'), 'logo_mobile_path', 'Optional. Small or square image shown in the top bar on phones (light theme); falls back to the logo', 'image/png,image/jpeg,image/svg+xml,image/webp'],
        'logo_mobile_dark' => [__('Mobile logo for dark backgrounds'), 'logo_mobile_dark_path', 'Optional. Used on phones in the dark theme; falls back to the mobile logo, then the dark logo', 'image/png,image/jpeg,image/svg+xml,image/webp'],
        'favicon' => [__('Browser tab icon (favicon)'), 'favicon_path', 'PNG or ICO, max 512 KB (made 64×64)', 'image/png,image/x-icon,.ico'],
    ];
@endphp

@section('page-actions')
    <a href="{{ route('settings.appearance.sample-pdf') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Sample PDF') }}
    </a>
    <x-confirm-form :action="route('settings.appearance.reset')" method="POST" :message="__('Reset all appearance settings and remove the uploaded logos?')"
                    :label="__('Reset to defaults')" icon="bi-arrow-counterclockwise" :confirm-button="__('Reset')" />
@endsection

@section('content')
@foreach ($warnings as $warning)
    <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ $warning }}</div>
@endforeach

<div class="row g-3">
    {{-- ===================== Form ===================== --}}
    <div class="col-12 col-xl-7">
        <form method="POST" action="{{ route('settings.appearance.update') }}" enctype="multipart/form-data" id="appearance-form">
            @csrf
            @method('PUT')

            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-palette2 me-1"></i>{{ __('Presets') }}</div>
                <div class="card-body">
                    <div class="preset-grid">
                        @foreach ($presets as $key => $preset)
                            {{-- the thumbnail is drawn in the preset's own colours (data, not hard-coded styling) --}}
                            <button type="button" class="preset-card" aria-pressed="false"
                                    data-preset='@json(\Illuminate\Support\Arr::except($preset, ['label', 'description']))'
                                    style="--pv-sidebar: {{ $preset['sidebar_bg'] }}; --pv-sidebar-text: {{ $preset['sidebar_text'] }}; --pv-topbar: {{ $preset['topbar_bg'] }}; --pv-body: {{ $preset['body_bg'] }}; --pv-card: {{ $preset['card_bg'] }}; --pv-primary: {{ $preset['primary_color'] }}; --pv-success: {{ $preset['success_color'] }}; --pv-warning: {{ $preset['warning_color'] }};">
                                <span class="preset-thumb" aria-hidden="true">
                                    <span class="pv-sidebar"><i class="pv-logo"></i><i class="pv-nav is-active"></i><i class="pv-nav"></i><i class="pv-nav"></i><i class="pv-nav"></i></span>
                                    <span class="pv-main">
                                        <span class="pv-topbar"></span>
                                        <span class="pv-body">
                                            <span class="pv-card"><i class="pv-line"></i><i class="pv-value"></i><i class="pv-btn"></i></span>
                                            <span class="pv-card pv-chart"><i></i><i></i><i></i><i></i><i></i></span>
                                        </span>
                                    </span>
                                </span>
                                <span class="preset-name">
                                    {{ __($preset['label']) }}
                                    @if ($preset['theme_mode'] === 'dark')<span class="badge text-bg-secondary ms-1">{{ __('Dark') }}</span>@endif
                                    <i class="bi bi-check-circle-fill preset-check"></i>
                                </span>
                                <span class="preset-desc">{{ __($preset['description']) }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="form-text mt-2 mb-0">{{ __('A preset only fills the colours and theme below — press Save to apply it.') }}</div>
                </div>
            </div>

            {{-- Branding --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-badge-tm me-1"></i>{{ __('Branding') }}</div>
                <div class="card-body">
                    <x-form.input name="app_name" :label="__('App name')" :value="$values['app_name']" maxlength="60" required data-preview="app_name"
                                  :help="__('Shown in the browser tab, the sidebar and the login page.')" />

                    @foreach ($uploads as $key => [$label, $settingKey, $help, $accept])
                        <x-form.group :label="$label" :for="$key" :name="$key" :help="__($help)">
                            <div class="d-flex align-items-center gap-3">
                                @if ($values[$settingKey])
                                    <img src="{{ branding_url($values[$settingKey]) }}" alt="" class="app-upload-preview">
                                @endif
                                <input type="file" id="{{ $key }}" name="{{ $key }}" accept="{{ $accept }}"
                                       class="form-control @error($key) is-invalid @enderror" data-preview-file="{{ $key }}">
                            </div>
                            @if ($values[$settingKey])
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove[]" value="{{ $key }}" id="remove_{{ $key }}">
                                    <label class="form-check-label small" for="remove_{{ $key }}">{{ __('Remove the current file') }}</label>
                                </div>
                            @endif
                        </x-form.group>
                    @endforeach
                </div>
            </div>

            {{-- Colours --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-droplet me-1"></i>{{ __('Colours') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($colorFields as $key => $label)
                            <div class="col-12 col-md-6">
                                <x-form.group :label="$label" :for="$key" :name="$key" class="mb-0">
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" value="{{ old($key, $values[$key]) }}"
                                               data-color-for="{{ $key }}" aria-label="{{ $label }}">
                                        <input type="text" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" maxlength="7"
                                               class="form-control text-uppercase font-monospace @error($key) is-invalid @enderror"
                                               data-color="{{ $key }}" required>
                                    </div>
                                </x-form.group>
                            </div>
                        @endforeach
                    </div>
                    <div id="contrast-hint" class="alert alert-warning py-2 small mt-3 mb-0 d-none" role="status"></div>
                </div>
            </div>

            {{-- Typography & layout --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-fonts me-1"></i>{{ __('Typography & layout') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.select name="font_family" :label="__('Font')" :options="array_combine(array_keys($fonts), array_keys($fonts))"
                                           :selected="$values['font_family']" required data-preview="font_family" group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.select name="base_font_size" :label="__('Font size')" :options="[13 => '13 px', 14 => '14 px', 15 => '15 px', 16 => '16 px']"
                                           :selected="$values['base_font_size']" required data-preview="base_font_size" group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="border_radius" type="number" :label="__('Corner radius')" :value="$values['border_radius']"
                                          min="0" max="12" step="1" suffix="px" required data-preview="border_radius" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="theme_mode" :label="__('Theme')" required group-class="mb-0" data-preview="theme_mode"
                                           :options="['light' => __('Light'), 'dark' => __('Dark'), 'auto' => __('Auto (follow the device)')]"
                                           :selected="$values['theme_mode']" :help="__('Default for everyone; each user can choose their own in My profile.')" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="table_density" :label="__('Table rows')" required group-class="mb-0" data-preview="table_density"
                                           :options="['normal' => __('Normal'), 'compact' => __('Compact')]" :selected="$values['table_density']" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Login page --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Login page') }}</div>
                <div class="card-body">
                    <x-form.group :label="__('Login logo')" for="login_logo" name="login_logo"
                                  :help="__('Optional. Shown on the coloured panel; falls back to the dark logo, then the logo.')">
                        <div class="d-flex align-items-center gap-3">
                            @if ($values['login_logo_path'])
                                <img src="{{ branding_url($values['login_logo_path']) }}" alt="" class="app-upload-preview">
                            @endif
                            <input type="file" id="login_logo" name="login_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   class="form-control @error('login_logo') is-invalid @enderror">
                        </div>
                        @if ($values['login_logo_path'])
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove[]" value="login_logo" id="remove_login_logo">
                                <label class="form-check-label small" for="remove_login_logo">{{ __('Remove the current file') }}</label>
                            </div>
                        @endif
                    </x-form.group>
                    <x-form.input name="login_title" :label="__('Heading')" :value="$values['login_title']" maxlength="60"
                                  :placeholder="__('Empty = app name (hidden when a logo is shown)')" />
                    <x-form.input name="login_welcome_text" :label="__('Welcome text')" :value="$values['login_welcome_text']" maxlength="150" />
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <x-form.group :label="__('Panel colour')" for="login_panel_color" name="login_panel_color" class="mb-0" :help="__('Empty = automatic.')">
                                <div class="input-group app-input-narrow">
                                    <input type="color" class="form-control form-control-color"
                                           value="{{ old('login_panel_color', $values['login_panel_color'] ?? $values['primary_color']) }}"
                                           data-color-for="login_panel_color" aria-label="{{ __('Panel colour') }}">
                                    <input type="text" id="login_panel_color" name="login_panel_color" maxlength="7"
                                           value="{{ old('login_panel_color', $values['login_panel_color']) }}"
                                           class="form-control text-uppercase font-monospace @error('login_panel_color') is-invalid @enderror" data-color="login_panel_color">
                                </div>
                            </x-form.group>
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.group :label="__('Panel text colour')" for="login_text_color" name="login_text_color" class="mb-0" :help="__('Empty = automatic.')">
                                <div class="input-group app-input-narrow">
                                    <input type="color" class="form-control form-control-color"
                                           value="{{ old('login_text_color', $values['login_text_color'] ?? '#FFFFFF') }}"
                                           data-color-for="login_text_color" aria-label="{{ __('Panel text colour') }}">
                                    <input type="text" id="login_text_color" name="login_text_color" maxlength="7"
                                           value="{{ old('login_text_color', $values['login_text_color']) }}"
                                           class="form-control text-uppercase font-monospace @error('login_text_color') is-invalid @enderror" data-color="login_text_color">
                                </div>
                            </x-form.group>
                        </div>
                    </div>
                    <x-form.group :label="__('Background image')" for="login_background" name="login_background"
                                  :help="__('JPG or WEBP, max 3 MB (resized to 1920 px wide). Empty = sidebar colour.')" class="mb-0">
                        <div class="d-flex align-items-center gap-3">
                            @if ($values['login_background_path'])
                                <img src="{{ branding_url($values['login_background_path']) }}" alt="" class="app-upload-preview">
                            @endif
                            <input type="file" id="login_background" name="login_background" accept="image/jpeg,image/webp"
                                   class="form-control @error('login_background') is-invalid @enderror">
                        </div>
                        @if ($values['login_background_path'])
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove[]" value="login_background" id="remove_login_background">
                                <label class="form-check-label small" for="remove_login_background">{{ __('Remove the current file') }}</label>
                            </div>
                        @endif
                    </x-form.group>
                </div>
            </div>

            {{-- Print --}}
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-printer me-1"></i>{{ __('Print (PDF bills, statements, receipts)') }}</div>
                <div class="card-body">
                    <x-form.textarea name="print_header_text" :label="__('Header text')" :value="$values['print_header_text']" rows="2" maxlength="300"
                                     :placeholder="__('Empty = farm name and address')" />
                    <x-form.textarea name="print_footer_text" :label="__('Footer text')" :value="$values['print_footer_text']" rows="2" maxlength="300"
                                     :placeholder="__('e.g. Bank: SBI A/c 1234… · Thank you!')" />
                    <x-form.group :label="__('PDF accent colour (table headers)')" for="pdf_accent_color" name="pdf_accent_color" class="mb-0"
                                  :help="__('Empty = primary colour.')">
                        <div class="input-group app-input-narrow">
                            <input type="color" class="form-control form-control-color"
                                   value="{{ old('pdf_accent_color', $values['pdf_accent_color'] ?? $values['primary_color']) }}"
                                   data-color-for="pdf_accent_color" aria-label="{{ __('PDF accent colour') }}">
                            <input type="text" id="pdf_accent_color" name="pdf_accent_color" maxlength="7"
                                   value="{{ old('pdf_accent_color', $values['pdf_accent_color']) }}"
                                   class="form-control text-uppercase font-monospace @error('pdf_accent_color') is-invalid @enderror" data-color="pdf_accent_color">
                        </div>
                    </x-form.group>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                <a href="{{ route('settings.appearance.edit') }}" class="btn btn-outline-secondary">{{ __('Discard changes') }}</a>
            </div>
        </form>
    </div>

    {{-- ===================== Live preview ===================== --}}
    <div class="col-12 col-xl-5">
        <div class="app-preview-sticky">
            <div class="small text-body-secondary mb-2"><i class="bi bi-eye me-1"></i>{{ __('Live preview — not saved until you press Save') }}</div>
            <div id="appearance-preview" class="app-preview border rounded overflow-hidden" data-bs-theme="{{ $values['theme_mode'] === 'dark' ? 'dark' : 'light' }}">
                <div class="d-flex">
                    <div class="app-preview-sidebar p-2">
                        <div class="fw-semibold mb-2 text-truncate" data-preview-text="app_name">{{ $values['app_name'] }}</div>
                        <div class="app-preview-nav active">{{ __('Dashboard') }}</div>
                        <div class="app-preview-nav">{{ __('Milk Entry') }}</div>
                        <div class="app-preview-nav">{{ __('Customers') }}</div>
                        <div class="app-preview-nav">{{ __('Reports') }}</div>
                    </div>
                    <div class="flex-grow-1 app-preview-body">
                        <div class="app-preview-topbar px-3 py-2 border-bottom small">{{ __('My Dairy Farm') }}</div>
                        <div class="p-3">
                            <div class="alert alert-success py-2 small mb-2">{{ __('Milk entry saved.') }}</div>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <button type="button" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm">{{ __('Edit') }}</button>
                                <button type="button" class="btn btn-secondary btn-sm">{{ __('Back') }}</button>
                                <button type="button" class="btn btn-danger btn-sm">{{ __('Delete') }}</button>
                            </div>
                            <div class="card">
                                <table class="table mb-0 small">
                                    <thead><tr><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                                    <tbody>
                                        <tr><td>Sharma</td><td class="text-end text-money">₹3,600.00</td><td><span class="badge text-bg-success">{{ __('Paid') }}</span></td></tr>
                                        <tr><td>ABC Hotel</td><td class="text-end text-money">₹1,44,000.00</td><td><span class="badge text-bg-warning">{{ __('Due') }}</span></td></tr>
                                        <tr><td>Gupta Sweets</td><td class="text-end text-money">₹22,500.00</td><td><span class="badge text-bg-danger">{{ __('Overdue') }}</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <a href="#" class="small d-inline-block mt-2" onclick="return false">{{ __('A link') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>window.AppearanceFonts = @json($fonts);</script>
    <script src="{{ asset_v('js/modules/appearance.js') }}"></script>
@endpush
