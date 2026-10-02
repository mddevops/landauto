<?php

namespace App\Models;

use App\Enums\Entitlement;
use App\Enums\EntitlementValueType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plan_id
 * @property Entitlement $key
 * @property EntitlementValueType $value_type
 * @property bool|null $boolean_value
 * @property int|null $integer_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value_type', 'boolean_value', 'integer_value'])]
#[Hidden(['id', 'plan_id'])]
class PlanEntitlement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => Entitlement::class,
            'value_type' => EntitlementValueType::class,
            'boolean_value' => 'boolean',
            'integer_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
