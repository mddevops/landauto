<?php

namespace Database\Seeders;

use App\Actions\Sites\CreateSite;
use App\Blocks\BlockStateDefaults;
use App\Enums\BlockCategory;
use App\Enums\CatalogAccessMode;
use App\Enums\DeveloperPermission;
use App\Enums\DeveloperProfileStatus;
use App\Enums\Entitlement;
use App\Enums\MediaAngle;
use App\Enums\PlatformRole;
use App\Enums\SiteType;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModification;
use App\Models\DeveloperProfile;
use App\Models\Form;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\Popup;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\Submission;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Deterministic data for Playwright browser tests (tests/browser).
 *
 * Credentials are test-only and duplicated in tests/browser/support/users.ts.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        // Known credentials must never be created in a development or production database.
        if (! app()->environment('e2e')) {
            throw new RuntimeException('E2eSeeder may only run in the e2e environment.');
        }

        // Long Russian name on purpose: layouts must survive realistic long user data.
        $member = $this->createUser('Александра Константиновна Преображенская', 'member@landflow.test');
        $this->createWorkspace($member, 'Личный автопарк');
        $workspaceWithSites = $this->createWorkspace($member, 'Автосалон Север');
        Site::factory()->for($workspaceWithSites)->create(['name' => 'Сайт автосалона']);
        Site::factory()->for($workspaceWithSites)->archived()->create(['name' => 'Архивный лендинг']);
        $this->createWorkspace($member, 'Недоступный Workspace', WorkspaceMemberStatus::Suspended);

        // Separate user for login/logout flows so they do not share the login rate limit
        // with the authenticated storage state.
        $loginUser = $this->createUser('Иван Петров', 'login@landflow.test');
        $this->createWorkspace($loginUser, 'Workspace Ивана');

        // Reused by verification-flow tests, including Playwright retries.
        $unverified = $this->createUser('Мария Неподтверждённая', 'unverified@landflow.test', false);
        $this->createWorkspace($unverified, 'Workspace Марии');

        // Core platform flow: creates Sites, so it is isolated from the `member` assertions.
        // The generous test-only limit keeps repeated runs against one server passing.
        $this->call([TemplateSeeder::class, OfficialBlockSeeder::class]);
        $plan = Plan::factory()->create(['key' => 'e2e-sites', 'name' => 'E2E Sites']);
        $plan->setEntitlement(Entitlement::MaxSites, 100);
        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $creator = $this->createUser('Олег Создатель', 'creator@landflow.test');
        $this->createWorkspace($creator, 'Автосалон Юг', plan: $plan);
        $this->createWorkspace($creator, 'Сервисный центр Юг', plan: $plan);

        // Designer flow: creates its own Site, so it never touches the `creator` assertions.
        $designer = $this->createUser('Дина Дизайнерова', 'designer@landflow.test');
        $this->createWorkspace($designer, 'Студия Дины', plan: $plan);

        // Automotive flow: a platform catalog administrator (explicit platform role, no
        // Workspace access) and a dealer who builds a vehicle page on a separate Site.
        $this->call(CatalogDemoSeeder::class);
        $catalogAdmin = $this->createUser('Пётр Каталогов', 'catalog@landflow.test');
        $this->createWorkspace($catalogAdmin, 'Workspace Петра');
        PlatformRoleAssignment::query()->create(['user_id' => $catalogAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
        $dealer = $this->createUser('Денис Дилеров', 'dealer@landflow.test');
        $this->createWorkspace($dealer, 'Автосалон Восток', plan: $plan);

        // Interactive flow: Forms, Popups and submissions on its own Site.
        $interactive = $this->createUser('Инна Интерактивова', 'interactive@landflow.test');
        $this->createVehicleShowcase($this->createWorkspace($interactive, 'Автосалон Запад', plan: $plan));

        // Publishing flow: a Site on its own Landflow subdomain with a ready lead Form + Popup.
        $publisher = $this->createUser('Павел Публикаторов', 'publisher@landflow.test');
        $this->createPublishingSite($this->createWorkspace($publisher, 'Автосалон Центр', plan: $plan));

        // Full publishing lifecycle: a ready Draft v1 with interactive automotive Blocks, an Admin
        // (publish, no restore) and a Designer (preview only).
        $lifecycle = $this->createUser('Лев Циклов', 'lifecycle@landflow.test');
        $lifecycleWorkspace = $this->createWorkspace($lifecycle, 'Автосалон Цикл', plan: $plan);
        $lifecycleWorkspace->addMember($this->createUser('Дарья Оформителева', 'lifecycle-designer@landflow.test'), WorkspaceRole::Designer);
        $lifecycleWorkspace->addMember($this->createUser('Антон Админов', 'lifecycle-admin@landflow.test'), WorkspaceRole::Admin);
        $this->createLifecycleSite($lifecycleWorkspace);

        // Integrations flow: Owner (profiles, routes), Admin (logs, retry), Designer (no access).
        $integrator = $this->createUser('Ирина Интеграторова', 'integrations@landflow.test');
        $integrationsWorkspace = $this->createWorkspace($integrator, 'Автосалон Интеграция', plan: $plan);
        $integrationsWorkspace->addMember($this->createUser('Игорь Админов', 'integrations-admin@landflow.test'), WorkspaceRole::Admin);
        $integrationsWorkspace->addMember($this->createUser('Ника Дизайнова', 'integrations-designer@landflow.test'), WorkspaceRole::Designer);
        $this->createIntegrationsSite($integrationsWorkspace);

        // Workspace / Site navigation: a single-Workspace Owner who creates and renames Workspaces.
        $navigator = $this->createUser('Нина Навигаторова', 'navigator@landflow.test');
        $navigatorWorkspace = $this->createWorkspace($navigator, 'Автодом Навигатор', plan: $plan);
        app(CreateSite::class)->create($navigatorWorkspace, 'Сайт навигации', SiteType::MultiPage);

        // Custom domains + branding: a test-only plan with `custom_domain`, an Owner and a Designer.
        $domainsPlan = Plan::factory()->create(['key' => 'e2e-domains', 'name' => 'E2E Domains']);
        $domainsPlan->setEntitlement(Entitlement::MaxSites, 100);
        $domainsPlan->setEntitlement(Entitlement::CustomDomain, true);
        $domainsPlan->setEntitlement(Entitlement::MultiPageSites, true);
        $domainsOwner = $this->createUser('Дмитрий Доменов', 'domains@landflow.test');
        $domainsWorkspace = $this->createWorkspace($domainsOwner, 'Автосалон Домен', plan: $domainsPlan);
        $domainsWorkspace->addMember($this->createUser('Диана Доменная', 'domains-designer@landflow.test'), WorkspaceRole::Designer);
        $domainsSite = app(CreateSite::class)->create($domainsWorkspace, 'Сайт с доменом', SiteType::MultiPage);
        $domainsSite->forceFill(['subdomain' => 'domains-e2e'])->save();
        $this->placeBlock($domainsSite->pages()->where('is_home', true)->firstOrFail(), 'hero', 0, ['title' => 'Свой домен: главная']);

        $this->createTeamWorkspace();

        // Site formats: a Free-like plan (many Sites, no `multi_page_sites`) and one compatible
        // official Template each for Quiz and Chat Selection.
        $formatsPlan = Plan::factory()->create(['key' => 'e2e-formats', 'name' => 'E2E Formats']);
        $formatsPlan->setEntitlement(Entitlement::MaxSites, 100);
        $this->createWorkspace($this->createUser('Фёкла Форматова', 'formats@landflow.test'), 'Автосалон Формат', plan: $formatsPlan);
        foreach ([['e2e-quiz', 'Квиз: подбор автомобиля', SiteType::Quiz], ['e2e-chat', 'Чат: подбор автомобиля', SiteType::ChatSelection]] as [$slug, $name, $type]) {
            $template = Template::query()->create(['slug' => $slug, 'name' => $name]);
            $template->forceFill(['is_official' => true, 'site_types' => [$type->value]])->save();
            $template->versions()->create(['version' => '1.0.0']);
        }

        // Block authoring: only an active Developer Profile with `create_blocks` grants access;
        // no platform role, and the personal Workspace grants no authoring.
        $developer = $this->createUser('Девелопер Блоков', 'developer@landflow.test');
        $this->createWorkspace($developer, 'Workspace Девелопера');
        $this->createDeveloperProfile($developer, 'Студия блоков E2E', 'e2e-block-studio');

        // Block Studio editing flows log in separately so login throttling never couples the specs.
        $studioDeveloper = $this->createUser('Сергей Студийный', 'studio-developer@landflow.test');
        $this->createWorkspace($studioDeveloper, 'Workspace Студийного');
        $this->createDeveloperProfile($studioDeveloper, 'Студия кода E2E', 'e2e-code-studio');

        // Sandboxed runtime: a platform Block published from Block Studio, placed and published on
        // its own Site with a lead Popup.
        $this->createSandboxedPlatformBlock();
        $sandboxOwner = $this->createUser('Сабина Песочникова', 'sandbox@landflow.test');
        $sandboxSite = app(CreateSite::class)->create($this->createWorkspace($sandboxOwner, 'Автосалон Песочница', plan: $plan), 'Сайт с блоком из студии', SiteType::MultiPage);
        $sandboxSite->forceFill(['subdomain' => 'sandbox-e2e'])->save();
        $sandboxForm = Form::factory()->for($sandboxSite)->withLeadFields()->create(['name' => 'Заявка с сайта']);
        Popup::factory()->for($sandboxSite)->create(['name' => 'Обратный звонок'])->form()->associate($sandboxForm)->save();

        // Customer catalog (D-079): a Developer Block that only a Super Admin grants per Site, a
        // customer Site on `license-e2e` and a dedicated Super Admin with their own login throttle.
        $catalogAuthor = $this->createUser('Артём Каталожный', 'catalog-author@landflow.test');
        $this->createWorkspace($catalogAuthor, 'Workspace Артёма');
        $this->createGrantOnlyDeveloperBlock($this->createDeveloperProfile($catalogAuthor, 'Студия каталога E2E', 'e2e-catalog-studio'));
        $licensee = $this->createUser('Лиана Лицензиатова', 'licensee@landflow.test');
        $licenseSite = app(CreateSite::class)->create($this->createWorkspace($licensee, 'Автосалон Лицензия', plan: $plan), 'Сайт по лицензии', SiteType::MultiPage);
        $licenseSite->forceFill(['subdomain' => 'license-e2e'])->save();
        $licenseAdmin = $this->createUser('Ольга Лицензиарова', 'licenses-admin@landflow.test');
        $this->createWorkspace($licenseAdmin, 'Workspace Ольги');
        PlatformRoleAssignment::query()->create(['user_id' => $licenseAdmin->id, 'role' => PlatformRole::SuperAdmin->value]);
    }

    private function createGrantOnlyDeveloperBlock(DeveloperProfile $profile): void
    {
        $definition = BlockDefinition::factory()->developer($profile)->create([
            'slug' => 'e2e-partner-showcase',
            'name' => 'Витрина партнёра',
            'category' => BlockCategory::Cta,
            'access_mode' => CatalogAccessMode::AdminGrant,
        ]);
        BlockVersion::factory()
            ->sandboxed('<section class="showcase"><h2>{{ title }}</h2></section>', '.showcase { padding: 24px; background: #e0f2fe; }')
            ->for($definition, 'definition')
            ->create(['schema_json' => ['fields' => [
                ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'default' => 'Партнёрская витрина', 'max_length' => 80],
            ]]]);
    }

    private function createSandboxedPlatformBlock(): void
    {
        $definition = BlockDefinition::factory()->platform()->create(['slug' => 'e2e-studio-promo', 'name' => 'Промо из студии', 'category' => BlockCategory::Cta]);
        BlockVersion::factory()
            ->sandboxed(
                '<section class="promo">{{#if photo}}<img src="{{ photo.url }}" alt="{{ photo.alt }}">{{/if}}<h2>{{ title }}</h2><button type="button" data-landflow-action="cta">Узнать цену</button></section>',
                '.promo { padding: 24px; background: #fef3c7; } .promo img { width: 48px; height: 48px; }',
                'landflow.root.setAttribute("data-ready", "yes");',
            )
            ->for($definition, 'definition')
            ->create(['schema_json' => ['fields' => [
                ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'default' => 'Спецпредложение', 'max_length' => 80],
                ['key' => 'photo', 'type' => 'image', 'label' => 'Фото'],
                ['key' => 'cta', 'type' => 'action', 'label' => 'Кнопка'],
            ]]]);
    }

    private function createDeveloperProfile(User $user, string $name, string $slug): DeveloperProfile
    {
        $profile = new DeveloperProfile(['display_name' => $name, 'bio' => null]);
        $profile->slug = $slug;
        $profile->status = DeveloperProfileStatus::Active;
        $profile->user()->associate($user)->save();
        $profile->permissions()->createMany(array_map(
            fn (DeveloperPermission $permission): array => ['permission' => $permission->value],
            DeveloperPermission::defaults(),
        ));

        return $profile;
    }

    /**
     * Phase 8 team flows: a test-only Team plan (10 seats), an Owner with Site A and Site B, one
     * member per specialised role, a Designer who is invited through the UI and a foreign
     * Workspace whose resources have fixed public IDs (tests/browser/support/users.ts).
     */
    private function createTeamWorkspace(): void
    {
        $plan = Plan::factory()->create(['key' => 'e2e-team', 'name' => 'E2E Team']);
        $plan->setEntitlement(Entitlement::MaxSites, 100);
        $plan->setEntitlement(Entitlement::MaxMembers, 10);
        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $workspace = $this->createWorkspace($this->createUser('Тимур Командиров', 'team-owner@landflow.test'), 'Автосалон Команда', plan: $plan);
        $siteA = app(CreateSite::class)->create($workspace, 'Сайт команды А', SiteType::MultiPage);
        $siteA->forceFill(['subdomain' => 'team-a-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $siteB = app(CreateSite::class)->create($workspace, 'Сайт команды Б', SiteType::MultiPage);
        $siteB->forceFill(['subdomain' => 'team-b-e2e'])->save();
        $this->placeBlock($siteA->pages()->where('is_home', true)->firstOrFail(), 'hero', 0, ['title' => 'Команда: главная']);
        $form = Form::factory()->for($siteA)->withLeadFields()->create(['name' => 'Заявка команды']);
        Submission::factory()->create(['form_id' => $form->id]);

        $mark = AutoMark::query()->firstOrCreate(['url' => 'testmash'], ['name' => 'Тестмаш', 'name_ru' => 'Тестмаш', 'country' => 'Россия', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 't-1'], ['name' => 'Т-1', 'name_ru' => 'Т-1', 'year_from' => 2024, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'i'], ['name' => 'I', 'year_from' => 2024, 'status' => true]);
        $hatchback = $generation->series()->firstOrCreate(['url' => 'hatchback'], ['name' => 'Хэтчбек', 'status' => true]);
        $generation->series()->firstOrCreate(['url' => 'wagon'], ['name' => 'Универсал', 'status' => true]);
        $equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($hatchback, 'series')->state(['name' => '1.6 MT 110 л.с.']), 'modification')
            ->create(['name' => 'Стандарт']);
        $vehicle = SiteVehicle::factory()->for($siteA)->forSeries($hatchback)->create(['sort_order' => 0]);
        SiteOffer::factory()->forEquipment($equipment)->create(['site_vehicle_id' => $vehicle->id, 'price_minor' => 200_000_000]);

        $workspace->addMember($this->createUser('Полина Ценова', 'team-pricing@landflow.test'), WorkspaceRole::PricingManager);
        $workspace->addMember($this->createUser('Пётр Выпускалов', 'team-publisher@landflow.test'), WorkspaceRole::Publisher);
        $workspace->addMember($this->createUser('Лидия Заявкина', 'team-leads@landflow.test'), WorkspaceRole::LeadManager);
        $workspace->addMember($this->createUser('Иван Связев', 'team-integrations@landflow.test'), WorkspaceRole::IntegrationsManager);
        $this->createWorkspace($this->createUser('Дмитрий Макетов', 'team-designer@landflow.test'), 'Студия Макетова');

        $foreign = new Workspace;
        $foreign->forceFill(['public_id' => '01k0f0re0000000000000000w1', 'name' => 'Автосалон Чужой'])->save();
        $foreignMember = new WorkspaceMember;
        $foreignMember->forceFill([
            'public_id' => '01k0f0re0000000000000000m1',
            'workspace_id' => $foreign->id,
            'user_id' => $this->createUser('Фёдор Чужаков', 'team-foreign@landflow.test')->id,
            'role' => WorkspaceRole::Owner,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ])->save();
        WorkspaceVehicle::factory()->for($foreign)->forSeries($hatchback)->create(['public_id' => '01k0f0re0000000000000000v1']);
        WorkspaceAsset::factory()->for($foreign)->create(['public_id' => '01k0f0re0000000000000000a1']);
    }

    /**
     * Site «Сайт интеграций» on `integrations-e2e`: hero with a Popup button and vehicle offers
     * whose button opens the same Popup with the trusted vehicle/offer context. No routes,
     * profiles or analytics: the spec configures them through the UI.
     */
    private function createIntegrationsSite(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, 'Сайт интеграций', SiteType::MultiPage);
        $site->forceFill(['subdomain' => 'integrations-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $form = Form::factory()->for($site)->withLeadFields()->create(['name' => 'Заявка с сайта']);
        $popup = Popup::factory()->for($site)->create(['name' => 'Обратный звонок', 'title' => 'Перезвоним за 5 минут']);
        $popup->form()->associate($form)->save();

        $mark = AutoMark::query()->firstOrCreate(['url' => 'moskvich'], ['name' => 'Moskvich', 'name_ru' => 'Москвич', 'country' => 'Россия', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 'moskvich-6'], ['name' => 'Moskvich 6', 'name_ru' => 'Москвич 6', 'year_from' => 2023, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'i'], ['name' => 'I', 'year_from' => 2023, 'status' => true]);
        $series = $generation->series()->firstOrCreate(['url' => 'liftback'], ['name' => 'Лифтбек', 'status' => true]);
        $equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($series, 'series')->state(['name' => '1.5 CVT 174 л.с.']), 'modification')
            ->create(['name' => 'Престиж']);
        $vehicle = SiteVehicle::factory()->for($site)->forSeries($series)->create(['sort_order' => 0]);
        SiteOffer::factory()->forEquipment($equipment)->create(['site_vehicle_id' => $vehicle->id, 'price_minor' => 254_000_000]);

        $popupAction = ['type' => 'open_popup', 'popup' => $popup->public_id];
        $home = $site->pages()->where('is_home', true)->firstOrFail();
        $this->placeBlock($home, 'hero', 0, [
            'title' => 'Интеграции: главная',
            'primary_button' => ['label' => 'Перезвоните мне', 'action' => $popupAction],
        ]);
        $this->placeBlock($home, 'vehicle-offers', 1, [
            'vehicle' => $vehicle->public_id,
            'button' => ['label' => 'Узнать цену', 'action' => $popupAction],
        ]);
    }

    private function createPublishingSite(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, 'Сайт для публикации', SiteType::MultiPage);
        $site->forceFill(['subdomain' => 'publish-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $form = Form::factory()->for($site)->withLeadFields()->create(['name' => 'Заявка с сайта']);
        Popup::factory()->for($site)->create(['name' => 'Обратный звонок'])->form()->associate($form)->save();
    }

    /**
     * Site «Сайт жизненного цикла» on `lifecycle-e2e`: home Page with a hero (Popup button and a
     * scroll button to the vehicle card), vehicle card, offers, a one-card vehicle carousel and a
     * gallery. Two vehicles of a dedicated catalog branch; the priced one has two colors backed by
     * real image files (two angles for «Белый»).
     */
    private function createLifecycleSite(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, 'Сайт жизненного цикла', SiteType::MultiPage);
        $site->forceFill(['subdomain' => 'lifecycle-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $form = Form::factory()->for($site)->withLeadFields()->create(['name' => 'Заявка на тест-драйв']);
        $popup = Popup::factory()->for($site)->create(['name' => 'Тест-драйв', 'title' => 'Тест-драйв за 15 минут']);
        $popup->form()->associate($form)->save();

        $mark = AutoMark::query()->firstOrCreate(['url' => 'moskvich'], ['name' => 'Moskvich', 'name_ru' => 'Москвич', 'country' => 'Россия', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 'moskvich-3'], ['name' => 'Moskvich 3', 'name_ru' => 'Москвич 3', 'year_from' => 2022, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'i'], ['name' => 'I', 'year_from' => 2022, 'status' => true]);
        $series = $generation->series()->firstOrCreate(['url' => 'crossover'], ['name' => 'Кроссовер', 'status' => true]);
        $liftback = $generation->series()->firstOrCreate(['url' => 'liftback'], ['name' => 'Лифтбек', 'status' => true]);
        $equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($series, 'series')->state(['name' => '1.5 CVT 150 л.с.']), 'modification')
            ->create(['name' => 'Люкс']);
        $vehicle = SiteVehicle::factory()->for($site)->forSeries($series)->create(['sort_order' => 0]);
        SiteVehicle::factory()->for($site)->forSeries($liftback)->create(['sort_order' => 1]);
        SiteOffer::factory()->forEquipment($equipment)->create(['site_vehicle_id' => $vehicle->id, 'price_minor' => 199_000_000]);

        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $colors = [['Белый', '#f4f4f4', [MediaAngle::FrontThreeQuarter, MediaAngle::Side]], ['Красный', '#c62828', [MediaAngle::FrontThreeQuarter]]];

        foreach ($colors as $order => [$name, $swatch, $angles]) {
            $set = new SeriesMediaSet(['name' => $name, 'swatch_hex' => $swatch, 'status' => true, 'sort_order' => $order]);
            $set->catalog_series_public_id = $series->public_id;
            $set->save();

            foreach ($angles as $angle) {
                $path = "series-media/e2e-lifecycle/{$order}-{$angle->value}.png";
                Storage::disk(SeriesMediaImage::DISK)->put($path, $png);
                SeriesMediaImage::factory()->for($set, 'set')->create([
                    'angle' => $angle,
                    'path' => $path,
                    'original_name' => "{$angle->value}.png",
                    'size_bytes' => strlen($png),
                    'width' => 1,
                    'height' => 1,
                ]);
            }
        }

        $popupAction = ['type' => 'open_popup', 'popup' => $popup->public_id];
        $home = $site->pages()->where('is_home', true)->firstOrFail();
        $card = $this->placeBlock($home, 'vehicle-card', 1, ['vehicle' => $vehicle->public_id]);
        $this->placeBlock($home, 'hero', 0, [
            'title' => 'Цикл: версия 1',
            'primary_button' => ['label' => 'Записаться на тест-драйв', 'action' => $popupAction],
            'secondary_button' => ['label' => 'К ценам', 'action' => ['type' => 'scroll_to', 'block' => $card->public_id]],
        ]);
        $this->placeBlock($home, 'vehicle-offers', 2, [
            'vehicle' => $vehicle->public_id,
            'button' => ['label' => 'Оставить заявку', 'action' => $popupAction],
        ]);
        $this->placeBlock($home, 'vehicle-grid', 3, [
            'carousel' => ['enabled' => true, 'per_view' => 'one', 'gap' => 'medium', 'arrows' => true, 'dots' => true, 'loop' => false, 'autoplay' => false, 'delay' => 's5'],
        ]);
        $this->placeBlock($home, 'vehicle-gallery', 4, ['vehicle' => $vehicle->public_id]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function placeBlock(Page $page, string $slug, int $sortOrder, array $state): BlockInstance
    {
        $version = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', $slug))->latest('id')->firstOrFail();

        $block = new BlockInstance;
        $block->forceFill([
            'page_id' => $page->id,
            'block_version_id' => $version->id,
            'sort_order' => $sortOrder,
            'state_json' => array_replace(app(BlockStateDefaults::class)->fromSchema($version->schema_json), $state),
        ])->save();

        return $block;
    }

    /**
     * Site «Витрина Запад» for the Carousel, Lightbox and Offer → Popup flows: two vehicles of a
     * dedicated catalog branch (never touched by other specs), one priced Offer and a two-angle
     * media set backed by real image files.
     */
    private function createVehicleShowcase(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, 'Витрина Запад', SiteType::MultiPage);
        // Many preview submissions from one IP across retries must not hit the default IP limit.
        $site->forceFill(['form_security' => ['ip_limit' => 1000]])->save();

        $mark = AutoMark::query()->firstOrCreate(['url' => 'lada'], ['name' => 'Lada', 'name_ru' => 'Лада', 'country' => 'Россия', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 'vesta'], ['name' => 'Vesta', 'name_ru' => 'Веста', 'year_from' => 2015, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'i'], ['name' => 'I', 'year_from' => 2015, 'status' => true]);
        $sedan = $generation->series()->firstOrCreate(['url' => 'sedan'], ['name' => 'Седан', 'status' => true]);
        $wagon = $generation->series()->firstOrCreate(['url' => 'sw-cross'], ['name' => 'SW Cross', 'status' => true]);
        $equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($sedan, 'series')->state(['name' => '1.6 MT 106 л.с.']), 'modification')
            ->create(['name' => 'Comfort']);

        $vehicle = SiteVehicle::factory()->for($site)->forSeries($sedan)->create(['sort_order' => 0]);
        SiteVehicle::factory()->for($site)->forSeries($wagon)->create(['sort_order' => 1]);
        SiteOffer::factory()->forEquipment($equipment)->create(['site_vehicle_id' => $vehicle->id, 'price_minor' => 125_000_000]);

        $set = new SeriesMediaSet(['name' => 'Серебристый', 'swatch_hex' => '#c0c0c0', 'status' => true, 'sort_order' => 0]);
        $set->catalog_series_public_id = $sedan->public_id;
        $set->save();
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        foreach ([MediaAngle::FrontThreeQuarter, MediaAngle::Side] as $angle) {
            $path = "series-media/e2e/{$angle->value}.png";
            Storage::disk(SeriesMediaImage::DISK)->put($path, $png);
            SeriesMediaImage::factory()->for($set, 'set')->create([
                'angle' => $angle,
                'path' => $path,
                'original_name' => "{$angle->value}.png",
                'size_bytes' => strlen($png),
                'width' => 1,
                'height' => 1,
            ]);
        }
    }

    private function createUser(string $name, string $email, bool $verified = true): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
            'password' => 'e2e-password',
        ])->save();

        return $user;
    }

    private function createWorkspace(
        User $user,
        string $name,
        WorkspaceMemberStatus $status = WorkspaceMemberStatus::Active,
        ?Plan $plan = null,
    ): Workspace {
        $workspace = Workspace::create(['name' => $name]);
        $workspace->forceFill(['plan_id' => $plan?->id])->save();
        $workspace->addMember($user, WorkspaceRole::Owner, $status);

        return $workspace;
    }
}
