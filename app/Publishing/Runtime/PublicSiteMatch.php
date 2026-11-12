<?php

namespace App\Publishing\Runtime;

use App\Models\Site;

/**
 * A resolved public address: the Site, the base path of this address ('/{number}' on the public
 * domain, '' on a custom domain) and, when the request did not use the Site's primary address,
 * the absolute primary base URL to 301-redirect to (null = serve here).
 */
final readonly class PublicSiteMatch
{
    public function __construct(public Site $site, public string $basePath, public ?string $redirectBase) {}
}
