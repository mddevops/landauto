import type { Page, TestInfo } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
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
    category: string,
    submit: string,
) {
    await page.getByLabel('Название').fill(name);
    await page.getByLabel('Категория').selectOption({ label: category });
    await page.getByLabel('Slug').fill(slug);
    await expectNoHorizontalOverflow(page);
    const button = page.getByRole('button', { name: submit, exact: true });
    await button.scrollIntoViewIfNeeded();
    await expect(button).toBeInViewport();
    await button.click();
}

/** Waits for the debounced autosave of the latest edit to finish. */
async function expectDraftSaved(page: Page) {
    const status = page.locator('[data-status]');
    await expect(status).not.toHaveAttribute('data-status', 'saved');
    await expect(status).toHaveAttribute('data-status', 'saved');
}

async function openMode(page: Page, mode: string) {
    await page.getByRole('tab', { name: mode, exact: true }).click();
}

async function expectStudio(
    page: Page,
    name: string,
    slug: string,
    category: string,
) {
    await expect(page).toHaveTitle(/Студия блоков/);
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    await expect(page.getByText(category, { exact: true })).toBeVisible();
    await expect(page.getByText('Нет версий', { exact: true })).toBeVisible();
    await expect(page.locator('[data-status]')).toHaveText('Черновик сохранён');

    await openMode(page, 'Настройки');
    const slugInput = page.getByLabel('Slug');
    await expect(slugInput).toHaveValue(slug);
    await expect(slugInput).not.toBeEditable();
}

async function renameBlock(page: Page, name: string, category: string) {
    const metadata = page.getByRole('region', { name: 'Основные данные' });
    await metadata.getByLabel('Название').fill(name);
    await metadata.getByLabel('Категория').selectOption({ label: category });
    await metadata.getByRole('button', { name: 'Сохранить' }).click();
    await expect(page.getByText('Изменения сохранены.')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    await openMode(page, 'Настройки');
    await expect(metadata.getByLabel('Название')).toHaveValue(name);
    await expect(metadata.getByLabel('Категория')).toHaveValue('footer');
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
        page.getByRole('heading', { level: 1, name: 'Студия' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.getByRole('link', { name: /^Блоки/ }).click();

    await expect(page).toHaveURL('/developer/blocks');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Блоки' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);
    await page.getByRole('link', { name: /^Создать (первый )?блок$/ }).click();

    await expect(page).toHaveURL('/developer/blocks/create');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Новый блок' }),
    ).toBeVisible();
    await createBlock(page, name, slug, 'Меню', 'Создать блок');

    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);
    await expect(page.getByText(`Блок «${name}» создан.`)).toBeVisible();
    await expectStudio(page, name, slug, 'Меню');

    const ownership = page.getByRole('region', { name: 'Владение' });
    await expect(
        ownership.getByText(users.developer.profile, { exact: true }),
    ).toBeVisible();
    await expect(
        ownership.getByText('Разработчик', { exact: true }),
    ).toBeVisible();
    await expect(ownership.getByText('0', { exact: true })).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await renameBlock(page, renamed, 'Подвал');
    await expectNoHorizontalOverflow(page);

    await page.goto('/developer/blocks');
    const listed = page
        .getByTestId('authoring-block')
        .filter({ hasText: renamed });
    await expect(listed).toContainText(slug);
    await expect(listed).toContainText('Подвал');
    await expectNoHorizontalOverflow(page);

    // Developer authoring never grants Platform Block authoring.
    browserIssues.expectFailedResponse(403, '/platform/blocks');
    expect((await page.goto('/platform/blocks'))?.status()).toBe(403);
});

test('developer writes code and builds the schema in the Block Studio @responsive', async ({
    page,
}, testInfo) => {
    const suffix = uniqueSuffix(testInfo);
    const name = `E2E студия ${suffix}`;

    await login(page, users.studioDeveloper);
    await page.goto('/developer/blocks/create');
    await createBlock(
        page,
        name,
        `e2e-studio-${suffix}`,
        'Первый экран',
        'Создать блок',
    );
    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);

    const checks = page.getByRole('complementary', { name: 'Проверка схемы' });
    await expect(checks).toContainText('Ошибок в схеме нет.');

    // Code mode: the starter Draft is editable per file and autosaves.
    const html = page.getByLabel('index.html', { exact: true });
    await expect(html).toHaveValue(/\{\{ title \}\}/);
    await html.fill(
        '<section class="promo">\n  <h2>{{ title }}</h2>\n</section>',
    );
    await expectDraftSaved(page);

    await page.getByRole('button', { name: 'styles.css' }).click();
    await page
        .getByLabel('styles.css', { exact: true })
        .fill('.promo { padding: 32px; }');
    await expectDraftSaved(page);
    await expectNoHorizontalOverflow(page);

    // Invalid JSON is saved as work in progress and reported, not rejected.
    await page.getByRole('button', { name: 'schema.json' }).click();
    const schema = page.getByLabel('schema.json', { exact: true });
    await schema.fill('{"fields": [');
    await expectDraftSaved(page);
    await expect(checks).toContainText(
        'schema.json сейчас не является корректным JSON',
    );

    await openMode(page, 'Конструктор схемы');
    await expect(
        page.getByText('Конструктор открывается только для корректного JSON'),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Открыть schema.json' }).click();
    await schema.fill(
        '{"fields":[{"key":"title","type":"text","label":"Заголовок"}]}',
    );
    await expectDraftSaved(page);
    await expect(checks).toContainText('Ошибок в схеме нет.');

    // Schema Builder edits the same canonical JSON.
    await openMode(page, 'Конструктор схемы');
    await expect(page.getByTestId('schema-field')).toHaveCount(1);
    await page.getByLabel('Тип нового поля').selectOption({ label: 'Число' });
    await page.getByRole('button', { name: 'Добавить поле' }).click();
    const numberField = page.getByTestId('schema-field').nth(1);
    await numberField.getByRole('button', { name: /^Новое поле/ }).click();
    await numberField.getByLabel('Ключ').fill('columns');
    await numberField.getByLabel('Подпись').fill('Колонки');
    await numberField.getByLabel('Минимум', { exact: true }).fill('5');
    await numberField.getByLabel('Максимум', { exact: true }).fill('1');
    await expectDraftSaved(page);
    await expect(checks).toContainText('fields.1.min');
    await expect(checks).toContainText('Минимум не может превышать максимум.');

    await numberField.getByLabel('Минимум', { exact: true }).fill('1');
    await numberField.getByLabel('Максимум', { exact: true }).fill('4');
    await expectDraftSaved(page);
    await expect(checks).toContainText('Ошибок в схеме нет.');

    await numberField
        .getByRole('button', { name: 'Переместить поле «Колонки» выше' })
        .click();
    await expect(page.getByTestId('schema-field').first()).toContainText(
        'Колонки',
    );
    await expectDraftSaved(page);
    await expectNoHorizontalOverflow(page);

    // Reload restores the saved Draft.
    await page.reload();
    await expect(page.getByLabel('index.html', { exact: true })).toHaveValue(
        /class="promo"/,
    );
    await page.getByRole('button', { name: 'schema.json' }).click();
    const saved = JSON.parse(
        await page.getByLabel('schema.json', { exact: true }).inputValue(),
    ) as { fields: { key: string; type: string; min?: number }[] };
    expect(saved.fields.map((field) => field.key)).toEqual([
        'columns',
        'title',
    ]);
    expect(saved.fields[0]).toMatchObject({ type: 'number', min: 1, max: 4 });
    await expect(page.getByText('Нет версий', { exact: true })).toBeVisible();
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
    await createBlock(page, name, slug, 'Контакты', 'Создать официальный блок');

    await expect(page).toHaveURL(/\/platform\/blocks\/[0-9a-z]{26}$/i);
    await expect(
        page.getByText(`Официальный блок «${name}» создан.`),
    ).toBeVisible();
    await expectStudio(page, name, slug, 'Контакты');

    const ownership = page.getByRole('region', { name: 'Владение' });
    await expect(
        ownership.getByText('Landflow', { exact: true }),
    ).toBeVisible();
    await expect(
        ownership.getByText('Платформа Landflow', { exact: true }),
    ).toBeVisible();
    await expect(ownership.getByText('0', { exact: true })).toBeVisible();

    await renameBlock(page, renamed, 'Подвал');

    await page.goto('/platform/blocks');
    await expect(
        page.getByTestId('authoring-block').filter({ hasText: renamed }),
    ).toContainText(slug);
});
