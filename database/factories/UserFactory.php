<?php

namespace Database\Factories;

use App\Enums\Rank;
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

    /**
     * Give the user a specific platform rank.
     */
    public function rank(Rank $rank): static
    {
        return $this->state(fn (array $attributes) => [
            'rank' => $rank,
        ]);
    }

    /**
     * Make the user the platform owner (Admin I).
     */
    public function adminOne(): static
    {
        return $this->rank(Rank::AdminOne);
    }

    /**
     * Make the user an appointed Admin II.
     */
    public function adminTwo(): static
    {
        return $this->rank(Rank::AdminTwo);
    }

    /**
     * Give the user a verified secondary recovery email.
     */
    public function withVerifiedRecoveryEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'recovery_email' => fake()->unique()->safeEmail(),
            'recovery_email_verified_at' => now(),
        ]);
    }
}
