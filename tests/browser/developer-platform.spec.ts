import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const developer = users.platformDeveloper;
const customer = users.platformCustomer;
const premium = users.platformPremium;
const catalogAdmin = users.licensesAdmin;

type Account = { email: string; password: string };

async function login(page: Page, account: Account) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function openAs(browser: Browser, account: Account): Promise<Page> {
    const context = await browser.newContext({
        storageState: guestStorageState,
    });
    const page = await context.newPage();
    await login(page, account);

    return page;
}

/** Waits for the debounced Block Studio autosave of the latest edit. */
async function expectDraftSaved(page: Page) {
    const status = page.locator('[data-status]');
    await expect(status).not.toHaveAttribute('data-status', 'saved');
    await expect(status).toHaveAttribute('data-status', 'saved');
}

async function editSource(page: Page, file: string, value: string) {
    await page.getByRole('button', { name: file }).click();
    await page.getByRole('textbox', { name: file, exact: true }).fill(value);
    await expectDraftSaved(page);
}

async function openDesigner(page: Page, site: string) {
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${site}»` })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
}

test('a Block published in Block Studio reaches customers according to its catalog access', async ({
    page,
    browser,
}) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const name = `Баннер платформы ${suffix}`;

    // The Developer authors and publishes a Block; no review queue stands in between.
    await login(page, developer);
    await page.goto('/developer/blocks/create');
    await page.getByLabel('Название').fill(name);
    await page
        .getByLabel('Категория')
        .selectOption({ label: 'Призыв к действию' });
    await page.getByLabel('Slug').fill(`e2e-platform-${suffix}`);
    await page
        .getByRole('button', { name: 'Создать блок', exact: true })
        .click();
    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);

    await editSource(
        page,
        'schema.json',
        '{"fields":[{"key":"title","type":"text","label":"Заголовок","default":"Платформенный баннер","max_length":80}]}',
    );
    await editSource(
        page,
        'index.html',
        '<section class="banner"><h2>{{ title }}</h2></section>',
    );
    await expect(
        page.getByRole('complementary', { name: 'Проверки перед публикацией' }),
    ).toContainText('Все проверки пройдены.');
    await page
        .getByRole('button', {
            name: 'Одобрить и опубликовать',
            exact: true,
        })
        .click();
    await expect(page.getByText('Опубликована версия 1.0.0.')).toBeVisible();

    // The author selects the «По тарифу» mode; Super Admin chooses the Plans that include it.
    await page.getByRole('tab', { name: 'Настройки', exact: true }).click();
    const access = page.getByRole('region', { name: 'Доступ в каталоге' });
    await access.getByLabel('Режим доступа').selectOption('entitlement');
    await access.getByRole('button', { name: 'Сохранить доступ' }).click();
    await expect(page.getByText('Доступ в каталоге сохранён.')).toBeVisible();

    const admin = await openAs(browser, catalogAdmin);
    await admin.goto('/platform/catalog-access');
    const item = admin
        .getByRole('heading', { name })
        .locator('xpath=ancestor::section[1]');
    await item.getByRole('checkbox', { name: 'E2E Domains' }).check();
    await item.getByRole('button', { name: 'Сохранить тарифы' }).click();
    await expect(
        admin.getByText('Доступ блока по тарифам сохранён.'),
    ).toBeVisible();
    await admin.context().close();

    // A customer whose Plan is not mapped sees the Russian upgrade reason and a disabled Add button.
    const refused = await openAs(browser, customer);
    await openDesigner(refused, customer.site);
    const refusedCard = refused
        .getByRole('complementary', { name: 'Левая панель' })
        .getByTestId('catalog-block')
        .filter({ hasText: name });
    await expect(refusedCard).toContainText(`Автор: ${developer.profile}`);
    await expect(refusedCard).toContainText('По тарифу · Тарифы: E2E Domains');
    await expect(refusedCard).toContainText(
        'Для доступа к блоку нужен тариф «E2E Domains» или отдельная лицензия каталога.',
    );
    await expect(
        refusedCard.getByRole('button', { name: `Добавить блок «${name}»` }),
    ).toBeDisabled();
    await refused.context().close();

    // A customer on the mapped Plan adds the Block and edits it through its schema.
    const allowed = await openAs(browser, premium);
    await openDesigner(allowed, premium.site);
    await allowed
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: `Добавить блок «${name}»` })
        .click();
    const frame = allowed.locator(`iframe[title="${name}"]`);
    await expect(frame).toHaveAttribute('sandbox', 'allow-scripts');
    const canvas = allowed.frameLocator(`iframe[title="${name}"]`);
    await expect(
        canvas.getByRole('heading', { name: 'Платформенный баннер' }),
    ).toBeVisible();
    await allowed
        .getByRole('complementary', { name: 'Свойства' })
        .getByLabel('Заголовок', { exact: true })
        .fill('Баннер премиум');
    await expect(
        canvas.getByRole('heading', { name: 'Баннер премиум' }),
    ).toBeVisible();
    await expect(allowed.getByText('Сохранено', { exact: true })).toBeVisible();

    // An unpublished Draft change never reaches the placed Instance.
    await page.getByRole('tab', { name: 'Код', exact: true }).click();
    await editSource(
        page,
        'index.html',
        '<section class="banner"><h2>{{ title }}</h2><p>Черновик автора</p></section>',
    );
    await allowed.reload();
    await expect(
        canvas.getByRole('heading', { name: 'Баннер премиум' }),
    ).toBeVisible();
    await expect(canvas.getByText('Черновик автора')).toHaveCount(0);
    await allowed.context().close();
});
