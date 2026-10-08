import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function addMediaSet(page: Page, name: string, swatch: string) {
    await page.getByRole('button', { name: 'Добавить набор' }).click();
    const dialog = page.getByRole('dialog', { name: 'Новый набор' });
    await dialog.getByLabel('Название').fill(name);
    await dialog.getByLabel('Цвет образца').fill(swatch);
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();

    const slot = page
        .getByRole('region', { name })
        .getByRole('figure')
        .filter({ hasText: 'Спереди 3/4' });
    await slot.getByLabel('Файл для ракурса «Спереди 3/4»').setInputFiles({
        name: `${name}.png`,
        mimeType: 'image/png',
        buffer: png,
    });
    await slot.getByRole('button', { name: 'Загрузить' }).click();
    await expect(
        slot.getByRole('img', { name: `${name}: Спереди 3/4` }),
    ).toBeVisible();
}

test('platform admin prepares the catalog, dealer adds a priced vehicle and shows it on the site', async ({
    page,
    context,
}) => {
    test.setTimeout(120_000);

    // Platform super admin: Series media sets and an Equipment characteristic.
    await login(page, users.catalogAdmin.email, users.catalogAdmin.password);
    await page.goto('/platform/catalog');
    await expect(
        page.getByRole('heading', { name: 'Каталог автомобилей', level: 1 }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Kia', exact: true }).click();
    await page.getByRole('link', { name: 'Rio', exact: true }).click();
    await page
        .getByRole('link', { name: 'IV Рестайлинг', exact: true })
        .click();
    await page.getByRole('link', { name: 'Медиа серии Седан' }).click();
    await expect(
        page.getByRole('heading', { name: 'Медиа серии Седан' }),
    ).toBeVisible();

    await addMediaSet(page, 'Белый', '#f4f4f4');
    await addMediaSet(page, 'Красный', '#c62828');

    await page.getByRole('link', { name: 'Вернуться к каталогу' }).click();
    await page
        .getByRole('link', { name: '1.6 AT 123 л.с.', exact: true })
        .click();
    await page.getByRole('link', { name: 'Comfort', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Комплектация Comfort' }),
    ).toBeVisible();
    await page.getByLabel('Клиренс, мм').fill('165');
    await page
        .getByRole('button', { name: 'Сохранить характеристики' })
        .click();
    await expect(page.getByText('Сохранено')).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Клиренс, мм')).toHaveValue('165');

    // Dealer: new Site, vehicle by Series, offer with a price and a benefit.
    await context.clearCookies();
    const dealer = users.dealer;
    const siteName = `Автосалон E2E ${Date.now()}`;
    await login(page, dealer.email, dealer.password);

    await page.getByRole('link', { name: 'Создать сайт' }).click();
    await page
        .getByRole('group', { name: '2. Старт' })
        .getByRole('radio', { name: dealer.start })
        .check();
    await page.getByLabel('Название сайта').fill(siteName);
    await page.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(page).toHaveURL(/\/dashboard\?site=/);

    await page
        .getByRole('link', { name: `Автомобили сайта «${siteName}»` })
        .click();
    await page.getByRole('link', { name: 'Добавить автомобиль' }).click();
    await page.getByRole('link', { name: 'Kia', exact: true }).click();
    await page.getByRole('link', { name: 'Rio', exact: true }).click();
    await page
        .getByRole('link', { name: 'IV Рестайлинг', exact: true })
        .click();
    await page.getByRole('button', { name: 'Добавить серию Седан' }).click();
    await expect(
        page.getByRole('heading', { name: 'Kia Rio', level: 1 }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Добавить предложение' }).click();
    const offerDialog = page.getByRole('dialog', {
        name: 'Новое предложение',
    });
    const modification = offerDialog.getByLabel('Модификация');
    const modificationValue = await modification
        .locator('option', { hasText: '1.6 AT 123 л.с.' })
        .getAttribute('value');
    await modification.selectOption(modificationValue ?? '');
    await offerDialog
        .getByLabel('Комплектация')
        .selectOption({ label: 'Comfort' });
    await offerDialog.getByLabel('Цена, ₽', { exact: true }).fill('1 549 900');
    await offerDialog.getByRole('button', { name: 'Добавить выгоду' }).click();
    await offerDialog.getByLabel('Сумма, ₽').fill('100 000');
    await offerDialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(offerDialog).toBeHidden();
    await expect(
        page.getByRole('region', { name: 'Предложения' }),
    ).toContainText(/1\s549\s900\s₽/);

    // Designer: Vehicle Grid shows every Site vehicle; an Offers block is bound explicitly.
    await page.goto('/dashboard');
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();
    const left = page.getByRole('complementary', { name: 'Левая панель' });
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    const canvas = page.getByRole('main', { name: 'Холст' });

    await left
        .getByRole('button', { name: 'Добавить блок «Каталог автомобилей»' })
        .click();
    await expect(canvas).toContainText('Kia Rio');
    await expect(canvas).toContainText(/от 1\s549\s900\s₽/);
    await expect(canvas).toContainText(/Выгода до 100\s000\s₽/);

    await left
        .getByRole('button', { name: 'Добавить блок «Цены и предложения»' })
        .click();
    await properties
        .getByLabel('Автомобиль')
        .selectOption({ label: 'Kia Rio IV Рестайлинг Седан' });
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
    await expect(canvas).toContainText('Comfort');

    // Preview: price, image and color switching; the offer expands to catalog data.
    const previewOpened = context.waitForEvent('page');
    await page.getByRole('link', { name: 'Предпросмотр' }).click();
    const preview = await previewOpened;
    const main = preview.getByRole('main', { name: 'Предпросмотр страницы' });
    const card = main.getByRole('article').filter({ hasText: 'Kia Rio' });

    await expect(card).toContainText(/от 1\s549\s900\s₽/);
    const image = card.getByRole('img');
    await expect(image).toHaveAccessibleName(/Белый/);
    await expect
        .poll(() =>
            image.evaluate((element: HTMLImageElement) => element.naturalWidth),
        )
        .toBe(1);
    await card.getByRole('button', { name: 'Красный' }).click();
    await expect(image).toHaveAccessibleName(/Красный/);
    await expect(card.getByRole('button', { name: 'Красный' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );

    await main.getByRole('button', { name: 'Подробнее' }).click();
    await expect(
        main.getByRole('button', { name: 'Скрыть подробности' }),
    ).toHaveAttribute('aria-expanded', 'true');
    await expect(main).toContainText('Клиренс');
    await expect(main).toContainText('165 мм');
    await expect(main).toContainText('Подогрев передних сидений');
});
