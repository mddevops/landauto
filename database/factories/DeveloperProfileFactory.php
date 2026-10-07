<?php

namespace Database\Factories;

use App\Enums\DeveloperProfileStatus;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeveloperProfile>
 */
class DeveloperProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->company(),
            'slug' => fake()->unique()->regexify('[a-z]{4,10}-[a-z0-9]{4}'),
            'status' => DeveloperProfileStatus::Active,
            'bio' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => DeveloperProfileStatus::Suspended]);
    }
}
