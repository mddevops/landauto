<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\BlockDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property bool $is_official
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
#[Hidden(['id'])]
class BlockDefinition extends Model
{
    /** @use HasFactory<BlockDefinitionFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
        ];
    }

    /**
     * @return HasMany<BlockVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BlockVersion::class);
    }
}
