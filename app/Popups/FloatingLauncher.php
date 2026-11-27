<?php

namespace App\Popups;

use App\Models\Site;
use Illuminate\Validation\Validator;

/**
 * Site-owned floating launcher. It never creates a hidden Popup: it may open an existing
 * Site Popup and/or show a small hover dialog with a CTA.
 */
final class FloatingLauncher
{
    public const DESKTOP_VARIANTS = ['standard', 'bookmark'];

    public const MOBILE_VARIANTS = ['standard', 'full_width'];

    public const ICONS = [
        'phone', 'phone_arrow', 'phone_waves', 'phone_circle', 'phone_dots',
        'phone_outline', 'phone_outline_arrow', 'phone_outline_waves', 'phone_outline_circle',
        'message', 'car',
    ];

    public const HOVER = ['none', 'dialog', 'shadow', 'scale', 'fade'];

    public const IDLE = ['none', 'waves', 'pulse', 'scale', 'wobble', 'shake', 'jelly', 'spin', 'bounce'];

    public const DESKTOP_SIZES = [44, 64, 80, 100];

    public const MOBILE_SIZES = [44, 64, 80];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'popup' => null,
            'desktop' => self::deviceDefaults('desktop'),
            'mobile' => self::deviceDefaults('mobile'),
            'dialog' => [
                'title' => 'Трудный выбор? Есть вопрос?',
                'subtitle' => 'Мы перезвоним вам в течение нескольких минут',
                'cta_label' => 'Оставьте телефон',
                'open_delay_seconds' => 45,
                'show_seconds' => 5,
                'open_popup' => true,
                ...self::dialogStyleDefaults('desktop'),
                'mobile' => self::dialogStyleDefaults('mobile'),
            ],
        ];
    }

    /**
     * Внешний вид диалога у кнопки: отдельно для десктопа и мобильных.
     *
     * @return array<string, mixed>
     */
    private static function dialogStyleDefaults(string $kind): array
    {
        $mobile = $kind === 'mobile';

        return [
            'title_size' => $mobile ? 18 : 19,
            'title_line' => 24,
            'subtitle_size' => 14,
            'subtitle_line' => 16,
            'button_enabled' => true,
            'cta_icon' => $mobile ? 'phone' : 'phone_waves',
            'cta_size' => 14,
            'cta_line' => 16,
            'pad_left' => $mobile ? 16 : 34,
            'pad_right' => $mobile ? 16 : 25,
            'pad_top' => $mobile ? 16 : 18,
            'pad_bottom' => $mobile ? 16 : 15,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeDialogStyle(mixed $input, string $kind): array
    {
        $defaults = self::dialogStyleDefaults($kind);

        if (! is_array($input)) {
            return $defaults;
        }

        $icon = $input['cta_icon'] ?? null;

        return [
            'title_size' => self::clampInt($input['title_size'] ?? null, 10, 48, $defaults['title_size']),
            'title_line' => self::clampInt($input['title_line'] ?? null, 10, 64, $defaults['title_line']),
            'subtitle_size' => self::clampInt($input['subtitle_size'] ?? null, 10, 32, $defaults['subtitle_size']),
            'subtitle_line' => self::clampInt($input['subtitle_line'] ?? null, 10, 48, $defaults['subtitle_line']),
            'button_enabled' => (bool) ($input['button_enabled'] ?? $defaults['button_enabled']),
            'cta_icon' => is_string($icon) && in_array($icon, self::ICONS, true) ? $icon : $defaults['cta_icon'],
            'cta_size' => self::clampInt($input['cta_size'] ?? null, 10, 32, $defaults['cta_size']),
            'cta_line' => self::clampInt($input['cta_line'] ?? null, 10, 48, $defaults['cta_line']),
            'pad_left' => self::clampInt($input['pad_left'] ?? null, 0, 80, $defaults['pad_left']),
            'pad_right' => self::clampInt($input['pad_right'] ?? null, 0, 80, $defaults['pad_right']),
            'pad_top' => self::clampInt($input['pad_top'] ?? null, 0, 80, $defaults['pad_top']),
            'pad_bottom' => self::clampInt($input['pad_bottom'] ?? null, 0, 80, $defaults['pad_bottom']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Site $site): array
    {
        return self::tryNormalize($site->floating_launcher_json) ?? self::defaults();
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
        $enabled = (bool) ($input['enabled'] ?? false);
        $popup = $input['popup'] ?? null;

        if ($popup !== null && (! is_string($popup) || strlen($popup) !== 26)) {
            return null;
        }

        $desktop = self::normalizeDevice($input['desktop'] ?? null, 'desktop');
        $mobile = self::normalizeDevice($input['mobile'] ?? null, 'mobile');
        $dialog = self::normalizeDialog($input['dialog'] ?? null);

        if ($desktop === null || $mobile === null || $dialog === null) {
            return null;
        }

        if ($enabled && $popup === null && $dialog['open_popup'] === true) {
            return null;
        }

        return [
            'enabled' => $enabled,
            'popup' => $popup,
            'desktop' => $desktop,
            'mobile' => $mobile,
            'dialog' => $dialog,
        ];
    }

    public static function validate(Validator $validator, mixed $input, Site $site): void
    {
        $normalized = self::tryNormalize($input);

        if ($normalized === null) {
            $validator->errors()->add('launcher', 'Проверьте настройки плавающей кнопки.');

            return;
        }

        $popupId = $normalized['popup'];

        if (is_string($popupId) && ! $site->popups()->where('public_id', $popupId)->exists()) {
            $validator->errors()->add('popup', 'Выберите попап этого сайта.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeDevice(mixed $input, string $device): ?array
    {
        $defaults = self::deviceDefaults($device);

        if ($input === null) {
            return $defaults;
        }

        if (! is_array($input)) {
            return null;
        }

        $variants = $device === 'desktop' ? self::DESKTOP_VARIANTS : self::MOBILE_VARIANTS;
        $variant = $input['variant'] ?? $defaults['variant'];

        if (! is_string($variant) || ! in_array($variant, $variants, true)) {
            return null;
        }

        $size = $input['size'] ?? $defaults['size'];
        $min = 24;
        $max = 160;

        if (! is_numeric($size) || (int) $size < $min || (int) $size > $max) {
            return null;
        }

        $icon = $input['icon'] ?? $defaults['icon'];
        $hover = $input['hover'] ?? $defaults['hover'];
        $idle = $input['idle'] ?? $defaults['idle'];
        $text = is_string($input['text'] ?? null) ? mb_substr(trim($input['text']), 0, 40) : $defaults['text'];
        $subtitle = is_string($input['subtitle'] ?? null) ? mb_substr(trim($input['subtitle']), 0, 80) : $defaults['subtitle'];
        $iconSize = $input['icon_size'] ?? $defaults['icon_size'];
        $interval = $input['interval_seconds'] ?? $defaults['interval_seconds'];
        $width = $input['width'] ?? $defaults['width'];

        if (! is_string($icon) || ! in_array($icon, self::ICONS, true)) {
            return null;
        }

        if (! is_string($hover) || ! in_array($hover, self::HOVER, true)) {
            return null;
        }

        if (! is_string($idle) || ! in_array($idle, self::IDLE, true)) {
            return null;
        }

        if (! is_numeric($iconSize) || (int) $iconSize < 12 || (int) $iconSize > 64) {
            return null;
        }

        if (! is_numeric($interval) || (int) $interval < 0 || (int) $interval > 60) {
            return null;
        }

        if (! is_numeric($width) || (int) $width < 80 || (int) $width > 360) {
            return null;
        }

        return [
            'variant' => $variant,
            'size' => (int) $size,
            'text_instead_of_icon' => (bool) ($input['text_instead_of_icon'] ?? false),
            'text' => $text === '' ? $defaults['text'] : $text,
            'icon' => $icon,
            'auto_icon_size' => (bool) ($input['auto_icon_size'] ?? true),
            'icon_size' => (int) $iconSize,
            'hover' => $hover,
            'idle' => $idle,
            'interval_seconds' => (int) $interval,
            'width' => (int) $width,
            'subtitle' => $subtitle,
            'look' => self::normalizeLook($input['look'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeLook(mixed $input): array
    {
        $defaults = self::lookDefaults();

        if (! is_array($input)) {
            return $defaults;
        }

        $hex = function (mixed $value, string $fallback): string {
            return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
                ? strtoupper($value)
                : $fallback;
        };
        $percent = function (mixed $value, int $fallback): int {
            if (! is_numeric($value)) {
                return $fallback;
            }

            $number = (int) $value;

            return $number < 0 || $number > 100 ? $fallback : $number;
        };
        $positions = [
            'top-left', 'top-center', 'top-right',
            'middle-left', 'middle-center', 'middle-right',
            'bottom-left', 'bottom-center', 'bottom-right',
        ];
        $position = $input['position'] ?? $defaults['position'];
        $radius = is_numeric($input['radius'] ?? null) ? (int) $input['radius'] : $defaults['radius'];
        $angle = is_numeric($input['angle'] ?? null) ? (int) $input['angle'] : $defaults['angle'];

        return [
            'gradient' => (bool) ($input['gradient'] ?? $defaults['gradient']),
            'color_from' => $hex($input['color_from'] ?? null, $defaults['color_from']),
            'opacity_from' => $percent($input['opacity_from'] ?? null, $defaults['opacity_from']),
            'color_to' => $hex($input['color_to'] ?? null, $defaults['color_to']),
            'opacity_to' => $percent($input['opacity_to'] ?? null, $defaults['opacity_to']),
            'angle' => $angle < 0 || $angle > 360 ? $defaults['angle'] : $angle,
            'icon_color' => $hex($input['icon_color'] ?? null, $defaults['icon_color']),
            'position' => is_string($position) && in_array($position, $positions, true) ? $position : $defaults['position'],
            'radius' => $radius < 0 || $radius > 80 ? $defaults['radius'] : $radius,
            'offset_x' => self::clampInt($input['offset_x'] ?? null, 0, 300, $defaults['offset_x']),
            'offset_y' => self::clampInt($input['offset_y'] ?? null, 0, 300, $defaults['offset_y']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lookDefaults(): array
    {
        return [
            'gradient' => true,
            'color_from' => '#C76EEC',
            'opacity_from' => 100,
            'color_to' => '#28ADCB',
            'opacity_to' => 100,
            'angle' => 240,
            'icon_color' => '#FFFFFF',
            'position' => 'bottom-right',
            'radius' => 32,
            'offset_x' => 24,
            'offset_y' => 24,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeDialog(mixed $input): ?array
    {
        $defaults = self::defaults()['dialog'];

        if ($input === null) {
            return $defaults;
        }

        if (! is_array($input)) {
            return null;
        }

        $title = is_string($input['title'] ?? null) ? mb_substr(trim($input['title']), 0, 120) : $defaults['title'];
        $subtitle = is_string($input['subtitle'] ?? null) ? mb_substr(trim($input['subtitle']), 0, 200) : $defaults['subtitle'];
        $cta = is_string($input['cta_label'] ?? null) ? mb_substr(trim($input['cta_label']), 0, 40) : $defaults['cta_label'];
        $openDelay = $input['open_delay_seconds'] ?? $defaults['open_delay_seconds'];
        $show = $input['show_seconds'] ?? $defaults['show_seconds'];

        if (! is_numeric($openDelay) || (int) $openDelay < 0 || (int) $openDelay > 120) {
            return null;
        }

        if (! is_numeric($show) || (int) $show < 1 || (int) $show > 30) {
            return null;
        }

        return [
            'title' => $title === '' ? $defaults['title'] : $title,
            'subtitle' => $subtitle,
            'cta_label' => $cta === '' ? $defaults['cta_label'] : $cta,
            'open_delay_seconds' => (int) $openDelay,
            'show_seconds' => (int) $show,
            'open_popup' => (bool) ($input['open_popup'] ?? true),
            ...self::normalizeDialogStyle($input, 'desktop'),
            'mobile' => self::normalizeDialogStyle($input['mobile'] ?? null, 'mobile'),
        ];
    }

    private static function clampInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $number = (int) $value;

        return $number < $min || $number > $max ? $fallback : $number;
    }

    /**
     * @return array<string, mixed>
     */
    private static function deviceDefaults(string $device): array
    {
        return [
            'variant' => 'standard',
            'size' => 64,
            'text_instead_of_icon' => false,
            'text' => 'Звонок',
            'icon' => 'phone',
            'auto_icon_size' => true,
            'icon_size' => $device === 'mobile' ? 24 : 32,
            'hover' => 'dialog',
            'idle' => 'none',
            'interval_seconds' => 0,
            'width' => 206,
            'subtitle' => '',
            'look' => self::lookDefaults(),
        ];
    }
}
