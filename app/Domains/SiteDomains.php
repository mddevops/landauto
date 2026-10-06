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
     * Only a fully active domain (ownership, routing, SSL) can become primary; at most one per Site.
     */
    public function makePrimary(Site $site, SiteDomain $domain): void
    {
        if (! $domain->isActive()) {
            throw ValidationException::withMessages([
                'domain' => 'Основным можно сделать только активный домен: подтверждённый, направленный на Landflow и с SSL-сертификатом.',
            ]);
        }

        DB::transaction(function () use ($site, $domain): void {
            Site::query()->whereKey($site->id)->lockForUpdate()->first();
            $site->domains()->whereKeyNot($domain->id)->where('is_primary', true)->update(['is_primary' => false, 'updated_at' => now()]);
            $domain->forceFill(['is_primary' => true])->save();
        });

        Log::info('custom_domain.primary_set', $this->context($site, $domain));
    }

    /**
     * Makes the Landflow subdomain the primary address again.
     */
    public function resetPrimary(Site $site): void
    {
        $site->domains()->where('is_primary', true)->update(['is_primary' => false, 'updated_at' => now()]);

        Log::info('custom_domain.primary_reset', ['site' => $site->public_id]);
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
