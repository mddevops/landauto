<?php

namespace App\Models;

use App\Enums\DeliveryDestinationType;
use App\Enums\IntegrationStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\FormRouteFactory;
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
 * One independent destination of a Form's Submissions (FORMS_AND_INTEGRATIONS.md §21). HTTP
 * routes go through a binding of the Form's own Site; email routes list their recipients. The
 * visitor never chooses a route, URL or credentials.
 *
 * @property int $id
 * @property string $public_id
 * @property int $form_id
 * @property string $name
 * @property DeliveryDestinationType $destination_type
 * @property int|null $integration_profile_id
 * @property int|null $site_integration_binding_id
 * @property list<string>|null $email_destination
 * @property list<array<string, mixed>>|null $mapping_json
 * @property array<string, mixed>|null $settings_json
 * @property IntegrationStatus $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 * @property-read SiteIntegrationBinding|null $binding
 * @property-read IntegrationProfile|null $profile
 */
#[Fillable(['name', 'email_destination', 'mapping_json', 'settings_json', 'sort_order'])]
#[Hidden(['id', 'form_id', 'integration_profile_id', 'site_integration_binding_id'])]
class FormRoute extends Model
{
    /** @use HasFactory<FormRouteFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination_type' => DeliveryDestinationType::class,
            'status' => IntegrationStatus::class,
            'email_destination' => 'array',
            'mapping_json' => 'array',
            'settings_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FormRoute $route): void {
            if ($route->exists && $route->isDirty(['form_id', 'destination_type', 'integration_profile_id', 'site_integration_binding_id'])) {
                throw new LogicException('Form route owner and destination are immutable.');
            }

            if ($route->destination_type === DeliveryDestinationType::Email) {
                if ($route->site_integration_binding_id !== null || $route->integration_profile_id !== null) {
                    throw new LogicException('Email routes have no integration binding.');
                }

                return;
            }

            $binding = SiteIntegrationBinding::query()->with('profile')->find($route->site_integration_binding_id);
            $siteId = Form::query()->whereKey($route->form_id)->value('site_id');

            if ($binding === null || $binding->site_id !== (int) $siteId
                || $binding->integration_profile_id !== $route->integration_profile_id
                || $binding->profile->provider_type !== $route->destination_type->providerType()) {
                throw new LogicException('HTTP routes must use a matching binding of the Form Site.');
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', IntegrationStatus::Active->value);
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<SiteIntegrationBinding, $this>
     */
    public function binding(): BelongsTo
    {
        return $this->belongsTo(SiteIntegrationBinding::class, 'site_integration_binding_id');
    }

    /**
     * @return BelongsTo<IntegrationProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class, 'integration_profile_id');
    }

    /**
     * @return HasMany<SubmissionDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SubmissionDelivery::class);
    }

    public function isActive(): bool
    {
        return $this->status === IntegrationStatus::Active;
    }
}
