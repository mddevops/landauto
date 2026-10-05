<?php

namespace Tests\Feature\Integrations;

use App\Integrations\Http\HostResolver;
use App\Integrations\Http\OutboundHttpPolicy;
use App\Integrations\Http\OutboundRequestRejected;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class OutboundHttpPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new FakeHostResolver([
            'crm.example.com' => ['93.184.216.34'],
            'v6.example.com' => ['2606:4700:4700::1111'],
            'localhost' => ['127.0.0.1'],
            'internal.example.com' => ['10.1.2.3'],
            'rebind.example.com' => ['93.184.216.34', '192.168.0.10'],
            'metadata.example.com' => ['169.254.169.254'],
        ]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejected(): array
    {
        return [
            'plain http' => ['http://crm.example.com/leads', 'unsafe_scheme'],
            'ftp' => ['ftp://crm.example.com/', 'unsafe_scheme'],
            'file' => ['file:///etc/passwd', 'unsafe_url'],
            'gopher' => ['gopher://crm.example.com/', 'unsafe_scheme'],
            'userinfo' => ['https://user:pass@crm.example.com/', 'unsafe_url'],
            'fragment' => ['https://crm.example.com/#x', 'unsafe_url'],
            'whitespace' => ["https://crm.example.com/\nHost: evil", 'unsafe_url'],
            'loopback literal' => ['https://127.0.0.1/', 'destination_blocked'],
            'localhost' => ['https://localhost/', 'destination_blocked'],
            'private 10' => ['https://10.0.0.5/', 'destination_blocked'],
            'private 172' => ['https://172.16.1.1/', 'destination_blocked'],
            'private 192' => ['https://192.168.1.1/', 'destination_blocked'],
            'private hostname' => ['https://internal.example.com/', 'destination_blocked'],
            'metadata literal' => ['https://169.254.169.254/latest/meta-data/', 'destination_blocked'],
            'metadata hostname' => ['https://metadata.example.com/', 'destination_blocked'],
            'cgnat' => ['https://100.64.0.1/', 'destination_blocked'],
            'multicast' => ['https://224.0.0.1/', 'destination_blocked'],
            'unspecified' => ['https://0.0.0.0/', 'destination_blocked'],
            'broadcast' => ['https://255.255.255.255/', 'destination_blocked'],
            'ipv6 loopback' => ['https://[::1]/', 'destination_blocked'],
            'ipv6 mapped' => ['https://[::ffff:127.0.0.1]/', 'destination_blocked'],
            'ipv6 mapped public' => ['https://[::ffff:93.184.216.34]/', 'destination_blocked'],
            'ipv6 unique local' => ['https://[fd00:ec2::254]/', 'destination_blocked'],
            'ipv6 link local' => ['https://[fe80::1]/', 'destination_blocked'],
            'ipv6 multicast' => ['https://[ff02::1]/', 'destination_blocked'],
            'nat64' => ['https://[64:ff9b::7f00:1]/', 'destination_blocked'],
            '6to4' => ['https://[2002:7f00:1::]/', 'destination_blocked'],
            'decimal ip' => ['https://2130706433/', 'unsafe_url'],
            'hex ip' => ['https://0x7f000001/', 'unsafe_url'],
            'short ip' => ['https://127.1/', 'unsafe_url'],
            'dns rebinding' => ['https://rebind.example.com/', 'destination_blocked'],
            'port zero' => ['https://crm.example.com:0/', 'unsafe_url'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_unsafe_destinations_are_rejected(string $url, string $code): void
    {
        try {
            app(OutboundHttpPolicy::class)->check($url);
            $this->fail("Expected {$url} to be rejected.");
        } catch (OutboundRequestRejected $rejected) {
            $this->assertSame($code, $rejected->errorCode);
            $this->assertFalse($rejected->transient);
        }
    }

    public function test_unresolvable_hosts_are_a_transient_dns_failure(): void
    {
        try {
            app(OutboundHttpPolicy::class)->check('https://unknown.example.com/');
            $this->fail('Expected a DNS failure.');
        } catch (OutboundRequestRejected $rejected) {
            $this->assertSame('dns_error', $rejected->errorCode);
            $this->assertTrue($rejected->transient);
        }
    }

    public function test_public_destinations_are_pinned_to_the_vetted_address(): void
    {
        $policy = app(OutboundHttpPolicy::class);

        $this->assertSame('crm.example.com:443:93.184.216.34', $policy->check('https://crm.example.com/leads?x=1')->resolveEntry());
        $this->assertSame('crm.example.com:8443:93.184.216.34', $policy->check('https://CRM.example.com:8443/')->resolveEntry());
        $this->assertSame('v6.example.com:443:[2606:4700:4700::1111]', $policy->check('https://v6.example.com/')->resolveEntry());
        $this->assertSame('93.184.216.34', $policy->check('https://93.184.216.34/hook')->ip);
    }

    public function test_plain_http_needs_explicit_config_and_never_unlocks_private_addresses(): void
    {
        config(['integrations.http.allow_plain_http' => true]);
        $policy = app(OutboundHttpPolicy::class);

        $this->assertSame(80, $policy->check('http://crm.example.com/')->port);

        $this->expectException(OutboundRequestRejected::class);
        $policy->check('http://127.0.0.1/');
    }
}
