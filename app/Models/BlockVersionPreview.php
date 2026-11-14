<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Preview screenshot of one Block Version (D-130), uploaded by the Block owner. Block Versions stay
 * immutable; a replaced preview is a new row with a new `public_id`, so its URL can be cached forever.
 *
 * @property int $id
 * @property string $public_id
 * @property int $block_version_id
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'block_version_id', 'path', 'created_by_user_id'])]
class BlockVersionPreview extends Model
{
    use HasImmutablePublicId;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BlockVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BlockVersion::class, 'block_version_id');
    }
}
