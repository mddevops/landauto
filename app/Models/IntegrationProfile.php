<?php

namespace App\Models;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationProviderType;
use App\Enums\IntegrationStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\IntegrationProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Reusable Workspace connection to an external HTTP system (D-039). Credentials are stored
 * encrypted with the application key and never serialized: browser props are built explicitly
 * and show only the masked hint (D-041, D-042).
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property string $name
 * @property IntegrationProviderType $provider_type
 * @property string|null $provider_key
 * @property string|null $base_url
 * @property IntegrationAuthType $auth_type
 * @property array<string, string>|null $encrypted_credentials
 * @property string|null $credentials_hint
 * @property array<string, mixed>|null $settings_json
 * @property IntegrationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'provider_key', 'base_url', 'settings_json'])]
#[Hidden(['id', 'workspace_id', 'encrypted_credentials', 'credentials_hint'])]
class IntegrationProfile extends Model
{
    /** @use HasFactory<IntegrationProfileFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auth_type' => 'none',
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider_type' => IntegrationProviderType::class,
            'auth_type' => IntegrationAuthType::class,
            'status' => IntegrationStatus::class,
            'encrypted_credentials' => 'encrypted:array',
            'settings_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (IntegrationProfile $profile): void {
            if ($profile->exists && $profile->isDirty(['workspace_id', 'provider_type'])) {
                throw new LogicException('Integration Profile owner and provider type are immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isActive(): bool
    {
        return $this->status === IntegrationStatus::Active;
    }
}
