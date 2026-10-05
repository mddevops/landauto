<?php

namespace App\Models;

use App\Enums\DeliveryDestinationType;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One Submission + Form Route delivery (FORMS_AND_INTEGRATIONS.md §25), unique per pair. Its
 * `public_id` is the stable idempotency key sent to destinations on every attempt. Error fields
 * hold only safe codes and short Russian messages, never secrets or provider bodies.
 *
 * @property int $id
 * @property string $public_id
 * @property int $submission_id
 * @property int $form_route_id
 * @property DeliveryDestinationType $destination_type
 * @property DeliveryStatus $status
 * @property int $attempt_count
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $last_attempt_at
 * @property int|null $last_http_status
 * @property string|null $last_error_code
 * @property string|null $last_error_message_safe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Submission $submission
 * @property-read FormRoute $route
 */
#[Hidden(['id', 'submission_id', 'form_route_id'])]
class SubmissionDelivery extends Model
{
    use HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempt_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination_type' => DeliveryDestinationType::class,
            'status' => DeliveryStatus::class,
            'attempt_count' => 'integer',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'last_http_status' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SubmissionDelivery $delivery): void {
            if ($delivery->exists && $delivery->isDirty(['submission_id', 'form_route_id', 'destination_type'])) {
                throw new LogicException('Delivery Submission and route are immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<FormRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(FormRoute::class, 'form_route_id');
    }

    /**
     * @return HasMany<SubmissionDeliveryAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(SubmissionDeliveryAttempt::class)->orderBy('attempt_number');
    }
}
