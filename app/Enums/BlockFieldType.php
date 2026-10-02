<?php

namespace App\Enums;

enum BlockFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Boolean = 'boolean';
    case Select = 'select';
    case Image = 'image';
    case Group = 'group';
    case Repeater = 'repeater';

    public function isContainer(): bool
    {
        return $this === self::Group || $this === self::Repeater;
    }

    /**
     * @return list<string>
     */
    public function allowedKeys(): array
    {
        $common = ['key', 'type', 'label', 'help'];

        return match ($this) {
            self::Text, self::Textarea => [...$common, 'required', 'max_length', 'default'],
            self::Boolean => [...$common, 'default'],
            self::Select => [...$common, 'required', 'options', 'default'],
            self::Image => [...$common, 'required'],
            self::Group => [...$common, 'fields'],
            self::Repeater => [...$common, 'fields', 'min_items', 'max_items'],
        };
    }
}
