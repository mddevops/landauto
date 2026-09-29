import { expect, test as setup } from './support/fixtures';
import { guestStorageState, memberStorageState, users } from './support/users';

setup.use({ storageState: guestStorageState });

setup('authenticate member', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Электронная почта').fill(users.member.email);
    await page
        .getByLabel('Пароль', { exact: true })
        .fill(users.member.password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();

    await expect(page).toHaveURL('/dashboard');

    await page.context().storageState({ path: memberStorageState });
});
