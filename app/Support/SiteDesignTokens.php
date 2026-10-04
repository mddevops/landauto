<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Fixed set of Site-level design tokens. Values are closed enums or hex colours so they can be
 * rendered as CSS variables without any customer-supplied CSS.
 */
final class SiteDesignTokens
{
    public const COLOR_PATTERN = '/^#[0-9a-f]{6}$/';

    public const DEFAULTS = [
        'primary_color' => '#171717',
        'secondary_color' => '#525252',
        'font_family' => 'sans',
        'radius' => 'medium',
        'container' => 'default',
        'button_style' => 'solid',
    ];

    public const CHOICES = [
        'font_family' => ['sans', 'serif'],
        'radius' => ['none', 'small', 'medium', 'large'],
        'container' => ['narrow', 'default', 'wide'],
        'button_style' => ['solid', 'outline'],
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $rules = [
            'primary_color' => ['required', 'string', 'regex:'.self::COLOR_PATTERN],
            'secondary_color' => ['required', 'string', 'regex:'.self::COLOR_PATTERN],
        ];

        foreach (self::CHOICES as $key => $choices) {
            $rules[$key] = ['required', 'string', Rule::in($choices)];
        }

        return $rules;
    }

    /**
     * Stored tokens merged over defaults; unknown or invalid stored values fall back to defaults.
     *
     * @return array<string, string>
     */
    public static function resolve(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $tokens = self::DEFAULTS;

        foreach ($tokens as $key => $default) {
            $value = $stored[$key] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $valid = array_key_exists($key, self::CHOICES)
                ? in_array($value, self::CHOICES[$key], true)
                : preg_match(self::COLOR_PATTERN, $value) === 1;

            if ($valid) {
                $tokens[$key] = $value;
            }
        }

        return $tokens;
    }
}
