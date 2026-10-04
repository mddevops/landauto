<?php

namespace App\Enums;

/**
 * Stable, user-safe Publish failure codes. Summaries never contain stack traces, paths or secrets.
 */
enum PublishFailure: string
{
    case ValidationFailed = 'validation_failed';
    case RenderFailed = 'render_failed';
    case ArtifactInvalid = 'artifact_invalid';
    case Conflict = 'conflict';
    case Internal = 'internal_error';

    public function summary(): string
    {
        return match ($this) {
            self::ValidationFailed => 'Сайт не прошёл проверку перед публикацией. Исправьте ошибки и попробуйте снова.',
            self::RenderFailed => 'Не удалось подготовить страницы сайта. Текущая версия сайта не изменилась.',
            self::ArtifactInvalid => 'Подготовленные страницы не прошли проверку. Текущая версия сайта не изменилась.',
            self::Conflict => 'Сайт уже публикуется. Дождитесь завершения текущей публикации.',
            self::Internal => 'Публикация не удалась из-за внутренней ошибки. Текущая версия сайта не изменилась.',
        };
    }
}
