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
