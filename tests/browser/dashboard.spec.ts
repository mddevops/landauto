import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow, isMobileViewport } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { users } from './support/users';

test(
    'dashboard renders the Russian app shell',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/dashboard');

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle('Панель управления - Landflow');
        await expect(
            page
                .getByRole('navigation', { name: 'Навигационная цепочка' })
                .getByText('Панель управления'),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'dashboard', 'overview');

        const sidebarToggle = page.locator('header').getByRole('button', {
            name: 'Показать или скрыть боковую панель',
        });
        // The breadcrumb's current page also has role "link", so scope to the sidebar.
        const sidebarNavLink = page
            .locator('[data-slot="sidebar"]')
            .getByRole('link', { name: 'Панель управления' });

        if (isMobileViewport(page)) {
            // Mobile: the sidebar is a sheet opened from the header.
            await expect(sidebarNavLink).toBeHidden();

            await sidebarToggle.click();

            const sidebar = page.getByRole('dialog');
            await expect(sidebarNavLink).toBeVisible();
            await expect(sidebar.getByText(users.member.name)).toBeVisible();
            await expectNoHorizontalOverflow(page);

            await captureScreenshot(
                page,
                testInfo,
                'dashboard',
                'sidebar-open',
                {
                    fullPage: false,
                },
            );

            const overlay = page.locator('[data-slot="sheet-overlay"]');
            const overlayBox = await overlay.boundingBox();
            expect(overlayBox).not.toBeNull();
            await page.mouse.click(
                overlayBox!.x + overlayBox!.width - 2,
                overlayBox!.y + overlayBox!.height - 2,
            );
            await expect(sidebar).toBeHidden();
        } else {
            // Tablet/desktop: the sidebar is always rendered and can collapse to icons.
            await expect(sidebarNavLink).toBeVisible();
            await expect(page.getByText(users.member.name)).toBeVisible();
        }
    },
);

test('user menu shows the account and opens settings', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('button', { name: users.member.name }).click();

    const menu = page.getByRole('menu');
    await expect(menu.getByText(users.member.email)).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'Выйти' })).toBeVisible();

    await menu.getByRole('menuitem', { name: 'Настройки' }).click();

    await expect(page).toHaveURL('/settings/profile');
    await expect(
        page.getByRole('heading', { name: 'Настройки', exact: true }),
    ).toBeVisible();
});

test('workspace switcher lists accessible workspaces and changes context', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const switcher = page.getByRole('button', {
        name: `Сменить рабочее пространство. Текущее: ${users.member.workspaces[0]}`,
    });
    await expect(switcher).toBeVisible();
    await switcher.click();

    const menu = page.getByRole('menu');
    await expect(
        menu.getByRole('menuitem', { name: /Личный автопарк/ }),
    ).toBeDisabled();
    await expect(
        menu.getByRole('menuitem', { name: users.member.workspaces[1] }),
    ).toBeVisible();
    await expect(menu.getByText('Недоступный Workspace')).toHaveCount(0);

    const switchRequest = page.waitForRequest(
        (request) =>
            request.method() === 'POST' &&
            /\/workspaces\/[0-9A-HJKMNP-TV-Z]{26}\/switch$/i.test(
                new URL(request.url()).pathname,
            ),
    );

    await menu
        .getByRole('menuitem', { name: users.member.workspaces[1] })
        .click();

    const request = await switchRequest;
    expect(request.url()).not.toMatch(/\/workspaces\/\d+\/switch$/);
    await expect(
        page.getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${users.member.workspaces[1]}`,
        }),
    ).toBeVisible();

    await page.reload();
    await expect(
        page.getByRole('button', {
            name: `Сменить рабочее пространство. Текущее: ${users.member.workspaces[1]}`,
        }),
    ).toBeVisible();
});

test('single workspace is shown without a switch menu', async ({ page }) => {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(users.login.email);
    await page.getByLabel('Пароль', { exact: true }).fill(users.login.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();

    await expect(page).toHaveURL('/dashboard');
    await expect(
        page.getByLabel(
            `Текущее рабочее пространство: ${users.login.workspace}`,
        ),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: /Сменить рабочее пространство/ }),
    ).toHaveCount(0);
});

test('collapsed sidebar state survives a reload on desktop', async ({
    page,
}) => {
    await page.goto('/dashboard');

    const toggle = page
        .locator('header')
        .getByRole('button', { name: 'Показать или скрыть боковую панель' });
    // Collapse is a visual state without text change; the sidebar exposes it via data-state.
    const sidebar = page.locator('[data-slot="sidebar"][data-state]');

    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
    await toggle.click();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');

    await page.reload();
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');

    await toggle.click();
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
});
