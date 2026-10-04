<?php

namespace App\Blocks;

/**
 * Builds the initial draft state of a new Block Instance from the defaults declared in its schema.
 */
final class BlockStateDefaults
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function fromSchema(array $schema): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'] ?? [];

        return $this->fields($fields);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function fields(array $fields): array
    {
        $state = [];

        foreach ($fields as $field) {
            $key = (string) $field['key'];

            if ($field['type'] === 'group') {
                /** @var list<array<string, mixed>> $children */
                $children = $field['fields'];
                $state[$key] = $this->fields($children);
            } elseif ($field['type'] === 'repeater') {
                $state[$key] = [];
            } elseif (array_key_exists('default', $field)) {
                $state[$key] = $field['default'];
            }
        }

        return $state;
    }
}
