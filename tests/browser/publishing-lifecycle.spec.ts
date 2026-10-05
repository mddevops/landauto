import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.lifecycle;
const v1Price = /1\s990\s000\s₽/;
const v2Price = /2\s090\s000\s₽/;
// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

/** Uploads an image into the Site library and selects it as the hero background. */
async function setHeroImage(page: Page, name: string) {
    await page
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    const properties = page.getByRole('complementary', { name: 'Свойства' });
    await properties
        .getByRole('button', { name: 'Выбрать: Фоновое изображение' })
        .click();
    const library = page.getByRole('dialog', {
        name: 'Библиотека изображений',
    });
    await library.getByLabel('Загрузить изображение').setInputFiles({
        name,
        mimeType: 'image/png',
        buffer: png,
    });
    await expect(library).toBeHidden();
    await expect(properties.getByText(name)).toBeVisible();
    await expectSaved(page);
}

/** Version-scoped URL of the hero background on a published page. */
async function heroImageUrl(target: Page): Promise<string> {
    const src = await target
        .locator('img[src*="/_landflow/assets/"]')
        .first()
        .getAttribute('src');
    expect(src).toBeTruthy();

    return new URL(src ?? '', owner.publicUrl).href;
}

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
    await setHeroImage(page, 'showroom-a.png');

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
    const imageA = await heroImageUrl(raw);

    // Production SEO head, robots.txt and sitemap.
    await expect(raw.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        `${owner.publicUrl}/`,
    );
    await expect(raw.locator('meta[name="robots"]')).toHaveAttribute(
        'content',
        'index, follow',
    );
    await raw.goto(`${owner.publicUrl}/robots.txt`);
    expect(await raw.content()).toContain(
        `Sitemap: ${owner.publicUrl}/sitemap.xml`,
    );
    await raw.goto(`${owner.publicUrl}/sitemap.xml`);
    expect(await raw.content()).toContain(`<loc>${owner.publicUrl}/</loc>`);

    // Draft v2: heading, hero image, price and Form field label.
    await page.goto(`${siteUrl}/designer`);
    await page
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();
    await page
        .getByRole('complementary', { name: 'Свойства' })
        .getByLabel('Заголовок', { exact: true })
        .fill('Цикл: версия 2');
    await expectSaved(page);
    await setHeroImage(page, 'showroom-b.png');

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
    expect(await heroImageUrl(visitor)).toBe(imageA);
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
    const imageB = await heroImageUrl(visitor);
    expect(imageB.split('/').pop()).not.toBe(imageA.split('/').pop());
    for (const historical of [v1ImageUrl, imageA]) {
        expect((await raw.goto(historical))?.status()).toBe(200);
    }

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
    await expectVisitorSees(
        visitor,
        'Цикл: версия 2',
        v2Price,
        'Цикл: версия 1',
    );
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

    // Public interactivity after hydration: carousel, lightbox, colors, offer details, Popup.
    const carousel = visitor.getByRole('region', {
        name: 'Автомобили в наличии',
    });
    const dot = (position: number) =>
        carousel.getByRole('button', { name: `Перейти к слайду ${position}` });
    await expect(carousel.getByRole('group', { name: /из 2$/ })).toHaveCount(2);
    await expect(dot(1)).toHaveAttribute('aria-current', 'true');
    await carousel.getByRole('button', { name: 'Следующий слайд' }).click();
    await expect(dot(2)).toHaveAttribute('aria-current', 'true');

    const photo = visitor
        .getByRole('button', { name: /Открыть фото на весь экран/ })
        .first();
    await photo.click();
    const lightbox = visitor.getByRole('dialog', { name: /Фото:/ });
    await expect(lightbox).toContainText('1 из 2');
    await lightbox.getByRole('button', { name: 'Следующее фото' }).click();
    await expect(lightbox).toContainText('2 из 2');
    await visitor.keyboard.press('Escape');
    await expect(lightbox).toBeHidden();

    const card = visitor
        .getByRole('article')
        .filter({ has: visitor.getByRole('button', { name: 'Красный' }) })
        .first();
    const cardImage = card.getByRole('img').first();
    await expect(cardImage).toHaveAccessibleName(/Белый/);
    await card.getByRole('button', { name: 'Красный' }).click();
    await expect(cardImage).toHaveAccessibleName(/Красный/);
    await expect(card.getByRole('button', { name: 'Красный' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );

    await visitor
        .getByRole('button', { name: 'Подробнее', expanded: false })
        .first()
        .click();
    await expect(
        visitor.getByRole('button', { name: 'Скрыть подробности' }),
    ).toHaveAttribute('aria-expanded', 'true');
    await expect(
        visitor.getByText('Модификация 1.5 CVT 150 л.с.'),
    ).toBeVisible();
    await visitor.getByRole('button', { name: 'Оставить заявку' }).click();
    await expect(
        visitor.getByRole('dialog', { name: 'Тест-драйв за 15 минут' }),
    ).toBeVisible();
    await visitor.keyboard.press('Escape');

    // Usable at 375 px without horizontal overflow.
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

    // Designer: preview yes, publish/restore no. Admin: publish yes, restore no.
    const designer = await signedIn(
        browser,
        users.lifecycleDesigner.email,
        users.lifecycleDesigner.password,
    );
    await designer.goto(`${siteUrl}/preview`);
    await expect(
        designer
            .getByRole('main', { name: 'Предпросмотр страницы' })
            .getByRole('heading', { name: 'Цикл: версия 1' }),
    ).toBeVisible();
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

    const workspaceAdmin = await signedIn(
        browser,
        users.lifecycleAdmin.email,
        users.lifecycleAdmin.password,
    );
    await openPublishing(workspaceAdmin, siteUrl);
    await expect(
        workspaceAdmin.getByRole('button', { name: 'Опубликовать' }),
    ).toBeEnabled();
    await expect(
        workspaceAdmin.getByRole('button', { name: /^Восстановить версию/ }),
    ).toHaveCount(0);
    await workspaceAdmin.context().close();

    // Another Workspace's member gets 404 for this Site.
    const stranger = await signedIn(
        browser,
        users.publisher.email,
        users.publisher.password,
    );
    expect((await stranger.goto(`${siteUrl}/publishing`))?.status()).toBe(404);
    expect((await stranger.goto(`${siteUrl}/preview`))?.status()).toBe(404);
    await stranger.context().close();

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
        .getByRole('region', { name: 'Белый' })
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
