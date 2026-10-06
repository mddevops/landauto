<?php

namespace App\Http\Controllers\Domains;

use App\Domains\CustomDomainAccess;
use App\Domains\DomainSsl;
use App\Domains\DomainVerifier;
use App\Domains\SiteDomains;
use App\Enums\DomainSslStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteDomainRequest;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Домены» of a Site: `manage_domains` to see the page, plus the `custom_domain` entitlement to
 * change anything. DNS instructions come from configuration; no certificate data is ever shown.
 */
class SiteDomainController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private CustomDomainAccess $access,
        private SiteDomains $domains,
    ) {}

    public function index(Request $request, Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('manageDomains', $site);

        return Inertia::render('sites/domains', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'landflowAddress' => PublicSiteResolver::url($site),
            'domains' => $site->domains()
                ->orderByDesc('is_primary')
                ->orderBy('hostname')
                ->get()
                ->map(fn (SiteDomain $domain): array => $this->present($domain))
                ->values()
                ->all(),
            'dns' => [
                'cname_target' => config('domains.cname_target') ?: null,
                'ipv4' => config('domains.ipv4') ?: null,
                'ipv6' => config('domains.ipv6') ?: null,
            ],
            'can' => [
                'manage' => $this->access->canManage($request->user(), $site),
            ],
            'entitled' => $this->access->entitled($site),
        ]);
    }

    public function store(StoreSiteDomainRequest $request, Site $site): RedirectResponse
    {
        $this->domains->add($site, $request->string('hostname')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Домен добавлен. Настройте DNS-записи и запустите проверку.']);

        return to_route('sites.domains.index', $site);
    }

    public function check(Request $request, Site $site, SiteDomain $domain, DomainVerifier $verifier, DomainSsl $ssl): RedirectResponse
    {
        $this->authorizeDomain($request, $site, $domain);

        $verifier->check($domain);

        Inertia::flash('toast', match (true) {
            ! $domain->isDnsReady() => ['type' => 'error', 'message' => $domain->last_error_message_safe ?? 'DNS ещё не настроен.'],
            $ssl->request($domain) => ['type' => 'success', 'message' => 'DNS настроен верно. Выпускаем SSL-сертификат.'],
            default => ['type' => 'success', 'message' => 'DNS настроен верно.'],
        });

        return to_route('sites.domains.index', $site);
    }

    public function provisionSsl(Request $request, Site $site, SiteDomain $domain, DomainSsl $ssl): RedirectResponse
    {
        $this->authorizeDomain($request, $site, $domain);

        $problem = $ssl->ineligibility($domain);

        Inertia::flash('toast', match (true) {
            $problem !== null => ['type' => 'error', 'message' => $problem],
            $ssl->request($domain, manual: true) => ['type' => 'success', 'message' => 'Выпускаем SSL-сертификат. Обычно это занимает несколько минут.'],
            default => ['type' => 'error', 'message' => $domain->last_error_message_safe ?? 'Сертификат сейчас нельзя выпустить.'],
        });

        return to_route('sites.domains.index', $site);
    }

    public function destroy(Request $request, Site $site, SiteDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($request, $site, $domain);

        $this->domains->remove($site, $domain);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Домен отключён.']);

        return to_route('sites.domains.index', $site);
    }

    private function authorizeDomain(Request $request, Site $site, SiteDomain $domain): void
    {
        $this->scope->site($site);
        abort_unless($domain->site_id === $site->id, 404);
        abort_unless($this->access->canManage($request->user(), $site), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteDomain $domain): array
    {
        return [
            'public_id' => $domain->public_id,
            'hostname' => $domain->hostname,
            'state' => $domain->state(),
            'state_label' => $domain->stateLabel(),
            'verification_status' => $domain->verification_status->value,
            'routing_status' => $domain->routing_status->value,
            'ssl_status' => $domain->ssl_status->value,
            'is_primary' => $domain->is_primary,
            'ssl_expires_at' => $domain->ssl_expires_at?->toIso8601String(),
            'can_provision_ssl' => $domain->isDnsReady() && in_array($domain->ssl_status, [DomainSslStatus::Pending, DomainSslStatus::Failed], true),
            'last_checked_at' => $domain->last_checked_at?->toIso8601String(),
            'last_error' => $domain->last_error_message_safe,
            'verification' => [
                'name' => $domain->verificationRecordName(),
                'value' => $domain->verificationRecordValue(),
            ],
            'url' => PublicSiteResolver::scheme().'://'.$domain->hostname.PublicSiteResolver::portSuffix(),
        ];
    }
}
