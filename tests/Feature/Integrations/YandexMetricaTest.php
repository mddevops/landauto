<?php

namespace Tests\Feature\Integrations;

use App\Enums\WorkspaceRole;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteAnalyticsSettings;
use App\Models\User;
use App\Publishing\PublishSite;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class YandexMetricaTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private const SETTINGS = [
        'enabled' => true,
        'counter_id' => '98765432',
        'clickmap' => true,
        'track_links' => false,
        'accurate_track_bounce' => true,
        'webvisor' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
    }

    public function test_owner_saves_draft_settings_and_the_integrations_page_shows_them(): void
    {
        $this->save($this->owner, self::SETTINGS)
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $settings = $this->site->analyticsSettings()->firstOrFail();
        $this->assertTrue($settings->yandex_metrica_enabled);
        $this->assertSame('98765432', $settings->yandex_metrica_counter_id);
        $this->assertFalse($settings->track_links);
        $this->assertFalse($settings->webvisor_enabled);

        $this->as($this->owner)->get(route('sites.integrations.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/integrations')
                ->where('analytics', self::SETTINGS));
    }

    public function test_defaults_keep_webvisor_off_and_metrica_disabled(): void
    {
        $this->as($this->owner)->get(route('sites.integrations.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('analytics', [
                'enabled' => false,
                'counter_id' => null,
                'clickmap' => true,
                'track_links' => true,
                'accurate_track_bounce' => true,
                'webvisor' => false,
            ]));
    }

    public function test_counter_is_required_when_enabled_and_must_be_digits(): void
    {
        foreach ([null, '', '12ab5678', '0123456', '123', '1"onload="x', '<script>', str_repeat('9', 17)] as $counter) {
            $this->save($this->owner, ['counter_id' => $counter] + self::SETTINGS)->assertSessionHasErrors('counter_id');
        }

        $this->save($this->owner, ['enabled' => false, 'counter_id' => null] + self::SETTINGS)->assertSessionHasNoErrors();
        $this->assertNull(SiteAnalyticsSettings::query()->firstOrFail()->yandex_metrica_counter_id);
    }

    public function test_only_integration_managers_change_settings(): void
    {
        $admin = $this->member(WorkspaceRole::Admin);
        $this->save($admin, self::SETTINGS)->assertSessionHasNoErrors();

        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $this->save($this->member($role), ['counter_id' => '11112222'] + self::SETTINGS)->assertForbidden();
        }

        $this->assertSame('98765432', SiteAnalyticsSettings::query()->firstOrFail()->yandex_metrica_counter_id);

        $foreign = Site::factory()->create();
        $this->save($this->owner, self::SETTINGS, $foreign)->assertNotFound();
        $this->assertSame(1, SiteAnalyticsSettings::query()->count());
    }

    public function test_loader_reaches_visitors_only_after_publish(): void
    {
        $this->publish();
        $this->save($this->owner, self::SETTINGS)->assertSessionHasNoErrors();

        $this->visit('/')->assertOk()->assertDontSee('mc.yandex.ru', false);

        $this->publish();

        $html = (string) $this->visit('/')->assertOk()->getContent();
        $this->assertStringContainsString('"https://mc.yandex.ru/metrika/tag.js", "ym"', $html);
        $this->assertStringContainsString('ym(98765432, "init", {"clickmap":true,"trackLinks":false,"accurateTrackBounce":true,"webvisor":false});', $html);
        $this->assertStringContainsString('<img src="https://mc.yandex.ru/watch/98765432"', $html);
        $this->assertStringContainsString('"analytics":{"metrica":"98765432"}', $html);
        $this->assertStringNotContainsString('userParams', $html);
        $this->assertStringNotContainsString('UserID', $html);

        $version = PublishedVersion::query()->latest('id')->firstOrFail();
        $this->assertSame([
            'yandex_metrica' => ['counter_id' => '98765432', 'clickmap' => true, 'track_links' => false, 'accurate_track_bounce' => true, 'webvisor' => false],
        ], $version->public_manifest_json['analytics']);
    }

    public function test_disabling_metrica_needs_publish_and_disabled_sites_keep_their_manifest_shape(): void
    {
        $this->save($this->owner, self::SETTINGS);
        $this->publish();
        $this->visit('/')->assertSee('mc.yandex.ru', false);

        $this->save($this->owner, ['enabled' => false] + self::SETTINGS)->assertSessionHasNoErrors();
        $this->visit('/')->assertSee('mc.yandex.ru', false);

        $this->publish();
        $this->visit('/')->assertDontSee('mc.yandex.ru', false)->assertSee('"analytics":{"metrica":null}', false);
        $this->assertArrayNotHasKey('analytics', PublishedVersion::query()->latest('id')->firstOrFail()->public_manifest_json);
    }

    public function test_authenticated_preview_never_loads_metrica(): void
    {
        $this->save($this->owner, self::SETTINGS);
        $this->publish();

        $this->as($this->owner)->get(route('sites.preview', $this->site))
            ->assertOk()
            ->assertDontSee('mc.yandex.ru', false)
            ->assertDontSee('98765432', false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function save(User $user, array $data, ?Site $site = null): TestResponse
    {
        return $this->as($user)->put(route('sites.analytics.update', $site ?? $this->site), $data);
    }

    private function publish(): void
    {
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://dealer.localhost'.$path));
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }
}
