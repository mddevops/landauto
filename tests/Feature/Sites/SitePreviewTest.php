<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SiteDesignTokens;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SitePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_previews_visible_draft_blocks_with_tokens_and_assets(): void
    {
        [$user, $workspace, $site, $home] = $this->siteFor(WorkspaceRole::Owner);
        $this->seed(OfficialBlockSeeder::class);
        $hero = BlockVersion::query()->whereRelation('definition', 'slug', 'hero')->latest('id')->firstOrFail();
        $asset = SiteAsset::factory()->for($site)->create();
        $visible = BlockInstance::factory()->for($home)->for($hero, 'version')->create([
            'sort_order' => 0,
            'state_json' => ['title' => 'Черновой заголовок', 'image' => $asset->public_id],
        ]);
        $hidden = BlockInstance::factory()->for($home)->for($hero, 'version')->create(['sort_order' => 1, 'state_json' => []]);
        $hidden->forceFill(['is_hidden' => true])->save();
        $about = Page::factory()->for($site)->create(['title' => 'О нас']);

        $this->as($user, $workspace)->get(route('sites.preview', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/preview')
                ->where('page.public_id', $home->public_id)
                ->has('blocks', 1)
                ->where('blocks.0.public_id', $visible->public_id)
                ->where('blocks.0.state.title', 'Черновой заголовок')
                ->where('design', SiteDesignTokens::DEFAULTS)
                ->where('assets.0.url', route('sites.assets.show', [$site, $asset], false))
                ->has('pages', 2)
                ->missing('blocks.0.id')
                ->missing('assets.0.path'));

        $this->as($user, $workspace)->get(route('sites.preview', ['site' => $site, 'page' => $about->public_id]))
            ->assertInertia(fn (Assert $page) => $page->where('page.title', 'О нас')->has('blocks', 0));
        $this->as($user, $workspace)->get(route('sites.designer', $site))
            ->assertInertia(fn (Assert $page) => $page->where('can.preview', true));
    }

    public function test_designer_and_admin_preview_but_designer_cannot_publish(): void
    {
        [$admin, $adminWorkspace, $adminSite] = $this->siteFor(WorkspaceRole::Admin);
        $this->as($admin, $adminWorkspace)->get(route('sites.preview', $adminSite))->assertOk();

        [$designer, $designerWorkspace, $designerSite] = $this->siteFor(WorkspaceRole::Designer);
        $this->as($designer, $designerWorkspace)->get(route('sites.preview', $designerSite))->assertOk();
        $this->as($designer, $designerWorkspace)->get(route('sites.designer', $designerSite))
            ->assertInertia(fn (Assert $page) => $page->where('can.preview', true));
        $this->assertFalse(app(WorkspaceAuthorization::class)->allowsForWorkspace($designer, $designerWorkspace, WorkspacePermission::PublishSite));
    }

    public function test_preview_requires_preview_permission_and_own_workspace(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);
        $foreignSite = Site::factory()->create();
        $foreignPage = Page::factory()->create();

        $this->as($user, $workspace)->get(route('sites.preview', $site))->assertForbidden();
        $this->as($user, $workspace)->get(route('sites.designer', $site))
            ->assertInertia(fn (Assert $page) => $page->where('can.preview', false));
        [$designer, $designerWorkspace] = $this->siteFor(WorkspaceRole::Designer);
        $this->as($designer, $designerWorkspace)->get(route('sites.preview', $foreignSite))->assertNotFound();

        [$owner, $ownerWorkspace, $ownSite] = $this->siteFor(WorkspaceRole::Owner);
        $this->as($owner, $ownerWorkspace)->get(route('sites.preview', ['site' => $ownSite, 'page' => $foreignPage->public_id]))->assertNotFound();
    }

    /**
     * @return array{User, Workspace, Site, Page}
     */
    private function siteFor(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);
        $site = Site::factory()->for($workspace)->create();
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($site)->save();

        return [$user, $workspace, $site, $home];
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
