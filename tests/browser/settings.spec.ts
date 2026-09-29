import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { users } from './support/users';

test(
    'profile settings render in Russian with the current account data',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/settings/profile');

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle('Настройки профиля - Landflow');
        await expect(
            page.getByRole('heading', { name: 'Настройки', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Профиль', exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Имя')).toHaveValue(users.member.name);
        await expect(page.getByLabel('Электронная почта')).toHaveValue(
            users.member.email,
        );
        await expect(
            page.getByRole('button', { name: 'Сохранить' }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'settings', 'profile');
    },
);

test('settings navigation opens appearance settings', async ({ page }) => {
    await page.goto('/settings/profile');

    await page
        .getByRole('navigation', { name: 'Настройки' })
        .getByRole('link', { name: 'Внешний вид' })
        .click();

    await expect(page).toHaveURL('/settings/appearance');
    await expect(page).toHaveTitle('Внешний вид - Landflow');
    await expect(
        page.getByRole('heading', { level: 2, name: 'Внешний вид' }),
    ).toBeVisible();
});

test('security settings require password confirmation', async ({
    page,
}, testInfo) => {
    await page.goto('/settings/profile');

    await page
        .getByRole('navigation', { name: 'Настройки' })
        .getByRole('link', { name: 'Безопасность' })
        .click();

    await expect(
        page.getByRole('heading', { name: 'Подтверждение пароля' }),
    ).toBeVisible();

    await page
        .getByLabel('Пароль', { exact: true })
        .fill(users.member.password);
    await page
        .getByRole('button', { name: 'Подтвердить', exact: true })
        .click();

    await expect(page).toHaveURL('/settings/security');
    await expect(
        page.getByRole('heading', { name: 'Смена пароля' }),
    ).toBeVisible();

    await captureScreenshot(page, testInfo, 'settings', 'security');
});

test('profile validation errors are shown in Russian', async ({ page }) => {
    await page.goto('/settings/profile');

    // Whitespace passes the browser `required` check; the server trims it and rejects it.
    await page.getByLabel('Имя').fill('   ');
    await page.getByRole('button', { name: 'Сохранить' }).click();

    await expect(
        page.getByText('Поле «Имя» обязательно для заполнения.'),
    ).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Имя')).toHaveValue(users.member.name);
});
