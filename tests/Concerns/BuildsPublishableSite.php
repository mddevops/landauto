<?php

namespace Tests\Concerns;

use App\Automotive\VehiclePhotos;
use App\Blocks\BlockStateDefaults;
use App\Enums\MediaAngle;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\VehiclePhoto;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * A complete, publishable Site: home Page with a hero that opens a lead Popup, and a priced
 * KIA Rio vehicle with one own colour photo. Requires RefreshDatabase and RefreshCatalogDatabase.
 */
trait BuildsPublishableSite
{
    use CreatesCatalogMedia;

    protected Workspace $workspace;

    protected User $owner;

    protected Site $site;

    protected Page $home;

    protected Form $form;

    protected Popup $popup;

    protected SiteVehicle $vehicle;

    protected SiteOffer $offer;

    /** The vehicle's own copy of the platform colour photo. */
    protected VehiclePhoto $image;

    protected function buildPublishableSite(): void
    {
        Storage::fake('local');
        $this->seed(OfficialBlockSeeder::class);

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Дилер']);
        $this->home = Page::factory()->for($this->site)->home()->create();

        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
        $this->popup = Popup::factory()->for($this->site)->create(['name' => 'Заявка', 'title' => 'Оставьте заявку']);
        $this->popup->form()->associate($this->form)->save();

        $mark = AutoMark::factory()->create(['name' => 'KIA', 'url' => 'kia']);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV']);
        $series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан']);
        $modification = AutoModification::factory()->for($series, 'series')->create();
        $equipment = AutoEquipment::factory()->for($modification, 'modification')->create(['name' => 'Prestige']);

        $set = $this->colorSet($series, 'Белый', [MediaAngle::FrontThreeQuarterLeft]);

        $this->vehicle = SiteVehicle::factory()->for($this->site)->forSeries($series)->create();
        app(VehiclePhotos::class)->copyFromLibrary($this->vehicle, [$set->public_id], []);
        $this->image = $this->vehicle->photos()->firstOrFail();
        $this->offer = SiteOffer::factory()->for($this->vehicle, 'vehicle')->forEquipment($equipment)->create(['price_minor' => 210_000_000]);

        $this->place('hero', [
            'title' => 'Заголовок v1',
            'primary_button' => ['label' => 'Заявка', 'action' => ['type' => 'open_popup', 'popup' => $this->popup->public_id]],
        ]);
        $this->place('vehicle-card', ['vehicle' => $this->vehicle->public_id]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function place(string $slug, array $state = [], bool $hidden = false, ?Page $page = null): BlockInstance
    {
        $page ??= $this->home;
        $version = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', $slug))->latest('id')->firstOrFail();

        $block = BlockInstance::factory()->create([
            'page_id' => $page->id,
            'block_version_id' => $version->id,
            'sort_order' => $page->blocks()->count(),
            'state_json' => array_replace(app(BlockStateDefaults::class)->fromSchema($version->schema_json), $state),
        ]);

        if ($hidden) {
            $block->forceFill(['is_hidden' => true])->save();
        }

        return $block;
    }

    protected function actAsMember(User $user): void
    {
        $this->actingAs($user);
        app(WorkspaceContext::class)->resolve($user, app('session.store'));
    }
}
