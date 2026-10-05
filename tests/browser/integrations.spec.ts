import { execFileSync } from 'node:child_process';
import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.integrations;
const token = 'e2e-crm-token-5f2a9c7d41';
const internalToken = 'e2e-internal-token-0b6e33';
const counter = '12345678';

/**
 * Runs an artisan command against the isolated E2E database (never the developer one): the
 * same `.env.e2e` the server uses. Deliveries are queued, so the spec drives the worker.
 */
function artisan(...args: string[]) {
    execFileSync('php', ['artisan', ...args, '--no-interaction'], {
        env: { ...process.env, APP_ENV: 'e2e' },
        stdio: 'pipe',
    });
}

const runQueue = () =>
    artisan('queue:work', '--stop-when-empty', '--tries=1', '--sleep=0');

function runDueRetries() {
    artisan('integrations:dispatch-due-deliveries');
    runQueue();
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

async function createProfile(
    page: Page,
    name: string,
    url: string,
    secret: string,
) {
    await page.getByRole('button', { name: 'Добавить подключение' }).click();
    const dialog = page.getByRole('dialog', { name: 'Новое подключение' });
    await dialog.getByLabel('Название').fill(name);
    await dialog.getByLabel('Адрес').fill(url);
    await dialog.getByLabel('Токен или ключ').fill(secret);
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();
}

async function addMapping(
    dialog: ReturnType<Page['getByRole']>,
    index: number,
    target: string,
    source: string,
) {
    await dialog.getByRole('button', { name: 'Добавить поле' }).click();
    await dialog.getByLabel('Поле в системе').nth(index).fill(target);
    await dialog
        .getByLabel('Значение из Landflow')
        .nth(index)
        .selectOption(source);
}

async function addWebhookRoute(
    page: Page,
    name: string,
    path: string,
    mapping: [string, string][] = [],
) {
    await page.getByRole('button', { name: 'Добавить маршрут' }).click();
    const dialog = page.getByRole('dialog', { name: 'Новый маршрут' });
    await dialog.getByLabel('Куда передавать').selectOption('webhook');
    await dialog.getByLabel('Название').fill(name);
    await dialog.getByLabel('Путь (необязательно)').fill(path);

    for (const [index, [target, source]] of mapping.entries()) {
        await addMapping(dialog, index, target, source);
    }

    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('article', { name })).toBeVisible();
}

async function submitLead(
    target: Page,
    phone: string,
    endpoint: string,
): Promise<number> {
    const popup = target.getByRole('dialog', {
        name: 'Перезвоним за 5 минут',
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

const delivery = (page: Page, route: string) =>
    page.getByRole('article', { name: `Доставка «${route}»`, exact: true });

/** Goals the page queued into the official `ym` stub (the real tag.js never loads in E2E). */
async function metricaCalls(target: Page): Promise<unknown[][]> {
    return target.evaluate(() => {
        const ym = (window as { ym?: { a?: ArrayLike<unknown>[] } }).ym;

        return Array.from(ym?.a ?? [], (args) => Array.from(args));
    });
}

test('integrations lifecycle: profiles, bindings, routes, deliveries, retries, permissions and Metrica', async ({
    page,
    browser,
}) => {
    test.setTimeout(300_000);
    const run = String(Date.now());
    const vehicleLeadPhone = `+7 (996) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-11`;
    const heroLeadPhone = `+7 (996) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-22`;
    const previewLeadPhone = `+7 (996) ${run.slice(-7, -4)}-${run.slice(-4, -2)}-33`;
    const metricaRequests: string[] = [];
    // The official loader stays real; only the external tag.js is answered locally.
    await page.context().route('https://mc.yandex.ru/**', (route) => {
        metricaRequests.push(route.request().url());

        return route.fulfill({
            status: 200,
            contentType: 'application/javascript',
            body: '',
        });
    });

    await login(page, owner.email, owner.password);
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${owner.site}»` })
        .click();
    await expect(page).toHaveURL(/\/sites\/[0-9A-HJKMNP-TV-Z]{26}\/designer$/i);
    const siteUrl = page.url().replace(/\/designer$/, '');

    // Workspace profile with a token: masked after save and after reload, never in HTML/props.
    await page.goto('/integrations');
    await createProfile(page, 'CRM вебхук', 'https://hooks.e2e.test', token);
    const crm = page.getByRole('article', { name: 'CRM вебхук' });
    await expect(crm).toContainText('Секрет');
    await expect(crm).toContainText(token.slice(-4));
    expect(await page.content()).not.toContain(token);
    await page.reload();
    await expect(crm).toBeVisible();
    expect(await page.content()).not.toContain(token);

    // Test Connection through the fake CRM; the toast never echoes the token.
    await page
        .getByRole('button', { name: 'Проверить подключение CRM вебхук' })
        .click();
    const connected = page.getByText(/Подключение работает: HTTP 200/).first();
    await expect(connected).toBeVisible();
    expect(await connected.textContent()).not.toContain(token);

    // SSRF regression: a host resolving to a private network is refused by the same policy.
    await createProfile(
        page,
        'Внутренний сервис',
        'https://private.e2e.test',
        internalToken,
    );
    await page
        .getByRole('button', {
            name: 'Проверить подключение Внутренний сервис',
        })
        .click();
    await expect(
        page
            .getByText(
                'Проверка не прошла: Адрес назначения запрещён политикой безопасности.',
            )
            .first(),
    ).toBeVisible();
    expect(await page.content()).not.toContain(internalToken);

    // Site binding with a Site-specific override, and Yandex Metrica (Draft until Publish).
    await page.goto(`${siteUrl}/integrations`);
    await page.getByRole('button', { name: 'Подключить', exact: true }).click();
    const binding = page.getByRole('dialog', { name: 'Подключить интеграцию' });
    await binding
        .getByLabel('Подключение')
        .selectOption({ label: 'CRM вебхук — Вебхук' });
    await binding.getByRole('button', { name: 'Добавить параметр' }).click();
    await binding.getByLabel('Ключ параметра 1').fill('dealer_id');
    await binding.getByLabel('Значение параметра 1').fill('D-101');
    await binding.getByRole('button', { name: 'Сохранить' }).click();
    await expect(binding).toBeHidden();
    await expect(
        page.getByRole('article', { name: 'CRM вебхук' }),
    ).toContainText('D-101');
    expect(await page.content()).not.toContain(token);

    await page.getByLabel('Подключить счётчик').check();
    await page.getByLabel('Номер счётчика').fill(counter);
    await page
        .getByRole('button', { name: 'Сохранить', exact: true })
        .last()
        .click();
    await expect(
        page.getByText(/Настройки Яндекс Метрики сохранены/).first(),
    ).toBeVisible();

    // Routes: email + webhook with field / trusted price / override mapping.
    await page.goto(`${siteUrl}/forms`);
    await page.getByRole('link', { name: /Заявка с сайта/ }).click();
    await page.getByRole('link', { name: 'Передача заявок' }).click();
    await page.getByRole('button', { name: 'Добавить маршрут' }).click();
    const emailRoute = page.getByRole('dialog', { name: 'Новый маршрут' });
    await emailRoute.getByLabel('Название').fill('Почта продаж');
    await emailRoute.getByLabel('Получатели').fill('sales@example.ru');
    await emailRoute.getByRole('button', { name: 'Сохранить' }).click();
    await expect(emailRoute).toBeHidden();
    await addWebhookRoute(page, 'CRM', '/leads', [
        ['phone', 'field.phone'],
        ['price', 'offer.price_minor'],
        ['dealer', 'override.dealer_id'],
    ]);
    const routesUrl = page.url();

    await page.goto(`${siteUrl}/publishing`);
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText('Сайт опубликован. Версия 1.').first(),
    ).toBeVisible();

    // Published HTML: official Metrica loader with the counter; no destinations or secrets.
    const visitor = await page.context().newPage();
    await visitor.goto(owner.publicUrl);
    const html = await visitor.content();
    expect(html).toContain('https://mc.yandex.ru/metrika/tag.js');
    expect(html).toContain(`ym(${counter}, "init"`);
    for (const hidden of [token, 'hooks.e2e.test', 'D-101', 'dealer_id']) {
        expect(html).not.toContain(hidden);
    }

    // Vehicle lead: client validation error first, then success; Metrica goals have no payload.
    await visitor.getByRole('button', { name: 'Узнать цену' }).first().click();
    const popup = visitor.getByRole('dialog', {
        name: 'Перезвоним за 5 минут',
    });
    await popup.getByRole('button', { name: 'Отправить' }).click();
    await expect(popup.getByText('Заполните это поле.')).toBeVisible();
    expect(
        await submitLead(visitor, vehicleLeadPhone, '/_landflow/forms/'),
    ).toBe(201);
    await visitor.keyboard.press('Escape');
    await expect(popup).toBeHidden();

    const calls = await metricaCalls(visitor);
    const goals = calls.filter((args) => args[1] === 'reachGoal');
    expect(calls).toContainEqual([
        Number(counter),
        'init',
        {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: false,
        },
    ]);
    expect(goals.map((args) => args[2])).toEqual(
        expect.arrayContaining([
            'popup_open',
            'form_start',
            'form_validation_error',
            'form_submit',
            'vehicle_form_submit',
            'form_success',
            'popup_close',
        ]),
    );
    for (const args of goals) {
        expect(args).toHaveLength(3);
        expect(args[0]).toBe(Number(counter));
    }
    const serialized = JSON.stringify(calls);
    for (const personal of [
        vehicleLeadPhone,
        vehicleLeadPhone.replace(/\D/g, ''),
        '@',
        owner.name,
    ]) {
        expect(serialized).not.toContain(personal);
    }

    // Success came back before any adapter ran: the Submission and both Deliveries wait.
    await page.goto(`${siteUrl}/deliveries`);
    await expect(delivery(page, 'Почта продаж')).toContainText('В очереди');
    await expect(delivery(page, 'CRM')).toContainText('В очереди');
    await page.goto(`${siteUrl}/submissions`);
    await expect(
        page.getByRole('article').filter({ hasText: vehicleLeadPhone }),
    ).toHaveCount(1);

    // Worker: email + webhook (fake CRM accepts only phone + positive trusted price + dealer).
    runQueue();
    await page.goto(`${siteUrl}/deliveries`);
    await expect(delivery(page, 'Почта продаж')).toContainText('Доставлено');
    await expect(delivery(page, 'CRM')).toContainText('Доставлено');

    // Temporary 503 (retried) and permanent 401 (failed, no endless retry).
    await page.goto(routesUrl);
    await page
        .getByRole('button', { name: 'Изменить маршрут CRM', exact: true })
        .click();
    const crmRoute = page.getByRole('dialog', { name: 'Настройки маршрута' });
    await crmRoute.getByLabel('Путь (необязательно)').fill('/flaky');
    await crmRoute.getByRole('button', { name: 'Сохранить' }).click();
    await expect(crmRoute).toBeHidden();
    await addWebhookRoute(page, 'CRM отказ', '/unauthorized');

    await visitor.reload();
    await visitor.getByRole('button', { name: 'Перезвоните мне' }).click();
    expect(await submitLead(visitor, heroLeadPhone, '/_landflow/forms/')).toBe(
        201,
    );
    await visitor.keyboard.press('Escape');

    runQueue();
    await page.goto(`${siteUrl}/deliveries`);
    await expect(delivery(page, 'CRM').first()).toContainText(
        'Повтор запланирован',
    );
    await expect(delivery(page, 'CRM').first()).toContainText(
        'Сервис временно недоступен. Доставка будет повторена. (HTTP 503)',
    );
    const refused = delivery(page, 'CRM отказ');
    await expect(refused).toContainText('Ошибка');
    await expect(refused).toContainText(
        'Сервис отклонил авторизацию. Проверьте доступ в профиле интеграции. (HTTP 401)',
    );

    runDueRetries();
    runDueRetries();
    await page.reload();
    const retried = delivery(page, 'CRM').first();
    await expect(retried).toContainText('Доставлено');
    await retried.getByText('История попыток').click();
    await expect(retried).toContainText('№2');
    await expect(retried).toContainText('HTTP 503');
    await refused.getByText('История попыток').click();
    await expect(refused.getByRole('listitem')).toHaveCount(1);
    for (const hidden of [token, heroLeadPhone, vehicleLeadPhone]) {
        expect(await page.content()).not.toContain(hidden);
    }

    // Admin: logs and a manual retry that records a new attempt.
    const admin = await signedIn(
        browser,
        users.integrationsAdmin.email,
        users.integrationsAdmin.password,
    );
    await admin.goto(`${siteUrl}/deliveries`);
    await delivery(admin, 'CRM отказ')
        .getByRole('button', { name: 'Повторить' })
        .click();
    await expect(
        admin.getByText('Повторная отправка запущена.').first(),
    ).toBeVisible();
    runQueue();
    await admin.reload();
    const manual = delivery(admin, 'CRM отказ');
    await manual.getByText('История попыток').click();
    await expect(manual).toContainText('№2 (вручную)');
    await expect(manual).toContainText('Ошибка');
    await admin.context().close();

    // Preview: Draft submission creates no Delivery and Metrica never loads.
    const deliveriesBefore = await page.getByRole('article').count();
    const metricaBeforePreview = metricaRequests.length;
    const preview = await page.context().newPage();
    await preview.goto(`${siteUrl}/preview`);
    await preview
        .getByRole('main', { name: 'Предпросмотр страницы' })
        .getByRole('button', { name: 'Перезвоните мне' })
        .click();
    expect(await submitLead(preview, previewLeadPhone, '/preview/forms/')).toBe(
        201,
    );
    expect(
        await preview.evaluate(() => 'ym' in window),
        'Preview must not define ym',
    ).toBe(false);
    expect(await preview.content()).not.toContain('mc.yandex.ru');
    expect(metricaRequests.length).toBe(metricaBeforePreview);
    runQueue();
    await page.reload();
    await expect(page.getByRole('article')).toHaveCount(deliveriesBefore);

    // Designer: no credentials, bindings or delivery logs.
    const designer = await signedIn(
        browser,
        users.integrationsDesigner.email,
        users.integrationsDesigner.password,
    );
    for (const url of [
        '/integrations',
        `${siteUrl}/integrations`,
        `${siteUrl}/deliveries`,
    ]) {
        expect((await designer.goto(url))?.status(), url).toBe(403);
    }
    await designer.context().close();

    // Another Workspace: the Site's bindings and deliveries do not exist; profiles are not listed.
    const stranger = await signedIn(
        browser,
        users.publisher.email,
        users.publisher.password,
    );
    for (const url of [`${siteUrl}/integrations`, `${siteUrl}/deliveries`]) {
        expect((await stranger.goto(url))?.status(), url).toBe(404);
    }
    await stranger.goto('/integrations');
    await expect(
        stranger.getByRole('heading', { level: 1, name: 'Интеграции' }),
    ).toBeVisible();
    expect(await stranger.content()).not.toContain('CRM вебхук');
    await stranger.context().close();
});
