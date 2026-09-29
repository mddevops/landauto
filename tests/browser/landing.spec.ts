import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { guestStorageState } from './support/users';

test.use({ storageState: guestStorageState });

test(
    'landing page renders in Russian for guests',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/');

        expect(response?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ru');
        await expect(page).toHaveTitle('Добро пожаловать - Landflow');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Landflow' }),
        ).toBeVisible();
        await expect(
            page.getByText('Конструктор сайтов для автомобильного бизнеса.'),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Войти' })).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Регистрация' }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'landing', 'guest');
    },
);

test('landing page leads guests to the login page', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Войти' }).click();

    await expect(page).toHaveURL('/login');
    await expect(
        page.getByRole('heading', { name: 'Вход в аккаунт' }),
    ).toBeVisible();
});
