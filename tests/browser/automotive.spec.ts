import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

const ANGLE = '3/4, смотрит влево';

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function addColor(page: Page, name: string, swatch: string) {
    await page.getByRole('button', { name: 'Добавить цвет' }).click();
    const dialog = page.getByRole('dialog', { name: 'Новый цвет' });
    await dialog.getByLabel('Название цвета').fill(name);
    await dialog.getByRole('textbox', { name: 'Образец цвета' }).fill(swatch);
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();

    const color = page.getByRole('article', { name: `Цвет ${name}` });
    await color
        .getByRole('figure')
        .filter({ hasText: ANGLE })
        .locator('input[type="file"]')
        .setInputFiles({
            name: `${name}.png`,
            mimeType: 'image/png',
            buffer: png,
        });
    await expect(color.getByRole('img', { name: ANGLE })).toBeVisible();
}

test('platform admin prepares series photos, dealer adds a priced vehicle with the wizard and shows it on the site', async ({
    page,
    context,
}) => {
    test.setTimeout(120_000);

    // Platform: browse the auto.ru catalog and fill the Series photo library.
    await login(page, users.catalogAdmin.email, users.catalogAdmin.password);
    await page.goto('/platform/catalog');
    await expect(
        page.getByRole('heading', { name: 'Каталог автомобилей', level: 1 }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Kia', exact: true }).click();
    await page.getByRole('link', { name: 'Rio', exact: true }).click();
    await page
        .getByRole('link', { name: 'IV Рестайлинг, Седан', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Kia Rio', level: 1 }),
    ).toBeVisible();

    await addColor(page, 'Белый', '#f4f4f4');
    await addColor(page, 'Красный', '#c62828');

    await page.getByRole('tab', { name: 'Модификации и комплектации' }).click();
    await expect(page.getByText('Comfort', { exact: true })).toBeVisible();

    // Dealer: new Site, vehicle through the wizard with photos and a priced trim.
    await context.clearCookies();
    const dealer = users.dealer;
    const siteName = `Автосалон E2E ${Date.now()}`;
    await login(page, dealer.email, dealer.password);

    await page.getByRole('link', { name: 'Создать сайт' }).click();
    await page
        .getByRole('group', { name: '2. Шаблон' })
        .getByRole('radio', { name: dealer.start })
        .check();
    await page.getByLabel('Название сайта').fill(siteName);
    await page
        .getByRole('button', { name: 'Создать и открыть дизайнер' })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    await page.goto('/dashboard');

    await page
        .getByRole('link', { name: `Автомобили сайта «${siteName}»` })
        .click();
    await page.getByRole('link', { name: 'Добавить автомобиль' }).click();

    await page.getByRole('button', { name: 'Kia', exact: true }).click();
    await page.getByRole('button', { name: 'Rio', exact: true }).click();
    await page
        .getByRole('button', { name: 'IV Рестайлинг, Седан', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Фото', level: 2 }),
    ).toBeVisible();
    await expect(page.getByRole('checkbox').first()).toBeChecked();
    await page.getByRole('button', { name: 'Далее' }).click();

    await page
        .getByRole('checkbox', { name: 'Выбрать комплектацию Comfort' })
        .check();
    await page
        .getByRole('textbox', { name: 'Цена со скидкой: Comfort' })
        .fill('1 549 900');
    await page.getByRole('button', { name: 'Далее' }).click();
    await expect(
        page.getByRole('heading', { name: 'Проверьте и добавьте' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Добавить', exact: true }).click();

    await expect(
        page.getByRole('heading', { name: 'Kia Rio', level: 1 }),
    ).toBeVisible();
    const offers = page.getByRole('region', { name: 'Комплектации и цены' });
    await expect(offers).toContainText(/1\s549\s900\s₽/);

    await offers.getByRole('button', { name: 'Изменить' }).click();
    const offerDialog = page.getByRole('dialog', {
        name: 'Изменить предложение',
    });
    await offerDialog.getByRole('button', { name: 'Добавить выгоду' }).click();
    await offerDialog.getByLabel('Сумма, ₽').fill('100 000');
    await offerDialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(offerDialog).toBeHidden();

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

    // Preview: price, the copied photo and colour switching; the trim expands to catalog data.
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
    await expect(main).toContainText('160 мм');
    await expect(main).toContainText('Подогрев передних сидений');
});
