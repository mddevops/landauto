<?php

namespace App\Models\Catalog\Concerns;

use App\Exceptions\InvalidCatalogDataException;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog V2 dictionaries are exactly two levels: root rows are groups, children are entries
 * whose parent is a root group. Cycles are impossible because the parent is fixed after creation.
 *
 * @property int|null $parent_id
 * @property string $code
 *
 * @mixin Model
 */
trait TwoLevelDictionary
{
    public const CODE_PATTERN = '/^[a-z][a-z0-9_]{0,99}$/';

    public static function bootTwoLevelDictionary(): void
    {
        static::saving(function (Model $entry): void {
            /** @var static $entry */
            if (preg_match(self::CODE_PATTERN, (string) $entry->code) !== 1) {
                throw new InvalidCatalogDataException('Dictionary code must be lowercase latin letters, digits and underscores.');
            }

            if ($entry->parent_id === null) {
                return;
            }

            $parent = static::query()->find($entry->parent_id);

            if ($parent === null || $parent->parent_id !== null || ($entry->exists && $parent->getKey() === $entry->getKey())) {
                throw new InvalidCatalogDataException('A dictionary entry parent must be a root group.');
            }
        });
    }

    public function isGroup(): bool
    {
        return $this->parent_id === null;
    }
}
