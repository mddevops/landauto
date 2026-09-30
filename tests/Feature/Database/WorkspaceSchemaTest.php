<?php

namespace Tests\Feature\Database;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspaces_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('workspaces', [
            'id', 'public_id', 'name', 'status', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('workspaces', 'owner_user_id'));
    }

    public function test_workspace_members_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('workspace_members', [
            'id', 'public_id', 'workspace_id', 'user_id', 'role', 'status', 'joined_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_new_workspace_and_membership_default_to_active_status(): void
    {
        $workspaceId = $this->insertWorkspace();
        $memberId = $this->insertMember($workspaceId, User::factory()->create()->id);

        $this->assertSame(WorkspaceStatus::Active->value, DB::table('workspaces')->where('id', $workspaceId)->value('status'));
        $this->assertSame(WorkspaceMemberStatus::Active->value, DB::table('workspace_members')->where('id', $memberId)->value('status'));
    }

    public function test_workspace_public_id_is_unique(): void
    {
        $publicId = (string) Str::ulid();
        $this->insertWorkspace($publicId);

        $this->expectException(QueryException::class);
        $this->insertWorkspace($publicId);
    }

    public function test_membership_public_id_is_unique(): void
    {
        $workspaceId = $this->insertWorkspace();
        $publicId = (string) Str::ulid();
        $this->insertMember($workspaceId, User::factory()->create()->id, $publicId);

        $this->expectException(QueryException::class);
        $this->insertMember($workspaceId, User::factory()->create()->id, $publicId);
    }

    public function test_user_can_be_a_member_of_a_workspace_only_once(): void
    {
        $workspaceId = $this->insertWorkspace();
        $userId = User::factory()->create()->id;
        $this->insertMember($workspaceId, $userId);

        $this->expectException(QueryException::class);
        $this->insertMember($workspaceId, $userId, role: 'admin');
    }

    public function test_user_can_be_a_member_of_several_workspaces(): void
    {
        $userId = User::factory()->create()->id;
        $this->insertMember($this->insertWorkspace(), $userId);
        $this->insertMember($this->insertWorkspace(), $userId);

        $this->assertSame(2, DB::table('workspace_members')->where('user_id', $userId)->count());
    }

    public function test_membership_requires_existing_workspace_and_user(): void
    {
        $userId = User::factory()->create()->id;
        $workspaceId = $this->insertWorkspace();

        $this->assertQueryFails(fn () => $this->insertMember(999_999, $userId));
        $this->assertQueryFails(fn () => $this->insertMember($workspaceId, 999_999));
        $this->assertSame(0, DB::table('workspace_members')->count());
    }

    public function test_deleting_a_workspace_deletes_its_memberships(): void
    {
        $workspaceId = $this->insertWorkspace();
        $this->insertMember($workspaceId, User::factory()->create()->id);

        DB::table('workspaces')->where('id', $workspaceId)->delete();

        $this->assertSame(0, DB::table('workspace_members')->where('workspace_id', $workspaceId)->count());
    }

    public function test_user_with_a_membership_cannot_be_deleted_implicitly(): void
    {
        $user = User::factory()->create();
        $workspaceId = $this->insertWorkspace();
        $this->insertMember($workspaceId, $user->id);

        $this->assertQueryFails(fn () => $user->delete());

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspaceId, 'user_id' => $user->id]);
    }

    private function assertQueryFails(callable $query): void
    {
        try {
            $query();
        } catch (QueryException) {
            return;
        }

        $this->fail('The query was expected to violate a database constraint.');
    }

    private function insertWorkspace(?string $publicId = null): int
    {
        return DB::table('workspaces')->insertGetId([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'name' => 'Рабочее пространство',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMember(int $workspaceId, int $userId, ?string $publicId = null, string $role = 'owner'): int
    {
        return DB::table('workspace_members')->insertGetId([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
