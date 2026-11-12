<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleMediaResolver;
use App\Automotive\VehiclePhotos;
use App\Enums\MediaAngle;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalogMedia;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VehicleMediaResolverTest extends TestCase
{
    use CreatesCatalogMedia, RefreshCatalogDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_vehicle_shows_its_own_colours_in_order_with_angles_left_to_right_and_gallery(): void
    {
        $series = AutoSeries::factory()->create();
        $white = $this->colorSet($series, 'Белый', [MediaAngle::SideRight, MediaAngle::Front, MediaAngle::SideLeft]);
        $black = $this->colorSet($series, 'Чёрный', [MediaAngle::FrontThreeQuarterRight]);
        $gallery = $this->galleryImage($series);
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        app(VehiclePhotos::class)->copyFromLibrary($vehicle, [$black->public_id, $white->public_id], [$gallery->public_id]);

        $resolved = app(VehicleMediaResolver::class)->resolve($vehicle->fresh() ?? $vehicle);

        $this->assertSame(VehicleMediaResolver::SOURCE_SITE, $resolved['source']);
        $this->assertSame(['Чёрный', 'Белый'], array_column($resolved['sets'], 'name'));
        $this->assertSame(['side_left', 'front', 'side_right'], array_column($resolved['sets'][1]['images'], 'angle'));
        $this->assertStringStartsWith('/vehicle-photos/', $resolved['sets'][1]['images'][0]['url']);
        $this->assertCount(1, $resolved['gallery']);
        $this->assertSame('front_3_4_right', $resolved['primary_angle'], 'preferred angle is missing, so the first available one is used');
        $this->assertSame($resolved['sets'][0]['images'][0]['url'], VehicleMediaResolver::cover($resolved)['url'] ?? null);
    }

    public function test_chosen_primary_angle_wins_and_empty_colours_are_skipped(): void
    {
        $series = AutoSeries::factory()->create();
        $white = $this->colorSet($series, 'Белый', [MediaAngle::FrontThreeQuarterLeft, MediaAngle::Front]);
        $vehicle = SiteVehicle::factory()->forSeries($series)->create(['primary_angle' => MediaAngle::Front]);
        $photos = app(VehiclePhotos::class);
        $photos->copyFromLibrary($vehicle, [$white->public_id], []);
        $photos->addColor($vehicle, 'Пустой', null);

        $resolved = app(VehicleMediaResolver::class)->resolve($vehicle->fresh() ?? $vehicle);
        $this->assertSame(['Белый'], array_column($resolved['sets'], 'name'));
        $this->assertSame('front', $resolved['primary_angle']);
        $this->assertSame($resolved['sets'][0]['images'][1]['url'], VehicleMediaResolver::cover($resolved)['url'] ?? null);

        $vehicle->update(['primary_angle' => MediaAngle::SideRight]);
        $resolved = app(VehicleMediaResolver::class)->resolve($vehicle->fresh() ?? $vehicle);
        $this->assertSame('front_3_4_left', $resolved['primary_angle'], 'missing chosen angle falls back to the preferred one');
    }

    public function test_upload_into_an_angle_replaces_the_previous_photo(): void
    {
        $series = AutoSeries::factory()->create();
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        $photos = app(VehiclePhotos::class);
        $color = $photos->addColor($vehicle, 'Синий', '#0000ff');

        $first = $photos->upload($vehicle, UploadedFile::fake()->image('a.png'), $color, MediaAngle::Front);
        $second = $photos->upload($vehicle, UploadedFile::fake()->image('b.png'), $color, MediaAngle::Front);

        $this->assertSame([$second->public_id], $color->photos()->pluck('public_id')->all());
        Storage::disk('local')->assertMissing($first->path);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_vehicle_without_photos_resolves_empty_and_many_uses_bulk_queries(): void
    {
        $empty = SiteVehicle::factory()->create();
        $series = AutoSeries::factory()->create();
        $white = $this->colorSet($series, 'Белый');
        $vehicles = SiteVehicle::factory()->count(3)->forSeries($series)->state(fn () => ['site_id' => Site::factory()])->create();
        $vehicles->each(fn (SiteVehicle $vehicle) => app(VehiclePhotos::class)->copyFromLibrary($vehicle, [$white->public_id], []));
        $vehicles = SiteVehicle::query()->whereKey($vehicles->modelKeys())->get();

        DB::enableQueryLog();
        $resolved = app(VehicleMediaResolver::class)->resolveMany($vehicles->push($empty));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['source' => null, 'primary_angle' => null, 'sets' => [], 'gallery' => []], $resolved[$empty->public_id]);
        $this->assertSame(VehicleMediaResolver::SOURCE_SITE, $resolved[$vehicles->first()?->public_id]['source']);
        $this->assertLessThanOrEqual(3, $queries);
    }
}
