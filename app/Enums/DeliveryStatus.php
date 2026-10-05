<?php

namespace App\Enums;

/**
 * State of one Submission + Route delivery (FORMS_AND_INTEGRATIONS.md §25). Only `pending` and
 * `retry_scheduled` deliveries can be claimed by a worker.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case RetryScheduled = 'retry_scheduled';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function claimable(): array
    {
        return [self::Pending->value, self::RetryScheduled->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'В очереди',
            self::Processing => 'Отправляется',
            self::Delivered => 'Доставлено',
            self::RetryScheduled => 'Повтор запланирован',
            self::Failed => 'Ошибка',
            self::Cancelled => 'Отменено',
        };
    }
}
