@extends('layouts.website')

@section('title', $page === 'home' ? '' : __("website.pages.$page"))

@section('content')
    <section class="w-section w-page-intro">
        <div class="container">
            <h1>{{ $page === 'home' ? \App\Support\Website::get('tagline') : __("website.pages.$page") }}</h1>
            <p class="w-lead">{{ __('website.placeholder.text') }}</p>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="w-btn w-btn-accent" href="{{ \App\Support\Website::telLink() }}">@include('website.icons.telephone-fill') {{ __('website.action.call') }}</a>
                <a class="w-btn w-btn-outline" href="{{ \App\Support\Website::whatsappLink() }}" target="_blank" rel="noopener">@include('website.icons.whatsapp') {{ __('website.action.whatsapp') }}</a>
            </div>
        </div>
    </section>
@endsection
