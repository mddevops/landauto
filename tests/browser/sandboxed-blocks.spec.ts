import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.sandbox;
// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(owner.email);
    await page.getByLabel('Пароль', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function expectSaved(page: Page) {
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

function blockFrame(page: Page) {
    return page.locator(`iframe[title="${owner.block}"]`);
}

test('a Studio Block is placed, edited through its schema and published only inside the sandbox', async ({
    page,
}) => {
    test.setTimeout(120_000);

    await login(page);
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${owner.site}»` })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    await page
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: `Добавить блок «${owner.block}»` })
        .click();

    // Designer canvas: the published code renders in an opaque-origin frame from Instance state.
    await expect(blockFrame(page)).toHaveAttribute('sandbox', 'allow-scripts');
    const canvas = page.frameLocator(`iframe[title="${owner.block}"]`);
    await expect(
        canvas.getByRole('heading', { name: 'Спецпредложение' }),
    ).toBeVisible();
    await expect(canvas.locator('[data-ready="yes"]')).toHaveCount(1);
    await expect(page.locator('section.promo')).toHaveCount(0);

    // The Properties Editor is built from the published schema.
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await properties
        .getByLabel('Заголовок', { exact: true })
        .fill('Цена недели');
    await expect(
        canvas.getByRole('heading', { name: 'Цена недели' }),
    ).toBeVisible();
    await properties.getByRole('button', { name: 'Выбрать: Фото' }).click();
    const library = page.getByRole('dialog', {
        name: 'Библиотека изображений',
    });
    await library.getByLabel('Загрузить изображение').setInputFiles({
        name: 'sandbox-photo.png',
        mimeType: 'image/png',
        buffer: png,
    });
    await expect(library).toBeHidden();
    // Draft assets need a session the sandbox never has: the canvas shows a placeholder.
    await expect(canvas.locator('img')).toHaveAttribute(
        'src',
        /^data:image\/svg\+xml,/,
    );
    const cta = properties.getByRole('group', { name: 'Кнопка' });
    await cta.getByLabel('Кнопка: тип').selectOption('open_popup');
    await cta
        .getByLabel('Попап', { exact: true })
        .selectOption({ label: 'Обратный звонок' });
    await expectSaved(page);

    // Draft preview: the Block's own action opens the Site Popup through the Action System.
    const [preview] = await Promise.all([
        page.context().waitForEvent('page'),
        page.getByRole('link', { name: 'Предпросмотр' }).click(),
    ]);
    const previewBlock = preview.frameLocator(`iframe[title="${owner.block}"]`);
    await previewBlock.getByRole('button', { name: 'Узнать цену' }).click();
    await expect(
        preview.getByRole('dialog', { name: 'Перезвоним за 5 минут' }),
    ).toBeVisible();
    await preview.close();

    await page.getByRole('link', { name: 'Публикация' }).click();
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText('Сайт опубликован. Версия 1.').first(),
    ).toBeVisible();

    // Published Site: stored HTML holds only the empty frame; hydration renders the code inside it.
    const visitor = await page.context().newPage();
    await visitor.goto(owner.publicUrl);
    const published = visitor.frameLocator(`iframe[title="${owner.block}"]`);
    await expect(
        published.getByRole('heading', { name: 'Цена недели' }),
    ).toBeVisible();
    await expect(visitor.locator('section.promo')).toHaveCount(0);
    await expect(blockFrame(visitor)).toHaveAttribute(
        'sandbox',
        'allow-scripts',
    );
    const image = published.locator('img');
    await expect(image).toHaveAttribute('src', /\/_landflow\/assets\//);
    await expect
        .poll(() =>
            image.evaluate(
                (element) => (element as HTMLImageElement).naturalWidth,
            ),
        )
        .toBe(1);

    await published.getByRole('button', { name: 'Узнать цену' }).click();
    await expect(
        visitor.getByRole('dialog', { name: 'Перезвоним за 5 минут' }),
    ).toBeVisible();
});
