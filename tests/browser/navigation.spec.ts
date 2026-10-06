import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow, isMobileViewport } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const navigator = users.navigator;

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function signedIn(
    browser: Browser,
    email: string,
    password: string,
): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await login(page, email, password);

    return page;
}

async function openSidebar(page: Page) {
    if (isMobileViewport(page)) {
        await page
            .locator('header')
            .getByRole('button', { name: 'Показать или скрыть боковую панель' })
            .click();
    }
}

test('owner creates and renames a Workspace from the switcher', async ({
    page,
}) => {
    await login(page, navigator.email, navigator.password);
    const renamed = `Пространство E2E ${Date.now()}`;

    await page
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${navigator.workspace}`,
        })
        .click();
    await page
        .getByRole('menu')
        .getByRole('menuitem', { name: 'Создать пространство' })
        .click();

    await expect(page).toHaveURL('/workspaces/create');
    const name = page.getByLabel('Название пространства');
    await expect(name).toHaveAttribute(
        'placeholder',
        'Например, «АвтоГрупп Ростов»',
    );
    await name.fill('АвтоГрупп Ростов');
    await page.getByRole('button', { name: 'Создать пространство' }).click();

    await expect(page).toHaveURL('/dashboard');
    await expect(page.getByTestId('dashboard-workspace')).toHaveText(
        'Пространство: АвтоГрупп Ростов',
    );
    await expect(
        page.getByRole('heading', { name: 'Здесь пока нет сайтов' }),
    ).toBeVisible();

    await page
        .getByRole('button', {
            name: 'Сменить рабочее пространство. Текущее: АвтоГрупп Ростов',
        })
        .click();
    const menu = page.getByRole('menu');
    await expect(
        menu.getByRole('menuitem', { name: navigator.workspace }),
    ).toBeVisible();
    await menu
        .getByRole('menuitem', { name: 'Управление пространством' })
        .click();

    await expect(page).toHaveURL('/workspace/settings');
    await expect(page.getByText('Рабочее пространство').first()).toBeVisible();
    await page.getByLabel('Название', { exact: true }).fill(renamed);
    await page.getByRole('button', { name: 'Сохранить' }).click();
    await expect(
        page.getByText('Название пространства сохранено.'),
    ).toBeVisible();
    await expect(
        page.getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${renamed}`,
        }),
    ).toBeVisible();

    await page
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${renamed}`,
        })
        .click();
    await page
        .getByRole('menu')
        .getByRole('menuitem', { name: navigator.workspace })
        .click();
    await expect(page.getByTestId('dashboard-workspace')).toHaveText(
        `Пространство: ${navigator.workspace}`,
    );
    await expect(
        page.getByRole('heading', { name: navigator.site }),
    ).toBeVisible();
});

test(
    'site context has its own navigation and leads back to all sites',
    { tag: '@responsive' },
    async ({ page }) => {
        await login(page, navigator.email, navigator.password);

        await page
            .getByRole('link', { name: `Открыть сайт «${navigator.site}»` })
            .click();
        await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}$/i);
        await expect(
            page.getByRole('heading', { level: 1, name: navigator.site }),
        ).toBeVisible();
        await expect(page.getByTestId('site-overview')).toContainText(
            'Сайт ещё не опубликован',
        );
        await expectNoHorizontalOverflow(page);

        await openSidebar(page);
        const siteNav = page.getByRole('navigation', {
            name: `Разделы сайта «${navigator.site}»`,
        });
        for (const item of [
            'Общее',
            'Дизайнер',
            'Предпросмотр',
            'Автомобили',
            'Формы',
            'Попапы',
            'SEO',
            'Заявки',
            'Доставка заявок',
            'Интеграции',
            'Защита форм',
            'Публикация',
        ]) {
            await expect(
                siteNav.getByRole('link', { name: item, exact: true }),
            ).toBeVisible();
        }
        await expect(
            page.getByRole('navigation', { name: 'Навигация по пространству' }),
        ).toHaveCount(0);
        await expectNoHorizontalOverflow(page);

        await siteNav.getByRole('link', { name: 'Формы', exact: true }).click();
        await expect(page).toHaveURL(/\/forms$/);

        await openSidebar(page);
        await page
            .locator('[data-slot="sidebar"]')
            .getByRole('link', { name: 'Все сайты' })
            .click();
        await expect(page).toHaveURL('/dashboard');
        await expect(
            page.getByRole('heading', { level: 1, name: 'Все сайты' }),
        ).toBeVisible();
    },
);

test('designer sees only permitted site sections', async ({ browser }) => {
    const designer = await signedIn(
        browser,
        users.integrationsDesigner.email,
        users.integrationsDesigner.password,
    );

    await designer
        .getByRole('link', {
            name: `Открыть сайт «${users.integrations.site}»`,
        })
        .click();
    const siteNav = designer.getByRole('navigation', {
        name: `Разделы сайта «${users.integrations.site}»`,
    });

    await expect(siteNav.getByRole('link')).toHaveText([
        'Общее',
        'Дизайнер',
        'Предпросмотр',
        'Формы',
        'Попапы',
    ]);
    await expect(
        designer.getByRole('link', { name: 'Перейти к публикации' }),
    ).toHaveCount(0);
    await expect(designer.getByLabel('Название', { exact: true })).toHaveCount(
        0,
    );

    await designer.context().close();
});

test('admin site navigation includes leads and settings but not workspace settings', async ({
    browser,
}) => {
    const admin = await signedIn(
        browser,
        users.integrationsAdmin.email,
        users.integrationsAdmin.password,
    );

    await expect(
        admin
            .getByRole('navigation', { name: 'Навигация по пространству' })
            .getByRole('link'),
    ).toHaveText(['Все сайты', 'Интеграции', 'Команда']);

    await admin
        .getByRole('link', {
            name: `Открыть сайт «${users.integrations.site}»`,
        })
        .click();
    await expect(
        admin
            .getByRole('navigation', {
                name: `Разделы сайта «${users.integrations.site}»`,
            })
            .getByRole('link'),
    ).toHaveText([
        'Общее',
        'Дизайнер',
        'Предпросмотр',
        'Автомобили',
        'Формы',
        'Попапы',
        'SEO',
        'Заявки',
        'Доставка заявок',
        'Интеграции',
        'Защита форм',
        'Публикация',
        'Домены',
    ]);

    await admin.context().close();
});
