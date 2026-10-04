<?php

namespace Tests\Feature\Popups;

use App\Enums\PopupSize;
use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SitePopupTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_owner_creates_updates_and_deletes_a_site_popup(): void
    {
        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $popup = Popup::query()->sole();
        $this->assertSame([$this->site->id, 'Обратный звонок', PopupSize::Large], [$popup->site_id, $popup->name, $popup->size]);

        $this->as($this->owner)->get(route('sites.popups.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/popups/index')
                ->where('popups.0.public_id', $popup->public_id)
                ->where('popups.0.size', 'large')
                ->where('can.editPopups', true)
                ->missing('popups.0.id')
                ->missing('popups.0.site_id'));

        $this->as($this->owner)->patch(route('sites.popups.update', [$this->site, $popup]), $this->payload(['name' => 'Тест-драйв', 'status' => false]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Тест-драйв', false], [$popup->fresh()?->name, $popup->fresh()?->status]);

        $this->as($this->owner)->delete(route('sites.popups.destroy', [$this->site, $popup]))->assertRedirect();
        $this->assertModelMissing($popup);
    }

    public function test_popup_must_stay_closable_and_valid(): void
    {
        $store = fn (array $overrides) => $this->as($this->owner)->post(route('sites.popups.store', $this->site), $this->payload($overrides));

        $store(['show_close_button' => false, 'close_on_escape' => false])->assertSessionHasErrors('show_close_button');
        $store(['size' => 'huge'])->assertSessionHasErrors('size');
        $store(['animation' => 'spin'])->assertSessionHasErrors('animation');
        $store(['name' => ''])->assertSessionHasErrors('name');
        $store(['text' => str_repeat('а', 2001)])->assertSessionHasErrors('text');
        $this->assertSame(0, Popup::query()->count());

        $store(['show_close_button' => false, 'close_on_escape' => true])->assertSessionHasNoErrors();
    }

    public function test_foreign_cross_site_and_numeric_popup_references_are_not_found(): void
    {
        $foreign = Popup::factory()->create();
        $otherSite = Site::factory()->for($this->workspace)->create();
        $otherSitePopup = Popup::factory()->for($otherSite)->create();

        $this->as($this->owner)->patch(route('sites.popups.update', [$this->site, $foreign]), $this->payload())->assertNotFound();
        $this->as($this->owner)->delete(route('sites.popups.destroy', [$this->site, $foreign]))->assertNotFound();
        $this->as($this->owner)->patch(route('sites.popups.update', [$this->site, $otherSitePopup]), $this->payload())->assertNotFound();
        $this->as($this->owner)->get(route('sites.popups.index', $foreign->site))->assertNotFound();
        $this->as($this->owner)->patch("/sites/{$this->site->public_id}/popups/{$otherSitePopup->id}", $this->payload())->assertNotFound();

        $this->assertModelExists($foreign);
        $this->assertSame('Обратный звонок', $otherSitePopup->fresh()?->name);
    }

    public function test_popup_editing_follows_edit_popups_permission(): void
    {
        $members = [];

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $members[$role->value] = User::factory()->create();
            $this->workspace->addMember($members[$role->value], $role);
        }

        $this->as($members['designer'])->post(route('sites.popups.store', $this->site), $this->payload())->assertSessionHasNoErrors();
        $this->as($members['admin'])->get(route('sites.popups.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.editPopups', true));
        $this->as($members['admin'])->post(route('sites.popups.store', $this->site), $this->payload())->assertSessionHasNoErrors();

        $this->as($members['content_editor'])->get(route('sites.popups.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.editPopups', false));
        $this->as($members['content_editor'])->post(route('sites.popups.store', $this->site), $this->payload())->assertForbidden();

        $this->assertSame(2, Popup::query()->count());
    }

    public function test_designer_and_preview_receive_only_active_popups_without_internal_ids(): void
    {
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($this->site)->save();
        $active = Popup::factory()->for($this->site)->create(['name' => 'Активный']);
        Popup::factory()->for($this->site)->inactive()->create(['name' => 'Выключенный']);
        Popup::factory()->create();

        foreach (['sites.designer', 'sites.preview'] as $route) {
            $this->as($this->owner)->get(route($route, $this->site))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('popups', 1)
                    ->where('popups.0.public_id', $active->public_id)
                    ->where('popups.0.close_on_escape', true)
                    ->missing('popups.0.id'));
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Обратный звонок',
            'status' => true,
            'title' => 'Перезвоним за 5 минут',
            'text' => 'Оставьте телефон — менеджер свяжется с вами.',
            'size' => 'large',
            'animation' => 'slide_up',
            'close_on_overlay' => true,
            'close_on_escape' => true,
            'show_close_button' => true,
            'mobile_fullscreen' => true,
            ...$overrides,
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
