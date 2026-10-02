<?php

namespace App\Models;

use App\Enums\Entitlement;
use App\Enums\EntitlementValueType;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'is_active'])]
#[Hidden(['id'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PlanEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function setEntitlement(Entitlement $entitlement, bool|int $value): PlanEntitlement
    {
        $expectedType = $entitlement->valueType();

        if (($expectedType === EntitlementValueType::Boolean && ! is_bool($value))
            || ($expectedType === EntitlementValueType::Integer && (! is_int($value) || $value < 0))) {
            throw new \InvalidArgumentException("Invalid value for entitlement {$entitlement->value}.");
        }

        return $this->entitlements()->updateOrCreate(
            ['key' => $entitlement],
            [
                'value_type' => $expectedType,
                'boolean_value' => is_bool($value) ? $value : null,
                'integer_value' => is_int($value) ? $value : null,
            ],
        );
    }
}
