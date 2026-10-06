<?php

namespace App\Domains;

use App\Models\Site;
use App\Models\SiteDomain;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adding and removing custom hostnames of a Site. Log events carry public IDs and the hostname
 * only, never tokens.
 */
final class SiteDomains
{
    public function add(Site $site, string $hostname): SiteDomain
    {
        if (SiteDomain::query()->where('hostname', $hostname)->exists()) {
            throw $this->taken($site, $hostname);
        }

        try {
            $domain = new SiteDomain;
            $domain->forceFill([
                'site_id' => $site->id,
                'hostname' => $hostname,
                'verification_token' => SiteDomain::newVerificationToken(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw $this->taken($site, $hostname);
        }

        Log::info('custom_domain.added', $this->context($site, $domain));

        return $domain;
    }

    /**
     * Removing a primary domain makes the Landflow subdomain primary again; the subdomain itself is
     * never removed.
     */
    public function remove(Site $site, SiteDomain $domain): void
    {
        DB::transaction(fn () => $domain->delete());

        Log::info('custom_domain.removed', $this->context($site, $domain) + ['was_primary' => $domain->is_primary]);
    }

    /**
     * @return array<string, string>
     */
    public function context(Site $site, SiteDomain $domain): array
    {
        return ['site' => $site->public_id, 'domain' => $domain->public_id, 'hostname' => $domain->hostname];
    }

    private function taken(Site $site, string $hostname): ValidationException
    {
        $ownSite = SiteDomain::query()->where('hostname', $hostname)->where('site_id', $site->id)->exists();

        return ValidationException::withMessages([
            'hostname' => $ownSite
                ? 'Этот домен уже добавлен к сайту.'
                : 'Этот домен уже подключён к другому сайту Landflow.',
        ]);
    }
}
