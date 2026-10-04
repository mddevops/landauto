<?php

namespace App\Models\Catalog;

use App\Models\Catalog\Concerns\TwoLevelDictionary;
use Database\Factories\Catalog\AutoOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Factory option group (root) or option (child).
 *
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property int $sort_order
 * @property-read AutoOption|null $parent
 */
#[Fillable(['code', 'name', 'sort_order'])]
#[Hidden(['id', 'parent_id'])]
class AutoOption extends CatalogModel
{
    /** @use HasFactory<AutoOptionFactory> */
    use HasFactory, TwoLevelDictionary;

    protected const PARENT_KEY = 'parent_id';

    protected $table = 'auto_options';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoOption, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutoOption::class, 'parent_id');
    }

    /**
     * @return HasMany<AutoOption, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(AutoOption::class, 'parent_id');
    }
}
