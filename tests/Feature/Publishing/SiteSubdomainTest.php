<?php

namespace Tests\Feature\Publishing;

use App\Actions\Sites\CreateSite;
use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Support\SiteSubdomain;
use App\Support\WorkspaceContext;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class SiteSubdomainTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
    }

    public function test_label_rules(): void
    {
        foreach (['dealer', 'dealer-name', 'kia-2026', 'a', str_repeat('a', 63)] as $label) {
            $this->assertTrue(SiteSubdomain::isWellFormed($label), $label);
        }

        foreach (['', '-dealer', 'dealer-', 'Dealer', 'dealer_name', 'dealer.name', 'дилер', 'dealer name', str_repeat('a', 64)] as $label) {
            $this->assertFalse(SiteSubdomain::isWellFormed($label), $label);
        }

        foreach (['www', 'admin', 'api', 'app', 'support', 'static', 'assets', 'platform', 'auth', 'login', 'register', 'xn--80ak6aa92e'] as $label) {
            $this->assertTrue(SiteSubdomain::isReserved($label), $label);
        }

        $this->assertFalse(SiteSubdomain::isReserved('dealer'));
    }

    public function test_new_sites_get_a_unique_transliterated_subdomain_that_survives_renames(): void
    {
        $this->seed(TemplateSeeder::class);
        $template = Template::query()->where('slug', 'blank')->firstOrFail();
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, 10);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $create = fn (string $name): Site => app(CreateSite::class)->create($workspace, $template, $name);

        $first = $create('Changan Москва');
        $second = $create('Changan Москва');
        $reserved = $create('Admin');
        $symbols = $create('★★★');

        $this->assertSame('changan-moskva', $first->subdomain);
        $this->assertSame('changan-moskva-2', $second->subdomain);
        $this->assertSame('admin-site', $reserved->subdomain);
        $this->assertSame('site', $symbols->subdomain);

        $first->update(['name' => 'Haval Казань']);
        $this->assertSame('changan-moskva', $first->fresh()?->subdomain);
    }

    public function test_owner_and_admin_change_the_subdomain_and_the_public_host_follows(): void
    {
        $this->publish();
        $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertOk();

        $this->change($this->owner, '  Dealer-Name ')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.publishing.show', $this->site));

        $this->assertSame('dealer-name', $this->site->fresh()?->subdomain);
        $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertNotFound();
        $this->onPublicHost(fn () => $this->get('http://dealer-name.localhost/'))->assertOk()->assertSee('Заголовок v1');

        $this->change($this->member(WorkspaceRole::Admin), 'dealer-admin')->assertSessionHasNoErrors();
        $this->assertSame('dealer-admin', $this->site->fresh()?->subdomain);
    }

    public function test_designer_and_content_editor_cannot_change_the_subdomain(): void
    {
        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = $this->member($role);
            $this->change($member, 'hijacked')->assertForbidden();
            $this->as($member)->get(route('sites.publishing.show', $this->site))
                ->assertInertia(fn (Assert $page) => $page->where('can.manageDomains', false));
        }

        $this->assertSame('dealer', $this->site->fresh()?->subdomain);
    }

    public function test_a_foreign_workspace_cannot_change_the_subdomain(): void
    {
        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);

        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->put(route('sites.subdomain.update', $this->site), ['subdomain' => 'hijacked'])
            ->assertNotFound();

        $this->assertSame('dealer', $this->site->fresh()?->subdomain);
    }

    public function test_invalid_reserved_and_taken_subdomains_are_rejected(): void
    {
        Site::factory()->archived()->create(['subdomain' => 'taken-archived']);
        Site::factory()->create(['subdomain' => 'taken']);

        $cases = [
            '' => 'Укажите адрес сайта.',
            '-dealer' => 'Используйте строчные латинские буквы',
            'dealer_name' => 'Используйте строчные латинские буквы',
            'дилер' => 'Используйте строчные латинские буквы',
            str_repeat('a', 64) => 'не должен превышать 63 символа',
            'www' => 'зарезервирован',
            'xn--80ak6aa92e' => 'зарезервирован',
            'taken' => 'уже занят',
            'taken-archived' => 'уже занят',
        ];

        foreach ($cases as $value => $message) {
            $this->change($this->owner, (string) $value)->assertSessionHasErrors('subdomain');
            $this->assertStringContainsString($message, (string) session('errors')?->first('subdomain'), (string) $value);
        }

        $this->assertSame('dealer', $this->site->fresh()?->subdomain);
        $this->change($this->owner, 'dealer')->assertSessionHasNoErrors();
    }

    public function test_publishing_requires_a_subdomain_and_the_page_shows_the_address(): void
    {
        $this->as($this->owner)->get(route('sites.publishing.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('address.subdomain', 'dealer')
                ->where('address.domain', 'localhost')
                ->where('address.url', 'http://dealer.localhost/')
                ->where('can.manageDomains', true));

        $this->site->forceFill(['subdomain' => null])->save();
        $this->actAsMember($this->owner);

        $codes = array_map(fn ($issue) => $issue->code, app(PublishValidator::class)->validate($this->site->fresh() ?? $this->site)->errors);
        $this->assertContains('subdomain_missing', $codes);
    }

    private function publish(): void
    {
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    /**
     * @return TestResponse<Response>
     */
    private function change(User $user, string $subdomain): TestResponse
    {
        return $this->as($user)->put(route('sites.subdomain.update', $this->site), ['subdomain' => $subdomain]);
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
