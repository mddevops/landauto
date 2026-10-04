<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(SiteAsset::DISK);
    }

    public function test_designer_uploads_image_to_server_generated_private_path(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Designer);

        $this->as($user, $workspace)->from(route('sites.designer', $site))
            ->post(route('sites.assets.store', $site), ['file' => UploadedFile::fake()->image('../../evil name.png', 640, 480)])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.designer', $site));

        $asset = $site->assets()->sole();
        $this->assertSame("site-assets/{$site->public_id}/{$asset->public_id}.png", $asset->path);
        $this->assertSame(['image/png', 640, 480], [$asset->mime_type, $asset->width, $asset->height]);
        Storage::disk(SiteAsset::DISK)->assertExists($asset->path);

        $this->as($user, $workspace)->get(route('sites.designer', $site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('assets.0.public_id', $asset->public_id)
                ->where('assets.0.url', route('sites.assets.show', [$site, $asset], false))
                ->where('can.manageAssets', true)
                ->missing('assets.0.id')
                ->missing('assets.0.path'));
    }

    public function test_svg_non_images_and_oversized_files_are_rejected(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::Owner);
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $disguised = UploadedFile::fake()->createWithContent('photo.png', '<?php echo 1; ?>');

        $this->as($user, $workspace)->post(route('sites.assets.store', $site), ['file' => $svg])
            ->assertSessionHasErrors(['file' => 'Поддерживаются только изображения JPEG, PNG и WebP.']);
        $this->as($user, $workspace)->post(route('sites.assets.store', $site), ['file' => $disguised])
            ->assertSessionHasErrors('file');

        $this->as($user, $workspace)->post(route('sites.assets.store', $site), ['file' => UploadedFile::fake()->image('big.jpg')->size(10241)])
            ->assertSessionHasErrors(['file' => 'Размер файла не должен превышать 10 МБ.']);
        $this->as($user, $workspace)->post(route('sites.assets.store', $site), [])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, SiteAsset::query()->count());
        $this->assertSame([], Storage::disk(SiteAsset::DISK)->allFiles());
    }

    public function test_content_editor_cannot_upload_and_foreign_site_is_not_found(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);
        $foreign = Site::factory()->create();

        $this->as($user, $workspace)->post(route('sites.assets.store', $site), ['file' => UploadedFile::fake()->image('a.png')])->assertForbidden();
        $this->as($user, $workspace)->post(route('sites.assets.store', $foreign), [])->assertNotFound();
        $this->assertSame(0, SiteAsset::query()->count());
    }

    public function test_asset_is_served_with_nosniff_only_within_its_site(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);
        $asset = SiteAsset::factory()->for($site)->create();
        Storage::disk(SiteAsset::DISK)->put($asset->path, 'png-bytes');
        $sibling = Site::factory()->for($workspace)->create();
        $foreignAsset = SiteAsset::factory()->create();

        $response = $this->as($user, $workspace)->get(route('sites.assets.show', [$site, $asset]))->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('image/png', $response->headers->get('Content-Type'));

        $this->as($user, $workspace)->get(route('sites.assets.show', [$sibling, $asset]))->assertNotFound();
        $this->as($user, $workspace)->get(route('sites.assets.show', [$foreignAsset->site, $foreignAsset]))->assertNotFound();
    }

    public function test_guest_cannot_fetch_assets(): void
    {
        $asset = SiteAsset::factory()->create();

        $this->get(route('sites.assets.show', [$asset->site, $asset]))->assertRedirect(route('login'));
    }

    public function test_block_image_field_accepts_only_assets_of_the_same_site(): void
    {
        [$user, $workspace, $site] = $this->siteFor(WorkspaceRole::ContentEditor);
        $this->seed(OfficialBlockSeeder::class);
        $hero = BlockVersion::query()->whereRelation('definition', 'slug', 'hero')->latest('id')->firstOrFail();
        $block = BlockInstance::factory()->for($site->homePage()->sole())->for($hero, 'version')->create(['state_json' => []]);
        $own = SiteAsset::factory()->for($site)->create();
        $other = SiteAsset::factory()->for(Site::factory()->for($workspace))->create();
        $url = route('sites.blocks.state', [$site, $block]);

        $this->as($user, $workspace)->patch($url, ['state' => ['image' => $own->public_id]])->assertSessionHasNoErrors();
        $this->assertSame($own->public_id, $block->fresh()?->state_json['image']);

        $this->as($user, $workspace)->patch($url, ['state' => ['image' => $other->public_id]])
            ->assertSessionHasErrors(['state.image' => 'Изображение не найдено в библиотеке этого сайта.']);
        $this->as($user, $workspace)->patch($url, ['state' => ['image' => 'https://example.com/car.jpg']])
            ->assertSessionHasErrors('state.image');
        $this->assertSame($own->public_id, $block->fresh()?->state_json['image']);
    }

    /**
     * @return array{User, Workspace, Site}
     */
    private function siteFor(WorkspaceRole $role): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);
        $site = Site::factory()->for($workspace)->create();
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($site)->save();

        return [$user, $workspace, $site];
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
