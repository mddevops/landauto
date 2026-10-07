<?php

namespace App\Models;

use Database\Factories\TemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable published snapshot of a Template (P9-007). `content_json` holds the Pages and their
 * Block Instances with pinned Block Versions; Sites copy it (P9-015) and never sync later changes.
 * Internal storage only: never sent to the browser as-is.
 *
 * @property int $id
 * @property int $template_id
 * @property string $version
 * @property array{pages: list<array{key: string, title: string, slug: string, is_home: bool, blocks: list<array{key: string, block_version_id: int, is_hidden: bool, state: array<string, mixed>}>}>}|null $content_json
 * @property int|null $published_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['version'])]
#[Hidden(['id', 'template_id', 'content_json', 'published_by_user_id'])]
class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Template Versions are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Template Versions are immutable.');
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
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
