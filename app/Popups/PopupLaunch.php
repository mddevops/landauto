<?php

namespace App\Popups;

use Illuminate\Validation\Validator;

/**
 * Auto-show and audience rules for a Site Popup.
 *
 * Landflow decisions (not Calltouch claims):
 * - Conditions apply only to automatic show. A visitor click always opens the chosen published Popup.
 * - When several auto-show Popups match, only the first by name then public_id opens; never stack modals.
 * - `auto` waits 8 seconds (Landflow default). Scroll, exit-intent and extra triggers are out of scope.
 * - Missing visitor data (geo, goals) makes that rule false.
 */
final class PopupLaunch
{
    public const AUTO_DELAY_SECONDS = 8;

    public const MODES = ['off', 'auto', 'once_session', 'periodic', 'custom'];

    public const JOINS = ['and', 'or'];

    public const CONFIRMATIONS = ['replace', 'notice'];

    /** @var array<string, list<string>> */
    public const OPERATORS = [
        'pages' => ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
        'devices' => ['equals', 'not_equals'],
        'visit' => ['not_visited', 'visited', 'unique'],
        'goals' => ['equals', 'not_equals'],
        'geo' => ['equals', 'not_equals'],
        'channel' => ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
        'source' => ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
        'campaign' => ['contains', 'not_contains', 'equals', 'not_equals', 'regex'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'auto_show' => 'off',
            'delay_seconds' => self::AUTO_DELAY_SECONDS,
            'period_seconds' => 60,
            'shows' => [],
            'confirmation' => 'replace',
            'conditions' => [
                'join' => 'and',
                'groups' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(mixed $raw): array
    {
        $normalized = self::tryNormalize($raw);

        return $normalized ?? self::defaults();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function tryNormalize(mixed $input): ?array
    {
        if ($input === null || $input === []) {
            return self::defaults();
        }

        if (! is_array($input)) {
            return null;
        }

        $defaults = self::defaults();
        $mode = $input['auto_show'] ?? $defaults['auto_show'];

        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            return null;
        }

        $delay = $input['delay_seconds'] ?? $defaults['delay_seconds'];
        $period = $input['period_seconds'] ?? $defaults['period_seconds'];
        $confirmation = $input['confirmation'] ?? $defaults['confirmation'];

        if (! is_numeric($delay) || (int) $delay < 0 || (int) $delay > 300) {
            return null;
        }

        if (! is_numeric($period) || (int) $period < 10 || (int) $period > 86_400) {
            return null;
        }

        if (! is_string($confirmation) || ! in_array($confirmation, self::CONFIRMATIONS, true)) {
            return null;
        }

        $shows = [];

        if (isset($input['shows'])) {
            if (! is_array($input['shows']) || count($input['shows']) > 10) {
                return null;
            }

            foreach ($input['shows'] as $show) {
                if (! is_array($show) || ! is_numeric($show['delay_seconds'] ?? null)) {
                    return null;
                }

                $seconds = (int) $show['delay_seconds'];

                if ($seconds < 0 || $seconds > 300) {
                    return null;
                }

                $shows[] = ['delay_seconds' => $seconds];
            }
        }

        $conditions = $input['conditions'] ?? $defaults['conditions'];
        $normalizedConditions = self::normalizeConditions($conditions);

        if ($normalizedConditions === null) {
            return null;
        }

        if ($mode === 'custom' && $shows === []) {
            return null;
        }

        return [
            'auto_show' => $mode,
            'delay_seconds' => (int) $delay,
            'period_seconds' => (int) $period,
            'shows' => $shows,
            'confirmation' => $confirmation,
            'conditions' => $normalizedConditions,
        ];
    }

    public static function validate(Validator $validator, mixed $input, string $attribute = 'launch'): void
    {
        if (self::tryNormalize($input) === null) {
            $validator->errors()->add($attribute, 'Проверьте настройки запуска и условия аудитории.');
        }
    }

    /**
     * @return array{join: string, groups: list<array{join: string, rules: list<array{type: string, operator: string, value: string|int|null}>}>}|null
     */
    private static function normalizeConditions(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $join = $input['join'] ?? 'and';

        if (! is_string($join) || ! in_array($join, self::JOINS, true)) {
            return null;
        }

        $groupsInput = $input['groups'] ?? [];

        if (! is_array($groupsInput) || count($groupsInput) > 8) {
            return null;
        }

        $groups = [];

        foreach ($groupsInput as $group) {
            $normalized = self::normalizeGroup($group);

            if ($normalized === null) {
                return null;
            }

            $groups[] = $normalized;
        }

        return ['join' => $join, 'groups' => $groups];
    }

    /**
     * @return array{join: string, rules: list<array{type: string, operator: string, value: string|int|null}>}|null
     */
    private static function normalizeGroup(mixed $group): ?array
    {
        if (! is_array($group)) {
            return null;
        }

        $join = $group['join'] ?? 'and';

        if (! is_string($join) || ! in_array($join, self::JOINS, true)) {
            return null;
        }

        $rulesInput = $group['rules'] ?? [];

        if (! is_array($rulesInput) || $rulesInput === [] || count($rulesInput) > 10) {
            return null;
        }

        $rules = [];

        foreach ($rulesInput as $rule) {
            $normalized = self::normalizeRule($rule);

            if ($normalized === null) {
                return null;
            }

            $rules[] = $normalized;
        }

        return ['join' => $join, 'rules' => $rules];
    }

    /**
     * @return array{type: string, operator: string, value: string|int|null}|null
     */
    private static function normalizeRule(mixed $rule): ?array
    {
        if (! is_array($rule) || ! is_string($rule['type'] ?? null) || ! is_string($rule['operator'] ?? null)) {
            return null;
        }

        $type = $rule['type'];
        $operator = $rule['operator'];
        $allowed = self::OPERATORS[$type] ?? null;

        if ($allowed === null || ! in_array($operator, $allowed, true)) {
            return null;
        }

        if ($type === 'visit' && $operator === 'unique') {
            return ['type' => $type, 'operator' => $operator, 'value' => null];
        }

        if ($type === 'visit' && ($operator === 'visited' || $operator === 'not_visited')) {
            if (! is_numeric($rule['value'] ?? null)) {
                return null;
            }

            $days = (int) $rule['value'];

            if ($days < 1 || $days > 365) {
                return null;
            }

            return ['type' => $type, 'operator' => $operator, 'value' => $days];
        }

        if ($type === 'devices') {
            $value = $rule['value'] ?? null;

            if (! is_string($value) || ! in_array($value, ['mobile', 'desktop'], true)) {
                return null;
            }

            return ['type' => $type, 'operator' => $operator, 'value' => $value];
        }

        $value = $rule['value'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > 200) {
            return null;
        }

        if ($operator === 'regex') {
            $pattern = '#'.str_replace('#', '\#', $value).'#u';

            if (@preg_match($pattern, '') === false) {
                return null;
            }
        }

        return ['type' => $type, 'operator' => $operator, 'value' => $value];
    }
}
