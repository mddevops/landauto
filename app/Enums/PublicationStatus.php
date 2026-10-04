<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Validating = 'validating';
    case Building = 'building';
    case Activating = 'activating';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isInProgress(): bool
    {
        return in_array($this, [self::Validating, self::Building, self::Activating], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Validating => 'Проверка',
            self::Building => 'Сборка',
            self::Activating => 'Активация',
            self::Succeeded => 'Опубликовано',
            self::Failed => 'Ошибка',
        };
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Validating => [self::Building, self::Failed],
            self::Building => [self::Activating, self::Failed],
            self::Activating => [self::Succeeded, self::Failed],
            self::Succeeded, self::Failed => [],
        };
    }
}
