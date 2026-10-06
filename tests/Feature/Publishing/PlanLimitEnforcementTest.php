<?php

namespace Tests\Feature\Publishing;

use App\Enums\DomainSslStatus;
use App\Enums\Entitlement;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\Template;
use App\Publishing\PublishSite;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

/**
 * Cross-cutting P7-007 review: every paid capability follows the live typed entitlements of the
 * Workspace plan, an inactive plan denies everything, and a downgrade never breaks what is live.
 */
class PlanLimitEnforcementTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->plan = Plan::factory()->create();
        $this->plan->setEntitlement(Entitlement::MaxSites, 5);
        $this->plan->setEntitlement(Entitlement::MaxMembers, 10);
        $this->plan->setEntitlement(Entitlement::CustomDomain, true);
        $this->plan->setEntitlement(Entitlement::RemoveBranding, true);
        $this->workspace->plan()->associate($this->plan)->save();
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
        SiteDomain::factory()->for($this->site)->active()->create([
            'hostname' => 'dealer.ru',
            'is_primary' => true,
            'ssl_status' => DomainSslStatus::Active,
        ]);
    }

    public function test_paid_plan_unlocks_every_capability(): void
    {
        $this->visit('http://dealer.ru/')->assertOk()->assertDontSee('Создано на Landflow');
        $this->visit('http://dealer.localhost/')->assertRedirect('http://dealer.ru/');
        $this->createSite('Второй сайт')->assertSessionHasNoErrors();
    }

    public function test_inactive_plan_denies_every_entitlement_without_breaking_the_live_site(): void
    {
        $this->plan->forceFill(['is_active' => false])->save();
        $entitlements = app(WorkspaceEntitlements::class);
        $workspace = $this->workspace->refresh();

        $this->assertSame(0, $entitlements->limit($workspace, Entitlement::MaxSites));
        $this->assertSame(0, $entitlements->limit($workspace, Entitlement::MaxMembers));
        $this->assertFalse($entitlements->allows($workspace, Entitlement::CustomDomain));
        $this->assertFalse($entitlements->allows($workspace, Entitlement::RemoveBranding));

        $this->visit('http://dealer.localhost/')->assertOk()->assertSee('Создано на Landflow');
        $this->visit('http://dealer.ru/')->assertNotFound();
        $this->createSite('Второй сайт')->assertSessionHasErrors('site');
        $this->assertSame(1, Site::query()->count());
    }

    public function test_lowering_max_sites_below_active_count_keeps_sites_live_but_blocks_new_ones(): void
    {
        $this->createSite('Второй сайт')->assertSessionHasNoErrors();
        $this->plan->setEntitlement(Entitlement::MaxSites, 1);

        $this->createSite('Третий сайт')->assertSessionHasErrors('site');
        $this->assertSame(2, Site::query()->count());
        $this->visit('http://dealer.ru/')->assertOk();
    }

    /**
     * @return TestResponse<Response>
     */
    private function createSite(string $name): TestResponse
    {
        return $this->actingAs($this->owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id])
            ->post(route('sites.store'), ['name' => $name, 'template' => Template::factory()->create()->public_id]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $url): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get($url));
    }
}
