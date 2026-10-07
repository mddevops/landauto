import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const account = users.chat;

test('a chat Site asks scripted questions and ends with a lead, without operator replies @responsive', async ({
    page,
}) => {
    test.setTimeout(90_000);
    const siteName = `Чат ${Date.now().toString(36)}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/sites/create');
    await page
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: /^Чат-подбор/ })
        .check();
    await expect(
        page
            .getByRole('group', { name: '2. Старт' })
            .getByRole('radio', { name: account.template }),
    ).toBeChecked();
    await page.getByLabel('Название сайта').fill(siteName);
    await page.getByRole('button', { name: 'Создать сайт' }).click();
    await expect(page).toHaveURL(/\/dashboard\?site=/);

    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();
    await expect(page.getByRole('main', { name: 'Холст' })).toContainText(
        'Подбор автомобиля в чате',
    );
    const siteUrl = page.url().replace(/\/designer.*$/, '');
    const previewHref = await page
        .getByRole('link', { name: 'Предпросмотр' })
        .getAttribute('href');
    expect(previewHref).not.toBeNull();

    await page.goto(previewHref!);
    const preview = page.getByRole('main', { name: 'Предпросмотр страницы' });
    const log = preview.getByRole('log', { name: 'Переписка' });
    const choices = preview.getByRole('group', { name: 'Варианты ответа' });
    await expect(preview).toContainText(
        'Автоматические вопросы сайта. Менеджер ответит после заявки.',
    );
    await expect(log).toContainText('Для чего нужен автомобиль?');
    await choices.getByRole('button', { name: 'Для семьи' }).click();
    await expect(log).toContainText('Вы: Для семьи');
    await choices.getByRole('button', { name: 'Трейд-ин' }).click();
    // The Site has no vehicles yet, so the chat goes straight to the lead.
    await expect(log).toContainText('Оставьте контакты');
    await expect(log.getByRole('listitem')).toHaveCount(6);
    await expectNoHorizontalOverflow(page);

    await preview.getByRole('button', { name: 'Оставить заявку' }).click();
    const dialog = page.getByRole('dialog', { name: 'Получите подборку' });
    await dialog.getByLabel('Имя').fill('Пётр');
    await dialog.getByLabel('Телефон').fill('+7 999 333-44-55');
    await dialog.getByRole('button', { name: 'Получить подборку' }).click();
    await expect(
        dialog.getByText(
            'Спасибо! Менеджер свяжется с вами и пришлёт подборку.',
        ),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    // No operator message appears after the lead.
    await expect(log.getByRole('listitem')).toHaveCount(6);

    await page.goto(`${siteUrl}/submissions?mode=preview`);
    const context = page
        .getByRole('region', { name: 'Контекст заявки' })
        .first();
    await expect(context).toContainText('Для чего нужен автомобиль?');
    await expect(context).toContainText('Для семьи');
    await expect(context).toContainText('Трейд-ин');
});
