<?php

namespace App\Blocks;

use App\Enums\BlockActionType;
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

    private const URL_MAX_LENGTH = 2048;

    private const EMAIL_MAX_LENGTH = 254;

    private const PHONE_PATTERN = '/^\+?[0-9 ()\-]{3,32}$/';

    /** @var array{assets: array<string, string>, pages: array<string, string>, blocks: array<string, string>, vehicles: array<string, string>, popups: array<string, string>} State path => referenced public ID. */
    private array $references = ['assets' => [], 'pages' => [], 'blocks' => [], 'vehicles' => [], 'popups' => []];

    /**
     * @param  array<string, mixed>  $schema
     * @param  BlockReferenceResolver|null  $resolver  Without a resolver every asset/page/block reference is rejected.
     * @return array<string, string> State path => Russian message; empty when valid.
     */
    public function errors(array $schema, mixed $state, ?BlockReferenceResolver $resolver = null): array
    {
        $this->errors = [];
        $this->references = ['assets' => [], 'pages' => [], 'blocks' => [], 'vehicles' => [], 'popups' => []];
        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'] ?? [];

        $this->validateObject($fields, $state, 'state', false);

        $this->checkReferences($this->references['assets'], fn (array $ids): array => $resolver?->existingAssets($ids) ?? [], 'Изображение не найдено в библиотеке этого сайта.');
        $this->checkReferences($this->references['pages'], fn (array $ids): array => $resolver?->existingPages($ids) ?? [], 'Страница не найдена на этом сайте.');
        $this->checkReferences($this->references['blocks'], fn (array $ids): array => $resolver?->existingBlocks($ids) ?? [], 'Блок не найден на этой странице.');
        $this->checkReferences($this->references['vehicles'], fn (array $ids): array => $resolver?->existingVehicles($ids) ?? [], 'Автомобиль не найден на этом сайте.');
        $this->checkReferences($this->references['popups'], fn (array $ids): array => $resolver?->existingPopups($ids) ?? [], 'Попап не найден или выключен на этом сайте.');

        return $this->errors;
    }

    /**
     * Public IDs the state references, by kind and state path, without checking that they exist.
     *
     * @param  array<string, mixed>  $schema
     * @return array{assets: array<string, string>, pages: array<string, string>, blocks: array<string, string>, vehicles: array<string, string>, popups: array<string, string>}
     */
    public function references(array $schema, mixed $state): array
    {
        $this->errors = [];
        $this->references = ['assets' => [], 'pages' => [], 'blocks' => [], 'vehicles' => [], 'popups' => []];
        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'] ?? [];

        $this->validateObject($fields, $state, 'state', false);

        return $this->references;
    }

    /**
     * Publish-time completeness: required values are filled and Repeaters reach `min_items`.
     * Fields hidden by `visible_if` are skipped, matching what the renderer shows.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string> State path => Russian message; empty when complete.
     */
    public function missing(array $schema, mixed $state): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'] ?? [];
        $missing = [];
        $this->collectMissing($fields, is_array($state) ? $state : [], 'state', $missing);

        return $missing;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<mixed>  $state
     * @param  array<string, string>  $missing
     */
    private function collectMissing(array $fields, array $state, string $path, array &$missing): void
    {
        $defaults = [];

        foreach ($fields as $field) {
            $defaults[(string) $field['key']] = $field['default'] ?? null;
        }

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $condition = $field['visible_if'] ?? null;

            if (is_array($condition) && ($state[$condition['field']] ?? $defaults[$condition['field']] ?? null) !== $condition['equals']) {
                continue;
            }

            $value = $state[$key] ?? null;
            $fieldPath = "{$path}.{$key}";
            $type = BlockFieldType::from((string) $field['type']);

            if ($type === BlockFieldType::Group) {
                $this->collectMissing($this->nestedFields($field), is_array($value) ? $value : [], $fieldPath, $missing);
            } elseif ($type === BlockFieldType::Repeater) {
                $items = is_array($value) && array_is_list($value) ? $value : [];
                $minItems = (int) ($field['min_items'] ?? 0);

                if (count($items) < $minItems) {
                    $missing[$fieldPath] = "Добавьте не меньше {$minItems} элементов.";
                }

                foreach ($items as $index => $item) {
                    $this->collectMissing($this->nestedFields($field), is_array($item) ? $item : [], "{$fieldPath}.{$index}", $missing);
                }
            } elseif (($field['required'] ?? false) === true && $this->isEmptyValue($type, $value)) {
                $missing[$fieldPath] = 'Заполните обязательное поле.';
            }
        }
    }

    private function isEmptyValue(BlockFieldType $type, mixed $value): bool
    {
        if ($type === BlockFieldType::Action) {
            $target = is_array($value) && is_string($value['type'] ?? null)
                ? ($value[BlockActionType::tryFrom($value['type'])?->targetKey() ?? ''] ?? null)
                : null;

            return ! is_string($target) || trim($target) === '';
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws InvalidBlockStateException
     */
    public function assertValid(array $schema, mixed $state, ?BlockReferenceResolver $resolver = null): void
    {
        $errors = $this->errors($schema, $state, $resolver);

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
            BlockFieldType::Number => $this->validateNumber($field, $value, $path),
            BlockFieldType::Boolean => $this->validateBoolean($value, $path),
            BlockFieldType::Select => $this->validateSelect($field, $value, $path),
            BlockFieldType::Image => $this->validateImage($value, $path),
            BlockFieldType::Action => $this->validateAction($value, $path),
            BlockFieldType::Vehicle => $this->reference('vehicles', is_string($value) ? $value : '', $path, 'Выберите автомобиль этого сайта.'),
            BlockFieldType::Group => $this->validateObject($this->nestedFields($field), $value, $path, false),
            BlockFieldType::Repeater => $this->validateRepeater($field, $value, $path),
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateNumber(array $field, mixed $value, string $path): void
    {
        if (! BlockSchemaValidator::isNumber($value)) {
            $this->errors[$path] = 'Значение должно быть числом.';

            return;
        }

        $min = $field['min'] ?? null;
        $max = $field['max'] ?? null;

        if (BlockSchemaValidator::isNumber($min) && $value < $min) {
            $this->errors[$path] = "Значение должно быть не меньше {$min}.";
        } elseif (BlockSchemaValidator::isNumber($max) && $value > $max) {
            $this->errors[$path] = "Значение должно быть не больше {$max}.";
        }
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

        $this->references['assets'][$path] = $value;
    }

    /**
     * An action is `{type, <target>}`; a missing/null target is allowed while drafting.
     */
    private function validateAction(mixed $value, string $path): void
    {
        if (! is_array($value) || array_is_list($value)) {
            $this->errors[$path] = 'Действие должно быть объектом.';

            return;
        }

        $type = is_string($value['type'] ?? null) ? BlockActionType::tryFrom($value['type']) : null;

        if ($type === null) {
            $this->errors["{$path}.type"] = 'Выберите поддерживаемое действие.';

            return;
        }

        $targetKey = $type->targetKey();

        foreach (array_keys($value) as $key) {
            if ($key !== 'type' && $key !== $targetKey) {
                $this->errors["{$path}.{$key}"] = 'Поле не относится к выбранному действию.';
            }
        }

        $target = $value[$targetKey] ?? null;
        $targetPath = "{$path}.{$targetKey}";

        if ($target === null) {
            return;
        }

        if (! is_string($target)) {
            $this->errors[$targetPath] = 'Значение должно быть строкой.';

            return;
        }

        match ($type) {
            BlockActionType::OpenUrl => $this->isSafeUrl($target)
                ? null
                : $this->errors[$targetPath] = 'Укажите адрес, начинающийся с http:// или https://.',
            BlockActionType::OpenPage => $this->reference('pages', $target, $targetPath, 'Выберите страницу сайта.'),
            BlockActionType::ScrollTo => $this->reference('blocks', $target, $targetPath, 'Выберите блок на этой странице.'),
            BlockActionType::Phone => preg_match(self::PHONE_PATTERN, $target) === 1 && preg_match_all('/\d/', $target) >= 3
                ? null
                : $this->errors[$targetPath] = 'Укажите телефон: цифры, пробелы, скобки, дефисы и «+» в начале.',
            BlockActionType::Email => mb_strlen($target) <= self::EMAIL_MAX_LENGTH && filter_var($target, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : $this->errors[$targetPath] = 'Укажите корректный email.',
            BlockActionType::OpenPopup => $this->reference('popups', $target, $targetPath, 'Выберите попап этого сайта.'),
        };
    }

    private function isSafeUrl(string $url): bool
    {
        if (mb_strlen($url) > self::URL_MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    /**
     * @param  'pages'|'blocks'|'vehicles'|'popups'  $kind
     */
    private function reference(string $kind, string $id, string $path, string $message): void
    {
        if (! Str::isUlid($id)) {
            $this->errors[$path] = $message;

            return;
        }

        $this->references[$kind][$path] = $id;
    }

    /**
     * @param  array<string, string>  $references
     * @param  Closure(list<string>): array<mixed>  $existing
     */
    private function checkReferences(array $references, Closure $existing, string $message): void
    {
        if ($references === []) {
            return;
        }

        $found = $existing(array_values(array_unique($references)));

        foreach ($references as $path => $id) {
            if (! in_array($id, $found, true)) {
                $this->errors[$path] = $message;
            }
        }
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
