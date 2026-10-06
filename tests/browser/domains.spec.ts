import { execFileSync } from 'node:child_process';
import type { Browser, Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow, isMobileViewport } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.domains;
const hostname = 'dealer.e2e.test';
const customUrl = `http://${hostname}:8200`;

/**
 * Runs an artisan command against the isolated E2E environment. DNS answers come from the fake
 * resolver and certificates from the fake provisioner (both honoured only in testing/e2e).
 */
function artisan(...args: string[]) {
    execFileSync('php', ['artisan', ...args, '--no-interaction'], {
        env: { ...process.env, APP_ENV: 'e2e' },
        stdio: 'pipe',
    });
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
    const page = await (await browser.newContext()).newPage();
    await login(page, email, password);

    return page;
}

async function openSiteSection(page: Page, section: string) {
    if (isMobileViewport(page)) {
        await page
            .locator('header')
            .getByRole('button', { name: 'Показать или скрыть боковую панель' })
            .click();
    }

    await page
        .getByRole('navigation', { name: `Разделы сайта «${owner.site}»` })
        .getByRole('link', { name: section, exact: true })
        .click();
}

async function openSite(page: Page) {
    await page
        .getByRole('link', { name: `Открыть сайт «${owner.site}»` })
        .click();
    await expect(page.getByTestId('site-overview')).toBeVisible();
}

test('owner connects a custom domain, gets SSL, makes it primary and publishes', async ({
    page,
    browser,
}) => {
    await login(page, owner.email, owner.password);
    await openSite(page);
    await openSiteSection(page, 'Домены');

    await expect(
        page.getByRole('heading', { level: 1, name: 'Домены' }),
    ).toBeVisible();
    await expect(page.getByTestId('landflow-address')).toHaveText(
        `${owner.publicUrl}/`,
    );
    await expect(page.getByTestId('no-domains')).toBeVisible();

    // Normalized on the server: case and the trailing dot do not matter.
    await page.getByLabel('Домен', { exact: true }).fill('Dealer.E2E.test.');
    await page.getByRole('button', { name: 'Добавить домен' }).click();
    await expect(
        page.getByText(
            'Домен добавлен. Настройте DNS-записи и запустите проверку.',
        ),
    ).toBeVisible();

    const card = page.getByTestId('site-domain').filter({ hasText: hostname });
    await expect(card.getByTestId('domain-state')).toHaveText(
        'Ожидает проверки DNS',
    );
    await expect(
        card.getByText(`_landflow-verification.${hostname}`, { exact: true }),
    ).toBeVisible();
    await expect(
        card.getByText('domains.landflow.test', { exact: true }),
    ).toBeVisible();
    const txt = (
        await card
            .locator('code', { hasText: 'landflow-site-verification=' })
            .textContent()
    )?.trim();
    expect(txt).toMatch(/^landflow-site-verification=[a-z0-9]{40}$/);

    // Nothing is configured yet: the check reports the missing TXT record.
    await card
        .getByRole('button', { name: `Проверить DNS домена ${hostname}` })
        .click();
    await expect(
        card.getByText('TXT-запись для подтверждения не найдена', {
            exact: false,
        }),
    ).toBeVisible();

    artisan(
        'domains:fake-dns',
        `_landflow-verification.${hostname}`,
        `--txt=${txt}`,
    );
    artisan('domains:fake-dns', hostname, '--a=203.0.113.10');
    await card
        .getByRole('button', { name: `Проверить DNS домена ${hostname}` })
        .click();
    await expect(
        page.getByText('DNS настроен верно. Выпускаем SSL-сертификат.'),
    ).toBeVisible();
    await expect(card.getByTestId('domain-state')).toHaveText(
        'Выпуск сертификата',
    );

    artisan('queue:work', '--stop-when-empty', '--tries=1', '--sleep=0');
    await page.reload();
    await expect(card.getByTestId('domain-state')).toHaveText('Активен');
    await expect(
        card.getByText('Сертификат действует до', { exact: false }),
    ).toBeVisible();

    await card
        .getByRole('button', { name: `Сделать основным домен ${hostname}` })
        .click();
    await expect(
        page.getByText(`Основной адрес сайта — ${hostname}.`),
    ).toBeVisible();
    await expect(page.getByTestId('primary-address')).toHaveText(
        `Основной адрес: ${customUrl}/`,
    );

    await openSiteSection(page, 'Публикация');
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText('Сайт опубликован. Версия 1.').first(),
    ).toBeVisible();

    // Visitors on the Landflow address land on the primary domain with path and query kept.
    const visitor = await (await browser.newContext()).newPage();
    const response = await visitor.goto(`${owner.publicUrl}/?utm_source=e2e`);
    await expect(visitor).toHaveURL(`${customUrl}/?utm_source=e2e`);
    expect(
        (await response?.request().redirectedFrom()?.response())?.status(),
    ).toBe(301);
    expect(response?.status()).toBe(200);
    await expect(
        visitor.getByRole('heading', { name: 'Свой домен: главная' }),
    ).toBeVisible();
    await expect(visitor.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        `${customUrl}/`,
    );

    // Branding follows the live `remove_branding` entitlement, without republishing.
    await expect(visitor.getByTestId('landflow-branding')).toHaveText(
        'Создано на Landflow',
    );
    artisan(
        'tinker',
        "--execute=App\\Models\\Plan::query()->where('key', 'e2e-domains')->firstOrFail()->setEntitlement(App\\Enums\\Entitlement::RemoveBranding, true);",
    );
    await visitor.reload();
    await expect(
        visitor.getByRole('heading', { name: 'Свой домен: главная' }),
    ).toBeVisible();
    await expect(visitor.getByTestId('landflow-branding')).toHaveCount(0);

    await visitor.goto(`${customUrl}/sitemap.xml`);
    const sitemap = await visitor.content();
    expect(sitemap).toContain(`${customUrl}/`);
    expect(sitemap).not.toContain('domains-e2e.localhost');

    await visitor.goto(`${customUrl}/robots.txt`);
    await expect(visitor.locator('body')).toContainText(
        `Sitemap: ${customUrl}/sitemap.xml`,
    );

    // Application routes are never reachable through a customer host.
    const dashboard = await visitor.goto(`${customUrl}/dashboard`);
    expect(dashboard?.status()).toBe(404);
    await visitor.context().close();
});

test('owner edits page SEO in the Site SEO section', async ({ browser }) => {
    const page = await signedIn(browser, owner.email, owner.password);

    await openSite(page);
    await openSiteSection(page, 'SEO');
    await expect(
        page.getByRole('heading', { level: 1, name: 'SEO' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `${customUrl}/`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: `${customUrl}/sitemap.xml` }),
    ).toBeVisible();

    const home = page.getByTestId('seo-page').filter({ hasText: 'Главная' });
    await home
        .getByLabel('Заголовок для поисковиков')
        .fill('Автосалон Домен — новые автомобили');
    await home
        .getByRole('button', { name: 'Сохранить SEO страницы «Главная»' })
        .click();
    await expect(page.getByText('SEO страницы сохранено.')).toBeVisible();
    await expect(page).toHaveURL(/\/seo$/);
    await expect(home.getByLabel('Заголовок для поисковиков')).toHaveValue(
        'Автосалон Домен — новые автомобили',
    );
    await page.context().close();
});

test('designer cannot manage domains @responsive', async ({ browser }) => {
    const designer = await signedIn(
        browser,
        users.domainsDesigner.email,
        users.domainsDesigner.password,
    );

    await openSite(designer);

    if (isMobileViewport(designer)) {
        await designer
            .locator('header')
            .getByRole('button', { name: 'Показать или скрыть боковую панель' })
            .click();
    }

    const nav = designer.getByRole('navigation', {
        name: `Разделы сайта «${owner.site}»`,
    });
    await expect(nav.getByRole('link', { name: 'Дизайнер' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Домены' })).toHaveCount(0);

    const siteUrl = designer.url();
    const forbidden = await designer.goto(`${siteUrl}/domains`);
    expect(forbidden?.status()).toBe(403);
    await designer.context().close();
});

test('domains page fits a phone screen @responsive', async ({ browser }) => {
    const page = await signedIn(browser, owner.email, owner.password);

    await openSite(page);
    await openSiteSection(page, 'Домены');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Домены' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.context().close();
});
