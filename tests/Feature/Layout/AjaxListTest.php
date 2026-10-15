<?php

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Section;
use App\Models\User;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    Section::seedDefaultsFor($this->farm);
    $this->owner = User::factory()->forFarm($this->farm)->withRole(RoleName::Owner)->create();
    User::factory()->forFarm($this->farm)->create(['name' => 'Asha Accountant']);
});

it('returns the full page with an AJAX-enabled filter form normally', function (string $route, string $target) {
    $this->actingAs($this->owner)->get(route($route))
        ->assertOk()
        ->assertSee('<html', false)
        ->assertSee('data-ajax-list="#'.$target.'"', false)
        ->assertSee('id="'.$target.'" data-ajax-results', false)
        ->assertHeaderMissing('X-Ajax-List-Fragment');
})->with([
    ['users.index', 'users-results'],
    ['audit-logs.index', 'audit-results'],
    ['sections.index', 'master-results'],
]);

it('returns only the results fragment for AJAX filter requests', function () {
    $this->actingAs($this->owner)
        ->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('users.index', ['search' => 'asha']))
        ->assertOk()
        ->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertDontSee('<html', false)
        ->assertDontSee('data-ajax-list', false)
        ->assertSee('<table', false)
        ->assertSee('Asha Accountant')
        ->assertDontSee($this->owner->name);

    $this->actingAs($this->owner)
        ->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('sections.index', ['search' => 'herd']))
        ->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertSee('Own Farm/Herd')
        ->assertDontSee('Animal Trading');

    $this->actingAs($this->owner)
        ->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('audit-logs.index', ['event' => 'updated']))
        ->assertHeader('X-Ajax-List-Fragment', '1')
        ->assertDontSee('<html', false);
});

it('does not mark a redirect as a fragment (invalid filter), so the browser shows the full page', function () {
    $this->actingAs($this->owner)
        ->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('audit-logs.index', ['from' => '2026-10-12', 'to' => '2026-10-11']))
        ->assertRedirect()
        ->assertHeaderMissing('X-Ajax-List-Fragment');
});

it('keeps pagination links inside the fragment', function () {
    User::factory()->count(30)->forFarm($this->farm)->create();

    $this->actingAs($this->owner)
        ->withHeaders(['X-Ajax-List' => '1'])
        ->get(route('users.index'))
        ->assertSee('class="pagination', false)
        ->assertSee('page=2', false);
});
