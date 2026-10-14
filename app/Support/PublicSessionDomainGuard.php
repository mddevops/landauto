<?php

namespace App\Support;

use LogicException;

final class PublicSessionDomainGuard
{
    public function assertSafe(?string $sessionDomain, string $publicDomain): void
    {
        if ($sessionDomain === null || trim($sessionDomain) === '') {
            return;
        }
        $cookie = ltrim(strtolower(trim($sessionDomain)), '.');
        $public = strtolower(trim($publicDomain, '.'));
        if ($cookie === $public || str_ends_with($public, '.'.$cookie)) {
            throw new LogicException('SESSION_DOMAIN must not include the published Site domain.');
        }
    }
}
