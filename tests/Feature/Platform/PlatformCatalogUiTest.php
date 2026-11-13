<?php

namespace Tests\Feature\Platform;

use App\Catalog\AutoCatalog;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\PlatformRoleAssignment;
use App\Models\SeriesGalleryImage;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class PlatformCatalogUiTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_customers_cannot_open_or_mutate_the_platform_catalog(): void
    {
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $series = AutoSeries::factory()->create();
        $ref = (string) $series->ref();

        $this->get(route('platform.catalog.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('platform.catalog.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.catalog.series', $ref))->assertForbidden();
        $this->actingAs($owner)->post(route('platform.catalog.media.sets.store', $ref), ['name' => 'Белый', 'status' => 1, 'sort_order' => 0])->assertForbidden();
        $this->actingAs($owner)->post(route('platform.catalog.gallery.store', $ref), ['files' => [UploadedFile::fake()->image('a.png')]])->assertForbidden();

        $this->assertSame(0, SeriesMediaSet::query()->count());
        $this->assertSame(0, SeriesGalleryImage::query()->count());
    }

    public function test_browser_walks_marks_models_generations_and_series(): void
    {
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $kia = AutoMark::factory()->create(['name' => 'Kia', 'url' => 'kia', 'popular' => 1, 'logo_min' => 'mark_small/kia.webp']);
        AutoMark::factory()->create(['name' => 'Пустая', 'url' => 'empty']);
        $rio = AutoModel::factory()->for($kia, 'mark')->create(['name' => 'Rio', 'url' => 'rio', 'year_from' => 2011]);
        $generation = AutoGeneration::factory()->for($rio, 'model')->create(['name' => 'IV', 'year_from' => 2017, 'year_to' => 2020]);
        $series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Седан', 'image' => 'serie/2/rio.jpg']);
        $ref = (string) $series->ref();
        SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Белый']);
        $equipment = AutoEquipment::factory()->for(AutoModification::factory()->for($series, 'series'), 'modification')->create(['name' => 'Comfort']);

        $this->actingAs($manager)->get(route('platform.catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/index')
                ->has('marks', 1)
                ->where('marks.0.url', 'kia')
                ->where('marks.0.logo', app(AutoCatalog::class)->imageUrl('mark_small/kia.webp'))
                ->missing('marks.0.id'));

        $this->actingAs($manager)->get(route('platform.catalog.mark', 'kia'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/mark')
                ->where('models.0.name', 'Rio')
                ->where('models.0.image', app(AutoCatalog::class)->imageUrl('serie/2/rio.jpg')));

        $this->actingAs($manager)->get(route('platform.catalog.model', ['kia', 'rio']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/model')
                ->where('generations.0.name', 'IV')
                ->where('generations.0.years', '2017–2020')
                ->where('generations.0.series.0.ref', $ref)
                ->where('generations.0.series.0.colors_count', 1)
                ->where('generations.0.series.0.gallery_count', 0));

        $this->actingAs($manager)->get(route('platform.catalog.series', $ref))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/catalog/series')
                ->where('series.title', 'Kia Rio')
                ->where('library.colors.0.name', 'Белый')
                ->where('trims.0.equipments.0.key', $equipment->external_key)
                ->where('trims.0.equipments.0.name', 'Comfort')
                ->has('angles', 5)
                ->where('can.manageMedia', true));

        $this->actingAs($manager)->get(route('platform.catalog.mark', 'nope'))->assertNotFound();
        $this->actingAs($manager)->get(route('platform.catalog.model', ['kia', 'nope']))->assertNotFound();
        $this->actingAs($manager)->get(route('platform.catalog.series', '1:2'))->assertNotFound();
    }

    public function test_series_colours_are_managed_with_server_side_validation(): void
    {
        Storage::fake(SeriesMediaImage::DISK);
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $series = AutoSeries::factory()->create();
        $ref = (string) $series->ref();
        $store = fn (array $data) => $this->actingAs($manager)->post(route('platform.catalog.media.sets.store', $ref), [...$data, 'status' => 1, 'sort_order' => 0]);

        $store(['name' => 'Белый', 'swatch_hex' => '#FFFFFF'])->assertSessionHasNoErrors();
        $set = SeriesMediaSet::query()->sole();
        $this->assertSame(['#ffffff', $ref], [$set->swatch_hex, $set->catalog_series_ref]);
        $store(['name' => 'Белый', 'swatch_hex' => 'white'])->assertSessionHasErrors(['name', 'swatch_hex']);

        $this->actingAs($manager)
            ->patch(route('platform.catalog.media.sets.update', $set), ['name' => 'Белый перламутр', 'swatch_hex' => '', 'status' => 0, 'sort_order' => 3])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Белый перламутр', null, false, 3], [$set->fresh()?->name, $set->fresh()?->swatch_hex, $set->fresh()?->status, $set->fresh()?->sort_order]);

        $image = SeriesMediaImage::factory()->for($set, 'set')->create();
        Storage::disk(SeriesMediaImage::DISK)->put($image->path, 'png');
        $this->actingAs($manager)->delete(route('platform.catalog.media.sets.destroy', $set))->assertRedirect();
        $this->assertModelMissing($set);
        $this->assertModelMissing($image);
        Storage::disk(SeriesMediaImage::DISK)->assertMissing($image->path);
    }

    public function test_gallery_is_uploaded_reordered_and_removed(): void
    {
        Storage::fake(SeriesGalleryImage::DISK);
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $series = AutoSeries::factory()->create();
        $ref = (string) $series->ref();

        $this->actingAs($manager)
            ->post(route('platform.catalog.gallery.store', $ref), ['files' => [
                UploadedFile::fake()->image('salon.jpg', 1200, 800),
                UploadedFile::fake()->image('wheel.png', 800, 600),
            ]])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)
            ->post(route('platform.catalog.gallery.store', $ref), ['files' => [UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')]])
            ->assertSessionHasErrors();

        $images = SeriesGalleryImage::query()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertSame([1200, 800], [$images[0]->width, $images[0]->height]);
        Storage::disk(SeriesGalleryImage::DISK)->assertExists($images[0]->path);

        $this->actingAs($manager)
            ->put(route('platform.catalog.gallery.reorder', $ref), ['images' => [$images[1]->public_id, $images[0]->public_id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$images[1]->public_id, $images[0]->public_id], SeriesGalleryImage::query()->ordered()->pluck('public_id')->all());

        $this->actingAs(User::factory()->create())->get($images[0]->url())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($manager)->delete(route('platform.catalog.gallery.destroy', $images[0]->public_id))->assertRedirect();
        $this->assertModelMissing($images[0]);
        Storage::disk(SeriesGalleryImage::DISK)->assertMissing($images[0]->path);
    }

    public function test_catalog_images_are_served_only_from_known_folders_to_signed_in_users(): void
    {
        Storage::fake(AutoCatalog::IMAGES_DISK);
        Storage::disk(AutoCatalog::IMAGES_DISK)->put('serie/2/rio.jpg', 'jpeg');
        Storage::disk(AutoCatalog::IMAGES_DISK)->put('secret.txt', 'secret');
        $user = User::factory()->create();
        $url = (string) app(AutoCatalog::class)->imageUrl('serie/2/rio.jpg');

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($user)->get('/media/catalog/serie/2/missing.jpg')->assertNotFound();
        $this->actingAs($user)->get('/media/catalog/serie/../secret.txt')->assertNotFound();
        $this->actingAs($user)->get('/media/catalog/secret.txt')->assertNotFound();
        $this->actingAs($user)->get('/media/catalog/serie/2/rio.php')->assertNotFound();
    }

    private function platformUser(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }
}
