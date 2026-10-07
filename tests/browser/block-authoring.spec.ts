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

/** Authored code probing the sandbox boundary; each probe reports whether it got through. */
const SANDBOX_PROBE = `
var results = [];
function probe(name, run) {
    try { run(); results.push(name + ': доступно'); } catch (error) { results.push(name + ': заблокировано'); }
}
probe('cookie', function () { return document.cookie; });
probe('storage', function () { return window.localStorage.length; });
probe('parent', function () { return window.parent.document.title; });
probe('top', function () { window.top.location.href = '/dashboard'; });
fetch('/dashboard').then(
    function () { results.push('fetch: доступно'); },
    function () { results.push('fetch: заблокировано'); }
).then(function () {
    var output = document.createElement('p');
    output.setAttribute('data-probe', '');
    output.textContent = results.join('; ');
    landflow.root.appendChild(output);
});
throw new Error('Проверка ошибки выполнения');
`;

test('developer writes code and builds the schema in the Block Studio @responsive', async ({
    page,
    browserIssues,
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

    const checks = page.getByRole('complementary', { name: 'Проверки' });
    await expect(checks).toContainText('Ошибок в схеме нет.');
    await expect(checks).toContainText('Ошибок в шаблоне нет.');

    // Code mode: the starter Draft is editable per file and autosaves.
    const html = page.getByLabel('index.html', { exact: true });
    await expect(html).toHaveValue(/\{\{ title \}\}/);
    await html.fill(
        '<section class="promo">\n  <h2>{{ title }}</h2>\n  <p>{{ subtitle }}</p>\n</section>',
    );
    await expectDraftSaved(page);
    await expect(checks).toContainText(
        'Строка 3Поле «subtitle» не описано в схеме.',
    );
    await html.fill(
        '<section class="promo">\n  <h2>{{ title }}</h2>\n</section>',
    );
    await expectDraftSaved(page);
    await expect(checks).toContainText('Ошибок в шаблоне нет.');

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

    // Live preview renders the Draft in the opaque-origin sandbox.
    await page
        .getByLabel('schema.json', { exact: true })
        .fill(
            '{"fields":[{"key":"title","type":"text","label":"Заголовок"},{"key":"cta","type":"action","label":"Кнопка"}]}',
        );
    await expectDraftSaved(page);
    await page.getByRole('button', { name: 'index.html' }).click();
    await page
        .getByLabel('index.html', { exact: true })
        .fill(
            '<section class="promo">\n  <h2>{{ title }}</h2>\n  <button data-landflow-action="cta">Подробнее</button>\n</section>',
        );
    await expectDraftSaved(page);
    await expect(checks).toContainText('Ошибок в шаблоне нет.');

    await openMode(page, 'Предпросмотр');
    const iframe = page.getByTitle('Предпросмотр блока');
    await expect(iframe).toHaveAttribute('sandbox', 'allow-scripts');
    const frame = page.frameLocator('iframe[title="Предпросмотр блока"]');
    const previewData = page.getByRole('region', {
        name: 'Данные предпросмотра',
    });
    await previewData.getByLabel('Заголовок').fill('Летняя распродажа');
    await expect(frame.getByRole('heading', { level: 2 })).toHaveText(
        'Летняя распродажа',
    );
    await expectDraftSaved(page);

    await frame.getByRole('button', { name: 'Подробнее' }).click();
    await expect(
        page.getByText(
            'Вызвано действие «cta». В предпросмотре действия не выполняются.',
        ),
    ).toBeVisible();

    const viewport = page.getByTestId('preview-viewport');
    await page.getByRole('button', { name: 'Телефон' }).click();
    await expect(viewport).toHaveCSS('width', '375px');
    await expectNoHorizontalOverflow(page);
    await page.getByRole('button', { name: 'Компьютер' }).click();

    // Authored JS cannot reach cookies, storage, the parent, top navigation or the network.
    browserIssues.expectConsoleError('Проверка ошибки выполнения');
    browserIssues.expectConsoleError('allow-top-navigation');
    browserIssues.expectConsoleError('Content Security Policy');
    await openMode(page, 'Код');
    await page.getByRole('button', { name: 'script.js' }).click();
    await page.getByLabel('script.js', { exact: true }).fill(SANDBOX_PROBE);
    await expectDraftSaved(page);
    await openMode(page, 'Предпросмотр');
    await expect(frame.locator('[data-probe]')).toHaveText(
        'cookie: заблокировано; storage: заблокировано; parent: заблокировано; top: заблокировано; fetch: заблокировано',
    );
    await expect(
        page.getByRole('region', { name: 'Ошибки выполнения' }),
    ).toContainText('Проверка ошибки выполнения');
    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);
    await expect(frame.getByRole('heading', { level: 2 })).toHaveText(
        'Летняя распродажа',
    );

    // Preview data is part of the Draft and survives a reload.
    await page.reload();
    await openMode(page, 'Предпросмотр');
    await expect(
        page
            .getByRole('region', { name: 'Данные предпросмотра' })
            .getByLabel('Заголовок'),
    ).toHaveValue('Летняя распродажа');
});

test('Block preview escapes sources and only trusts its own sandbox', async ({
    page,
}, testInfo) => {
    const suffix = uniqueSuffix(testInfo);
    const breakout =
        '</script><img src="x" onerror="document.body.setAttribute(\'data-escaped\', \'no\')">';

    await login(page, users.studioDeveloper);
    await page.goto('/developer/blocks/create');
    await createBlock(
        page,
        `E2E песочница ${suffix}`,
        `e2e-sandbox-${suffix}`,
        'Контент',
        'Создать блок',
    );
    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);

    await page
        .getByLabel('index.html', { exact: true })
        .fill('<h2>{{ title }}</h2>\n<p data-css-check>Стили</p>');
    await page.getByRole('button', { name: 'styles.css' }).click();
    await page
        .getByLabel('styles.css', { exact: true })
        .fill(
            "p { color: rgb(1, 2, 3); } </style><script>document.body.setAttribute('data-escaped', 'no')</script>",
        );
    await page.getByRole('button', { name: 'script.js' }).click();
    await page
        .getByLabel('script.js', { exact: true })
        .fill(
            [
                "parent.postMessage({ type: 'landflow:navigate', url: '/dashboard' }, '*');",
                "parent.postMessage({ type: 'landflow:action', key: 'unknown' }, '*');",
                "document.body.setAttribute('data-js', 'ok');",
                "// </script><script>document.body.setAttribute('data-escaped', 'no')</script>",
            ].join('\n'),
        );
    await expectDraftSaved(page);

    await openMode(page, 'Предпросмотр');
    await page
        .getByRole('region', { name: 'Данные предпросмотра' })
        .getByLabel('Заголовок')
        .fill(breakout);

    const iframe = page.getByTitle('Предпросмотр блока');
    const frame = page.frameLocator('iframe[title="Предпросмотр блока"]');
    await expect(frame.getByRole('heading', { level: 2 })).toHaveText(breakout);
    await expect(frame.locator('body')).toHaveAttribute('data-js', 'ok');
    await expect(frame.locator('body')).not.toHaveAttribute('data-escaped');
    await expect(frame.locator('[data-css-check]')).toHaveCSS(
        'color',
        'rgb(1, 2, 3)',
    );

    // Opaque origin with scripts only; the Landflow CSP precedes every authored source.
    await expect(iframe).toHaveAttribute('sandbox', 'allow-scripts');
    const srcdoc = (await iframe.getAttribute('srcdoc')) ?? '';
    const csp = srcdoc.indexOf('http-equiv="Content-Security-Policy"');
    expect(csp).toBeGreaterThan(0);
    expect(csp).toBeLessThan(srcdoc.indexOf('<style'));
    expect(csp).toBeLessThan(srcdoc.indexOf('<script'));
    expect(srcdoc).toContain("default-src 'none'");
    expect(srcdoc).toContain("connect-src 'none'");
    expect(srcdoc).toContain("form-action 'none'");

    // Forged bridge messages: unknown type / action from the sandbox, resize from a foreign window.
    const heightBefore = await iframe.evaluate(
        (element) => element.getBoundingClientRect().height,
    );
    await page.evaluate(async () => {
        window.postMessage({ type: 'landflow:resize', height: 3000 }, '*');
        const forger = document.createElement('iframe');
        forger.srcdoc =
            "<script>parent.postMessage({ type: 'landflow:resize', height: 3000 }, '*'); parent.postMessage({ type: 'landflow:action', key: 'cta' }, '*');</script>";
        const loaded = new Promise((resolve) =>
            forger.addEventListener('load', resolve),
        );
        document.body.appendChild(forger);
        await loaded;
        await new Promise((resolve) => setTimeout(resolve, 200));
        forger.remove();
    });
    expect(
        await iframe.evaluate(
            (element) => element.getBoundingClientRect().height,
        ),
    ).toBe(heightBefore);
    await expect(page.getByText(/Вызвано действие/)).toHaveCount(0);
    await expect(page).toHaveURL(/\/developer\/blocks\/[0-9a-z]{26}$/i);
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
