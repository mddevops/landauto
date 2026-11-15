<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteComponent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteComponentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Site $site;

    private Page $home;

    private Page $about;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->user, WorkspaceRole::Designer);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->home = Page::factory()->for($this->site)->home()->create();
        $this->about = Page::factory()->for($this->site)->create(['title' => 'О компании', 'slug' => 'about']);
    }

    public function test_header_is_shared_across_pages_and_cannot_be_added_twice(): void
    {
        $this->as()->post(route('sites.blocks.store', [$this->site, $this->home]), ['block' => 'header'])
            ->assertSessionHasNoErrors();

        $component = SiteComponent::query()->sole();
        $this->assertSame(0, $this->home->blocks()->count());
        $this->assertSame(0, $this->about->blocks()->count());

        $this->as()->get(route('sites.designer', ['site' => $this->site, 'page' => $this->about->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 1)
                ->where('blocks.0.public_id', $component->public_id)
                ->where('blocks.0.slug', 'header')
                ->where('blocks.0.is_component', true));

        $this->as()->post(route('sites.blocks.store', [$this->site, $this->about]), ['block' => 'header'])
            ->assertSessionHasErrors('block');
        $this->assertSame(1, SiteComponent::query()->count());

        $state = [...$component->state_json, 'logo_text' => 'Авто Север'];
        $this->as()->patch(route('sites.blocks.state', [$this->site, $component]), [
            'page' => $this->about->public_id,
            'state' => $state,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Авто Север', $component->fresh()?->state_json['logo_text']);
        $this->as()->get(route('sites.designer', ['site' => $this->site, 'page' => $this->home->public_id]))
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.state.logo_text', 'Авто Север'));

        $this->as()->post(route('sites.blocks.duplicate', [$this->site, $component]))
            ->assertSessionHasErrors('block');

        $later = Page::factory()->for($this->site)->create(['title' => 'Контакты', 'slug' => 'contacts']);
        $this->as()->get(route('sites.designer', ['site' => $this->site, 'page' => $later->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 1)
                ->where('blocks.0.public_id', $component->public_id));
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
