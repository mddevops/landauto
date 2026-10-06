<?php

namespace Tests\Feature\Publishing;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteVersionRestore;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VersionHistoryTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
    }

    public function test_publication_note_is_normalized_stored_and_shown_without_mutating_the_version(): void
    {
        $note = "  <b>Новые цены</b>\r\nи акция\u{0007}  ";

        $this->as($this->owner)->post(route('sites.publishing.store', $this->site), ['note' => $note])
            ->assertInertiaFlash('toast.type', 'success');

        $version = PublishedVersion::query()->sole();
        $this->assertSame("<b>Новые цены</b>\nи акция", $version->publication?->note);

        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('production.note', "<b>Новые цены</b>\nи акция")
                ->where('lastAttempt.note', "<b>Новые цены</b>\nи акция")
                ->where('versions.0.note', "<b>Новые цены</b>\nи акция")
                ->where('versions.0.publisher', $this->owner->name)
                ->missing('versions.0.id'));

        $this->expectException(LogicException::class);
        $version->publication?->update(['note' => 'Другое']);
    }

    public function test_blank_and_too_long_notes(): void
    {
        $this->as($this->owner)->post(route('sites.publishing.store', $this->site), ['note' => '   '])
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertNull(Publication::query()->sole()->note);

        $this->as($this->owner)->post(route('sites.publishing.store', $this->site), ['note' => str_repeat('я', 501)])
            ->assertSessionHasErrors(['note' => 'Комментарий не должен быть длиннее 500 символов.']);
        $this->assertSame(1, Publication::query()->count());

        $this->as($this->owner)->post(route('sites.publishing.store', $this->site), ['note' => str_repeat('я', 500)])
            ->assertInertiaFlash('toast.type', 'success');
    }

    public function test_restore_records_an_audit_entry_and_changes_only_the_draft(): void
    {
        $v1 = $this->publish();
        $this->home->forceFill(['title' => 'Главная v2'])->save();
        $v2 = $this->publish();
        $snapshot = $v1->draft_snapshot_json;

        $this->as($this->owner)->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $v1]))
            ->assertInertiaFlash('toast.type', 'success');

        $restore = SiteVersionRestore::query()->sole();
        $this->assertSame($this->site->id, $restore->site_id);
        $this->assertSame($v1->id, $restore->published_version_id);
        $this->assertSame($this->owner->id, $restore->actor_user_id);
        $this->assertSame($snapshot, $v1->fresh()?->draft_snapshot_json);
        $this->assertSame($v2->id, $this->site->fresh()?->active_published_version_id);
        $this->assertSame($snapshot['pages'][0]['title'], $this->home->fresh()?->title);
        $this->assertNotSame('Главная v2', $this->home->fresh()?->title);

        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lastRestore.version_number', 1)
                ->where('lastRestore.actor', $this->owner->name)
                ->where('versions.1.version_number', 1)
                ->where('versions.1.restores_count', 1)
                ->where('versions.1.last_restore.actor', $this->owner->name)
                ->where('versions.0.restores_count', 0)
                ->where('versions.0.last_restore', null)
                ->where('production.version_number', 2)
                ->where('production.has_unpublished_changes', true));
    }

    public function test_unpublished_changes_indicator_follows_the_draft(): void
    {
        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('production', null));

        $this->publish();
        $this->assertDraftChanged(false);

        $this->home->forceFill(['title' => 'Новый заголовок'])->save();
        $this->assertDraftChanged(true);

        $this->publish();
        $this->assertDraftChanged(false);

        // Hidden blocks are not part of what visitors see.
        $this->place('cta', ['title' => 'Скрытый блок'], hidden: true);
        $this->assertDraftChanged(false);
    }

    public function test_publisher_can_publish_and_view_history_but_cannot_restore(): void
    {
        $publisher = $this->member(WorkspaceRole::Publisher);
        $this->as($publisher)->post(route('sites.publishing.store', $this->site), ['note' => 'Релиз'])
            ->assertInertiaFlash('toast.type', 'success');
        $version = PublishedVersion::query()->sole();

        $this->as($publisher)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.publish', true)
                ->where('can.restoreVersion', false)
                ->where('versions.0.publisher', $publisher->name));

        $this->as($publisher)->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $version]))
            ->assertForbidden();
        $this->assertSame(0, SiteVersionRestore::query()->count());

        $this->as($this->owner)->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $version]))
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertSame(1, SiteVersionRestore::query()->count());
    }

    public function test_history_respects_site_access(): void
    {
        $other = Site::factory()->for($this->workspace)->create();
        $member = $this->workspace->addMember(User::factory()->create(), WorkspaceRole::Publisher);
        $member->forceFill(['site_access_mode' => SiteAccessMode::SelectedSites])->save();
        $member->sites()->attach($other->id);

        $this->as($member->user)->get(route('sites.publishing.show', $this->site))->assertNotFound();
        $this->as($member->user)->post(route('sites.publishing.store', $this->site))->assertNotFound();
        $this->as($member->user)->get(route('sites.publishing.show', $other))->assertOk();
    }

    public function test_history_is_paginated_deterministically(): void
    {
        foreach (range(1, 23) as $number) {
            PublishedVersion::factory()->for($this->site)->create(['version_number' => $number]);
        }

        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->has('versions', 20)
                ->where('versions.0.version_number', 23)
                ->where('versions.19.version_number', 4)
                ->where('versionsPage', ['current' => 1, 'last' => 2, 'total' => 23, 'per_page' => 20]));

        $this->as($this->owner)->get(route('sites.publishing.show', ['site' => $this->site, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('versions', 3)
                ->where('versions.0.version_number', 3)
                ->where('versions.2.version_number', 1));

        $this->as($this->owner)->get(route('sites.publishing.show', ['site' => $this->site, 'page' => -4]))
            ->assertInertia(fn (Assert $page) => $page->where('versionsPage.current', 1));
    }

    private function assertDraftChanged(bool $changed): void
    {
        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('production.has_unpublished_changes', $changed));
    }

    private function publish(): PublishedVersion
    {
        $this->as($this->owner)->post(route('sites.publishing.store', $this->site))
            ->assertInertiaFlash('toast.type', 'success');

        return PublishedVersion::query()->where('site_id', $this->site->id)->orderByDesc('version_number')->firstOrFail();
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }
}
