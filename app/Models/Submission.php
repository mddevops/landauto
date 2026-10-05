<?php

namespace App\Models;

use App\Enums\SubmissionMode;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A persisted visitor lead. `payload` snapshots each field's key, type, label and value at
 * submission time, so history stays readable after the Form changes (FORMS_AND_INTEGRATIONS.md §32).
 * All personal data lives in this row, so future deletion/anonymization (D-094) is row-local.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int $form_id
 * @property SubmissionStatus $status
 * @property SubmissionMode $mode
 * @property list<array{key: string, type: string, label: string, value: string|bool|null}> $payload
 * @property array<string, mixed>|null $context
 * @property string|null $phone_original
 * @property string|null $phone_normalized
 * @property string|null $email_normalized
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'form_id', 'ip', 'user_agent'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, HasImmutablePublicId;

    /** Snapshot columns that never change after the lead is stored. */
    private const IMMUTABLE = ['site_id', 'form_id', 'mode', 'payload', 'context', 'phone_original', 'phone_normalized', 'email_normalized', 'ip', 'user_agent', 'submitted_at'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'received',
        'mode' => 'public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'mode' => SubmissionMode::class,
            'payload' => 'array',
            'context' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Submission $submission): void {
            if ($submission->exists && $submission->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Submission snapshot is immutable.');
            }

            $form = $submission->form()->first();

            if ($form === null || $form->site_id !== $submission->site_id) {
                throw new LogicException('Submission Form must belong to the Submission Site.');
            }
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
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return HasMany<SubmissionDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SubmissionDelivery::class);
    }
}
