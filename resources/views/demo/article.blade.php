@extends('demo.layout')

{{-- Demo Blog article (local only): /demo/article?a=<slug>. "Goshala diary" look — a diary page on the dark desk,
     highlighter headings, sticky-note tips, milk-bottle reading progress. Content: lang/hi/demo/blog.php → posts. --}}
@php
    $t = trans('demo/blog', [], 'hi');
    $a = $t['article'];
    $posts = collect($t['posts']);
    $post = $posts->firstWhere('slug', (string) request()->query('a', $posts->first()['slug']));
    abort_unless($post, 404);
    $pic = fn ($n, $w) => asset("images/website/home/{$n}-{$w}.webp");
    $mins = fn ($p) => max(1, (int) ceil(collect($p['body'])->flatten()->map(fn ($s) => count(preg_split('/\s+/u', trim($s))))->sum() / 180));
    $url = fn ($p) => route('demo', 'article').'?a='.$p['slug'];
    $day = fn ($p) => \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $p['date'])->locale('hi');
    $headings = collect($post['body'])->filter(fn ($b) => $b[0] === 'h')->values();
    $related = $posts->where('slug', '!=', $post['slug'])->sortByDesc(fn ($p) => $p['cat'] === $post['cat'])->take(3);
    $shareText = $post['title'].' — '.$url($post);
    $trial = 'https://wa.me/91XXXXXXXXXX?text='.rawurlencode($a['cta_msg']);
    $hNo = 0;
@endphp

@section('title', $post['title'].' · Yashadhya Organics')
@section('description', $post['excerpt'])

@push('head')
  <link rel="preload" as="image" href="{{ $pic($post['img'], 800) }}" fetchpriority="high">
  <link href="https://fonts.googleapis.com/css2?family=Kalam:wght@400;700&display=swap" rel="stylesheet">
  <link href="{{ asset('dummyassets/blog.css') }}?v={{ filemtime(public_path('dummyassets/blog.css')) }}" rel="stylesheet">
@endpush

@section('content')
<article class="ar bl-desk">
  <!-- ============ HEADER on the desk ============ -->
  <header class="ar-head">
    <div class="container-xl">
      <a class="ar-back bl-hand" href="{{ route('demo', 'blog') }}">← {{ $a['back'] }}</a>
      <div class="row g-5 align-items-center mt-1">
        <div class="col-lg-7">
          <span class="ar-tag bl-hand"><i class="bl-dot bl-c-{{ $post['cat'] }}"></i>{{ $t['categories'][$post['cat']] }}</span>
          <h1 class="ar-title hi">{{ $post['title'] }}</h1>
          <p class="ar-excerpt hi">{{ $post['excerpt'] }}</p>
          <p class="ar-by bl-hand mb-0">✎ {{ $t['author'] }} · {{ $mins($post) }} {{ $t['min'] }}</p>
        </div>
        <div class="col-lg-5">
          <figure class="bl-polaroid ar-cover mb-0">
            <span class="bl-tape" aria-hidden="true"></span>
            <span class="bl-photo"><img src="{{ $pic($post['img'], 800) }}" srcset="{{ $pic($post['img'], 480) }} 480w, {{ $pic($post['img'], 800) }} 800w" sizes="(min-width: 992px) 34vw, 86vw" alt="" fetchpriority="high"></span>
            <figcaption class="bl-cap bl-hand">यशाध्य गौशाला</figcaption>
            <span class="ar-date-stamp" aria-label="{{ $post['date'] }}"><b>{{ $day($post)->format('j') }}</b><small>{{ $day($post)->translatedFormat('M Y') }}</small></span>
          </figure>
        </div>
      </div>
    </div>
  </header>

  <!-- ============ DIARY PAGE + side rail ============ -->
  <div class="ar-main">
    <div class="container-xl">
      <div class="row g-4 g-xl-5">
        <div class="col-lg-8">
          <div class="ar-page">
            <div class="ar-body hi" data-ar-body>
              @foreach ($post['body'] as $b)
                @switch($b[0])
                  @case('h') <h2 id="h{{ ++$hNo }}" data-ar-lit><span>{{ $b[1] }}</span></h2> @break
                  @case('p') <p>{{ $b[1] }}</p> @break
                  @case('quote') <blockquote data-ar-lit>{{ $b[1] }}<svg viewBox="0 0 300 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 8 C 60 2, 120 11, 180 6 S 260 3, 298 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg></blockquote> @break
                  @case('tip') <aside class="ar-tip"><span class="bl-tape" aria-hidden="true"></span><b>📌 जानने लायक</b>{{ $b[1] }}</aside> @break
                  @case('list') <ul>@foreach ($b[1] as $li)<li>{{ $li }}</li>@endforeach</ul> @break
                  @case('steps') <ol class="ar-steps">@foreach ($b[1] as $li)<li>{{ $li }}</li>@endforeach</ol> @break
                @endswitch
              @endforeach
              <p class="ar-sign">— {{ $t['author'] }}, यशाध्य गौशाला से</p>
            </div>
            <p class="bl-note hi mt-4 mb-0">{{ $t['sample_note'] }}</p>
          </div>
        </div>

        <aside class="col-lg-4">
          <div class="ar-rail">
            <div class="ar-bottle" data-ar-bottle aria-hidden="true">
              <svg viewBox="0 0 54 96">
                <defs><clipPath id="arBottleClip"><path d="M19 14h16v10c0 4 9 8 9 18v44a6 6 0 0 1-6 6H16a6 6 0 0 1-6-6V42c0-10 9-14 9-18z"/></clipPath></defs>
                <rect class="cap" x="17" y="4" width="20" height="10" rx="3"/>
                <rect clip-path="url(#arBottleClip)" x="0" y="96" width="54" height="96" fill="#FFFDF6" data-ar-milk/>
                <path class="glass" d="M19 14h16v10c0 4 9 8 9 18v44a6 6 0 0 1-6 6H16a6 6 0 0 1-6-6V42c0-10 9-14 9-18z"/>
              </svg>
              <span class="ar-bottle-txt"><b data-ar-pct>0%</b>पढ़ते-पढ़ते बोतल भरिए<br><a class="ar-bottle-done" href="{{ $trial }}" target="_blank" rel="noopener">शाबाश! अब असली चखिए →</a></span>
            </div>
            @if ($headings->isNotEmpty())
              <nav class="ar-card ar-toc" aria-label="{{ $a['contents'] }}">
                <span class="bl-cap bl-hand">{{ $a['contents'] }}</span>
                <ol class="hi">
                  @foreach ($headings as $i => $h)
                    <li><a href="#h{{ $i + 1 }}" data-ar-toc>{{ $h[1] }}</a></li>
                  @endforeach
                </ol>
              </nav>
            @endif
            <div class="ar-card">
              <span class="bl-cap bl-hand">{{ $a['size'] }}</span>
              <div class="ar-size" role="group" aria-label="{{ $a['size'] }}">
                <button type="button" data-ar-size="-1" aria-label="A−">A−</button>
                <button type="button" data-ar-size="1" aria-label="A+">A+</button>
              </div>
              <div class="ar-share">
                <a class="ar-share-wa" href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener">
                  <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" fill="currentColor"/></svg> {{ $a['share'] }}
                </a>
                <button type="button" class="hi" data-ar-copy data-url="{{ $url($post) }}" data-done="{{ $a['copied'] }}">{{ $a['copy'] }}</button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <!-- ============ TRY IT ============ -->
  <section class="ar-cta text-center">
    <div class="container-xl">
      <h2 class="bl-h2 hi">{{ $a['cta_title'] }}</h2>
      <a class="btn btn-gold rounded-pill px-5 py-3 mt-3 hi magnetic" href="{{ $trial }}" target="_blank" rel="noopener">{{ $a['cta'] }} →</a>
    </div>
  </section>

  <!-- ============ RELATED polaroids ============ -->
  <section class="bl-related">
    <div class="container-xl">
      <span class="bl-cap-light">{{ $a['related'] }} ↓</span>
      <div class="bl-grid mt-5">
        @foreach ($related as $p)
          <a class="bl-polaroid bl-card" href="{{ $url($p) }}">
            <span class="bl-tape" aria-hidden="true"></span>
            <span class="bl-photo"><img src="{{ $pic($p['img'], 480) }}" alt="" loading="lazy" decoding="async"></span>
            <span class="bl-hand bl-cap"><i class="bl-dot bl-c-{{ $p['cat'] }}"></i>{{ $t['categories'][$p['cat']] }}</span>
            <span class="bl-ptitle hi">{{ $p['title'] }}</span>
            <span class="bl-hand bl-pmeta">{{ $day($p)->translatedFormat('j M') }} · {{ $mins($p) }} {{ $t['min'] }}</span>
          </a>
        @endforeach
      </div>
    </div>
  </section>
</article>
@endsection

@push('scripts')
<script src="{{ asset('dummyassets/blog.js') }}?v={{ filemtime(public_path('dummyassets/blog.js')) }}" defer></script>
@endpush
