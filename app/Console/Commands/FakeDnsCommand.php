<?php

namespace App\Console\Commands;

use App\Domains\CustomHostname;
use App\Domains\Dns\DnsResolver;
use App\Domains\Dns\FakeDnsResolver;
use Illuminate\Console\Command;

/**
 * E2E helper: sets answers of the fake DNS resolver. Refuses to run unless the fake driver is
 * active (testing / e2e environments only).
 */
class FakeDnsCommand extends Command
{
    protected $signature = 'domains:fake-dns
        {name : DNS name, e.g. www.dealer.test or _landflow-verification.www.dealer.test}
        {--txt=* : TXT values}
        {--a=* : IPv4 addresses}
        {--aaaa=* : IPv6 addresses}
        {--cname= : CNAME target}
        {--clear : Remove every record of the name}';

    protected $description = 'Set fake DNS records (testing/e2e only)';

    public function handle(DnsResolver $resolver): int
    {
        if (! $resolver instanceof FakeDnsResolver) {
            $this->error('The fake DNS driver is not active.');

            return self::FAILURE;
        }

        $name = CustomHostname::normalize((string) $this->argument('name'));

        if ($this->option('clear')) {
            $resolver->forget($name);

            return self::SUCCESS;
        }

        foreach (['txt', 'a', 'aaaa'] as $type) {
            /** @var list<string> $values */
            $values = $this->option($type);

            if ($values !== []) {
                $resolver->set($type, $name, $values);
            }
        }

        if (is_string($this->option('cname'))) {
            $resolver->set('cname', $name, [CustomHostname::normalize($this->option('cname'))]);
        }

        return self::SUCCESS;
    }
}
