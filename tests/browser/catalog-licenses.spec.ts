import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });
// Both tests grant and revoke licenses for the same Block and Workspace.
test.describe.configure({ mode: 'serial' });

const customer = users.licensee;
const other = users.licenseeOther;
const admin = users.licensesAdmin;

async function login(page: Page, user: { email: string; password: string }) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(user.email);
    await page.getByLabel('Пароль', { exact: true }).fill(user.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function openDesigner(page: Page, site: string) {
    await page.goto('/dashboard');
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${site}»` })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
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

function blockFrame(page: Page) {
    return page
        .frameLocator(`iframe[title="${customer.block}"]`)
        .first()
        .getByRole('heading', { name: 'Партнёрская витрина' });
}

async function openLicenses(browser: Browser) {
    const context = await browser.newContext({
        storageState: guestStorageState,
    });
    const page = await context.newPage();
    await login(page, admin);
    await page
        .getByRole('link', { name: 'Лицензии каталога', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Лицензии каталога', level: 1 }),
    ).toBeVisible();

    return { context, page };
}

async function grant(
    adminPage: Page,
    scope: 'Один сайт' | 'Всё пространство',
    target: string,
) {
    await adminPage
        .getByLabel('Блок или шаблон', { exact: true })
        .selectOption({
            label: `Блок «${customer.block}» · ${customer.author} · Выдаёт администратор`,
        });
    await adminPage
        .getByRole('group', { name: 'На что выдаётся' })
        .getByRole('radio', { name: scope })
        .check();
    await adminPage
        .getByLabel(scope === 'Один сайт' ? 'Сайт' : 'Пространство', {
            exact: true,
        })
        .fill(target);
    await adminPage.getByRole('button', { name: 'Выдать лицензию' }).click();
}

async function revoke(adminPage: Page, recipient: string) {
    await adminPage
        .getByRole('button', {
            name: `Отозвать лицензию на «${customer.block}» у ${recipient}`,
        })
        .click();
    const dialog = adminPage.getByRole('dialog', {
        name: 'Отозвать лицензию?',
    });
    await dialog.getByRole('button', { name: 'Отозвать лицензию' }).click();
    await expect(adminPage.getByText('Лицензия отозвана.')).toBeVisible();
}

test('a Site license unlocks a catalog Block for that one Site only', async ({
    page,
    browser,
}) => {
    test.setTimeout(120_000);

    // Without a license the customer sees the access card and cannot add the Block.
    await login(page, customer);
    await openDesigner(page, customer.site);
    await expect(catalogCard(page)).toContainText(`Автор: ${customer.author}`);
    await expect(catalogCard(page)).toContainText('Выдаёт администратор');
    await expect(catalogCard(page)).toContainText(
        'Блок выдаёт администратор Landflow для сайта или всего пространства.',
    );
    await expect(addButton(page)).toBeDisabled();

    const { context: adminContext, page: adminPage } =
        await openLicenses(browser);
    await grant(adminPage, 'Один сайт', customer.subdomain);
    await expect(
        adminPage.getByText(
            `Лицензия на «${customer.block}» выдана сайту «${customer.site}».`,
        ),
    ).toBeVisible();
    const row = adminPage
        .getByTestId('license-row')
        .filter({ hasText: customer.site });
    await expect(row).toContainText(customer.block);
    await expect(row).toContainText(`Один сайт: ${customer.site}`);
    await expect(row).toContainText(customer.workspace);
    await expect(row).toContainText('Выдана администратором');

    // The licensed Site adds the Block; it renders in its sandbox frame.
    await page.reload();
    await expect(addButton(page)).toBeEnabled();
    await addButton(page).click();
    await expect(blockFrame(page)).toBeVisible();

    // The second Site of the same Workspace is not covered.
    await openDesigner(page, customer.secondSite);
    await expect(addButton(page)).toBeDisabled();

    await revoke(adminPage, `сайта «${customer.site}»`);
    await expect(adminPage.getByTestId('license-row')).toHaveCount(0);
    await adminContext.close();

    // Revocation blocks new installs; the installed version keeps rendering (D-122).
    await openDesigner(page, customer.site);
    await expect(addButton(page)).toBeDisabled();
    await expect(blockFrame(page)).toBeVisible();
});

test('a Workspace license covers every current and future Site of that Workspace only', async ({
    page,
    browser,
}) => {
    test.setTimeout(150_000);
    const newSite = `Новый сайт ${Date.now().toString(36)}`;

    // Addressed by the subdomain of any Site in the Workspace.
    const { context: adminContext, page: adminPage } =
        await openLicenses(browser);
    await grant(adminPage, 'Всё пространство', customer.secondSubdomain);
    await expect(
        adminPage.getByText(
            `Лицензия на «${customer.block}» выдана пространству «${customer.workspace}».`,
        ),
    ).toBeVisible();
    await expect(
        adminPage
            .getByTestId('license-row')
            .filter({ hasText: `Всё пространство: ${customer.workspace}` }),
    ).toContainText(customer.block);

    await login(page, customer);
    for (const site of [customer.site, customer.secondSite]) {
        await openDesigner(page, site);
        await expect(addButton(page)).toBeEnabled();
    }

    // A Site created after the grant is covered too.
    await page.goto('/sites/create');
    await page
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: /^Лендинг/ })
        .check();
    await page.getByLabel('Название сайта').fill(newSite);
    await page.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(page).toHaveURL(/\/dashboard\?site=/);
    await openDesigner(page, newSite);
    await expect(addButton(page)).toBeEnabled();
    await addButton(page).click();
    await expect(blockFrame(page)).toBeVisible();

    // Another Workspace gains nothing.
    const otherContext = await browser.newContext({
        storageState: guestStorageState,
    });
    const otherPage = await otherContext.newPage();
    await login(otherPage, other);
    await openDesigner(otherPage, other.site);
    await expect(addButton(otherPage)).toBeDisabled();
    await otherContext.close();

    await revoke(adminPage, `пространства «${customer.workspace}»`);
    await adminContext.close();

    await openDesigner(page, newSite);
    await expect(addButton(page)).toBeDisabled();
    await expect(blockFrame(page)).toBeVisible();
});
