<?php

namespace App\Models;

use App\Blocks\BlockSchemaValidator;
use Database\Factories\BlockVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable: a change to a Block requires a new version (BLOCK_SYSTEM.md §6).
 *
 * @property int $id
 * @property int $block_definition_id
 * @property string $version
 * @property array<string, mixed> $schema_json
 * @property Carbon|null $created_at
 */
#[Fillable(['version', 'schema_json'])]
#[Hidden(['id', 'block_definition_id'])]
class BlockVersion extends Model
{
    /** @use HasFactory<BlockVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BlockVersion $version): void {
            app(BlockSchemaValidator::class)->assertValid($version->schema_json);
        });

        static::updating(function (): void {
            throw new LogicException('Block versions are immutable; publish a new version instead.');
        });
    }

    /**
     * @return BelongsTo<BlockDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(BlockDefinition::class, 'block_definition_id');
    }
}
