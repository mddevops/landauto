import { execFileSync } from 'node:child_process';
import type { Browser, BrowserContext, Locator, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { foreignTeamIds, guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.teamOwner;
const designer = users.teamDesigner;
const sourcePrice = /2\s000\s000\s₽/;
const protectedPrice = /1\s900\s000\s₽/;
// 1×1 PNG generated in memory; uploads never read files from the developer machine.
const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);

type Account = { email: string; password: string };
type SessionState = Awaited<ReturnType<BrowserContext['storageState']>>;

/** One login per account and spec run, so the login rate limit is never reached. */
const sessions = new Map<string, SessionState>();

/**
 * Runs an artisan command against the isolated E2E environment (never the developer one) and
 * returns its output.
 */
function artisan(...args: string[]): string {
    return execFileSync('php', ['artisan', ...args, '--no-interaction'], {
        env: { ...process.env, APP_ENV: 'e2e' },
        stdio: 'pipe',
        encoding: 'utf8',
    }).trim();
}

async function signedIn(
    browser: Browser,
    account: Account,
    viewport?: { width: number; height: number },
): Promise<Page> {
    const cached = sessions.get(account.email);
    const context = await browser.newContext({
        storageState: cached ?? guestStorageState,
        ...(viewport ? { viewport } : {}),
    });
    const page = await context.newPage();

    if (cached) {
        return page;
    }

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
    sessions.set(account.email, await context.storageState());

    return page;
}

/** `/sites/{public_id}` of a Site card on the current Workspace dashboard. */
async function siteUrl(page: Page, name: string): Promise<string> {
    await page.goto('/dashboard');
    const href = await page
        .getByRole('link', { name: `Открыть сайт «${name}»` })
        .getAttribute('href');

    return new URL(href ?? '', 'http://e2e.invalid').pathname;
}

async function expectStatus(page: Page, url: string, status: number) {
    expect((await page.goto(url))?.status(), url).toBe(status);
}

/** POST with the session's CSRF cookie, as Inertia would send it. */
async function postStatus(page: Page, url: string): Promise<number> {
    const xsrf = (await page.context().cookies()).find(
        (cookie) => cookie.name === 'XSRF-TOKEN',
    );
    const response = await page.request.post(url, {
        headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? '') },
        maxRedirects: 0,
    });

    return response.status();
}

function memberRow(page: Page, email: string): Locator {
    return page
        .getByRole('list', { name: 'Участники' })
        .getByRole('listitem')
        .filter({ hasText: email });
}

async function openTeam(page: Page) {
    await page.goto('/workspace/team');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Команда' }),
    ).toBeVisible();
}

async function switchWorkspace(page: Page, from: string, to: string) {
    await page.goto('/dashboard');
    await page
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${from}`,
        })
        .click();
    await page.getByRole('menu').getByRole('menuitem', { name: to }).click();
    await expect(page.getByTestId('dashboard-workspace')).toHaveText(
        `Пространство: ${to}`,
    );
}

async function selectHero(page: Page): Promise<Locator> {
    await page
        .getByRole('button', { name: 'Выбрать блок «Первый экран»' })
        .click();

    return page.getByRole('complementary', { name: 'Свойства' });
}

async function editHeroTitle(page: Page, url: string, title: string) {
    await page.goto(`${url}/designer`);
    const properties = await selectHero(page);
    await properties.getByLabel('Заголовок', { exact: true }).fill(title);
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();
}

async function expectHeroReadOnly(page: Page, url: string) {
    await page.goto(`${url}/designer`);
    const properties = await selectHero(page);
    await expect(
        properties.getByLabel('Заголовок', { exact: true }),
    ).toBeDisabled();
}

async function openVehicle(page: Page, url: string, series: string) {
    await page.goto(`${url}/vehicles`);
    await page
        .getByRole('link', { name: new RegExp(series) })
        .first()
        .click();
    await expect(
        page.getByText(`I · ${series}`, { exact: true }),
    ).toBeVisible();
}

async function setOfferPrice(page: Page, price: string) {
    await page.getByRole('button', { name: 'Изменить', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Изменить предложение' });
    await dialog.getByLabel('Цена, ₽', { exact: true }).fill(price);
    await dialog.getByRole('button', { name: 'Сохранить' }).click();
    await expect(dialog).toBeHidden();
}

async function openImport(page: Page, siteB: string, sourceName: string) {
    await page.goto(`${siteB}/vehicles/import`);
    await page.getByLabel('Сайт-источник').selectOption({ label: sourceName });
    await expect(
        page.getByRole('group', { name: 'Автомобили', exact: true }),
    ).toBeVisible();
}

function importVehicle(page: Page, series: string): Locator {
    return page.getByRole('checkbox', { name: new RegExp(series) });
}

async function submitImport(page: Page, expected: RegExp) {
    await page.getByRole('button', { name: 'Скопировать выбранные' }).click();
    await expect(
        page.getByRole('region', { name: 'Результат копирования' }),
    ).toContainText(expected);
}

test('owner invites a designer, limits Site access, suspends, restores and removes the member', async ({
    browser,
}) => {
    test.setTimeout(150_000);
    const ownerPage = await signedIn(browser, owner);
    const siteA = await siteUrl(ownerPage, owner.siteA);
    const siteB = await siteUrl(ownerPage, owner.siteB);

    // Owner: Workspace switcher shows the current Workspace, «Команда» is in the sidebar.
    await ownerPage
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${owner.workspace}`,
        })
        .click();
    await expect(
        ownerPage.getByRole('menu').getByRole('menuitem', {
            name: owner.workspace,
        }),
    ).toBeVisible();
    await ownerPage.keyboard.press('Escape');
    await ownerPage.getByRole('link', { name: 'Команда', exact: true }).click();
    await expect(
        ownerPage.getByRole('heading', { level: 1, name: 'Команда' }),
    ).toBeVisible();

    await ownerPage.getByRole('button', { name: 'Пригласить' }).click();
    const invite = ownerPage.getByRole('dialog', {
        name: 'Пригласить участника',
    });
    await invite.getByLabel('Электронная почта').fill(designer.email);
    await invite.getByLabel('Роль').selectOption({ label: 'Дизайнер' });
    await invite.getByRole('button', { name: 'Отправить приглашение' }).click();
    await expect(invite).toBeHidden();
    const pending = ownerPage
        .getByRole('list', { name: 'Приглашения' })
        .getByRole('listitem')
        .filter({ hasText: designer.email });
    await expect(pending).toContainText('Ожидает ответа');
    await expect(pending).toContainText('Дизайнер');

    // Test-only helper: a fresh link for the pending invitation; only its hash is stored.
    const invitationPath = artisan('team:e2e-invitation-url', designer.email);
    expect(invitationPath).toMatch(/^\/invitations\/[a-f0-9]{64}$/);
    const token = invitationPath.split('/').pop() ?? '';
    await ownerPage.reload();
    await expect(pending).toBeVisible();
    const teamHtml = await ownerPage.content();
    expect(teamHtml).not.toContain(token);
    expect(teamHtml).not.toContain('token_hash');

    const designerPage = await signedIn(browser, designer);
    await designerPage.goto(invitationPath);
    await expect(designerPage).toHaveURL('/invitation');
    await designerPage
        .getByRole('button', { name: 'Принять приглашение' })
        .click();
    await expect(designerPage).toHaveURL('/dashboard');
    await expect(designerPage.getByTestId('dashboard-workspace')).toHaveText(
        `Пространство: ${owner.workspace}`,
    );

    // Owner: selected Sites → Site A only.
    await openTeam(ownerPage);
    await expect(pending).toHaveCount(0);
    const row = memberRow(ownerPage, designer.email);
    await expect(row).toContainText('Дизайнер');
    await row.getByRole('button', { name: 'Доступ к сайтам' }).click();
    const access = ownerPage.getByRole('dialog', { name: 'Доступ к сайтам' });
    await access.getByRole('radio', { name: 'Выбранные сайты' }).check();
    await access.getByRole('checkbox', { name: owner.siteA }).check();
    await access.getByRole('button', { name: 'Сохранить' }).click();
    await expect(access).toBeHidden();
    await expect(row).toContainText('Выбранные сайты: 1');

    await designerPage.goto('/dashboard');
    await expect(
        designerPage.getByRole('heading', { name: owner.siteA }),
    ).toBeVisible();
    await expect(
        designerPage.getByRole('heading', { name: owner.siteB }),
    ).toHaveCount(0);
    await expectStatus(designerPage, siteB, 404);
    await expectStatus(designerPage, `${siteB}/designer`, 404);

    // Designer on Site A: design and content yes; prices and Publish no.
    await editHeroTitle(designerPage, siteA, 'Команда: правка дизайнера');
    await expectStatus(designerPage, `${siteA}/vehicles`, 403);
    await designerPage.goto(`${siteA}/publishing`);
    await expect(
        designerPage.getByText('У вас нет права публиковать этот сайт.'),
    ).toBeVisible();
    expect(await postStatus(designerPage, `${siteA}/publishing`)).toBe(403);

    // Suspended: the next request has no access to the Workspace.
    await openTeam(ownerPage);
    await row.getByRole('button', { name: 'Приостановить' }).click();
    await expect(row).toContainText('Приостановлен');
    await expectStatus(designerPage, siteA, 404);
    await designerPage.goto('/dashboard');
    await expect(designerPage.getByTestId('dashboard-workspace')).toHaveText(
        `Пространство: ${designer.workspace}`,
    );

    // Restored: the Workspace is back in the switcher with the same Site access.
    await row.getByRole('button', { name: 'Восстановить доступ' }).click();
    await expect(row).toContainText('Активен');
    await switchWorkspace(designerPage, designer.workspace, owner.workspace);
    await expect(
        designerPage.getByRole('heading', { name: owner.siteA }),
    ).toBeVisible();

    // Removed: Workspace access disappears.
    await row.getByRole('button', { name: 'Удалить из пространства' }).click();
    await row.getByRole('button', { name: 'Подтвердить удаление' }).click();
    await expect(row).toHaveCount(0);
    await expectStatus(designerPage, siteA, 404);
    await designerPage.goto('/dashboard');
    await expect(designerPage.getByTestId('dashboard-workspace')).toHaveText(
        `Пространство: ${designer.workspace}`,
    );
    await designerPage
        .getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${designer.workspace}`,
        })
        .click();
    await expect(
        designerPage.getByRole('menu').getByRole('menuitem', {
            name: owner.workspace,
        }),
    ).toHaveCount(0);

    await designerPage.context().close();
    await ownerPage.context().close();
});

test('workspace library, site-to-site copy with conflicts and shared assets stay independent', async ({
    browser,
}) => {
    test.setTimeout(150_000);
    const page = await signedIn(browser, owner);
    const siteA = await siteUrl(page, owner.siteA);
    const siteB = await siteUrl(page, owner.siteB);

    // Library: add a Series, put it on Site A, delete the library entry; the Site copy stays.
    await page.goto('/workspace/vehicles');
    await page.getByRole('link', { name: 'Добавить из каталога' }).click();
    await page.getByRole('link', { name: 'Тестмаш', exact: true }).click();
    await page.getByRole('link', { name: 'Т-1', exact: true }).click();
    await page.getByRole('link', { name: 'I', exact: true }).click();
    await page
        .getByRole('button', { name: 'Добавить серию Универсал' })
        .click();
    await expect(page).toHaveURL(/\/workspace\/vehicles\/[0-9a-z]{26}$/i);
    await page
        .getByLabel('Сайт', { exact: true })
        .selectOption({ label: owner.siteA });
    await page.getByRole('button', { name: 'Добавить на сайт' }).click();
    await expect(
        page.getByText(`Автомобиль добавлен на сайт «${owner.siteA}».`).first(),
    ).toBeVisible();
    await page
        .getByRole('button', { name: 'Удалить', exact: true })
        .first()
        .click();
    const remove = page.getByRole('dialog', {
        name: 'Удалить автомобиль из библиотеки?',
    });
    await remove.getByRole('button', { name: 'Удалить' }).click();
    await expect(page).toHaveURL('/workspace/vehicles');
    await page.goto(`${siteA}/vehicles`);
    await expect(
        page.getByRole('link', { name: /Универсал/ }).first(),
    ).toBeVisible();

    // Site A → Site B with offers.
    await openImport(page, siteB, owner.siteA);
    await importVehicle(page, 'Хэтчбек').check();
    await page
        .getByRole('checkbox', {
            name: 'Предложения с ценами, наличием и бейджами',
        })
        .check();
    await submitImport(page, /Скопирован/);
    await openVehicle(page, siteB, 'Хэтчбек');
    await expect(page.getByText(sourcePrice).first()).toBeVisible();

    // The destination gets its own price; a repeated copy is skipped by default.
    await setOfferPrice(page, '1 900 000');
    await expect(page.getByText(protectedPrice).first()).toBeVisible();
    await openImport(page, siteB, owner.siteA);
    const conflict = importVehicle(page, 'Хэтчбек');
    await expect(
        page.getByRole('listitem').filter({ has: conflict }),
    ).toContainText('Уже есть на сайте');
    await conflict.check();
    await expect(page.getByRole('radio', { name: 'Пропустить' })).toBeChecked();
    await expect(
        page.getByText(/здесь 1\s900\s000\s₽, на источнике 2\s000\s000\s₽/),
    ).toBeVisible();
    await submitImport(page, /пропущен/i);

    // Explicit update of the text only keeps the destination price.
    await conflict.check();
    await page.getByRole('radio', { name: 'Обновить выбранное' }).check();
    await page.getByRole('checkbox', { name: /^Название и описание/ }).check();
    await submitImport(page, /Обновлён/);
    await openVehicle(page, siteB, 'Хэтчбек');
    await expect(page.getByText(protectedPrice).first()).toBeVisible();

    // Prices change only when explicitly selected.
    await openImport(page, siteB, owner.siteA);
    await importVehicle(page, 'Хэтчбек').check();
    await page.getByRole('radio', { name: 'Обновить выбранное' }).check();
    await page
        .getByRole('checkbox', { name: /^Цены, наличие и бейджи предложений/ })
        .check();
    await submitImport(page, /Обновлён/);
    await openVehicle(page, siteB, 'Хэтчбек');
    await expect(page.getByText(sourcePrice).first()).toBeVisible();

    // Shared asset: upload, copy to Site A, delete the source; the Site copy keeps working.
    await page.goto('/workspace/assets');
    await page.getByLabel('Файл изображения').setInputFiles({
        name: 'team-logo.png',
        mimeType: 'image/png',
        buffer: png,
    });
    const card = page
        .getByRole('listitem')
        .filter({ hasText: 'team-logo.png' });
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'На сайт' }).click();
    const copy = page.getByRole('dialog', { name: 'Копировать на сайт' });
    await copy
        .getByLabel('Сайт', { exact: true })
        .selectOption({ label: owner.siteA });
    await copy.getByRole('button', { name: 'Копировать' }).click();
    await expect(
        page
            .getByText(`Изображение скопировано на сайт «${owner.siteA}».`)
            .first(),
    ).toBeVisible();
    await card.getByRole('button', { name: 'Удалить team-logo.png' }).click();
    await page
        .getByRole('dialog', { name: 'Удалить изображение?' })
        .getByRole('button', { name: 'Удалить' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'В медиатеке пока нет изображений' }),
    ).toBeVisible();

    await page.goto(`${siteA}/designer`);
    const properties = await selectHero(page);
    await properties
        .getByRole('button', { name: 'Выбрать: Фоновое изображение' })
        .click();
    const library = page.getByRole('dialog', {
        name: 'Библиотека изображений',
    });
    const siteCopy = library.getByRole('button', {
        name: 'Выбрать «team-logo.png»',
    });
    await expect(siteCopy).toBeVisible();
    await expect
        .poll(() =>
            siteCopy
                .locator('img')
                .evaluate((image: HTMLImageElement) => image.naturalWidth),
        )
        .toBe(1);
    await siteCopy.click();
    await expect(library).toBeHidden();
    await expect(properties.getByText('team-logo.png')).toBeVisible();
    await expect(page.getByText('Сохранено', { exact: true })).toBeVisible();

    await page.context().close();
});

test('specialised roles see only what their permissions allow on Site A', async ({
    browser,
}) => {
    test.setTimeout(120_000);

    // Pricing Manager: offer price and benefits; no design edits, no Publish.
    const pricing = await signedIn(browser, users.teamPricing);
    const siteA = await siteUrl(pricing, owner.siteA);
    await openVehicle(pricing, siteA, 'Хэтчбек');
    await setOfferPrice(pricing, '2 100 000');
    await expect(pricing.getByText(/2\s100\s000\s₽/).first()).toBeVisible();
    await pricing
        .getByRole('button', { name: 'Изменить', exact: true })
        .click();
    const offer = pricing.getByRole('dialog', { name: 'Изменить предложение' });
    await expect(
        offer.getByRole('button', { name: 'Добавить выгоду' }),
    ).toBeEnabled();
    await pricing.keyboard.press('Escape');
    await expectHeroReadOnly(pricing, siteA);
    await pricing.goto(`${siteA}/publishing`);
    await expect(
        pricing.getByText('У вас нет права публиковать этот сайт.'),
    ).toBeVisible();
    await pricing.context().close();

    // Publisher: preview and Publish; no design or price edits.
    const publisher = await signedIn(browser, users.teamPublisher);
    await publisher.goto(`${siteA}/publishing`);
    await expect(
        publisher.getByRole('button', { name: 'Опубликовать' }),
    ).toBeVisible();
    await expect(
        publisher.getByRole('link', { name: 'Предпросмотр' }).first(),
    ).toBeVisible();
    await expectHeroReadOnly(publisher, siteA);
    await expectStatus(publisher, `${siteA}/vehicles`, 403);
    await publisher.context().close();

    // Lead Manager: Submissions and delivery metadata; no integrations, no export.
    const leads = await signedIn(browser, users.teamLeads);
    await leads.goto(`${siteA}/submissions`);
    await expect(
        leads.getByRole('heading', { level: 1, name: 'Заявки' }),
    ).toBeVisible();
    await expect(
        leads.getByRole('button', { name: /Экспорт|Выгруз/ }),
    ).toHaveCount(0);
    await expect(
        leads.getByRole('link', { name: /Экспорт|Выгруз/ }),
    ).toHaveCount(0);
    await leads.goto(`${siteA}/deliveries`);
    await expect(
        leads.getByRole('heading', { level: 1, name: 'Доставка заявок' }),
    ).toBeVisible();
    await expectStatus(leads, '/integrations', 403);
    await expectStatus(leads, `${siteA}/integrations`, 403);
    await expectStatus(leads, '/workspace/team', 403);
    await leads.context().close();

    // Integrations Manager: profiles and routes; no Submission contents.
    const integrations = await signedIn(browser, users.teamIntegrations);
    await integrations.goto('/integrations');
    await expect(
        integrations.getByRole('button', { name: 'Добавить подключение' }),
    ).toBeVisible();
    await integrations.goto(`${siteA}/deliveries`);
    await expect(
        integrations.getByRole('heading', {
            level: 1,
            name: 'Доставка заявок',
        }),
    ).toBeVisible();
    await expectStatus(integrations, `${siteA}/submissions`, 403);
    await integrations.context().close();
});

test('publisher publishes with a note; owner restores an older version into the draft only', async ({
    browser,
}) => {
    test.setTimeout(150_000);
    const publisher = await signedIn(browser, users.teamPublisher);
    const siteA = await siteUrl(publisher, owner.siteA);
    const history = publisher.getByTestId('version-history');

    await publisher.goto(`${siteA}/publishing`);
    await publisher
        .getByLabel('Комментарий к публикации')
        .fill('Первый релиз команды');
    await publisher.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        publisher.getByText('Сайт опубликован. Версия 1.').first(),
    ).toBeVisible();
    const v1 = history.getByRole('listitem').filter({ hasText: 'Версия 1' });
    await expect(v1).toContainText(users.teamPublisher.name);
    await expect(v1).toContainText('Первый релиз команды');
    await expect(publisher.getByTestId('draft-state')).toHaveText(
        'Опубликовано',
    );
    await expect(
        publisher.getByRole('button', { name: /^Восстановить версию/ }),
    ).toHaveCount(0);

    const ownerPage = await signedIn(browser, owner);
    await editHeroTitle(ownerPage, siteA, 'Команда: версия 2');

    await publisher.reload();
    await expect(publisher.getByTestId('draft-state')).toHaveText(
        'Есть неопубликованные изменения',
    );
    await publisher.getByLabel('Комментарий к публикации').fill('Второй релиз');
    await publisher.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        publisher.getByText('Сайт опубликован. Версия 2.').first(),
    ).toBeVisible();
    await expect(
        history.getByRole('listitem').filter({ hasText: 'Версия 2' }),
    ).toContainText('Второй релиз');

    const visitor = await (await browser.newContext()).newPage();
    await visitor.goto(owner.publicUrl);
    await expect(
        visitor.getByRole('heading', { name: 'Команда: версия 2' }),
    ).toBeVisible();

    await ownerPage.goto(`${siteA}/publishing`);
    await ownerPage
        .getByRole('button', { name: 'Восстановить версию 1 в черновик' })
        .click();
    await ownerPage
        .getByRole('dialog', { name: 'Восстановить версию 1?' })
        .getByRole('button', { name: 'Восстановить' })
        .click();
    await expect(
        ownerPage.getByText(/Черновик восстановлен из версии 1\./).first(),
    ).toBeVisible();
    await expect(ownerPage.getByTestId('production-status')).toContainText(
        'Версия 2',
    );
    await expect(ownerPage.getByTestId('last-restore')).toContainText(
        'версия 1',
    );
    await expect(
        ownerPage
            .getByTestId('version-history')
            .getByRole('listitem')
            .filter({ hasText: 'Версия 1' }),
    ).toContainText('Восстановлена в черновик: 1');
    await expect(ownerPage.getByTestId('draft-state')).toHaveText(
        'Есть неопубликованные изменения',
    );

    // Production stays on version 2 until the next explicit Publish.
    await visitor.reload();
    await expect(
        visitor.getByRole('heading', { name: 'Команда: версия 2' }),
    ).toBeVisible();

    await visitor.context().close();
    await ownerPage.context().close();
    await publisher.context().close();
});

test('foreign Workspace resources and team actions are not reachable', async ({
    browser,
}) => {
    const page = await signedIn(browser, owner);

    expect(
        (
            await page.request.get(
                `/workspace/vehicles/${foreignTeamIds.vehicle}`,
            )
        ).status(),
    ).toBe(404);
    expect(
        (
            await page.request.get(`/workspace/assets/${foreignTeamIds.asset}`)
        ).status(),
    ).toBe(404);
    expect(
        await postStatus(
            page,
            `/workspace/team/members/${foreignTeamIds.member}/suspend`,
        ),
    ).toBe(404);
    expect(
        await postStatus(
            page,
            `/workspaces/${foreignTeamIds.workspace}/switch`,
        ),
    ).toBe(404);

    await page.context().close();
});

test.describe('team UI at 375px', () => {
    const phone = { width: 375, height: 812 };

    async function expectDialogFits(page: Page, dialog: Locator) {
        await expect(dialog).toBeVisible();
        const box = await dialog.boundingBox();
        expect(box?.x ?? -1).toBeGreaterThanOrEqual(0);
        expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(
            phone.width,
        );
        await expectNoHorizontalOverflow(page);
    }

    test('team, library, assets, copy conflicts and history fit a phone', async ({
        browser,
    }) => {
        test.setTimeout(120_000);
        const page = await signedIn(browser, owner, phone);
        const siteA = await siteUrl(page, owner.siteA);
        const siteB = await siteUrl(page, owner.siteB);

        await openTeam(page);
        await expectNoHorizontalOverflow(page);
        await page.getByRole('button', { name: 'Пригласить' }).click();
        const invite = page.getByRole('dialog', {
            name: 'Пригласить участника',
        });
        await invite.getByLabel('Роль').selectOption({ label: 'Дизайнер' });
        await invite.getByRole('radio', { name: 'Выбранные сайты' }).check();
        await expect(invite.getByRole('list', { name: 'Сайты' })).toBeVisible();
        await expectDialogFits(page, invite);
        await invite.getByRole('button', { name: 'Отмена' }).click();

        const pricingRow = memberRow(page, users.teamPricing.email);
        await expect(
            pricingRow.getByRole('button', { name: 'Приостановить' }),
        ).toBeVisible();
        await pricingRow
            .getByRole('button', { name: 'Доступ к сайтам' })
            .click();
        const access = page.getByRole('dialog', { name: 'Доступ к сайтам' });
        await access.getByRole('radio', { name: 'Выбранные сайты' }).check();
        await expect(
            access.getByRole('checkbox', { name: owner.siteA }),
        ).toBeVisible();
        await expectDialogFits(page, access);
        await access.getByRole('button', { name: 'Отмена' }).click();

        for (const url of [
            '/workspace/vehicles',
            '/workspace/vehicles/create',
            '/workspace/assets',
        ]) {
            await page.goto(url);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            await expectNoHorizontalOverflow(page);
        }

        await openImport(page, siteB, owner.siteA);
        await importVehicle(page, 'Хэтчбек').check();
        await page.getByRole('radio', { name: 'Обновить выбранное' }).check();
        await expect(
            page.getByRole('checkbox', {
                name: /^Цены, наличие и бейджи предложений/,
            }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await page.goto(`${siteA}/publishing`);
        await expect(page.getByTestId('version-history')).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await page.context().close();
    });
});
