// Test-only users created by database/seeders/E2eSeeder.php (keep both files in sync).
export const users = {
    // Authenticated storage state for most tests (see auth.setup.ts).
    member: {
        name: 'Александра Константиновна Преображенская',
        email: 'member@landflow.test',
        password: 'e2e-password',
    },
    // Login/logout flows only, so they do not share the login rate limit with `member`.
    login: {
        name: 'Иван Петров',
        email: 'login@landflow.test',
        password: 'e2e-password',
    },
    unverified: {
        name: 'Мария Неподтверждённая',
        email: 'unverified@landflow.test',
        password: 'e2e-password',
    },
} as const;

export const memberStorageState = 'playwright/.auth/member.json';

export const guestStorageState = { cookies: [], origins: [] };
