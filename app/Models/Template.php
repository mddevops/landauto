<?php

namespace App\Models;

use App\Enums\SiteType;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property list<string>|null $site_types
 * @property bool $is_official
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
#[Hidden(['id'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'site_types' => 'array',
        ];
    }

    /**
     * Compatible Site types declared by the Template (D-119), in canonical order.
     *
     * @return list<SiteType>
     */
    public function siteTypes(): array
    {
        $stored = $this->site_types ?? [];

        return array_values(array_filter(SiteType::cases(), fn (SiteType $type): bool => in_array($type->value, $stored, true)));
    }

    public function supports(SiteType $type): bool
    {
        return in_array($type, $this->siteTypes(), true);
    }

    /**
     * Templates a customer may create a Site from.
     *
     * @param  Builder<Template>  $query
     */
    public function scopeAvailableForSites(Builder $query): void
    {
        $query->where('is_official', true);
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }
}
