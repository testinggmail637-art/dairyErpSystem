@extends('demo.layout')

{{-- Demo Contact page (local only). Text: lang/hi/demo/contact.php — phone, email, address, areas are [placeholders] for the owner. --}}
@php
    $t = trans('demo/contact', [], 'hi');
    $waNo = '91XXXXXXXXXX';
    $tel = '+91XXXXXXXXXX';
    $missed = '+91XXXXXXXXXX';
    $mail = 'hello@example.com';
    $hrefs = [
        'wa' => "https://wa.me/{$waNo}?text=".rawurlencode($t['composer']['greeting'].' '.$t['composer']['intents'][0]['msg']),
        'call' => "tel:{$tel}",
        'missed' => "tel:{$missed}",
        'mail' => "mailto:{$mail}",
    ];
    $icons = [
        'wa' => '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8a11.6 11.6 0 0 1-4.5-4c-.3-.5-1-1.6-1-3s.7-2.1 1-2.4a1 1 0 0 1 .7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6a8 8 0 0 0 1.5 1.9 7.5 7.5 0 0 0 2.2 1.4c.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3a2.3 2.3 0 0 1-.2 1.4z" fill="currentColor"/>',
        'call' => '<path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z" fill="currentColor"/>',
        'missed' => '<path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z" fill="currentColor"/><path d="M15 3l6 6M21 3l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
    ];
    $c = $t['composer'];
@endphp

@section('title', 'संपर्क · Yashadhya Organics')
@section('description', $t['meta_description'])

@push('head')
  <link href="{{ asset('dummyassets/contact.css') }}?v={{ filemtime(public_path('dummyassets/contact.css')) }}" rel="stylesheet">
@endpush

@section('content')
<!-- ============ HERO + 4 ways to reach us ============ -->
<section id="top" class="ct-hero">
  <div class="container-xl">
    <div class="d-flex flex-wrap gap-4 mono small-label mb-4">
      <span class="ct-status" data-ct-status data-open="{{ $t['hero']['open'] }}" data-closed="{{ $t['hero']['closed'] }}"><span class="ct-dot"></span> <span class="hi" data-ct-status-text>…</span></span>
      <span><span class="ls-0">संपर्क</span> · CONTACT</span>
    </div>
    <h1 class="ct-title display-font"><span class="line"><span class="word">Say</span></span> <span class="line"><span class="word gold">namaste</span></span></h1>
    <p class="ct-hi hi">{{ $t['hero']['title'] }}</p>
    <p class="ct-lead">{{ $t['hero']['text'] }}</p>
    <button type="button" class="ct-save mt-2" data-ct-save data-tel="{{ $tel }}" data-mail="{{ $mail }}" data-addr="{{ $t['visit']['address'] }}">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 3v12m0 0l-5-5m5 5l5-5M4 19h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span class="hi"><b>{{ $t['hero']['save'] }}</b> <small>{{ $t['hero']['save_hint'] }}</small></span>
    </button>

    <div class="ct-ways mt-5">
      @foreach ($t['ways'] as $w)
        <a @class(['ct-way', 'ct-way--wa' => $w['key'] === 'wa']) href="{{ $hrefs[$w['key']] }}" @if ($w['key'] === 'wa') target="_blank" rel="noopener" @endif data-ct-way="{{ $w['key'] }}">
          <span class="ct-way-top"><span class="ct-ico"><svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$w['key']] !!}</svg></span><span class="mono small-label">{{ $w['tag'] }}</span></span>
          <b class="hi">{{ $w['title'] }}</b>
          <p class="hi">{{ $w['text'] }}</p>
          <span class="ct-way-foot"><span class="mono small">{{ $w['eta'] }}</span><span class="ct-go hi">{{ $w['cta'] }} →</span></span>
        </a>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ ONE-TAP MESSAGE: pick a need → WhatsApp message builds itself ============ -->
<section class="ct-composer bg-cream text-ink section-pad">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <span class="mono small-label brown"><span class="ls-0">{{ $c['eyebrow'] }}</span> · ONE TAP</span>
        <h2 class="chapter-title display-font mt-2">What do<br>you need?<br><span class="hi brown">{{ $c['title'] }}</span></h2>
        <p class="ct-hint hi mt-3">{{ $c['hint'] }}</p>
        <div class="ct-chips mt-4" role="radiogroup" aria-label="{{ $c['title'] }}">
          @foreach ($c['intents'] as $i => $in)
            <button type="button" class="ct-chip hi" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" data-msg="{{ $in['msg'] }}">{{ $in['label'] }}</button>
          @endforeach
        </div>
        <div class="row g-3 mt-3">
          <div class="col-sm-6"><label class="visually-hidden" for="ctName">{{ $c['name'] }}</label><input id="ctName" class="form-control ct-input hi" type="text" autocomplete="name" placeholder="{{ $c['name'] }}" maxlength="60"></div>
          <div class="col-sm-6"><label class="visually-hidden" for="ctArea">{{ $c['area'] }}</label><input id="ctArea" class="form-control ct-input hi" type="text" autocomplete="address-level3" placeholder="{{ $c['area'] }}" maxlength="60"></div>
          <div class="col-12">
            <div class="ct-note">
              <label class="visually-hidden" for="ctNote">{{ $c['note'] }}</label>
              <textarea id="ctNote" class="form-control ct-input hi" rows="2" maxlength="300" placeholder="{{ $c['note'] }}"></textarea>
              <button type="button" class="ct-mic d-none" data-ct-mic aria-pressed="false" data-on="{{ $c['mic_on'] }}" data-off="{{ $c['mic'] }}">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3" fill="currentColor"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span class="hi" data-ct-mic-text>{{ $c['mic'] }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="ct-phone" data-ct-composer data-wa="{{ $waNo }}" data-greeting="{{ $c['greeting'] }}" data-name-line="{{ $c['name_line'] }}" data-area-line="{{ $c['area_line'] }}">
          <div class="ct-phone-bar"><span class="ct-avatar display-font">Y</span><span><b>Yashadhya Organics</b><small class="mono">ONLINE</small></span></div>
          <div class="ct-phone-body">
            <span class="ct-day mono">{{ $c['preview'] }}</span>
            <div class="ct-bubble hi" data-ct-preview aria-live="polite">{{ $c['greeting'] }} {{ $c['intents'][0]['msg'] }}</div>
          </div>
          <a class="ct-send btn btn-gold rounded-pill w-100 py-3 magnetic" href="{{ $hrefs['wa'] }}" target="_blank" rel="noopener" data-ct-send>
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">{!! $icons['wa'] !!}</svg> <span class="hi">{{ $c['send'] }}</span> →
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ DELIVERY AREA CHECK ============ -->
<section class="ct-area section-pad">
  <div class="container-xl">
    <div class="ct-area-card">
      <div>
        <span class="mono small-label gold"><span class="ls-0">{{ $t['area']['eyebrow'] }}</span> · AREA CHECK</span>
        <h2 class="ct-h2 hi mt-2">{{ $t['area']['title'] }}</h2>
      </div>
      <form class="ct-area-form" data-ct-area data-zones="{{ json_encode($t['area']['zones'], JSON_UNESCAPED_UNICODE) }}" data-yes="{{ $t['area']['yes'] }}" data-no="{{ $t['area']['no'] }}" data-wa="{{ $waNo }}" novalidate>
        <label class="visually-hidden" for="ctAreaQ">{{ $t['area']['placeholder'] }}</label>
        <input id="ctAreaQ" class="form-control ct-input ct-input--dark hi" type="text" placeholder="{{ $t['area']['placeholder'] }}" maxlength="60" required>
        <button class="btn btn-gold rounded-pill px-4 hi" type="submit">{{ $t['area']['check'] }}</button>
      </form>
      <div class="ct-area-result d-none" data-ct-area-result aria-live="polite">
        <p class="hi mb-3" data-ct-area-text></p>
        <a class="btn btn-outline-cream rounded-pill px-4 hi" href="#" target="_blank" rel="noopener" data-ct-area-cta data-yes="{{ $t['area']['yes_cta'] }}" data-no="{{ $t['area']['no_cta'] }}"></a>
      </div>
      <p class="ct-zones mb-0"><span class="mono small-label">{{ $t['area']['zones_label'] }}</span>
        @foreach ($t['area']['zones'] as $z)
          <button type="button" class="ct-zone hi" data-ct-zone="{{ $z['name'] }}">{{ $z['name'] }}</button>
        @endforeach
      </p>
    </div>
  </div>
</section>

<!-- ============ VISIT: address + hours ============ -->
<section class="ct-visit section-pad pt-0">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="ct-map">
          <svg class="ct-map-art" viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <path class="ct-road" d="M-20 330 C 120 300 160 220 260 230 S 420 300 470 210 S 560 80 640 60"/>
            <path class="ct-river" d="M-20 120 C 80 150 160 90 240 120 S 380 180 640 140"/>
            <g class="ct-fields"><rect x="40" y="200" width="90" height="60" rx="6"/><rect x="150" y="270" width="70" height="50" rx="6"/><rect x="330" y="80" width="100" height="55" rx="6"/><rect x="480" y="250" width="80" height="70" rx="6"/></g>
          </svg>
          <span class="ct-pin" aria-hidden="true"><span></span></span>
          <div class="ct-map-card">
            <span class="mono small-label gold"><span class="ls-0">{{ $t['visit']['eyebrow'] }}</span> · VISIT US</span>
            <h2 class="ct-h2 hi mt-2">{{ $t['visit']['title'] }}</h2>
            <p class="hi mb-1"><b>{{ $t['visit']['address'] }}</b></p>
            <p class="hi ct-muted">{{ $t['visit']['distance'] }}</p>
            <a class="btn btn-gold rounded-pill px-4 magnetic" href="https://maps.google.com/?q=[farm+location]" target="_blank" rel="noopener"><span class="hi">{{ $t['visit']['maps'] }}</span> ↗</a>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="ct-hours">
          <span class="mono small-label gold"><span class="ls-0">{{ $t['visit']['hours_label'] }}</span> · HOURS</span>
          <dl class="mt-3 mb-0">
            @foreach ($t['visit']['hours'] as $h)
              <div><dt class="hi">{{ $h['what'] }}</dt><dd class="hi">{{ $h['when'] }}</dd></div>
            @endforeach
          </dl>
          @php $b = $t['visit']['book']; @endphp
          <div class="ct-book mt-4" data-ct-book data-wa="{{ $waNo }}" data-msg="{{ $b['msg'] }}" data-today="{{ $b['today'] }}" data-tomorrow="{{ $b['tomorrow'] }}">
            <h3 class="ct-book-title hi">{{ $b['title'] }}</h3>
            <span class="mono small-label">{{ $b['day'] }}</span>
            <div class="ct-days" role="radiogroup" aria-label="{{ $b['day'] }}" data-ct-days></div>
            <span class="mono small-label d-block mt-3">{{ $b['slot'] }}</span>
            <div class="ct-chips ct-chips--sm" role="radiogroup" aria-label="{{ $b['slot'] }}">
              @foreach ($b['slots'] as $i => $slot)
                <button type="button" class="ct-chip hi" role="radio" aria-checked="{{ $i === 0 ? 'true' : 'false' }}" data-ct-slot="{{ $slot }}">{{ $slot }}</button>
              @endforeach
            </div>
            <div class="d-flex align-items-center justify-content-between gap-3 mt-3">
              <span class="mono small-label">{{ $b['people'] }}</span>
              <div class="ct-step"><button type="button" data-ct-step="-1" aria-label="−">−</button><output data-ct-people aria-live="polite">2</output><button type="button" data-ct-step="1" aria-label="+">+</button></div>
            </div>
            <a class="btn btn-dark rounded-pill w-100 py-3 mt-3 hi" href="#" target="_blank" rel="noopener" data-ct-book-go>{{ $b['cta'] }} →</a>
          </div>
          <p class="ct-rules hi mt-4 mb-0">{{ $t['visit']['rules'] }}</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="ct-faq section-pad pt-0">
  <div class="container-xl">
    <span class="mono small-label gold"><span class="ls-0">{{ $t['faq']['eyebrow'] }}</span> · FAQ</span>
    <h2 class="chapter-title display-font mt-2">Before<br>you ask<br><span class="hi gold">{{ $t['faq']['title'] }}</span></h2>
    <div class="ct-faq-list mt-4">
      @foreach ($t['faq']['items'] as $f)
        <details class="ct-q"><summary class="hi">{{ $f['q'] }}</summary><p class="hi">{{ $f['a'] }}</p></details>
      @endforeach
    </div>
  </div>
</section>

<!-- ============ END ============ -->
<section class="ct-end section-pad text-center">
  <div class="container-xl">
    <h2 class="epi-title display-font">Tomorrow,<br><span class="gold">7 AM.</span><span class="hi">{{ $t['end']['title'] }}</span></h2>
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
      <a class="btn btn-gold btn-lg rounded-pill px-4 magnetic hi" href="{{ $hrefs['wa'] }}" target="_blank" rel="noopener">{{ $t['end']['cta'] }} →</a>
      <a class="btn btn-outline-cream btn-lg rounded-pill px-4 magnetic hi" href="{{ route('demo', 'products') }}">{{ $t['end']['products'] }}</a>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/contact.js') }}?v={{ filemtime(public_path('dummyassets/contact.js')) }}" defer></script>
@endpush
