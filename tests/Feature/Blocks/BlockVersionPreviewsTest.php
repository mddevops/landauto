<?php

namespace Tests\Feature\Blocks;

use App\Enums\DeveloperPermission;
use App\Enums\PlatformRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\BlockVersionPreview;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlockVersionPreviewsTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private BlockDefinition $block;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(BlockVersionPreview::DISK);
        $this->profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->profile->permissions()->create(['permission' => DeveloperPermission::ApproveNativeBlocks]);
        $this->block = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'promo']);
    }

    private function publishWithPreview(?UploadedFile $preview): void
    {
        $user = $this->profile->user;
        $this->actingAs($user)->put(route('developer.blocks.draft', $this->block), ['revision' => 0, 'sources' => [
            'html' => '<h2>{{ title }}</h2>',
            'css' => '',
            'js' => '',
            'schema' => '{"fields":[{"key":"title","type":"text","label":"Заголовок"}]}',
        ]])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('developer.blocks.publish', $this->block), array_filter(['revision' => 1, 'preview' => $preview]))
            ->assertSessionHasNoErrors();
    }

    public function test_publishing_with_a_screenshot_stores_the_version_preview(): void
    {
        $this->publishWithPreview(UploadedFile::fake()->image('screen.png', 1200, 700));

        $version = BlockVersion::query()->sole();
        $preview = $version->preview;
        $this->assertNotNull($preview);
        $this->assertSame(['image/png', 1200, 700], [$preview->mime_type, $preview->width, $preview->height]);
        $this->assertSame("block-previews/{$this->block->public_id}/{$preview->public_id}.png", $preview->path);
        Storage::disk(BlockVersionPreview::DISK)->assertExists($preview->path);

        $url = route('block-previews.show', $preview->public_id, false);
        $this->actingAs($this->profile->user)->get(route('developer.blocks.show', $this->block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versions.0.preview_url', $url)
                ->where('block.preview_url', $url)
                ->missing('versions.0.preview_path'));
        $this->actingAs($this->profile->user)->get(route('developer.blocks.index'))
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.preview_url', $url));

        $this->actingAs(User::factory()->create())->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->app['auth']->guard()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_publishing_without_a_preview_still_publishes_and_bad_files_are_rejected(): void
    {
        $this->publishWithPreview(null);
        $this->assertNull(BlockVersion::query()->sole()->preview);

        $version = BlockVersion::query()->sole();
        $url = route('developer.blocks.versions.preview', ['block' => $this->block->public_id, 'version' => $version->version]);

        $this->actingAs($this->profile->user)->post($url, ['preview' => UploadedFile::fake()->create('drawing.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors(['preview' => 'Поддерживаются только изображения JPEG, PNG и WebP.']);
        $this->actingAs($this->profile->user)->post($url, ['preview' => UploadedFile::fake()->image('huge.png')->size(11000)])
            ->assertSessionHasErrors('preview');
        $this->assertSame(0, BlockVersionPreview::query()->count());
    }

    public function test_owner_replaces_and_removes_a_version_preview(): void
    {
        $this->publishWithPreview(UploadedFile::fake()->image('first.png'));
        $first = BlockVersionPreview::query()->sole();
        $url = route('developer.blocks.versions.preview', ['block' => $this->block->public_id, 'version' => '1.0.0']);

        $this->actingAs($this->profile->user)->post($url, ['preview' => UploadedFile::fake()->image('second.webp')])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $this->block));

        $second = BlockVersionPreview::query()->sole();
        $this->assertNotSame($first->public_id, $second->public_id, 'a replaced preview gets a new cache-safe address');
        $this->assertSame('image/webp', $second->mime_type);
        Storage::disk(BlockVersionPreview::DISK)->assertMissing($first->path);
        Storage::disk(BlockVersionPreview::DISK)->assertExists($second->path);

        $this->actingAs($this->profile->user)->delete($url)->assertRedirect(route('developer.blocks.show', $this->block));
        $this->assertSame(0, BlockVersionPreview::query()->count());
        Storage::disk(BlockVersionPreview::DISK)->assertMissing($second->path);
    }

    public function test_only_the_block_owner_manages_previews(): void
    {
        $this->publishWithPreview(null);
        $other = DeveloperProfile::factory()->withPermissions()->create()->user;
        $url = route('developer.blocks.versions.preview', ['block' => $this->block->public_id, 'version' => '1.0.0']);

        $this->actingAs($other)->post($url, ['preview' => UploadedFile::fake()->image('x.png')])->assertNotFound();
        $this->actingAs($other)->delete($url)->assertNotFound();
        $this->actingAs($this->profile->user)
            ->post(route('developer.blocks.versions.preview', ['block' => $this->block->public_id, 'version' => '9.9.9']), ['preview' => UploadedFile::fake()->image('x.png')])
            ->assertNotFound();
        $this->assertSame(0, BlockVersionPreview::query()->count());
    }

    public function test_super_admin_adds_previews_to_official_versions_shown_in_the_designer_library(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $admin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $admin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $hero = BlockDefinition::query()->where('slug', 'hero')->sole();
        $version = $hero->versions()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('platform.blocks.versions.preview', ['block' => $hero->public_id, 'version' => $version->version]), ['preview' => UploadedFile::fake()->image('hero.jpg')])
            ->assertSessionHasNoErrors();
        $preview = BlockVersionPreview::query()->sole();
        $url = route('block-previews.show', $preview->public_id, false);

        $this->actingAs($admin)->get(route('platform.blocks.groups.show', $hero->group))
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.preview_url', $url));

        $this->actingAs($this->profile->user)->post(route('platform.blocks.versions.preview', ['block' => $hero->public_id, 'version' => $version->version]), ['preview' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
    }

    public function test_previews_of_workspace_private_blocks_are_never_served(): void
    {
        $private = BlockDefinition::factory()->workspacePrivate()->create();
        $version = BlockVersion::factory()->for($private, 'definition')->create();
        $preview = new BlockVersionPreview;
        $preview->path = 'block-previews/private.png';
        $preview->mime_type = 'image/png';
        $preview->size_bytes = 1;
        $preview->width = 1;
        $preview->height = 1;
        $preview->version()->associate($version);
        $preview->save();
        Storage::disk(BlockVersionPreview::DISK)->put($preview->path, 'x');

        $this->actingAs(User::factory()->create())->get(route('block-previews.show', $preview->public_id))->assertNotFound();
    }
}
