<?php

namespace Tests\Feature\Sites;

use App\Blocks\OfficialBlockCatalog;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
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

class PageBlocksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Site $site;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->user, WorkspaceRole::Designer);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->page = Page::factory()->for($this->site)->home()->create();
    }

    public function test_official_block_is_added_with_schema_defaults_and_selected(): void
    {
        $this->add('header');
        $response = $this->add('hero');

        $hero = $this->page->blocks()->get()->last();
        $this->assertNotNull($hero);
        $response->assertRedirect(route('sites.designer', [
            'site' => $this->site, 'page' => $this->page->public_id, 'block' => $hero->public_id,
        ]));
        $this->assertSame(1, $hero->sort_order);
        $this->assertSame('hero', $hero->version->definition->slug);
        $this->assertSame('Новые автомобили в наличии', $hero->state_json['title']);
        $this->assertSame('left', $hero->state_json['align']);
        $this->assertSame(['label' => 'Подобрать автомобиль'], $hero->state_json['primary_button']);

        $this->as()->get(route('sites.designer', ['site' => $this->site, 'block' => $hero->public_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedBlock', $hero->public_id)
                ->where('blocks.1.is_hidden', false)
                ->has('library', count(array_unique(array_column(OfficialBlockCatalog::blocks(), 'slug'))))
                ->where('library.1', ['slug' => 'hero', 'name' => 'Первый экран']));
    }

    public function test_only_existing_official_blocks_can_be_added(): void
    {
        $private = BlockDefinition::factory()->create(['is_official' => false, 'slug' => 'private']);
        BlockVersion::factory()->for($private, 'definition')->create();

        $this->add('private')->assertSessionHasErrors('block');
        $this->add('missing')->assertSessionHasErrors('block');
        $this->assertSame(0, BlockInstance::query()->count());
    }

    public function test_blocks_are_moved_duplicated_hidden_and_deleted_in_dense_order(): void
    {
        foreach (['header', 'hero', 'footer'] as $slug) {
            $this->add($slug);
        }
        [$header, $hero, $footer] = $this->page->blocks()->get()->all();

        $this->as()->post(route('sites.blocks.move', [$this->site, $footer]), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->as()->post(route('sites.blocks.move', [$this->site, $header]), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertOrder([$header->id, $footer->id, $hero->id]);

        $this->as()->patch(route('sites.blocks.visibility', [$this->site, $footer]), ['hidden' => true])->assertSessionHasNoErrors();
        $this->as()->post(route('sites.blocks.duplicate', [$this->site, $footer]))->assertSessionHasNoErrors();
        $copy = BlockInstance::query()->latest('id')->firstOrFail();
        $this->assertOrder([$header->id, $footer->id, $copy->id, $hero->id]);
        $this->assertTrue($copy->is_hidden);
        $this->assertSame($footer->fresh()?->state_json, $copy->state_json);
        $this->assertSame($footer->block_version_id, $copy->block_version_id);

        $this->as()->delete(route('sites.blocks.destroy', [$this->site, $footer]))->assertRedirect();
        $this->assertModelMissing($footer);
        $this->assertSame([$header->id, $copy->id, $hero->id], $this->page->blocks()->pluck('id')->all());
    }

    public function test_content_editor_saves_schema_valid_state_and_gets_field_errors(): void
    {
        $this->add('hero');
        $block = $this->page->blocks()->sole();
        $editor = User::factory()->create();
        $this->workspace->addMember($editor, WorkspaceRole::ContentEditor);
        $asEditor = fn () => $this->actingAs($editor)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);

        $asEditor()->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can', ['editDesign' => false, 'editContent' => true, 'manageAssets' => false, 'preview' => false])
                ->where('blocks.0.schema.fields.1.key', 'title'));

        $asEditor()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => [
            'title' => 'Весеннее предложение',
            'align' => 'center',
            'primary_button' => ['label' => 'Записаться'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Весеннее предложение', $block->fresh()?->state_json['title']);

        $asEditor()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => [
            'title' => str_repeat('я', 121),
            'align' => 'right',
            'secret' => 'x',
        ]])->assertSessionHasErrors(['state.title', 'state.align', 'state.secret']);
        $this->assertSame('Весеннее предложение', $block->fresh()?->state_json['title']);
    }

    public function test_repeater_items_keep_client_ulids_and_order_and_reject_duplicates(): void
    {
        $this->add('benefits');
        $block = $this->page->blocks()->sole();
        $first = '01J9Z3QK5V8W2X4Y6Z8A0B2C4D';
        $second = '01J9Z3QK5V8W2X4Y6Z8A0B2C4E';

        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['items' => [
            ['id' => $second, 'title' => 'Трейд-ин'],
            ['id' => $first, 'title' => 'Гарантия', 'text' => 'Пять лет'],
        ]]])->assertSessionHasNoErrors();

        $this->assertSame([$second, $first], array_column($block->fresh()?->state_json['items'] ?? [], 'id'));

        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['items' => [
            ['id' => $first, 'title' => 'А'],
            ['id' => $first, 'title' => 'Б'],
        ]]])->assertSessionHasErrors('state.items.1.id');
    }

    public function test_content_editor_cannot_change_structure_and_foreign_blocks_are_not_found(): void
    {
        $this->add('hero');
        $block = $this->page->blocks()->sole();
        $editor = User::factory()->create();
        $this->workspace->addMember($editor, WorkspaceRole::ContentEditor);

        $this->actingAs($editor)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id])
            ->delete(route('sites.blocks.destroy', [$this->site, $block]))->assertForbidden();

        $foreign = BlockInstance::factory()->create();
        $this->as()->delete(route('sites.blocks.destroy', [$this->site, $foreign]))->assertNotFound();
        $this->as()->patch(route('sites.blocks.state', [$this->site, $foreign]), ['state' => []])->assertNotFound();
        $this->as()->post(route('sites.blocks.store', [$this->site, $foreign->page]), ['block' => 'hero'])->assertNotFound();
        $this->assertModelExists($foreign);
    }

    private function add(string $slug): TestResponse
    {
        return $this->as()->post(route('sites.blocks.store', [$this->site, $this->page]), ['block' => $slug]);
    }

    public function test_actions_reference_only_pages_of_this_site_and_blocks_of_this_page(): void
    {
        $this->add('benefits');
        $this->add('cta');
        [$benefits, $cta] = $this->page->blocks()->get()->all();
        $this->assertSame('1.1.0', $cta->version->version);
        $about = Page::factory()->for($this->site)->create();
        $foreignPage = Page::factory()->create();
        $otherPageBlock = BlockInstance::factory()->for($about)->create();
        $save = fn (array $action): TestResponse => $this->as()->patch(
            route('sites.blocks.state', [$this->site, $cta]),
            ['state' => ['button' => ['label' => 'Подробнее', 'action' => $action]]],
        );

        $save(['type' => 'open_page', 'page' => $about->public_id])->assertSessionHasNoErrors();
        $save(['type' => 'scroll_to', 'block' => $benefits->public_id])->assertSessionHasNoErrors();
        $this->assertSame(['type' => 'scroll_to', 'block' => $benefits->public_id], $cta->fresh()?->state_json['button']['action']);

        $save(['type' => 'open_page', 'page' => $foreignPage->public_id])
            ->assertSessionHasErrors(['state.button.action.page' => 'Страница не найдена на этом сайте.']);
        $save(['type' => 'scroll_to', 'block' => $otherPageBlock->public_id])
            ->assertSessionHasErrors(['state.button.action.block' => 'Блок не найден на этой странице.']);
        $save(['type' => 'open_url', 'url' => 'javascript:alert(document.cookie)'])
            ->assertSessionHasErrors('state.button.action.url');
    }

    /**
     * @param  list<int>  $ids
     */
    private function assertOrder(array $ids): void
    {
        $this->assertSame($ids, $this->page->blocks()->pluck('id')->all());
        $this->assertSame(range(0, count($ids) - 1), $this->page->blocks()->pluck('sort_order')->all());
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
