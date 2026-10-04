<?php

namespace App\Enums;

/**
 * Where a Submission came from. Preview submissions are test entries sent from the
 * authenticated draft preview: they are stored for debugging, hidden from the normal lead
 * list, never delivered to integrations and never counted as conversions (X-021).
 */
enum SubmissionMode: string
{
    case Public = 'public';
    case Preview = 'preview';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Заявка с сайта',
            self::Preview => 'Тестовая (предпросмотр)',
        };
    }
}
