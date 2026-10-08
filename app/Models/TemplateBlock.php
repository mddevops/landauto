<?php

namespace App\Models;

use App\Blocks\BlockStateValidator;
use App\Models\Concerns\HasImmutablePublicId;
use App\Templates\TemplateBlockReferences;
use Database\Factories\TemplateBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Template-owned Draft use of a pinned published Block Version on a Template Page (P9-007).
 * Templates own no assets, vehicles or Popups, so state may reference only Template Pages and
 * scroll targets on the same Page.
 *
 * @property int $id
 * @property string $public_id
 * @property int $template_page_id
 * @property int $block_version_id
 * @property int $sort_order
 * @property bool $is_hidden
 * @property array<string, mixed> $state_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sort_order', 'state_json'])]
#[Hidden(['id', 'template_page_id', 'block_version_id'])]
class TemplateBlock extends Model
{
    /** @use HasFactory<TemplateBlockFactory> */
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
        static::creating(function (TemplateBlock $block): void {
            if ($block->version->definition->isWorkspacePrivate()) {
                throw new LogicException('Templates may only use catalog Blocks.');
            }
        });

        static::updating(function (TemplateBlock $block): void {
            if ($block->isDirty('template_page_id') || $block->isDirty('block_version_id')) {
                throw new LogicException('Template Block page and Block Version are immutable.');
            }
        });

        static::saving(function (TemplateBlock $block): void {
            app(BlockStateValidator::class)->assertValid(
                $block->version->schema_json,
                $block->state_json,
                new TemplateBlockReferences($block->page),
            );
        });
    }

    /**
     * @return BelongsTo<TemplatePage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(TemplatePage::class, 'template_page_id');
    }

    /**
     * @return BelongsTo<BlockVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'block_version_id');
    }
}
