<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceAsset>
 */
class WorkspaceAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'path' => 'workspace-assets/test/'.Str::lower((string) Str::ulid()).'.png',
            'original_name' => 'photo.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 800,
            'height' => 600,
        ];
    }
}
