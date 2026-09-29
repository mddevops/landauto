import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

async function submitLogin(page: Page, email: string, password: string) {
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
}

test(
    'login page renders in Russian',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/login');

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle('Вход - Landflow');
        await expect(
            page.getByRole('heading', { name: 'Вход в аккаунт' }),
        ).toBeVisible();
        await expect(page.getByLabel('Электронная почта')).toBeVisible();
        await expect(page.getByLabel('Пароль', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Запомнить меня')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Войти', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Зарегистрироваться' }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'auth', 'login');
    },
);

test('guest is redirected from dashboard to login', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL('/login');
    await expect(
        page.getByRole('heading', { name: 'Вход в аккаунт' }),
    ).toBeVisible();
});

test('wrong password shows a Russian error and keeps the user on login', async ({
    page,
}, testInfo) => {
    await page.goto('/login');
    await submitLogin(page, users.login.email, 'wrong-password');

    await expect(
        page.getByText('Неверная электронная почта или пароль.'),
    ).toBeVisible();
    await expect(page).toHaveURL('/login');

    await captureScreenshot(
        page,
        testInfo,
        'auth',
        'login-invalid-credentials',
    );
});

test('user can log in with the keyboard and reach the dashboard', async ({
    page,
}) => {
    await page.goto('/login');

    await expect(page.getByLabel('Электронная почта')).toBeFocused();
    await page.keyboard.type(users.login.email);
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Пароль', { exact: true })).toBeFocused();
    await page.keyboard.type(users.login.password);
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL('/dashboard');
    await expect(page).toHaveTitle('Панель управления - Landflow');
});

test('user can log out from the user menu', async ({ page }) => {
    await page.goto('/login');
    await submitLogin(page, users.login.email, users.login.password);
    await expect(page).toHaveURL('/dashboard');

    await page.getByRole('button', { name: users.login.name }).click();
    await page.getByRole('menuitem', { name: 'Выйти' }).click();

    await expect(page).toHaveURL('/');
    await page.goto('/dashboard');
    await expect(page).toHaveURL('/login');
});
