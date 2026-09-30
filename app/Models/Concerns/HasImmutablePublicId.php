<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Internal bigint key plus an immutable ULID `public_id` used for routes and serialized output (ADR-001).
 *
 * @mixin Model
 */
trait HasImmutablePublicId
{
    use HasUlids;

    public static function bootHasImmutablePublicId(): void
    {
        static::updating(function (Model $model): void {
            if ($model->isDirty('public_id')) {
                throw new LogicException('public_id is immutable.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
