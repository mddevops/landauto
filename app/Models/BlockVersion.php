<?php

namespace App\Models;

use App\Blocks\BlockSchemaValidator;
use App\Enums\BlockRuntime;
use Database\Factories\BlockVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable: a change to a Block requires a new version (BLOCK_SYSTEM.md §6). A `sandboxed`
 * version carries its authored source snapshot (ADR-008); an `official` one carries none.
 *
 * @property int $id
 * @property int $block_definition_id
 * @property string $version
 * @property BlockRuntime $runtime
 * @property array<string, mixed> $schema_json
 * @property string|null $html
 * @property string|null $css
 * @property string|null $js
 * @property int|null $published_by_user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['version', 'schema_json'])]
#[Hidden(['id', 'block_definition_id', 'published_by_user_id'])]
class BlockVersion extends Model
{
    /** @use HasFactory<BlockVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['runtime' => 'official'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'runtime' => BlockRuntime::class,
            'schema_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BlockVersion $version): void {
            app(BlockSchemaValidator::class)->assertValid($version->schema_json);

            $hasSources = $version->html !== null && $version->css !== null && $version->js !== null;
            $noSources = $version->html === null && $version->css === null && $version->js === null;

            if ($version->runtime === BlockRuntime::Sandboxed ? ! $hasSources : ! $noSources) {
                throw new LogicException('Only sandboxed Block Versions carry authored sources, and they carry all of them.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Block versions are immutable; publish a new version instead.');
        });
    }

    /**
     * Versions the trusted application registry can render.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfficialRuntime(Builder $query): void
    {
        $query->where('runtime', BlockRuntime::Official->value);
    }

    /**
     * @return BelongsTo<BlockDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(BlockDefinition::class, 'block_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
