<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the profile page', function () {
    $user = User::factory()->create(['email' => 'sita@example.com']);

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('sita@example.com');
});

it('updates the own name only', function () {
    $user = User::factory()->create(['email' => 'sita@example.com', 'is_active' => true]);

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Sita Devi',
        'email' => 'hacker@example.com',
        'is_active' => false,
    ])->assertRedirect()->assertSessionHas('success');

    $user->refresh();
    expect($user->name)->toBe('Sita Devi')
        ->and($user->email)->toBe('sita@example.com')
        ->and($user->is_active)->toBeTrue();
});

it('changes the password with the current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.password'), [
        'current_password' => 'password',
        'password' => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(Hash::check('new-secret-1', $user->fresh()->password))->toBeTrue();
});

it('refuses a password change with a wrong current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.password'), [
        'current_password' => 'wrong',
        'password' => 'new-secret-1',
        'password_confirmation' => 'new-secret-1',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('requires a confirmed password of at least 8 characters', function () {
    $this->actingAs(User::factory()->create())->put(route('profile.password'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'other',
    ])->assertSessionHasErrors('password');
});
