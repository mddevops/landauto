<?php

namespace App\Models;

use App\Enums\CatalogLicenseScope;
use App\Enums\CatalogLicenseSource;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\CatalogLicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Right to use one catalog item — a Block Definition or a Template, never both — for exactly one
 * target (D-121): one Site (`site` scope) or one Workspace with all its current and future Sites
 * (`workspace` scope). A Template license also covers the Block Versions of the Template Version
 * being installed. Identity is immutable; revoking deletes it, already installed Block Versions
 * stay usable (D-122). The granting User is audit identity only.
 *
 * @property int $id
 * @property string $public_id
 * @property CatalogLicenseScope $scope
 * @property int|null $site_id
 * @property int|null $workspace_id
 * @property int|null $block_definition_id
 * @property int|null $template_id
 * @property CatalogLicenseSource $source
 * @property int|null $granted_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'workspace_id', 'block_definition_id', 'template_id', 'granted_by_user_id'])]
class CatalogLicense extends Model
{
    /** @use HasFactory<CatalogLicenseFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope' => CatalogLicenseScope::class, 'source' => CatalogLicenseSource::class];
    }

    protected static function booted(): void
    {
        static::saving(function (CatalogLicense $license): void {
            if (($license->block_definition_id === null) === ($license->template_id === null)) {
                throw new LogicException('A catalog license covers exactly one Block Definition or Template.');
            }

            $targetsSite = $license->site_id !== null && $license->workspace_id === null;
            $targetsWorkspace = $license->workspace_id !== null && $license->site_id === null;

            if (! match ($license->getAttribute('scope')) {
                CatalogLicenseScope::Site => $targetsSite,
                CatalogLicenseScope::Workspace => $targetsWorkspace,
                default => false,
            }) {
                throw new LogicException('A catalog license targets exactly one Site or one Workspace, matching its scope.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Catalog licenses are immutable; revoke and grant again instead.');
        });
    }

    /**
     * Licenses effective for the Site: its own Site licenses and its Workspace's licenses.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveFor(Builder $query, Site $site): void
    {
        $query->where(fn (Builder $target) => $target
            ->where('site_id', $site->id)
            ->orWhere('workspace_id', $site->workspace_id));
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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
