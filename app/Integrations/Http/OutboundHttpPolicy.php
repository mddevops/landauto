<?php

namespace App\Integrations\Http;

/**
 * The single outbound policy for every customer-configured destination: Delivery and Test
 * Connection (SECURITY.md §16, §46, D-062). Adapters never check URLs themselves.
 *
 * - `https` only (`http` only when `integrations.http.allow_plain_http` is enabled);
 * - no userinfo, fragments or control characters; a host is required;
 * - every resolved address must be public: loopback, private, unique-local, link-local, cloud
 *   metadata, CGNAT, multicast, reserved, documentation, NAT64/6to4 and IPv4-mapped/compatible
 *   IPv6 ranges are refused, for IP literals and for hostnames;
 * - the request is pinned to the vetted address; redirects are never followed by the client.
 */
final class OutboundHttpPolicy
{
    private const MAX_URL_LENGTH = 2048;

    /** @var list<string> */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/96', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23',
        '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    public function __construct(private HostResolver $resolver) {}

    /**
     * @throws OutboundRequestRejected
     */
    public function check(string $url): ValidatedDestination
    {
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            throw self::unsafe();
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw self::unsafe();
        }

        $scheme = strtolower($parts['scheme']);
        $allowed = config('integrations.http.allow_plain_http') === true ? ['https', 'http'] : ['https'];

        if (! in_array($scheme, $allowed, true)) {
            throw new OutboundRequestRejected('unsafe_scheme', $scheme === 'http'
                ? 'Разрешены только адреса https://.'
                : 'Недопустимый адрес назначения.');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if ($host === '' || $port < 1) {
            throw self::unsafe();
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || preg_match('/^[0-9.]+$|^0x/i', $host) === 1) {
                throw self::unsafe();
            }

            $addresses = $this->resolver->resolve($host);

            if ($addresses === []) {
                throw new OutboundRequestRejected('dns_error', 'Не удалось найти адрес сервиса.', true);
            }
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new OutboundRequestRejected('destination_blocked', 'Адрес назначения запрещён политикой безопасности.');
            }
        }

        return new ValidatedDestination($url, $host, $port, $addresses[0]);
    }

    public static function isPublicAddress(string $ip): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inRange($binary, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function inRange(string $binary, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $networkBinary = (string) inet_pton($network);

        if (strlen($networkBinary) !== strlen($binary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);

        if (strncmp($binary, $networkBinary, $bytes) !== 0) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($binary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }

    private static function unsafe(): OutboundRequestRejected
    {
        return new OutboundRequestRejected('unsafe_url', 'Недопустимый адрес назначения.');
    }
}
