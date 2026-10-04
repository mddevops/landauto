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
} as const;

export const memberStorageState = 'playwright/.auth/member.json';

export const guestStorageState = { cookies: [], origins: [] };
