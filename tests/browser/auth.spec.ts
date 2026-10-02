import type { Page } from '@playwright/test';
import { expect, test } from './support/fixtures';
import { expectNoHorizontalOverflow } from './support/layout';
import { captureScreenshot } from './support/screenshots';
import { guestStorageState, users } from './support/users';

test.use({ storageState: guestStorageState });

// Must match every word form of the removed passkey sign-in UI (D-095), e.g.
// «Войти с помощью ключа доступа» and «Или войдите по электронной почте».
const removedLoginUi =
    /ключ\S* доступа|passkey|двухфактор|войдите по электронной почте/i;

test('removed passkey sign-in copy is caught by the negative pattern', () => {
    for (const removed of [
        'Войти с помощью ключа доступа',
        'Или войдите по электронной почте',
    ]) {
        expect(removed).toMatch(removedLoginUi);
    }
});

async function submitLogin(page: Page, email: string, password: string) {
    await page.getByLabel('Электронная почта').fill(email);
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
}

test(
    'login page renders in Russian',
    { tag: '@responsive' },
    async ({ page }, testInfo) => {
        const response = await page.goto('/login');

        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle('Вход - Landflow');
        await expect(
            page.getByRole('heading', { name: 'Вход в аккаунт' }),
        ).toBeVisible();
        await expect(page.getByLabel('Электронная почта')).toBeVisible();
        await expect(page.getByLabel('Пароль', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Запомнить меня')).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Войти', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Зарегистрироваться' }),
        ).toBeVisible();

        // Passkeys are not a Landflow sign-in method (D-095).
        await expect(
            page.getByRole('button', { name: removedLoginUi }),
        ).toHaveCount(0);
        await expect(page.getByText(removedLoginUi)).toHaveCount(0);
        await expectNoHorizontalOverflow(page);

        await captureScreenshot(page, testInfo, 'auth', 'login');
    },
);

test('guest is redirected from dashboard to login', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL('/login');
    await expect(
        page.getByRole('heading', { name: 'Вход в аккаунт' }),
    ).toBeVisible();
});

test('wrong password shows a Russian error and keeps the user on login', async ({
    page,
}, testInfo) => {
    await page.goto('/login');
    await submitLogin(page, users.login.email, 'wrong-password');

    await expect(
        page.getByText('Неверная электронная почта или пароль.'),
    ).toBeVisible();
    await expect(page).toHaveURL('/login');
    await expect(page.getByLabel('Электронная почта')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await expect(page.getByLabel('Электронная почта')).toHaveAttribute(
        'aria-describedby',
        'email-error',
    );

    await captureScreenshot(
        page,
        testInfo,
        'auth',
        'login-invalid-credentials',
    );
});

test('login controls keep natural order and wrap at 320px', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto('/login');

    await expectNoHorizontalOverflow(page);
    const remember = await page.getByLabel('Запомнить меня').boundingBox();
    const forgot = await page
        .getByRole('link', { name: 'Забыли пароль?' })
        .boundingBox();

    expect(remember).not.toBeNull();
    expect(forgot).not.toBeNull();
    expect(forgot!.y).toBeGreaterThan(remember!.y);
});

test('registration uses natural keyboard order including password controls', async ({
    page,
}) => {
    await page.goto('/register');

    await expect(page.getByLabel('Имя')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Электронная почта')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Пароль', { exact: true })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'Показать пароль' }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Подтверждение пароля')).toBeFocused();
});

test('user can log in with the keyboard and reach the dashboard', async ({
    page,
}) => {
    await page.goto('/login');

    await expect(page.getByLabel('Электронная почта')).toBeFocused();
    await page.keyboard.type(users.login.email);
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Пароль', { exact: true })).toBeFocused();
    await page.keyboard.type(users.login.password);
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL('/dashboard');
    await expect(page).toHaveTitle('Панель управления - Landflow');
});

test(
    'unverified user lands on the email verification notice after login',
    {
        tag: '@responsive',
    },
    async ({ page }, testInfo) => {
        const notice = page.getByRole('heading', {
            name: 'Подтверждение электронной почты',
        });

        await page.goto('/login');
        await submitLogin(
            page,
            users.unverified.email,
            users.unverified.password,
        );

        await expect(page).toHaveURL('/email/verify');
        await expect(notice).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Отправить письмо повторно' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', {
                name: 'Исправить электронную почту в профиле',
            }),
        ).toBeVisible();

        await page
            .getByRole('button', { name: 'Отправить письмо повторно' })
            .click();
        await expect(
            page.getByText(
                'Новая ссылка для подтверждения отправлена на электронную почту, указанную при регистрации.',
            ),
        ).toBeVisible();

        await captureScreenshot(page, testInfo, 'auth', 'verify-email');

        await page.goto('/dashboard');
        await expect(page).toHaveURL('/email/verify');
        await expect(notice).toBeVisible();

        // The profile stays reachable to fix a mistyped email; account deletion does not.
        await page.goto('/settings/profile');
        await expect(page.getByLabel('Электронная почта')).toHaveValue(
            users.unverified.email,
        );
        await expect(
            page.getByText('Электронная почта не подтверждена.'),
        ).toBeVisible();
        await expect(
            page.getByText('Удаление аккаунта и всех связанных с ним данных'),
        ).toBeVisible();
        await expect(
            page
                .getByRole('note')
                .getByText(
                    'Удалить аккаунт можно после подтверждения электронной почты.',
                ),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Удалить аккаунт' }),
        ).toHaveCount(0);
        await expect(
            page
                .getByRole('navigation', { name: 'Настройки' })
                .getByRole('link'),
        ).toHaveText(['Профиль']);
        await expect(
            page
                .locator('[data-slot="sidebar"]')
                .getByRole('link', { name: 'Панель управления' }),
        ).toHaveCount(0);

        await captureScreenshot(
            page,
            testInfo,
            'settings',
            'profile-unverified',
        );
    },
);

test('user can log out from the user menu', async ({ page }) => {
    await page.goto('/login');
    await submitLogin(page, users.login.email, users.login.password);
    await expect(page).toHaveURL('/dashboard');

    await page.getByRole('button', { name: users.login.name }).click();
    await page.getByRole('menuitem', { name: 'Выйти' }).click();

    await expect(page).toHaveURL('/');
    await page.goto('/dashboard');
    await expect(page).toHaveURL('/login');
});
