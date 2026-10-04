<?php

namespace App\Models;

use App\Blocks\BlockStateValidator;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\BlockInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site-owned draft use of a pinned Block Version on a Page (BLOCK_SYSTEM.md §61).
 *
 * @property int $id
 * @property string $public_id
 * @property int $page_id
 * @property int $block_version_id
 * @property int $sort_order
 * @property bool $is_hidden
 * @property array<string, mixed> $state_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sort_order', 'state_json'])]
#[Hidden(['id', 'page_id', 'block_version_id'])]
class BlockInstance extends Model
{
    /** @use HasFactory<BlockInstanceFactory> */
    use HasFactory, HasImmutablePublicId;

    protected $table = 'page_blocks';

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
        static::creating(function (BlockInstance $instance): void {
            if (! $instance->version->definition->is_official) {
                throw new LogicException('Only official Blocks can be placed until other Block scopes are defined.');
            }
        });

        static::updating(function (BlockInstance $instance): void {
            if ($instance->isDirty('page_id')) {
                throw new LogicException('Block Instance page is immutable.');
            }

            if ($instance->isDirty('block_version_id')) {
                throw new LogicException('Block Version is pinned; upgrades need an explicit workflow.');
            }
        });

        static::saving(function (BlockInstance $instance): void {
            app(BlockStateValidator::class)->assertValid($instance->version->schema_json, $instance->state_json);
        });
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<BlockVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'block_version_id');
    }
}
