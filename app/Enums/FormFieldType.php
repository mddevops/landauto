<?php

namespace App\Enums;

enum FormFieldType: string
{
    case Text = 'text';
    case Phone = 'phone';
    case Email = 'email';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Строка',
            self::Phone => 'Телефон',
            self::Email => 'Email',
            self::Textarea => 'Многострочный текст',
            self::Select => 'Выбор из списка',
            self::Checkbox => 'Флажок',
            self::Consent => 'Согласие',
            self::Hidden => 'Скрытое поле',
        };
    }

    /** Default and maximum length of free-text values. */
    public function maxLength(): ?int
    {
        return match ($this) {
            self::Text, self::Hidden => 255,
            self::Textarea => 2000,
            self::Phone => 32,
            self::Email => 254,
            default => null,
        };
    }

    public function isBoolean(): bool
    {
        return $this === self::Checkbox || $this === self::Consent;
    }
}
