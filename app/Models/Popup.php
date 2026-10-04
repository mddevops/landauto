<?php

namespace App\Models;

use App\Enums\PopupAnimation;
use App\Enums\PopupSize;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\PopupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site-owned reusable modal presentation (D-035). A Popup never owns fields, routing,
 * delivery or credentials; lead collection belongs to a Form.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property string $name
 * @property bool $status
 * @property string|null $title
 * @property string|null $text
 * @property PopupSize $size
 * @property PopupAnimation $animation
 * @property bool $close_on_overlay
 * @property bool $close_on_escape
 * @property bool $show_close_button
 * @property bool $mobile_fullscreen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'status', 'title', 'text', 'size', 'animation', 'close_on_overlay', 'close_on_escape', 'show_close_button', 'mobile_fullscreen'])]
#[Hidden(['id', 'site_id'])]
class Popup extends Model
{
    /** @use HasFactory<PopupFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => true,
        'size' => 'medium',
        'animation' => 'fade',
        'close_on_overlay' => true,
        'close_on_escape' => true,
        'show_close_button' => true,
        'mobile_fullscreen' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'size' => PopupSize::class,
            'animation' => PopupAnimation::class,
            'close_on_overlay' => 'boolean',
            'close_on_escape' => 'boolean',
            'show_close_button' => 'boolean',
            'mobile_fullscreen' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Popup $popup): void {
            if ($popup->isDirty('site_id')) {
                throw new LogicException('Popup Site is immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }
}
