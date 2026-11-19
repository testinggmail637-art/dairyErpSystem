@php use App\Support\Website; $logoDark = Website::get('logo_dark'); @endphp
<footer class="w-footer">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-12 col-lg-4">
                <img class="w-footer-logo" src="{{ asset($logoDark.'-400.webp') }}" width="400" height="123" alt="{{ Website::get('site_name') }}" loading="lazy">
                <p class="mt-3">{{ __('website.footer.about') }}</p>
                <p class="w-footer-label mb-2">{{ __('website.footer.follow_us') }}</p>
                <div class="d-flex gap-3 w-footer-social">@include('website.partials.social')</div>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="w-footer-title">{{ __('website.footer.quick_links') }}</h2>
                <ul class="w-footer-links">
                    @foreach (['about', 'our-herd', 'home-delivery', 'bulk-orders', 'farmers', 'quality', 'blog', 'faq', 'contact'] as $item)
                        <li><a href="{{ Website::route($item) }}">{{ __("website.nav.$item") }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="w-footer-title">{{ __('website.footer.our_products') }}</h2>
                <ul class="w-footer-links">
                    @foreach (__('website.products') as $name)
                        <li><a href="{{ Website::route('products') }}">{{ $name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-12 col-lg-4">
                <h2 class="w-footer-title">{{ __('website.footer.reach_us') }}</h2>
                <ul class="w-footer-contact">
                    <li>@include('website.icons.geo-alt-fill')<a href="{{ Website::get('map_url') }}" target="_blank" rel="noopener">{{ Website::get('address') }}</a></li>
                    <li>@include('website.icons.telephone-fill')<a href="{{ Website::telLink() }}">{{ Website::get('phone') }}</a></li>
                    <li>@include('website.icons.envelope-fill')<a href="mailto:{{ Website::get('email') }}">{{ Website::get('email') }}</a></li>
                    <li>@include('website.icons.clock-fill')<span>{{ Website::get('timings') }}</span></li>
                </ul>
                <p class="w-footer-label mb-1">{{ __('website.footer.delivery_areas') }}</p>
                <p>{{ Website::get('delivery_areas') }}</p>
            </div>
        </div>
        <div class="w-footer-bottom">
            <span>© {{ now()->year }} {{ Website::get('site_name') }}. {{ __('website.footer.rights') }}</span>
            <span>{{ __('website.footer.fssai') }}: {{ Website::get('fssai') }}</span>
            <span class="d-flex flex-wrap gap-3">
                @foreach (['privacy', 'terms', 'refund-policy'] as $item)
                    <a href="{{ Website::route($item) }}">{{ __("website.pages.$item") }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
