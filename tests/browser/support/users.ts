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
        template: 'Пустой шаблон',
    },
    // Designer flow on its own Site (Workspace Owner, so preview is allowed).
    designer: {
        name: 'Дина Дизайнерова',
        email: 'designer@landflow.test',
        password: 'e2e-password',
        workspace: 'Студия Дины',
        template: 'Пустой шаблон',
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
        template: 'Пустой шаблон',
    },
    // Interactive flow: Forms, Popups and submissions on its own Site.
    interactive: {
        name: 'Инна Интерактивова',
        email: 'interactive@landflow.test',
        password: 'e2e-password',
        workspace: 'Автосалон Запад',
        template: 'Пустой шаблон',
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
    lifecycleAdmin: {
        name: 'Антон Админов',
        email: 'lifecycle-admin@landflow.test',
        password: 'e2e-password',
    },
} as const;

export const memberStorageState = 'playwright/.auth/member.json';

export const guestStorageState = { cookies: [], origins: [] };
