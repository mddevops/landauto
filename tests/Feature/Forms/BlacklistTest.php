<?php

namespace Tests\Feature\Forms;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Forms\SubmissionGuard;
use App\Models\BlacklistEntry;
use App\Models\Form;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class BlacklistTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
    }

    public function test_global_workspace_and_site_entries_block_ip_and_phone(): void
    {
        $this->entry(BlacklistScope::Global, BlacklistType::Ip, '10.0.0.66');
        $this->entry(BlacklistScope::Workspace, BlacklistType::Phone, '79990000001', workspace: $this->workspace);
        $this->entry(BlacklistScope::Site, BlacklistType::Ip, '2001:db8::1', site: $this->site);
        $this->entry(BlacklistScope::Site, BlacklistType::Phone, '79990000002', site: $this->site);

        $this->submit('79991112233', '10.0.0.66')->assertUnprocessable()->assertJsonPath('message', SubmissionGuard::REJECTION_MESSAGE);
        $this->submit('+7 (999) 000-00-01', '10.0.0.2')->assertUnprocessable();
        $this->submit('79991112234', '2001:0db8:0000::0001')->assertUnprocessable();
        $this->submit('7 999 000 00 02', '10.0.0.3')->assertUnprocessable();
        $this->assertSame(0, Submission::query()->count());

        $this->submit('79991112235', '10.0.0.4')->assertCreated();
    }

    public function test_expired_entries_are_ignored(): void
    {
        $this->entry(BlacklistScope::Site, BlacklistType::Phone, '79990000001', site: $this->site, expiresAt: now()->subMinute());
        $this->entry(BlacklistScope::Global, BlacklistType::Ip, '10.0.0.9', expiresAt: now()->addDay());

        $this->submit('79990000001', '10.0.0.1')->assertCreated();
        $this->submit('79990000003', '10.0.0.9')->assertUnprocessable();

        $this->travel(2)->days();
        $this->submit('79990000004', '10.0.0.9')->assertCreated();
    }

    public function test_tenant_entries_do_not_leak_to_other_workspaces_or_sites(): void
    {
        $sibling = Form::factory()->for(Site::factory()->for($this->workspace))->withLeadFields()->create();
        $foreign = Form::factory()->withLeadFields()->create();
        $this->entry(BlacklistScope::Site, BlacklistType::Phone, '79990000001', site: $this->site);
        $this->entry(BlacklistScope::Workspace, BlacklistType::Phone, '79990000002', workspace: $this->workspace);

        $this->submit('79990000001', '10.0.0.1', $sibling)->assertCreated();
        $this->submit('79990000002', '10.0.0.2', $sibling)->assertUnprocessable();
        $this->submit('79990000001', '10.0.0.3', $foreign)->assertCreated();
        $this->submit('79990000002', '10.0.0.4', $foreign)->assertCreated();
    }

    public function test_entry_scope_columns_must_be_consistent(): void
    {
        $entry = new BlacklistEntry;
        $entry->scope = BlacklistScope::Global;
        $entry->site_id = $this->site->id;
        $entry->type = BlacklistType::Ip;
        $entry->value = '10.0.0.1';

        $this->expectException(LogicException::class);
        $entry->save();
    }

    public function test_tenant_management_follows_permissions_and_never_exposes_global_entries(): void
    {
        $owner = $this->member(WorkspaceRole::Owner);
        $admin = $this->member(WorkspaceRole::Admin);
        $designer = $this->member(WorkspaceRole::Designer);
        $global = $this->entry(BlacklistScope::Global, BlacklistType::Ip, '10.0.0.66');
        $store = fn (User $user, array $payload) => $this->as($user)->post(route('sites.blacklist.store', $this->site), $payload);

        $store($designer, ['scope' => 'site', 'type' => 'phone', 'value' => '79990000001'])->assertForbidden();
        $store($admin, ['scope' => 'workspace', 'type' => 'phone', 'value' => '79990000001'])->assertForbidden();
        $store($owner, ['scope' => 'global', 'type' => 'ip', 'value' => '10.0.0.1'])->assertSessionHasErrors('scope');
        $store($owner, ['scope' => 'site', 'type' => 'ip', 'value' => '999.1.1.1'])->assertSessionHasErrors('value');
        $store($owner, ['scope' => 'site', 'type' => 'phone', 'value' => '+7 (999) 000-00-01', 'reason' => 'Спам', 'expires_in_days' => 7])->assertSessionHasNoErrors();
        $store($owner, ['scope' => 'workspace', 'type' => 'ip', 'value' => '10.0.0.5', 'workspace_id' => Workspace::factory()->create()->id])->assertSessionHasNoErrors();

        $siteEntry = BlacklistEntry::query()->where('scope', 'site')->sole();
        $workspaceEntry = BlacklistEntry::query()->where('scope', 'workspace')->sole();
        $this->assertSame(['79990000001', $this->site->id, $owner->id], [$siteEntry->value, $siteEntry->site_id, $siteEntry->created_by_user_id]);
        $this->assertSame($this->workspace->id, $workspaceEntry->workspace_id);

        $this->as($owner)->get(route('sites.form-security.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blacklist.site', 1)
                ->has('blacklist.workspace', 1)
                ->where('blacklist.site.0.value', '79990000001')
                ->missing('blacklist.global'));
        $this->as($admin)->get(route('sites.form-security.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->has('blacklist.site', 1)->has('blacklist.workspace', 0));
        $this->as($designer)->get(route('sites.form-security.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->has('blacklist.site', 0)->has('blacklist.workspace', 0));

        $this->as($designer)->delete(route('sites.blacklist.destroy', [$this->site, $siteEntry->public_id]))->assertForbidden();
        $this->as($admin)->delete(route('sites.blacklist.destroy', [$this->site, $workspaceEntry->public_id]))->assertForbidden();
        $this->as($owner)->delete(route('sites.blacklist.destroy', [$this->site, $global->public_id]))->assertNotFound();
        $this->as($owner)->delete(route('sites.blacklist.destroy', [Site::factory()->for($this->workspace)->create(), $siteEntry->public_id]))->assertNotFound();
        $this->as($owner)->delete(route('sites.blacklist.destroy', [$this->site, $siteEntry->public_id]))->assertRedirect();
        $this->assertModelMissing($siteEntry);
        $this->assertModelExists($global);
    }

    public function test_global_entries_are_managed_only_by_audited_operator_command(): void
    {
        Log::spy();
        $operator = User::factory()->create(['email' => 'ops@landflow.test']);
        PlatformRoleAssignment::query()->create(['user_id' => $operator->id, 'role' => PlatformRole::SuperAdmin->value]);
        User::factory()->create(['email' => 'nobody@landflow.test']);

        $this->artisan('blacklist:global', ['action' => 'add', '--type' => 'phone', '--value' => '+7 999 000-00-09', '--reason' => 'Массовый спам', '--actor' => 'nobody@landflow.test'])->assertFailed();
        $this->artisan('blacklist:global', ['action' => 'add', '--type' => 'phone', '--value' => '+7 999 000-00-09', '--actor' => 'ops@landflow.test'])->assertFailed();
        $this->artisan('blacklist:global', ['action' => 'add', '--type' => 'phone', '--value' => '+7 999 000-00-09', '--reason' => 'Массовый спам', '--actor' => 'ops@landflow.test'])->assertSuccessful();

        $entry = BlacklistEntry::query()->sole();
        $this->assertSame([BlacklistScope::Global, '79990000009', $operator->id], [$entry->scope, $entry->value, $entry->created_by_user_id]);
        $this->submit('79990000009', '10.0.0.1')->assertUnprocessable();

        $this->artisan('blacklist:global', ['action' => 'remove', '--entry' => $entry->public_id, '--reason' => 'Ошибка', '--actor' => 'ops@landflow.test'])->assertSuccessful();
        $this->assertModelExists($entry);
        $this->submit('79990000009', '10.0.0.1')->assertCreated();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'Global blacklist entry added.' && $context['actor_user_id'] === $operator->id && ! in_array('79990000009', $context, true))->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => $message === 'Global blacklist entry expired.')->once();
    }

    private function entry(BlacklistScope $scope, BlacklistType $type, string $value, ?Workspace $workspace = null, ?Site $site = null, mixed $expiresAt = null): BlacklistEntry
    {
        $entry = new BlacklistEntry(['expires_at' => $expiresAt]);
        $entry->scope = $scope;
        $entry->type = $type;
        $entry->value = $type === BlacklistType::Ip ? (string) inet_ntop((string) inet_pton($value)) : $value;
        $entry->workspace_id = $workspace?->id;
        $entry->site_id = $site?->id;
        $entry->save();

        return $entry;
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }

    private function submit(string $phone, string $ip, ?Form $form = null): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('forms.submissions.store', ($form ?? $this->form)->public_id), [
            'fields' => ['name' => 'Иван', 'phone' => $phone, 'consent' => true],
        ]);
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
