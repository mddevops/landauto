import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const owner = users.native;

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(owner.email);
    await page.getByLabel('Пароль', { exact: true }).fill(owner.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

async function openPublishing(page: Page, site: string) {
    await page.goto('/dashboard');
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${site}»` })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    await page.getByRole('link', { name: 'Публикация' }).click();
}

test('Native Blocks publish as host DOM with scoped CSS, hydrate cleanly and run their actions', async ({
    page,
}) => {
    test.setTimeout(120_000);

    await login(page);

    // Designer (application origin): authored Native source renders only in the sandbox frame.
    await page
        .getByRole('link', { name: `Открыть дизайнер сайта «${owner.site}»` })
        .click();
    await expect(page).toHaveURL(/\/designer$/);
    await expect(
        page.locator('iframe[title="Нативный герой"]'),
    ).toHaveAttribute('sandbox', 'allow-scripts');
    await expect(page.locator('[data-landflow-native]')).toHaveCount(0);

    await page.getByRole('link', { name: 'Публикация' }).click();
    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText(/Сайт опубликован\. Версия \d+\./).first(),
    ).toBeVisible();

    const visitor = await page.context().newPage();
    const stylesheet = visitor.waitForResponse((candidate) =>
        candidate.url().includes('/_landflow/runtime/'),
    );
    const response = await visitor.goto(owner.publicUrl);
    expect(response?.status()).toBe(200);

    // Initial HTML from the server already contains the compiled Native output; no iframe.
    const html = (await response?.text()) ?? '';
    expect(html).toMatch(
        /<div data-landflow-native="e2e-native-hero--1-0-0--[0-9a-f]{10}" data-landflow-block="e2e-native-hero" data-landflow-instance="[0-9a-z]{26}"><section class="card"><h2>Нативный герой<\/h2>/,
    );
    expect(html).toContain(
        '<button type="button" data-landflow-action="cta">Оставить заявку</button>',
    );
    expect(html).toMatch(
        /<link rel="stylesheet" href="\/_landflow\/runtime\/[0-9a-z]{26}\/[0-9a-f]{64}\.css">/,
    );
    expect(html).not.toContain('<iframe title="Нативный герой"');

    const css = await stylesheet;
    expect(css.status()).toBe(200);
    expect(css.headers()['content-type']).toContain('text/css');
    expect(css.headers()['cache-control']).toContain('immutable');

    // Hydration keeps exactly one root per Instance and never wraps it in a frame.
    const hero = visitor.locator('[data-landflow-block="e2e-native-hero"]');
    const offer = visitor.locator('[data-landflow-block="e2e-native-offer"]');
    await expect(hero).toHaveCount(1);
    await expect(offer).toHaveCount(1);
    await expect(
        hero.getByRole('heading', { name: 'Нативный герой' }),
    ).toBeVisible();
    await expect(visitor.locator('iframe[title="Нативный герой"]')).toHaveCount(
        0,
    );
    await expect(
        visitor
            .frameLocator('iframe[title="Промо из студии"]')
            .getByRole('heading', { name: 'Старый блок студии' }),
    ).toBeVisible();

    // CSS isolation: both versions style `.card` and `h2`, each only inside its own root.
    await expect(hero.locator('.card')).toHaveCSS(
        'background-color',
        'rgb(254, 243, 199)',
    );
    await expect(hero.locator('h2')).toHaveCSS('color', 'rgb(185, 28, 28)');
    await expect(hero.locator('.card')).toHaveCSS('padding-top', '32px');
    await expect(offer.locator('.card')).toHaveCSS(
        'background-color',
        'rgb(219, 234, 254)',
    );
    await expect(offer.locator('h2')).toHaveCSS('color', 'rgb(29, 78, 216)');
    await expect(offer.locator('.card')).toHaveCSS('padding-top', '8px');
    expect(
        await hero
            .locator('.card')
            .evaluate((element) => getComputedStyle(element).animationName),
    ).toMatch(/^lf-[0-9a-f]{10}-fade$/);
    // `:root { --hero-accent }` stays local to the Native root.
    expect(
        await visitor.evaluate(() =>
            getComputedStyle(document.documentElement)
                .getPropertyValue('--hero-accent')
                .trim(),
        ),
    ).toBe('');

    // Delegated actions resolve against this Instance: scroll to the offer, open the Site Popup.
    await hero.getByRole('button', { name: 'К предложению' }).click();
    await expect(visitor).toHaveURL(/#block-[0-9a-z]{26}$/);
    await hero.getByRole('button', { name: 'Оставить заявку' }).click();
    await expect(
        visitor.getByRole('dialog', { name: 'Перезвоним за 5 минут' }),
    ).toBeVisible();
});

test('a Native Block with JavaScript cannot be published and production stays unpublished', async ({
    page,
    browserIssues,
}) => {
    await login(page);
    await openPublishing(page, owner.blockedSite);

    await expect(page.getByTestId('publish-errors')).toContainText(
        'Нативный JavaScript этого блока ещё не одобрен. Опубликуйте версию после внедрения доверенного JS runtime.',
    );
    await expect(
        page.getByRole('button', { name: 'Опубликовать' }),
    ).toBeDisabled();

    browserIssues.expectFailedResponse(404, 'native-js-e2e.localhost');
    const visitor = await page.context().newPage();
    const response = await visitor.goto(owner.blockedPublicUrl);
    expect(response?.status()).toBe(404);
});
