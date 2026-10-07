<?php

namespace Tests\Feature\Workspaces;

use App\Enums\SiteAccessMode;
use App\Enums\SiteStatus;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\GrantsRolePermissions;
use Tests\TestCase;

class WorkspaceAssetsTest extends TestCase
{
    use GrantsRolePermissions, RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(WorkspaceAsset::DISK);
        $this->workspace = Workspace::factory()->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Сайт А']);
    }

    public function test_owner_uploads_to_a_server_generated_private_path(): void
    {
        $this->as($this->owner)
            ->post(route('workspace.assets.store'), ['file' => UploadedFile::fake()->image('../../logo салона.png', 320, 200)])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        $asset = WorkspaceAsset::query()->sole();
        $this->assertSame("workspace-assets/{$this->workspace->public_id}/{$asset->public_id}.png", $asset->path);
        $this->assertSame(['image/png', 320, 200, $this->workspace->id], [$asset->mime_type, $asset->width, $asset->height, $asset->workspace_id]);
        Storage::disk(WorkspaceAsset::DISK)->assertExists($asset->path);

        $this->as($this->owner)
            ->get(route('workspace.assets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/assets/index')
                ->where('assets.0.public_id', $asset->public_id)
                ->where('assets.0.url', route('workspace.assets.show', $asset->public_id, false))
                ->where('sites.0.public_id', $this->site->public_id)
                ->missing('assets.0.path')
                ->missing('assets.0.id')
                ->missing('sites.0.id'));

        $this->as($this->owner)->get(route('workspace.assets.show', $asset->public_id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_upload_reuses_the_shared_image_restrictions(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $disguised = UploadedFile::fake()->createWithContent('photo.png', '<?php echo 1; ?>');

        $this->as($this->owner)->post(route('workspace.assets.store'), ['file' => $svg])
            ->assertSessionHasErrors(['file' => 'Поддерживаются только изображения JPEG, PNG и WebP.']);
        $this->as($this->owner)->post(route('workspace.assets.store'), ['file' => $disguised])->assertSessionHasErrors('file');
        $this->as($this->owner)->post(route('workspace.assets.store'), ['file' => UploadedFile::fake()->image('big.jpg')->size(10241)])
            ->assertSessionHasErrors(['file' => 'Размер файла не должен превышать 10 МБ.']);
        $this->as($this->owner)->post(route('workspace.assets.store'), ['file' => UploadedFile::fake()->image('huge.png', 10001, 10)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, WorkspaceAsset::query()->count());
        $this->assertSame([], Storage::disk(WorkspaceAsset::DISK)->allFiles());
    }

    public function test_members_without_the_media_permission_are_denied(): void
    {
        $asset = $this->storedAsset();

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);

            $this->as($member)->get(route('workspace.assets.index'))->assertForbidden();
            $this->as($member)->post(route('workspace.assets.store'), ['file' => UploadedFile::fake()->image('a.png')])->assertForbidden();
            $this->as($member)->get(route('workspace.assets.show', $asset->public_id))->assertForbidden();
            $this->as($member)->post(route('workspace.assets.copy', $asset->public_id), ['site' => $this->site->public_id])->assertForbidden();
            $this->as($member)->delete(route('workspace.assets.destroy', $asset->public_id))->assertForbidden();
        }

        $this->assertSame(1, WorkspaceAsset::query()->count());
        $this->assertSame(0, SiteAsset::query()->count());
    }

    public function test_another_workspace_cannot_list_download_copy_or_delete(): void
    {
        $asset = $this->storedAsset();
        $other = Workspace::factory()->create();
        $otherSite = Site::factory()->for($other)->create();
        $intruder = User::factory()->create();
        $other->addMember($intruder, WorkspaceRole::Owner);
        $as = fn () => $this->actingAs($intruder)->withSession([WorkspaceContext::SESSION_KEY => $other->public_id]);

        $as()->get(route('workspace.assets.index'))->assertInertia(fn (Assert $page) => $page->has('assets', 0));
        $as()->get(route('workspace.assets.show', $asset->public_id))->assertNotFound();
        $as()->post(route('workspace.assets.copy', $asset->public_id), ['site' => $otherSite->public_id])->assertNotFound();
        $as()->delete(route('workspace.assets.destroy', $asset->public_id))->assertNotFound();

        // An asset is never copied into a Site of another Workspace.
        $this->as($this->owner)->post(route('workspace.assets.copy', $asset->public_id), ['site' => $otherSite->public_id])->assertSessionHasErrors('site');

        $this->assertSame(0, SiteAsset::query()->count());
        Storage::disk(WorkspaceAsset::DISK)->assertExists($asset->path);
    }

    public function test_copy_creates_an_independent_site_asset_that_survives_library_deletion(): void
    {
        $asset = $this->storedAsset();

        $this->as($this->owner)
            ->post(route('workspace.assets.copy', $asset->public_id), ['site' => $this->site->public_id, 'path' => '../../.env'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Изображение скопировано на сайт «Сайт А».');

        $copy = $this->site->assets()->sole();
        $this->assertNotSame($asset->public_id, $copy->public_id);
        $this->assertSame("site-assets/{$this->site->public_id}/{$copy->public_id}.png", $copy->path);
        $this->assertSame([$asset->original_name, $asset->mime_type, $asset->size_bytes, $asset->width, $asset->height], [$copy->original_name, $copy->mime_type, $copy->size_bytes, $copy->width, $copy->height]);
        $disk = Storage::disk(WorkspaceAsset::DISK);
        $this->assertSame($disk->get($asset->path), $disk->get($copy->path));

        $this->as($this->owner)->delete(route('workspace.assets.destroy', $asset->public_id))->assertSessionHasNoErrors();

        $this->assertNull($asset->fresh());
        $disk->assertMissing($asset->path);
        $this->assertNotNull($copy->fresh());
        $disk->assertExists($copy->path);
        $this->as($this->owner)->get(route('sites.assets.show', [$this->site, $copy]))->assertOk();
    }

    public function test_selected_site_member_copies_only_to_assigned_sites_where_assets_are_managed(): void
    {
        $asset = $this->storedAsset();
        $unassigned = Site::factory()->for($this->workspace)->create(['name' => 'Сайт Б']);
        $archived = Site::factory()->for($this->workspace)->create(['status' => SiteStatus::Archived]);
        $this->grant(WorkspaceRole::Designer, [WorkspacePermission::ViewSite, WorkspacePermission::ManageAssets, WorkspacePermission::ManageWorkspaceAssets]);
        $member = $this->workspace->addMember(User::factory()->create(), WorkspaceRole::Designer);
        $member->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $member->sites()->attach([$this->site->id, $archived->id]);

        $this->as($member->user)
            ->get(route('workspace.assets.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sites', 1)->where('sites.0.public_id', $this->site->public_id));

        foreach ([$unassigned->public_id, $archived->public_id, (string) $this->site->id] as $site) {
            $this->as($member->user)->post(route('workspace.assets.copy', $asset->public_id), ['site' => $site])->assertSessionHasErrors('site');
        }

        $this->assertSame(0, SiteAsset::query()->count());

        $this->as($member->user)->post(route('workspace.assets.copy', $asset->public_id), ['site' => $this->site->public_id])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->site->assets()->count());

        $this->grant(WorkspaceRole::Designer, [WorkspacePermission::ViewSite, WorkspacePermission::ManageWorkspaceAssets]);
        $this->as($member->user)->post(route('workspace.assets.copy', $asset->public_id), ['site' => $this->site->public_id])->assertSessionHasErrors('site');
    }

    private function storedAsset(): WorkspaceAsset
    {
        $this->as($this->owner)->post(route('workspace.assets.store'), ['file' => UploadedFile::fake()->image('logo.png', 64, 64)])->assertSessionHasNoErrors();

        return WorkspaceAsset::query()->latest('id')->firstOrFail();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
