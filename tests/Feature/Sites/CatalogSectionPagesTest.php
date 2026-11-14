<?php

namespace Tests\Feature\Sites;

use App\Automotive\SectionPages;
use App\Enums\SectionPageRole;
use App\Enums\SiteType;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteVehicle;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class CatalogSectionPagesTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    private Page $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage]);
        $this->home = Page::factory()->for($this->site)->home()->create();
    }

    public function test_adding_catalog_creates_prefilled_template_pages_with_home_header(): void
    {
        $header = BlockDefinition::query()->where('slug', 'header')->sole()->versions()->latest('id')->firstOrFail();
        $this->home->blocks()->make(['sort_order' => 0, 'state_json' => ['logo_text' => 'Киа Центр']])
            ->version()->associate($header)->save();

        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])->assertSessionHasNoErrors();

        $section = $this->site->sections()->sole();
        $pages = $section->pages()->with('blocks.version.definition')->get()->keyBy(fn (Page $page): string => $page->section_role->value);

        $this->assertSame(['index', 'mark', 'model', 'item'], array_map(fn (SectionPageRole $role): string => $role->value, SectionPageRole::cases()));
        $this->assertEqualsCanonicalizing(['index', 'mark', 'model', 'item'], $pages->keys()->all());
        $this->assertSame('_catalog-item', $pages['item']->slug);
        $this->assertSame(
            ['header', 'vehicle-gallery', 'vehicle-offers', 'vehicle-characteristics', 'vehicle-equipment'],
            $pages['item']->blocks->map(fn ($block): string => $block->version->definition->slug)->all(),
        );
        $this->assertSame(['header', SectionPages::LISTING_BLOCK], $pages['index']->blocks->map(fn ($block): string => $block->version->definition->slug)->all());
        $this->assertSame('Киа Центр', $pages['index']->blocks->first()?->state_json['logo_text']);

        app(SectionPages::class)->ensure($section);
        $this->assertSame(4, $section->pages()->count());
    }

    public function test_template_pages_cannot_be_deleted_alone_and_go_with_the_section(): void
    {
        $section = $this->catalog();
        $item = $section->pages()->where('section_role', 'item')->sole();

        $this->as()->delete(route('sites.pages.destroy', [$this->site, $item]))->assertSessionHasErrors('page');
        $this->assertModelExists($item);

        $this->as()->patch(route('sites.pages.update', [$this->site, $item]), ['title' => 'Автомобиль', 'slug' => 'auto'])->assertSessionHasNoErrors();
        $this->assertSame('Автомобиль', $item->fresh()?->title);
        $this->assertSame('_catalog-item', $item->fresh()?->slug);

        $this->as()->delete(route('sites.sections.destroy', [$this->site, $section]))->assertRedirect();
        $this->assertSame(0, Page::query()->whereNotNull('site_section_id')->count());
        $this->assertModelExists($this->home);
    }

    public function test_vehicles_get_unique_addresses_per_model_and_keep_old_ones(): void
    {
        [$rio, $rioWagon, $ceed] = $this->vehicles();

        $this->as()->get(route('sites.vehicles.show', [$this->site, $rio]))
            ->assertInertia(fn (Assert $page) => $page->where('address.base_url', null));
        $this->catalog();

        $this->as()->get(route('sites.vehicles.show', [$this->site, $rio]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('address.slug', 'iv-restyling-sedan')
                ->where('address.base_url', 'http://localhost/'.$this->site->public_number.'/catalog/kia/rio/'));

        $this->assertSame('iv-restyling-sedan-2', $rioWagon->fresh()?->slug);
        $this->assertSame('iv-restyling-sedan', $ceed->fresh()?->slug, 'another model may reuse the address');

        $this->as()->patch(route('sites.vehicles.update', [$this->site, $rioWagon]), ['slug' => 'iv-restyling-sedan'])
            ->assertSessionHasErrors(['slug' => 'Такой адрес уже есть у другого автомобиля этой модели.']);
        $this->as()->patch(route('sites.vehicles.update', [$this->site, $rioWagon]), ['slug' => 'Bad slug'])
            ->assertSessionHasErrors('slug');

        $this->as()->patch(route('sites.vehicles.update', [$this->site, $rioWagon]), ['slug' => 'IV-Restyling-Universal'])->assertSessionHasNoErrors();
        $this->assertSame('iv-restyling-universal', $rioWagon->fresh()?->slug);
        $this->assertSame(['iv-restyling-sedan-2'], $rioWagon->slugRedirects()->pluck('slug')->all());
    }

    public function test_designer_shows_template_page_on_a_sample_vehicle(): void
    {
        [$rio] = $this->vehicles();
        $section = $this->catalog();
        $item = $section->pages()->where('section_role', 'item')->sole();

        $this->as()->get(route('sites.designer', ['site' => $this->site, 'page' => $item->public_id, 'sample' => $rio->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.section_role', 'item')
                ->where('catalog.prefix', 'catalog')
                ->where('catalog.pages.item', $item->public_id)
                ->has('catalog.vehicles', 3)
                ->where('catalog.vehicles.0.mark.segment', 'kia')
                ->where('pageContext', ['role' => 'item', 'mark' => 'kia', 'model' => 'rio', 'vehicle' => $rio->public_id])
                ->where('pages', fn ($pages): bool => collect($pages)->where('section_role', 'item')->count() === 1));
    }

    public function test_preview_resolves_section_addresses(): void
    {
        [$rio] = $this->vehicles();
        $section = $this->catalog();
        $pages = $section->pages()->get()->keyBy(fn (Page $page): string => $page->section_role->value);

        foreach ([
            'catalog' => ['index', null, null, null],
            'catalog/kia' => ['mark', 'kia', null, null],
            'catalog/kia/rio' => ['model', 'kia', 'rio', null],
            'catalog/kia/rio/iv-restyling-sedan' => ['item', 'kia', 'rio', $rio->public_id],
        ] as $path => [$role, $mark, $model, $vehicle]) {
            $this->as()->get(route('sites.preview', ['site' => $this->site, 'path' => $path]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('page.public_id', $pages[$role]->public_id)
                    ->where('pageContext', ['role' => $role, 'mark' => $mark, 'model' => $model, 'vehicle' => $vehicle]));
        }

        foreach (['catalog/bmw', 'catalog/kia/x5', 'catalog/kia/rio/unknown', 'autos/kia', 'catalog/kia/rio/iv-restyling-sedan/extra'] as $path) {
            $this->as()->get(route('sites.preview', ['site' => $this->site, 'path' => $path]))->assertNotFound();
        }
    }

    public function test_template_pages_stay_out_of_publication_and_seo_lists(): void
    {
        $this->catalog();

        $this->as()->get(route('sites.seo.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('pages', 1));
    }

    public function test_open_vehicle_action_accepts_only_own_vehicles(): void
    {
        [$rio] = $this->vehicles();
        $foreign = SiteVehicle::factory()->forSeries(AutoSeries::factory()->create())->create();
        $cta = BlockDefinition::query()->where('slug', 'cta')->sole()->versions()->latest('id')->firstOrFail();
        $block = $this->home->blocks()->make(['sort_order' => 0, 'state_json' => []]);
        $block->version()->associate($cta)->save();

        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['button' => ['label' => 'Rio', 'action' => ['type' => 'open_vehicle', 'vehicle' => $foreign->public_id]]]])
            ->assertSessionHasErrors();
        $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), ['state' => ['button' => ['label' => 'Rio', 'action' => ['type' => 'open_vehicle', 'vehicle' => $rio->public_id]]]])
            ->assertSessionHasNoErrors();
    }

    private function catalog(): SiteSection
    {
        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])->assertSessionHasNoErrors();

        return $this->site->sections()->sole();
    }

    /**
     * Kia Rio sedan and wagon share the generated address base; Kia Ceed is another model.
     *
     * @return array{SiteVehicle, SiteVehicle, SiteVehicle}
     */
    private function vehicles(): array
    {
        $kia = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia']);
        $rio = AutoModel::factory()->for($kia, 'mark')->create(['name' => 'Rio', 'url' => 'rio']);
        $ceed = AutoModel::factory()->for($kia, 'mark')->create(['name' => 'Ceed', 'url' => 'ceed']);
        $series = fn (AutoModel $model): AutoSeries => AutoSeries::factory()
            ->for(AutoGeneration::factory()->for($model, 'model')->create(['name' => 'IV Рестайлинг']), 'generation')
            ->create(['name' => 'Седан']);

        return [
            SiteVehicle::factory()->for($this->site)->forSeries($series($rio))->create(['sort_order' => 1]),
            SiteVehicle::factory()->for($this->site)->forSeries($series($rio))->create(['sort_order' => 2]),
            SiteVehicle::factory()->for($this->site)->forSeries($series($ceed))->create(['sort_order' => 3]),
        ];
    }

    private function as(): self
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
