// Test-only users created by database/seeders/E2eSeeder.php (keep both files in sync).
export const users = {
    // Authenticated storage state for most tests (see auth.setup.ts).
    member: {
        name: 'Александра Константиновна Преображенская',
        email: 'member@landflow.test',
        password: 'e2e-password',
        workspaces: ['Личный автопарк', 'Автосалон Север'],
    },
    // Login/logout flows only, so they do not share the login rate limit with `member`.
    login: {
        name: 'Иван Петров',
        email: 'login@landflow.test',
        password: 'e2e-password',
        workspace: 'Workspace Ивана',
    },
    unverified: {
        name: 'Мария Неподтверждённая',
        email: 'unverified@landflow.test',
        password: 'e2e-password',
    },
    // Core platform flow: two Workspaces with a Site limit and the official Blank Template.
    creator: {
        name: 'Олег Создатель',
        email: 'creator@landflow.test',
        password: 'e2e-password',
        workspaces: ['Автосалон Юг', 'Сервисный центр Юг'],
        start: 'Пустой старт',
    },
    // Designer flow on its own Site (Workspace Owner, so preview is allowed).
    designer: {
        name: 'Дина Дизайнерова',
        email: 'designer@landflow.test',
        password: 'e2e-password',
        workspace: 'Студия Дины',
        start: 'Пустой старт',
    },
    // Automotive flow: platform super admin (catalog + Series media) and a dealer Owner.
    catalogAdmin: {
        name: 'Пётр Каталогов',
        email: 'catalog@landflow.test',
        password: 'e2e-password',
    },
    dealer: {
        name: 'Денис Дилеров',
        email: 'dealer@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Восток',
        start: 'Пустой старт',
    },
    // Interactive flow: Forms, Popups and submissions on its own Site.
    interactive: {
        name: 'Инна Интерактивова',
        email: 'interactive@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Запад',
        start: 'Пустой старт',
    },
    // Publishing flow: Site «Сайт для публикации» on the `publish-e2e` subdomain.
    publisher: {
        name: 'Павел Публикаторов',
        email: 'publisher@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Центр',
        site: 'Сайт для публикации',
        publicUrl: 'http://publish-e2e.localhost:8200',
    },
    // Full publishing lifecycle on `lifecycle-e2e`: Owner, Admin (publish) and Designer (preview).
    lifecycle: {
        name: 'Лев Циклов',
        email: 'lifecycle@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Цикл',
        site: 'Сайт жизненного цикла',
        publicUrl: 'http://lifecycle-e2e.localhost:8200',
    },
    lifecycleDesigner: {
        name: 'Дарья Оформителева',
        email: 'lifecycle-designer@landflow.test',
        password: 'e2e-password',
    },
    // Integrations flow on `integrations-e2e`: Owner, Admin (logs, retry), Designer (no access).
    integrations: {
        name: 'Ирина Интеграторова',
        email: 'integrations@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Интеграция',
        site: 'Сайт интеграций',
        publicUrl: 'http://integrations-e2e.localhost:8200',
    },
    integrationsAdmin: {
        name: 'Игорь Админов',
        email: 'integrations-admin@landflow.test',
        password: 'e2e-password',
    },
    integrationsDesigner: {
        name: 'Ника Дизайнова',
        email: 'integrations-designer@landflow.test',
        password: 'e2e-password',
    },
    lifecycleAdmin: {
        name: 'Антон Админов',
        email: 'lifecycle-admin@landflow.test',
        password: 'e2e-password',
    },
    // Workspace / Site navigation: starts with one Workspace and one Site.
    navigator: {
        name: 'Нина Навигаторова',
        email: 'navigator@landflow.test',
        password: 'e2e-password',
        workspace: 'Автодом Навигатор',
        site: 'Сайт навигации',
    },
    // Custom domains + branding on `domains-e2e`; the plan has `custom_domain`.
    domains: {
        name: 'Дмитрий Доменов',
        email: 'domains@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Домен',
        site: 'Сайт с доменом',
        publicUrl: 'http://domains-e2e.localhost:8200',
    },
    domainsDesigner: {
        name: 'Диана Доменная',
        email: 'domains-designer@landflow.test',
        password: 'e2e-password',
    },
    // Phase 8 team flows: Team plan (10 seats), Site A on `team-a-e2e` and Site B.
    teamOwner: {
        name: 'Тимур Командиров',
        email: 'team-owner@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Команда',
        siteA: 'Сайт команды А',
        siteB: 'Сайт команды Б',
        publicUrl: 'http://team-a-e2e.localhost:8200',
    },
    // Not a member yet: joins through the invitation flow.
    teamDesigner: {
        name: 'Дмитрий Макетов',
        email: 'team-designer@landflow.test',
        password: 'e2e-password',
        workspace: 'Студия Макетова',
    },
    teamPricing: {
        name: 'Полина Ценова',
        email: 'team-pricing@landflow.test',
        password: 'e2e-password',
    },
    teamPublisher: {
        name: 'Пётр Выпускалов',
        email: 'team-publisher@landflow.test',
        password: 'e2e-password',
    },
    teamLeads: {
        name: 'Лидия Заявкина',
        email: 'team-leads@landflow.test',
        password: 'e2e-password',
    },
    teamIntegrations: {
        name: 'Иван Связев',
        email: 'team-integrations@landflow.test',
        password: 'e2e-password',
    },
    // Site formats: plan without `multi_page_sites`; official Quiz / Chat Templates exist.
    formats: {
        name: 'Фёкла Форматова',
        email: 'formats@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Формат',
        quizTemplate: 'Квиз: подбор автомобиля',
        chatTemplate: 'Чат: подбор автомобиля',
    },
    // Block authoring: active Developer Profile with `create_blocks`, no platform role.
    developer: {
        name: 'Девелопер Блоков',
        email: 'developer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия блоков E2E',
    },
    // Block Studio code / schema editing; same rights as `developer`, separate login throttle.
    studioDeveloper: {
        name: 'Сергей Студийный',
        email: 'studio-developer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия кода E2E',
    },
    // Sandboxed runtime: platform Block «Промо из студии» and a Site on `sandbox-e2e` with a Popup.
    sandbox: {
        name: 'Сабина Песочникова',
        email: 'sandbox@landflow.test',
        password: 'e2e-password',
        site: 'Сайт с блоком из студии',
        block: 'Промо из студии',
        publicUrl: 'http://sandbox-e2e.localhost:8200',
    },
    // Native runtime (X-024): seeded Native Blocks on `native-e2e` (mixed runtimes, a Popup) and an
    // unapproved Native JavaScript Block on `native-js-e2e`.
    native: {
        name: 'Наиль Нативный',
        email: 'native@landflow.test',
        password: 'e2e-password',
        site: 'Сайт с нативными блоками',
        blockedSite: 'Сайт с неодобренным кодом',
        publicUrl: 'http://native-e2e.localhost:8200',
        blockedPublicUrl: 'http://native-js-e2e.localhost:8200',
    },
    // Customer catalog (D-121): Developer Block «Витрина партнёра» needs a Super Admin license for
    // a Site or for the whole Workspace «Автосалон Лицензия» (two seeded Sites).
    licensee: {
        name: 'Лиана Лицензиатова',
        email: 'licensee@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Лицензия',
        site: 'Сайт по лицензии',
        subdomain: 'license-e2e',
        secondSite: 'Второй сайт по лицензии',
        secondSubdomain: 'license-two-e2e',
        block: 'Витрина партнёра',
        author: 'Студия каталога E2E',
    },
    // Another Workspace that must never inherit «Автосалон Лицензия» licenses.
    licenseeOther: {
        name: 'Олег Сторонний',
        email: 'licensee-other@landflow.test',
        password: 'e2e-password',
        site: 'Сайт другого пространства',
    },
    licensesAdmin: {
        name: 'Ольга Лицензиарова',
        email: 'licenses-admin@landflow.test',
        password: 'e2e-password',
    },
    // Template Builder: Developer Profile with `create_templates`, no Templates yet.
    templateDeveloper: {
        name: 'Тимур Шаблонов',
        email: 'template-developer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия шаблонов E2E',
    },
    // Quiz flow: plan without `multi_page_sites`; the official quiz Template is seeded.
    quiz: {
        name: 'Зоя Квизова',
        email: 'quiz@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Квиз',
        template: 'Квиз: подбор автомобиля',
    },
    // Chat flow: plan without `multi_page_sites`; the official chat Template is seeded.
    chat: {
        name: 'Чеслав Чатов',
        email: 'chat@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Чат',
        template: 'Чат: подбор автомобиля',
    },
    // Template installation: Developer who publishes and a customer who creates Sites from it.
    templateInstaller: {
        name: 'Илья Установщиков',
        email: 'template-installer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия установки E2E',
    },
    templateCustomer: {
        name: 'Карина Шаблонова',
        email: 'template-customer@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Шаблон',
    },
    // Developer platform E2E (P9-011): a Developer and two customers, only one with `custom_domain`.
    platformDeveloper: {
        name: 'Пётр Платформенный',
        email: 'platform-developer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия платформы E2E',
    },
    platformCustomer: {
        name: 'Вера Клиентова',
        email: 'platform-customer@landflow.test',
        password: 'e2e-password',
        site: 'Сайт без опции домена',
    },
    platformPremium: {
        name: 'Марк Премиумов',
        email: 'platform-premium@landflow.test',
        password: 'e2e-password',
        site: 'Сайт с опцией домена',
    },
    // Marketplace Listings (P10-001): only `submit_marketplace_item`; one published own Block per
    // browser project, because a product has at most one listing.
    marketplaceDeveloper: {
        name: 'Мирон Маркетов',
        email: 'marketplace-developer@landflow.test',
        password: 'e2e-password',
        profile: 'Студия Marketplace E2E',
        blocks: {
            desktop: 'Витрина Marketplace desktop',
            tablet: 'Витрина Marketplace tablet',
            mobile: 'Витрина Marketplace mobile',
        },
    },
} as const;

// Fixed public IDs of the foreign Workspace «Автосалон Чужой» (E2eSeeder::createTeamWorkspace).
export const foreignTeamIds = {
    workspace: '01k0f0re0000000000000000w1',
    member: '01k0f0re0000000000000000m1',
    vehicle: '01k0f0re0000000000000000v1',
    asset: '01k0f0re0000000000000000a1',
} as const;

export const memberStorageState = 'playwright/.auth/member.json';

export const guestStorageState = { cookies: [], origins: [] };
