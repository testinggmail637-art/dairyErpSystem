@extends('demo.layout')

{{-- Demo About page (local only). Text: lang/hi/about.php — story, names, years and place are [placeholders] for the owner. --}}
@php
    $t = trans('about', [], 'hi');
    $img = 'images/website/home/';
    $pic = fn ($n, $w) => asset("{$img}{$n}-{$w}.webp");
    $journeyImg = [['cow-calf', 768], ['glass-bottles', 480], ['hero-herd', 768], ['milk-pitcher', 480], ['bilona-churning', 480]];
    $wa = 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($t['place']['whatsapp_text']);
@endphp

@section('title', 'हमारे बारे में · Yashadhya Organics')
@section('description', $t['meta_description'])

@push('head')
  <link rel="preload" as="image" href="{{ $pic('sunrise-farmer', 1280) }}" fetchpriority="high">
  <link href="{{ asset('dummyassets/about.css') }}?v={{ filemtime(public_path('dummyassets/about.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO: split — words left, sunrise photo right ============ -->
<section id="top" class="ab-hero">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <div class="d-flex flex-wrap gap-4 mono small-label mb-4 ab-meta"><span><span class="blink green">●</span> GOSHALA ONLINE</span><span><span class="ls-0">कहानी</span> · OUR STORY</span></div>
        <h1 class="ab-title display-font"><span class="line"><span class="word">Our</span></span><span class="line"><span class="word gold">story</span></span></h1>
        <p class="ab-hi hi">{{ $t['hero']['title'] }}</p>
        <p class="ab-lead">{{ $t['hero']['text'] }}</p>
      </div>
      <div class="col-lg-6">
        <figure class="ab-hero-photo">
          <img src="{{ $pic('sunrise-farmer', 1280) }}" srcset="{{ $pic('sunrise-farmer', 768) }} 768w, {{ $pic('sunrise-farmer', 1280) }} 1280w" sizes="(min-width: 992px) 48vw, 92vw" alt="Farmer with cows at sunrise" fetchpriority="high">
          <figcaption class="ab-now" data-ab-now data-schedule="{{ json_encode($t['now']['schedule'], JSON_UNESCAPED_UNICODE) }}">
            <span class="ab-now-dot" aria-hidden="true"></span>
            <span class="mono" data-ab-clock>--:--</span>
            <span class="hi"><b>{{ $t['now']['label'] }}:</b> <span data-ab-status>…</span></span>
          </figcaption>
        </figure>
      </div>
    </div>
  </div>
</section>

<!-- ============ MANIFESTO: words light up as you scroll ============ -->
<section class="ab-manifesto bg-cream text-ink">
  <div class="container-xl">
    <span class="mono small-label brown">MANIFESTO</span>
    <p class="promise-text hi mt-3" data-split-words>{{ $t['manifesto'] }}</p>
  </div>
</section>

<!-- ============ LETTER from the founder ============ -->
<section class="ab-letter-sec section-pad">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-4">
        <span class="mono small-label gold"><span class="ls-0">{{ $t['letter']['eyebrow'] }}</span> · A LETTER</span>
        <h2 class="chapter-title display-font mt-2">Why we<br>began<br><span class="hi gold">{{ $t['letter']['title'] }}</span></h2>
      </div>
      <div class="col-lg-8">
        <article class="ab-letter" data-ab-letter>
          <span class="ab-stamp mono" aria-hidden="true">YASHADHYA<br>· EST [YEAR] ·</span>
          <p class="ab-hello hi">{{ $t['letter']['hello'] }}</p>
          @foreach ($t['letter']['body'] as $line)
            <p class="hi">{{ $line }}</p>
          @endforeach
          <svg class="ab-sign" viewBox="0 0 240 60" aria-hidden="true"><path d="M6 44c18-30 30-34 34-24s-10 24-2 22 20-30 30-28-6 26 4 24 18-22 28-20 0 18 10 16 22-14 34-16 20 6 32 2 28-14 50-12"/></svg>
          <p class="ab-signer"><b class="hi">{{ $t['letter']['sign'] }}</b><small class="hi">{{ $t['letter']['role'] }}</small></p>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- ============ JOURNEY: sideways track of milestones ============ -->
<section class="ab-journey section-pad pt-0">
  <div class="container-xl d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
      <span class="mono small-label gold"><span class="ls-0">{{ $t['journey']['eyebrow'] }}</span> · JOURNEY</span>
      <h2 class="chapter-title display-font mt-2">The road<br><span class="hi gold">{{ $t['journey']['title'] }}</span></h2>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="ab-hint hi d-none d-md-inline">{{ $t['journey']['hint'] }}</span>
      <button type="button" class="ab-arrow" data-ab-prev aria-label="पिछला">←</button>
      <button type="button" class="ab-arrow" data-ab-next aria-label="अगला">→</button>
    </div>
  </div>
  <div class="ab-track" data-ab-track>
    @foreach ($t['journey']['items'] as $i => $j)
      <article class="ab-step">
        <img src="{{ $pic($journeyImg[$i][0], $journeyImg[$i][1]) }}" alt="" loading="lazy" decoding="async">
        <div class="ab-step-body">
          <span class="ab-year display-font">{{ $j['year'] }}</span>
          <b class="hi">{{ $j['title'] }}</b>
          <p class="hi">{{ $j['text'] }}</p>
        </div>
        <span class="ab-step-no mono">0{{ $i + 1 }}</span>
      </article>
    @endforeach
  </div>
  <div class="container-xl"><div class="ab-progress" aria-hidden="true"><span data-ab-progress></span></div></div>
</section>

<!-- ============ MEMORIES: polaroids you can drag around ============ -->
<section class="ab-memories section-pad pt-0">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <span class="mono small-label gold"><span class="ls-0">{{ $t['memories']['eyebrow'] }}</span> · MEMORIES</span>
        <h2 class="chapter-title display-font mt-2">Moments<br><span class="hi gold">{{ $t['memories']['title'] }}</span></h2>
      </div>
      <span class="ab-hint hi">{{ $t['memories']['hint'] }} ✋</span>
    </div>
    <div class="ab-wall mt-4" data-ab-wall>
      @foreach ($t['memories']['items'] as $i => $m)
        <figure class="ab-polaroid" style="--r: {{ [-6, 4, -3, 7, -5, 3][$i] }}deg; --x: {{ [2, 22, 42, 62, 12, 50][$i] }}%; --y: {{ [4, 0, 8, 2, 46, 44][$i] }}%">
          <img src="{{ $pic($m['img'], $m['w']) }}" alt="" loading="lazy" decoding="async" draggable="false">
          <figcaption class="hi">{{ $m['cap'] }}</figcaption>
        </figure>
      @endforeach
    </div>
    <p class="ab-note hi mt-3 mb-0">{{ $t['memories']['note'] }}</p>
  </div>
</section>

<!-- ============ VALUES ============ -->
<section class="ab-values bg-cream text-ink section-pad">
  <div class="container-xl">
    <span class="mono small-label brown"><span class="ls-0">{{ $t['values']['eyebrow'] }}</span> · VALUES</span>
    <h2 class="chapter-title display-font mt-2">Three rules<br><span class="hi brown">{{ $t['values']['title'] }}</span></h2>
    <ol class="ab-value-list mt-5">
      @foreach ($t['values']['items'] as $i => $v)
        <li><span class="ab-vnum display-font">0{{ $i + 1 }}</span><b class="hi">{{ $v['title'] }}</b><p class="hi">{{ $v['text'] }}</p></li>
      @endforeach
    </ol>
  </div>
</section>

<!-- ============ TEAM ============ -->
<section class="ab-team section-pad">
  <div class="container-xl">
    <span class="mono small-label gold"><span class="ls-0">{{ $t['team']['eyebrow'] }}</span> · THE PEOPLE</span>
    <h2 class="chapter-title display-font mt-2">The people<br><span class="hi gold">{{ $t['team']['title'] }}</span></h2>
    <div class="ab-team-grid mt-5">
      @foreach ($t['team']['items'] as $m)
        <div class="ab-person">
          <span class="ab-avatar display-font" aria-hidden="true">{{ mb_substr(trim($m['role']), 0, 1) }}</span>
          <b class="hi">{{ $m['name'] }}</b>
          <small class="hi">{{ $m['role'] }}</small>
        </div>
      @endforeach
    </div>
    <p class="ab-note hi mt-3 mb-0">{{ $t['team']['note'] }}</p>
  </div>
</section>

<!-- ============ PLACE: stylised map card (no map embed — keeps the page fast) ============ -->
<section class="ab-place section-pad pt-0">
  <div class="container-xl">
    <div class="ab-map">
      <svg class="ab-map-art" viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <path class="ab-road" d="M-20 330 C 120 300 160 220 260 230 S 420 300 470 210 S 560 80 640 60"/>
        <path class="ab-river" d="M-20 120 C 80 150 160 90 240 120 S 380 180 640 140"/>
        <g class="ab-fields"><rect x="40" y="200" width="90" height="60" rx="6"/><rect x="150" y="270" width="70" height="50" rx="6"/><rect x="330" y="80" width="100" height="55" rx="6"/><rect x="480" y="250" width="80" height="70" rx="6"/></g>
      </svg>
      <span class="ab-pin" aria-hidden="true"><span></span></span>
      <div class="ab-map-card">
        <span class="mono small-label gold"><span class="ls-0">{{ $t['place']['eyebrow'] }}</span> · FIND US</span>
        <h2 class="hi mt-2">{{ $t['place']['title'] }}</h2>
        <ul>
          <li class="hi"><b>{{ $t['place']['village'] }}</b>, {{ $t['place']['district'] }}</li>
          <li class="hi">{{ $t['place']['distance'] }}</li>
          <li class="hi">{{ $t['place']['timing'] }}</li>
        </ul>
        <a class="btn btn-gold rounded-pill px-4 magnetic" href="{{ $wa }}" target="_blank" rel="noopener">{{ $t['place']['cta'] }} →</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ END ============ -->
<section class="ab-end section-pad text-center">
  <div class="container-xl">
    <h2 class="epi-title display-font">Join the<br>family<span class="hi gold">{{ $t['end']['title'] }}</span></h2>
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
      <a class="btn btn-gold btn-lg rounded-pill px-4 magnetic" href="{{ route('demo', 'herd') }}">{{ $t['end']['herd'] }} →</a>
      <a class="btn btn-outline-cream btn-lg rounded-pill px-4 magnetic" href="{{ route('demo', 'products') }}">{{ $t['end']['products'] }}</a>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/about.js') }}?v={{ filemtime(public_path('dummyassets/about.js')) }}" defer></script>
@endpush
