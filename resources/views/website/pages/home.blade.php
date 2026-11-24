@extends('layouts.website')

{{-- Home redesign (7A.3b): 12 sections chosen one by one on the design canvas (DECISIONS #209). --}}
@php
    use App\Support\Website;
    $img = 'images/website/home/';
    $w = [ // responsive widths per photo
        'hero-herd' => [768, 1280, 1920], 'milking' => [640, 1024], 'milk-pitcher' => [800, 1280],
        'milk-bottle' => [480, 800, 1200], 'milk-cup' => [480, 800, 1200], 'dahi' => [480, 800, 1200],
        'ghee-jar' => [480, 800, 1200], 'paneer' => [480, 800, 1200], 'breed-murrah' => [480, 800],
        'bilona-churning' => [480, 800], 'sunrise-farmer' => [768, 1280, 1920], 'cow-calf' => [768, 1280, 1920],
    ];
    $srcset = fn ($n) => collect($w[$n])->map(fn ($x) => asset("{$img}{$n}-{$x}.webp")." {$x}w")->implode(', ');
    $wa = Website::whatsappLink();
    $wave = '<svg viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0 H1440 V26 Q 1350 60 1260 26 T 1080 26 T 900 26 T 720 26 T 540 26 T 360 26 T 180 26 T 0 26 Z"/></svg>';
    $arrowL = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';
    $arrowR = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
    $steps = __('home.journey.steps');
    $dsteps = __('home.delivery.steps');
@endphp

@push('head')
    <link rel="preload" as="image" href="{{ asset($img.'hero-herd-1280.webp') }}" fetchpriority="high">
    <link rel="stylesheet" href="{{ asset_v('css/home.css') }}">
@endpush

@section('content')
<div class="h-page">

    {{-- 1. Hero: big words with the herd photo inside + milk wave --}}
    <section class="h-hero">
        <div class="h-hero-top">
            <p class="h-eyebrow h-gold">{{ __('home.hero.eyebrow') }}</p>
            <p class="h-hero-word" aria-hidden="true"><span>{{ __('home.hero.word_1') }}</span> <span>{{ __('home.hero.word_2') }}</span></p>
            <span class="h-drop" aria-hidden="true"></span>
        </div>
        <div class="h-hero-bottom">
            <div class="h-wave h-wave-up">{!! str_replace('M0 0 H1440 V26 Q 1350 60 1260 26 T 1080 26 T 900 26 T 720 26 T 540 26 T 360 26 T 180 26 T 0 26 Z', 'M0 30 Q 90 0 180 30 T 360 30 T 540 30 T 720 30 T 900 30 T 1080 30 T 1260 30 T 1440 30 V60 H0z', $wave) !!}</div>
            <div class="container h-hero-row">
                <div>
                    <h1>{{ __('home.hero.title') }}</h1>
                    <p class="h-lead">{{ __('home.hero.text') }}</p>
                </div>
                <div class="h-hero-ctas">
                    <a class="h-btn h-btn-accent h-btn-lg" href="#enquiry">{{ __('home.hero.cta') }}</a>
                    <a class="h-btn h-btn-outline h-btn-lg" href="#breeds">{{ __('home.hero.cta_secondary') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Free from: tilted test report --}}
    <section class="h-sec h-cream" id="quality">
        <div class="container h-split">
            <div data-reveal>
                <p class="h-eyebrow">{{ __('home.quality.eyebrow') }}</p>
                <h2 class="h-xl">{{ __('home.quality.title_1') }}<br>{{ __('home.quality.title_2') }}</h2>
                <p class="h-lead">{{ __('home.quality.text') }}</p>
                <a class="h-link" href="{{ Website::route('quality') }}">{{ __('home.quality.link') }} →</a>
            </div>
            <div class="h-report-stage" data-reveal>
                <div class="h-report-back" aria-hidden="true"></div>
                <div class="h-report">
                    <div class="h-report-head">
                        <span><b>{{ __('home.quality.report') }}</b><br><small>{{ __('home.quality.batch') }}</small></span>
                        <img class="w-logo-light" src="{{ asset(Website::get('logo').'-400.webp') }}" width="110" height="34" alt="" loading="lazy">
                        <img class="w-logo-dark" src="{{ asset(Website::get('logo_dark').'-400.webp') }}" width="110" height="34" alt="" loading="lazy">
                    </div>
                    <ul class="h-report-rows">
                        @foreach (__('home.quality.rows') as [$label, $value])
                            <li><span>{{ $label }}</span><b class="h-ok">{{ $value }}</b></li>
                        @endforeach
                        <li class="h-sep"><span>{{ __('home.quality.fat') }}</span><b class="h-mono">{{ __('home.quality.fat_value') }}</b></li>
                    </ul>
                    <div class="h-stamp">{{ __('home.quality.stamp') }}<br>✓</div>
                </div>
            </div>
        </div>
    </section>

    {{-- 3. What we offer: green bento --}}
    <section class="h-sec h-band" id="offer">
        <div class="h-wave h-wave-page">{!! $wave !!}</div>
        <div class="container">
            <div class="h-head" data-reveal>
                <div><p class="h-eyebrow h-gold">{{ __('home.offer.eyebrow') }}</p><h2 class="h-lg">{{ __('home.offer.title') }}</h2></div>
                <a class="h-link h-link-light" href="#products">{{ __('home.offer.all') }} →</a>
            </div>
            <div class="h-bento-stage">
                <div class="h-bento">
                    <a href="#products" class="h-tile h-tile-main" data-reveal>
                        <span class="h-tile-text">
                            <span class="h-tile-num">{{ __('home.offer.main_label') }}</span>
                            <span><b class="h-tile-title">{{ __('home.offer.main_title') }}</b><span class="h-tile-sub">{{ __('home.offer.main_text') }}</span></span>
                            <span class="h-btn h-btn-primary h-btn-sm">{{ __('home.offer.main_cta') }} →</span>
                        </span>
                        <img src="{{ asset($img.'milk-bottle-800.webp') }}" srcset="{{ $srcset('milk-bottle') }}" sizes="(min-width: 1100px) 25vw, 40vw" alt="" loading="lazy" decoding="async">
                    </a>
                    <a href="#products" class="h-tile h-tile-photo h-tile-wide" data-reveal>
                        <img src="{{ asset($img.'breed-murrah-800.webp') }}" srcset="{{ $srcset('breed-murrah') }}" sizes="(min-width: 1100px) 50vw, 100vw" alt="" loading="lazy" decoding="async">
                        <span class="h-tile-cap"><span class="h-tile-num h-gold">02</span><b>{{ __('home.offer.buffalo_title') }}</b><span>{{ __('home.offer.buffalo_text') }}</span></span>
                    </a>
                    <a href="{{ Website::route('home-delivery') }}" class="h-tile h-tile-accent" data-reveal>
                        <span class="h-tile-num">{{ __('home.offer.delivery_label') }}</span>
                        <span><b class="h-tile-big">{{ __('home.offer.delivery_time') }}</b><span class="h-tile-sub2">{{ __('home.offer.delivery_text') }}</span></span>
                    </a>
                    <a href="{{ Website::route('bulk-orders') }}" class="h-tile h-tile-photo" data-reveal>
                        <img src="{{ asset($img.'bilona-churning-800.webp') }}" srcset="{{ $srcset('bilona-churning') }}" sizes="(min-width: 1100px) 25vw, 50vw" alt="" loading="lazy" decoding="async">
                        <span class="h-tile-cap h-bottom"><span class="h-tile-num h-gold">04</span><b>{{ __('home.offer.bulk_title') }}</b></span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- 4. Breeds: 3D coverflow --}}
    <section class="h-sec h-cream h-center" id="breeds">
        <div class="h-wave h-wave-band">{!! $wave !!}</div>
        <div class="container">
            <div data-reveal><p class="h-eyebrow">{{ __('home.breeds.eyebrow') }}</p><h2 class="h-lg">{{ __('home.breeds.title') }}</h2></div>
            <div class="h-coverflow" data-coverflow aria-roledescription="carousel" aria-label="{{ __('home.breeds.title') }}">
                @foreach (__('home.breeds.items') as $i => $b)
                    <button type="button" class="h-cf-card" data-i="{{ $i }}" data-name="{{ $b['name'] }}" data-tag="{{ $b['tag'] }}" data-line="{{ $b['line'] }}" aria-label="{{ $b['name'] }}">
                        @include('website.partials.img', ['name' => $img.$b['img'], 'widths' => [480, 800], 'sizes' => '300px', 'alt' => ''])
                    </button>
                @endforeach
            </div>
            @php $b1 = __('home.breeds.items')[1]; @endphp
            <div class="h-cf-controls">
                <button type="button" class="h-round" data-cf-prev aria-label="{{ __('home.breeds.prev') }}">{!! $arrowL !!}</button>
                <div class="h-cf-info" aria-live="polite"><b data-cf-name>{{ $b1['name'] }}</b> <span class="h-tag" data-cf-tag>{{ $b1['tag'] }}</span><br><span data-cf-line>{{ $b1['line'] }}</span></div>
                <button type="button" class="h-round" data-cf-next aria-label="{{ __('home.breeds.next') }}">{!! $arrowR !!}</button>
            </div>
        </div>
    </section>

    {{-- 5. Farm to glass: 3D photo stack --}}
    <section class="h-sec h-band h-glow" id="journey">
        <div class="h-wave h-wave-page">{!! $wave !!}</div>
        <div class="container h-split h-split-stack">
            <div data-reveal>
                <p class="h-eyebrow h-gold">{{ __('home.journey.eyebrow') }}</p>
                <h2 class="h-lg">{{ __('home.journey.title') }}</h2>
                <div class="h-steps" data-steps role="tablist">
                    @foreach ($steps as $i => $s)
                        <button type="button" @class(['h-step', 'is-active' => $i === 0]) data-i="{{ $i }}" role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                            <span class="h-step-num">0{{ $i + 1 }}</span><span><b>{{ $s['title'] }}</b><small>{{ $s['text'] }}</small></span>
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="h-stack-stage">
                <div class="h-stack" data-stack>
                    <div class="h-stack-card" data-i="0"><img src="{{ asset($img.'hero-herd-768.webp') }}" srcset="{{ $srcset('hero-herd') }}" sizes="(min-width: 768px) 520px, 92vw" alt="{{ $steps[0]['alt'] }}" loading="lazy" decoding="async"></div>
                    <div class="h-stack-card" data-i="1"><img src="{{ asset($img.'milking-640.webp') }}" srcset="{{ $srcset('milking') }}" sizes="(min-width: 768px) 520px, 92vw" alt="{{ $steps[1]['alt'] }}" loading="lazy" decoding="async"></div>
                    <div class="h-stack-card h-stack-test" data-i="2">
                        <b>{{ __('home.journey.test_title') }}</b>
                        @foreach (__('home.journey.test_rows') as [$label, $value])
                            <span><span>{{ $label }}</span><b>{{ $value }}</b></span>
                        @endforeach
                        <div class="h-stamp h-stamp-sm">{{ __('home.quality.stamp') }} ✓</div>
                    </div>
                    <div class="h-stack-card" data-i="3"><img src="{{ asset($img.'milk-pitcher-800.webp') }}" srcset="{{ $srcset('milk-pitcher') }}" sizes="(min-width: 768px) 520px, 92vw" alt="{{ $steps[3]['alt'] }}" loading="lazy" decoding="async"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- 6. Products: tall photo cards slider --}}
    <section class="h-sec h-cream" id="products">
        <div class="h-wave h-wave-band">{!! $wave !!}</div>
        <div class="container">
            <div class="h-head" data-reveal>
                <div><p class="h-eyebrow">{{ __('home.products.eyebrow') }}</p><h2 class="h-xl">{{ __('home.products.title') }}</h2></div>
                <div class="h-slider-nav">
                    <button type="button" class="h-round" data-prod-prev aria-label="{{ __('home.products.prev') }}">{!! $arrowL !!}</button>
                    <button type="button" class="h-round" data-prod-next aria-label="{{ __('home.products.next') }}">{!! $arrowR !!}</button>
                </div>
            </div>
        </div>
        <div class="h-prod-track" data-prod-track>
            @foreach (__('home.products.items') as $p)
                <a href="#enquiry" class="h-prod">
                    <img @class(['h-ghee' => $p['img'] === 'ghee-jar']) src="{{ asset($img.$p['img'].'-800.webp') }}" srcset="{{ $srcset($p['img']) }}" sizes="(min-width: 1100px) 380px, 260px" alt="{{ $p['name'] }}" loading="lazy" decoding="async">
                    <span class="h-prod-cap"><span><small>{{ $p['size'] }}</small><b>{{ $p['name'] }}</b></span><b>{{ $p['price'] }}</b></span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- 7. Home delivery: click steps + plan chips --}}
    <section class="h-sec h-band" id="delivery">
        <div class="h-wave h-wave-page">{!! $wave !!}</div>
        <div class="container h-split h-split-delivery">
            <div data-reveal>
                <p class="h-eyebrow h-gold">{{ __('home.delivery.eyebrow') }}</p>
                <h2 class="h-lg">{{ __('home.delivery.title') }}</h2>
                <div class="h-dsteps" data-dsteps data-step-word="{{ __('home.delivery.step') }}">
                    @foreach ($dsteps as $i => $s)
                        <button type="button" @class(['h-dstep', 'is-active' => $i === 1]) data-i="{{ $i }}" data-src="{{ asset($img.$s['img'].'-800.webp') }}" data-srcset="{{ $srcset($s['img']) }}">
                            <span class="h-dnum">0{{ $i + 1 }}</span><span><b>{{ $s['title'] }}</b><small>{{ $s['text'] }}</small></span>
                        </button>
                        @if ($i === 1)
                            <div class="h-chips" data-chips>
                                @foreach (__('home.delivery.plans') as $j => $plan)
                                    <button type="button" @class(['h-chip', 'is-on' => $j === 0]) aria-pressed="{{ $j === 0 ? 'true' : 'false' }}">{{ $plan }}</button>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
                <a class="h-btn h-btn-accent h-btn-lg h-mt" href="#enquiry">{{ __('home.delivery.cta') }}</a>
            </div>
            <div class="h-dphoto" data-reveal>
                <img data-dimg src="{{ asset($img.'milk-pitcher-800.webp') }}" srcset="{{ $srcset('milk-pitcher') }}" sizes="(min-width: 768px) 45vw, 92vw" alt="{{ $dsteps[1]['title'] }}" loading="lazy" decoding="async">
                <span class="h-dbadge" data-dbadge>{{ __('home.delivery.step') }} 02 / 03</span>
            </div>
        </div>
    </section>

    {{-- 8. Why Yashadhya: golden sunrise photo + quote --}}
    <section class="h-why" id="why">
        <img class="h-why-bg" src="{{ asset($img.'sunrise-farmer-1280.webp') }}" srcset="{{ $srcset('sunrise-farmer') }}" sizes="100vw" alt="{{ __('home.why.image_alt') }}" loading="lazy" decoding="async">
        <div class="h-why-shade" aria-hidden="true"></div>
        <div class="container h-why-inner">
            <p class="h-eyebrow h-cream-gold">{{ __('home.why.eyebrow') }}</p>
            <blockquote data-reveal>{{ __('home.why.quote') }}</blockquote>
            <p class="h-why-by">{{ __('home.why.by') }}</p>
            <ul class="h-promises">
                @foreach (__('home.why.promises') as [$t, $s])
                    <li><b>{{ $t }}</b><span>{{ $s }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 8b. Questions nobody asks: packet milk vs our farm (table; cards on phones) --}}
    <section class="h-sec h-cream" id="compare">
        <div class="h-wave h-wave-dark">{!! $wave !!}</div>
        <div class="container">
            <div class="h-center h-vs-head" data-reveal>
                <p class="h-eyebrow">{{ __('home.questions.eyebrow') }}</p>
                <h2 class="h-xl">{{ __('home.questions.title') }}</h2>
                <p class="h-lead">{{ __('home.questions.text') }}</p>
            </div>
            <div class="h-vs-wrap" data-reveal>
                <table class="h-vs">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('home.questions.col_question') }}</th>
                            <th scope="col" class="h-vs-them">{{ __('home.questions.col_them') }}</th>
                            <th scope="col" class="h-vs-us">{{ __('home.questions.col_us') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (__('home.questions.rows') as $row)
                            <tr>
                                <th scope="row">{{ $row['q'] }}</th>
                                <td class="h-vs-them" data-label="{{ __('home.questions.col_them') }}"><span class="h-vs-cell">@include('website.icons.x-circle-fill') {{ $row['them'] }}</span></td>
                                <td class="h-vs-us" data-label="{{ __('home.questions.col_us') }}"><span class="h-vs-cell">@include('website.icons.check-circle-fill') {{ $row['us'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="h-center mt-4" data-reveal>
                <a class="h-btn h-btn-accent" href="#enquiry">{{ __('home.questions.cta') }}</a>
                <p class="h-vs-note">{{ __('home.questions.note') }}</p>
            </div>
        </div>
    </section>

    {{-- 9. Reviews — SAMPLE text, never shown in production (owner adds real reviews; DECISIONS #204) --}}
    @unless (app()->isProduction())
        <section class="h-sec h-cream h-reviews" id="reviews">
            <div class="container h-center" data-reveal>
                <p class="h-eyebrow">{{ __('home.reviews.eyebrow') }} <span class="h-muted">{{ __('home.reviews.sample') }}</span></p>
                <h2 class="h-xl">{{ __('home.reviews.title') }}</h2>
            </div>
            @php $revs = array_chunk(__('home.reviews.items'), 3); @endphp
            <div class="h-marquee">
                @foreach ($revs as $r => $row)
                    <div @class(['h-marquee-row', 'h-right' => $r === 1]) data-marquee>
                        @foreach ($row as $k => [$text, $by])
                            <figure @class(['h-rev', 'h-rev-band' => $r === 1 && $k === 0])><span class="h-stars" aria-hidden="true">★★★★★</span><blockquote>{{ $text }}</blockquote><figcaption>{{ $by }}</figcaption></figure>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>
    @endunless

    {{-- 10. FAQ: pick a question, answer in a chat box --}}
    <section class="h-sec h-band" id="faq">
        <div class="h-wave h-wave-page">{!! $wave !!}</div>
        @php $faqs = __('home.faq.items'); @endphp
        <div class="container h-split h-split-faq">
            <div data-reveal>
                <p class="h-eyebrow h-gold">{{ __('home.faq.eyebrow') }}</p>
                <h2 class="h-lg">{{ __('home.faq.title_1') }}<br>{{ __('home.faq.title_2') }}</h2>
                <div class="h-faq-list" data-faq>
                    @foreach ($faqs as $i => [$q, $a])
                        <button type="button" @class(['h-faq-q', 'is-on' => $i === 0]) data-a="{{ $a }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ $q }}</button>
                    @endforeach
                </div>
            </div>
            <div class="h-chat" data-reveal>
                <div class="h-chat-head"><span class="h-avatar">{{ mb_substr(__('website.footer.word'), 0, 1) }}</span><span><b>{{ __('home.faq.support') }}</b><small>{{ __('home.faq.reply_time') }}</small></span></div>
                <div class="h-chat-body" data-chat aria-live="polite">
                    <div class="h-bubble h-me">{{ $faqs[0][0] }}</div>
                    <div class="h-bubble h-them">{{ $faqs[0][1] }}</div>
                </div>
                <div class="h-chat-foot"><span>{{ __('home.faq.more') }}</span><a class="h-btn h-btn-accent h-btn-sm" href="{{ $wa }}" target="_blank" rel="noopener">{{ __('home.faq.whatsapp') }}</a></div>
            </div>
        </div>
    </section>

    {{-- 11. Enquiry: glass form over the cow-and-calf photo (UI only until 7A.9 leads) --}}
    <section class="h-enquiry" id="enquiry">
        <img class="h-enq-bg" src="{{ asset($img.'cow-calf-1280.webp') }}" srcset="{{ $srcset('cow-calf') }}" sizes="100vw" alt="{{ __('home.enquiry.image_alt') }}" loading="lazy" decoding="async">
        <div class="h-enq-shade" aria-hidden="true"></div>
        <div class="container h-enq-inner">
            <form class="h-form" data-home-form data-ok="{{ __('home.enquiry.ok') }}" data-bad="{{ __('home.enquiry.bad') }}" novalidate>
                <p class="h-eyebrow h-gold">{{ __('home.enquiry.eyebrow') }}</p>
                <h2 class="h-lg">{{ __('home.enquiry.title_1') }}<br>{{ __('home.enquiry.title_2') }}</h2>
                <label>{{ __('home.enquiry.name') }}<input type="text" name="name" autocomplete="name" placeholder="{{ __('home.enquiry.name_ph') }}" required></label>
                <label>{{ __('home.enquiry.phone') }}<input type="tel" name="phone" inputmode="numeric" autocomplete="tel" placeholder="{{ __('home.enquiry.phone_ph') }}" pattern="[0-9]{10}" required></label>
                <label>{{ __('home.enquiry.interest') }}
                    <select name="interest">@foreach (__('home.enquiry.options') as $o)<option>{{ $o }}</option>@endforeach</select>
                </label>
                <button type="submit" class="h-btn h-btn-accent h-btn-lg">{{ __('home.enquiry.submit') }}</button>
                <p class="h-form-msg" data-form-msg role="status"></p>
                <div class="h-enq-links"><a href="{{ Website::telLink() }}">{{ __('website.action.call') }}</a><span aria-hidden="true">·</span><a href="{{ $wa }}" target="_blank" rel="noopener">{{ __('website.action.whatsapp') }}</a></div>
            </form>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/home.js') }}" defer></script>
@endpush
