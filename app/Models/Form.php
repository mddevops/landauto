<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site-owned data-collection entity (FORMS_AND_INTEGRATIONS.md §3). Fields are addressed by
 * stable machine keys that future integration mapping relies on.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property string $name
 * @property bool $status
 * @property string $submit_label
 * @property string $success_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'status', 'submit_label', 'success_message'])]
#[Hidden(['id', 'site_id'])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory, HasImmutablePublicId;

    public const DEFAULT_SUBMIT_LABEL = 'Отправить';

    public const DEFAULT_SUCCESS_MESSAGE = 'Спасибо! Мы свяжемся с вами.';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => true,
        'submit_label' => self::DEFAULT_SUBMIT_LABEL,
        'success_message' => self::DEFAULT_SUCCESS_MESSAGE,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (Form $form): void {
            if ($form->isDirty('site_id')) {
                throw new LogicException('Form Site is immutable.');
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
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }
}
