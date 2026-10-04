<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteDesignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_opens_designer_with_home_page_blocks_in_order(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        [$user, $workspace] = $this->member(WorkspaceRole::ContentEditor);
        $site = Site::factory()->for($workspace)->create(['name' => 'Автосалон Север']);
        $home = Page::factory()->for($site)->home()->create(['title' => 'Главная']);
        Page::factory()->for($site)->create();
        $hero = $this->place($home, 'hero', 1, ['title' => 'Новые автомобили']);
        $header = $this->place($home, 'header', 0, []);

        $this->designer($user, $workspace, $site)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/designer')
                ->where('site', ['public_id' => $site->public_id, 'name' => 'Автосалон Север'])
                ->where('page', ['public_id' => $home->public_id, 'title' => 'Главная'])
                ->has('blocks', 2)
                ->where('blocks.0.public_id', $header->public_id)
                ->where('blocks.0.slug', 'header')
                ->where('blocks.0.name', 'Шапка')
                ->where('blocks.0.version', '1.0.0')
                ->where('blocks.0.is_hidden', false)
                ->where('blocks.0.state', [])
                ->missing('blocks.0.id')
                ->where('blocks.1.public_id', $hero->public_id)
                ->where('blocks.1.state', ['title' => 'Новые автомобили'])
                ->missing('site.id')
                ->missing('site.workspace_id'));
    }

    public function test_site_from_another_workspace_is_not_found_even_for_its_member(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Owner);
        $otherWorkspace = Workspace::factory()->create();
        $otherWorkspace->addMember($user, WorkspaceRole::Owner);
        $otherSite = Site::factory()->for($otherWorkspace)->create(['name' => 'Чужой сайт']);
        $foreignSite = Site::factory()->create(['name' => 'Посторонний сайт']);

        $this->designer($user, $workspace, $otherSite)->assertNotFound()->assertDontSee('Чужой сайт');
        $this->designer($user, $workspace, $foreignSite)->assertNotFound()->assertDontSee('Посторонний сайт');
    }

    public function test_numeric_id_and_guest_access_are_rejected(): void
    {
        [$user, $workspace] = $this->member(WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get('/sites/'.$site->id.'/designer')
            ->assertNotFound();

        auth()->logout();

        $this->get(route('sites.designer', $site))->assertRedirect(route('login'));
    }

    /**
     * @return array{User, Workspace}
     */
    private function member(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);

        return [$user, $workspace];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function place(Page $page, string $slug, int $sortOrder, array $state): BlockInstance
    {
        $version = BlockVersion::query()->whereRelation('definition', 'slug', $slug)->where('version', '1.0.0')->sole();

        return BlockInstance::factory()->for($page)->for($version, 'version')->create([
            'sort_order' => $sortOrder,
            'state_json' => $state,
        ]);
    }

    private function designer(User $user, Workspace $workspace, Site $site): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.designer', $site));
    }
}
