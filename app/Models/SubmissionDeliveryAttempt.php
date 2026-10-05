<?php

namespace App\Models;

use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryTrigger;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One adapter call of a Delivery (FORMS_AND_INTEGRATIONS.md §26). `response_summary_json` keeps
 * only allowlisted metadata; tokens, Authorization headers and raw payloads are never stored.
 *
 * @property int $id
 * @property int $submission_delivery_id
 * @property int $attempt_number
 * @property DeliveryTrigger $trigger
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property DeliveryOutcome|null $status
 * @property int|null $http_status
 * @property string|null $provider_code
 * @property int|null $latency_ms
 * @property array<string, scalar|null>|null $response_summary_json
 * @property string|null $error_code
 * @property string|null $safe_error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'submission_delivery_id'])]
class SubmissionDeliveryAttempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => DeliveryTrigger::class,
            'status' => DeliveryOutcome::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'http_status' => 'integer',
            'latency_ms' => 'integer',
            'response_summary_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SubmissionDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(SubmissionDelivery::class, 'submission_delivery_id');
    }
}
