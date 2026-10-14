<?php

namespace Tests\Unit;

use App\Support\PublicSessionDomainGuard;
use LogicException;
use PHPUnit\Framework\TestCase;

class PublicSessionDomainGuardTest extends TestCase
{
    public function test_host_only_and_separate_domains_are_safe(): void
    {
        $guard = new PublicSessionDomainGuard;
        $guard->assertSafe(null, 'landflow.me');
        $guard->assertSafe('app.landflow.me', 'sites.landflow.me');
        $this->addToAssertionCount(1);
    }

    public function test_parent_cookie_domain_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        (new PublicSessionDomainGuard)->assertSafe('.landflow.me', 'landflow.me');
    }
}
