import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

const author = users.marketplaceDeveloper;

test('a Developer lists an own published Block, publishes and unpublishes it @responsive', async ({
    page,
}, testInfo) => {
    // One listing per product: every project lists its own seeded Block.
    const project = testInfo.project.name as keyof typeof author.blocks;
    const blockName = author.blocks[project];
    const title = `Карточка витрины (${project})`;
    const slug = `e2e-listing-${project}`;

    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(author.email);
    await page.getByLabel('Пароль', { exact: true }).fill(author.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL('/dashboard');

    await page.goto('/developer');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Студия' }),
    ).toBeVisible();
    await expect(page.getByText('Публикация в Marketplace')).toBeVisible();
    // Automated checks replaced manual review (D-120): no moderation wording may return.
    await expect(page.locator('body')).not.toContainText(/модерац/i);
    await expectNoHorizontalOverflow(page);

    await page.getByRole('link', { name: /^Marketplace/ }).click();
    await expect(page).toHaveURL('/developer/marketplace');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Marketplace' }),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Создать карточку' }).click();
    const form = page.getByRole('region', { name: 'Новая карточка' });
    await form.getByLabel('Блок', { exact: true }).check();
    await form.getByLabel('Продукт').selectOption({ label: blockName });
    await form.getByLabel('Название').fill(title);
    await form.getByLabel('Slug').fill(slug);
    await form
        .getByLabel('Описание')
        .fill('Витрина спецпредложений для сайта дилера.');
    await expectNoHorizontalOverflow(page);
    await form.getByRole('button', { name: 'Создать карточку' }).click();

    await expect(page).toHaveURL(
        /\/developer\/marketplace\/[0-9A-HJKMNP-TV-Z]{26}$/i,
    );
    await expect(
        page.getByRole('heading', { level: 1, name: title }),
    ).toBeVisible();
    const status = page.getByTestId('listing-status');
    await expect(status).toHaveText('Черновик');
    const product = page.getByRole('complementary', { name: 'Продукт' });
    await expect(product).toContainText(blockName);
    await expect(product).toContainText(author.profile);
    await expect(product).toContainText('Бесплатно');
    await expect(page.getByLabel('Slug')).toHaveValue(slug);
    await expect(page.getByLabel('Slug')).not.toBeEditable();
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Опубликовать' }).click();
    await expect(
        page.getByText('Карточка опубликована в Marketplace.'),
    ).toBeVisible();
    await expect(status).toHaveText('Опубликовано');
    await expect(
        page.getByText('Карточка опубликована и будет видна в Marketplace.'),
    ).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.getByRole('button', { name: 'Снять с публикации' }).click();
    await expect(page.getByText('Карточка снята с публикации.')).toBeVisible();
    await expect(status).toHaveText('Черновик');

    await page.goto('/developer/marketplace');
    const row = page
        .getByTestId('marketplace-listing')
        .filter({ hasText: title });
    await expect(row).toContainText(blockName);
    await expect(row).toContainText('Черновик');
    await expectNoHorizontalOverflow(page);
});
