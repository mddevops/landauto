import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow, isMobileViewport } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const dealer = users.dealer;

test('owner adds the catalog section, renames its prefix and keeps the old address @responsive', async ({
    page,
}) => {
    test.setTimeout(60_000);
    const siteName = `Разделы E2E ${Date.now().toString(36)}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(dealer.email);
    await page.getByLabel('Пароль', { exact: true }).fill(dealer.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/sites/create');
    await page
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: /Многостраничный сайт/ })
        .check();
    await page.getByLabel('Название сайта').fill(siteName);
    await page
        .getByRole('button', { name: 'Создать и открыть дизайнер' })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    const siteUrl = page.url().replace(/\/designer$/, '');

    await page.goto(siteUrl);

    if (isMobileViewport(page)) {
        await page
            .locator('header')
            .getByRole('button', { name: 'Показать или скрыть боковую панель' })
            .click();
    }

    await page
        .getByRole('navigation', { name: `Разделы сайта «${siteName}»` })
        .getByRole('link', { name: 'Разделы', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Разделы' }),
    ).toBeVisible();
    await expect(page.getByText('Разделов пока нет')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page
        .getByRole('button', { name: 'Добавить раздел «Каталог автомобилей»' })
        .click();
    const section = page.getByRole('region', {
        name: 'Раздел «Каталог автомобилей»',
    });
    await expect(section).toBeVisible();
    const prefix = section.getByLabel('Адрес раздела');
    await expect(prefix).toHaveValue('catalog');

    // Reserved addresses are refused by the backend.
    await prefix.fill('sitemap');
    await section
        .getByRole('button', { name: 'Сохранить раздел «Каталог автомобилей»' })
        .click();
    await expect(
        section.getByText('Этот адрес зарезервирован, выберите другой.'),
    ).toBeVisible();

    await prefix.fill('autos');
    await expect(
        section.getByText('старый адрес будет перенаправлять на новый'),
    ).toBeVisible();
    await section
        .getByRole('button', { name: 'Сохранить раздел «Каталог автомобилей»' })
        .click();
    await expect(
        page
            .getByText(
                'Раздел сохранён. Изменения появятся на сайте после публикации.',
            )
            .first(),
    ).toBeVisible();
    await expect(prefix).toHaveValue('autos');
    await expect(section.getByText('/catalog', { exact: true })).toBeVisible();
    await expect(
        page
            .getByRole('listitem')
            .filter({ hasText: 'Автомобили с пробегом' })
            .getByText('Скоро'),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);

    // The section comes with template pages edited in the Designer.
    await expect(
        section
            .getByRole('listitem')
            .filter({ hasText: 'Карточка автомобиля' }),
    ).toContainText('/autos/kia/rio-iv-restyling');
    await section
        .getByRole('link', {
            name: 'Открыть в дизайнере: Карточка автомобиля',
        })
        .click();
    await expect(page).toHaveURL(/\/designer\?page=/);
    await expect(
        page.getByText(
            'Добавьте автомобили на сайт — здесь появится автомобиль этой страницы.',
        ),
    ).toHaveCount(4);
    await page.getByRole('tab', { name: 'Страницы' }).click();
    const catalogPages = page.getByRole('region', {
        name: 'Каталог автомобилей',
    });
    await expect(
        catalogPages.getByRole('link', { name: /Страница марки/ }),
    ).toContainText('/autos/kia');
    await expect(
        catalogPages.getByRole('link', { name: /Страница модели/ }),
    ).toHaveCount(0);
    await expect(
        catalogPages.getByRole('button', { name: /Удалить страницу/ }),
    ).toHaveCount(0);

    // After publishing, the section is served at its address; the old prefix redirects there.
    await page.goto(`${siteUrl}/publishing`);
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText('Сайт опубликован. Версия 1.').first(),
    ).toBeVisible();
    const publicUrl = (
        await page.getByTestId('public-url').getAttribute('href')
    )?.replace(/\/$/, '');
    expect(publicUrl).toBeTruthy();

    const visitor = await page.context().newPage();
    await visitor.goto(`${publicUrl}/autos`);
    await expect(
        visitor.getByRole('heading', { level: 2, name: 'Каталог автомобилей' }),
    ).toBeVisible();
    await expect(
        visitor.getByText('Добавьте автомобили в разделе «Автомобили» сайта.'),
    ).toBeVisible();
    await visitor.goto(`${publicUrl}/catalog`);
    await expect(visitor).toHaveURL(`${publicUrl}/autos`);
});
