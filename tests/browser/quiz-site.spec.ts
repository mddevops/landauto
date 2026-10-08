import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const account = users.quiz;

test('a quiz Site from the official Template collects answers into a lead @responsive', async ({
    page,
}) => {
    test.setTimeout(90_000);
    const siteName = `Квиз ${Date.now().toString(36)}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/sites/create');
    await page
        .getByRole('group', { name: '1. Формат' })
        .getByRole('radio', { name: /^Квиз/ })
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
        'Какой автомобиль вы ищете?',
    );
    const siteUrl = page.url().replace(/\/designer.*$/, '');
    const previewHref = await page
        .getByRole('link', { name: 'Предпросмотр' })
        .getAttribute('href');
    expect(previewHref).not.toBeNull();

    // The visitor walks through the quiz in the Draft preview.
    await page.goto(previewHref!);
    const quiz = page.getByRole('main', { name: 'Предпросмотр страницы' });
    await expect(quiz.getByText('Вопрос 1 из 3')).toBeVisible();
    await quiz.getByRole('button', { name: 'Кроссовер' }).click();
    await quiz.getByRole('button', { name: 'Назад' }).click();
    await quiz.getByRole('button', { name: 'Внедорожник' }).click();
    await quiz.getByRole('button', { name: '2–4 млн ₽' }).click();
    await quiz.getByRole('button', { name: 'В этом месяце' }).click();
    await expect(quiz.getByText('Подборка готова')).toBeVisible();
    await expect(quiz).toContainText('Внедорожник');
    await expectNoHorizontalOverflow(page);

    await quiz.getByRole('button', { name: 'Получить подборку' }).click();
    const dialog = page.getByRole('dialog', { name: 'Получите подборку' });
    await dialog.getByLabel('Имя').fill('Анна');
    await dialog.getByLabel('Телефон').fill('+7 999 222-33-44');
    await dialog.getByRole('button', { name: 'Получить подборку' }).click();
    await expect(
        dialog.getByText(
            'Спасибо! Менеджер свяжется с вами и пришлёт подборку.',
        ),
    ).toBeVisible();

    // The lead keeps the answers resolved by the backend.
    await page.goto(`${siteUrl}/submissions?mode=preview`);
    const context = page
        .getByRole('region', { name: 'Контекст заявки' })
        .first();
    await expect(context).toContainText('Какой автомобиль вы ищете?');
    await expect(context).toContainText('Внедорожник');
    await expect(context).toContainText('2–4 млн ₽');
    await expect(context).toContainText('В этом месяце');
});
