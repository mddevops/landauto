<?php

namespace Tests\Feature\Sites;

use App\Enums\SiteType;
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
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class SiteTypeStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->user, WorkspaceRole::Owner);
    }

    public function test_existing_sites_default_to_multi_page_and_the_type_is_immutable(): void
    {
        $this->assertTrue(Schema::hasColumn('sites', 'site_type'));
        $site = Site::factory()->for($this->workspace)->create();
        $this->assertSame(SiteType::MultiPage, $site->fresh()?->site_type);

        $this->expectException(LogicException::class);
        $site->forceFill(['site_type' => SiteType::Landing])->save();
    }

    public function test_only_multi_page_sites_get_additional_pages(): void
    {
        $multi = $this->siteOf(SiteType::MultiPage);
        $this->as()->post(route('sites.pages.store', $multi), ['title' => 'Услуги'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $multi->pages()->count());

        foreach ([SiteType::Landing, SiteType::Quiz, SiteType::ChatSelection] as $type) {
            $site = $this->siteOf($type);
            $this->as()->post(route('sites.pages.store', $site), ['title' => 'Вторая'])->assertForbidden();
            $this->assertSame(1, $site->pages()->count(), $type->value);
        }
    }

    public function test_landing_keeps_block_structure_editable(): void
    {
        $landing = $this->siteOf(SiteType::Landing);
        $page = $landing->pages()->sole();

        $this->as()->post(route('sites.blocks.store', [$landing, $page]), ['block' => 'hero'])->assertSessionHasNoErrors();
        $this->assertSame(1, $page->blocks()->count());
    }

    public function test_quiz_and_chat_lock_block_structure_but_keep_content_editable(): void
    {
        foreach ([SiteType::Quiz, SiteType::ChatSelection] as $type) {
            $site = $this->siteOf($type);
            $page = $site->pages()->sole();
            $block = $this->placeHero($page);

            $this->as()->post(route('sites.blocks.store', [$site, $page]), ['block' => 'cta'])->assertForbidden();
            $this->as()->post(route('sites.blocks.move', [$site, $block]), ['direction' => 'down'])->assertForbidden();
            $this->as()->post(route('sites.blocks.duplicate', [$site, $block]))->assertForbidden();
            $this->as()->patch(route('sites.blocks.visibility', [$site, $block]), ['hidden' => true])->assertForbidden();
            $this->as()->delete(route('sites.blocks.destroy', [$site, $block]))->assertForbidden();
            $this->assertSame(1, $page->blocks()->count(), $type->value);

            $state = [...$block->state_json, 'title' => 'Подберём автомобиль за 3 шага'];
            $this->as()->patch(route('sites.blocks.state', [$site, $block]), ['state' => $state])->assertSessionHasNoErrors();
            $this->assertSame('Подберём автомобиль за 3 шага', $block->fresh()?->state_json['title']);

            $this->as()->get(route('sites.designer', $site))->assertInertia(fn (Assert $designer) => $designer
                ->where('can.editDesign', true)
                ->where('can.editStructure', false)
                ->where('can.addPage', false));
        }
    }

    private function siteOf(SiteType $type): Site
    {
        $site = Site::factory()->for($this->workspace)->create(['site_type' => $type]);
        Page::factory()->for($site)->home()->create();

        return $site;
    }

    private function placeHero(Page $page): BlockInstance
    {
        $version = BlockVersion::query()->whereRelation('definition', 'slug', 'hero')->latest('id')->firstOrFail();

        return BlockInstance::factory()->for($page)->for($version, 'version')->create();
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
