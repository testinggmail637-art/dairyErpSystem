@extends('demo.layout')

{{-- Demo Blog list (local only) — "goshala diary" look: paper sheet on a dark desk, taped polaroids, index tabs.
     Text + sample articles: lang/hi/demo/blog.php. Search, category tabs and "more" run in the browser. --}}
@php
    $t = trans('demo/blog', [], 'hi');
    $pic = fn ($n, $w) => asset("images/website/home/{$n}-{$w}.webp");
    $mins = fn ($p) => max(1, (int) ceil(collect($p['body'])->flatten()->map(fn ($s) => count(preg_split('/\s+/u', trim($s))))->sum() / 180));
    $url = fn ($p) => route('demo', 'article').'?a='.$p['slug'];
    $day = fn ($p) => \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $p['date'])->locale('hi');
    [$top, $rest] = [$t['posts'][0], array_slice($t['posts'], 1)];
    $perPage = 4;
    $wa = 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($t['weekly']['msg']);
@endphp

@section('title', 'लेख · Yashadhya Organics')
@section('description', $t['meta_description'])

@push('head')
  <link href="https://fonts.googleapis.com/css2?family=Kalam:wght@400;700&display=swap" rel="stylesheet">
  <link href="{{ asset('dummyassets/blog.css') }}?v={{ filemtime(public_path('dummyassets/blog.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO: an open diary page on the desk ============ -->
<section id="top" class="bl-desk bl-hero">
  <div class="container-xl">
    <div class="bl-sheet">
      <span class="bl-stamp" aria-hidden="true"><span>READ<br>SLOW</span><small>लेख · {{ count($t['posts']) }}</small></span>
      <div class="row g-5 align-items-center">
        <div class="col-lg-7">
          <span class="bl-hand bl-date">{{ now('Asia/Kolkata')->locale('hi')->translatedFormat('j F, l') }}</span>
          <h1 class="bl-title hi">{{ $t['hero']['title'] }}</h1>
          <p class="bl-hand bl-lead">{{ $t['hero']['text'] }}</p>
          <label class="visually-hidden" for="blQ">{{ $t['hero']['search'] }}</label>
          <div class="bl-search">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 20l1-4L16 5l3 3L8 19l-4 1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 7l3 3" stroke="currentColor" stroke-width="1.8"/></svg>
            <input id="blQ" class="bl-hand" type="search" placeholder="{{ $t['hero']['search'] }}" maxlength="60" autocomplete="off" data-bl-q>
          </div>
        </div>
        <div class="col-lg-5">
          <a class="bl-polaroid bl-polaroid--big" href="{{ $url($top) }}">
            <span class="bl-tape" aria-hidden="true"></span>
            <span class="bl-photo"><img src="{{ $pic($top['img'], 800) }}" srcset="{{ $pic($top['img'], 480) }} 480w, {{ $pic($top['img'], 800) }} 800w" sizes="(min-width: 992px) 36vw, 86vw" alt="" fetchpriority="high"></span>
            <span class="bl-hand bl-cap">{{ $t['featured'] }} ↓</span>
            <span class="bl-ptitle hi">{{ $top['title'] }}</span>
            <span class="bl-hand bl-pmeta">{{ $day($top)->translatedFormat('j M') }} · {{ $mins($top) }} {{ $t['min'] }} · {{ $t['read'] }} →</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ BOARD: index tabs + pinned polaroids ============ -->
<section class="bl-desk bl-list">
  <div class="container-xl">
    <div class="bl-tabs" role="radiogroup" aria-label="Category">
      <button type="button" class="bl-tab hi" role="radio" aria-checked="true" data-bl-cat="">{{ $t['all'] }}</button>
      @foreach ($t['categories'] as $key => $name)
        <button type="button" class="bl-tab bl-c-{{ $key }} hi" role="radio" aria-checked="false" data-bl-cat="{{ $key }}">{{ $name }}</button>
      @endforeach
    </div>
    <div class="bl-board">
      <div class="bl-grid" data-bl-grid data-per-page="{{ $perPage }}" aria-live="polite">
        @foreach ($rest as $p)
          <a class="bl-polaroid bl-card" href="{{ $url($p) }}" data-cat="{{ $p['cat'] }}" data-text="{{ $p['title'] }} {{ $p['excerpt'] }} {{ $t['categories'][$p['cat']] }}">
            <span class="bl-tape" aria-hidden="true"></span>
            <span class="bl-photo"><img src="{{ $pic($p['img'], 480) }}" srcset="{{ $pic($p['img'], 480) }} 480w, {{ $pic($p['img'], 800) }} 800w" sizes="(min-width: 992px) 28vw, (min-width: 576px) 44vw, 86vw" alt="" loading="lazy" decoding="async"></span>
            <span class="bl-hand bl-cap"><i class="bl-dot bl-c-{{ $p['cat'] }}"></i>{{ $t['categories'][$p['cat']] }}</span>
            <span class="bl-ptitle hi">{{ $p['title'] }}</span>
            <span class="bl-excerpt hi">{{ $p['excerpt'] }}</span>
            <span class="bl-hand bl-pmeta">{{ $day($p)->translatedFormat('j M') }} · {{ $mins($p) }} {{ $t['min'] }}</span>
          </a>
        @endforeach
      </div>
      <p class="bl-none bl-hand d-none" data-bl-none>{{ $t['hero']['none'] }}</p>
      <div class="text-center mt-5"><button type="button" class="bl-more bl-hand d-none" data-bl-more>{{ $t['more'] }} ↓</button></div>
      <p class="bl-note hi mt-4 mb-0 text-center">{{ $t['sample_note'] }}</p>
    </div>
  </div>
</section>

<!-- ============ POSTCARD: weekly on WhatsApp ============ -->
<section class="bl-desk bl-post-sec">
  <div class="container-xl">
    <div class="bl-postcard">
      <div class="bl-post-msg">
        <span class="bl-hand bl-cap">{{ $t['weekly']['eyebrow'] }} · EVERY SUNDAY</span>
        <h2 class="bl-h2 hi">{{ $t['weekly']['title'] }}</h2>
        <p class="bl-hand mb-0">{{ $t['weekly']['text'] }}</p>
      </div>
      <div class="bl-post-to">
        <span class="bl-postage" aria-hidden="true"><span>🐄</span><small>गौ डाक</small></span>
        <span class="bl-postmark" aria-hidden="true">YASHADHYA<br>{{ now('Asia/Kolkata')->format('d·m·y') }}</span>
        <a class="btn btn-gold rounded-pill px-4 py-3 hi magnetic mt-auto" href="{{ $wa }}" target="_blank" rel="noopener">{{ $t['weekly']['cta'] }} →</a>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/blog.js') }}?v={{ filemtime(public_path('dummyassets/blog.js')) }}" defer></script>
@endpush
