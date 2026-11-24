<?php

use App\Models\Farm;

beforeEach(function () {
    Farm::factory()->create();
});

it('shows all twelve home sections in Hindi and English', function (string $url, string $locale) {
    $response = $this->get($url)->assertOk();

    foreach (['hero.title', 'quality.title_2', 'offer.title', 'breeds.title', 'journey.title', 'products.title',
        'delivery.title', 'why.quote', 'questions.title', 'questions.note', 'reviews.title', 'faq.title_1', 'enquiry.title_2'] as $key) {
        $response->assertSee(__("home.$key", [], $locale));
    }
    foreach (__('home.questions.rows', [], $locale) as $row) {
        $response->assertSee($row['q'])->assertSee($row['us']);
    }
    foreach (__('home.breeds.items', [], $locale) as $breed) {
        $response->assertSee($breed['name']);
    }
    $response->assertSee(__('website.footer.word', [], $locale)); // 12. footer brand word
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

it('takes the new band colours from the website settings', function () {
    $this->get('/')->assertSee('--w-band: #093F1E', false)->assertSee('--w-on-band: #F8F4E8', false);
});

it('never shows the sample reviews on the live site', function () {
    $this->get('/')->assertSee(__('home.reviews.title', [], 'hi'));

    app()->detectEnvironment(fn () => 'production');
    $this->get('/')->assertOk()->assertDontSee(__('home.reviews.title', [], 'hi'));
});
