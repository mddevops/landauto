<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use App\Enums\PublishFailure;
use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One Publish attempt (ADR-006 §5) with its audit actor. Failure details are limited to a stable
 * code, a safe Russian summary and safe metadata (issue codes/counts); never traces or secrets.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int|null $published_version_id
 * @property int|null $actor_user_id
 * @property string|null $note plain-text publication note, fixed at creation
 * @property PublicationStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $safe_error_code
 * @property string|null $safe_error_summary
 * @property array<string, mixed>|null $metadata_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'published_version_id', 'actor_user_id'])]
class Publication extends Model
{
    use HasImmutablePublicId;

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'validating',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Publication $publication): void {
            if ($publication->isDirty(['site_id', 'actor_user_id', 'note', 'started_at'])) {
                throw new LogicException('Publication identity is immutable.');
            }

            $from = $publication->getOriginal('status');

            if ($from instanceof PublicationStatus && ! $from->isInProgress()) {
                throw new LogicException('A finished Publication is immutable.');
            }

            if ($from instanceof PublicationStatus && $publication->isDirty('status')
                && ! in_array($publication->status, $from->next(), true)) {
                throw new LogicException('Invalid Publication status transition.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Publications are retained as audit records.');
        });
    }

    public function moveTo(PublicationStatus $status): void
    {
        $this->update(['status' => $status]);
    }

    public function succeed(PublishedVersion $version): void
    {
        $this->update([
            'status' => PublicationStatus::Succeeded,
            'published_version_id' => $version->id,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata  safe, non-sensitive details only (issue codes, counts)
     */
    public function fail(PublishFailure $failure, array $metadata = []): void
    {
        $this->update([
            'status' => PublicationStatus::Failed,
            'completed_at' => now(),
            'safe_error_code' => $failure->value,
            'safe_error_summary' => $failure->summary(),
            'metadata_json' => array_merge($this->metadata_json ?? [], $metadata) ?: null,
        ]);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'published_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
