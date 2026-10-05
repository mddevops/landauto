<?php

namespace App\Integrations\Mapping;

/**
 * Declarative payload builder: an ordered list of `{target, source, value?, missing}` rules turns
 * a Submission into a JSON object. No expressions, scripts or visitor-supplied paths; the output
 * depends only on the rules and the stored Submission, so it is deterministic.
 *
 * @phpstan-type Rule array{target: string, source: string, value?: string|null, missing?: string}
 */
final class FieldMapper
{
    public const MISSING_OMIT = 'omit';

    public const MISSING_NULL = 'null';

    public const MISSING_ERROR = 'error';

    public const MISSING = [self::MISSING_OMIT, self::MISSING_NULL, self::MISSING_ERROR];

    public const MAX_RULES = 50;

    /** Up to four dot-separated segments: `client.phone`. */
    public const TARGET_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,63}(\.[A-Za-z_][A-Za-z0-9_]{0,63}){0,3}$/';

    /**
     * @param  list<Rule>  $rules  Empty rules use the default payload.
     * @return array<string, mixed>
     *
     * @throws MappingFailure
     */
    public function map(array $rules, MappingContext $context): array
    {
        $payload = [];

        foreach ($rules === [] ? self::defaultRules($context) : $rules as $rule) {
            $target = $rule['target'];

            if (preg_match(self::TARGET_PATTERN, $target) !== 1) {
                throw new MappingFailure('invalid_mapping', 'Настройка передачи содержит недопустимое поле.');
            }

            $value = MappingSources::resolve($rule['source'], $context, $rule['value'] ?? null);

            if ($value === null || $value === '') {
                $missing = $rule['missing'] ?? self::MISSING_OMIT;

                if ($missing === self::MISSING_ERROR) {
                    throw new MappingFailure('missing_required_value', "В заявке нет значения для обязательного поля «{$target}».");
                }

                if ($missing !== self::MISSING_NULL) {
                    continue;
                }

                $value = null;
            }

            self::set($payload, explode('.', $target), $value);
        }

        return $payload;
    }

    /**
     * Payload used when a route has no explicit mapping.
     *
     * @return list<Rule>
     */
    public static function defaultRules(MappingContext $context): array
    {
        $rules = [
            ['target' => 'submission_id', 'source' => 'submission.public_id'],
            ['target' => 'submitted_at', 'source' => 'submission.submitted_at'],
            ['target' => 'form', 'source' => 'form.name'],
            ['target' => 'site', 'source' => 'site.name'],
        ];

        foreach (array_keys($context->fields) as $key) {
            $rules[] = ['target' => 'fields.'.$key, 'source' => MappingSources::FIELD_PREFIX.$key];
        }

        foreach (['vehicle.title', 'vehicle.color', 'offer.modification', 'offer.equipment', 'offer.price', 'offer.currency', 'page.url', 'utm.source', 'utm.medium', 'utm.campaign', 'utm.content', 'utm.term'] as $source) {
            $rules[] = ['target' => $source, 'source' => $source];
        }

        return $rules;
    }

    /**
     * Save-time validation of a rule list against the Form fields and the binding overrides.
     *
     * @param  list<array<string, mixed>>  $rules
     * @param  array<int, string>  $fieldKeys
     * @param  array<int, string>  $overrideKeys
     * @return array<string, string> Error messages keyed by `mapping.N.attribute`.
     */
    public static function validate(array $rules, array $fieldKeys, array $overrideKeys): array
    {
        $errors = [];
        $targets = [];

        foreach ($rules as $index => $rule) {
            $target = $rule['target'] ?? null;
            $source = $rule['source'] ?? null;

            if (! is_string($target) || preg_match(self::TARGET_PATTERN, $target) !== 1) {
                $errors["mapping.{$index}.target"] = 'Поле назначения: латиница, цифры и «_», вложенность через точку.';
            } else {
                foreach ($targets as $existing) {
                    if ($existing === $target || str_starts_with($existing, $target.'.') || str_starts_with($target, $existing.'.')) {
                        $errors["mapping.{$index}.target"] = 'Поле назначения повторяется или пересекается с другим.';
                    }
                }

                $targets[] = $target;
            }

            $valid = is_string($source) && (
                MappingSources::isFixed($source)
                || $source === MappingSources::CONSTANT
                || in_array(MappingSources::fieldKey($source), $fieldKeys, true)
                || in_array(MappingSources::overrideKey($source), $overrideKeys, true)
            );

            if (! $valid) {
                $errors["mapping.{$index}.source"] = 'Выберите источник из списка.';
            }

            $value = $rule['value'] ?? null;

            if ($source === MappingSources::CONSTANT && (! is_string($value) || $value === '' || mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) {
                $errors["mapping.{$index}.value"] = 'Укажите постоянное значение одной строкой до 255 символов.';
            }

            if (! in_array($rule['missing'] ?? self::MISSING_OMIT, self::MISSING, true)) {
                $errors["mapping.{$index}.missing"] = 'Выберите, что делать при пустом значении.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return Rule
     */
    public static function normalize(array $rule): array
    {
        $normalized = [
            'target' => (string) $rule['target'],
            'source' => (string) $rule['source'],
            'missing' => (string) ($rule['missing'] ?? self::MISSING_OMIT),
        ];

        if ($normalized['source'] === MappingSources::CONSTANT) {
            $normalized['value'] = (string) $rule['value'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $path
     */
    private static function set(array &$payload, array $path, string|int|float|bool|null $value): void
    {
        $key = array_shift($path);

        if ($path === []) {
            $payload[$key] = $value;

            return;
        }

        if (! isset($payload[$key]) || ! is_array($payload[$key])) {
            $payload[$key] = [];
        }

        self::set($payload[$key], $path, $value);
    }
}
