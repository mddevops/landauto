<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublicationStatus;
use App\Enums\PublishedVersionStatus;
use App\Enums\PublishFailure;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishOutcome;
use App\Publishing\PublishSite;
use App\Publishing\Rendering\PageRenderer;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Support\FakePageRenderer;
use Tests\TestCase;

class PublishSiteTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    private FakePageRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $renderer = app(PageRenderer::class);
        assert($renderer instanceof FakePageRenderer);
        $this->renderer = $renderer;
    }

    public function test_first_publish_activates_version_one(): void
    {
        $draftBefore = $this->hero()->state_json;

        $this->publishAs($this->owner)->assertRedirect(route('sites.publishing.show', $this->site));

        $site = $this->site->fresh();
        $version = $site?->activePublishedVersion;
        $this->assertNotNull($version);
        $this->assertSame(1, $version->version_number);
        $this->assertSame(PublishedVersionStatus::Ready, $version->status);
        $this->assertNotNull($version->ready_at);
        $this->assertSame($this->owner->id, $version->created_by);
        $this->assertStringContainsString('Заголовок v1', $version->pages()->firstOrFail()->rendered_html);

        $publication = Publication::query()->sole();
        $this->assertSame(PublicationStatus::Succeeded, $publication->status);
        $this->assertSame($version->id, $publication->published_version_id);
        $this->assertSame($this->owner->id, $publication->actor_user_id);
        $this->assertNotNull($publication->completed_at);

        $this->assertSame($draftBefore, $this->hero()->state_json);
    }

    public function test_second_publish_activates_a_new_version_and_keeps_the_old_one(): void
    {
        $this->publish();
        $this->editDraft('Заголовок v2', 230_000_000);

        $outcome = $this->publish();

        $this->assertTrue($outcome->succeeded());
        $versions = PublishedVersion::query()->orderBy('version_number')->get();
        $this->assertSame([1, 2], $versions->pluck('version_number')->all());
        $this->assertSame($versions[1]->id, $this->site->fresh()?->active_published_version_id);
        $this->assertSame(PublishedVersionStatus::Ready, $versions[0]->status);
        $this->assertStringContainsString('Заголовок v1', $versions[0]->pages()->firstOrFail()->rendered_html);
        $this->assertStringContainsString('Заголовок v2', $versions[1]->pages()->firstOrFail()->rendered_html);
        $this->assertSame(210_000_000, $versions[0]->public_manifest_json['offer_prices'][$this->offer->public_id]['price_minor']);
        $this->assertSame(230_000_000, $versions[1]->public_manifest_json['offer_prices'][$this->offer->public_id]['price_minor']);
    }

    public function test_production_stays_on_the_old_version_until_activation(): void
    {
        $v1 = $this->publish()->version;
        $this->editDraft('Заголовок v2', 230_000_000);
        $observed = null;
        $this->renderer->onRender = function () use (&$observed): void {
            $observed = $this->site->fresh()?->active_published_version_id;
        };

        $v2 = $this->publish()->version;

        $this->assertSame($v1?->id, $observed);
        $this->assertSame($v2?->id, $this->site->fresh()?->active_published_version_id);
    }

    public function test_validation_failure_creates_no_version_and_keeps_production(): void
    {
        $v1 = $this->publish()->version;
        $this->home->forceFill(['is_home' => false])->save();

        $outcome = $this->publish();

        $this->assertFalse($outcome->succeeded());
        $this->assertSame(PublicationStatus::Failed, $outcome->publication->status);
        $this->assertSame(PublishFailure::ValidationFailed->value, $outcome->publication->safe_error_code);
        $this->assertSame(['errors' => ['home_page_missing']], $outcome->publication->metadata_json);
        $this->assertContains('home_page_missing', $outcome->validation?->errorCodes() ?? []);
        $this->assertSame(1, PublishedVersion::query()->count());
        $this->assertSame($v1?->id, $this->site->fresh()?->active_published_version_id);
        $this->assertCount(1, $this->renderer->calls);
    }

    public function test_render_failure_fails_the_version_and_keeps_production(): void
    {
        $v1 = $this->publish()->version;
        $this->editDraft('Заголовок v2', 230_000_000);
        $this->renderer->fail = true;

        $outcome = $this->publish();

        $this->assertSame(PublishFailure::RenderFailed->value, $outcome->publication->safe_error_code);
        $failed = PublishedVersion::query()->where('version_number', 2)->sole();
        $this->assertSame(PublishedVersionStatus::Failed, $failed->status);
        $this->assertSame(0, $failed->pages()->count());
        $this->assertSame($failed->id, $outcome->publication->published_version_id);
        $this->assertSame($v1?->id, $this->site->fresh()?->active_published_version_id);
        $this->assertSame('Заголовок v2', $this->hero()->state_json['title']);

        $this->renderer->fail = false;
        $v3 = $this->publish()->version;
        $this->assertSame(3, $v3?->version_number);
        $this->assertSame($v3?->id, $this->site->fresh()?->active_published_version_id);
    }

    public function test_a_publish_while_another_is_running_is_rejected(): void
    {
        $competing = null;
        $this->renderer->onRender = function () use (&$competing): void {
            $competing = $this->publish();
        };

        $outcome = $this->publish();

        $this->assertTrue($outcome->succeeded());
        $this->assertNotNull($competing);
        $this->assertSame(PublicationStatus::Failed, $competing->publication->status);
        $this->assertSame(PublishFailure::Conflict->value, $competing->publication->safe_error_code);
        $this->assertSame(1, PublishedVersion::query()->count());
        $this->assertSame($outcome->version?->id, $this->site->fresh()?->active_published_version_id);
    }

    public function test_an_abandoned_attempt_cannot_activate_after_a_newer_publish(): void
    {
        $newer = null;
        $this->renderer->onRender = function () use (&$newer): void {
            $this->travel(20)->minutes();
            $this->editDraft('Заголовок v2', 230_000_000);
            $newer = $this->publish();
        };

        $stuck = $this->publish();

        $this->assertNotNull($newer);
        $this->assertTrue($newer->succeeded());
        $this->assertSame(2, $newer->version?->version_number);
        $this->assertFalse($stuck->succeeded());
        $this->assertSame(PublishFailure::Internal->value, $stuck->publication->safe_error_code);
        $this->assertSame(['abandoned' => true], $stuck->publication->metadata_json);
        $this->assertSame(PublishedVersionStatus::Failed, PublishedVersion::query()->where('version_number', 1)->sole()->status);
        $this->assertSame($newer->version?->id, $this->site->fresh()?->active_published_version_id);
    }

    public function test_owner_and_admin_may_publish_but_designer_and_content_editor_may_not(): void
    {
        $admin = $this->member(WorkspaceRole::Admin);
        $this->publishAs($admin)->assertRedirect();
        $this->assertSame(1, PublishedVersion::query()->count());

        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = $this->member($role);
            $this->publishAs($member)->assertForbidden();
            $this->as($member)->get(route('sites.publishing.show', $this->site))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('sites/publishing')
                    ->where('can.publish', false)
                    ->where('production.version_number', 1));
        }

        $this->assertSame(1, Publication::query()->count());
    }

    public function test_a_foreign_workspace_cannot_see_or_publish_the_site(): void
    {
        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);

        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->post(route('sites.publishing.store', $this->site))
            ->assertNotFound();
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->get(route('sites.publishing.show', $this->site))
            ->assertNotFound();

        $this->assertSame(0, Publication::query()->count());
    }

    public function test_publishing_page_shows_production_last_attempt_and_check(): void
    {
        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/publishing')
                ->where('production', null)
                ->where('lastAttempt', null)
                ->where('check.errors', [])
                ->where('can.publish', true));

        $this->publish();
        $this->home->forceFill(['is_home' => false])->save();
        $this->publish();

        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('production.version_number', 1)
                ->where('production.publisher', $this->owner->name)
                ->where('lastAttempt.status', 'failed')
                ->where('lastAttempt.error', PublishFailure::ValidationFailed->summary())
                ->where('check.errors.0.code', 'home_page_missing')
                ->missing('production.id'));
    }

    private function publish(): PublishOutcome
    {
        $this->actAsMember($this->owner);

        return app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
    }

    /**
     * @return TestResponse<Response>
     */
    private function publishAs(User $user): TestResponse
    {
        return $this->as($user)->post(route('sites.publishing.store', $this->site));
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }

    private function hero(): BlockInstance
    {
        return $this->home->blocks()->orderBy('sort_order')->firstOrFail();
    }

    private function editDraft(string $title, int $price): void
    {
        $hero = $this->hero();
        $hero->forceFill(['state_json' => [...$hero->state_json, 'title' => $title]])->save();
        $this->offer->forceFill(['price_minor' => $price])->save();
    }
}
