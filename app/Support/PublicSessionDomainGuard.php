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

    /**
     * Published Sites must never share the application origin (D-127): their scripts would run
     * next to the application session.
     */
    public function assertSeparateHosts(string $appUrl, string $publicDomain): void
    {
        $app = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        $public = strtolower(trim($publicDomain, '.'));
        if ($app === '' || $app === $public) {
            throw new LogicException('LANDFLOW_PUBLIC_DOMAIN must differ from the APP_URL host.');
        }
    }
}
