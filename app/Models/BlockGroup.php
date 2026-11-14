<?php

namespace App\Models;

use App\Enums\BlockCategory;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\BlockGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Platform-managed group of Block Definitions (D-130): «Шапка», «Подвал», «Контакты»… Every Block,
 * official or developer, belongs to one group; `kind` keeps the visual category of its Blocks.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property BlockCategory $kind
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'kind', 'sort_order'])]
#[Hidden(['id'])]
class BlockGroup extends Model
{
    /** @use HasFactory<BlockGroupFactory> */
    use HasFactory, HasImmutablePublicId;

    public const NAME_MAX = 80;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BlockCategory::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['kind' => 'other', 'sort_order' => 0];

    /**
     * @return HasMany<BlockDefinition, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(BlockDefinition::class);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_values(self::query()->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (BlockGroup $group): array => ['value' => $group->public_id, 'label' => $group->name])
            ->all());
    }
}
