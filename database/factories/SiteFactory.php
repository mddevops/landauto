<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company(),
            'subdomain' => fn (): string => 'site-'.Str::lower((string) Str::ulid()),
            'status' => SiteStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => SiteStatus::Archived]);
    }

    public function withoutSubdomain(): static
    {
        return $this->state(fn (): array => ['subdomain' => null]);
    }
}
