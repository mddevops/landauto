<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoGenerationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Поколение.
 *
 * @property int $model_id
 * @property int|null $external_id
 * @property string $name
 * @property int|null $year_from
 * @property int|null $year_to
 * @property-read AutoModel $model
 */
class AutoGeneration extends CatalogModel
{
    /** @use HasFactory<AutoGenerationFactory> */
    use HasFactory;

    protected $table = 'auto_generations';

    protected $hidden = ['id', 'model_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['external_id' => 'integer', 'year_from' => 'integer', 'year_to' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoModel, $this>
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AutoModel::class, 'model_id');
    }

    /**
     * @return HasMany<AutoSeries, $this>
     */
    public function series(): HasMany
    {
        return $this->hasMany(AutoSeries::class, 'generation_id');
    }

    /**
     * «2020–2024», «с 2021» or null.
     */
    public function years(): ?string
    {
        return match (true) {
            $this->year_from !== null && $this->year_to !== null => $this->year_from === $this->year_to ? (string) $this->year_from : "{$this->year_from}–{$this->year_to}",
            $this->year_from !== null => "с {$this->year_from}",
            default => null,
        };
    }
}
