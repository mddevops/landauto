<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_designer_creates_pages_with_unique_generated_slugs_and_opens_them(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Designer);

        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'О компании'])
            ->assertSessionHasNoErrors();
        $first = $site->pages()->where('title', 'О компании')->sole();
        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'О компании'])
            ->assertRedirect(route('sites.designer', ['site' => $site, 'page' => $site->pages()->latest('id')->first()?->public_id]));

        $this->assertSame('o-kompanii', $first->slug);
        $this->assertSame(['home', 'o-kompanii', 'o-kompanii-2'], $site->pages()->orderBy('sort_order')->pluck('slug')->all());

        $this->as($user, $workspace)->get(route('sites.designer', ['site' => $site, 'page' => $first->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.public_id', $first->public_id)
                ->has('pages', 3)
                ->where('pages.0.is_home', true)
                ->where('can.editDesign', true)
                ->missing('pages.0.id'));
    }

    public function test_slug_is_validated_and_unique_per_site(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Owner);
        Page::factory()->create(['slug' => 'contacts']);

        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'Контакты', 'slug' => 'contacts'])
            ->assertSessionHasNoErrors();
        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'Ещё', 'slug' => 'contacts'])
            ->assertSessionHasErrors(['slug' => 'Страница с таким адресом уже есть на сайте.']);
        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'Ещё', 'slug' => 'Плохой адрес'])
            ->assertSessionHasErrors('slug');
        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_page_rename_keeps_home_slug_and_delete_removes_blocks_but_never_home(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Owner);
        $home = $site->homePage()->sole();
        $page = Page::factory()->for($site)->create(['sort_order' => 1]);
        BlockInstance::factory()->for($page)->create();

        $this->as($user, $workspace)->patch(route('sites.pages.update', [$site, $home]), ['title' => 'Старт', 'slug' => 'start'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Старт', 'home'], [$home->fresh()?->title, $home->fresh()?->slug]);

        $this->as($user, $workspace)->delete(route('sites.pages.destroy', [$site, $home]))->assertSessionHasErrors('page');
        $this->as($user, $workspace)->delete(route('sites.pages.destroy', [$site, $page]))
            ->assertRedirect(route('sites.designer', $site));

        $this->assertModelMissing($page);
        $this->assertSame(0, BlockInstance::query()->count());
        $this->assertModelExists($home);
    }

    public function test_content_editor_cannot_manage_pages(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);

        $this->as($user, $workspace)->post(route('sites.pages.store', $site), ['title' => 'Новая'])->assertForbidden();
        $this->as($user, $workspace)->delete(route('sites.pages.destroy', [$site, $site->homePage()->sole()]))->assertForbidden();
    }

    public function test_foreign_site_and_page_are_not_found_before_validation(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Owner);
        $foreignSite = Site::factory()->create();
        $foreignPage = Page::factory()->for($foreignSite)->create();

        $this->as($user, $workspace)->post(route('sites.pages.store', $foreignSite), [])->assertNotFound();
        $this->as($user, $workspace)->patch(route('sites.pages.update', [$site, $foreignPage]), ['title' => 'X'])->assertNotFound();
        $this->as($user, $workspace)->delete(route('sites.pages.destroy', [$site, $foreignPage]))->assertNotFound();
        $this->as($user, $workspace)->get(route('sites.designer', ['site' => $site, 'page' => $foreignPage->public_id]))->assertNotFound();
        $this->assertModelExists($foreignPage);
    }

    /**
     * @return array{User, Workspace, Site}
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

        return [$user, $workspace, $site];
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
