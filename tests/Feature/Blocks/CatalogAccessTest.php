<?php

namespace Tests\Feature\Blocks;

use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Enums\PlatformRole;
use App\Enums\SiteLicenseSource;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\SiteLicense;
use App\Models\User;
use App\Publishing\PublishValidator;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

/**
 * P9-014 / D-079: catalog access modes, Site-scoped licenses and the backend checks when adding a
 * Block Instance, duplicating it and publishing the Site.
 */
class CatalogAccessTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    private Plan $plan;

    private DeveloperProfile $developer;

    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->plan = Plan::factory()->create();
        $this->plan->setEntitlement(Entitlement::MaxSites, 5);
        $this->plan->setEntitlement(Entitlement::RemoveBranding, false);
        $this->workspace->plan()->associate($this->plan)->save();

        $this->developer = DeveloperProfile::factory()->withPermissions()->create(['display_name' => 'Студия Альфа']);
        $this->blocks = [
            'free' => $this->catalogBlock('dev-free', 'Бесплатная карточка', []),
            'entitlement' => $this->catalogBlock('dev-plan', 'Карточка по тарифу', [
                'access_mode' => CatalogAccessMode::Entitlement,
                'access_entitlement' => Entitlement::RemoveBranding,
            ]),
            'paid' => $this->catalogBlock('dev-paid', 'Платная карточка', [
                'access_mode' => CatalogAccessMode::Paid,
                'price_minor' => 150_050,
                'price_currency' => 'RUB',
            ]),
            'grant' => $this->catalogBlock('dev-grant', 'Карточка по выдаче', [
                'access_mode' => CatalogAccessMode::AdminGrant,
            ]),
        ];
    }

    public function test_designer_library_shows_russian_access_cards_without_internal_ids(): void
    {
        $this->as()->get(route('sites.designer', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('library', function ($library): bool {
                $items = collect($library)->keyBy('slug');

                foreach ($items as $item) {
                    $this->assertSame(['slug', 'name', 'author', 'access', 'available', 'reason'], array_keys($item));
                    $this->assertSame(['mode', 'restricted', 'label', 'detail'], array_keys($item['access']));
                }

                $this->assertNull($items['hero']['author']);
                $this->assertSame('Студия Альфа', $items['dev-free']['author']);
                $this->assertSame(
                    ['mode' => 'free', 'restricted' => false, 'label' => 'Бесплатно', 'detail' => null],
                    $items['dev-free']['access'],
                );
                $this->assertTrue($items['dev-free']['available']);

                $this->assertSame('Опция тарифа: Без брендинга Landflow', $items['dev-plan']['access']['detail']);
                $this->assertFalse($items['dev-plan']['available']);
                $this->assertSame('Блок доступен на тарифе с опцией «Без брендинга Landflow».', $items['dev-plan']['reason']);

                $this->assertSame("1\u{00A0}500,50\u{00A0}₽ за сайт", $items['dev-paid']['access']['detail']);
                $this->assertSame('Платно', $items['dev-paid']['access']['label']);
                $this->assertFalse($items['dev-paid']['available']);

                $this->assertSame('Выдаёт администратор', $items['dev-grant']['access']['label']);
                $this->assertSame('Блок выдаёт администратор Landflow для конкретного сайта.', $items['dev-grant']['reason']);

                return true;
            }));
    }

    public function test_each_access_mode_is_checked_when_adding_a_block(): void
    {
        $this->add('dev-free')->assertSessionHasNoErrors();
        $this->add('dev-plan')->assertSessionHasErrors(['block' => 'Блок доступен на тарифе с опцией «Без брендинга Landflow».']);
        $this->add('dev-paid')->assertSessionHasErrors(['block' => 'Платный блок: нужна лицензия для этого сайта. Покупка в Landflow пока недоступна.']);
        $this->add('dev-grant')->assertSessionHasErrors(['block' => 'Блок выдаёт администратор Landflow для конкретного сайта.']);
        $this->assertSame(1, $this->placedCount());

        $this->plan->setEntitlement(Entitlement::RemoveBranding, true);
        $this->add('dev-plan')->assertSessionHasNoErrors();
        $this->add('dev-paid')->assertSessionHasErrors('block');
        $this->add('dev-grant')->assertSessionHasErrors('block');

        $this->license($this->site, 'paid');
        $this->license($this->site, 'grant');
        $this->add('dev-paid')->assertSessionHasNoErrors();
        $this->add('dev-grant')->assertSessionHasNoErrors();
        $this->assertSame(4, $this->placedCount());
    }

    public function test_a_license_covers_only_its_own_site(): void
    {
        $other = Site::factory()->for($this->workspace)->create(['name' => 'Второй сайт']);
        $otherHome = Page::factory()->for($other)->home()->create();
        $this->license($this->site, 'grant');

        $this->add('dev-grant')->assertSessionHasNoErrors();
        $this->as()->post(route('sites.blocks.store', [$other, $otherHome]), ['block' => 'dev-grant'])
            ->assertSessionHasErrors(['block' => 'Блок выдаёт администратор Landflow для конкретного сайта.']);

        $this->as()->get(route('sites.designer', $other))
            ->assertInertia(fn (Assert $page) => $page->where(
                'library',
                fn ($library): bool => collect($library)->firstWhere('slug', 'dev-grant')['available'] === false,
            ));
        $this->assertSame(0, $otherHome->blocks()->count());
    }

    public function test_entitlement_and_license_stay_separate(): void
    {
        $this->plan->setEntitlement(Entitlement::RemoveBranding, true);
        $this->add('dev-paid')->assertSessionHasErrors('block');
        $this->add('dev-grant')->assertSessionHasErrors('block');

        $this->plan->setEntitlement(Entitlement::RemoveBranding, false);
        $this->license($this->site, 'entitlement');
        $this->add('dev-plan')->assertSessionHasNoErrors();
        $this->assertSame(1, $this->placedCount());
    }

    public function test_duplicating_rechecks_access(): void
    {
        $license = $this->license($this->site, 'grant');
        $this->add('dev-grant')->assertSessionHasNoErrors();
        $block = BlockInstance::query()->latest('id')->firstOrFail();

        $license->delete();
        $this->as()->post(route('sites.blocks.duplicate', [$this->site, $block]))
            ->assertSessionHasErrors(['block' => 'Блок выдаёт администратор Landflow для конкретного сайта.']);
        $this->assertSame(1, $this->placedCount());
    }

    public function test_publishing_rechecks_access_for_every_placed_block(): void
    {
        $this->placeCatalog('paid');
        $this->assertContains('block_access_denied', $this->issueCodes());

        $this->license($this->site, 'paid');
        $this->assertNotContains('block_access_denied', $this->issueCodes());

        $this->placeCatalog('entitlement');
        $issues = app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id));
        $this->assertContains('block_access_denied', $issues->errorCodes());
        $this->assertStringContainsString('«Карточка по тарифу»', implode(' ', array_column($issues->toArray()['errors'], 'message')));

        $this->plan->setEntitlement(Entitlement::RemoveBranding, true);
        $this->assertNotContains('block_access_denied', $this->issueCodes());
    }

    public function test_model_rejects_inconsistent_access_fields(): void
    {
        $invalid = [
            ['access_mode' => CatalogAccessMode::Entitlement],
            ['access_mode' => CatalogAccessMode::Entitlement, 'access_entitlement' => Entitlement::MaxSites],
            ['access_mode' => CatalogAccessMode::Paid, 'price_minor' => 0, 'price_currency' => 'RUB'],
            ['access_mode' => CatalogAccessMode::Paid, 'price_minor' => 100, 'price_currency' => 'XXX'],
            ['access_mode' => CatalogAccessMode::Free, 'price_minor' => 100, 'price_currency' => 'RUB'],
            ['access_mode' => CatalogAccessMode::AdminGrant, 'access_entitlement' => Entitlement::RemoveBranding],
        ];

        foreach ($invalid as $attributes) {
            try {
                $this->blocks['free']->forceFill($attributes)->save();
                $this->fail('Inconsistent catalog access must be rejected.');
            } catch (LogicException) {
                $this->blocks['free']->refresh();
            }
        }

        $this->assertSame(CatalogAccessMode::Free, $this->blocks['free']->access_mode);
    }

    public function test_author_updates_access_mode_from_the_studio(): void
    {
        Log::spy();
        $user = $this->developer->user;
        $block = $this->blocks['free'];

        $this->actingAs($user)->get(route('developer.blocks.show', $block))
            ->assertInertia(fn (Assert $page) => $page
                ->where('access', ['mode' => 'free', 'entitlement' => null, 'price' => ''])
                ->has('accessModes', 4)
                ->where('accessEntitlements', fn ($choices): bool => collect($choices)->pluck('value')->all() === ['custom_domain', 'remove_branding', 'multi_page_sites']));

        $this->actingAs($user)->put(route('developer.blocks.access', $block), ['mode' => 'paid', 'price' => '1 500,5'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('developer.blocks.show', $block));
        $block->refresh();
        $this->assertSame([CatalogAccessMode::Paid, 150_050, 'RUB'], [$block->access_mode, $block->price_minor, $block->price_currency]);
        Log::shouldHaveReceived('info')->with('developer.block_access_updated', Mockery::on(
            fn (array $context): bool => $context['block'] === $block->public_id && $context['access_mode'] === 'paid' && $context['actor_user_id'] === $user->id,
        ));

        $this->actingAs($user)->put(route('developer.blocks.access', $block), ['mode' => 'paid', 'price' => '0'])
            ->assertSessionHasErrors('price');
        $this->actingAs($user)->put(route('developer.blocks.access', $block), ['mode' => 'entitlement', 'entitlement' => 'max_sites'])
            ->assertSessionHasErrors('entitlement');
        $this->actingAs($user)->put(route('developer.blocks.access', $block), ['mode' => 'reseller'])
            ->assertSessionHasErrors('mode');

        $this->actingAs($user)->put(route('developer.blocks.access', $block), ['mode' => 'entitlement', 'entitlement' => 'multi_page_sites', 'price' => '900'])
            ->assertSessionHasNoErrors();
        $block->refresh();
        $this->assertSame([CatalogAccessMode::Entitlement, Entitlement::MultiPageSites, null, null], [$block->access_mode, $block->access_entitlement, $block->price_minor, $block->price_currency]);

        $foreign = $this->catalogBlock('foreign-card', 'Чужая карточка', [], DeveloperProfile::factory()->withPermissions()->create());
        $this->actingAs($user)->put(route('developer.blocks.access', $foreign), ['mode' => 'admin_grant'])->assertNotFound();
        $this->assertSame(CatalogAccessMode::Free, $foreign->fresh()?->access_mode);
    }

    public function test_super_admin_sets_access_on_official_blocks(): void
    {
        $official = BlockDefinition::query()->where('slug', 'hero')->sole();

        $this->actingAs($this->userWithRole(PlatformRole::SuperAdmin))
            ->put(route('platform.blocks.access', $official), ['mode' => 'admin_grant'])
            ->assertSessionHasNoErrors();
        $this->assertSame(CatalogAccessMode::AdminGrant, $official->fresh()?->access_mode);
        $this->add('hero')->assertSessionHasErrors('block');
    }

    public function test_super_admin_grants_and_revokes_site_licenses(): void
    {
        Log::spy();
        $admin = $this->userWithRole(PlatformRole::SuperAdmin);

        $this->actingAs($admin)->get(route('platform.licenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/licenses/index')
                ->has('licenses', 0)
                ->where('items', fn ($items): bool => collect($items)->pluck('name')->sort()->values()->all() === ['Карточка по выдаче', 'Карточка по тарифу', 'Платная карточка'])
                ->where('items.0', fn ($item): bool => array_keys($item->all()) === ['public_id', 'name', 'author', 'access'])
                ->missing('items.0.id'));

        $grant = $this->blocks['grant'];
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['block' => $grant->public_id, 'site' => ' DEALER '])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('platform.licenses.index'));
        $license = SiteLicense::query()->sole();
        $this->assertSame([$this->site->id, $grant->id, SiteLicenseSource::AdminGrant, $admin->id], [$license->site_id, $license->block_definition_id, $license->source, $license->granted_by_user_id]);
        Log::shouldHaveReceived('info')->with('platform.site_license_granted', [
            'license' => $license->public_id,
            'block' => $grant->public_id,
            'site' => $this->site->public_id,
            'actor_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('platform.licenses.store'), ['block' => $grant->public_id, 'site' => $this->site->public_id])
            ->assertSessionHasErrors(['site' => 'У этого сайта уже есть лицензия на этот блок.']);
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['block' => $this->blocks['free']->public_id, 'site' => 'dealer'])
            ->assertSessionHasErrors(['block' => 'Этот блок бесплатный — лицензия не нужна.']);
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['block' => $grant->public_id, 'site' => 'missing'])
            ->assertSessionHasErrors(['site' => 'Сайт не найден. Укажите поддомен или ID сайта.']);
        $this->assertSame(1, SiteLicense::query()->count());

        $this->actingAs($admin)->get(route('platform.licenses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('licenses', 1)
                ->where('licenses.0.public_id', $license->public_id)
                ->where('licenses.0.site', 'Дилер')
                ->where('licenses.0.source_label', 'Выдана администратором')
                ->missing('licenses.0.id')
                ->missing('licenses.0.site_id'));

        $this->actingAs($admin)->delete(route('platform.licenses.destroy', $license->public_id))
            ->assertRedirect(route('platform.licenses.index'));
        $this->assertSame(0, SiteLicense::query()->count());
        Log::shouldHaveReceived('info')->with('platform.site_license_revoked', Mockery::on(fn (array $context): bool => $context['license'] === $license->public_id));
        $this->add('dev-grant')->assertSessionHasErrors('block');
    }

    public function test_only_license_managers_reach_the_license_admin(): void
    {
        $license = $this->license($this->site, 'grant');
        $forbidden = [
            $this->userWithRole(PlatformRole::CatalogManager),
            $this->developer->user,
            $this->owner,
        ];

        foreach ($forbidden as $user) {
            $this->actingAs($user)->get(route('platform.licenses.index'))->assertForbidden();
            $this->actingAs($user)->post(route('platform.licenses.store'), ['block' => $this->blocks['paid']->public_id, 'site' => 'dealer'])->assertForbidden();
            $this->actingAs($user)->delete(route('platform.licenses.destroy', $license->public_id))->assertForbidden();
        }

        $this->assertSame(1, SiteLicense::query()->count());
    }

    /**
     * @param  array<string, mixed>  $access
     */
    private function catalogBlock(string $slug, string $name, array $access, ?DeveloperProfile $profile = null): BlockDefinition
    {
        $definition = BlockDefinition::factory()->developer($profile ?? $this->developer)->create(['slug' => $slug, 'name' => $name, ...$access]);
        BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]],
        ]);

        return $definition;
    }

    private function license(Site $site, string $block): SiteLicense
    {
        return SiteLicense::factory()->create(['site_id' => $site->id, 'block_definition_id' => $this->blocks[$block]->id]);
    }

    private function placeCatalog(string $block): BlockInstance
    {
        return BlockInstance::factory()->create([
            'page_id' => $this->home->id,
            'block_version_id' => $this->blocks[$block]->versions()->sole()->id,
            'sort_order' => $this->home->blocks()->count(),
            'state_json' => ['title' => 'Привет'],
        ]);
    }

    private function add(string $slug): TestResponse
    {
        return $this->as()->post(route('sites.blocks.store', [$this->site, $this->home]), ['block' => $slug]);
    }

    private function placedCount(): int
    {
        return BlockInstance::query()->whereHas('version.definition', fn ($query) => $query->where('slug', '!=', 'hero')->where('slug', '!=', 'vehicle-card'))->count();
    }

    /**
     * @return list<string>
     */
    private function issueCodes(): array
    {
        return app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id))->errorCodes();
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }

    private function as(): static
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
