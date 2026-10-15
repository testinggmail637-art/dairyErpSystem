<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function notifyUser(User $user, array $data, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => $data,
        'read_at' => $read ? now() : null,
    ]);
}

it('shows the unread count and recent notifications in the bell', function () {
    $user = User::factory()->forFarm()->create();
    notifyUser($user, ['message' => 'Vaccination due for C-101']);
    notifyUser($user, ['message' => 'Feed stock low'], read: true);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Vaccination due for C-101')
        ->assertSee('Feed stock low')
        ->assertSee('data-testid="unread-count">1</span>', false);
});

it('opens a notification, marks it read and follows its link', function () {
    $user = User::factory()->forFarm()->create();
    $n = notifyUser($user, ['message' => 'Go', 'url' => route('profile.edit')]);

    $this->actingAs($user)->get(route('notifications.open', $n->id))
        ->assertRedirect(route('profile.edit'));

    expect($n->fresh()->read_at)->not->toBeNull();
});

it('does not redirect to external links', function () {
    $user = User::factory()->forFarm()->create();
    $n = notifyUser($user, ['message' => 'Bad', 'url' => 'https://evil.example.com']);

    $this->actingAs($user)->get(route('notifications.open', $n->id))
        ->assertRedirect(route('notifications.index'));
});

it('marks all notifications read', function () {
    $user = User::factory()->forFarm()->create();
    notifyUser($user, ['message' => 'A']);
    notifyUser($user, ['message' => 'B']);

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('cannot open a notification of another user', function () {
    $other = notifyUser(User::factory()->forFarm()->create(), ['message' => 'Private']);

    $this->actingAs(User::factory()->forFarm()->create())
        ->get(route('notifications.open', $other->id))
        ->assertNotFound();
});

it('lists notifications', function () {
    $user = User::factory()->forFarm()->create();
    notifyUser($user, ['message' => 'Calving expected for B-07']);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Calving expected for B-07');
});
