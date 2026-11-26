@extends('demo.layout')

{{-- Demo "our herd" page (local only). Text: lang/hi/herd.php — herd counts / names are [placeholders] for the owner. --}}
@php
    $t = trans('herd', [], 'hi');
    $img = 'images/website/home/';
    $wa = 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($t['visit']['whatsapp_text']);
    $icons = [
        'leaf' => '<path d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14zm0 0 7-7"/>',
        'drop' => '<path d="M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
        'cross' => '<rect x="3" y="3" width="18" height="18" rx="5"/><path d="M12 8v8M8 12h8"/>',
        'ban' => '<circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
    ];
@endphp

@section('title', 'हमारे पशु · Yashadhya Organics')
@section('description', $t['meta_description'])

@push('head')
  <link rel="preload" as="image" href="{{ asset($img.'hero-herd-1280.webp') }}" fetchpriority="high">
  <link href="{{ asset('dummyassets/herd.css') }}?v={{ filemtime(public_path('dummyassets/herd.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO: herd photo + counters ============ -->
<section id="top" class="hd-hero">
  <img class="hd-hero-bg" src="{{ asset($img.'hero-herd-1280.webp') }}" srcset="{{ asset($img.'hero-herd-768.webp') }} 768w, {{ asset($img.'hero-herd-1280.webp') }} 1280w, {{ asset($img.'hero-herd-1920.webp') }} 1920w" sizes="100vw" alt="" fetchpriority="high">
  <div class="hd-hero-shade"></div>
  <div class="container-xl hd-hero-inner">
    <div class="d-flex flex-wrap gap-4 mono small-label mb-4 hd-meta">
      <span><span class="blink green">●</span> GOSHALA ONLINE</span><span>वंश · OUR HERD</span>
    </div>
    <h1 class="hd-title display-font"><span class="line"><span class="word">Our</span> <span class="word gold">herd</span></span></h1>
    <p class="hd-hi hi">{{ $t['hero']['title'] }}</p>
    <p class="hd-lead">{{ $t['hero']['text'] }}</p>
  </div>
  <div class="container-xl hd-stats">
    @foreach ($t['stats'] as $s)
      <div class="hd-stat">
        <span class="display-font hd-stat-n" @if ($s['n'] !== null) data-count="{{ $s['n'] }}" @endif>{{ $s['n'] ?? '[N]' }}</span>
        <b class="hi">{{ $s['label'] }}</b>
        <small>{{ $s['note'] }}</small>
      </div>
    @endforeach
  </div>
</section>

<!-- ============ A2 explainer: one bead in the protein chain ============ -->
<section class="hd-a2 bg-cream text-ink section-pad">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5">
        <span class="mono small-label brown"><span class=\"ls-0\">{{ $t['a2']['eyebrow'] }}</span> · A1 vs A2</span>
        <h2 class="chapter-title display-font mt-2">One link<br><span class="hi brown">{{ $t['a2']['title'] }}</span></h2>
        <p class="mt-3 fs-5">{{ $t['a2']['text'] }}</p>
        <small class="muted">{{ $t['a2']['note'] }}</small>
      </div>
      <div class="col-lg-7">
        <div class="hd-chains" data-hd-chains>
          @foreach (['a1', 'a2'] as $type)
            <div class="hd-chain hd-{{ $type }}">
              <span class="mono small-label">{{ $t['a2'][$type] }}</span>
              <svg viewBox="0 0 560 70" role="img" aria-label="{{ $t['a2'][$type] }}">
                <line x1="20" y1="35" x2="540" y2="35" class="hd-link"/>
                @for ($b = 0; $b < 11; $b++)
                  <circle cx="{{ 30 + $b * 50 }}" cy="35" r="{{ $b === 6 ? 22 : 15 }}" @class(['hd-bead', 'hd-key' => $b === 6])/>
                @endfor
                <text x="330" y="40" text-anchor="middle" class="hd-key-label">{{ $type === 'a1' ? 'His' : 'Pro' }}</text>
              </svg>
            </div>
          @endforeach
          <p class="mono small-label mt-2 mb-0">β-CASEIN · POSITION 67</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ BREEDS: cards stack on scroll ============ -->
<section id="breeds" class="hd-breeds section-pad">
  <div class="container-xl">
    <div class="chapter-head reveal">
      <span class="chapter-num display-font">03</span>
      <div>
        <span class="mono small-label gold">वंश · LINEAGE</span>
        <h2 class="chapter-title display-font">Four breeds<br><span class="hi gold">{{ $t['breeds_title'] }}</span></h2>
      </div>
    </div>
    <div class="hd-stack mt-5">
      @foreach ($t['breeds'] as $i => $b)
        <article class="hd-card" style="--i: {{ $i }}">
          <div class="hd-card-media">
            <img src="{{ asset($img.$b['img'].'-800.webp') }}" srcset="{{ asset($img.$b['img'].'-480.webp') }} 480w, {{ asset($img.$b['img'].'-800.webp') }} 800w" sizes="(min-width: 992px) 45vw, 92vw" alt="{{ $b['name'] }}" loading="lazy" decoding="async">
            <span class="hd-tag">{{ $b['tag'] }}</span>
          </div>
          <div class="hd-card-body">
            <span class="mono small-label">NO.0{{ $i + 1 }} / 04</span>
            <h3><span class="display-font">{{ $b['en'] }}</span><span class="hi gold">{{ $b['name'] }}</span></h3>
            <dl>
              @foreach (['from', 'look', 'milk', 'nature', 'ours'] as $k)
                <div><dt class="mono small-label">{{ $t['labels'][$k] }}</dt><dd>{{ $b[$k] }}</dd></div>
              @endforeach
            </dl>
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ FAMILY: flip cards (front photo + name, back details) ============ -->
<section class="hd-family section-pad pt-0">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <span class="mono small-label gold"><span class="ls-0">{{ $t['family']['eyebrow'] }}</span> · MEET THE FAMILY</span>
        <h2 class="chapter-title display-font mt-2">Say hello<br><span class="hi gold">{{ $t['family']['title'] }}</span></h2>
      </div>
      <span class="hd-hint hi">{{ $t['family']['hint'] }} ↻</span>
    </div>
    <div class="hd-family-row mt-4" data-hd-family>
      @foreach ($t['family']['items'] as $c)
        <button type="button" class="hd-flip" aria-pressed="false">
          <span class="hd-flip-inner">
            <span class="hd-flip-front">
              <img src="{{ asset($img.$c['img'].'-480.webp') }}" alt="" loading="lazy" decoding="async">
              <span class="hd-flip-cap"><b class="hi">{{ $c['name'] }}</b><small class="hi">{{ $c['breed'] }}</small></span>
            </span>
            <span class="hd-flip-back">
              <b class="hi">{{ $c['name'] }}</b>
              @foreach (['age', 'calves', 'likes', 'nature'] as $k)
                <span class="hd-fact"><small class="hi">{{ $t['family']['labels'][$k] }}</small><span class="hi">{{ $c[$k] }}</span></span>
              @endforeach
            </span>
          </span>
        </button>
      @endforeach
    </div>
    <p class="hd-note hi mt-3 mb-0">{{ $t['family']['note'] }}</p>
  </div>
</section>

<!-- ============ A DAY AT THE GOSHALA: line draws as you scroll ============ -->
<section class="hd-day section-pad">
  <span class="hd-sky-sun" aria-hidden="true"></span>
  <div class="container-xl">
    <span class="mono small-label gold"><span class=\"ls-0\">{{ $t['day']['eyebrow'] }}</span> · A DAY</span>
    <h2 class="chapter-title display-font mt-2">Dawn to dusk<br><span class="hi gold">{{ $t['day']['title'] }}</span></h2>
    <ol class="hd-timeline mt-5" data-hd-timeline>
      <span class="hd-line" aria-hidden="true"><span></span></span>
      @foreach ($t['day']['items'] as $d)
        <li><time class="display-font">{{ $d['t'] }}</time><div><b class="hi">{{ $d['title'] }}</b><p>{{ $d['text'] }}</p></div></li>
      @endforeach
    </ol>
  </div>
</section>

<!-- ============ CARE promises ============ -->
<section class="hd-care bg-cream text-ink section-pad">
  <div class="container-xl">
    <span class="mono small-label brown"><span class=\"ls-0\">{{ $t['care']['eyebrow'] }}</span> · CARE</span>
    <h2 class="chapter-title display-font mt-2">Good care<br><span class="hi brown">{{ $t['care']['title'] }}</span></h2>
    <div class="hd-care-grid mt-5">
      @foreach ($t['care']['items'] as $c)
        <div class="hd-care-item">
          <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$c['icon']] !!}</svg>
          <b class="hi">{{ $c['title'] }}</b>
          <p>{{ $c['text'] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ CALVES: Gauri + photos ============ -->
<section class="hd-calves section-pad">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5 text-center text-lg-start">
        <button type="button" class="hd-gauri-btn" data-hd-gauri data-facts="{{ json_encode($t['gauri_facts'], JSON_UNESCAPED_UNICODE) }}" aria-label="{{ $t['gauri_hint'] }}">
          <span class="hd-bubble hi" aria-live="polite">{{ $t['gauri_hint'] }} 👆</span>
          <svg class="gauri hd-gauri" viewBox="0 0 1312 1199" role="img" aria-label="Gauri, the Yashadhya calf">
          <image href="{{ asset('dummyassets/images/gauri-body.webp') }}" width="1312" height="1199"/>
          <g class="gauri-head"><image href="{{ asset('dummyassets/images/gauri-head.webp') }}" width="1312" height="1199"/></g>
        </svg>
        </button>
        <span class="mono small-label gold d-block mt-3"><span class=\"ls-0\">{{ $t['calves']['eyebrow'] }}</span> · CALVES</span>
        <h2 class="chapter-title display-font">Little ones<br><span class="hi gold">{{ $t['calves']['title'] }}</span></h2>
        <p class="hd-lead">{{ $t['calves']['text'] }}</p>
      </div>
      <div class="col-lg-7">
        <div class="hd-photos">
          <img src="{{ asset($img.'cow-calf-768.webp') }}" srcset="{{ asset($img.'cow-calf-768.webp') }} 768w, {{ asset($img.'cow-calf-1280.webp') }} 1280w" sizes="(min-width: 992px) 40vw, 90vw" alt="Cow with her calf" loading="lazy" decoding="async">
          <img src="{{ asset($img.'farmer-calf-640.webp') }}" srcset="{{ asset($img.'farmer-calf-640.webp') }} 640w, {{ asset($img.'farmer-calf-1024.webp') }} 1024w" sizes="(min-width: 992px) 26vw, 60vw" alt="Farmer with a calf" loading="lazy" decoding="async">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ VISIT ============ -->
<section class="hd-visit">
  <img class="hd-visit-bg" src="{{ asset($img.'sunset-cows-1280.webp') }}" srcset="{{ asset($img.'sunset-cows-768.webp') }} 768w, {{ asset($img.'sunset-cows-1280.webp') }} 1280w, {{ asset($img.'sunset-cows-1920.webp') }} 1920w" sizes="100vw" alt="" loading="lazy" decoding="async">
  <div class="hd-hero-shade"></div>
  <div class="container-xl text-center hd-visit-inner">
    <span class="mono small-label gold"><span class=\"ls-0\">{{ $t['visit']['eyebrow'] }}</span> · VISIT</span>
    <h2 class="epi-title display-font mt-3">Come say hi<br><span class="hi gold">{{ $t['visit']['title'] }}</span></h2>
    <p class="hd-lead mx-auto mt-3">{{ $t['visit']['text'] }}</p>
    <a class="btn btn-gold btn-lg rounded-pill px-4 mt-3 magnetic" href="{{ $wa }}" target="_blank" rel="noopener">WhatsApp पर बताएं →</a>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/herd.js') }}?v={{ filemtime(public_path('dummyassets/herd.js')) }}" defer></script>
@endpush
