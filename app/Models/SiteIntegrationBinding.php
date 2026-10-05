<?php

namespace App\Models;

use App\Enums\IntegrationStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteIntegrationBindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A Site's use of a Workspace Integration Profile (D-040): Site-specific, non-secret overrides
 * such as dealer or source IDs. The shared credentials stay on the profile and are never copied.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int $integration_profile_id
 * @property string|null $name
 * @property array<string, string> $overrides_json
 * @property array<string, mixed>|null $mapping_defaults_json
 * @property IntegrationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read IntegrationProfile $profile
 */
#[Fillable(['name', 'overrides_json', 'mapping_defaults_json'])]
#[Hidden(['id', 'site_id', 'integration_profile_id'])]
class SiteIntegrationBinding extends Model
{
    /** @use HasFactory<SiteIntegrationBindingFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'overrides_json' => '{}',
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overrides_json' => 'array',
            'mapping_defaults_json' => 'array',
            'status' => IntegrationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteIntegrationBinding $binding): void {
            if ($binding->exists && $binding->isDirty(['site_id', 'integration_profile_id'])) {
                throw new LogicException('Integration binding Site and profile are immutable.');
            }

            $siteWorkspace = Site::query()->whereKey($binding->site_id)->value('workspace_id');
            $profileWorkspace = IntegrationProfile::query()->whereKey($binding->integration_profile_id)->value('workspace_id');

            if ($siteWorkspace === null || $siteWorkspace !== $profileWorkspace) {
                throw new LogicException('Integration binding profile must belong to the Site Workspace.');
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
     * @return BelongsTo<IntegrationProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class, 'integration_profile_id');
    }

    public function isActive(): bool
    {
        return $this->status === IntegrationStatus::Active;
    }
}
