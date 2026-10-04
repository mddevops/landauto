<?php

namespace Tests\Feature\Catalog;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\PlatformRoleAssignment;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\SiteAsset;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SeriesMediaImagesTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_catalog_manager_uploads_prepared_images_with_server_generated_keys(): void
    {
        Storage::fake(SeriesMediaImage::DISK);
        $manager = $this->platformUser(PlatformRole::CatalogManager);
        $set = SeriesMediaSet::factory()->create(['name' => 'Белый']);

        $this->actingAs($manager)
            ->post(route('platform.catalog.media.images.store', $set), [
                'file' => UploadedFile::fake()->image('../../front evil.png', 1600, 900),
                'angle' => 'front_3_4',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $image = SeriesMediaImage::query()->sole();
        $this->assertSame("series-media/{$set->public_id}/{$image->public_id}.png", $image->path);
        $this->assertSame('front_3_4', $image->angle->value);
        $this->assertSame([1600, 900], [$image->width, $image->height]);
        Storage::disk(SeriesMediaImage::DISK)->assertExists($image->path);
        $this->assertSame(0, SiteAsset::query()->count(), 'platform media is never a Site Asset');
        $this->assertArrayNotHasKey('path', $image->toArray());

        $this->actingAs($manager)
            ->post(route('platform.catalog.media.images.store', $set), [
                'file' => UploadedFile::fake()->image('again.webp', 100, 100),
                'angle' => 'front_3_4',
            ])
            ->assertSessionHasErrors('angle');

        $this->expectException(LogicException::class);
        $image->forceFill(['path' => 'other.png'])->save();
    }

    public function test_svg_disguised_and_oversized_files_are_rejected(): void
    {
        Storage::fake(SeriesMediaImage::DISK);
        $manager = $this->platformUser(PlatformRole::SuperAdmin);
        $set = SeriesMediaSet::factory()->create();
        $store = fn (UploadedFile $file, string $angle = 'side') => $this->actingAs($manager)
            ->post(route('platform.catalog.media.images.store', $set), ['file' => $file, 'angle' => $angle]);

        $store(UploadedFile::fake()->createWithContent('car.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))->assertSessionHasErrors('file');
        $store(UploadedFile::fake()->createWithContent('car.png', '<?php echo 1; ?>'))->assertSessionHasErrors('file');
        $store(UploadedFile::fake()->image('big.jpg')->size(10241))->assertSessionHasErrors('file');
        $store(UploadedFile::fake()->image('car.png'), 'top')->assertSessionHasErrors('angle');
        $store(UploadedFile::fake()->image('car.png'), 'http://example.test/x.png')->assertSessionHasErrors('angle');

        $this->assertSame(0, SeriesMediaImage::query()->count());
        $this->assertSame([], Storage::disk(SeriesMediaImage::DISK)->allFiles());
    }

    public function test_workspace_owner_cannot_manage_platform_media(): void
    {
        Storage::fake(SeriesMediaImage::DISK);
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $set = SeriesMediaSet::factory()->create();
        $image = SeriesMediaImage::factory()->for($set, 'set')->create();

        $this->actingAs($owner)
            ->post(route('platform.catalog.media.images.store', $set), ['file' => UploadedFile::fake()->image('a.png'), 'angle' => 'rear'])
            ->assertForbidden();
        $this->actingAs($owner)->delete(route('platform.catalog.media.images.destroy', $image))->assertForbidden();
        $this->assertModelExists($image);
    }

    public function test_media_is_readable_while_active_and_deletion_removes_the_file(): void
    {
        Storage::fake(SeriesMediaImage::DISK);
        Storage::disk(SeriesMediaImage::DISK)->put('series-media/x/front.png', 'png-bytes');
        $set = SeriesMediaSet::factory()->create();
        $image = SeriesMediaImage::factory()->for($set, 'set')->create(['path' => 'series-media/x/front.png']);
        $customer = User::factory()->create();
        $manager = $this->platformUser(PlatformRole::CatalogManager);

        $this->get(route('catalog-media.show', $image))->assertRedirect(route('login'));
        $this->actingAs($customer)->get($image->url())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');

        $set->update(['status' => false]);
        $this->actingAs($customer)->get($image->url())->assertNotFound();
        $this->actingAs($manager)->get($image->url())->assertOk();

        $this->actingAs($manager)->delete(route('platform.catalog.media.images.destroy', $image))->assertRedirect();
        $this->assertModelMissing($image);
        Storage::disk(SeriesMediaImage::DISK)->assertMissing('series-media/x/front.png');
    }

    private function platformUser(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }
}
