<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            // Random per user — no shared/known password. Tests that need to
            // log in must pass their own 'password' override explicitly.
            'password' => Str::password(20),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'office_region' => 'CHR Region VIII',
            'is_staff' => false,
            // Unrated: (2 - 1.0) = 1.0, so WCS is the plain sum of weights.
            'performance_rating' => 1.0,
            'role_id' => fn () => Role::default()->id,
            'remember_token' => Str::random(10),
            // Factory users are approved and active by default so every
            // existing test that logs one in keeps working unchanged; tests
            // for the pending/rejected/inactive paths override explicitly.
            'registration_status' => User::REGISTRATION_APPROVED,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the user carries their own caseload.
     */
    public function investigator(): static
    {
        return $this->withRole(Role::INVESTIGATOR);
    }

    /**
     * Indicate that the user oversees the whole office's caseload.
     */
    public function supervisor(): static
    {
        return $this->withRole(Role::SUPERVISOR)->state(fn (array $attributes) => [
            'is_staff' => true,
        ]);
    }

    /**
     * Indicate that the user administers accounts and registrations.
     */
    public function admin(): static
    {
        return $this->withRole(Role::ADMIN);
    }

    /**
     * Indicate that the user holds the named role.
     */
    public function withRole(string $roleName): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['role_name' => $roleName])->id,
        ]);
    }
}
