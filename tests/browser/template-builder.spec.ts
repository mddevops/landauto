import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const author = users.templateDeveloper;

test('a Developer builds a Template in the Designer, previews devices and publishes a version', async ({
    page,
}) => {
    test.setTimeout(120_000);

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(author.email);
    await page.getByLabel('Пароль', { exact: true }).fill(author.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/developer');
    await page.getByRole('link', { name: /^Шаблоны/ }).click();
    await expect(page).toHaveURL('/developer/templates');
    await expect(page.getByText('Шаблонов пока нет.')).toBeVisible();
    await page.getByRole('link', { name: 'Создать шаблон' }).click();

    await page.getByLabel('Название').fill('Лендинг дилера E2E');
    await page.getByLabel('Slug').fill('e2e-dealer-landing');
    await page.getByLabel('Лендинг', { exact: true }).check();
    await page.getByRole('button', { name: 'Создать шаблон' }).click();
    await expect(page).toHaveURL(
        /\/studio\/templates\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i,
    );
    await expect(
        page.getByRole('heading', { name: 'Лендинг дилера E2E', level: 1 }),
    ).toBeVisible();

    const left = page.getByRole('complementary', { name: 'Левая панель' });
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    const canvas = page.getByRole('main', { name: 'Холст' });

    await left
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    await expect(properties).toContainText('Первый экран');
    await properties
        .getByLabel('Заголовок', { exact: true })
        .fill('Весенняя распродажа');
    await expect(canvas).toContainText('Весенняя распродажа');
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();

    // Preview of the saved Draft at the three device widths.
    const previewHref = await page
        .getByRole('link', { name: 'Предпросмотр' })
        .getAttribute('href');
    expect(previewHref).not.toBeNull();
    await page.goto(previewHref!);
    const viewport = page.getByTestId('template-preview-viewport');
    const frame = page.frameLocator('iframe[title^="Предпросмотр страницы"]');
    await expect(frame.getByText('Весенняя распродажа')).toBeVisible();

    await page.getByRole('button', { name: 'Телефон' }).click();
    await expect(page.getByRole('button', { name: 'Телефон' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    expect((await viewport.boundingBox())?.width).toBe(375);
    await page.getByRole('button', { name: 'Планшет' }).click();
    expect((await viewport.boundingBox())?.width).toBe(768);
    await expect(frame.getByText('Весенняя распродажа')).toBeVisible();

    // Publishing runs automated checks only and creates an immutable version.
    await page.getByRole('link', { name: 'Вернуться в дизайнер' }).click();
    await page.getByRole('link', { name: 'Публикация шаблона' }).click();
    await expect(page.getByText('Проверки пройдены.')).toBeVisible();
    await page.getByRole('button', { name: 'Опубликовать версию' }).click();
    await expect(page.getByText('Опубликована версия 1.0.0.')).toBeVisible();
    await expect(
        page.getByRole('list', { name: 'Версии шаблона' }),
    ).toContainText('Версия 1.0.0');

    await page.getByRole('button', { name: 'Опубликовать версию' }).click();
    await expect(page.getByText('Изменений с версии 1.0.0 нет.')).toBeVisible();
});
