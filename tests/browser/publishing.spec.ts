import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const publisher = users.publisher;

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(publisher.email);
    await page.getByLabel('Пароль', { exact: true }).fill(publisher.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function openDesigner(page: Page) {
    await page
        .getByRole('link', {
            name: `Открыть дизайнер сайта «${publisher.site}»`,
        })
        .click();
    await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i);
}

async function expectSaved(page: Page) {
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

async function publish(page: Page, version: number) {
    await page.getByRole('link', { name: 'Публикация' }).click();
    await expect(
        page.getByRole('heading', { level: 1, name: 'Публикация' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText(`Сайт опубликован. Версия ${version}.`).first(),
    ).toBeVisible();
    await expect(page.getByTestId('production-status')).toContainText(
        `Версия ${version}`,
    );
}

test('owner publishes a Site; visitors get stored HTML, hydration and version-bound leads', async ({
    page,
    browser,
    browserIssues,
}) => {
    test.setTimeout(120_000);
    const run = String(Date.now());
    const phone = `+7 (998) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-${run.slice(-2)}`;
    const visitor = await page.context().newPage();

    // Not published yet: the subdomain answers with a safe 404.
    browserIssues.expectFailedResponse(404, 'publish-e2e.localhost');
    await visitor.goto(publisher.publicUrl);
    await expect(
        visitor.getByRole('heading', { name: 'Страница не найдена' }),
    ).toBeVisible();

    await login(page);
    await openDesigner(page);
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await page
        .getByRole('complementary', { name: 'Левая панель' })
        .getByRole('button', { name: 'Добавить блок «Первый экран»' })
        .click();
    await properties
        .getByLabel('Заголовок', { exact: true })
        .fill('Опубликованный заголовок');
    const primaryButton = properties.getByRole('group', {
        name: 'Основная кнопка',
    });
    await primaryButton.getByLabel('Действие: тип').selectOption('open_popup');
    await primaryButton
        .getByLabel('Попап', { exact: true })
        .selectOption({ label: 'Обратный звонок' });
    await expectSaved(page);

    // Draft SEO of the home Page goes into the published head.
    const left = page.getByRole('complementary', { name: 'Левая панель' });
    await left.getByRole('tab', { name: 'Страницы' }).click();
    await left.getByRole('button', { name: 'SEO страницы «Главная»' }).click();
    const seo = page.getByRole('dialog', { name: 'SEO страницы «Главная»' });
    await seo
        .getByLabel('Заголовок для поисковиков')
        .fill('Дилер — новые автомобили');
    await seo
        .getByLabel('Описание для поисковиков')
        .fill('Автомобили в наличии и спецпредложения.');
    await seo.getByRole('button', { name: 'Сохранить' }).click();
    await expect(
        page.getByText('SEO страницы сохранено.').first(),
    ).toBeVisible();

    await publish(page, 1);

    // Publish-time HTML: the content is there before any JavaScript runs.
    const noScript = await browser.newContext({ javaScriptEnabled: false });
    const raw = await noScript.newPage();
    await raw.goto(publisher.publicUrl);
    await expect(
        raw.getByRole('heading', { name: 'Опубликованный заголовок' }),
    ).toBeVisible();
    expect(await raw.content()).toContain('id="lf-page-data"');
    await expect(raw).toHaveTitle('Дилер — новые автомобили');
    await expect(raw.locator('meta[name="description"]')).toHaveAttribute(
        'content',
        'Автомобили в наличии и спецпредложения.',
    );
    await expect(raw.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        `${publisher.publicUrl}/`,
    );
    await raw.goto(`${publisher.publicUrl}/sitemap.xml`);
    expect(await raw.content()).toContain(`${publisher.publicUrl}/`);
    await noScript.close();

    // Hydrated page: the button opens the published Popup and the lead goes to the version.
    await visitor.goto(publisher.publicUrl);
    await expect(
        visitor.getByRole('heading', { name: 'Опубликованный заголовок' }),
    ).toBeVisible();
    await visitor.getByRole('button', { name: 'Подобрать автомобиль' }).click();
    const popup = visitor.getByRole('dialog', {
        name: 'Перезвоним за 5 минут',
    });
    await expect(popup).toBeVisible();
    await popup.getByLabel(/Телефон/).fill(phone);
    await popup
        .getByRole('checkbox', { name: /Согласен на обработку данных/ })
        .check();
    const submitted = visitor.waitForResponse(
        (response) =>
            response.url().includes('/_landflow/forms/') &&
            response.request().method() === 'POST',
    );
    await popup.getByRole('button', { name: 'Отправить' }).click();
    expect((await submitted).status()).toBe(201);
    await expect(popup.getByRole('status')).toHaveText(
        'Спасибо! Мы свяжемся с вами.',
    );

    // Draft edits stay out of production until the next publish.
    await page.getByRole('link', { name: 'Открыть дизайнер' }).click();
    await page
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    await properties
        .getByLabel('Заголовок', { exact: true })
        .fill('Черновой заголовок');
    await expectSaved(page);
    await visitor.reload();
    await expect(
        visitor.getByRole('heading', { name: 'Опубликованный заголовок' }),
    ).toBeVisible();
    await expect(visitor.getByText('Черновой заголовок')).toHaveCount(0);

    // The public lead is a real lead, not a preview test entry.
    await page.getByRole('button', { name: 'Разделы сайта' }).click();
    await page.getByRole('menuitem', { name: 'Заявки' }).click();
    const lead = page.getByRole('article').filter({ hasText: phone });
    await expect(lead).toHaveCount(1);
    await expect(lead).not.toContainText('Тестовая (предпросмотр)');

    await page.getByRole('link', { name: 'Открыть дизайнер' }).click();
    await expect(page).toHaveURL(/\/designer$/);
    await publish(page, 2);
    await visitor.reload();
    await expect(
        visitor.getByRole('heading', { name: 'Черновой заголовок' }),
    ).toBeVisible();

    // An explicit address change moves the public host at once.
    await expect(page.getByTestId('public-url')).toHaveText(
        `${publisher.publicUrl}/`,
    );
    await page.getByLabel('Поддомен').fill('publish-e2e-renamed');
    await page.getByRole('button', { name: 'Сохранить адрес' }).click();
    await expect(page.getByText('Адрес сайта сохранён.').first()).toBeVisible();
    await expect(page.getByTestId('public-url')).toHaveText(
        'http://publish-e2e-renamed.localhost:8200/',
    );
    await visitor.goto('http://publish-e2e-renamed.localhost:8200/');
    await expect(
        visitor.getByRole('heading', { name: 'Черновой заголовок' }),
    ).toBeVisible();
});
