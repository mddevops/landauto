<?php

namespace Tests\Feature\Publishing;

use App\Enums\Entitlement;
use App\Models\Plan;
use App\Models\PublishedPage;
use App\Models\Site;
use App\Publishing\PublishSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class PublishedBrandingTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private const FOOTER = '<footer data-testid="landflow-branding"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    public function test_sites_without_remove_branding_show_the_landflow_footer(): void
    {
        $this->visit()
            ->assertOk()
            ->assertSee(self::FOOTER, false)
            ->assertSee('Создано на Landflow');
    }

    public function test_branding_follows_the_live_entitlement_without_republishing(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::RemoveBranding, true);
        $versionBefore = $this->site->refresh()->active_published_version_id;

        $this->workspace->plan()->associate($plan)->save();
        $this->visit()->assertOk()->assertDontSee(self::FOOTER, false)->assertDontSee('Создано на Landflow');

        $plan->setEntitlement(Entitlement::RemoveBranding, false);
        $this->visit()->assertOk()->assertSee(self::FOOTER, false);

        $this->workspace->plan()->associate(Plan::factory()->create())->save();
        $this->visit()->assertOk()->assertSee(self::FOOTER, false);

        $this->assertSame($versionBefore, $this->site->refresh()->active_published_version_id);
    }

    public function test_branding_is_never_frozen_into_stored_artifacts(): void
    {
        $page = PublishedPage::query()->firstOrFail();

        $this->assertStringNotContainsString('Landflow', (string) $page->rendered_html);
        $this->assertStringNotContainsString('branding', (string) $page->getRawOriginal('hydration_json'));
        $this->assertArrayNotHasKey('branding', $this->site->refresh()->activePublishedVersion?->public_manifest_json ?? []);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'));
    }
}
