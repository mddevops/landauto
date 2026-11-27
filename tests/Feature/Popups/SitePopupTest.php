<?php

namespace Tests\Feature\Popups;

use App\Enums\PopupSize;
use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Popups\PopupLaunch;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
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
                    ->where('popups.0.launch.auto_show', 'off')
                    ->missing('popups.0.id'));
        }
    }

    public function test_launch_rules_are_saved_and_a_used_popup_cannot_be_deleted_without_reassign(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($this->site)->save();

        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $this->payload([
            'name' => 'Узнать цену',
            'launch' => [
                'auto_show' => 'once_session',
                'delay_seconds' => 3,
                'period_seconds' => 60,
                'shows' => [],
                'confirmation' => 'notice',
                'conditions' => [
                    'join' => 'and',
                    'name' => '  Кредитные страницы  ',
                    'enabled' => false,
                    'groups' => [[
                        'join' => 'and',
                        'rules' => [
                            ['type' => 'pages', 'operator' => 'contains', 'value' => '/credit'],
                            ['type' => 'devices', 'operator' => 'equals', 'value' => 'mobile'],
                        ],
                    ]],
                ],
            ],
        ]))->assertSessionHasNoErrors();

        $price = Popup::query()->where('name', 'Узнать цену')->sole();
        $this->assertSame('once_session', $price->launch_json['auto_show']);
        $this->assertSame('mobile', $price->launch_json['conditions']['groups'][0]['rules'][1]['value']);
        $this->assertSame('Кредитные страницы', $price->launch_json['conditions']['name']);
        $this->assertFalse($price->launch_json['conditions']['enabled']);

        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $this->payload(['name' => 'Тест-драйв']))->assertSessionHasNoErrors();
        $drive = Popup::query()->where('name', 'Тест-драйв')->sole();

        $this->as($this->owner)->post(route('sites.blocks.store', [$this->site, $home]), ['block' => 'cta'])->assertSessionHasNoErrors();
        $block = $home->blocks()->latest('id')->firstOrFail();
        $this->as($this->owner)->patch(route('sites.blocks.state', [$this->site, $block]), [
            'state' => ['button' => ['label' => 'Заявка', 'action' => ['type' => 'open_popup', 'popup' => $price->public_id]]],
        ])->assertSessionHasNoErrors();

        $this->as($this->owner)->delete(route('sites.popups.destroy', [$this->site, $price]))->assertSessionHasErrors('popup');
        $this->assertModelExists($price);

        $this->as($this->owner)->delete(route('sites.popups.destroy', [$this->site, $price]), ['replace_with' => $drive->public_id])->assertSessionHasNoErrors();
        $this->assertModelMissing($price);
        $this->assertSame($drive->public_id, $block->fresh()?->state_json['button']['action']['popup']);
    }

    public function test_floating_launcher_accepts_only_a_popup_of_the_same_site(): void
    {
        $popup = Popup::factory()->for($this->site)->create();
        $foreign = Popup::factory()->create();

        $this->as($this->owner)->patch(route('sites.popups.launcher', $this->site), [
            ...$this->launcherPayload($popup->public_id),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($this->site->fresh()?->floating_launcher_json['enabled']);
        $this->assertSame($popup->public_id, $this->site->fresh()?->floating_launcher_json['popup']);

        $this->as($this->owner)->patch(route('sites.popups.launcher', $this->site), [
            ...$this->launcherPayload($foreign->public_id),
        ])->assertSessionHasErrors('popup');
    }

    public function test_popup_launch_supports_the_verified_condition_pairs(): void
    {
        $this->assertSame(29, array_sum(array_map(count(...), PopupLaunch::OPERATORS)));
        $this->assertNull(PopupLaunch::tryNormalize([
            'auto_show' => 'auto',
            'conditions' => ['join' => 'and', 'groups' => [[
                'join' => 'and',
                'rules' => [['type' => 'pages', 'operator' => 'visited', 'value' => '/x']],
            ]]],
        ]));
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

    /**
     * @return array<string, mixed>
     */
    private function launcherPayload(string $popupId): array
    {
        return [
            'enabled' => true,
            'popup' => $popupId,
            'desktop' => [
                'variant' => 'standard',
                'size' => 64,
                'text_instead_of_icon' => false,
                'text' => 'Звонок',
                'icon' => 'phone',
                'auto_icon_size' => true,
                'icon_size' => 32,
                'hover' => 'dialog',
                'idle' => 'none',
                'interval_seconds' => 0,
                'width' => 206,
                'subtitle' => '',
            ],
            'mobile' => [
                'variant' => 'full_width',
                'size' => 64,
                'text_instead_of_icon' => true,
                'text' => 'Заявка',
                'icon' => 'phone',
                'auto_icon_size' => true,
                'icon_size' => 24,
                'hover' => 'none',
                'idle' => 'none',
                'interval_seconds' => 0,
                'width' => 206,
                'subtitle' => '',
            ],
            'dialog' => [
                'title' => 'Нужна помощь?',
                'subtitle' => 'Оставьте заявку',
                'cta_label' => 'Открыть форму',
                'open_delay_seconds' => 0,
                'show_seconds' => 5,
                'open_popup' => true,
            ],
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
