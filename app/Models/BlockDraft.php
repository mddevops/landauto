<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single editable Draft of a Block Definition's sources (ADR-008). Saving it never creates or
 * changes a Block Version; `revision` guards against overwriting a newer save.
 *
 * @property int $id
 * @property int $block_definition_id
 * @property string $html
 * @property string $css
 * @property string $js
 * @property string $schema_source
 * @property array<string, mixed>|null $preview_data
 * @property int $revision
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['html', 'css', 'js', 'schema_source'])]
#[Hidden(['id', 'block_definition_id', 'updated_by_user_id'])]
class BlockDraft extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['preview_data' => 'array', 'revision' => 'integer'];
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
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
