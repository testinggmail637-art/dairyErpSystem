<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Farm;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => (string) fake()->unique()->numberBetween(6000000000, 9999999999),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /** Give the user a seeded role (roles and permissions are seeded on first use). */
    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            if (! Role::query()->where('name', $role->value)->exists()) {
                (new RolesAndPermissionsSeeder)->run();
            }

            $user->assignRole($role->value);
        });
    }

    /** Link the user to $farm (a new farm when null), so app routes pass EnsureFarmSelected. */
    public function forFarm(?Farm $farm = null): static
    {
        return $this->hasAttached($farm ?? Farm::factory(), [], 'farms');
    }
}
