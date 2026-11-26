<!doctype html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yashadhya Organics — शुद्धता परंपरा की</title>
  <meta name="description" content="Pure A2 desi cow milk from our own goshala — Gir, Sahiwal, Tharparkar & Murrah. No water, no powder. Tested daily. Delivered before 7 AM in glass bottles.">
  <meta name="theme-color" content="#07110B">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=JetBrains+Mono:wght@400;600&family=Tiro+Devanagari+Hindi:ital@0;1&family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Site styles -->
  <link href="{{ asset('dummyassets/style.css') }}?v={{ filemtime(public_path('dummyassets/style.css')) }}" rel="stylesheet">
</head>
<body>

<!-- ============ LOADER ============ -->
<div id="loader" aria-hidden="true">
  <div class="d-flex justify-content-between mono small-label"><span>YASHADHYA://GOSHALA</span><span>04:30 AM · DAWN</span></div>
  <div class="text-center">
    <div class="hi gold fs-3 mb-2">गौशाला जाग रही है…</div>
    <div class="loader-num display-font"><span id="loaderPct">0</span>%</div>
    <div class="loader-bar mx-auto"><span id="loaderBar"></span></div>
  </div>
  <div class="d-flex justify-content-between align-items-center mono small-label">
    <span>MILKING · TESTING · COOLING · DELIVERY</span>
    <button id="loaderSkip" class="btn btn-ghost btn-sm mono">SKIP →</button>
  </div>
</div>

<!-- scroll progress + custom cursor -->
<div id="progress"></div>
<div id="cursor" aria-hidden="true"></div>

<!-- ============ NAVBAR ============ -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNav" aria-label="Main">
  <div class="container-xl">
    <a class="navbar-brand display-font d-flex align-items-center gap-2" href="#top"><img class="brand-logo" src="{{ $logo }}" alt="Yashadhya Organics"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="toggler-line"></span><span class="toggler-line"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav mx-auto mono">
        <li class="nav-item"><a class="nav-link" href="#ch1">01 सुबह</a></li>
        <li class="nav-item"><a class="nav-link" href="#ch2">02 संग्रह</a></li>
        <li class="nav-item"><a class="nav-link" href="#ch3">03 वंश</a></li>
        <li class="nav-item"><a class="nav-link" href="#ch4">04 प्रमाण</a></li>
        <li class="nav-item"><a class="nav-link" href="#ch5">05 योजना</a></li>
        <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="mono small-label d-none d-xl-inline"><span class="blink green">●</span> NEXT DELIVERY T-<span class="js-countdown">--:--</span></span>
        <a href="#contact" class="btn btn-gold rounded-pill px-4">दूध मंगवाएं</a>
      </div>
    </div>
  </div>
</nav>

<main>
<!-- ============ HERO ============ -->
<section id="top" class="hero">
  <svg class="layer layer-back" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
    <circle class="sun-glow" cx="1020" cy="600" r="220" fill="#F2B84B" opacity=".14"/>
    <circle class="sun" cx="1020" cy="600" r="140" fill="#F2B84B"/>
    <path d="M0 720 C 260 640 480 690 720 700 S 1180 640 1440 690 V 900 H 0 Z" fill="#14301A"/>
  </svg>
  <svg class="layer layer-mid" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
    <path d="M0 790 C 320 730 640 770 900 780 S 1280 740 1440 770 V 900 H 0 Z" fill="#0E2414"/>
  </svg>
  <svg class="layer layer-front" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
    <g class="sway" fill="#2F5A2C"><path d="M60 900 C 70 820 50 760 90 700 C 80 780 100 840 100 900 Z"/><path d="M110 900 C 130 800 160 760 200 730 C 170 800 150 850 150 900 Z"/><path d="M1300 900 C 1310 800 1290 750 1340 690 C 1330 780 1350 840 1350 900 Z"/><path d="M1360 900 C 1380 820 1410 780 1440 760 C 1420 820 1400 860 1400 900 Z"/></g>
    <g class="sway sway-2" fill="#1F4421"><path d="M0 900 C 20 840 10 800 40 760 C 40 820 50 860 50 900 Z"/><path d="M1240 900 C 1250 830 1240 790 1270 750 C 1270 820 1280 860 1280 900 Z"/></g>
  </svg>
  <div class="stars" aria-hidden="true"></div>
  <canvas id="particles" aria-hidden="true"></canvas>

  <div class="container-xl hero-inner">
    <div class="row align-items-start g-4">
      <div class="col-lg-10">
        <div class="d-flex flex-wrap gap-4 mono small-label mb-4 hero-meta">
          <span><span class="blink green">●</span> GOSHALA ONLINE</span><span>प्रस्तावना · PROLOGUE</span><span>FAT 4.2 · SNF 8.6 · 4°C</span>
        </div>
        <h1 class="hero-title display-font">
          <span class="line"><span class="word">Pure</span></span>
          <span class="line"><span class="word outline">by</span> <span class="word gold">dawn</span></span>
        </h1>
        <p class="hero-sub mt-4">
          <span class="hi fs-3 d-block text-cream">शुद्धता परंपरा की।</span>
          Desi cow milk from our own goshala. No water, no powder. Tested daily. At your door before 7 AM.
        </p>
        <div class="d-flex flex-wrap gap-3 mt-4 hero-cta">
          <a href="#ch1" class="btn btn-gold btn-lg rounded-pill px-4 magnetic">कहानी शुरू करें ↓</a>
          <a href="#gauri" class="btn btn-outline-cream btn-lg rounded-pill px-4 magnetic">गौरी से पूछें</a>
        </div>
      </div>
      <div class="col-lg-2 d-none d-lg-flex justify-content-end">
        <div class="vertical-hi hi gold" aria-hidden="true">यशाध्य</div>
      </div>
    </div>
  </div>
  <div class="container-xl hero-foot mono small-label d-flex justify-content-between flex-wrap gap-2">
    <span>GIR · SAHIWAL · THARPARKAR · MURRAH</span>
    <span class="scroll-cue">SCROLL <span class="scroll-line"></span></span>
  </div>
</section>

<!-- ============ MARQUEE (scroll-velocity) ============ -->
<div class="marquee" aria-hidden="true">
  <div class="marquee-track display-font">
    <span>Pure</span><span class="hi gold">शुद्ध</span><span class="outline">Desi</span><span class="hi gold">देसी</span><span>No water</span><span class="hi gold">पानी नहीं</span><span class="outline">Before 7</span><span class="hi gold">सुबह सात</span><span>Glass bottle</span><span class="hi gold">कांच की बोतल</span>
    <span>Pure</span><span class="hi gold">शुद्ध</span><span class="outline">Desi</span><span class="hi gold">देसी</span><span>No water</span><span class="hi gold">पानी नहीं</span><span class="outline">Before 7</span><span class="hi gold">सुबह सात</span><span>Glass bottle</span><span class="hi gold">कांच की बोतल</span>
  </div>
</div>

<!-- ============ PROMISE: word-by-word scrub ============ -->
<section class="promise bg-cream text-ink">
  <div class="container-xl">
    <p class="promise-text hi" data-split-words>हम वही दूध बेचते हैं जो अपने बच्चों को पिलाते हैं — अपनी गौशाला से, बिना कुछ मिलाए, बिना कुछ छिपाए।</p>
    <div class="row g-0 stats mt-5">
      <div class="col-6 col-lg-3 stat"><span class="stat-num hi" data-count="0">0</span><b>पानी · powder · preservative</b><small>Nothing added. Ever.</small></div>
      <div class="col-6 col-lg-3 stat"><span class="stat-num hi" data-count="4">0</span><b>tests every morning</b><small>Water, powder, preservatives, hormones.</small></div>
      <div class="col-6 col-lg-3 stat"><span class="stat-num hi"><span data-count="4">0</span>°</span><b>cooled after testing</b><small>Fresh till it reaches you.</small></div>
      <div class="col-6 col-lg-3 stat"><span class="stat-num hi brown">7:00</span><b>at your door, latest</b><small>One bill a month. Pause anytime.</small></div>
    </div>
  </div>
</section>

<!-- ============ CH 01: pinned horizontal morning ============ -->
<section id="ch1" class="chapter-morning">
  <div class="morning-pin">
    <div class="morning-sky"></div>
    <svg class="morning-sun" viewBox="0 0 200 200" aria-hidden="true"><circle cx="100" cy="100" r="60" fill="#F2B84B"/></svg>
    <div class="morning-head container-xl">
      <div class="chapter-head">
        <span class="chapter-num display-font">01</span>
        <div>
          <span class="mono small-label gold">अध्याय एक · CHAPTER ONE</span>
          <h2 class="chapter-title display-font">Farm to glass<br><span class="hi gold">खेत से गिलास तक</span></h2>
        </div>
      </div>
    </div>
    <div class="morning-track">
      <article class="m-card"><div class="mono small-label d-flex justify-content-between"><span>SEQ.01</span><span>GRAZE</span></div><div class="m-time display-font">4:30<small>AM</small></div><h3 class="hi">चराई और हरा चारा</h3><p>Open grazing and green fodder grown on our own land.</p><div class="photo-slot"><span>[PHOTO: cows grazing at dawn]</span><img src="{{ asset('dummyassets/images/morning-1.jpg') }}" alt="Cows grazing at dawn" loading="lazy" onerror="this.remove()"></div></article>
      <article class="m-card"><div class="mono small-label d-flex justify-content-between"><span>SEQ.02</span><span>MILK</span></div><div class="m-time display-font">5:30<small>AM</small></div><h3 class="hi">हाथ से दुहाई</h3><p>Hand milking into washed vessels, with clean hands.</p><div class="photo-slot"><span>[PHOTO: hand milking]</span><img src="{{ asset('dummyassets/images/morning-2.jpg') }}" alt="Hand milking" loading="lazy" onerror="this.remove()"></div></article>
      <article class="m-card"><div class="mono small-label d-flex justify-content-between"><span>SEQ.03</span><span>TEST</span></div><div class="m-time display-font">6:00<small>AM</small></div><h3 class="hi">जांच और ठंडा</h3><p>Fat/SNF and adulteration tests, then cooled to 4°C.</p><div class="photo-slot"><span>[PHOTO: testing kit]</span><img src="{{ asset('dummyassets/images/morning-3.jpg') }}" alt="Testing kit" loading="lazy" onerror="this.remove()"></div></article>
      <article class="m-card"><div class="mono small-label d-flex justify-content-between"><span>SEQ.04</span><span>DELIVER</span></div><div class="m-time display-font">7:00<small>AM</small></div><h3 class="hi">आपके दरवाज़े पर</h3><p>In a glass bottle, at your door before 7 AM.</p><div class="photo-slot"><span>[PHOTO: bottle at a doorstep]</span><img src="{{ asset('dummyassets/images/morning-4.jpg') }}" alt="Bottle at a doorstep" loading="lazy" onerror="this.remove()"></div></article>
    </div>
    <div class="morning-clock mono"><span class="green blink">●</span> <span id="morningClock">04:30</span> AM</div>
  </div>
</section>

<!-- ============ CH 02: products ============ -->
<section id="ch2" class="bg-cream text-ink section-pad">
  <div class="container-xl">
    <div class="chapter-head reveal">
      <span class="chapter-num display-font dark">02</span>
      <div>
        <span class="mono small-label brown">अध्याय दो · THE COLLECTION</span>
        <h2 class="chapter-title display-font">Made this morning<br><span class="hi brown">आज सुबह की रसोई</span></h2>
      </div>
    </div>
    <div class="row g-3 mt-4 products">
      <!-- data-product index matches PRODUCTS in script.js -->
      <div class="col-sm-6 col-lg"><button class="p-card tilt" data-product="0" data-bs-toggle="modal" data-bs-target="#productModal"><img class="p-img" src="{{ asset('dummyassets/images/product-1.png') }}" alt="" loading="lazy" onerror="this.remove()"><span class="p-glyph hi" aria-hidden="true">दू</span><span class="mono small-label">01 / 05 · MOST POPULAR</span><span class="p-body"><span class="hi p-hi">A2 देसी गाय का दूध</span><span class="display-font p-en">A2 Desi Cow Milk</span><small>1 L glass bottle</small><span class="mono small-label brown mt-2">OPEN FILE →</span></span></button></div>
      <div class="col-sm-6 col-lg"><button class="p-card tilt" data-product="1" data-bs-toggle="modal" data-bs-target="#productModal"><img class="p-img" src="{{ asset('dummyassets/images/product-2.png') }}" alt="" loading="lazy" onerror="this.remove()"><span class="p-glyph hi" aria-hidden="true">द</span><span class="mono small-label">02 / 05</span><span class="p-body"><span class="hi p-hi">मटका दही</span><span class="display-font p-en">Matka Curd</span><small>500 g · set in clay pots</small><span class="mono small-label brown mt-2">OPEN FILE →</span></span></button></div>
      <div class="col-sm-6 col-lg"><button class="p-card tilt" data-product="2" data-bs-toggle="modal" data-bs-target="#productModal"><img class="p-img" src="{{ asset('dummyassets/images/product-3.png') }}" alt="" loading="lazy" onerror="this.remove()"><span class="p-glyph hi" aria-hidden="true">घ</span><span class="mono small-label">03 / 05</span><span class="p-body"><span class="hi p-hi">बिलोना घी</span><span class="display-font p-en">Bilona Ghee</span><small>500 ml · hand-churned</small><span class="mono small-label brown mt-2">OPEN FILE →</span></span></button></div>
      <div class="col-sm-6 col-lg"><button class="p-card tilt" data-product="3" data-bs-toggle="modal" data-bs-target="#productModal"><img class="p-img" src="{{ asset('dummyassets/images/product-4.png') }}" alt="" loading="lazy" onerror="this.remove()"><span class="p-glyph hi" aria-hidden="true">प</span><span class="mono small-label">04 / 05</span><span class="p-body"><span class="hi p-hi">ताज़ा पनीर</span><span class="display-font p-en">Fresh Paneer</span><small>250 g · made daily</small><span class="mono small-label brown mt-2">OPEN FILE →</span></span></button></div>
      <div class="col-sm-6 col-lg"><button class="p-card tilt" data-product="4" data-bs-toggle="modal" data-bs-target="#productModal"><img class="p-img" src="{{ asset('dummyassets/images/product-5.png') }}" alt="" loading="lazy" onerror="this.remove()"><span class="p-glyph hi" aria-hidden="true">भ</span><span class="mono small-label">05 / 05</span><span class="p-body"><span class="hi p-hi">भैंस का दूध</span><span class="display-font p-en">Buffalo Milk</span><small>1 L · Murrah, thick &amp; creamy</small><span class="mono small-label brown mt-2">OPEN FILE →</span></span></button></div>
    </div>
  </div>
</section>

<!-- Product modal (Bootstrap) -->
<div class="modal fade" data-lenis-prevent id="productModal" tabindex="-1" aria-labelledby="pmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content hud">
      <div class="modal-header border-0">
        <span class="mono small-label">FILE <span id="pmNum">01</span> / 05</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4 align-items-start">
          <div class="col-md-4"><div class="pm-glyph hi gold" id="pmGlyph" aria-hidden="true">दू</div></div>
          <div class="col-md-8">
            <div class="hi gold fs-2" id="pmHi">A2 देसी गाय का दूध</div>
            <h3 class="display-font text-uppercase fs-1" id="pmTitle">A2 Desi Cow Milk</h3>
            <ul class="nav nav-pills hud-tabs mono my-3" role="tablist">
              <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pmT0" type="button" role="tab">OVERVIEW</button></li>
              <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pmT1" type="button" role="tab">PROCESS</button></li>
              <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pmT2" type="button" role="tab">ORDER</button></li>
            </ul>
            <div class="tab-content text-cream-2">
              <div class="tab-pane fade show active" id="pmT0" role="tabpanel"></div>
              <div class="tab-pane fade" id="pmT1" role="tabpanel"></div>
              <div class="tab-pane fade" id="pmT2" role="tabpanel"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-top border-hud pt-3 mt-3">
              <span class="display-font fs-3" id="pmPrice">₹[price]</span>
              <div class="d-flex gap-2">
                <a href="#contact" class="btn btn-gold rounded-pill px-4" data-bs-dismiss="modal">Order</a>
                <button type="button" class="btn btn-ghost rounded-pill mono" id="pmNext">NEXT FILE →</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ CH 03: breeds ============ -->
<section id="ch3" class="section-pad">
  <div class="container-xl">
    <div class="chapter-head reveal">
      <span class="chapter-num display-font">03</span>
      <div>
        <span class="mono small-label gold">अध्याय तीन · LINEAGE</span>
        <h2 class="chapter-title display-font">Only desi breeds<br><span class="hi gold">सिर्फ़ देसी नस्लें</span></h2>
      </div>
    </div>
    <div class="breeds mt-5">
      <div class="breed-row"><span class="mono small-label">NO.01</span><div class="breed-thumb" style="--coat:#9C4A26;--ink:#FFF4E6"><span class="hi thumb-ini" aria-hidden="true">गि</span><img src="{{ asset('images/website/home/breed-gir-480.webp') }}" alt="Gir cow" loading="lazy" onerror="this.remove()"></div><span class="breed-en display-font">Gir</span><span class="hi gold breed-hi">गिर</span><div class="breed-info"><span class="mono small-label green">DESI COW · A2</span><p>[Add 1–2 lines: origin, look, milk character]</p></div></div>
      <div class="breed-row"><span class="mono small-label">NO.02</span><div class="breed-thumb" style="--coat:#B8693A;--ink:#FFF4E6"><span class="hi thumb-ini" aria-hidden="true">सा</span><img src="{{ asset('images/website/home/breed-sahiwal-480.webp') }}" alt="Sahiwal cow" loading="lazy" onerror="this.remove()"></div><span class="breed-en display-font">Sahiwal</span><span class="hi gold breed-hi">साहीवाल</span><div class="breed-info"><span class="mono small-label green">DESI COW · A2</span><p>From Punjab. Reddish-brown and comfortable in the heat.</p></div></div>
      <div class="breed-row"><span class="mono small-label">NO.03</span><div class="breed-thumb" style="--coat:#E4E0D6;--ink:#07110B"><span class="hi thumb-ini" aria-hidden="true">था</span><img src="{{ asset('images/website/home/breed-tharparkar-480.webp') }}" alt="Tharparkar cow" loading="lazy" onerror="this.remove()"></div><span class="breed-en display-font">Tharparkar</span><span class="hi gold breed-hi">थारपारकर</span><div class="breed-info"><span class="mono small-label green">DESI COW · A2</span><p>[Add 1–2 lines: origin, look, milk character]</p></div></div>
      <div class="breed-row"><span class="mono small-label">NO.04</span><div class="breed-thumb" style="--coat:#1E1E1C;--ink:#F2B84B"><span class="hi thumb-ini" aria-hidden="true">मु</span><img src="{{ asset('images/website/home/breed-murrah-480.webp') }}" alt="Murrah buffalo" loading="lazy" onerror="this.remove()"></div><span class="breed-en display-font">Murrah</span><span class="hi gold breed-hi">मुर्रा</span><div class="breed-info"><span class="mono small-label green">BUFFALO</span><p>Thick, creamy milk — loved for dahi, paneer and chai.</p></div></div>
    </div>
  </div>
</section>

<!-- ============ CH 04: proof — bottle fills on scroll ============ -->
<section id="ch4" class="proof section-pad">
  <div class="container-xl">
    <div class="chapter-head reveal">
      <span class="chapter-num display-font">04</span>
      <div>
        <span class="mono small-label gold">अध्याय चार · PROOF</span>
        <h2 class="chapter-title display-font">Tested at 6:00<br><span class="hi gold">रिपोर्ट सबके लिए खुली</span></h2>
      </div>
    </div>
    <div class="row g-4 mt-4 align-items-center">
      <div class="col-lg-4 text-center">
        <svg class="bottle" viewBox="0 0 200 420" role="img" aria-label="Glass milk bottle filling up">
          <defs><clipPath id="bottleClip"><path d="M74 36h52v40c0 14 34 30 34 64v240c0 18-12 30-30 30H70c-18 0-30-12-30-30V140c0-34 34-50 34-64z"/></clipPath></defs>
          <rect x="68" y="6" width="64" height="30" rx="6" fill="#F2B84B"/>
          <g clip-path="url(#bottleClip)">
            <rect x="0" y="0" width="200" height="420" fill="#0E1E14"/>
            <g id="milkFill" transform="translate(0,380)">
              <path d="M0 10 Q 25 0 50 10 T 100 10 T 150 10 T 200 10 T 250 10 T 300 10 V 420 H 0 Z" fill="#FFFDF7"/>
            </g>
          </g>
          <path d="M74 36h52v40c0 14 34 30 34 64v240c0 18-12 30-30 30H70c-18 0-30-12-30-30V140c0-34 34-50 34-64z" fill="none" stroke="#8FA394" stroke-width="3"/>
          <path d="M150 170v170" stroke="#FFFFFF" stroke-width="6" stroke-linecap="round" opacity=".35"/>
          <g class="bottle-label">
            <rect x="52" y="200" width="96" height="120" rx="10" fill="#07110B" stroke="#F2B84B" stroke-width="2"/>
            <text class="label-fallback" x="100" y="258" text-anchor="middle" font-family="Tiro Devanagari Hindi, serif" font-size="26" fill="#F2B84B">यशाध्य</text>
            <text class="label-fallback" x="100" y="284" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="10" letter-spacing="3" fill="#EDE6D6">A2 · 1 L</text>
            <image class="label-logo" href="{{ $logo }}" x="60" y="210" width="80" height="80" preserveAspectRatio="xMidYMid meet"/>
            <text class="label-sub" x="100" y="308" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="9" letter-spacing="3" fill="#EDE6D6" opacity="0">A2 · 1 LITRE</text>
          </g>
        </svg>
        <div class="mono small-label mt-2">FILL LEVEL · <span id="fillPct">0</span>%</div>
      </div>
      <div class="col-lg-8">
        <div class="hud p-4">
          <div class="d-flex justify-content-between mono small-label mb-3"><span>MOD.01 · DAILY REPORT</span><span class="js-today">—</span></div>
          <div class="d-flex flex-wrap gap-5 mb-3">
            <div><div class="mono small-label">FAT</div><div class="display-font big-metric" data-count="4.2" data-decimals="1">0</div></div>
            <div><div class="mono small-label">SNF</div><div class="display-font big-metric" data-count="8.6" data-decimals="1">0</div></div>
            <div><div class="mono small-label">TEMP</div><div class="display-font big-metric"><span data-count="4">0</span>°C</div></div>
          </div>
          <ul class="checks mono list-unstyled mb-2">
            <li><span>WATER</span><span class="green">✓ NOT FOUND</span></li>
            <li><span>MILK POWDER</span><span class="green">✓ NOT FOUND</span></li>
            <li><span>PRESERVATIVES</span><span class="green">✓ NOT FOUND</span></li>
            <li><span>HORMONES (INCL. OXYTOCIN)</span><span class="green">✓ NOT FOUND</span></li>
          </ul>
          <small class="mono muted">SAMPLE — [connect to the real daily report]</small>
        </div>
      </div>
    </div>

    <div class="hud p-4 mt-4 compare">
      <div class="d-flex justify-content-between flex-wrap mono small-label mb-2"><span>MOD.02 · PACKET vs YASHADHYA</span><span>TYPICAL PACKET MILK, NO BRAND</span></div>
      <div class="cmp-row"><span class="mono small-label">SOURCE</span><span class="them">Mixed milk, thousands of animals</span><span class="us">Our own named breeds</span></div>
      <div class="cmp-row"><span class="mono small-label">AGE</span><span class="them">Often 1–2 days old</span><span class="us">Milked the same morning</span></div>
      <div class="cmp-row"><span class="mono small-label">ADULTERATION</span><span class="them">Machine-adjusted; powder common</span><span class="us">Nothing added; every batch tested</span></div>
      <div class="cmp-row"><span class="mono small-label">PRESERVATIVES</span><span class="them">Trust the label</span><span class="us">None — reports on request</span></div>
      <div class="cmp-row"><span class="mono small-label">HORMONES</span><span class="them">Unknown</span><span class="us">Never, incl. oxytocin</span></div>
      <div class="cmp-row"><span class="mono small-label">FEED</span><span class="them">Unknown</span><span class="us">Green fodder, oil cake, own hay</span></div>
      <div class="cmp-row"><span class="mono small-label">PACKAGING</span><span class="them">Plastic pouch</span><span class="us">Glass bottle, taken back</span></div>
      <div class="cmp-row"><span class="mono small-label">FARM VISIT</span><span class="them">No</span><span class="us">Yes — come and see</span></div>
    </div>
  </div>
</section>

<!-- ============ COMPARE: packet milk vs Yashadhya (text from lang/hi/home.php → questions) ============ -->
@php($cmp = trans('home.questions', [], 'hi'))
<section id="compare" class="section-pad compare">
  <div class="container-xl">
    <div class="text-center mx-auto reveal" style="max-width: 860px">
      <span class="mono small-label gold">{{ $cmp['eyebrow'] }}</span>
      <h2 class="compare-title hi mt-2">{{ $cmp['title'] }}</h2>
      <p class="compare-sub mt-3">{{ $cmp['text'] }}</p>
    </div>
    <div class="compare-table mt-5" role="table" aria-label="{{ $cmp['title'] }}">
      <div class="compare-row compare-head" role="row">
        <span role="columnheader">{{ $cmp['col_question'] }}</span><span role="columnheader">{{ $cmp['col_them'] }}</span><span role="columnheader" class="us">{{ $cmp['col_us'] }}</span>
      </div>
      @foreach ($cmp['rows'] as $row)
        <div class="compare-row" role="row">
          <span role="cell" class="q">{{ $row['q'] }}</span>
          <span role="cell" class="them"><i class="ic ic-no" aria-hidden="true">✕</i>{{ $row['them'] }}</span>
          <span role="cell" class="us"><i class="ic ic-yes" aria-hidden="true">✓</i>{{ $row['us'] }}</span>
        </div>
      @endforeach
    </div>
    <div class="text-center mt-4">
      <a href="#gauri" class="btn btn-gold rounded-pill px-4">{{ $cmp['cta'] }}</a>
      <p class="mt-3 mb-0 small" style="color: var(--cream-2)">{{ $cmp['note'] }}</p>
    </div>
  </div>
</section>

<!-- ============ CH 05: plan calculator ============ -->
<section id="ch5" class="section-pad">
  <div class="container-xl">
    <div class="chapter-head reveal">
      <span class="chapter-num display-font">05</span>
      <div>
        <span class="mono small-label gold">अध्याय पाँच · YOUR PLAN</span>
        <h2 class="chapter-title display-font">Configure your month<br><span class="hi gold">आधा लीटर से शुरू</span></h2>
      </div>
    </div>
    <div class="row g-3 mt-4">
      <div class="col-lg-6">
        <div class="hud p-4 h-100 d-flex flex-column gap-4">
          <div><div class="mono small-label mb-2">MILK</div>
            <div class="btn-group flex-wrap gap-2" role="group" aria-label="Milk">
              <input type="radio" class="btn-check" name="milk" id="mA2" value="a2" checked><label class="btn btn-pill" for="mA2">A2 Desi Cow</label>
              <input type="radio" class="btn-check" name="milk" id="mBuf" value="buf"><label class="btn btn-pill" for="mBuf">Murrah Buffalo</label>
            </div></div>
          <div><div class="mono small-label mb-2">PER DELIVERY</div>
            <div class="btn-group flex-wrap gap-2" role="group" aria-label="Quantity">
              <input type="radio" class="btn-check" name="qty" id="q05" value="0.5"><label class="btn btn-pill" for="q05">0.5 L</label>
              <input type="radio" class="btn-check" name="qty" id="q1" value="1" checked><label class="btn btn-pill" for="q1">1 L</label>
              <input type="radio" class="btn-check" name="qty" id="q15" value="1.5"><label class="btn btn-pill" for="q15">1.5 L</label>
              <input type="radio" class="btn-check" name="qty" id="q2" value="2"><label class="btn btn-pill" for="q2">2 L</label>
            </div></div>
          <div><div class="mono small-label mb-2">FREQUENCY</div>
            <div class="btn-group flex-wrap gap-2" role="group" aria-label="Frequency">
              <input type="radio" class="btn-check" name="freq" id="fD" value="30" checked><label class="btn btn-pill" for="fD">रोज़ · Daily</label>
              <input type="radio" class="btn-check" name="freq" id="fA" value="15"><label class="btn btn-pill" for="fA">Alternate days</label>
            </div></div>
          <small class="muted">Change quantity anytime · pause when you travel · one bill a month.</small>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="plan-out p-4 p-lg-5 h-100 d-flex flex-column justify-content-between gap-3">
          <div class="d-flex justify-content-between mono small-label"><span>OUTPUT · 30 DAYS</span><span><span id="planDel">30</span> DELIVERIES</span></div>
          <div class="display-font plan-litres"><span id="planL">30</span><small> LITRES</small></div>
          <div id="planLine">A2 Desi Cow milk · 1 L · every morning</div>
          <div class="d-flex justify-content-between align-items-baseline border-top border-dark pt-3 flex-wrap gap-2"><b>Estimated bill</b><span class="display-font fs-2" id="planBill">₹[price] × 30 L</span></div>
          <a href="#contact" class="btn btn-ink rounded-pill py-3 fw-bold">यह प्लान शुरू करें →</a>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="wave-div" aria-hidden="true"><svg viewBox="0 0 1440 120" preserveAspectRatio="none"><path class="wave-path" d="M0 60 C 240 20 480 100 720 60 C 960 20 1200 100 1440 60 L 1440 120 L 0 120 Z" fill="#EDE6D6"/></svg></div>
<!-- ============ GAURI chat ============ -->
<section id="gauri" class="bg-cream text-ink section-pad">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5">
        <svg class="gauri" viewBox="0 0 1312 1199" role="img" aria-label="Gauri, the Yashadhya calf">
          <image href="{{ asset('dummyassets/images/gauri-body.webp') }}" width="1312" height="1199"/>
          <g class="gauri-head"><image href="{{ asset('dummyassets/images/gauri-head.webp') }}" width="1312" height="1199"/></g>
        </svg>
        <span class="mono small-label brown d-block mt-3">COMPANION · साथी</span>
        <h2 class="chapter-title display-font">Ask Gauri<br><span class="hi brown">गौरी से पूछें</span></h2>
        <p>The goshala's youngest calf knows everything about the farm. Tap a question, or type your own.</p>
      </div>
      <div class="col-lg-7">
        <div class="chat">
          <div class="d-flex justify-content-between mono small-label"><span><span class="blink green">●</span> GAURI ONLINE</span><span><span id="msgCount">1</span> MESSAGES</span></div>
          <div class="chat-log" data-lenis-prevent id="chatLog" aria-live="polite">
            <div class="msg her">नमस्ते! मैं गौरी हूँ। Ask me anything about our milk, delivery or the goshala.</div>
          </div>
          <div class="d-flex flex-wrap gap-2" id="chips">
            <button type="button" class="chip">A2 दूध क्या है?</button>
            <button type="button" class="chip">डिलीवरी कितने बजे?</button>
            <button type="button" class="chip">Testing कैसे होती है?</button>
            <button type="button" class="chip">Bottle का क्या करें?</button>
            <button type="button" class="chip">Farm visit कर सकते हैं?</button>
          </div>
          <form class="d-flex gap-2" id="chatForm">
            <label for="chatInput" class="visually-hidden">Ask Gauri a question</label>
            <input id="chatInput" class="form-control chat-input" type="text" placeholder="जैसे: डिलीवरी कितने बजे?" autocomplete="off">
            <button class="btn btn-gold rounded-pill px-4 mono" type="submit">SEND</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ REVIEWS (fill with real ones) ============ -->
<section class="section-pad reviews">
  <div class="container-xl">
    <h2 class="chapter-title display-font reveal">घर-घर की <span class="gold">बात</span></h2>
    <div class="row g-3 mt-3">
      <div class="col-md-4"><figure class="hud p-4 h-100 review"><div class="gold">★★★★★</div><blockquote class="hi fs-5 my-3">[Real customer review]</blockquote><figcaption class="mono small-label">— [NAME], [AREA]</figcaption></figure></div>
      <div class="col-md-4"><figure class="hud p-4 h-100 review"><div class="gold">★★★★★</div><blockquote class="hi fs-5 my-3">[Real customer review]</blockquote><figcaption class="mono small-label">— [NAME], [AREA]</figcaption></figure></div>
      <div class="col-md-4"><figure class="hud p-4 h-100 review"><div class="gold">★★★★★</div><blockquote class="hi fs-5 my-3">[Real customer review]</blockquote><figcaption class="mono small-label">— [NAME], [AREA]</figcaption></figure></div>
    </div>
  </div>
</section>

<div class="wave-div" aria-hidden="true"><svg viewBox="0 0 1440 120" preserveAspectRatio="none"><path class="wave-path" d="M0 60 C 240 20 480 100 720 60 C 960 20 1200 100 1440 60 L 1440 120 L 0 120 Z" fill="#EDE6D6"/></svg></div>
<!-- ============ FAQ (Bootstrap accordion) ============ -->
<section id="faq" class="section-pad bg-cream text-ink">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-4"><h2 class="chapter-title display-font reveal">सवाल?<br><span class="hi brown">जवाब।</span></h2></div>
      <div class="col-lg-8">
        <div class="accordion faq" id="faqAcc">
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button hi" type="button" data-bs-toggle="collapse" data-bs-target="#f1" aria-expanded="true">A2 दूध क्या है?</button></h3><div id="f1" class="accordion-collapse collapse show" data-bs-parent="#faqAcc"><div class="accordion-body">A2 is a type of protein found in the milk of desi cows like Gir, Sahiwal and Tharparkar. Many people find it easier to digest.</div></div></div>
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed hi" type="button" data-bs-toggle="collapse" data-bs-target="#f2">किन इलाकों में डिलीवरी है?</button></h3><div id="f2" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">We currently deliver in [Zone 1], [Zone 2] and [Zone 3]. Send an enquiry and we will confirm your address the same day.</div></div></div>
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed hi" type="button" data-bs-toggle="collapse" data-bs-target="#f3">डिलीवरी कैसे रोकें?</button></h3><div id="f3" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">You can pause anytime — when you travel or need less. [How to pause: call / WhatsApp before X PM].</div></div></div>
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed hi" type="button" data-bs-toggle="collapse" data-bs-target="#f4">पेमेंट कैसे करें?</button></h3><div id="f4" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">One bill at the end of the month. [Payment modes: UPI / cash / bank transfer].</div></div></div>
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed hi" type="button" data-bs-toggle="collapse" data-bs-target="#f5">कांच की बोतलों का क्या करें?</button></h3><div id="f5" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Keep the empty bottles out — the farm takes them back. [Confirm bottle deposit, if any].</div></div></div>
          <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed hi" type="button" data-bs-toggle="collapse" data-bs-target="#f6">दूध कैसे टेस्ट होता है?</button></h3><div id="f6" class="accordion-collapse collapse" data-bs-parent="#faqAcc"><div class="accordion-body">Every batch is tested at 6 AM for fat/SNF and for water, milk powder, preservatives and hormones, then cooled to 4°C. Reports are available on request.</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ CONTACT ============ -->
<section id="contact" class="section-pad">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-6">
        <span class="mono small-label gold">उपसंहार · EPILOGUE</span>
        <h2 class="display-font epi-title mt-2">Tomorrow,<br><span class="gold">7 AM.</span></h2>
        <p class="muted mt-3">“We serve the same milk we give our own children.” — [Founder name]</p>
        <ul class="list-unstyled mono contact-list mt-4">
          <li>LOC · Yashadhya Dairy Farm, [village], [district], [state] [PIN]</li>
          <li>TEL · <a href="tel:+91XXXXXXXXXX">+91 [phone]</a> · <a href="mailto:[email]">[email]</a></li>
          <li>HRS · Daily 6 AM – 8 PM</li>
          <li>ZONES · [Zone 1] · [Zone 2] · [Zone 3]</li>
          <li class="green">FARM VISITS · OPEN — CALL AHEAD</li>
        </ul>
      </div>
      <div class="col-lg-6">
        <form class="hud p-4 p-lg-5 enquiry" id="enquiryForm" novalidate>
          <div class="d-flex justify-content-between mono small-label mb-3"><span>NEW ENQUIRY</span><span>REPLY · SAME DAY</span></div>
          <div class="mb-3"><label for="eName" class="form-label mono small-label">NAME · नाम</label><input id="eName" class="form-control line-input" type="text" autocomplete="name" required></div>
          <div class="mb-3"><label for="ePhone" class="form-label mono small-label">MOBILE · मोबाइल</label><input id="ePhone" class="form-control line-input" type="tel" autocomplete="tel" pattern="[0-9+ ]{10,14}" required></div>
          <div class="mb-4"><label for="eInt" class="form-label mono small-label">I WANT · मुझे चाहिए</label>
            <select id="eInt" class="form-select line-input"><option>Home delivery</option><option>Bulk supply (shop / sweet shop / hotel)</option><option>I am a farmer — I want to sell milk</option></select></div>
          <button class="btn btn-gold w-100 rounded-pill py-3 mono fw-semibold" type="submit">TRANSMIT · भेजें →</button>
          <div class="mono small-label green mt-3 d-none" id="enquiryOk">✓ RECEIVED — WE'LL REPLY TODAY</div>
        </form>
      </div>
    </div>
  </div>
</section>
</main>

<div class="footer-word display-font" aria-hidden="true">YASHADHYA</div>
<footer class="container-xl d-flex justify-content-between flex-wrap gap-3 mono small-label pb-5 mb-5 mb-lg-0">
  <span>© 2026 YASHADHYA ORGANICS · FSSAI [NUMBER]</span>
  <span class="d-flex flex-wrap gap-3"><a href="#">INSTAGRAM</a><a href="#">FACEBOOK</a><a href="#">YOUTUBE</a><a href="#">PRIVACY</a><a href="#">TERMS</a><a href="#">REFUNDS</a></span>
</footer>

<!-- sticky action bar (mobile) -->
<div class="action-bar d-lg-none">
  <a href="tel:+91XXXXXXXXXX">Call</a>
  <a href="https://wa.me/91XXXXXXXXXX" target="_blank" rel="noopener">WhatsApp</a>
  <a href="#contact" class="ab-main">दूध मंगवाएं</a>
</div>
<a href="#top" class="to-top" aria-label="Back to top">↑</a>

<!-- Scripts: Bootstrap, GSAP + ScrollTrigger, Lenis smooth scroll -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js"></script>
<script src="{{ asset('dummyassets/script.js') }}?v={{ filemtime(public_path('dummyassets/script.js')) }}"></script>
</body>
</html>
