@php use App\Support\Website; @endphp
{{-- Footer (home redesign 7A.3b): links + enquiry, contact, the brand word with the sunrise photo inside, legal. --}}
<footer class="w-footer">
    <div class="container">
        <div class="w-footer-top">
            <nav class="w-footer-nav" aria-label="{{ __('website.footer.quick_links') }}">
                @foreach (['products', 'home-delivery', 'bulk-orders', 'our-herd', 'quality', 'farmers', 'about', 'faq', 'blog', 'contact'] as $item)
                    <a href="{{ Website::route($item) }}">{{ __("website.nav.$item") }}</a>
                @endforeach
            </nav>
            <a class="w-btn w-btn-accent" href="{{ Website::route('contact') }}#enquiry">{{ __('website.action.enquiry') }}</a>
        </div>
        <div class="w-footer-contact">
            <a href="{{ Website::get('map_url') }}" target="_blank" rel="noopener">{{ Website::get('address') }}</a>
            <a href="{{ Website::telLink() }}">{{ Website::get('phone') }}</a>
            <a href="mailto:{{ Website::get('email') }}">{{ Website::get('email') }}</a>
            <span>{{ Website::get('timings') }}</span>
            <span>{{ __('website.footer.delivery_areas') }}: {{ Website::get('delivery_areas') }}</span>
            <span class="w-footer-social">@include('website.partials.social')</span>
        </div>
        <p class="w-footer-word" aria-hidden="true">{{ __('website.footer.word') }}</p>
        <div class="w-footer-bottom">
            <span>© {{ now()->year }} {{ Website::get('site_name') }}. {{ __('website.footer.rights') }} · {{ __('website.footer.fssai') }}: {{ Website::get('fssai') }}</span>
            <span class="d-flex flex-wrap justify-content-center gap-3">
                @foreach (['privacy', 'terms', 'refund-policy'] as $item)
                    <a href="{{ Website::route($item) }}">{{ __("website.pages.$item") }}</a>
                @endforeach
            </span>
        </div>
    </div>
</footer>
