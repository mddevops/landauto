import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const ulidPattern = /^[0-9A-HJKMNP-TV-Z]{26}$/i;

test('owner logs in, creates a Site from a Template and another Workspace cannot see it', async ({
    page,
}) => {
    const creator = users.creator;
    const siteName = `Сайт E2E ${Date.now()}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(creator.email);
    await page.getByLabel('Пароль', { exact: true }).fill(creator.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();

    await expect(page).toHaveURL('/dashboard');
    await expect(
        page.getByRole('heading', { level: 1, name: creator.workspaces[0] }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Создать сайт' }).click();

    await expect(page).toHaveURL('/sites/create');
    await expect(page).toHaveTitle('Создание сайта - Landflow');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Создание сайта' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);

    const templates = page.getByRole('group', { name: '1. Шаблон' });
    const template = templates.getByRole('radio', { name: creator.template });
    await template.check();
    await expect(template).toBeChecked();
    await page.getByLabel('Название сайта').fill(siteName);
    await page.getByRole('button', { name: 'Создать сайт' }).click();

    await expect(page).toHaveURL(/\/dashboard\?site=/);
    const createdSiteId = new URL(page.url()).searchParams.get('site');
    expect(createdSiteId).toMatch(ulidPattern);
    await expect(page.getByRole('alert')).toContainText('Сайт создан');
    await expect(page.getByRole('alert')).toContainText(siteName);
    await expect(
        page.getByRole('heading', { level: 3, name: siteName }),
    ).toBeVisible();

    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();

    await expect(page).toHaveURL(`/sites/${createdSiteId}/designer`);
    await expect(page).toHaveTitle(`Дизайнер — ${siteName} - Landflow`);
    await expect(
        page.getByRole('heading', { level: 1, name: siteName }),
    ).toBeVisible();
    await expect(page.getByText('Главная', { exact: true })).toBeVisible();
    await expect(page.getByRole('main', { name: 'Холст' })).toContainText(
        'На странице пока нет блоков.',
    );
    await expect(
        page.getByRole('complementary', { name: 'Блоки страницы' }),
    ).toBeVisible();
    await expect(
        page.getByRole('complementary', { name: 'Свойства' }),
    ).toContainText('Выберите блок, чтобы увидеть его свойства.');
    await expectNoHorizontalOverflow(page);

    await page.getByRole('link', { name: 'Назад к сайтам' }).click();
    await expect(page).toHaveURL('/dashboard');
    await expect(
        page.getByRole('heading', { level: 1, name: creator.workspaces[0] }),
    ).toBeVisible();

    await page
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${creator.workspaces[0]}`,
        })
        .click();
    await page
        .getByRole('menu')
        .getByRole('menuitem', { name: creator.workspaces[1] })
        .click();

    await expect(
        page.getByRole('heading', { level: 1, name: creator.workspaces[1] }),
    ).toBeVisible();
    await expect(page.getByRole('heading', { name: siteName })).toHaveCount(0);

    // A known Site public ID from another Workspace must not reveal or confirm that Site.
    await page.goto(`/dashboard?site=${createdSiteId}`);
    await expect(
        page.getByRole('heading', { level: 1, name: creator.workspaces[1] }),
    ).toBeVisible();
    await expect(page.getByText(siteName)).toHaveCount(0);
    await expect(page.getByText('Сайт создан')).toHaveCount(0);
});
