<?php

namespace App\Publishing\Runtime;

use App\Models\Site;

/**
 * A resolved public host: the Site, and the host to 301-redirect to when the request did not use
 * the Site's primary address (null = serve here).
 */
final readonly class PublicSiteMatch
{
    public function __construct(public Site $site, public ?string $redirectHost) {}
}
