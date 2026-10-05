<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublishedVersionStatus;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
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

class RestoreVersionTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private PublishedVersion $v1;

    private PublishedVersion $v2;

    private Form $laterForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->home->forceFill(['seo_title' => 'SEO v1'])->save();
        $this->v1 = $this->publish();

        // Draft v2: new heading, price, SEO, field label, an extra Page, Popup and Form with a lead.
        $this->hero()->forceFill(['state_json' => [...$this->hero()->state_json, 'title' => 'Заголовок v2']])->save();
        $this->offer->forceFill(['price_minor' => 230_000_000])->save();
        $this->home->forceFill(['seo_title' => 'SEO v2'])->save();
        $this->form->fields()->where('key', 'name')->sole()->update(['label' => 'Имя v2']);
        $promo = Page::factory()->for($this->site)->create(['slug' => 'promo', 'title' => 'Акции', 'sort_order' => 1]);
        $this->place('cta', ['title' => 'Акция'], page: $promo);
        Popup::factory()->for($this->site)->create(['name' => 'Новый попап']);
        $this->laterForm = Form::factory()->for($this->site)->withLeadFields()->create(['name' => 'Форма v2']);
        Submission::factory()->create(['form_id' => $this->laterForm->id]);
        $this->site->forceFill(['form_security' => ['ip_limit' => 7]])->save();
        $this->v2 = $this->publish();
    }

    public function test_restore_copies_the_version_into_the_draft_and_leaves_production_alone(): void
    {
        $heroId = $this->hero()->public_id;
        $publications = Publication::query()->count();

        $this->restore($this->owner, $this->v1)
            ->assertRedirect(route('sites.publishing.show', $this->site))
            ->assertInertiaFlash('toast.type', 'success');

        // Draft is v1 again, with the same public IDs.
        $this->assertSame('Заголовок v1', $this->hero()->state_json['title']);
        $this->assertSame($heroId, $this->hero()->public_id);
        $this->assertSame(210_000_000, $this->offer->fresh()?->price_minor);
        $this->assertSame('SEO v1', $this->home->fresh()?->seo_title);
        $this->assertSame('Имя', $this->form->fields()->where('key', 'name')->sole()->label);
        $this->assertSame(['home'], $this->site->pages()->pluck('slug')->all());
        $this->assertSame(2, BlockInstance::query()->count());
        $this->assertFalse(Popup::query()->where('name', 'Новый попап')->exists());

        // Operational data is untouched: the later Form is only switched off and keeps its lead.
        $this->assertFalse($this->laterForm->fresh()?->status);
        $this->assertSame(1, Submission::query()->where('form_id', $this->laterForm->id)->count());
        $this->assertSame(['ip_limit' => 7], $this->site->fresh()?->form_security);
        $this->assertSame('dealer', $this->site->fresh()?->subdomain);

        // Production is still v2; nothing was published or activated.
        $this->assertSame($this->v2->id, $this->site->fresh()?->active_published_version_id);
        $this->assertSame(2, PublishedVersion::query()->count());
        $this->assertSame($publications, Publication::query()->count());
        $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertSee('Заголовок v2')->assertDontSee('Заголовок v1');

        // Preview shows the restored Draft.
        $this->as($this->owner)->get(route('sites.preview', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.state.title', 'Заголовок v1'));
    }

    public function test_publishing_the_restored_draft_creates_a_new_version_with_the_old_content(): void
    {
        $this->restore($this->owner, $this->v1);

        $v3 = $this->publish();

        $this->assertSame(3, $v3->version_number);
        $this->assertSame($this->v1->manifest_hash, $v3->manifest_hash);
        $this->assertSame($v3->id, $this->site->fresh()?->active_published_version_id);
        $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertSee('Заголовок v1');
        $this->assertSame(PublishedVersionStatus::Ready, $this->v2->fresh()?->status);
    }

    public function test_restore_requires_restore_version_permission(): void
    {
        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = $this->member($role);
            $this->restore($member, $this->v1)->assertForbidden();
        }

        $this->assertSame('Заголовок v2', $this->hero()->state_json['title']);
        $this->as($this->member(WorkspaceRole::Admin))->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('can.restoreVersion', false));
    }

    public function test_foreign_workspaces_and_foreign_versions_are_404(): void
    {
        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $this->v1]))
            ->assertNotFound();

        $otherSite = Site::factory()->for($this->workspace)->create();
        $otherVersion = PublishedVersion::factory()->for($otherSite)->create();
        $this->restore($this->owner, $otherVersion)->assertNotFound();

        $this->assertSame('Заголовок v2', $this->hero()->state_json['title']);
    }

    public function test_failed_versions_cannot_be_restored(): void
    {
        $failed = PublishedVersion::factory()->for($this->site)->create(['version_number' => 9]);
        $failed->update(['status' => PublishedVersionStatus::Failed]);

        $this->restore($this->owner, $failed)->assertInertiaFlash('toast.type', 'error');

        $this->assertSame('Заголовок v2', $this->hero()->state_json['title']);
    }

    public function test_history_lists_versions_with_production_marker(): void
    {
        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.restoreVersion', true)
                ->has('versions', 2)
                ->where('versions.0.version_number', 2)
                ->where('versions.0.is_production', true)
                ->where('versions.0.status_label', 'Готова')
                ->where('versions.0.publisher', $this->owner->name)
                ->where('versions.1.version_number', 1)
                ->where('versions.1.is_production', false)
                ->missing('versions.0.draft_snapshot_json'));
    }

    private function publish(): PublishedVersion
    {
        $this->actAsMember($this->owner);
        $outcome = app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
        $this->assertTrue($outcome->succeeded());

        return $outcome->version ?? throw new \LogicException('No version.');
    }

    private function hero(): BlockInstance
    {
        return $this->home->blocks()->orderBy('sort_order')->firstOrFail();
    }

    /**
     * @return TestResponse<Response>
     */
    private function restore(User $user, PublishedVersion $version): TestResponse
    {
        return $this->as($user)->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $version]));
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
