@extends('layouts.website')

@php
    use App\Support\Website;
    $img = 'images/website/home/';
    $products = __('home.products.items');
    $total = str_pad((string) count($products), 2, '0', STR_PAD_LEFT);
@endphp

@push('head')
    <link rel="preload" as="image" href="{{ asset($img.'hero-herd-768.webp') }}" imagesrcset="{{ asset($img.'hero-herd-768.webp') }} 768w, {{ asset($img.'hero-herd-1280.webp') }} 1280w, {{ asset($img.'hero-herd-1920.webp') }} 1920w" imagesizes="100vw" fetchpriority="high">
@endpush

@section('content')
    {{-- 1. Hero --}}
    <section class="w-hero">
        @include('website.partials.img', ['name' => $img.'hero-herd', 'widths' => [768, 1280, 1920], 'sizes' => '100vw', 'alt' => __('home.hero.image_alt'), 'class' => 'w-hero-img', 'eager' => true])
        <div class="container w-hero-body">
            <p class="w-eyebrow w-eyebrow-light w-hero-in" style="--d: 0">{{ __('home.hero.eyebrow') }}</p>
            <h1 class="w-hero-in" style="--d: 1">{{ __('home.hero.title') }}</h1>
            <p class="w-hero-text w-hero-in" style="--d: 2">{{ __('home.hero.text') }}</p>
            <div class="d-flex flex-wrap gap-2 mt-4 w-hero-in" style="--d: 3">
                <a class="w-btn w-btn-accent" href="#enquiry">{{ __('home.hero.cta') }}</a>
                <a class="w-btn w-btn-ghost" href="{{ Website::route('our-herd') }}">{{ __('home.hero.cta_secondary') }}</a>
            </div>
            <ul class="w-hero-trust w-hero-in" style="--d: 4">
                @foreach (__('home.hero.trust') as $point)
                    <li>@include('website.icons.check-circle-fill') {{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 2. Free-from marquee --}}
    <section class="w-free" aria-label="{{ __('home.free_from.title') }}">
        <div class="w-marquee" id="free-marquee">
            @foreach ([false, true] as $copy)
                <ul class="w-marquee-track" @if ($copy) aria-hidden="true" @endif>
                    <li><strong>{{ __('home.free_from.title') }}</strong></li>
                    @foreach (__('home.free_from.items') as $item)
                        <li><span class="w-free-x">@include('website.icons.x-lg')</span> {{ $item }}</li>
                    @endforeach
                    <li>@include('website.icons.droplet-fill')</li>
                </ul>
            @endforeach
        </div>
        <button type="button" class="w-play w-play-sm" data-marquee-toggle aria-controls="free-marquee" aria-label="{{ __('home.pause') }}" data-label-pause="{{ __('home.pause') }}" data-label-play="{{ __('home.play') }}">
            <span class="w-when-playing">@include('website.icons.pause-circle-fill')</span><span class="w-when-paused">@include('website.icons.play-circle-fill')</span>
        </button>
    </section>

    {{-- 3. What we offer --}}
    <section class="w-section">
        <div class="container">
            <div class="w-heading" data-reveal>
                <p class="w-eyebrow">{{ __('home.offer.eyebrow') }}</p>
                <h2>{{ __('home.offer.title') }}</h2>
            </div>
            <div class="row g-3 g-lg-4">
                @foreach (__('home.offer.items') as $item)
                    <div class="col-12 col-sm-6 col-lg-3" data-reveal style="--d: {{ $loop->index }}">
                        <a class="w-card w-offer" href="{{ Website::route($item['page']) }}">
                            <span class="w-card-icon">@include('website.icons.'.$item['icon'])</span>
                            <h3 class="h4">{{ $item['title'] }}</h3>
                            <p>{{ $item['text'] }}</p>
                            <span class="w-link">{{ __('home.offer.more') }} @include('website.icons.arrow-right')</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4. Product showcase slider --}}
    <section class="w-showcase" data-slider aria-roledescription="carousel" aria-label="{{ __('home.products.eyebrow') }}">
        <div class="w-showcase-stage">
            <div class="container">
                <div class="w-slides">
                    @foreach ($products as $i => $p)
                        <div class="w-slide @if ($i === 0) is-active @endif" id="slide-{{ $i }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ count($products) }}" @if ($i !== 0) aria-hidden="true" @endif>
                            <span class="w-slide-word" aria-hidden="true">{{ $p['word'] }}</span>
                            <div class="row g-4 align-items-center">
                                <div class="col-12 col-lg-6 order-2 order-lg-1 w-slide-text">
                                    <p class="w-slide-count"><b>{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</b> / {{ $total }}</p>
                                    <p class="w-slide-sub">{{ $p['sub'] }}</p>
                                    <h2>{{ $p['name'] }}</h2>
                                    <p class="w-slide-desc">{{ $p['text'] }}</p>
                                    <ul class="w-sizes">
                                        @foreach ($p['sizes'] as $size)
                                            <li>{{ $size }}</li>
                                        @endforeach
                                    </ul>
                                    <p class="w-slide-price">{{ $p['price'] ?? __('home.products.ask_price') }}</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="w-btn w-btn-accent" href="#enquiry">{{ __('home.products.enquire') }}</a>
                                        <a class="w-btn w-btn-ghost" href="{{ Website::route('products') }}">{{ __('home.products.details') }}</a>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6 order-1 order-lg-2">
                                    <div class="w-slide-visual">
                                        <span class="w-slide-ring" aria-hidden="true"></span>
                                        @include('website.partials.img', ['name' => $img.$p['image'], 'widths' => [480, 800], 'sizes' => '(min-width: 992px) 380px, 260px', 'alt' => $p['name'], 'ratio' => [480, 480], 'class' => 'w-slide-photo'])
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="w-showcase-bar">
            <div class="container d-flex align-items-center gap-2 gap-md-3">
                <button type="button" class="w-round-btn" data-slide-prev aria-label="{{ __('home.products.prev') }}"><span class="w-flip">@include('website.icons.arrow-right')</span></button>
                <div class="w-slide-tabs" role="tablist">
                    @foreach ($products as $i => $p)
                        <button type="button" class="w-slide-tab @if ($i === 0) is-active @endif" data-slide-to="{{ $i }}" role="tab" aria-controls="slide-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? 0 : -1 }}">
                            @include('website.icons.'.$p['icon']) {{ $p['tab'] }}
                        </button>
                    @endforeach
                </div>
                <button type="button" class="w-round-btn" data-slide-next aria-label="{{ __('home.products.next') }}">@include('website.icons.arrow-right')</button>
                <button type="button" class="w-round-btn w-play" data-slide-toggle aria-label="{{ __('home.pause') }}" data-label-pause="{{ __('home.pause') }}" data-label-play="{{ __('home.play') }}">
                    <span class="w-when-playing">@include('website.icons.pause-circle-fill')</span><span class="w-when-paused">@include('website.icons.play-circle-fill')</span>
                </button>
            </div>
        </div>
    </section>

    {{-- 5. Market milk vs ours (drag to compare) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-heading text-center" data-reveal>
                <span class="w-pill">{{ __('home.compare.badge') }}</span>
                <h2>{{ __('home.compare.title') }}</h2>
                <p class="w-lead mx-auto">{{ __('home.compare.text') }}</p>
            </div>
            <div class="w-compare" data-compare data-reveal>
                @include('website.partials.img', ['name' => $img.'product-cow-milk', 'widths' => [480, 800], 'sizes' => '(min-width: 992px) 1100px, 100vw', 'alt' => __('home.compare.right'), 'class' => 'w-compare-img'])
                <div class="w-compare-left" aria-hidden="true">
                    @include('website.partials.img', ['name' => $img.'product-cow-milk', 'widths' => [480, 800], 'sizes' => '(min-width: 992px) 1100px, 100vw', 'alt' => '', 'class' => 'w-compare-img'])
                </div>
                <span class="w-compare-tag w-compare-tag-left">{{ __('home.compare.left') }}</span>
                <span class="w-compare-tag w-compare-tag-right">{{ __('home.compare.right') }}</span>
                <ul class="w-compare-points w-compare-points-left">
                    @foreach (__('home.compare.left_points') as $point)
                        <li><span class="w-no">@include('website.icons.x-circle-fill')</span> {{ $point }}</li>
                    @endforeach
                </ul>
                <ul class="w-compare-points w-compare-points-right">
                    @foreach (__('home.compare.right_points') as $point)
                        <li>@include('website.icons.check-circle-fill') {{ $point }}</li>
                    @endforeach
                </ul>
                <span class="w-compare-line" aria-hidden="true"><span class="w-compare-knob"><span class="w-flip">@include('website.icons.arrow-right')</span>@include('website.icons.arrow-right')</span></span>
                <input class="w-compare-range" type="range" min="0" max="100" value="50" aria-label="{{ __('home.compare.slider') }}">
            </div>
        </div>
    </section>

    {{-- 6. Desi breeds --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-heading" data-reveal>
                <p class="w-eyebrow">{{ __('home.breeds.eyebrow') }}</p>
                <h2>{{ __('home.breeds.title') }}</h2>
                <p class="w-lead">{{ __('home.breeds.text') }}</p>
            </div>
            <div class="row g-3 g-lg-4">
                @foreach (__('home.breeds.items') as $breed)
                    <div class="col-12 col-sm-6 col-lg-3" data-reveal style="--d: {{ $loop->index }}">
                        <article class="w-card w-breed">
                            <div class="w-breed-photo">
                                @include('website.partials.img', ['name' => $img.$breed['image'], 'widths' => [480, 800], 'sizes' => '(min-width: 992px) 25vw, (min-width: 576px) 50vw, 100vw', 'alt' => $breed['name'], 'ratio' => [480, 320]])
                                <span class="w-badge">{{ $breed['origin'] }}</span>
                            </div>
                            <div class="w-breed-body">
                                <h3 class="h4">{{ $breed['name'] }}</h3>
                                <p>{{ $breed['text'] }}</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
            <div class="text-center mt-4">
                <a class="w-btn w-btn-outline" href="{{ Website::route('our-herd') }}">{{ __('home.breeds.cta') }}</a>
            </div>
        </div>
    </section>

    {{-- 7. Bilona method timeline --}}
    <section class="w-section">
        <div class="container">
            <div class="w-heading text-center" data-reveal>
                <span class="w-pill">{{ __('home.bilona.badge') }}</span>
                <h2>{{ __('home.bilona.title') }}</h2>
            </div>
            <ol class="w-timeline" data-timeline style="--steps: {{ count(__('home.bilona.steps')) }}">
                @foreach (__('home.bilona.steps') as $step)
                    <li style="--i: {{ $loop->index }}">
                        <span class="w-timeline-photo">
                            @include('website.partials.img', ['name' => $img.$step['image'], 'widths' => [480], 'sizes' => '120px', 'alt' => $step['title'], 'ratio' => [120, 120]])
                        </span>
                        <div>
                            <h3 class="h4"><span class="w-timeline-no">{{ $loop->iteration }}.</span> {{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <p class="text-center mt-4 mb-0"><a class="w-link w-link-underline" href="{{ Website::route('about') }}">{{ __('home.bilona.cta') }} @include('website.icons.arrow-right')</a></p>
        </div>
    </section>

    {{-- 8. Trust bento with counters (stats are SAMPLE numbers until launch) --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-heading text-center" data-reveal>
                <span class="w-pill">{{ __('home.trust.badge') }}</span>
                <h2>{{ __('home.trust.title') }}</h2>
            </div>
            @php $tiles = __('home.trust.tiles'); @endphp
            <div class="w-bento">
                <div class="w-tile w-tile-zero" data-reveal>
                    <p class="w-zero"><span data-count="0" data-from="100">0</span>%</p>
                    <h3>{{ __('home.trust.zero_label') }}</h3>
                    <p>{{ __('home.trust.zero_text') }}</p>
                </div>
                <div class="w-tile w-tile-stats" data-reveal style="--d: 1">
                    @foreach (__('home.trust.stats') as $stat)
                        <div>
                            <p class="w-stat"><span data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</span>+</p>
                            <p class="w-stat-label">{{ $stat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="w-tile w-tile-lab" data-reveal style="--d: 2">
                    <span class="w-tile-icon">@include('website.icons.'.$tiles['lab']['icon'])</span>
                    <h3 class="h4">{{ $tiles['lab']['title'] }}</h3>
                    <p>{{ $tiles['lab']['text'] }}</p>
                </div>
                @foreach (['glass' => 'glass-bottles', 'fresh' => 'product-buffalo-milk', 'pure' => 'milking'] as $key => $photo)
                    <div class="w-tile w-tile-photo w-tile-{{ $key }}" data-reveal style="--d: {{ $loop->index + 2 }}">
                        @include('website.partials.img', ['name' => $img.$photo, 'widths' => $photo === 'milking' ? [640, 1024] : [480, 800], 'sizes' => '(min-width: 992px) 50vw, 100vw', 'alt' => '', 'class' => 'w-tile-bg'])
                        <span class="w-tile-icon">@include('website.icons.'.$tiles[$key]['icon'])</span>
                        <h3 class="h4">{{ $tiles[$key]['title'] }}</h3>
                        <p>{{ $tiles[$key]['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 9. How to order — 3 steps --}}
    <section class="w-section">
        <div class="container">
            <div class="w-heading text-center" data-reveal>
                <span class="w-pill">{{ __('home.order.badge') }}</span>
                <h2>{{ __('home.order.title') }}</h2>
            </div>
            <ol class="w-timeline w-timeline-icons" data-timeline style="--steps: 3">
                @foreach (__('home.order.steps') as $step)
                    <li style="--i: {{ $loop->index }}">
                        <span class="w-timeline-photo">@include('website.icons.'.$step['icon'])</span>
                        <div>
                            <h3 class="h4"><span class="w-timeline-no">{{ $loop->iteration }}.</span> {{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <ul class="w-points w-points-center" data-reveal>
                @foreach (__('home.order.points') as $point)
                    <li>@include('website.icons.'.$point['icon']) {{ $point['text'] }}</li>
                @endforeach
            </ul>
            <p class="text-center w-muted mb-0" data-reveal>@include('website.icons.geo-alt-fill') {{ __('home.order.areas') }} <strong class="w-strong">{{ Website::get('delivery_areas') }}</strong></p>
        </div>
    </section>

    {{-- 10. Why Yashadhya --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-12 col-lg-6 order-lg-2" data-reveal>
                    <figure class="w-quote">
                        @include('website.partials.img', ['name' => $img.'farmer-calf', 'widths' => [640, 1024], 'sizes' => '(min-width: 992px) 45vw, 100vw', 'alt' => __('home.why.image_alt'), 'ratio' => [640, 427]])
                        <blockquote>
                            <span class="w-quote-mark">@include('website.icons.quote')</span>
                            <p>{{ __('home.why.quote') }}</p>
                            <figcaption>— {{ __('home.why.quote_by') }}</figcaption>
                        </blockquote>
                    </figure>
                </div>
                <div class="col-12 col-lg-6">
                    <div data-reveal>
                        <p class="w-eyebrow">{{ __('home.why.eyebrow') }}</p>
                        <h2>{{ __('home.why.title') }}</h2>
                    </div>
                    <div class="row g-3 mt-2">
                        @foreach (__('home.why.values') as $value)
                            <div class="col-12 col-sm-6" data-reveal style="--d: {{ $loop->index }}">
                                <div class="w-value">
                                    <span class="w-card-icon">@include('website.icons.'.$value['icon'])</span>
                                    <h3 class="h4">{{ $value['title'] }}</h3>
                                    <p>{{ $value['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 11. Ecosystem tabs --}}
    <section class="w-section w-eco">
        @include('website.partials.img', ['name' => $img.'sunset-cows', 'widths' => [768, 1280, 1920], 'sizes' => '100vw', 'alt' => '', 'class' => 'w-eco-bg'])
        <div class="container position-relative">
            <div class="w-heading" data-reveal>
                <p class="w-eyebrow w-eyebrow-light">{{ __('home.ecosystem.eyebrow') }}</p>
                <h2>{{ __('home.ecosystem.title') }}</h2>
            </div>
            <div class="w-tabs" role="tablist">
                @foreach (__('home.ecosystem.tabs') as $i => $tab)
                    <button type="button" class="w-tab @if ($i === 0) active @endif" id="eco-tab-{{ $tab['key'] }}" data-bs-toggle="tab" data-bs-target="#eco-{{ $tab['key'] }}" role="tab" aria-controls="eco-{{ $tab['key'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                        @include('website.icons.'.$tab['icon']) {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="tab-content">
                @foreach (__('home.ecosystem.tabs') as $i => $tab)
                    <div class="tab-pane fade @if ($i === 0) show active @endif w-tab-panel" id="eco-{{ $tab['key'] }}" role="tabpanel" aria-labelledby="eco-tab-{{ $tab['key'] }}" tabindex="0">
                        <h3>{{ $tab['title'] }}</h3>
                        <p>{{ $tab['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 12. Reviews — SAMPLE text, never shown in production (owner adds real reviews; DECISIONS #204) --}}
    @unless (app()->isProduction())
        <section class="w-section">
            <div class="container">
                <div class="w-heading text-center" data-reveal>
                    <p class="w-eyebrow">{{ __('home.reviews.eyebrow') }}</p>
                    <h2>{{ __('home.reviews.title') }}</h2>
                </div>
                <div class="row g-3 g-lg-4">
                    @foreach (__('home.reviews.items') as $review)
                        <div class="col-12 col-md-4" data-reveal style="--d: {{ $loop->index }}">
                            <figure class="w-card w-review">
                                <div class="w-stars" aria-hidden="true">@for ($s = 0; $s < 5; $s++)@include('website.icons.star-fill')@endfor</div>
                                <blockquote><p>“{{ $review['text'] }}”</p></blockquote>
                                <figcaption><strong>{{ $review['name'] }}</strong><span>{{ $review['place'] }}</span></figcaption>
                            </figure>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endunless

    {{-- 13. FAQ --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-12 col-lg-4" data-reveal>
                    <p class="w-eyebrow">{{ __('home.faq.eyebrow') }}</p>
                    <h2>{{ __('home.faq.title') }}</h2>
                    <a class="w-link" href="{{ Website::route('faq') }}">{{ __('home.faq.all') }} @include('website.icons.arrow-right')</a>
                </div>
                <div class="col-12 col-lg-8" data-reveal style="--d: 1">
                    @foreach (__('home.faq.items') as $i => $faq)
                        <details class="w-faq" @if ($i === 0) open @endif>
                            <summary>{{ $faq['q'] }} <span class="w-faq-icon">@include('website.icons.plus-lg')</span></summary>
                            <p>{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 14. Questions nobody asks: packet milk vs our farm (a table; stacks into cards on phones) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-heading text-center" data-reveal>
                <span class="w-pill">{{ __('home.questions.badge') }}</span>
                <h2>{{ __('home.questions.title') }}</h2>
                <p class="w-lead">{{ __('home.questions.text') }}</p>
            </div>
            <div class="w-vs-wrap" data-reveal>
                <table class="w-vs">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('home.questions.col_question') }}</th>
                            <th scope="col" class="w-vs-them">{{ __('home.questions.col_them') }}</th>
                            <th scope="col" class="w-vs-us">{{ __('home.questions.col_us') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (__('home.questions.rows') as $row)
                            <tr>
                                <th scope="row">{{ $row['q'] }}</th>
                                <td class="w-vs-them" data-label="{{ __('home.questions.col_them') }}"><span class="w-vs-cell">@include('website.icons.x-circle-fill') {{ $row['them'] }}</span></td>
                                <td class="w-vs-us" data-label="{{ __('home.questions.col_us') }}"><span class="w-vs-cell">@include('website.icons.check-circle-fill') {{ $row['us'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-4" data-reveal>
                <a class="w-btn w-btn-accent" href="#enquiry">{{ __('home.questions.cta') }}</a>
                <p class="w-small w-muted mt-3 mb-0">{{ __('home.questions.note') }}</p>
            </div>
        </div>
    </section>

    {{-- 15. Enquiry band (UI only in Stage 1; saving leads comes in 7A.9) --}}
    <section class="w-section w-enquiry" id="enquiry">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-12 col-lg-5" data-reveal>
                    <h2>{{ __('home.enquiry.title') }}</h2>
                    <p class="w-lead">{{ __('home.enquiry.text') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="w-btn w-btn-primary" href="{{ Website::telLink() }}">@include('website.icons.telephone-fill') {{ Website::get('phone') }}</a>
                        <a class="w-btn w-btn-outline" href="{{ Website::whatsappLink() }}" target="_blank" rel="noopener">@include('website.icons.whatsapp') {{ __('website.action.whatsapp') }}</a>
                    </div>
                </div>
                <div class="col-12 col-lg-7" data-reveal style="--d: 1">
                    <form class="w-card w-form" data-demo-form novalidate>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="w-label" for="enq-name">{{ __('home.enquiry.name') }}</label>
                                <input class="w-input" id="enq-name" name="name" autocomplete="name" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="w-label" for="enq-phone">{{ __('home.enquiry.phone') }}</label>
                                <input class="w-input" id="enq-phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" maxlength="10" pattern="[6-9][0-9]{9}" required>
                            </div>
                            <div class="col-12">
                                <span class="w-label">{{ __('home.enquiry.interest') }}</span>
                                <div class="w-chips">
                                    @foreach (__('home.enquiry.interests') as $key => $label)
                                        <label class="w-chip"><input type="radio" name="interest" value="{{ $key }}" @checked($loop->first)> <span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="w-btn w-btn-accent w-100">{{ __('home.enquiry.submit') }}</button>
                                <p class="w-small w-muted mt-2 mb-0">{{ __('home.enquiry.note') }}</p>
                                <p class="w-form-msg" role="status" hidden>{{ __('home.enquiry.demo') }}</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
