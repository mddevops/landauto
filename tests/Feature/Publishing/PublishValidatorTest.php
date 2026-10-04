<?php

namespace Tests\Feature\Publishing;

use App\Blocks\BlockStateDefaults;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Catalog\AutoSeries;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishIssue;
use App\Publishing\PublishValidation;
use App\Publishing\PublishValidator;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class PublishValidatorTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private Page $home;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(OfficialBlockSeeder::class);
        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->home = Page::factory()->for($this->site)->home()->create();
    }

    public function test_complete_draft_passes(): void
    {
        $form = Form::factory()->for($this->site)->withLeadFields()->create();
        $popup = Popup::factory()->for($this->site)->create();
        $popup->form()->associate($form)->save();
        $this->place('hero', ['primary_button' => ['label' => 'Заявка', 'action' => ['type' => 'open_popup', 'popup' => $popup->public_id]]]);

        $result = $this->validate();

        $this->assertTrue($result->passes(), json_encode($result->toArray(), JSON_UNESCAPED_UNICODE) ?: '');
        $this->assertSame([], $result->warnings);
    }

    public function test_missing_home_page_blocks_publish(): void
    {
        $this->home->delete();
        Page::factory()->for($this->site)->create(['slug' => 'offers']);

        $this->assertSame(['home_page_missing'], $this->validate()->errorCodes());
    }

    public function test_required_values_and_repeater_minimums_are_checked_on_visible_blocks_only(): void
    {
        $hero = $this->place('hero', ['title' => '  ']);
        $benefits = $this->place('benefits', ['items' => []]);
        $this->place('hero', ['title' => ''], hidden: true);

        $result = $this->validate();

        $this->assertSame(['block_required_missing'], $result->errorCodes());
        $this->assertSame(
            [[$hero->public_id, 'state.title', 'Заполните обязательное поле.'], [$benefits->public_id, 'state.items', 'Добавьте не меньше 1 элементов.']],
            array_map(fn (PublishIssue $issue): array => [$issue->block, $issue->path, $issue->message], $result->errors),
        );
        $this->assertSame($this->home->public_id, $result->errors[0]->page);
    }

    public function test_state_is_validated_against_the_pinned_schema(): void
    {
        $hero = $this->place('hero');
        DB::table('page_blocks')->where('id', $hero->id)->update(['state_json' => json_encode(['title' => 'Заголовок', 'unknown' => 'x'])]);

        $result = $this->validate();

        $this->assertSame(['block_state_invalid'], $result->errorCodes());
        $this->assertSame('state.unknown', $result->errors[0]->path);
    }

    public function test_stale_actions_block_publish_and_disabled_popup_form_only_warns(): void
    {
        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers']);
        $target = $this->place('cta', hidden: true);
        $form = Form::factory()->for($this->site)->withLeadFields()->create();
        $popup = Popup::factory()->for($this->site)->create();
        $popup->form()->associate($form)->save();
        $closed = Popup::factory()->for($this->site)->create();

        $this->place('cta', ['button' => ['label' => 'Подробнее', 'action' => ['type' => 'open_page', 'page' => $offers->public_id]]]);
        $this->place('cta', ['button' => ['label' => 'Подробнее', 'action' => ['type' => 'scroll_to', 'block' => $target->public_id]]]);
        $this->place('cta', ['button' => ['label' => 'Подробнее', 'action' => ['type' => 'open_popup', 'popup' => $closed->public_id]]]);
        $this->place('cta', ['button' => ['label' => 'Подробнее', 'action' => ['type' => 'open_popup', 'popup' => $popup->public_id]]]);

        $offers->delete();
        $closed->update(['status' => false]);
        $form->update(['status' => false]);

        $result = $this->validate();

        $this->assertSame(['stale_page_action', 'hidden_scroll_target', 'stale_popup_action'], $result->errorCodes());
        $this->assertSame(['popup_form_disabled'], array_map(fn (PublishIssue $issue): string => $issue->code, $result->warnings));
        $this->assertSame($offers->public_id, $result->errors[0]->target);
    }

    public function test_active_popup_form_needs_fields(): void
    {
        $form = Form::factory()->for($this->site)->create();
        $popup = Popup::factory()->for($this->site)->create();
        $popup->form()->associate($form)->save();

        $result = $this->validate();

        $this->assertSame(['form_fields_missing'], $result->errorCodes());
        $this->assertSame($form->public_id, $result->errors[0]->target);
    }

    public function test_missing_asset_file_blocks_publish(): void
    {
        $asset = SiteAsset::factory()->for($this->site)->create();
        $this->place('hero', ['image' => $asset->public_id]);

        $this->assertSame(['asset_file_missing'], $this->validate()->errorCodes());

        Storage::disk('local')->put($asset->path, 'png');
        $this->assertTrue($this->validate()->passes());

        $asset->delete();
        $this->assertSame(['missing_asset'], $this->validate()->errorCodes());
    }

    public function test_vehicle_references_must_be_shown_and_incomplete_vehicles_warn(): void
    {
        $series = AutoSeries::factory()->create();
        $vehicle = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();
        $this->place('vehicle-card', ['vehicle' => $vehicle->public_id]);

        $result = $this->validate();
        $this->assertTrue($result->passes());
        $this->assertSame(['vehicle_without_offers', 'vehicle_without_media'], array_map(fn (PublishIssue $issue): string => $issue->code, $result->warnings));

        SiteOffer::factory()->for($vehicle, 'vehicle')->create();
        $this->assertSame(['vehicle_without_media'], array_map(fn (PublishIssue $issue): string => $issue->code, $this->validate()->warnings));

        $vehicle->update(['status' => false]);
        $result = $this->validate();
        $this->assertSame(['vehicle_unavailable'], $result->errorCodes());
        $this->assertSame($vehicle->public_id, $result->errors[0]->target);
    }

    public function test_actor_without_publish_permission_and_archived_site_are_rejected(): void
    {
        $designer = User::factory()->create();
        $this->workspace->addMember($designer, WorkspaceRole::Designer);
        app(WorkspaceContext::class)->resolve($designer, app('session.store'));

        $this->assertSame(['publish_forbidden'], $this->validate($designer)->errorCodes());

        $this->site->update(['status' => 'archived']);
        $this->assertSame(['site_archived'], $this->validate()->errorCodes());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function place(string $slug, array $state = [], bool $hidden = false): BlockInstance
    {
        $version = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', $slug))->latest('id')->firstOrFail();

        $block = BlockInstance::factory()->create([
            'page_id' => $this->home->id,
            'block_version_id' => $version->id,
            'sort_order' => $this->home->blocks()->count(),
            'state_json' => array_replace(app(BlockStateDefaults::class)->fromSchema($version->schema_json), $state),
        ]);

        if ($hidden) {
            $block->forceFill(['is_hidden' => true])->save();
        }

        return $block;
    }

    private function validate(?User $actor = null): PublishValidation
    {
        return app(PublishValidator::class)->validate($this->site->fresh() ?? $this->site, $actor);
    }
}
