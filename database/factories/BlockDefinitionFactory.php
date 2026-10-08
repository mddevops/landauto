<?php

namespace Database\Factories;

use App\Enums\BlockOwnerScope;
use App\Models\BlockDefinition;
use App\Models\DeveloperProfile;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Ownership is always explicit; the default is a platform-owned (official) definition.
 *
 * @extends Factory<BlockDefinition>
 */
class BlockDefinitionFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'owner_scope' => BlockOwnerScope::Platform,
            'developer_profile_id' => null,
            'workspace_id' => null,
        ];
    }

    public function platform(): static
    {
        return $this->state([
            'owner_scope' => BlockOwnerScope::Platform,
            'developer_profile_id' => null,
            'workspace_id' => null,
        ]);
    }

    public function developer(?DeveloperProfile $profile = null): static
    {
        return $this->state([
            'owner_scope' => BlockOwnerScope::Developer,
            'developer_profile_id' => $profile ?? DeveloperProfile::factory(),
            'workspace_id' => null,
        ]);
    }

    public function workspacePrivate(?Workspace $workspace = null): static
    {
        return $this->state([
            'owner_scope' => BlockOwnerScope::WorkspacePrivate,
            'developer_profile_id' => null,
            'workspace_id' => $workspace ?? Workspace::factory(),
        ]);
    }
}
