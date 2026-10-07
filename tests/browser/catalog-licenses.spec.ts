import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const customer = users.licensee;
const admin = users.licensesAdmin;

async function login(page: Page, user: { email: string; password: string }) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(user.email);
    await page.getByLabel('Пароль', { exact: true }).fill(user.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

function catalogCard(page: Page) {
    return page
        .getByRole('complementary', { name: 'Левая панель' })
        .getByTestId('catalog-block')
        .filter({ hasText: customer.block });
}

function addButton(page: Page) {
    return page
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: `Добавить блок «${customer.block}»` });
}

test('a Site license from a Super Admin unlocks a catalog Block for that Site only', async ({
    page,
    browser,
}) => {
    test.setTimeout(120_000);

    // Without a license the customer sees the access card and cannot add the Block.
    await login(page, customer);
    await page
        .getByRole('link', {
            name: `Открыть дизайнер сайта «${customer.site}»`,
        })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    await expect(catalogCard(page)).toContainText(`Автор: ${customer.author}`);
    await expect(catalogCard(page)).toContainText('Выдаёт администратор');
    await expect(catalogCard(page)).toContainText(
        'Блок выдаёт администратор Landflow для конкретного сайта.',
    );
    await expect(addButton(page)).toBeDisabled();

    // The Super Admin grants a license to exactly this Site by its subdomain.
    const adminContext = await browser.newContext({
        storageState: guestStorageState,
    });
    const adminPage = await adminContext.newPage();
    await login(adminPage, admin);
    await adminPage
        .getByRole('link', { name: 'Лицензии сайтов', exact: true })
        .click();
    await expect(
        adminPage.getByRole('heading', { name: 'Лицензии сайтов', level: 1 }),
    ).toBeVisible();
    await adminPage.getByLabel('Блок', { exact: true }).selectOption({
        label: `${customer.block} · ${customer.author} · Выдаёт администратор`,
    });
    await adminPage
        .getByLabel('Сайт', { exact: true })
        .fill(customer.subdomain);
    await adminPage.getByRole('button', { name: 'Выдать лицензию' }).click();
    await expect(
        adminPage.getByText(
            `Лицензия на «${customer.block}» выдана сайту «${customer.site}».`,
        ),
    ).toBeVisible();
    const row = adminPage
        .getByTestId('license-row')
        .filter({ hasText: customer.site });
    await expect(row).toContainText(customer.block);
    await expect(row).toContainText('Выдана администратором');

    // The customer can now add the Block; it renders in its sandbox frame.
    await page.reload();
    await expect(addButton(page)).toBeEnabled();
    await addButton(page).click();
    const frame = page.frameLocator(`iframe[title="${customer.block}"]`);
    await expect(
        frame.getByRole('heading', { name: 'Партнёрская витрина' }),
    ).toBeVisible();

    // Revoking the license locks adding again.
    await row
        .getByRole('button', {
            name: `Отозвать лицензию на «${customer.block}» у сайта «${customer.site}»`,
        })
        .click();
    const dialog = adminPage.getByRole('dialog', {
        name: 'Отозвать лицензию?',
    });
    await dialog.getByRole('button', { name: 'Отозвать лицензию' }).click();
    await expect(adminPage.getByText('Лицензия отозвана.')).toBeVisible();
    await expect(adminPage.getByTestId('license-row')).toHaveCount(0);
    await adminContext.close();

    await page.reload();
    await expect(addButton(page)).toBeDisabled();
});
