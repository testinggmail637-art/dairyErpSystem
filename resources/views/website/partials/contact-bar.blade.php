@php use App\Support\Website; @endphp
{{-- Mobile: fixed bottom bar. Desktop: one floating WhatsApp button. WhatsApp is only a link (no API — Phase 8). --}}
<nav class="w-contact-bar d-lg-none" aria-label="{{ __('website.action.enquiry') }}">
    <a href="{{ Website::telLink() }}">@include('website.icons.telephone-fill')<span>{{ __('website.action.call') }}</span></a>
    <a href="{{ Website::whatsappLink() }}" target="_blank" rel="noopener">@include('website.icons.whatsapp')<span>{{ __('website.action.whatsapp') }}</span></a>
    <a href="{{ Website::route('contact') }}#enquiry" class="w-contact-bar-main">{{ __('website.action.enquiry') }}</a>
</nav>
<a class="w-wa-float d-none d-lg-flex" href="{{ Website::whatsappLink() }}" target="_blank" rel="noopener" aria-label="{{ __('website.action.whatsapp') }}">@include('website.icons.whatsapp')</a>
