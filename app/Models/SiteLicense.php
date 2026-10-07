<?php

namespace App\Models;

use App\Enums\SiteLicenseSource;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteLicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Right of one Site to use one catalog item (D-079): a Block Definition or a Template, never both.
 * A Template license also covers the Blocks of that Template for the same Site. It never covers
 * another Site; revoking deletes it. The granting User is audit identity only.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int|null $block_definition_id
 * @property int|null $template_id
 * @property SiteLicenseSource $source
 * @property int|null $granted_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'block_definition_id', 'template_id', 'granted_by_user_id'])]
class SiteLicense extends Model
{
    /** @use HasFactory<SiteLicenseFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['source' => SiteLicenseSource::class];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteLicense $license): void {
            if (($license->block_definition_id === null) === ($license->template_id === null)) {
                throw new LogicException('A Site license covers exactly one Block Definition or Template.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Site licenses are immutable; revoke and grant again instead.');
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
     * @return BelongsTo<BlockDefinition, $this>
     */
    public function blockDefinition(): BelongsTo
    {
        return $this->belongsTo(BlockDefinition::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
