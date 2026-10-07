import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const account = users.formats;

async function createSite(
    page: Page,
    format: RegExp,
    name: string,
    start: { blank: true } | { template: string },
) {
    await page.goto('/sites/create');
    await page
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: format })
        .check();

    const startGroup = page.getByRole('group', { name: '2. Старт' });
    const blank = startGroup.getByRole('radio', { name: 'Пустой старт' });

    if ('blank' in start) {
        await expect(blank).toBeChecked();
    } else {
        await expect(blank).toHaveCount(0);
        await expect(
            startGroup.getByRole('radio', { name: start.template }),
        ).toBeChecked();
    }

    await expectNoHorizontalOverflow(page);
    await page.getByLabel('Название сайта').fill(name);
    await page.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(page).toHaveURL(/\/dashboard\?site=/);
    await expect(page.getByRole('heading', { level: 2, name })).toBeVisible();
}

test('formats: landing, quiz and chat on a plan without multi-page sites @responsive', async ({
    page,
}) => {
    test.setTimeout(60_000);
    const suffix = Date.now().toString(36);

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/sites/create');
    const formats = page.getByRole('group', { name: '1. Формат' });
    await expect(
        formats.getByRole('radio', { name: /Многостраничный сайт/ }),
    ).toBeDisabled();
    await expect(formats).toContainText('Недоступно на текущем тарифе.');
    await expectNoHorizontalOverflow(page);

    await createSite(page, /^Лендинг/, `Лендинг ${suffix}`, { blank: true });
    await createSite(page, /^Квиз/, `Квиз ${suffix}`, {
        template: account.quizTemplate,
    });
    await createSite(page, /^Чат-подбор/, `Чат ${suffix}`, {
        template: account.chatTemplate,
    });

    // The quiz Site keeps the Template structure: no new Pages or Blocks.
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «Квиз ${suffix}»` })
        .click();
    const left = page.getByRole('complementary', { name: 'Левая панель' });
    await left.getByRole('tab', { name: 'Страницы' }).click();
    await expect(left.getByRole('link', { name: 'Главная' })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Добавить страницу' }),
    ).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
});
