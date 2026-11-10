<?php

namespace Tests\Feature\Platform;

use App\Enums\Entitlement;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\Form;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
use App\Platform\PlatformSettings;
use App\Support\DefaultWorkspacePlan;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformSettingsAndStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_platform_settings_and_sync_free_storage(): void
    {
        $admin = User::factory()->create();
        PlatformRoleAssignment::query()->create([
            'user_id' => $admin->id,
            'role' => PlatformRole::SuperAdmin->value,
        ]);
        app(DefaultWorkspacePlan::class)->resolve();

        $this->actingAs($admin)
            ->put(route('platform.settings.update'), [
                'submission_retention_months' => 18,
                'account_deletion_grace_days' => 14,
                'free_max_storage_mb' => 750,
            ])
            ->assertRedirect(route('platform.settings.edit'));

        $settings = app(PlatformSettings::class);
        $this->assertSame(18, $settings->submissionRetentionMonths());
        $this->assertSame(14, $settings->accountDeletionGraceDays());
        $this->assertSame(750, $settings->freeMaxStorageMb());
        $this->assertSame(750, (int) app(DefaultWorkspacePlan::class)->resolve()
            ->entitlements()->where('key', Entitlement::MaxStorageMb)->value('integer_value'));
    }

    public function test_site_asset_upload_respects_workspace_storage_quota(): void
    {
        Storage::fake(SiteAsset::DISK);

        $owner = User::factory()->create();
        $plan = app(DefaultWorkspacePlan::class)->resolve();
        $plan->setEntitlement(Entitlement::MaxStorageMb, 1);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create();
        SiteAsset::factory()->for($site)->create(['size_bytes' => 1_048_576]);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('sites.assets.store', $site), [
                'file' => UploadedFile::fake()->image('extra.jpg', 40, 40),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_purge_command_deletes_expired_submissions_and_due_accounts(): void
    {
        $due = User::factory()->create(['pending_deletion_at' => now()->subDay()]);
        $emptyWorkspace = Workspace::factory()->create();
        $emptyWorkspace->addMember($due, WorkspaceRole::Owner);

        $keeper = User::factory()->create();
        $siteWorkspace = Workspace::factory()->create();
        $siteWorkspace->addMember($keeper, WorkspaceRole::Owner);
        $site = Site::factory()->for($siteWorkspace)->create();
        $form = Form::factory()->for($site)->create();

        $expired = Submission::factory()->for($form)->create([
            'site_id' => $site->id,
            'submitted_at' => now()->subMonths(13),
        ]);
        $fresh = Submission::factory()->for($form)->create([
            'site_id' => $site->id,
            'submitted_at' => now()->subDays(3),
        ]);

        $this->artisan('landflow:purge-personal-data')->assertSuccessful();

        $this->assertDatabaseMissing('submissions', ['id' => $expired->id]);
        $this->assertDatabaseHas('submissions', ['id' => $fresh->id]);
        $this->assertDatabaseMissing('users', ['id' => $due->id]);
    }
}
