<?php

namespace App\Models;

use App\Enums\PublishedVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A rendered HTML artifact of one Page in a Published Version. Written only while the version is
 * building; frozen together with the version once it is ready.
 *
 * @property int $id
 * @property int $published_version_id
 * @property string $page_public_id
 * @property string $slug
 * @property bool $is_home
 * @property string $title
 * @property int $sort_order
 * @property string $rendered_html
 * @property array<string, mixed> $hydration_json
 * @property array<string, mixed> $seo_json
 * @property string $content_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'published_version_id'])]
class PublishedPage extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'sort_order' => 'integer',
            'hydration_json' => 'array',
            'seo_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PublishedPage $page): void {
            if ($page->exists) {
                throw new LogicException('Published Page artifacts are immutable.');
            }

            $status = PublishedVersion::query()->whereKey($page->published_version_id)->first()?->status;

            if ($status !== PublishedVersionStatus::Building) {
                throw new LogicException('Artifacts can only be added to a building Published Version.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Published Page artifacts are immutable.');
        });
    }

    /**
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'published_version_id');
    }

    public function path(): string
    {
        return $this->is_home ? '/' : '/'.$this->slug;
    }
}
