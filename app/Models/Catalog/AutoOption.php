<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Option section (root) or option (child), ordered by `sort`.
 *
 * @property string $name
 * @property int|null $parent_id
 * @property int $sort
 * @property-read AutoOption|null $parent
 */
class AutoOption extends CatalogModel
{
    /** @use HasFactory<AutoOptionFactory> */
    use HasFactory;

    protected $table = 'auto_options';

    protected $hidden = ['id', 'parent_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoOption, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutoOption::class, 'parent_id');
    }
}
