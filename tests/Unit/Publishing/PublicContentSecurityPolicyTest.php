<?php

namespace Tests\Unit\Publishing;

use App\Publishing\Runtime\PublicContentSecurityPolicy;
use PHPUnit\Framework\TestCase;

class PublicContentSecurityPolicyTest extends TestCase
{
    public function test_default_policy_allows_only_the_published_and_legacy_sandbox_runtime(): void
    {
        $header = (new PublicContentSecurityPolicy)->header(false, false);

        $this->assertStringContainsString("default-src 'self'", $header);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $header);
        $this->assertStringContainsString("frame-src 'none'", $header);
        $this->assertStringContainsString("object-src 'none'", $header);
        $this->assertStringContainsString("frame-ancestors 'none'", $header);
        $this->assertStringNotContainsString("'unsafe-eval'", $header);
        $this->assertStringNotContainsString('yandex.ru', $header);
    }

    public function test_only_active_first_party_integrations_extend_the_policy(): void
    {
        $metrica = (new PublicContentSecurityPolicy)->header(true, false);
        $captcha = (new PublicContentSecurityPolicy)->header(false, true);

        $this->assertStringContainsString('https://mc.yandex.ru', $metrica);
        $this->assertStringNotContainsString('smartcaptcha.cloud.yandex.ru', $metrica);
        $this->assertStringContainsString('https://smartcaptcha.cloud.yandex.ru', $captcha);
        $this->assertStringNotContainsString('mc.yandex.ru', $captcha);
    }
}
