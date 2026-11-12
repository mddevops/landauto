<?php

namespace App\Catalog;

/**
 * Normalised technical facts of one auto.ru Modification. Older catalog rows keep only the drive
 * (in `transmission`) and city/highway consumption (in `drive`); their engine, power and gearbox
 * come from the characteristic values instead.
 */
final readonly class ModificationFacts
{
    public function __construct(
        public string $name,
        public ?string $volume,
        public ?int $power,
        public ?string $engine,
        public ?string $transmission,
        public ?string $drive,
        public ?string $consumption,
        public ?string $acceleration,
    ) {}

    /**
     * «1.6 AT (123 л.с.)» from the catalog, or «1,6 л · 123 л.с. · Автомат · Передний».
     */
    public function label(): string
    {
        if (preg_match('/\d/', $this->name) === 1) {
            return $this->name;
        }

        $summary = $this->summary(withEngine: false);

        return $summary !== '' ? $summary : $this->name;
    }

    /**
     * «1,6 л · 123 л.с. · Бензин · Автомат · Передний».
     */
    public function summary(bool $withEngine = true): string
    {
        return implode(' · ', array_filter([
            $this->volume === null ? null : self::decimal($this->volume).' л',
            $this->power === null ? null : $this->power.' л.с.',
            $withEngine && $this->engine !== null ? mb_convert_case($this->engine, MB_CASE_TITLE) : null,
            $this->transmission,
            $this->drive,
        ]));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function specs(): array
    {
        $specs = [
            'Объём двигателя' => $this->volume === null ? null : self::decimal($this->volume).' л',
            'Мощность' => $this->power === null ? null : $this->power.' л.с.',
            'Двигатель' => $this->engine === null ? null : mb_convert_case($this->engine, MB_CASE_TITLE),
            'Коробка передач' => $this->transmission,
            'Привод' => $this->drive,
            'Расход' => $this->consumption === null ? null : self::decimal($this->consumption).' л/100 км',
            'Разгон 0–100 км/ч' => $this->acceleration === null ? null : self::decimal($this->acceleration).' с',
        ];
        $filled = [];

        foreach ($specs as $label => $value) {
            if ($value !== null && $value !== '') {
                $filled[] = ['label' => $label, 'value' => $value];
            }
        }

        return $filled;
    }

    /**
     * Decimal without trailing zeros and with a Russian comma ("6.60" → "6,6").
     */
    public static function decimal(string $value): string
    {
        return str_replace('.', ',', str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value);
    }
}
