<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Identity mirrors what real bpms-provisioned users look like: a string id
     * and a unique username. `password` is vestigial (credentials are verified
     * against bpms.validate_login_apps, never locally) — left null by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'is_active' => true,
        ];
    }
}
