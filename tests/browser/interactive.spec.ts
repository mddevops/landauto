import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.interactive;

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(owner.email);
    await page.getByLabel('Пароль', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function createSite(page: Page, siteName: string) {
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
}

async function openSection(page: Page, name: 'Формы' | 'Попапы') {
    await page.getByRole('button', { name: 'Разделы сайта' }).click();
    await page.getByRole('menuitem', { name }).click();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
}

test('owner builds a form, attaches it to a popup and opens it from a button', async ({
    page,
    context,
}) => {
    test.setTimeout(120_000);
    await login(page);
    await createSite(page, `Интерактив E2E ${Date.now()}`);

    // Form with name, phone and the customer's own consent text.
    await openSection(page, 'Формы');
    await page.getByRole('button', { name: 'Создать форму' }).click();
    await page
        .getByRole('dialog', { name: 'Новая форма' })
        .getByLabel('Название')
        .fill('Заявка на звонок');
    await page.getByRole('button', { name: 'Создать', exact: true }).click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Заявка на звонок' }),
    ).toBeVisible();

    const addField = async (
        type: string,
        key: string | null,
        label: string,
        required: boolean,
    ) => {
        await page.getByRole('button', { name: 'Добавить поле' }).click();
        const dialog = page.getByRole('dialog', { name: 'Новое поле' });
        await dialog.getByLabel('Тип').selectOption(type);

        if (key !== null) {
            await dialog.getByLabel('Ключ').fill(key);
        }

        await dialog
            .getByLabel(type === 'consent' ? 'Текст согласия' : 'Подпись')
            .fill(label);
        const requiredBox = dialog.getByRole('checkbox');

        if ((await requiredBox.isChecked()) !== required) {
            await requiredBox.click();
        }

        await dialog.getByRole('button', { name: 'Сохранить' }).click();
        await expect(dialog).toBeHidden();
    };

    await addField('text', 'name', 'Ваше имя', false);
    await addField('phone', null, 'Телефон', true);
    await addField('consent', null, 'Тестовое согласие клиента', true);

    const fields = page.getByRole('list').filter({ hasText: 'ключ' });
    await expect(fields.getByRole('listitem')).toHaveCount(3);
    await page
        .getByRole('button', { name: 'Переместить поле «Телефон» выше' })
        .click();
    await expect(fields.getByRole('listitem').first()).toContainText('Телефон');
    await page
        .getByRole('button', { name: 'Переместить поле «Телефон» ниже' })
        .click();
    await expect(fields.getByRole('listitem').first()).toContainText(
        'Ваше имя',
    );

    // Popup that shows the form.
    await page.getByRole('link', { name: /Все формы/ }).click();
    await page.getByRole('link', { name: 'Открыть дизайнер' }).click();
    await openSection(page, 'Попапы');
    await page.getByRole('button', { name: 'Создать попап' }).click();
    const popupDialog = page.getByRole('dialog', { name: 'Новый попап' });
    await popupDialog.getByLabel('Название').fill('Обратный звонок');
    await popupDialog
        .getByLabel('Заголовок окна')
        .fill('Перезвоним за 5 минут');
    await popupDialog
        .getByLabel('Форма заявки')
        .selectOption({ label: 'Заявка на звонок' });
    await popupDialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(popupDialog).toBeHidden();
    await expect(page.getByText('Форма: Заявка на звонок')).toBeVisible();

    // Hero button opens the popup.
    await page.getByRole('link', { name: 'Открыть дизайнер' }).click();
    const left = page.getByRole('complementary', { name: 'Левая панель' });
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await left
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    const primaryButton = properties.getByRole('group', {
        name: 'Основная кнопка',
    });
    await primaryButton.getByLabel('Действие: тип').selectOption('open_popup');
    await primaryButton
        .getByLabel('Попап', { exact: true })
        .selectOption({ label: 'Обратный звонок' });
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();

    const previewOpened = context.waitForEvent('page');
    await page.getByRole('link', { name: 'Предпросмотр' }).click();
    const preview = await previewOpened;
    const trigger = preview
        .getByRole('main', { name: 'Предпросмотр страницы' })
        .getByRole('button', { name: 'Подобрать автомобиль' });

    await trigger.click();
    const popup = preview.getByRole('dialog', {
        name: 'Перезвоним за 5 минут',
    });
    await expect(popup).toBeVisible();
    await expect(popup).toHaveAttribute('aria-modal', 'true');
    await expect(popup.getByLabel('Ваше имя')).toBeVisible();
    await expect(popup.getByLabel(/Телефон/)).toBeVisible();
    await expect(
        popup.getByRole('checkbox', { name: /Тестовое согласие клиента/ }),
    ).toBeVisible();

    await preview.keyboard.press('Escape');
    await expect(popup).toBeHidden();
    await expect(trigger).toBeFocused();
});
