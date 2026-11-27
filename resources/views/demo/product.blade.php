@extends('demo.layout')

{{-- Demo product detail (local only): /demo/product?p=<slug>. Text: lang/hi/demo/products.php → items + detail.
     No prices — delivery has not started (DECISIONS #211); the picker only builds a "tell me" WhatsApp message. --}}
@php
    $t = trans('demo/products', [], 'hi');
    $d = $t['detail'];
    $items = collect($t['items']);
    $i = $items->search(fn ($p) => $p['slug'] === (string) request()->query('p', $items->first()['slug']));
    abort_if($i === false, 404);
    $p = $items[$i];
    $x = $d['items'][$p['slug']];
    $enName = trans('demo/products.items', [], 'en')[$i]['name'];
    $img = 'images/website/home/';
    $pic = fn ($n, $w) => asset("{$img}{$n}-{$w}.webp");
    $srcset = fn ($n) => "{$pic($n, 480)} 480w, {$pic($n, 800)} 800w";
    $plans = $d['plans'][$p['cat'] === 'milk' ? 'milk' : 'other'];
    $url = fn ($s) => route('demo', 'product').'?p='.$s;
    $msg = fn ($size, $qty, $plan) => strtr($d['order_text'], [':name' => $p['name'], ':size' => $size, ':qty' => $qty, ':plan' => $plan]);
    $wa = fn ($text) => 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($text);
    $more = $items->reject(fn ($o) => $o['slug'] === $p['slug'])->sortByDesc(fn ($o) => $o['cat'] === $p['cat'])->values();
@endphp

@section('title', $p['name'].' · Yashadhya Organics')
@section('description', $x['tagline'].' '.$p['text'])

@push('head')
  <link rel="preload" as="image" href="{{ $pic($x['gallery'][0], 800) }}" fetchpriority="high">
  <link href="{{ asset('dummyassets/products.css') }}?v={{ filemtime(public_path('dummyassets/products.css')) }}" rel="stylesheet">
  <link href="{{ asset('dummyassets/product.css') }}?v={{ filemtime(public_path('dummyassets/product.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO: gallery + picker ============ -->
<section id="top" class="pd-hero">
  <div class="container-xl">
    <a class="pd-back mono small-label" href="{{ route('demo', 'products') }}">← <span class="ls-0">{{ $d['back'] }}</span></a>
    <div class="pd-grid">
      <div class="pd-gallery" data-pd-gallery>
        <figure class="pd-stage" data-pd-stage>
          @foreach ($x['gallery'] as $g => $photo)
            <img @class(['is-on' => $g === 0, 'pp-ghee' => $photo === 'ghee-jar']) src="{{ $pic($photo, 800) }}" srcset="{{ $srcset($photo) }}" sizes="(min-width: 992px) 52vw, 92vw"
                 alt="{{ $p['name'] }} — {{ $d['photo'] }} {{ $g + 1 }}" width="800" height="1000" @if ($g > 0) loading="lazy" @endif decoding="async">
          @endforeach
          <span class="pp-soon">{{ $t['soon'] }}</span>
          <span class="pd-no display-font" aria-hidden="true">0{{ $i + 1 }}</span>
        </figure>
        <div class="pd-thumbs" role="group" aria-label="{{ $d['photo'] }}">
          @foreach ($x['gallery'] as $g => $photo)
            <button type="button" @class(['pd-thumb', 'is-on' => $g === 0]) data-pd-thumb="{{ $g }}" aria-pressed="{{ $g === 0 ? 'true' : 'false' }}" aria-label="{{ $d['photo'] }} {{ $g + 1 }}">
              <img @class(['pp-ghee' => $photo === 'ghee-jar']) src="{{ $pic($photo, 480) }}" alt="" width="96" height="96" loading="lazy" decoding="async">
            </button>
          @endforeach
          <span class="pd-photo-note">{{ $d['photo_note'] }}</span>
        </div>
      </div>

      <div class="pd-info">
        <span class="mono small-label gold">NO.0{{ $i + 1 }} · <span class="ls-0">{{ $t['filter'][$p['cat']] }}</span></span>
        <h1 class="pd-name"><span class="display-font">{{ $enName }}</span><span class="hi gold">{{ $p['name'] }}</span></h1>
        <p class="pd-tagline hi">{{ $x['tagline'] }}</p>
        <p class="pp-text">{{ $p['text'] }}</p>

        <form class="pd-pick" data-pd-pick data-template="{{ $d['order_text'] }}" data-name="{{ $p['name'] }}" onsubmit="return false">
          <fieldset>
            <legend class="mono small-label"><span class="ls-0">{{ $d['size'] }}</span></legend>
            <div class="pd-opts">
              @foreach ($p['sizes'] as $s => $size)
                <label class="pd-opt"><input type="radio" name="size" value="{{ $size }}" @checked($s === count($p['sizes']) - 1)><span class="mono">{{ $size }}</span></label>
              @endforeach
            </div>
          </fieldset>
          <fieldset>
            <legend class="mono small-label"><span class="ls-0">{{ $d['qty'] }}</span></legend>
            <div class="pd-qty">
              <button type="button" data-step="-1" aria-label="−">−</button>
              <input type="number" name="qty" value="1" min="1" max="10" inputmode="numeric" aria-label="{{ $d['qty'] }}">
              <button type="button" data-step="1" aria-label="+">+</button>
            </div>
          </fieldset>
          <fieldset class="pd-plan">
            <legend class="mono small-label"><span class="ls-0">{{ $d['plan'] }}</span></legend>
            <div class="pd-opts">
              @foreach ($plans as $key => $plan)
                <label class="pd-opt"><input type="radio" name="plan" value="{{ $plan }}" @checked($loop->first)><span>{{ $plan }}</span></label>
              @endforeach
            </div>
          </fieldset>
          <p class="pd-summary"><span class="mono small-label"><span class="ls-0">{{ $d['summary'] }}</span></span>
            <b data-pd-summary>{{ last($p['sizes']) }} × 1 · {{ reset($plans) }}</b></p>
          <a class="btn btn-gold btn-lg rounded-pill px-4 magnetic w-100" data-pd-wa target="_blank" rel="noopener"
             href="{{ $wa($msg(last($p['sizes']), 1, reset($plans))) }}">{{ $t['notify'] }} · WhatsApp →</a>
          <p class="pd-note">{{ $d['order_note'] }}</p>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ============ JOURNEY: the clock line fills as you scroll ============ -->
<section class="pd-journey section-pad" data-pd-journey>
  <div class="container-xl">
    <span class="mono small-label gold">FROM GOSHALA TO GLASS</span>
    <h2 class="chapter-title display-font mt-2">The journey<br><span class="hi gold">{{ $d['journey_title'] }}</span></h2>
    <div class="pd-line">
      <span class="pd-track" aria-hidden="true"><span class="pd-fill" data-pd-fill></span></span>
      <ol class="pd-steps">
        @foreach ($x['journey'] as [$time, $title, $text])
          <li class="pd-step"><span class="pd-time mono">{{ $time }}</span><span class="pd-dot" aria-hidden="true"></span>
            <h3 class="hi">{{ $title }}</h3><p>{{ $text }}</p></li>
        @endforeach
      </ol>
    </div>
  </div>
</section>

<!-- ============ LABEL + USE + HOME TEST ============ -->
<section class="pd-facts bg-cream text-ink section-pad">
  <div class="container-xl">
    <div class="row g-4 g-lg-5">
      <div class="col-lg-5">
        <div class="pd-label">
          <div class="pd-label-head"><span class="display-font">{{ $enName }}</span><span class="hi">{{ $d['facts_title'] }}</span></div>
          <dl>
            @foreach ($x['facts'] as $k => $v)
              <div><dt class="hi">{{ $k }}</dt><dd>{{ $v }}</dd></div>
            @endforeach
          </dl>
          <p class="pd-label-note">{{ $d['facts_note'] }}</p>
        </div>
      </div>
      <div class="col-lg-7">
        <span class="mono small-label brown"><span class="ls-0">{{ $d['use_title'] }}</span> · HOW TO USE</span>
        <ul class="pd-uses">
          @foreach ($x['use'] as $n => $use)<li><span class="mono">0{{ $n + 1 }}</span><b class="hi">{{ $use }}</b></li>@endforeach
        </ul>
        <span class="mono small-label brown d-block mt-5"><span class="ls-0">{{ $d['test_title'] }}</span> · HOME CHECK</span>
        <div class="pd-tests">
          @foreach ($x['test'] as [$title, $text])
            <div class="pd-test"><span class="pd-test-no hi">✓ {{ $title }}</span><p>{{ $text }}</p></div>
          @endforeach
        </div>
        <p class="pd-small">{{ $d['test_note'] }}</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="pd-faq section-pad">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-lg-4">
        <span class="mono small-label gold">QUESTIONS</span>
        <h2 class="chapter-title display-font mt-2">Ask us<br><span class="hi gold">{{ $d['faq_title'] }}</span></h2>
      </div>
      <div class="col-lg-8">
        @foreach ($x['faq'] as [$q, $a])
          <details class="pd-q" @if ($loop->first) open @endif><summary class="hi">{{ $q }}</summary><p>{{ $a }}</p></details>
        @endforeach
      </div>
    </div>
  </div>
</section>

<!-- ============ MORE PRODUCTS ============ -->
<section class="pd-more section-pad">
  <div class="container-xl">
    <span class="mono small-label gold">MORE FROM THE GOSHALA</span>
    <h2 class="chapter-title display-font mt-2">Also try<br><span class="hi gold">{{ $d['more_title'] }}</span></h2>
    <div class="pd-cards">
      @foreach ($more as $o)
        <a class="pd-card" href="{{ $url($o['slug']) }}">
          <span class="pd-card-img"><img @class(['pp-ghee' => $o['img'] === 'ghee-jar']) src="{{ $pic($o['img'], 480) }}" alt="{{ $o['name'] }}" width="480" height="600" loading="lazy" decoding="async"></span>
          <span class="mono small-label gold ls-0">{{ $t['filter'][$o['cat']] }}</span>
          <b class="hi">{{ $o['name'] }}</b>
          <span class="pd-card-go">{{ $d['view'] }} →</span>
        </a>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ JOIN ============ -->
<section id="join" class="pp-join section-pad">
  <div class="container-xl text-center">
    <span class="mono small-label gold"><span class="ls-0">{{ $t['join']['eyebrow'] }}</span> · BE THE FIRST</span>
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
<script src="{{ asset('dummyassets/product.js') }}?v={{ filemtime(public_path('dummyassets/product.js')) }}" defer></script>
@endpush
