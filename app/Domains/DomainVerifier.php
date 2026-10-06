<?php

namespace App\Domains;

use App\Domains\Dns\DnsResolver;
use App\Enums\DomainRoutingStatus;
use App\Enums\DomainVerificationStatus;
use App\Models\SiteDomain;
use Illuminate\Support\Facades\Log;

/**
 * DNS checks of a custom hostname (P7-002, D-111). Ownership needs the exact TXT value and stays
 * verified once proven; routing is re-evaluated on every check: a CNAME chain reaching the
 * configured target, or A/AAAA answers that all point to the configured ingress. Only DNS is
 * queried, never the customer's server.
 */
final class DomainVerifier
{
    private const MAX_CNAME_HOPS = 5;

    /** Safe Russian messages per error code; nothing from DNS answers is echoed. */
    private const MESSAGES = [
        'txt_missing' => 'TXT-запись для подтверждения не найдена. Изменения DNS могут применяться до 24 часов.',
        'txt_mismatch' => 'TXT-запись найдена, но её значение не совпадает с указанным.',
        'routing_missing' => 'Запись A или CNAME для домена не найдена.',
        'routing_mismatch' => 'Домен направлен не на Landflow: проверьте записи A, AAAA или CNAME.',
        'ingress_not_configured' => 'Адрес подключения доменов ещё не настроен в Landflow.',
    ];

    public function __construct(private DnsResolver $dns) {}

    public function check(SiteDomain $domain): SiteDomain
    {
        $wasVerified = $domain->verification_status === DomainVerificationStatus::Verified;
        $wasRouted = $domain->routing_status === DomainRoutingStatus::Verified;
        $errors = [];

        if (! $wasVerified) {
            $ownership = $this->ownership($domain);
            $domain->verification_status = $ownership === null ? DomainVerificationStatus::Verified
                : ($ownership === 'txt_mismatch' ? DomainVerificationStatus::Failed : DomainVerificationStatus::Pending);
            $domain->verified_at = $ownership === null ? now() : null;
            $errors[] = $ownership;
        }

        $routing = $this->routing($domain->hostname);
        $domain->routing_status = match ($routing) {
            null => DomainRoutingStatus::Verified,
            'routing_mismatch' => DomainRoutingStatus::Misconfigured,
            default => DomainRoutingStatus::Pending,
        };
        $domain->routing_verified_at = $routing === null ? ($domain->routing_verified_at ?? now()) : null;
        $errors[] = $routing;

        $code = collect($errors)->filter()->first();
        $domain->last_checked_at = now();
        $domain->last_error_code = $code;
        $domain->last_error_message_safe = $code === null ? null : self::MESSAGES[$code];
        $domain->save();

        $context = ['site' => $domain->site->public_id, 'domain' => $domain->public_id, 'hostname' => $domain->hostname];

        if (! $wasVerified && $domain->verification_status === DomainVerificationStatus::Verified) {
            Log::info('custom_domain.ownership_verified', $context);
        }

        if (! $wasRouted && $domain->routing_status === DomainRoutingStatus::Verified) {
            Log::info('custom_domain.routing_verified', $context);
        }

        return $domain;
    }

    private function ownership(SiteDomain $domain): ?string
    {
        $values = array_map(fn (string $value): string => trim($value, " \t\""), $this->dns->txt($domain->verificationRecordName()));

        if ($values === []) {
            return 'txt_missing';
        }

        foreach ($values as $value) {
            if (hash_equals($domain->verificationRecordValue(), $value)) {
                return null;
            }
        }

        return 'txt_mismatch';
    }

    private function routing(string $hostname): ?string
    {
        $target = (string) config('domains.cname_target');
        $ipv4 = (string) config('domains.ipv4');
        $ipv6 = (string) config('domains.ipv6');

        if ($target === '' && $ipv4 === '') {
            return 'ingress_not_configured';
        }

        $name = $hostname;

        for ($hop = 0; $hop < self::MAX_CNAME_HOPS; $hop++) {
            $next = $this->dns->cname($name);

            if ($next === null) {
                break;
            }

            if ($target !== '' && $next === $target) {
                return null;
            }

            $name = $next;
        }

        $a = array_map(self::ip(...), $this->dns->a($hostname));
        $aaaa = array_map(self::ip(...), $this->dns->aaaa($hostname));

        if ($a === [] && $aaaa === [] && $name === $hostname) {
            return 'routing_missing';
        }

        $ipv4Ok = $ipv4 !== '' && $a !== [] && array_diff($a, [self::ip($ipv4)]) === [];
        $ipv6Ok = $aaaa === [] || ($ipv6 !== '' && array_diff($aaaa, [self::ip($ipv6)]) === []);

        return $ipv4Ok && $ipv6Ok ? null : 'routing_mismatch';
    }

    private static function ip(string $address): string
    {
        $packed = @inet_pton($address);

        return $packed === false ? strtolower($address) : (string) inet_ntop($packed);
    }
}
