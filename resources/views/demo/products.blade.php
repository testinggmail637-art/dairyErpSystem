@extends('demo.layout')

{{-- Demo products page (local only). Text from lang/{hi,en}/products.php; no prices — delivery has not started (DECISIONS #211). --}}
@php
    $t = trans('products', [], 'hi');
    $en = trans('products.items', [], 'en');
    $img = 'images/website/home/';
    $srcset = fn ($n) => collect([480, 800, 1200])->map(fn ($x) => asset("{$img}{$n}-{$x}.webp")." {$x}w")->implode(', ');
    $wa = fn ($text) => 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($text);
    $cats = ['all', 'milk', 'ghee', 'dahi-paneer'];
@endphp

@section('title', 'संग्रह · Yashadhya Organics')
@section('description', $t['meta_description'])

@push('head')
  <link rel="preload" as="image" href="{{ asset($img.'milk-bottle-800.webp') }}" fetchpriority="high">
  <link href="{{ asset('dummyassets/products.css') }}?v={{ filemtime(public_path('dummyassets/products.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO ============ -->
<section id="top" class="pp-hero">
  <div class="container-xl">
    <div class="d-flex flex-wrap gap-4 mono small-label mb-4 pp-meta">
      <span><span class="blink green">●</span> GOSHALA ONLINE</span><span>संग्रह · THE COLLECTION</span><span>05 PRODUCTS · 0 ADDITIVES</span>
    </div>
    <h1 class="pp-title display-font">
      <span class="line"><span class="word">Made</span> <span class="word outline">this</span></span>
      <span class="line"><span class="word gold">morning</span></span>
    </h1>
    <p class="pp-hi hi">{{ $t['intro']['title'] }}</p>
    <p class="pp-lead">{{ $t['intro']['text'] }}</p>
    <a class="pp-notice" href="#join">
      <span class="pp-pulse" aria-hidden="true"></span>
      <span>{{ $t['intro']['notice'] }}</span>
      <b>{{ $t['intro']['notice_cta'] }} →</b>
    </a>
  </div>
  <span class="pp-ghost display-font" aria-hidden="true">02</span>
</section>

<!-- ============ MARQUEE ============ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track display-font">
    @foreach ([1, 2] as $_)
      @foreach ($t['items'] as $i => $p)
        <span>{{ $en[$i]['name'] }}</span><span class="hi gold">{{ $p['name'] }}</span>
      @endforeach
    @endforeach
  </div>
</div>

<!-- ============ FILTER + PRODUCT ROWS ============ -->
<section class="pp-list" id="products">
  <div class="pp-filter" role="group" aria-label="{{ $t['filter']['label'] }}" data-pp-filter>
    <div class="container-xl d-flex gap-2">
      @foreach ($cats as $cat)
        <button type="button" @class(['chip', 'is-on' => $cat === 'all']) data-cat="{{ $cat }}" aria-pressed="{{ $cat === 'all' ? 'true' : 'false' }}">{{ $t['filter'][$cat] }}</button>
      @endforeach
      <span class="pp-count mono small-label ms-auto d-none d-md-inline"><span data-pp-count>{{ count($t['items']) }}</span> / {{ count($t['items']) }}</span>
    </div>
  </div>

  <div class="container-xl">
    @foreach ($t['items'] as $i => $p)
      <article @class(['pp-row', 'pp-flip' => $i % 2 === 1]) id="{{ $p['slug'] }}" data-cat="{{ $p['cat'] }}">
        <figure class="pp-media">
          <img @class(['pp-ghee' => $p['img'] === 'ghee-jar']) src="{{ asset($img.$p['img'].'-800.webp') }}" srcset="{{ $srcset($p['img']) }}" sizes="(min-width: 992px) 46vw, 92vw" alt="{{ $p['name'] }}" width="800" height="1200" @if ($i > 0) loading="lazy" @endif decoding="async">
          <span class="pp-soon">{{ $t['soon'] }}</span>
          <span class="pp-num display-font" aria-hidden="true">0{{ $i + 1 }}</span>
        </figure>
        <div class="pp-info">
          <span class="mono small-label gold">NO.0{{ $i + 1 }} · <span class="ls-0">{{ $t['filter'][$p['cat']] }}</span></span>
          <h2 class="pp-name"><span class="display-font">{{ $en[$i]['name'] }}</span><span class="hi gold">{{ $p['name'] }}</span></h2>
          <p class="pp-sizes">@foreach ($p['sizes'] as $size)<span class="mono">{{ $size }}</span>@endforeach</p>
          <p class="pp-text">{{ $p['text'] }}</p>
          <ul class="pp-points">
            @foreach ($p['points'] as $point)<li>{{ $point }}</li>@endforeach
          </ul>
          <a class="btn btn-gold rounded-pill px-4 magnetic" href="{{ $wa(str_replace(':name', $p['name'], $t['notify_text'])) }}" target="_blank" rel="noopener">{{ $t['notify'] }} →</a>
        </div>
      </article>
    @endforeach
  </div>

  {{-- side navigator (desktop): which product is on screen, click to jump --}}
  <nav class="pp-dots" aria-label="{{ $t['intro']['eyebrow'] }}" data-pp-dots>
    @foreach ($t['items'] as $i => $p)
      <a href="#{{ $p['slug'] }}" data-target="{{ $p['slug'] }}"><span class="pp-dot-name hi">{{ $p['name'] }}</span><span class="mono">0{{ $i + 1 }}</span></a>
    @endforeach
  </nav>
</section>

<!-- ============ FREE FROM: stamps ============ -->
<section class="pp-free bg-cream text-ink section-pad">
  <div class="container-xl">
    <span class="mono small-label brown"><span class=\"ls-0\">{{ $t['free']['eyebrow'] }}</span> · FREE FROM</span>
    <h2 class="chapter-title display-font mt-2">Nothing added<br><span class="hi brown">{{ $t['free']['title'] }}</span></h2>
    <ul class="pp-stamps">
      @foreach ($t['free']['items'] as $i => $thing)
        <li><span class="pp-no hi">{{ $t['free']['no'] }}</span><b class="hi">{{ $thing }}</b><span class="mono small-label">0{{ $i + 1 }} / 04</span></li>
      @endforeach
    </ul>
  </div>
</section>

<!-- ============ JOIN ============ -->
<section id="join" class="pp-join section-pad">
  <div class="container-xl text-center">
    <span class="mono small-label gold"><span class=\"ls-0\">{{ $t['join']['eyebrow'] }}</span> · BE THE FIRST</span>
    <h2 class="epi-title display-font mt-3">Be the first<br><span class="hi gold">{{ $t['join']['title'] }}</span></h2>
    <p class="pp-lead mx-auto mt-3">{{ $t['join']['text'] }}</p>
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
      <a class="btn btn-gold btn-lg rounded-pill px-4 magnetic" href="{{ $wa($t['join']['whatsapp_text']) }}" target="_blank" rel="noopener">WhatsApp →</a>
      <a class="btn btn-outline-cream btn-lg rounded-pill px-4 magnetic" href="tel:+91XXXXXXXXXX">Call</a>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/products.js') }}?v={{ filemtime(public_path('dummyassets/products.js')) }}" defer></script>
@endpush
