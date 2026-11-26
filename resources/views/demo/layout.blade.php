{{-- Demo sandbox layout (local only): head, navbar, footer, mobile action bar, scripts. --}}
@php($base = request()->is('demo') ? '' : route('demo'))
<!doctype html>
<html lang="hi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Yashadhya Organics — शुद्धता परंपरा की')</title>
  <meta name="description" content="@yield('description', 'Pure A2 desi cow milk from our own goshala — Gir, Sahiwal, Tharparkar & Murrah. No water, no powder. Tested daily. Delivered before 7 AM in glass bottles.')">
  <meta name="theme-color" content="#07110B">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=JetBrains+Mono:wght@400;600&family=Tiro+Devanagari+Hindi:ital@0;1&family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Site styles -->
  <link href="{{ asset('dummyassets/style.css') }}?v={{ filemtime(public_path('dummyassets/style.css')) }}" rel="stylesheet">
  <script>try { if (sessionStorage.getItem('ylSeen')) document.documentElement.classList.add('no-loader'); } catch (e) {}</script>
  @stack('head')
</head>
<body>

@yield('loader')

<!-- scroll progress + custom cursor -->
<div id="progress"></div>
<div id="cursor" aria-hidden="true"></div>

<!-- ============ NAVBAR ============ -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNav" aria-label="Main">
  <div class="container-xl">
    <a class="navbar-brand display-font d-flex align-items-center gap-2" href="{{ $base }}#top"><img class="brand-logo" src="{{ $logo }}" alt="Yashadhya Organics"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="toggler-line"></span><span class="toggler-line"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav mx-auto mono">
        <li class="nav-item"><a class="nav-link" href="{{ $base }}#ch1">01 सुबह</a></li>
        <li class="nav-item"><a @class(['nav-link', 'active' => request()->is('demo/products')]) href="{{ route('demo', 'products') }}">02 संग्रह</a></li>
        <li class="nav-item"><a @class(['nav-link', 'active' => request()->is('demo/herd')]) href="{{ route('demo', 'herd') }}">03 वंश</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ $base }}#ch4">04 प्रमाण</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ $base }}#ch5">05 योजना</a></li>
        <li class="nav-item"><a @class(['nav-link', 'active' => request()->is('demo/about')]) href="{{ route('demo', 'about') }}">कहानी</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ $base }}#faq">FAQ</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="mono small-label d-none d-xl-inline"><span class="blink green">●</span> NEXT DELIVERY T-<span class="js-countdown">--:--</span></span>
        <a href="{{ $base }}#contact" class="btn btn-gold rounded-pill px-4">दूध मंगवाएं</a>
      </div>
    </div>
  </div>
</nav>

<main>
@yield('content')
</main>

<div class="footer-word display-font" aria-hidden="true">YASHADHYA</div>
<footer class="container-xl d-flex justify-content-between flex-wrap gap-3 mono small-label pb-5 mb-5 mb-lg-0">
  <span>© 2026 YASHADHYA ORGANICS · FSSAI [NUMBER]</span>
  <span class="d-flex flex-wrap gap-3"><a href="#">INSTAGRAM</a><a href="#">FACEBOOK</a><a href="#">YOUTUBE</a><a href="#">PRIVACY</a><a href="#">TERMS</a><a href="#">REFUNDS</a></span>
</footer>

<!-- sticky action bar (mobile) -->
<div class="action-bar d-lg-none">
  <a href="tel:+91XXXXXXXXXX"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z" fill="currentColor"/></svg>Call</a>
  <a href="https://wa.me/91XXXXXXXXXX" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8a11.6 11.6 0 0 1-4.5-4c-.3-.5-1-1.6-1-3s.7-2.1 1-2.4a1 1 0 0 1 .7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6a8 8 0 0 0 1.5 1.9 7.5 7.5 0 0 0 2.2 1.4c.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3a2.3 2.3 0 0 1-.2 1.4z" fill="currentColor"/></svg>WhatsApp</a>
  <a href="{{ $base }}#contact" class="ab-main"><svg class="ab-bottle" viewBox="0 0 20 32" aria-hidden="true"><defs><clipPath id="abBottle"><path d="M7 1h6v5l3 4v18a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V10l3-4z"/></clipPath></defs><g clip-path="url(#abBottle)"><rect x="0" y="0" width="20" height="32" fill="rgba(7,17,11,.15)"/><path class="ab-milk" d="M-20 15 q5 -3 10 0 t10 0 t10 0 t10 0 t10 0 V40 H-20 Z" fill="#FFF8EC"/></g><path d="M7 1h6v5l3 4v18a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V10l3-4z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>दूध मंगवाएं</a>
</div>
<a href="#top" class="to-top" aria-label="Back to top">↑</a>

<!-- Scripts: Bootstrap, GSAP + ScrollTrigger, Lenis smooth scroll -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js" defer></script>
<script src="{{ asset('dummyassets/script.js') }}?v={{ filemtime(public_path('dummyassets/script.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
