<?php

namespace Tests\Feature\Popups;

use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OpenPopupActionTest extends TestCase
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

    public function test_open_popup_accepts_only_active_popups_of_the_same_site(): void
    {
        $first = $this->block('cta');
        $second = $this->block('cta');
        $popup = Popup::factory()->for($this->site)->create();
        $inactive = Popup::factory()->for($this->site)->inactive()->create();
        $otherSitePopup = Popup::factory()->for(Site::factory()->for($this->workspace))->create();
        $foreignPopup = Popup::factory()->create();

        $this->save($first, ['type' => 'open_popup', 'popup' => $popup->public_id])->assertSessionHasNoErrors();
        $this->save($second, ['type' => 'open_popup', 'popup' => $popup->public_id])->assertSessionHasNoErrors();
        $this->assertSame(['type' => 'open_popup', 'popup' => $popup->public_id], $first->fresh()?->state_json['button']['action']);

        $notUsable = 'Попап не найден или выключен на этом сайте.';
        $this->save($first, ['type' => 'open_popup', 'popup' => $inactive->public_id])->assertSessionHasErrors(['state.button.action.popup' => $notUsable]);
        $this->save($first, ['type' => 'open_popup', 'popup' => $otherSitePopup->public_id])->assertSessionHasErrors(['state.button.action.popup' => $notUsable]);
        $this->save($first, ['type' => 'open_popup', 'popup' => $foreignPopup->public_id])->assertSessionHasErrors(['state.button.action.popup' => $notUsable]);
        $this->save($first, ['type' => 'open_popup', 'popup' => (string) $popup->id])->assertSessionHasErrors(['state.button.action.popup' => 'Выберите попап этого сайта.']);
        $this->save($first, ['type' => 'open_popup', 'popup' => $popup->public_id, 'script' => 'alert(1)'])->assertSessionHasErrors('state.button.action.script');
        $this->save($first, ['type' => 'open_popup', 'popup' => null])->assertSessionHasNoErrors();

        $this->assertSame(['type' => 'open_popup', 'popup' => null], $first->fresh()?->state_json['button']['action']);
    }

    private function block(string $slug): BlockInstance
    {
        $this->as()->post(route('sites.blocks.store', [$this->site, $this->page]), ['block' => $slug])->assertSessionHasNoErrors();

        return $this->page->blocks()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function save(BlockInstance $block, array $action): TestResponse
    {
        return $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), [
            'state' => ['button' => ['label' => 'Оставить заявку', 'action' => $action]],
        ]);
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
