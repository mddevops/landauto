<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'role' => WorkspaceRole::Designer,
            'token_hash' => WorkspaceInvitation::hashToken(Str::random(64)),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['cancelled_at' => now()]);
    }
}
