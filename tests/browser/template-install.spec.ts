import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const author = users.templateInstaller;
const customer = users.templateCustomer;

async function login(page: Page, account: { email: string; password: string }) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function setHeroTitle(page: Page, title: string) {
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await properties.getByLabel('Заголовок', { exact: true }).fill(title);
    await expect(page.getByRole('main', { name: 'Холст' })).toContainText(
        title,
    );
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

test('a Site created from a published Template is an independent copy', async ({
    browser,
}) => {
    test.setTimeout(150_000);
    const suffix = Date.now().toString(36);
    const templateName = `Лендинг установки ${suffix}`;
    const siteName = `Сайт из шаблона ${suffix}`;

    const authorContext = await browser.newContext();
    const authorPage = await authorContext.newPage();
    await login(authorPage, author);

    await authorPage.goto('/developer/templates/create');
    await authorPage.getByLabel('Название').fill(templateName);
    await authorPage.getByLabel('Slug').fill(`e2e-install-${suffix}`);
    await authorPage.getByLabel('Лендинг', { exact: true }).check();
    await authorPage.getByRole('button', { name: 'Создать шаблон' }).click();
    await expect(authorPage).toHaveURL(/\/designer$/);
    const designerUrl = authorPage.url();

    await authorPage
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    await setHeroTitle(authorPage, `Акция ${suffix}`);

    await authorPage.getByRole('link', { name: 'Публикация шаблона' }).click();
    await authorPage
        .getByRole('button', { name: 'Опубликовать версию' })
        .click();
    await expect(
        authorPage.getByText('Опубликована версия 1.0.0.'),
    ).toBeVisible();

    // The customer installs the published version into a new landing Site.
    const customerContext = await browser.newContext();
    const customerPage = await customerContext.newPage();
    await login(customerPage, customer);

    await customerPage.goto('/sites/create');
    await customerPage
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: /^Лендинг/ })
        .check();
    const startGroup = customerPage.getByRole('group', { name: '2. Старт' });
    const templateRadio = startGroup.getByRole('radio', { name: templateName });
    await expect(templateRadio).toBeEnabled();
    await expect(startGroup).toContainText(`Автор: ${author.profile}`);
    await templateRadio.check();
    await customerPage.getByLabel('Название сайта').fill(siteName);
    await customerPage.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(customerPage).toHaveURL(/\/dashboard\?site=/);

    await customerPage
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();
    const customerCanvas = customerPage.getByRole('main', { name: 'Холст' });
    await expect(customerCanvas).toContainText(`Акция ${suffix}`);
    const siteDesignerUrl = customerPage.url();

    // Editing and republishing the Template never changes the existing Site.
    await authorPage.goto(designerUrl);
    await authorPage
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    await setHeroTitle(authorPage, `Новая акция ${suffix}`);
    await authorPage.getByRole('link', { name: 'Публикация шаблона' }).click();
    await authorPage
        .getByRole('button', { name: 'Опубликовать версию' })
        .click();
    await expect(
        authorPage.getByText('Опубликована версия 1.1.0.'),
    ).toBeVisible();

    await customerPage.goto(siteDesignerUrl);
    await expect(customerCanvas).toContainText(`Акция ${suffix}`);
    await expect(customerCanvas).not.toContainText(`Новая акция ${suffix}`);

    await authorContext.close();
    await customerContext.close();
});
