import type { Page, TestInfo } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow, isMobileViewport } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

type Account = { email: string; password: string };

async function login(page: Page, account: Account) {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(account.email);
    await page.getByLabel('Пароль', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');
}

/** Block slugs are globally unique, so every run and project creates its own Block. */
function uniqueSuffix(testInfo: TestInfo): string {
    return `${Date.now().toString(36)}-${testInfo.project.name}`;
}

async function createBlock(
    page: Page,
    name: string,
    slug: string,
    submit: string,
) {
    await page.getByLabel('Название').fill(name);
    await page.getByLabel('Slug').fill(slug);
    await expectNoHorizontalOverflow(page);
    const button = page.getByRole('button', { name: submit, exact: true });
    await button.scrollIntoViewIfNeeded();
    await expect(button).toBeInViewport();
    await button.click();
}

async function expectEditor(page: Page, name: string, slug: string) {
    await expect(page).toHaveTitle(/Редактор блока/);
    await expect(
        page.getByText('Редактор блока', { exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    await expect(page.getByText('Нет версий', { exact: true })).toBeVisible();

    const slugInput = page.getByLabel('Slug');
    await expect(slugInput).toHaveValue(slug);
    await expect(slugInput).not.toBeEditable();
}

async function renameBlock(page: Page, name: string) {
    const metadata = page.getByRole('region', { name: 'Основные данные' });
    await metadata.getByLabel('Название').fill(name);
    await metadata.getByRole('button', { name: 'Сохранить' }).click();
    await expect(page.getByText('Изменения сохранены.')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    await expect(metadata.getByLabel('Название')).toHaveValue(name);
}

test('developer creates and renames an own Block @responsive', async ({
    page,
    browserIssues,
}, testInfo) => {
    const suffix = uniqueSuffix(testInfo);
    const name = `E2E блок ${suffix}`;
    const renamed = `E2E блок обновлён ${suffix}`;
    const slug = `e2e-block-${suffix}`;

    await login(page, users.developer);

    await page.goto('/developer');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Панель разработчика' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.getByRole('link', { name: 'Мои блоки' }).click();

    await expect(page).toHaveURL('/developer/blocks');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Мои блоки' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.getByRole('link', { name: /^Создать (первый )?блок$/ }).click();

    await expect(page).toHaveURL('/developer/blocks/create');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Новый блок' }),
    ).toBeVisible();
    await createBlock(page, name, slug, 'Создать блок');

    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);
    await expect(page.getByText(`Блок «${name}» создан.`)).toBeVisible();
    await expectEditor(page, name, slug);

    const ownership = page.getByRole('region', { name: 'Владение' });
    await expect(
        ownership.getByText(users.developer.profile, { exact: true }),
    ).toBeVisible();
    await expect(
        ownership.getByText('Разработчик', { exact: true }),
    ).toBeVisible();
    await expect(ownership.getByText('0', { exact: true })).toBeVisible();

    const roadmap = ['Схема', 'Предпросмотр', 'Версии'].map((title) =>
        page.getByRole('region', { name: title, exact: true }),
    );

    for (const card of roadmap) {
        await expect(card).toBeVisible();
    }

    if (isMobileViewport(page)) {
        const boxes = await Promise.all(
            roadmap.map((card) => card.boundingBox()),
        );
        const [first, second, third] = boxes.map((box) => box!);
        expect(second.y).toBeGreaterThanOrEqual(first.y + first.height);
        expect(third.y).toBeGreaterThanOrEqual(second.y + second.height);
        expect(second.x).toBe(first.x);
    }

    await expectNoHorizontalOverflow(page);

    await renameBlock(page, renamed);
    await expectNoHorizontalOverflow(page);

    await page.goto('/developer/blocks');
    await expect(
        page.getByTestId('authoring-block').filter({ hasText: renamed }),
    ).toContainText(slug);
    await expectNoHorizontalOverflow(page);

    // Developer authoring never grants Platform Block authoring.
    browserIssues.expectFailedResponse(403, '/platform/blocks');
    expect((await page.goto('/platform/blocks'))?.status()).toBe(403);
});

test('super admin creates and renames a Platform Block', async ({
    page,
}, testInfo) => {
    const suffix = uniqueSuffix(testInfo);
    const name = `E2E официальный блок ${suffix}`;
    const renamed = `E2E официальный блок обновлён ${suffix}`;
    const slug = `e2e-platform-block-${suffix}`;

    await login(page, users.catalogAdmin);

    await page.goto('/platform/blocks');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Блоки Landflow' }),
    ).toBeVisible();
    await expect(
        page.getByTestId('authoring-block').filter({ hasText: 'Первый экран' }),
    ).toContainText('hero');
    await page.getByRole('link', { name: 'Создать официальный блок' }).click();

    await expect(page).toHaveURL('/platform/blocks/create');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Новый официальный блок' }),
    ).toBeVisible();
    await createBlock(page, name, slug, 'Создать официальный блок');

    await expect(page).toHaveURL(/\/platform\/blocks\/[0-9a-z]{26}$/i);
    await expect(
        page.getByText(`Официальный блок «${name}» создан.`),
    ).toBeVisible();
    await expectEditor(page, name, slug);

    const ownership = page.getByRole('region', { name: 'Владение' });
    await expect(
        ownership.getByText('Landflow', { exact: true }),
    ).toBeVisible();
    await expect(
        ownership.getByText('Платформа Landflow', { exact: true }),
    ).toBeVisible();
    await expect(ownership.getByText('0', { exact: true })).toBeVisible();

    await renameBlock(page, renamed);

    await page.goto('/platform/blocks');
    await expect(
        page.getByTestId('authoring-block').filter({ hasText: renamed }),
    ).toContainText(slug);
});
