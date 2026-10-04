<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublicationStatus;
use App\Enums\PublishedVersionStatus;
use App\Enums\PublishFailure;
use App\Enums\WorkspaceRole;
use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class PublicationRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_attempt_records_actor_and_version(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create();
        $publication = $this->start($site, $user);

        $publication->moveTo(PublicationStatus::Building);
        $publication->moveTo(PublicationStatus::Activating);
        $version = PublishedVersion::factory()->for($site)->create();
        $version->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);
        $publication->succeed($version);

        $publication->refresh();
        $this->assertSame(PublicationStatus::Succeeded, $publication->status);
        $this->assertTrue($publication->actor?->is($user));
        $this->assertTrue($publication->version?->is($version));
        $this->assertNotNull($publication->completed_at);
        $this->assertArrayNotHasKey('actor_user_id', $publication->toArray());
    }

    public function test_failure_stores_only_safe_code_summary_and_metadata(): void
    {
        $publication = $this->start(Site::factory()->create(), User::factory()->create());

        $publication->fail(PublishFailure::ValidationFailed, ['errors' => ['home_page_missing']]);

        $publication->refresh();
        $this->assertSame(PublicationStatus::Failed, $publication->status);
        $this->assertSame('validation_failed', $publication->safe_error_code);
        $this->assertSame(PublishFailure::ValidationFailed->summary(), $publication->safe_error_summary);
        $this->assertSame(['errors' => ['home_page_missing']], $publication->metadata_json);
        $this->assertNull($publication->published_version_id);
    }

    public function test_status_transitions_are_enforced_and_finished_attempts_are_frozen(): void
    {
        $publication = $this->start(Site::factory()->create(), User::factory()->create());

        try {
            $publication->moveTo(PublicationStatus::Succeeded);
            $this->fail('Skipped straight to succeeded.');
        } catch (LogicException) {
            $publication->refresh();
        }

        $publication->fail(PublishFailure::Internal);

        try {
            $publication->moveTo(PublicationStatus::Building);
            $this->fail('A failed attempt was resumed.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $publication->delete();
    }

    public function test_publish_and_restore_follow_the_permission_matrix(): void
    {
        $workspace = Workspace::factory()->create();
        $site = Site::factory()->for($workspace)->create();
        $foreignSite = Site::factory()->create();

        $expected = [
            WorkspaceRole::Owner->value => [true, true],
            WorkspaceRole::Admin->value => [true, false],
            WorkspaceRole::Designer->value => [false, false],
            WorkspaceRole::ContentEditor->value => [false, false],
        ];

        foreach ($expected as $role => [$publish, $restore]) {
            $user = User::factory()->create();
            $workspace->addMember($user, WorkspaceRole::from($role));
            app(WorkspaceContext::class)->resolve($user, app('session.store'));

            $this->assertSame($publish, Gate::forUser($user)->allows('publish', $site), "{$role} publish");
            $this->assertSame($restore, Gate::forUser($user)->allows('restoreVersion', $site), "{$role} restore");
            $this->assertFalse(Gate::forUser($user)->allows('publish', $foreignSite), "{$role} foreign publish");
            $this->assertFalse(Gate::forUser($user)->allows('restoreVersion', $foreignSite), "{$role} foreign restore");

            app('session.store')->flush();
        }
    }

    private function start(Site $site, User $user): Publication
    {
        return Publication::query()->create([
            'site_id' => $site->id,
            'actor_user_id' => $user->id,
            'started_at' => now(),
        ]);
    }
}
