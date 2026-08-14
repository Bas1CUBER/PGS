<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Employee,
            'office' => fake()->randomElement(['ICT Division', 'Administrative', 'Clinical Services']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
        ]);
    }

    public function focal(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Focal,
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Employee,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Grant every module in the page-access matrix (needed for routes gated
     * by `page.access:*` since access is deny-by-default).
     */
    public function withPageAccess(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->pageAccess()->create([
                'roadmaps' => true,
                'scorecard' => true,
                'performance_assessment' => true,
                'cascading' => true,
                'governance' => true,
            ]);
        });
    }
}
