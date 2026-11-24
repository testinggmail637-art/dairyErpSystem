<?php

use App\Models\Farm;

beforeEach(function () {
    Farm::factory()->create();
});

it('shows every home section in Hindi and English', function (string $url, string $locale) {
    $response = $this->get($url)->assertOk();

    foreach (['hero.title', 'free_from.title', 'offer.title', 'breeds.title', 'products.eyebrow', 'compare.title', 'bilona.title', 'trust.title',
        'order.title', 'why.title', 'ecosystem.title', 'faq.title', 'questions.title', 'enquiry.title'] as $key) {
        $response->assertSee(__("home.$key", [], $locale));
    }
    foreach (__('home.breeds.items', [], $locale) as $breed) {
        $response->assertSee($breed['name']);
    }
})->with([['/', 'hi'], ['/en', 'en']]);

it('only uses photos that exist, in WebP with a srcset', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('#/images/website/home/([a-z0-9-]+\.webp)#', $html, $m);
    expect($m[1])->not->toBeEmpty();
    foreach (array_unique($m[1]) as $file) {
        expect(public_path("images/website/home/$file"))->toBeFile();
    }
    expect($html)->toContain('srcset=')->toContain('loading="lazy"')->toContain('fetchpriority="high"');
});

it('never shows the sample reviews on the live site', function () {
    $this->get('/')->assertSee(__('home.reviews.title', [], 'hi'));

    app()->detectEnvironment(fn () => 'production');
    $this->get('/')->assertOk()->assertDontSee(__('home.reviews.title', [], 'hi'));
});
