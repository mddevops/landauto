import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.lifecycle;
const v1Price = /1\s990\s000\s₽/;
const v2Price = /2\s090\s000\s₽/;

async function login(page: Page, email: string, password: string) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function signedIn(
    browser: Browser,
    email: string,
    password: string,
): Promise<Page> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, email, password);

    return page;
}

async function expectSaved(page: Page) {
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

async function openPublishing(page: Page, siteUrl: string) {
    await page.goto(`${siteUrl}/publishing`);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Публикация' }),
    ).toBeVisible();
}

async function publish(page: Page, version: number) {
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText(`Сайт опубликован. Версия ${version}.`).first(),
    ).toBeVisible();
    await expect(page.getByTestId('production-status')).toContainText(
        `Версия ${version}`,
    );
}

async function expectVisitorSees(
    visitor: Page,
    heading: string,
    price: RegExp,
    hidden: string,
) {
    await visitor.reload();
    await expect(visitor.getByRole('heading', { name: heading })).toBeVisible();
    await expect(visitor.getByText(price).first()).toBeVisible();
    await expect(visitor.getByText(hidden)).toHaveCount(0);
}

async function submitLead(
    target: Page,
    phone: string,
    endpoint: string,
): Promise<number> {
    const popup = target.getByRole('dialog', {
        name: 'Тест-драйв за 15 минут',
    });
    await popup.getByLabel(/Телефон/).fill(phone);
    await popup
        .getByRole('checkbox', { name: /Согласен на обработку данных/ })
        .check();
    const submitted = target.waitForResponse(
        (response) =>
            response.url().includes(endpoint) &&
            response.request().method() === 'POST',
    );
    await popup.getByRole('button', { name: 'Отправить' }).click();
    const status = (await submitted).status();
    await expect(popup.getByRole('status')).toHaveText(
        'Спасибо! Мы свяжемся с вами.',
    );

    return status;
}

function phoneFor(run: string, suffix: string): string {
    return `+7 (997) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-${suffix}`;
}

test('full publishing lifecycle: draft, preview, publish, isolation, blocked publish, history and restore', async ({
    page,
    browser,
    browserIssues,
}) => {
    test.setTimeout(300_000);
    const run = String(Date.now());
    const publicLeadPhone = phoneFor(run, '01');
    const previewLeadPhone = phoneFor(run, '02');
    const visitor = await page.context().newPage();

    // Before the first Publish the public host answers with the safe 404.
    browserIssues.expectFailedResponse(404, 'lifecycle-e2e.localhost');
    await visitor.goto(owner.publicUrl);
    await expect(
        visitor.getByRole('heading', { name: 'Страница не найдена' }),
    ).toBeVisible();

    await login(page, owner.email, owner.password);
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${owner.site}»` })
        .click();
    await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i);
    const siteUrl = page.url().replace(/\/designer$/, '');

    // Draft v1 in preview.
    const preview = await page.context().newPage();
    await preview.goto(`${siteUrl}/preview`);
    const previewMain = preview.getByRole('main', {
        name: 'Предпросмотр страницы',
    });
    await expect(
        previewMain.getByRole('heading', { name: 'Цикл: версия 1' }),
    ).toBeVisible();
    await expect(previewMain.getByText(v1Price).first()).toBeVisible();

    await openPublishing(page, siteUrl);
    await expect(page.getByTestId('production-status')).toHaveText(
        'Сайт ещё не опубликован.',
    );
    await publish(page, 1);

    // Generated HTML: heading and price are there before any JavaScript runs.
    const noScript = await browser.newContext({ javaScriptEnabled: false });
    const raw = await noScript.newPage();
    await raw.goto(owner.publicUrl);
    await expect(
        raw.getByRole('heading', { name: 'Цикл: версия 1' }),
    ).toBeVisible();
    await expect(raw.getByText(v1Price).first()).toBeVisible();
    const v1Image = await raw
        .locator('img[src*="/_landflow/media/"]')
        .first()
        .getAttribute('src');
    expect(v1Image).toBeTruthy();
    const v1ImageUrl = new URL(v1Image ?? '', owner.publicUrl).href;

    // Draft v2: heading, price and Form field label.
    await page.goto(`${siteUrl}/designer`);
    await page
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    await page
        .getByRole('complementary', { name: 'Свойства' })
        .getByLabel('Заголовок', { exact: true })
        .fill('Цикл: версия 2');
    await expectSaved(page);

    await page.goto(`${siteUrl}/vehicles`);
    await page.getByRole('link', { name: /Кроссовер/ }).click();
    const offers = page.getByRole('region', { name: 'Предложения' });
    await offers.getByRole('button', { name: 'Изменить' }).click();
    const offerDialog = page.getByRole('dialog', {
        name: 'Изменить предложение',
    });
    await offerDialog.getByLabel('Цена, ₽', { exact: true }).fill('2 090 000');
    await offerDialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(offerDialog).toBeHidden();
    await expect(offers).toContainText(v2Price);

    await page.goto(`${siteUrl}/forms`);
    await page.getByRole('link', { name: /Заявка на тест-драйв/ }).click();
    await page.getByRole('button', { name: 'Изменить поле «Имя»' }).click();
    const fieldDialog = page.getByRole('dialog', { name: 'Поле «Имя»' });
    await fieldDialog.getByLabel('Подпись').fill('Ваше имя');
    await fieldDialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(fieldDialog).toBeHidden();

    // Production still serves v1, and its Form is the published v1 Form (mode public).
    await expectVisitorSees(
        visitor,
        'Цикл: версия 1',
        v1Price,
        'Цикл: версия 2',
    );
    await expect(visitor.getByText(v2Price)).toHaveCount(0);
    await visitor
        .getByRole('button', { name: 'Записаться на тест-драйв' })
        .click();
    const publicPopup = visitor.getByRole('dialog', {
        name: 'Тест-драйв за 15 минут',
    });
    await expect(publicPopup.getByLabel('Имя', { exact: true })).toBeVisible();
    await expect(publicPopup.getByLabel('Ваше имя')).toHaveCount(0);
    expect(
        await submitLead(visitor, publicLeadPhone, '/_landflow/forms/'),
    ).toBe(201);

    // Preview shows Draft v2 and its submissions are preview test entries.
    await preview.reload();
    await expect(
        previewMain.getByRole('heading', { name: 'Цикл: версия 2' }),
    ).toBeVisible();
    await expect(previewMain.getByText(v2Price).first()).toBeVisible();
    await previewMain
        .getByRole('button', { name: 'Записаться на тест-драйв' })
        .click();
    await expect(
        preview
            .getByRole('dialog', { name: 'Тест-драйв за 15 минут' })
            .getByLabel('Ваше имя'),
    ).toBeVisible();
    expect(await submitLead(preview, previewLeadPhone, '/preview/forms/')).toBe(
        201,
    );

    await page.goto(`${siteUrl}/submissions`);
    const publicLead = page
        .getByRole('article')
        .filter({ hasText: publicLeadPhone });
    await expect(publicLead).toHaveCount(1);
    await expect(publicLead).not.toContainText('Тестовая (предпросмотр)');
    await expect(
        page.getByRole('article').filter({ hasText: previewLeadPhone }),
    ).toHaveCount(0);
    await page
        .getByRole('navigation', { name: 'Тип заявок' })
        .getByRole('link', { name: /Тестовые из предпросмотра/ })
        .click();
    await expect(
        page.getByRole('article').filter({ hasText: previewLeadPhone }),
    ).toContainText('Тестовая (предпросмотр)');

    // Publish v2; the v1 media URL keeps working for cached v1 pages.
    await openPublishing(page, siteUrl);
    await publish(page, 2);
    await expectVisitorSees(
        visitor,
        'Цикл: версия 2',
        v2Price,
        'Цикл: версия 1',
    );
    const historicalImage = await raw.goto(v1ImageUrl);
    expect(historicalImage?.status()).toBe(200);

    // A broken action (scroll to a hidden block) blocks Publish until it is fixed.
    await page.goto(`${siteUrl}/designer`);
    await page
        .getByRole('button', { name: 'Скрыть «Карточка автомобиля»' })
        .click();
    await expect(
        page.getByRole('button', { name: 'Показать «Карточка автомобиля»' }),
    ).toBeVisible();
    await openPublishing(page, siteUrl);
    await expect(page.getByTestId('publish-errors')).toContainText(
        'Действие прокручивает к скрытому блоку.',
    );
    await expect(
        page.getByRole('button', { name: 'Опубликовать' }),
    ).toBeDisabled();
    await page.goto(`${siteUrl}/designer`);
    await page
        .getByRole('button', { name: 'Показать «Карточка автомобиля»' })
        .click();
    await expect(
        page.getByRole('button', { name: 'Скрыть «Карточка автомобиля»' }),
    ).toBeVisible();
    await openPublishing(page, siteUrl);
    await expect(page.getByTestId('publish-errors')).toHaveCount(0);
    await publish(page, 3);

    // Version history and restore of v1 into the Draft; production stays on v3.
    const history = page.getByTestId('version-history');
    await expect(history.getByRole('listitem')).toHaveCount(3);
    await expect(
        history.getByRole('listitem').filter({ hasText: 'Версия 3' }),
    ).toContainText('На сайте');
    await expect(
        history.getByRole('listitem').filter({ hasText: 'Версия 1' }),
    ).toContainText(owner.name);
    await page
        .getByRole('button', { name: 'Восстановить версию 1 в черновик' })
        .click();
    const confirm = page.getByRole('dialog', {
        name: 'Восстановить версию 1?',
    });
    await confirm.getByRole('button', { name: 'Восстановить' }).click();
    await expect(
        page.getByText(/Черновик восстановлен из версии 1\./).first(),
    ).toBeVisible();
    await expect(page.getByTestId('production-status')).toContainText(
        'Версия 3',
    );

    await preview.reload();
    await expect(
        previewMain.getByRole('heading', { name: 'Цикл: версия 1' }),
    ).toBeVisible();
    await expect(previewMain.getByText(v1Price).first()).toBeVisible();
    await expectVisitorSees(
        visitor,
        'Цикл: версия 2',
        v2Price,
        'Цикл: версия 1',
    );

    // Publishing the restored Draft creates a new version.
    await publish(page, 4);
    await expectVisitorSees(
        visitor,
        'Цикл: версия 1',
        v1Price,
        'Цикл: версия 2',
    );

    // Interactive after hydration and usable at 375 px without horizontal overflow.
    await visitor.setViewportSize({ width: 375, height: 812 });
    await visitor.reload();
    await visitor
        .getByRole('button', { name: 'Записаться на тест-драйв' })
        .click();
    await expect(
        visitor
            .getByRole('dialog', { name: 'Тест-драйв за 15 минут' })
            .getByLabel('Имя', { exact: true }),
    ).toBeVisible();
    await visitor.keyboard.press('Escape');
    expect(
        await visitor.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await noScript.close();

    // A Designer member can see the page but cannot publish or restore.
    const designer = await signedIn(
        browser,
        users.lifecycleDesigner.email,
        users.lifecycleDesigner.password,
    );
    await openPublishing(designer, siteUrl);
    await expect(
        designer.getByText('У вас нет права публиковать этот сайт.'),
    ).toBeVisible();
    await expect(
        designer.getByRole('button', { name: 'Опубликовать' }),
    ).toHaveCount(0);
    await expect(
        designer.getByRole('button', { name: /^Восстановить версию/ }),
    ).toHaveCount(0);
    await designer.context().close();

    // Platform media referenced by a Published Version cannot be deleted.
    const admin = await signedIn(
        browser,
        users.catalogAdmin.email,
        users.catalogAdmin.password,
    );
    await admin.goto('/platform/catalog');
    await admin.getByRole('link', { name: 'Moskvich', exact: true }).click();
    await admin.getByRole('link', { name: 'Moskvich 3', exact: true }).click();
    await admin.getByRole('link', { name: 'I', exact: true }).click();
    await admin.getByRole('link', { name: 'Медиа серии Кроссовер' }).click();
    await admin
        .getByRole('button', { name: 'Удалить изображение: Спереди 3/4' })
        .click();
    await expect(
        admin
            .getByText(
                'Фото используется на опубликованных сайтах, удалить его нельзя.',
            )
            .first(),
    ).toBeVisible();
    await expect(
        admin.getByRole('img', { name: 'Белый: Спереди 3/4' }),
    ).toBeVisible();
    await admin.context().close();
});
