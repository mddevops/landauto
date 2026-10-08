<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\TemplatePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Draft Page of a Template (P9-007), edited in the Designer like a Site Page.
 *
 * @property int $id
 * @property string $public_id
 * @property int $template_id
 * @property string $title
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_home
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'sort_order'])]
#[Hidden(['id', 'template_id'])]
class TemplatePage extends Model
{
    /** @use HasFactory<TemplatePageFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (TemplatePage $page): void {
            if ($page->isDirty('template_id')) {
                throw new LogicException('Template Page template is immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<TemplateBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class)->orderBy('sort_order');
    }

    /**
     * Stored as TRUE or NULL so the (template_id, is_home) unique index allows one home Page.
     *
     * @return Attribute<bool, bool>
     */
    protected function isHome(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value,
            set: fn (bool $value): ?bool => $value ? true : null,
        );
    }
}
