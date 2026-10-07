<?php

namespace Tests\Feature\Workspaces;

use App\Actions\Accounts\CreateNewAccount;
use App\Enums\Entitlement;
use App\Enums\SiteType;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class DefaultFreePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_account_workspace_gets_active_free_plan_with_two_sites(): void
    {
        $workspace = $this->register('owner@example.com')->workspaces()->sole();
        $plan = $workspace->plan;

        $this->assertNotNull($plan);
        $this->assertSame(Plan::FREE_KEY, $plan->key);
        $this->assertTrue($plan->is_active);
        $this->assertSame(2, app(WorkspaceEntitlements::class)->limit($workspace, Entitlement::MaxSites));
        $this->assertSame([Entitlement::MaxSites], $plan->entitlements()->pluck('key')->all());
    }

    public function test_free_plan_is_shared_idempotently_and_never_overwritten(): void
    {
        $first = $this->register('first@example.com')->workspaces()->sole();
        Plan::query()->where('key', Plan::FREE_KEY)->sole()->setEntitlement(Entitlement::MaxSites, 5);

        $second = app(CreateNewAccount::class)->create([
            'name' => 'Второй владелец',
            'email' => 'second@example.com',
            'password' => null,
        ])->workspaces()->sole();

        $this->assertSame(1, Plan::query()->where('key', Plan::FREE_KEY)->count());
        $this->assertSame($first->plan_id, $second->plan_id);
        $this->assertSame(5, app(WorkspaceEntitlements::class)->limit($second, Entitlement::MaxSites));
    }

    public function test_verified_new_account_can_create_two_active_sites_but_not_a_third(): void
    {
        $user = $this->register('creator@example.com');
        $user->markEmailAsVerified();
        $workspace = $user->workspaces()->sole();
        $template = Template::factory()->create();

        $this->createSite($user, $workspace, $template, 'Первый сайт')->assertSessionHasNoErrors();
        $this->createSite($user, $workspace, $template, 'Второй сайт')->assertSessionHasNoErrors();
        $this->createSite($user, $workspace, $template, 'Третий сайт')->assertSessionHasErrors('site');

        $this->assertSame(2, $workspace->sites()->count());

        $workspace->sites()->firstOrFail()->forceFill(['status' => 'archived'])->save();

        $this->createSite($user, $workspace, $template, 'Третий сайт')->assertSessionHasNoErrors();
        $this->assertSame(3, $workspace->sites()->count());
        $this->assertSame(2, Site::query()->where('status', 'active')->count());
    }

    public function test_free_plan_cannot_create_multi_page_sites(): void
    {
        $user = $this->register('landing@example.com');
        $user->markEmailAsVerified();
        $workspace = $user->workspaces()->sole();

        $this->assertFalse(app(WorkspaceEntitlements::class)->allows($workspace, Entitlement::MultiPageSites));
        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('sites.store'), ['name' => 'Много страниц', 'site_type' => SiteType::MultiPage->value, 'start' => 'blank'])
            ->assertSessionHasErrors('site_type');
        $this->assertSame(0, $workspace->sites()->count());
    }

    public function test_business_code_has_no_raw_plan_name_checks(): void
    {
        $allowed = [
            $this->normalize(app_path('Models/Plan.php')),
            $this->normalize(app_path('Support/DefaultWorkspacePlan.php')),
            // Catalog access mode value (D-079), not a plan name.
            $this->normalize(app_path('Enums/CatalogAccessMode.php')),
        ];
        $offenders = [];

        foreach ([app_path(), resource_path('js')] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $path = $this->normalize($file->getPathname());

                if (! $file->isFile()
                    || ! in_array($file->getExtension(), ['php', 'ts', 'tsx'], true)
                    || preg_match('#/resources/js/(routes|actions|wayfinder)/#', $path) === 1
                    || in_array($path, $allowed, true)) {
                    continue;
                }

                if (preg_match('/[\'"](free|pro|team|business|бесплатный)[\'"]/iu', (string) file_get_contents($path)) === 1) {
                    $offenders[] = $path;
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    private function register(string $email): User
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Новый владелец',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        auth()->logout();

        return User::query()->where('email', $email)->sole();
    }

    private function createSite(User $user, Workspace $workspace, Template $template, string $name): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('sites.store'), ['name' => $name, 'site_type' => SiteType::Landing->value, 'start' => 'template', 'template' => $template->public_id]);
    }

    private function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
