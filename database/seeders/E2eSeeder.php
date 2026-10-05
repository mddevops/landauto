<?php

namespace Database\Seeders;

use App\Actions\Sites\CreateSite;
use App\Blocks\BlockStateDefaults;
use App\Enums\Entitlement;
use App\Enums\MediaAngle;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModification;
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
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
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

        // Full publishing lifecycle: a ready Draft v1 (hero + priced vehicle card) and a Designer
        // member without publish/restore rights.
        $lifecycle = $this->createUser('Лев Циклов', 'lifecycle@landflow.test');
        $lifecycleWorkspace = $this->createWorkspace($lifecycle, 'Автосалон Цикл', plan: $plan);
        $lifecycleWorkspace->addMember($this->createUser('Дарья Оформителева', 'lifecycle-designer@landflow.test'), WorkspaceRole::Designer);
        $this->createLifecycleSite($lifecycleWorkspace);
    }

    private function createPublishingSite(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, Template::query()->where('slug', 'blank')->firstOrFail(), 'Сайт для публикации');
        $site->forceFill(['subdomain' => 'publish-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $form = Form::factory()->for($site)->withLeadFields()->create(['name' => 'Заявка с сайта']);
        Popup::factory()->for($site)->create(['name' => 'Обратный звонок'])->form()->associate($form)->save();
    }

    /**
     * Site «Сайт жизненного цикла» on `lifecycle-e2e`: home Page with a hero (Popup button and a
     * scroll button to the vehicle card) and a priced vehicle of a dedicated catalog branch whose
     * media image is backed by a real file.
     */
    private function createLifecycleSite(Workspace $workspace): void
    {
        $site = app(CreateSite::class)->create($workspace, Template::query()->where('slug', 'blank')->firstOrFail(), 'Сайт жизненного цикла');
        $site->forceFill(['subdomain' => 'lifecycle-e2e', 'form_security' => ['ip_limit' => 1000]])->save();
        $form = Form::factory()->for($site)->withLeadFields()->create(['name' => 'Заявка на тест-драйв']);
        $popup = Popup::factory()->for($site)->create(['name' => 'Тест-драйв', 'title' => 'Тест-драйв за 15 минут']);
        $popup->form()->associate($form)->save();

        $mark = AutoMark::query()->firstOrCreate(['url' => 'moskvich'], ['name' => 'Moskvich', 'name_ru' => 'Москвич', 'country' => 'Россия', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 'moskvich-3'], ['name' => 'Moskvich 3', 'name_ru' => 'Москвич 3', 'year_from' => 2022, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'i'], ['name' => 'I', 'year_from' => 2022, 'status' => true]);
        $series = $generation->series()->firstOrCreate(['url' => 'crossover'], ['name' => 'Кроссовер', 'status' => true]);
        $equipment = AutoEquipment::factory()
            ->for(AutoModification::factory()->for($series, 'series')->state(['name' => '1.5 CVT 150 л.с.']), 'modification')
            ->create(['name' => 'Люкс']);
        $vehicle = SiteVehicle::factory()->for($site)->forSeries($series)->create(['sort_order' => 0]);
        SiteOffer::factory()->forEquipment($equipment)->create(['site_vehicle_id' => $vehicle->id, 'price_minor' => 199_000_000]);

        $set = new SeriesMediaSet(['name' => 'Белый', 'swatch_hex' => '#f4f4f4', 'status' => true, 'sort_order' => 0]);
        $set->catalog_series_public_id = $series->public_id;
        $set->save();
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $path = 'series-media/e2e-lifecycle/front_3_4.png';
        Storage::disk(SeriesMediaImage::DISK)->put($path, $png);
        SeriesMediaImage::factory()->for($set, 'set')->create([
            'angle' => MediaAngle::FrontThreeQuarter,
            'path' => $path,
            'original_name' => 'front_3_4.png',
            'size_bytes' => strlen($png),
            'width' => 1,
            'height' => 1,
        ]);

        $home = $site->pages()->where('is_home', true)->firstOrFail();
        $card = $this->placeBlock($home, 'vehicle-card', 1, ['vehicle' => $vehicle->public_id]);
        $this->placeBlock($home, 'hero', 0, [
            'title' => 'Цикл: версия 1',
            'primary_button' => ['label' => 'Записаться на тест-драйв', 'action' => ['type' => 'open_popup', 'popup' => $popup->public_id]],
            'secondary_button' => ['label' => 'К ценам', 'action' => ['type' => 'scroll_to', 'block' => $card->public_id]],
        ]);
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
        $site = app(CreateSite::class)->create($workspace, Template::query()->where('slug', 'blank')->firstOrFail(), 'Витрина Запад');
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
