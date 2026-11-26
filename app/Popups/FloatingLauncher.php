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

    public const ICONS = ['phone', 'message', 'car'];

    public const HOVER = ['none', 'dialog', 'scale'];

    public const IDLE = ['none', 'pulse', 'bounce'];

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
                'title' => 'Нужна помощь?',
                'subtitle' => 'Оставьте заявку — перезвоним.',
                'cta_label' => 'Открыть форму',
                'open_delay_seconds' => 0,
                'show_seconds' => 5,
                'open_popup' => true,
            ],
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
        $min = 44;
        $max = $device === 'desktop' ? 100 : 80;

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
        ];
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
        ];
    }
}
