<?php

namespace App\Enums;

enum IntegrationAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKeyHeader = 'api_key_header';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Без авторизации',
            self::Bearer => 'Bearer-токен',
            self::Basic => 'Логин и пароль (Basic)',
            self::ApiKeyHeader => 'API-ключ в заголовке',
        };
    }

    /**
     * Credential keys stored encrypted for this auth type. The first key is the main secret
     * whose last characters form the masked hint.
     *
     * @return list<string>
     */
    public function credentialKeys(): array
    {
        return match ($this) {
            self::None => [],
            self::Bearer, self::ApiKeyHeader => ['token'],
            self::Basic => ['password', 'username'],
        };
    }
}
