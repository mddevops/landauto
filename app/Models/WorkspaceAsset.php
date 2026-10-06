<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\WorkspaceAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable image in the shared Workspace media library (D-087 for Phase 8). It is only a source:
 * using it on a Site copies the file into a new SiteAsset, so Sites and Published Versions never
 * reference Workspace Assets.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_name'])]
#[Hidden(['id', 'workspace_id', 'path'])]
class WorkspaceAsset extends Model
{
    public const DISK = SiteAsset::DISK;

    /** @use HasFactory<WorkspaceAssetFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (WorkspaceAsset $asset): void {
            if ($asset->isDirty(['workspace_id', 'path', 'mime_type', 'size_bytes', 'width', 'height'])) {
                throw new LogicException('Workspace Asset files are immutable; upload a new asset instead.');
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
