<?php

namespace App\Models;

use App\Blocks\BlockStateValidator;
use App\Blocks\SiteBlockReferences;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site-owned shared Component instance (ADR-013): one per Component Definition, composed onto every Page.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int $block_version_id
 * @property int $sort_order
 * @property bool $is_hidden
 * @property array<string, mixed> $state_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sort_order', 'state_json'])]
#[Hidden(['id', 'site_id', 'block_version_id'])]
class SiteComponent extends Model
{
    /** @use HasFactory<SiteComponentFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_hidden' => 'boolean',
            'state_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SiteComponent $component): void {
            $definition = $component->version->definition;

            if ($definition->isWorkspacePrivate()) {
                throw new LogicException('Workspace-private Blocks cannot be placed until they have a customer runtime.');
            }

            if (! $definition->is_component) {
                throw new LogicException('Only Component Definitions may be stored as Site Components.');
            }
        });

        static::updating(function (SiteComponent $component): void {
            if ($component->isDirty('site_id')) {
                throw new LogicException('Site Component site is immutable.');
            }

            if ($component->isDirty('block_version_id')) {
                throw new LogicException('Block Version is pinned; upgrades need an explicit workflow.');
            }
        });

        static::saving(function (SiteComponent $component): void {
            app(BlockStateValidator::class)->assertValid(
                $component->version->schema_json,
                $component->state_json,
                new SiteBlockReferences($component->site),
            );
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<BlockVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'block_version_id');
    }
}
