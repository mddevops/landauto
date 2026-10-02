<?php

namespace Tests\Feature\Database;

use App\Enums\SiteStatus;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sites_table_has_only_the_required_foundation_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('sites', [
            'id', 'public_id', 'workspace_id', 'name', 'status', 'created_at', 'updated_at',
        ]));

        foreach ([
            'user_id',
            'folder_id',
            'template_id',
            'template_version_id',
            'current_draft_version_id',
            'current_published_version_id',
            'domain',
            'seo',
            'settings',
            'plan_id',
        ] as $prematureColumn) {
            $this->assertFalse(Schema::hasColumn('sites', $prematureColumn));
        }
    }

    public function test_site_defaults_to_active_status(): void
    {
        $siteId = $this->insertSite(Workspace::factory()->create()->id);

        $this->assertSame(
            SiteStatus::Active->value,
            DB::table('sites')->where('id', $siteId)->value('status'),
        );
    }

    public function test_site_public_id_is_unique(): void
    {
        $workspace = Workspace::factory()->create();
        $publicId = (string) Str::ulid();
        $this->insertSite($workspace->id, $publicId);

        $this->expectException(QueryException::class);
        $this->insertSite($workspace->id, $publicId);
    }

    public function test_site_requires_an_existing_workspace(): void
    {
        $this->expectException(QueryException::class);
        $this->insertSite(999_999);
    }

    public function test_each_site_belongs_to_exactly_one_workspace(): void
    {
        $firstWorkspace = Workspace::factory()->create();
        $secondWorkspace = Workspace::factory()->create();
        $firstSite = $this->insertSite($firstWorkspace->id);
        $secondSite = $this->insertSite($secondWorkspace->id);

        $this->assertDatabaseHas('sites', ['id' => $firstSite, 'workspace_id' => $firstWorkspace->id]);
        $this->assertDatabaseHas('sites', ['id' => $secondSite, 'workspace_id' => $secondWorkspace->id]);
        $this->assertSame(1, DB::table('sites')->where('workspace_id', $firstWorkspace->id)->count());
        $this->assertSame(1, DB::table('sites')->where('workspace_id', $secondWorkspace->id)->count());
    }

    public function test_workspace_queries_do_not_include_another_workspaces_sites(): void
    {
        $workspace = Workspace::factory()->create();
        $foreignWorkspace = Workspace::factory()->create();
        $ownPublicId = (string) Str::ulid();
        $foreignPublicId = (string) Str::ulid();
        $this->insertSite($workspace->id, $ownPublicId);
        $this->insertSite($foreignWorkspace->id, $foreignPublicId);

        $publicIds = DB::table('sites')
            ->where('workspace_id', $workspace->id)
            ->pluck('public_id')
            ->all();

        $this->assertSame([$ownPublicId], $publicIds);
        $this->assertNotContains($foreignPublicId, $publicIds);
    }

    public function test_workspace_with_sites_cannot_be_hard_deleted_implicitly(): void
    {
        $workspace = Workspace::factory()->create();
        $siteId = $this->insertSite($workspace->id);

        try {
            DB::table('workspaces')->where('id', $workspace->id)->delete();
        } catch (QueryException) {
            $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
            $this->assertDatabaseHas('sites', ['id' => $siteId, 'workspace_id' => $workspace->id]);

            return;
        }

        $this->fail('Workspace hard deletion should be restricted while it owns Sites.');
    }

    private function insertSite(int $workspaceId, ?string $publicId = null): int
    {
        return DB::table('sites')->insertGetId([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'workspace_id' => $workspaceId,
            'name' => 'Тестовый сайт',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
