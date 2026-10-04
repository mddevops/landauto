import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

test('owner builds a page in the designer, autosaves, reloads and previews it', async ({
    page,
    context,
}) => {
    test.setTimeout(90_000);
    const owner = users.designer;
    const siteName = `Дизайнер E2E ${Date.now()}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(owner.email);
    await page.getByLabel('Пароль', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.getByRole('link', { name: 'Создать сайт' }).click();
    await page
        .getByRole('group', { name: '1. Шаблон' })
        .getByRole('radio', { name: owner.template })
        .check();
    await page.getByLabel('Название сайта').fill(siteName);
    await page.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(page).toHaveURL(/\/dashboard\?site=/);
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();
    await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i);

    const left = page.getByRole('complementary', { name: 'Левая панель' });
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    const canvas = page.getByRole('main', { name: 'Холст' });
    const saved = page.getByText('Сохранено', { exact: true });
    const canvasOrder = () =>
        canvas
            .getByRole('button', { name: /^Выбрать блок/ })
            .evaluateAll((buttons) =>
                buttons.map((button) => button.getAttribute('aria-label')),
            );

    // Hero: edit the title, configure a safe action and upload a background image.
    await left
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    await expect(properties).toContainText('Первый экран');
    await properties
        .getByLabel('Заголовок', { exact: true })
        .fill('Тест-драйв новых моделей');
    await expect(canvas).toContainText('Тест-драйв новых моделей');

    const primaryButton = properties.getByRole('group', {
        name: 'Основная кнопка',
    });
    await primaryButton.getByLabel('Действие: тип').selectOption('phone');
    await primaryButton.getByLabel('Телефон').fill('+7 495 123-45-67');
    await expect(saved).toBeVisible();

    await properties
        .getByRole('button', { name: 'Выбрать: Фоновое изображение' })
        .click();
    const library = page.getByRole('dialog', {
        name: 'Библиотека изображений',
    });
    await library.getByLabel('Загрузить изображение').setInputFiles({
        name: 'showroom.png',
        mimeType: 'image/png',
        buffer: png,
    });
    await expect(library).toBeHidden();
    await expect(properties.getByText('showroom.png')).toBeVisible();
    await expect(canvas.locator('img')).toHaveCount(1);
    await expect(saved).toBeVisible();

    // Site style: outline buttons.
    await properties.getByRole('tab', { name: 'Стиль сайта' }).click();
    await properties.getByLabel('Стиль кнопок').selectOption('outline');
    await properties.getByRole('button', { name: 'Сохранить стиль' }).click();
    await expect(
        properties.getByRole('button', { name: 'Сохранить стиль' }),
    ).toBeDisabled();
    await properties.getByRole('tab', { name: 'Блок' }).click();

    // Benefits with two repeater items.
    await left
        .getByRole('button', { name: 'Добавить блок «Преимущества»' })
        .click();
    const benefitItems = properties.getByRole('group', {
        name: /^Преимущества \(/,
    });
    await expect(benefitItems).toBeVisible();
    await benefitItems
        .getByRole('button', { name: 'Добавить элемент' })
        .click();
    await benefitItems
        .getByRole('listitem')
        .nth(0)
        .getByLabel('Заголовок', { exact: true })
        .fill('Официальная гарантия');
    await benefitItems
        .getByRole('button', { name: 'Добавить элемент' })
        .click();
    await benefitItems
        .getByRole('listitem')
        .nth(1)
        .getByLabel('Заголовок', { exact: true })
        .fill('Трейд-ин');
    await expect(canvas).toContainText('Трейд-ин');
    await expect(saved).toBeVisible();

    // Reorder, then reload: order and draft state come back from the server.
    await left
        .getByRole('button', { name: 'Переместить «Преимущества» выше' })
        .click();
    await expect
        .poll(canvasOrder)
        .toEqual([
            'Выбрать блок «Преимущества»',
            'Выбрать блок «Первый экран»',
        ]);

    await page.reload();
    await expect
        .poll(canvasOrder)
        .toEqual([
            'Выбрать блок «Преимущества»',
            'Выбрать блок «Первый экран»',
        ]);
    await expect(canvas).toContainText('Официальная гарантия');
    await canvas
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    await expect(
        properties.getByLabel('Заголовок', { exact: true }),
    ).toHaveValue('Тест-драйв новых моделей');
    await expect(primaryButton.getByLabel('Телефон')).toHaveValue(
        '+7 495 123-45-67',
    );

    // Preview renders the saved draft without designer chrome.
    const previewOpened = context.waitForEvent('page');
    await page.getByRole('link', { name: 'Предпросмотр' }).click();
    const preview = await previewOpened;
    const previewMain = preview.getByRole('main', {
        name: 'Предпросмотр страницы',
    });

    await expect(preview.getByText('Предпросмотр черновика')).toBeVisible();
    await expect(
        previewMain.getByRole('heading', { name: 'Тест-драйв новых моделей' }),
    ).toBeVisible();
    await expect(previewMain).toContainText('Трейд-ин');
    await expect(
        previewMain.getByRole('link', { name: 'Подобрать автомобиль' }),
    ).toHaveAttribute('href', 'tel:+74951234567');
    await expect
        .poll(() =>
            previewMain
                .locator('img')
                .first()
                .evaluate((image: HTMLImageElement) => image.naturalWidth),
        )
        .toBe(1);
    await expect(
        preview.getByRole('button', { name: /Выбрать блок/ }),
    ).toHaveCount(0);
});
