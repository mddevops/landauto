import type { Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.interactive;
const rejectionMessage =
    'Не удалось отправить заявку. Попробуйте позже или свяжитесь с нами по телефону.';

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
    await openDesigner(page, siteName);
}

async function openDesigner(page: Page, siteName: string) {
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${siteName}»` })
        .click();
    await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i);
}

async function openSection(
    page: Page,
    name: 'Формы' | 'Попапы' | 'Защита форм' | 'Заявки',
) {
    await page.getByRole('button', { name: 'Разделы сайта' }).click();
    await page.getByRole('menuitem', { name }).click();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
}

/** Preview submissions are test entries, listed apart from real leads. */
async function showPreviewLeads(page: Page) {
    await page
        .getByRole('navigation', { name: 'Тип заявок' })
        .getByRole('link', { name: /Тестовые из предпросмотра/ })
        .click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Тестовые заявки' }),
    ).toBeVisible();
}

async function openPreviewLeads(page: Page) {
    await openSection(page, 'Заявки');
    await showPreviewLeads(page);
}

async function backToDesigner(page: Page) {
    await page.getByRole('link', { name: 'Открыть дизайнер' }).click();
    await expect(
        page.getByRole('complementary', { name: 'Левая панель' }),
    ).toBeVisible();
}

async function createForm(page: Page, name: string) {
    await openSection(page, 'Формы');
    await page.getByRole('button', { name: 'Создать форму' }).click();
    await page
        .getByRole('dialog', { name: 'Новая форма' })
        .getByLabel('Название')
        .fill(name);
    await page.getByRole('button', { name: 'Создать', exact: true }).click();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
}

async function addField(
    page: Page,
    type: string,
    key: string | null,
    label: string,
    required: boolean,
) {
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
}

async function createPopup(
    page: Page,
    name: string,
    title: string,
    formName: string,
) {
    await page.getByRole('link', { name: /Все формы/ }).click();
    await backToDesigner(page);
    await openSection(page, 'Попапы');
    await page.getByRole('button', { name: 'Создать попап' }).click();
    const dialog = page.getByRole('dialog', { name: 'Новый попап' });
    await dialog.getByLabel('Название').fill(name);
    await dialog.getByLabel('Заголовок окна').fill(title);
    await dialog.getByLabel('Форма заявки').selectOption({ label: formName });
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText(`Форма: ${formName}`)).toBeVisible();
}

async function bindPopup(group: Locator, popupName: string) {
    await group.getByLabel('Действие: тип').selectOption('open_popup');
    await group
        .getByLabel('Попап', { exact: true })
        .selectOption({ label: popupName });
}

async function expectSaved(page: Page) {
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

async function setCaptchaRequired(page: Page, required: boolean) {
    await openSection(page, 'Защита форм');
    const toggle = page.getByRole('checkbox', {
        name: 'Требовать капчу во всех формах сайта',
    });

    if ((await toggle.isChecked()) !== required) {
        await toggle.click();
        // Wait for the redirected page itself: toasts from earlier saves may still be visible.
        const reloaded = page.waitForResponse(
            (response) =>
                response.url().endsWith('/form-security') &&
                response.request().method() === 'GET',
        );
        await page.getByRole('button', { name: 'Сохранить' }).first().click();
        await reloaded;
        await expect(
            page.getByText('Настройки защиты сохранены.').first(),
        ).toBeVisible();
    }

    await expect(toggle).toBeChecked({ checked: required });
    await backToDesigner(page);
}

async function openPreview(page: Page): Promise<Page> {
    const opened = page.context().waitForEvent('page');
    await page.getByRole('link', { name: 'Предпросмотр' }).click();

    return opened;
}

function submissionResponse(preview: Page) {
    return preview.waitForResponse(
        (response) =>
            response.url().includes('/submissions') &&
            response.request().method() === 'POST',
    );
}

test('owner builds a form, attaches it to a popup and opens it from a button', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await login(page);
    await createSite(page, `Интерактив E2E ${Date.now()}`);

    // Form with name, phone and the customer's own consent text.
    await createForm(page, 'Заявка на звонок');
    await addField(page, 'text', 'name', 'Ваше имя', false);
    await addField(page, 'phone', null, 'Телефон', true);
    await addField(page, 'consent', null, 'Тестовое согласие клиента', true);

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

    // Popup that shows the form; the hero button opens it.
    await createPopup(
        page,
        'Обратный звонок',
        'Перезвоним за 5 минут',
        'Заявка на звонок',
    );
    await backToDesigner(page);
    await page
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    await bindPopup(
        page
            .getByRole('complementary', { name: 'Свойства' })
            .getByRole('group', { name: 'Основная кнопка' }),
        'Обратный звонок',
    );
    await expectSaved(page);

    const preview = await openPreview(page);
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

    // Client-side hints first, then a real submission persisted by the backend.
    await trigger.click();
    await popup.getByRole('button', { name: 'Отправить' }).click();
    await expect(popup.getByText('Заполните это поле.')).toBeVisible();
    await expect(popup.getByText('Подтвердите согласие.')).toBeVisible();

    await popup.getByLabel('Ваше имя').fill('Иван Покупатель');
    await popup.getByLabel(/Телефон/).fill('+7 (999) 111-22-33');
    await popup
        .getByRole('checkbox', { name: /Тестовое согласие клиента/ })
        .check();
    const submitted = submissionResponse(preview);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(201);
    await expect(popup.getByRole('status')).toHaveText(
        'Спасибо! Мы свяжемся с вами.',
    );

    // The preview lead is a marked test entry with its trusted context, not a real lead.
    await openSection(page, 'Заявки');
    await expect(page.getByText('Заявок пока нет')).toBeVisible();
    await showPreviewLeads(page);
    const lead = page.getByRole('article').first();
    await expect(lead).toContainText('Тестовая (предпросмотр)');
    await expect(lead).toContainText('Заявка на звонок');
    await expect(lead).toContainText('Иван Покупатель');
    await expect(lead).toContainText('+7 (999) 111-22-33');
    const leadContext = lead.getByRole('region', { name: 'Контекст заявки' });
    await expect(leadContext).toContainText('Обратный звонок');
    await expect(leadContext).toContainText('Главная');
});

test('vehicle offer reuses a popup with trusted context; anti-spam, captcha, carousel and lightbox work', async ({
    page,
    browserIssues,
}) => {
    test.setTimeout(180_000);
    // Run-unique names and phones keep Playwright retries independent on the seeded Site.
    const run = String(Date.now());
    const formName = `Заявка на автомобиль ${run}`;
    const popupName = `Предложение ${run}`;
    const phone = (prefix: string) =>
        `+7 (${prefix}) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-${run.slice(-2)}`;
    const leadPhone = phone('995');
    const captchaPhone = phone('996');

    await login(page);
    await openDesigner(page, 'Витрина Запад');
    await setCaptchaRequired(page, false);

    await createForm(page, formName);
    await addField(page, 'phone', null, 'Телефон', true);
    await addField(page, 'consent', null, 'Тестовое согласие клиента', true);
    await createPopup(page, popupName, 'Лучшая цена на автомобиль', formName);
    await backToDesigner(page);

    // Offers (button → popup), Vehicle Grid as a one-card carousel, Vehicle Gallery.
    const left = page.getByRole('complementary', { name: 'Левая панель' });
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await left
        .getByRole('button', { name: 'Добавить блок «Цены и предложения»' })
        .click();
    await properties
        .getByLabel('Автомобиль')
        .selectOption({ label: 'Lada Vesta I Седан' });
    await bindPopup(
        properties.getByRole('group', { name: 'Кнопка предложения' }),
        popupName,
    );
    await expectSaved(page);

    await left
        .getByRole('button', { name: 'Добавить блок «Каталог автомобилей»' })
        .click();
    const carouselSettings = properties.getByRole('group', {
        name: 'Карусель',
    });
    await carouselSettings
        .getByRole('checkbox', { name: 'Показывать каруселью' })
        .click();
    await carouselSettings
        .getByLabel('Карточек на экране')
        .selectOption({ label: '1' });
    await expectSaved(page);

    await left
        .getByRole('button', { name: 'Добавить блок «Галерея автомобиля»' })
        .click();
    await properties
        .getByLabel('Автомобиль')
        .selectOption({ label: 'Lada Vesta I Седан' });
    await expectSaved(page);

    const preview = await openPreview(page);
    const main = preview.getByRole('main', { name: 'Предпросмотр страницы' });

    // Carousel: buttons, dots and arrow keys.
    const carousel = main.getByRole('region', { name: 'Автомобили в наличии' });
    const dot = (position: number) =>
        carousel.getByRole('button', { name: `Перейти к слайду ${position}` });
    await expect(carousel.getByRole('group')).toHaveCount(2);
    await expect(dot(1)).toHaveAttribute('aria-current', 'true');
    await carousel.getByRole('button', { name: 'Следующий слайд' }).click();
    await expect(dot(2)).toHaveAttribute('aria-current', 'true');
    await expect(
        carousel.getByRole('button', { name: 'Следующий слайд' }),
    ).toBeDisabled();
    await carousel.focus();
    await preview.keyboard.press('ArrowLeft');
    await expect(dot(1)).toHaveAttribute('aria-current', 'true');

    // Lightbox: open, next, arrow key, Escape returns focus.
    const photo = main.getByRole('button', {
        name: /Открыть фото на весь экран/,
    });
    await photo.click();
    const lightbox = preview.getByRole('dialog', {
        name: /Фото: Lada Vesta/,
    });
    await expect(lightbox).toContainText('1 из 2');
    await expect(lightbox.getByRole('img')).toHaveAccessibleName(/Серебристый/);
    await lightbox.getByRole('button', { name: 'Следующее фото' }).click();
    await expect(lightbox).toContainText('2 из 2');
    await preview.keyboard.press('ArrowRight');
    await expect(lightbox).toContainText('1 из 2');
    await preview.keyboard.press('Escape');
    await expect(lightbox).toBeHidden();
    await expect(photo).toBeFocused();

    // Offer button opens the same popup; a spoofed price in the request is ignored.
    await preview.route('**/forms/*/submissions', async (route) => {
        const body = route.request().postDataJSON() as {
            context: Record<string, unknown>;
        };
        await route.continue({
            postData: JSON.stringify({
                ...body,
                context: { ...body.context, price: 1, price_minor: 100 },
            }),
        });
    });
    const offerButton = main.getByRole('button', { name: 'Оставить заявку' });
    const popup = preview.getByRole('dialog', {
        name: 'Лучшая цена на автомобиль',
    });
    const fillLead = async (value: string) => {
        await popup.getByLabel(/Телефон/).fill(value);
        await popup
            .getByRole('checkbox', { name: /Тестовое согласие клиента/ })
            .check();
    };

    await offerButton.click();
    await expect
        .poll(() =>
            popup.evaluate((element) =>
                element.contains(document.activeElement),
            ),
        )
        .toBe(true);
    await fillLead(leadPhone);
    let submitted = submissionResponse(preview);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(201);
    await expect(popup.getByRole('status')).toHaveText(
        'Спасибо! Мы свяжемся с вами.',
    );
    await preview.keyboard.press('Escape');
    await expect(offerButton).toBeFocused();

    // Duplicate phone for the same form is rejected and not persisted.
    browserIssues.expectFailedResponse(429, '/submissions');
    await offerButton.click();
    await fillLead(leadPhone);
    submitted = submissionResponse(preview);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(429);
    await expect(popup.getByRole('alert')).toHaveText(rejectionMessage);

    // A filled honeypot is rejected as spam.
    browserIssues.expectFailedResponse(422, '/submissions');
    await popup.getByLabel(/Телефон/).fill(phone('997'));
    await popup
        .locator('input[name="lf_website"]')
        .fill('https://spam.example', { force: true });
    submitted = submissionResponse(preview);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(422);
    await expect(popup.getByRole('alert')).toHaveText(rejectionMessage);
    await preview.keyboard.press('Escape');

    // Mobile 375 px: no horizontal overflow, popup still keyboard-usable.
    await preview.setViewportSize({ width: 375, height: 812 });
    await expect
        .poll(() =>
            preview.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        )
        .toBe(0);
    await offerButton.click();
    await expect(popup).toBeVisible();
    await preview.keyboard.press('Tab');
    await expect
        .poll(() =>
            popup.evaluate((element) =>
                element.contains(document.activeElement),
            ),
        )
        .toBe(true);
    await preview.keyboard.press('Escape');
    await expect(popup).toBeHidden();
    await preview.setViewportSize({ width: 1280, height: 800 });

    // Fake CAPTCHA (E2E never calls Yandex): required token first, then success.
    await setCaptchaRequired(page, true);
    await preview.reload();
    await offerButton.click();
    await fillLead(captchaPhone);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    await expect(
        popup.getByText('Подтвердите, что вы не робот.'),
    ).toBeVisible();
    await popup
        .getByRole('checkbox', { name: 'Я не робот (тестовая проверка)' })
        .check();
    submitted = submissionResponse(preview);
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(201);
    await expect(popup.getByRole('status')).toBeVisible();
    await setCaptchaRequired(page, false);

    // Preview test entries: only accepted ones, with the server-side vehicle, offer and price.
    await openPreviewLeads(page);
    const leads = page.getByRole('article').filter({ hasText: formName });
    await expect(leads).toHaveCount(2);
    const lead = leads.filter({ hasText: leadPhone });
    await expect(lead).toHaveCount(1);
    const leadContext = lead.getByRole('region', { name: 'Контекст заявки' });
    await expect(leadContext).toContainText(popupName);
    await expect(leadContext).toContainText('Lada Vesta');
    await expect(leadContext).toContainText('Comfort');
    await expect(leadContext).toContainText(/1\s250\s000\s₽/);
});
