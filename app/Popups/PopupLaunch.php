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
                'name' => '',
                'enabled' => true,
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
            'canvas' => self::normalizeCanvas($input['canvas'] ?? null, $confirmation),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeCanvas(mixed $input, string $confirmation): array
    {
        $defaults = self::canvasDefaults($confirmation);

        if (! is_array($input)) {
            return $defaults;
        }

        $modes = ['auto', 'off', 'once_session', 'periodic', 'custom'];
        $scenario = function (mixed $value, string $fallback) use ($modes): string {
            return is_string($value) && in_array($value, $modes, true) ? $value : $fallback;
        };
        $blocks = function (mixed $value, array $fallback): array {
            if (! is_array($value)) {
                return $fallback;
            }

            $normalized = $fallback;

            foreach ($fallback as $key => $enabled) {
                if (array_key_exists($key, $value)) {
                    $normalized[$key] = (bool) $value[$key];
                }
            }

            return $normalized;
        };
        $seconds = is_numeric($input['close_after_seconds'] ?? null) ? (int) $input['close_after_seconds'] : $defaults['close_after_seconds'];
        $width = is_numeric($input['width'] ?? null) ? (int) $input['width'] : $defaults['width'];
        $radius = is_numeric($input['radius'] ?? null) ? (int) $input['radius'] : $defaults['radius'];
        $elementRadius = is_numeric($input['element_radius'] ?? null) ? (int) $input['element_radius'] : $defaults['element_radius'];
        $positions = ['left', 'center', 'bottom-left', 'bottom-center', 'bottom-right'];
        $position = $input['position'] ?? $defaults['position'];
        $kind = $input['confirmation_kind'] ?? $defaults['confirmation_kind'];

        return [
            'desktop_scenario' => $scenario($input['desktop_scenario'] ?? null, $defaults['desktop_scenario']),
            'mobile_scenario' => $scenario($input['mobile_scenario'] ?? null, $defaults['mobile_scenario']),
            'preset' => is_string($input['preset'] ?? null) ? mb_substr($input['preset'], 0, 80) : '',
            'desktop_blocks' => $blocks($input['desktop_blocks'] ?? null, $defaults['desktop_blocks']),
            'mobile_blocks' => $blocks($input['mobile_blocks'] ?? null, $defaults['mobile_blocks']),
            'confirmation_kind' => $kind === 'notice' || $kind === 'replace' ? $kind : $defaults['confirmation_kind'],
            'close_scenario' => ($input['close_scenario'] ?? null) === 'manual' ? 'manual' : 'auto',
            'close_after_seconds' => $seconds < 1 || $seconds > 60 ? $defaults['close_after_seconds'] : $seconds,
            'confirm_blocks' => $blocks($input['confirm_blocks'] ?? null, $defaults['confirm_blocks']),
            'width' => $width < 240 || $width > 640 ? $defaults['width'] : $width,
            'radius' => $radius < 0 || $radius > 40 ? $defaults['radius'] : $radius,
            'element_radius' => $elementRadius < 0 || $elementRadius > 40 ? $defaults['element_radius'] : $elementRadius,
            'position' => is_string($position) && in_array($position, $positions, true) ? $position : $defaults['position'],
            'appearance' => self::normalizeAppearance($input['appearance'] ?? null),
            'appearance_mobile' => self::normalizeAppearance($input['appearance_mobile'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function canvasDefaults(string $confirmation): array
    {
        $blocks = [
            'logo' => true, 'title' => true, 'subtitle' => true, 'name' => true, 'phone' => true,
            'email' => true, 'comment' => true, 'call_time' => true, 'department' => true,
            'button' => true, 'consent' => true, 'copyright' => true, 'image' => true,
            'close' => true, 'ad_label' => true, 'promo_timer' => true, 'backdrop' => true,
        ];
        $confirm = [
            'icon' => true, 'timer' => true, 'title' => true, 'subtitle' => true,
            'close' => true, 'button' => true, 'copyright' => true, 'image' => true,
            'logo' => false, 'background' => false,
        ];

        return [
            'desktop_scenario' => 'auto',
            'mobile_scenario' => 'off',
            'preset' => '',
            'desktop_blocks' => $blocks,
            'mobile_blocks' => $blocks,
            'confirmation_kind' => $confirmation === 'notice' ? 'notice' : 'replace',
            'close_scenario' => 'auto',
            'close_after_seconds' => 5,
            'confirm_blocks' => $confirm,
            'width' => 300,
            'radius' => 16,
            'element_radius' => 10,
            'position' => 'bottom-right',
            'appearance' => self::appearanceDefaults(),
            'appearance_mobile' => self::appearanceDefaults(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function appearanceDefaults(): array
    {
        return [
            'height' => 296,
            'height_auto' => true,
            'gradient' => false,
            'color_from' => '#FFFFFF',
            'opacity_from' => 100,
            'color_to' => '#28ADCB',
            'opacity_to' => 100,
            'angle' => 240,
            'border_color' => '#C76EEC',
            'border_opacity' => 0,
            'border_width' => 1,
            'pad_left' => 24,
            'pad_right' => 24,
            'pad_top' => 24,
            'pad_bottom' => 18,
            'theme' => 'light',
            'confirm_width' => 300,
            'confirm_height' => 150,
            'confirm_height_auto' => true,
            'confirm_radius' => 16,
            'confirm_element_radius' => 4,
            'button_label' => 'Жду звонка',
            'button_color' => '#2AA3C7',
            'button_height' => 44,
            'title_size' => 24,
            'title_color' => '#1F282C',
            'title_align' => 'left',
            'subtitle_size' => 14,
            'illustration_kind' => 'manager',
            'illustration_width' => 107,
            'illustration_height' => 230,
            'illustration_opacity' => 100,
            'illustration_corner' => 'bottom-right',
            'illustration_x' => 28,
            'illustration_y' => 0,
            'backdrop_shape' => 'circle',
            'backdrop_width' => 419,
            'backdrop_height' => 419,
            'backdrop_gradient' => true,
            'backdrop_from' => '#28ADCB',
            'backdrop_from_opacity' => 100,
            'backdrop_to' => '#C76EEC',
            'backdrop_to_opacity' => 100,
            'backdrop_angle' => 240,
            'backdrop_corner' => 'bottom-right',
            'backdrop_x' => -85,
            'backdrop_y' => -30,
            'call_time_variant' => 'bullets',
            'call_time_size' => 12,
            'call_time_line' => 14,
            'call_time_color' => '#1F282C',
            'call_time_bullet_color' => '#C76EEC',
            'call_time_link_color' => '#28ADCB',
            'call_time_align' => 'left',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeAppearance(mixed $input): array
    {
        $defaults = self::appearanceDefaults();

        if (! is_array($input)) {
            return $defaults;
        }

        $hex = function (mixed $value, string $fallback): string {
            return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
                ? strtoupper($value)
                : $fallback;
        };
        $int = function (mixed $value, int $min, int $max, int $fallback): int {
            if (! is_numeric($value)) {
                return $fallback;
            }

            $number = (int) $value;

            return $number < $min || $number > $max ? $fallback : $number;
        };
        $choice = function (mixed $value, array $allowed, string $fallback): string {
            return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        };
        $corners = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
        $align = $input['title_align'] ?? $defaults['title_align'];
        $theme = $input['theme'] ?? $defaults['theme'];

        return [
            'height' => $int($input['height'] ?? null, 80, 900, $defaults['height']),
            'height_auto' => (bool) ($input['height_auto'] ?? $defaults['height_auto']),
            'gradient' => (bool) ($input['gradient'] ?? $defaults['gradient']),
            'color_from' => $hex($input['color_from'] ?? null, $defaults['color_from']),
            'opacity_from' => $int($input['opacity_from'] ?? null, 0, 100, $defaults['opacity_from']),
            'color_to' => $hex($input['color_to'] ?? null, $defaults['color_to']),
            'opacity_to' => $int($input['opacity_to'] ?? null, 0, 100, $defaults['opacity_to']),
            'angle' => $int($input['angle'] ?? null, 0, 360, $defaults['angle']),
            'border_color' => $hex($input['border_color'] ?? null, $defaults['border_color']),
            'border_opacity' => $int($input['border_opacity'] ?? null, 0, 100, $defaults['border_opacity']),
            'border_width' => $int($input['border_width'] ?? null, 0, 20, $defaults['border_width']),
            'pad_left' => $int($input['pad_left'] ?? null, 0, 80, $defaults['pad_left']),
            'pad_right' => $int($input['pad_right'] ?? null, 0, 80, $defaults['pad_right']),
            'pad_top' => $int($input['pad_top'] ?? null, 0, 80, $defaults['pad_top']),
            'pad_bottom' => $int($input['pad_bottom'] ?? null, 0, 80, $defaults['pad_bottom']),
            'theme' => $theme === 'dark' ? 'dark' : 'light',
            'confirm_width' => $int($input['confirm_width'] ?? null, 180, 640, $defaults['confirm_width']),
            'confirm_height' => $int($input['confirm_height'] ?? null, 80, 800, $defaults['confirm_height']),
            'confirm_height_auto' => (bool) ($input['confirm_height_auto'] ?? $defaults['confirm_height_auto']),
            'confirm_radius' => $int($input['confirm_radius'] ?? null, 0, 40, $defaults['confirm_radius']),
            'confirm_element_radius' => $int($input['confirm_element_radius'] ?? null, 0, 40, $defaults['confirm_element_radius']),
            'button_label' => is_string($input['button_label'] ?? null) && trim($input['button_label']) !== ''
                ? mb_substr(trim($input['button_label']), 0, 40)
                : $defaults['button_label'],
            'button_color' => $hex($input['button_color'] ?? null, $defaults['button_color']),
            'button_height' => $int($input['button_height'] ?? null, 24, 80, $defaults['button_height']),
            'title_size' => $int($input['title_size'] ?? null, 10, 48, $defaults['title_size']),
            'title_color' => $hex($input['title_color'] ?? null, $defaults['title_color']),
            'title_align' => in_array($align, ['left', 'center', 'right'], true) ? $align : $defaults['title_align'],
            'subtitle_size' => $int($input['subtitle_size'] ?? null, 10, 32, $defaults['subtitle_size']),
            'illustration_kind' => $choice($input['illustration_kind'] ?? null, ['manager', 'manager_woman', 'car'], $defaults['illustration_kind']),
            'illustration_width' => $int($input['illustration_width'] ?? null, 10, 600, $defaults['illustration_width']),
            'illustration_height' => $int($input['illustration_height'] ?? null, 10, 600, $defaults['illustration_height']),
            'illustration_opacity' => $int($input['illustration_opacity'] ?? null, 0, 100, $defaults['illustration_opacity']),
            'illustration_corner' => $choice($input['illustration_corner'] ?? null, $corners, $defaults['illustration_corner']),
            'illustration_x' => $int($input['illustration_x'] ?? null, -400, 400, $defaults['illustration_x']),
            'illustration_y' => $int($input['illustration_y'] ?? null, -400, 400, $defaults['illustration_y']),
            'backdrop_shape' => 'circle',
            'backdrop_width' => $int($input['backdrop_width'] ?? null, 50, 900, $defaults['backdrop_width']),
            'backdrop_height' => $int($input['backdrop_height'] ?? null, 50, 900, $defaults['backdrop_height']),
            'backdrop_gradient' => (bool) ($input['backdrop_gradient'] ?? $defaults['backdrop_gradient']),
            'backdrop_from' => $hex($input['backdrop_from'] ?? null, $defaults['backdrop_from']),
            'backdrop_from_opacity' => $int($input['backdrop_from_opacity'] ?? null, 0, 100, $defaults['backdrop_from_opacity']),
            'backdrop_to' => $hex($input['backdrop_to'] ?? null, $defaults['backdrop_to']),
            'backdrop_to_opacity' => $int($input['backdrop_to_opacity'] ?? null, 0, 100, $defaults['backdrop_to_opacity']),
            'backdrop_angle' => $int($input['backdrop_angle'] ?? null, 0, 360, $defaults['backdrop_angle']),
            'backdrop_corner' => $choice($input['backdrop_corner'] ?? null, $corners, $defaults['backdrop_corner']),
            'backdrop_x' => $int($input['backdrop_x'] ?? null, -600, 600, $defaults['backdrop_x']),
            'backdrop_y' => $int($input['backdrop_y'] ?? null, -600, 600, $defaults['backdrop_y']),
            'call_time_variant' => $choice($input['call_time_variant'] ?? null, ['bullets', 'row'], $defaults['call_time_variant']),
            'call_time_size' => $int($input['call_time_size'] ?? null, 8, 32, $defaults['call_time_size']),
            'call_time_line' => $int($input['call_time_line'] ?? null, 8, 48, $defaults['call_time_line']),
            'call_time_color' => $hex($input['call_time_color'] ?? null, $defaults['call_time_color']),
            'call_time_bullet_color' => $hex($input['call_time_bullet_color'] ?? null, $defaults['call_time_bullet_color']),
            'call_time_link_color' => $hex($input['call_time_link_color'] ?? null, $defaults['call_time_link_color']),
            'call_time_align' => $choice($input['call_time_align'] ?? null, ['left', 'center', 'right'], $defaults['call_time_align']),
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

        $name = is_string($input['name'] ?? null) ? mb_substr(trim($input['name']), 0, 80) : '';

        return [
            'join' => $join,
            'groups' => $groups,
            'name' => $name,
            'enabled' => (bool) ($input['enabled'] ?? true),
        ];
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

        if (strlen($value) > 200) {
            return null;
        }

        if ($value === '') {
            return ['type' => $type, 'operator' => $operator, 'value' => ''];
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
