<?php

namespace App\Blocks;

use App\Enums\BlockFieldType;
use App\Exceptions\InvalidBlockSchemaException;

/**
 * Validates the deterministic Block Schema stored in a Block Version (BLOCK_SYSTEM.md §8–§28).
 *
 * Schema shape: {"fields": [Field, ...]}; every Field has a snake_case `key`, a supported `type`
 * and a `label`, plus only the options its type allows.
 */
final class BlockSchemaValidator
{
    public const MAX_CONTAINER_DEPTH = 3;

    public const MAX_REPEATER_NESTING = 2;

    public const MAX_REPEATER_ITEMS = 50;

    public const TEXT_MAX_LENGTH = 255;

    public const TEXTAREA_MAX_LENGTH = 5000;

    /** Reserved for the stable Repeater item identifier stored in Block Instance state. */
    public const REPEATER_ITEM_ID_KEY = 'id';

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @return array<string, string> Schema path => Russian message; empty when valid.
     */
    public function errors(mixed $schema): array
    {
        $this->errors = [];

        if (! is_array($schema) || (array_is_list($schema) && $schema !== [])) {
            return ['schema' => 'Схема должна быть объектом.'];
        }

        $this->rejectUnknownKeys($schema, ['fields'], 'schema');

        if (! array_key_exists('fields', $schema)) {
            $this->errors['fields'] = 'Укажите список полей.';
        } else {
            $this->validateFields($schema['fields'], 'fields', 0, 0, false);
        }

        return $this->errors;
    }

    /**
     * @throws InvalidBlockSchemaException
     */
    public function assertValid(mixed $schema): void
    {
        $errors = $this->errors($schema);

        if ($errors !== []) {
            throw new InvalidBlockSchemaException($errors);
        }
    }

    private function validateFields(
        mixed $fields,
        string $path,
        int $containerDepth,
        int $repeaterNesting,
        bool $insideRepeater,
    ): void {
        if (! is_array($fields) || ! array_is_list($fields)) {
            $this->errors[$path] = 'Поля должны быть списком.';

            return;
        }

        $keys = [];
        /** @var array<string, array<string, mixed>> $controllers */
        $controllers = [];

        foreach ($fields as $index => $field) {
            $fieldPath = "{$path}.{$index}";

            if (! is_array($field) || array_is_list($field)) {
                $this->errors[$fieldPath] = 'Поле должно быть объектом.';

                continue;
            }

            $key = $field['key'] ?? null;

            if (! is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1) {
                $this->errors["{$fieldPath}.key"] = 'Ключ должен начинаться с латинской буквы и содержать только a–z, 0–9 и _.';
            } elseif (in_array($key, $keys, true)) {
                $this->errors["{$fieldPath}.key"] = 'Ключ поля должен быть уникальным на своём уровне.';
            } elseif ($insideRepeater && $key === self::REPEATER_ITEM_ID_KEY) {
                $this->errors["{$fieldPath}.key"] = 'Ключ id зарезервирован для идентификатора элемента повторителя.';
            } else {
                $keys[] = $key;
            }

            $type = is_string($field['type'] ?? null) ? BlockFieldType::tryFrom($field['type']) : null;

            if ($type === null) {
                $this->errors["{$fieldPath}.type"] = 'Неподдерживаемый тип поля.';

                continue;
            }

            $this->rejectUnknownKeys($field, $type->allowedKeys(), $fieldPath);
            $this->validateLabel($field, $fieldPath);

            if (array_key_exists('help', $field) && ! $this->isNonEmptyString($field['help'])) {
                $this->errors["{$fieldPath}.help"] = 'Подсказка должна быть непустой строкой.';
            }

            if (array_key_exists('required', $field) && ! is_bool($field['required'])) {
                $this->errors["{$fieldPath}.required"] = 'Признак обязательности должен быть логическим значением.';
            }

            match ($type) {
                BlockFieldType::Text => $this->validateText($field, $fieldPath, self::TEXT_MAX_LENGTH),
                BlockFieldType::Textarea => $this->validateText($field, $fieldPath, self::TEXTAREA_MAX_LENGTH),
                BlockFieldType::Boolean => $this->validateBoolean($field, $fieldPath),
                BlockFieldType::Select => $this->validateSelect($field, $fieldPath),
                BlockFieldType::Image, BlockFieldType::Action => null,
                BlockFieldType::Group, BlockFieldType::Repeater => $this->validateContainer(
                    $field,
                    $type,
                    $fieldPath,
                    $containerDepth,
                    $repeaterNesting,
                ),
            };

            if (array_key_exists('visible_if', $field)) {
                $this->validateCondition($field['visible_if'], $controllers, $fieldPath);
            }

            if (is_string($key) && in_array($type, [BlockFieldType::Boolean, BlockFieldType::Select], true)) {
                $controllers[$key] = $field;
            }
        }
    }

    /**
     * A field may depend only on an earlier boolean/select sibling: {"field": key, "equals": value}.
     *
     * @param  array<string, array<string, mixed>>  $controllers
     */
    private function validateCondition(mixed $condition, array $controllers, string $path): void
    {
        $path .= '.visible_if';

        if (! is_array($condition) || array_is_list($condition)) {
            $this->errors[$path] = 'Условие показа должно быть объектом.';

            return;
        }

        $this->rejectUnknownKeys($condition, ['field', 'equals'], $path);
        $controller = is_string($condition['field'] ?? null) ? ($controllers[$condition['field']] ?? null) : null;

        if ($controller === null) {
            $this->errors["{$path}.field"] = 'Условие должно ссылаться на предыдущее поле-переключатель или список того же уровня.';

            return;
        }

        $equals = $condition['equals'] ?? null;
        $valid = $controller['type'] === BlockFieldType::Boolean->value
            ? is_bool($equals)
            : in_array($equals, array_column(is_array($controller['options'] ?? null) ? $controller['options'] : [], 'value'), true);

        if (! $valid) {
            $this->errors["{$path}.equals"] = 'Значение условия не подходит к типу поля.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateContainer(
        array $field,
        BlockFieldType $type,
        string $path,
        int $containerDepth,
        int $repeaterNesting,
    ): void {
        $containerDepth++;
        $isRepeater = $type === BlockFieldType::Repeater;
        $repeaterNesting += $isRepeater ? 1 : 0;

        if ($containerDepth > self::MAX_CONTAINER_DEPTH) {
            $this->errors[$path] = 'Превышена допустимая глубина вложенности полей.';

            return;
        }

        if ($repeaterNesting > self::MAX_REPEATER_NESTING) {
            $this->errors[$path] = 'Превышена допустимая вложенность повторителей.';

            return;
        }

        if ($isRepeater) {
            $this->validateRepeaterLimits($field, $path);
        }

        if (! is_array($field['fields'] ?? null) || $field['fields'] === []) {
            $this->errors["{$path}.fields"] = 'Укажите хотя бы одно вложенное поле.';

            return;
        }

        $this->validateFields($field['fields'], "{$path}.fields", $containerDepth, $repeaterNesting, $isRepeater);
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateRepeaterLimits(array $field, string $path): void
    {
        $min = $field['min_items'] ?? 0;
        $max = $field['max_items'] ?? null;

        if (! is_int($min) || $min < 0) {
            $this->errors["{$path}.min_items"] = 'Минимальное количество элементов должно быть целым числом не меньше 0.';
        }

        if (! is_int($max) || $max < 1 || $max > self::MAX_REPEATER_ITEMS) {
            $this->errors["{$path}.max_items"] = 'Укажите максимальное количество элементов от 1 до '.self::MAX_REPEATER_ITEMS.'.';
        } elseif (is_int($min) && $min > $max) {
            $this->errors["{$path}.min_items"] = 'Минимальное количество элементов не может превышать максимальное.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateText(array $field, string $path, int $limit): void
    {
        $maxLength = $field['max_length'] ?? $limit;

        if (! is_int($maxLength) || $maxLength < 1 || $maxLength > $limit) {
            $this->errors["{$path}.max_length"] = "Максимальная длина должна быть от 1 до {$limit}.";
            $maxLength = $limit;
        }

        if (! array_key_exists('default', $field)) {
            return;
        }

        if (! is_string($field['default'])) {
            $this->errors["{$path}.default"] = 'Значение по умолчанию должно быть строкой.';
        } elseif (mb_strlen($field['default']) > $maxLength) {
            $this->errors["{$path}.default"] = 'Значение по умолчанию превышает максимальную длину.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateBoolean(array $field, string $path): void
    {
        if (array_key_exists('default', $field) && ! is_bool($field['default'])) {
            $this->errors["{$path}.default"] = 'Значение по умолчанию должно быть логическим.';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateSelect(array $field, string $path): void
    {
        $options = $field['options'] ?? null;

        if (! is_array($options) || ! array_is_list($options) || $options === []) {
            $this->errors["{$path}.options"] = 'Укажите хотя бы один вариант выбора.';

            return;
        }

        $values = [];

        foreach ($options as $index => $option) {
            $optionPath = "{$path}.options.{$index}";

            if (! is_array($option) || array_is_list($option)) {
                $this->errors[$optionPath] = 'Вариант должен быть объектом.';

                continue;
            }

            $this->rejectUnknownKeys($option, ['value', 'label'], $optionPath);
            $this->validateLabel($option, $optionPath);
            $value = $option['value'] ?? null;

            if (! is_string($value) || preg_match(self::KEY_PATTERN, $value) !== 1) {
                $this->errors["{$optionPath}.value"] = 'Значение варианта должно содержать только a–z, 0–9 и _.';
            } elseif (in_array($value, $values, true)) {
                $this->errors["{$optionPath}.value"] = 'Значения вариантов должны быть уникальными.';
            } else {
                $values[] = $value;
            }
        }

        if (array_key_exists('default', $field) && ! in_array($field['default'], $values, true)) {
            $this->errors["{$path}.default"] = 'Значение по умолчанию должно совпадать с одним из вариантов.';
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private function validateLabel(array $node, string $path): void
    {
        if (! $this->isNonEmptyString($node['label'] ?? null)) {
            $this->errors["{$path}.label"] = 'Укажите подпись.';
        }
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $allowed
     */
    private function rejectUnknownKeys(array $node, array $allowed, string $path): void
    {
        foreach (array_keys($node) as $key) {
            if (! in_array($key, $allowed, true)) {
                $this->errors["{$path}.{$key}"] = 'Неизвестный параметр схемы.';
            }
        }
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
