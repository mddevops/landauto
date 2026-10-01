<?php

namespace App\Data;

final readonly class YandexUserProfile
{
    public function __construct(
        public string $providerUserId,
        public string $email,
        public string $name,
    ) {}
}
