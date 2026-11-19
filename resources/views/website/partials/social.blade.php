@foreach (\App\Support\Website::get('social', []) as $network => $url)
    @if ($url)
        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}">@include("website.icons.$network")</a>
    @endif
@endforeach
