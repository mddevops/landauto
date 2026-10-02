import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { users } from './support/users';

// Must match every word form of the removed 2FA / passkey UI (D-095).
const removedConfirmPasswordUi =
    /ключ\S* доступа|passkey|двухфактор|подтвердите паролем/i;
const removedSecurityUi =
    /двухфактор|ключ\S* доступа|passkey|код\S* восстановления|аутентификатор|totp/i;

test('removed 2FA and passkey copy is caught by the negative patterns', () => {
    for (const removed of [
        'Подтвердить с помощью ключа доступа',
        'Или подтвердите паролем',
    ]) {
        expect(removed).toMatch(removedConfirmPasswordUi);
    }

    for (const removed of [
        'Двухфакторная аутентификация',
        'Ключи доступа',
        'Ключей доступа пока нет',
        'Добавить ключ доступа',
        'Коды восстановления',
        'приложения-аутентификатора с поддержкой TOTP',
    ]) {
        expect(removed).toMatch(removedSecurityUi);
    }

    // The remaining confirm-password copy must not trip the pattern.
    expect('Подтвердите пароль, чтобы продолжить.').not.toMatch(
        removedConfirmPasswordUi,
    );
});

test(
    'profile settings render in Russian with the current account data',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/settings/profile');

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle('Настройки профиля - Landflow');
        await expect(
            page.getByRole('heading', { name: 'Настройки', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Профиль', exact: true }),
        ).toBeVisible();
        await expect(page.getByLabel('Имя')).toHaveValue(users.member.name);
        await expect(page.getByLabel('Электронная почта')).toHaveValue(
            users.member.email,
        );
        await expect(
            page.getByRole('button', { name: 'Сохранить' }),
        ).toBeVisible();
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'settings', 'profile');
    },
);

test('settings navigation opens appearance settings', async ({ page }) => {
    await page.goto('/settings/profile');

    await page
        .getByRole('navigation', { name: 'Настройки' })
        .getByRole('link', { name: 'Внешний вид' })
        .click();

    await expect(page).toHaveURL('/settings/appearance');
    await expect(page).toHaveTitle('Внешний вид - Landflow');
    await expect(
        page.getByRole('heading', { level: 2, name: 'Внешний вид' }),
    ).toBeVisible();
});

test('security settings require password confirmation', async ({
    page,
}, testInfo) => {
    await page.goto('/settings/profile');

    await page
        .getByRole('navigation', { name: 'Настройки' })
        .getByRole('link', { name: 'Безопасность' })
        .click();

    await expect(
        page.getByRole('heading', { name: 'Подтверждение пароля' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: removedConfirmPasswordUi }),
    ).toHaveCount(0);
    await expect(page.getByText(removedConfirmPasswordUi)).toHaveCount(0);

    await captureScreenshot(page, testInfo, 'auth', 'confirm-password');

    await page
        .getByLabel('Пароль', { exact: true })
        .fill(users.member.password);
    await page
        .getByRole('button', { name: 'Подтвердить', exact: true })
        .click();

    await expect(page).toHaveURL('/settings/security');
    await expect(
        page.getByRole('heading', { name: 'Смена пароля' }),
    ).toBeVisible();
    await expect(page.getByLabel('Текущий пароль')).toBeVisible();
    await expect(page.getByLabel('Новый пароль')).toBeVisible();
    await expect(page.getByLabel('Подтверждение пароля')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Сохранить' })).toBeVisible();

    // Two-factor authentication and passkeys are not Landflow features (D-095).
    await expect(page.getByText(removedSecurityUi)).toHaveCount(0);
    await expect(page.getByRole('heading')).toHaveText([
        'Настройки',
        'Безопасность',
        'Смена пароля',
    ]);

    await captureScreenshot(page, testInfo, 'settings', 'security');
});

test(
    'changing the email asks for the current password',
    {
        tag: '@responsive',
    },
    async ({ page }, testInfo) => {
        await page.goto('/settings/profile');

        const currentPassword = page.getByLabel('Текущий пароль');
        await expect(currentPassword).toHaveCount(0);

        await page
            .getByLabel('Электронная почта')
            .fill('member-new@landflow.test');
        await expect(currentPassword).toBeVisible();
        await expect(
            page.getByText('На новую почту придёт письмо со ссылкой', {
                exact: false,
            }),
        ).toBeVisible();

        await captureScreenshot(
            page,
            testInfo,
            'settings',
            'profile-email-change',
        );

        // The same address in another case/with spaces is not a change.
        await page
            .getByLabel('Электронная почта')
            .fill(`  ${users.member.email.toUpperCase()} `);
        await expect(currentPassword).toHaveCount(0);

        await page
            .getByLabel('Электронная почта')
            .fill('member-new@landflow.test');
        await currentPassword.fill('wrong-password');
        await page.getByRole('button', { name: 'Сохранить' }).click();

        await expect(page.getByText('Неверный пароль.')).toBeVisible();
        await expect(currentPassword).toHaveValue('');

        await page.reload();
        await expect(page.getByLabel('Электронная почта')).toHaveValue(
            users.member.email,
        );
    },
);

test('profile validation errors are shown in Russian', async ({ page }) => {
    await page.goto('/settings/profile');

    // Whitespace passes the browser `required` check; the server trims it and rejects it.
    await page.getByLabel('Имя').fill('   ');
    await page.getByRole('button', { name: 'Сохранить' }).click();

    await expect(
        page.getByText('Поле «Имя» обязательно для заполнения.'),
    ).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Имя')).toHaveValue(users.member.name);
});
