<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A previous prefix of a Site section; the public runtime redirects it to the current prefix (ADR-011 §2).
 *
 * @property int $id
 * @property int $site_id
 * @property int $site_section_id
 * @property string $prefix
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['prefix'])]
class SiteSectionRedirect extends Model
{
    /**
     * @return BelongsTo<SiteSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }
}
