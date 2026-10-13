<?php

namespace App\Blocks;

use App\Models\Site;
use Illuminate\Validation\ValidationException;

final class SiteTypeBlockAccess
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function schema(Site $site, array $schema): array
    {
        if (! $site->site_type->hasLockedStructure()) {
            return $schema;
        }

        return ['fields' => array_values(array_filter(
            is_array($schema['fields'] ?? null) ? $schema['fields'] : [],
            fn (mixed $field): bool => is_array($field) && ($field['exposed'] ?? false) === true,
        ))];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function visibleState(Site $site, array $schema, array $state): array
    {
        if (! $site->site_type->hasLockedStructure()) {
            return $state;
        }

        return array_intersect_key($state, array_flip($this->keys($schema)));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function merge(Site $site, array $schema, array $current, array $submitted): array
    {
        if (! $site->site_type->hasLockedStructure()) {
            return $submitted;
        }

        $allowed = array_flip($this->keys($schema));
        $unknown = array_diff_key($submitted, $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'state' => 'Для этого типа сайта можно менять только открытые шаблоном изображения, цвета и шрифты.',
            ]);
        }

        return array_replace($current, array_intersect_key($submitted, $allowed));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function keys(array $schema): array
    {
        return array_values(array_map(
            fn (array $field): string => (string) $field['key'],
            array_filter(is_array($schema['fields'] ?? null) ? $schema['fields'] : [], fn (mixed $field): bool => is_array($field) && ($field['exposed'] ?? false) === true),
        ));
    }
}
