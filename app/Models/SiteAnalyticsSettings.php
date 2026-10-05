<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Draft Yandex Metrica settings of a Site (D-044, DATABASE.md §63). The counter ID is public by
 * design (SECURITY.md §86). Changes reach visitors only through Publish: the safe subset is
 * copied into the Published manifest.
 *
 * @property int $id
 * @property int $site_id
 * @property bool $yandex_metrica_enabled
 * @property string|null $yandex_metrica_counter_id
 * @property bool $clickmap
 * @property bool $track_links
 * @property bool $accurate_track_bounce
 * @property bool $webvisor_enabled
 * @property array<string, mixed>|null $event_tracking_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['yandex_metrica_enabled', 'yandex_metrica_counter_id', 'clickmap', 'track_links', 'accurate_track_bounce', 'webvisor_enabled'])]
#[Hidden(['id', 'site_id'])]
class SiteAnalyticsSettings extends Model
{
    /** At most 15 digits: the counter is printed as a JavaScript number literal. */
    public const COUNTER_PATTERN = '/^[1-9][0-9]{3,14}$/';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'yandex_metrica_enabled' => false,
        'clickmap' => true,
        'track_links' => true,
        'accurate_track_bounce' => true,
        'webvisor_enabled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'yandex_metrica_enabled' => 'boolean',
            'clickmap' => 'boolean',
            'track_links' => 'boolean',
            'accurate_track_bounce' => 'boolean',
            'webvisor_enabled' => 'boolean',
            'event_tracking_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Published subset: null unless Metrica is enabled with a valid counter.
     *
     * @return array{counter_id: string, clickmap: bool, track_links: bool, accurate_track_bounce: bool, webvisor: bool}|null
     */
    public function publishedMetrica(): ?array
    {
        $counter = $this->yandex_metrica_counter_id;

        if (! $this->yandex_metrica_enabled || $counter === null || preg_match(self::COUNTER_PATTERN, $counter) !== 1) {
            return null;
        }

        return [
            'counter_id' => $counter,
            'clickmap' => $this->clickmap,
            'track_links' => $this->track_links,
            'accurate_track_bounce' => $this->accurate_track_bounce,
            'webvisor' => $this->webvisor_enabled,
        ];
    }
}
