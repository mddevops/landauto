<?php

namespace App\Models;

use App\Enums\DomainRoutingStatus;
use App\Enums\DomainSslStatus;
use App\Enums\DomainVerificationStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A customer hostname of a Site (P7-001, D-111). Ownership (TXT), routing (A/AAAA/CNAME) and SSL
 * are independent lifecycles; only a fully ready domain may become primary. Certificates and
 * private keys live in infrastructure, never here.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property string $hostname
 * @property string $verification_token
 * @property DomainVerificationStatus $verification_status
 * @property DomainRoutingStatus $routing_status
 * @property DomainSslStatus $ssl_status
 * @property bool $is_primary
 * @property Carbon|null $verified_at
 * @property Carbon|null $routing_verified_at
 * @property Carbon|null $ssl_issued_at
 * @property Carbon|null $ssl_expires_at
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error_code
 * @property string|null $last_error_message_safe
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'verification_token'])]
class SiteDomain extends Model
{
    /** @use HasFactory<SiteDomainFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'verification_status' => 'pending',
        'routing_status' => 'pending',
        'ssl_status' => 'pending',
        'is_primary' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => DomainVerificationStatus::class,
            'routing_status' => DomainRoutingStatus::class,
            'ssl_status' => DomainSslStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'routing_verified_at' => 'datetime',
            'ssl_issued_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public static function newVerificationToken(): string
    {
        return Str::lower(Str::random(40));
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function verificationRecordName(): string
    {
        return config('domains.verification_prefix').'.'.$this->hostname;
    }

    public function verificationRecordValue(): string
    {
        return config('domains.verification_value_prefix').$this->verification_token;
    }

    public function isDnsReady(): bool
    {
        return $this->verification_status === DomainVerificationStatus::Verified
            && $this->routing_status === DomainRoutingStatus::Verified;
    }

    public function isActive(): bool
    {
        return $this->isDnsReady() && $this->ssl_status === DomainSslStatus::Active;
    }

    /**
     * One UI state derived from the independent lifecycles.
     */
    public function state(): string
    {
        return match (true) {
            $this->isActive() => 'active',
            $this->ssl_status === DomainSslStatus::Provisioning => 'ssl_provisioning',
            $this->ssl_status === DomainSslStatus::Failed,
            $this->verification_status === DomainVerificationStatus::Failed => 'failed',
            $this->isDnsReady() => 'verified',
            $this->verification_status === DomainVerificationStatus::Verified,
            $this->routing_status !== DomainRoutingStatus::Pending => 'dns_misconfigured',
            default => 'pending',
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state()) {
            'active' => 'Активен',
            'ssl_provisioning' => 'Выпуск сертификата',
            'failed' => 'Ошибка',
            'verified' => 'DNS настроен',
            'dns_misconfigured' => 'DNS настроен не полностью',
            default => 'Ожидает проверки DNS',
        };
    }
}
