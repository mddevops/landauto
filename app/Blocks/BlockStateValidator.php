<?php

namespace App\Blocks;

use App\Enums\BlockFieldType;
use App\Exceptions\InvalidBlockStateException;
use Closure;
use Illuminate\Support\Str;

/**
 * Validates Block Instance draft state against a valid Block Schema (BLOCK_SYSTEM.md §61–§63).
 *
 * Draft state may be incomplete while the customer edits: missing keys and null scalar values
 * are allowed, so `required` and `min_items` are publish-time checks. Everything else is strict:
 * unknown keys, wrong types, length/option/item-count limits and Repeater item identity.
 */
final class BlockStateValidator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, string> State path => referenced Site Asset public ID. */
    private array $assetReferences = [];

    /**
     * @param  array<string, mixed>  $schema
     * @param  (Closure(list<string>): array<mixed>)|null  $existingAssets  Returns which of the given asset IDs
     *                                                                      belong to the Block's Site; without it every image reference is rejected.
     * @return array<string, string> State path => Russian message; empty when valid.
     */
    public function errors(array $schema, mixed $state, ?Closure $existingAssets = null): array
    {
        $this->errors = [];
        $this->assetReferences = [];
        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'] ?? [];

        $this->validateObject($fields, $state, 'state', false);

        if ($this->assetReferences !== []) {
            $existing = $existingAssets === null
                ? []
                : $existingAssets(array_values(array_unique($this->assetReferences)));

            foreach ($this->assetReferences as $path => $id) {
                if (! in_array($id, $existing, true)) {
                    $this->errors[$path] = 'Изображение не найдено в библиотеке этого сайта.';
                }
            }
        }

        return $this->errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  (Closure(list<string>): array<mixed>)|null  $existingAssets
     *
     * @throws InvalidBlockStateException
     */
    public function assertValid(array $schema, mixed $state, ?Closure $existingAssets = null): void
    {
        $errors = $this->errors($schema, $state, $existingAssets);

        if ($errors !== []) {
            throw new InvalidBlockStateException($errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function validateObject(array $fields, mixed $value, string $path, bool $isRepeaterItem): void
    {
        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            $this->errors[$path] = 'Значение должно быть объектом.';

            return;
        }

        $byKey = [];

        foreach ($fields as $field) {
            $byKey[(string) $field['key']] = $field;
        }

        foreach ($value as $key => $fieldValue) {
            $fieldPath = "{$path}.{$key}";

            if ($isRepeaterItem && $key === BlockSchemaValidator::REPEATER_ITEM_ID_KEY) {
                continue;
            }

            if (! array_key_exists($key, $byKey)) {
                $this->errors[$fieldPath] = 'Поле отсутствует в схеме блока.';

                continue;
            }

            $this->validateValue($byKey[$key], $fieldValue, $fieldPath);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateValue(array $field, mixed $value, string $path): void
    {
        $type = BlockFieldType::from((string) $field['type']);

        if ($value === null && ! $type->isContainer()) {
            return;
        }

        match ($type) {
            BlockFieldType::Text => $this->validateString($value, $path, (int) ($field['max_length'] ?? BlockSchemaValidator::TEXT_MAX_LENGTH)),
            BlockFieldType::Textarea => $this->validateString($value, $path, (int) ($field['max_length'] ?? BlockSchemaValidator::TEXTAREA_MAX_LENGTH)),
            BlockFieldType::Boolean => $this->validateBoolean($value, $path),
            BlockFieldType::Select => $this->validateSelect($field, $value, $path),
            BlockFieldType::Image => $this->validateImage($value, $path),
            BlockFieldType::Group => $this->validateObject($this->nestedFields($field), $value, $path, false),
            BlockFieldType::Repeater => $this->validateRepeater($field, $value, $path),
        };
    }

    private function validateBoolean(mixed $value, string $path): void
    {
        if (! is_bool($value)) {
            $this->errors[$path] = 'Значение должно быть логическим.';
        }
    }

    /**
     * An image value is the public ID of a Site Asset (ADR-003); ownership is checked after traversal.
     */
    private function validateImage(mixed $value, string $path): void
    {
        if (! is_string($value) || ! Str::isUlid($value)) {
            $this->errors[$path] = 'Выберите изображение из библиотеки сайта.';

            return;
        }

        $this->assetReferences[$path] = $value;
    }

    private function validateString(mixed $value, string $path, int $maxLength): void
    {
        if (! is_string($value)) {
            $this->errors[$path] = 'Значение должно быть строкой.';
        } elseif (mb_strlen($value) > $maxLength) {
            $this->errors[$path] = "Значение не должно превышать {$maxLength} символов.";
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateSelect(array $field, mixed $value, string $path): void
    {
        /** @var list<array{value: string, label: string}> $options */
        $options = $field['options'] ?? [];

        if (! in_array($value, array_column($options, 'value'), true)) {
            $this->errors[$path] = 'Выбран недопустимый вариант.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateRepeater(array $field, mixed $value, string $path): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->errors[$path] = 'Элементы повторителя должны быть списком.';

            return;
        }

        $maxItems = (int) $field['max_items'];

        if (count($value) > $maxItems) {
            $this->errors[$path] = "Допускается не более {$maxItems} элементов.";
        }

        $ids = [];
        $idKey = BlockSchemaValidator::REPEATER_ITEM_ID_KEY;

        foreach ($value as $index => $item) {
            $itemPath = "{$path}.{$index}";
            $id = is_array($item) ? ($item[$idKey] ?? null) : null;

            if (! is_string($id) || ! Str::isUlid($id)) {
                $this->errors["{$itemPath}.{$idKey}"] = 'Элемент повторителя должен иметь идентификатор ULID.';
            } elseif (in_array($id, $ids, true)) {
                $this->errors["{$itemPath}.{$idKey}"] = 'Идентификаторы элементов повторителя должны быть уникальными.';
            } else {
                $ids[] = $id;
            }

            $this->validateObject($this->nestedFields($field), $item, $itemPath, true);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>
     */
    private function nestedFields(array $field): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $field['fields'] ?? [];

        return $fields;
    }
}
