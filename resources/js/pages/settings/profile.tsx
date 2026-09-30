import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type PageProps = {
    auth: Auth;
};

// Mirrors the server-side normalization (trimmed, lowercased) that decides whether
// the submitted email is a change and therefore requires the current password.
function normalizeEmail(email: string): string {
    return email.trim().toLowerCase();
}

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const [email, setEmail] = useState(auth.user.email);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const emailChanged = normalizeEmail(email) !== auth.user.email;
    const emailUnverified =
        mustVerifyEmail && auth.user.email_verified_at === null;

    return (
        <>
            <Head title="Настройки профиля" />

            <h1 className="sr-only">Настройки профиля</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Профиль"
                    description="Измените имя и адрес электронной почты"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={['current_password']}
                    resetOnSuccess={['current_password']}
                    onError={(errors) => {
                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Имя</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Имя и фамилия"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Электронная почта</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="email@example.com"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {emailUnverified && !emailChanged && (
                                <div>
                                    <p className="-mt-4 text-sm text-muted-foreground">
                                        Электронная почта не подтверждена.{' '}
                                        <Link
                                            href={send()}
                                            as="button"
                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                        >
                                            Отправить письмо для подтверждения
                                            повторно.
                                        </Link>
                                    </p>

                                    {status === 'verification-link-sent' && (
                                        <div className="mt-2 text-sm font-medium text-green-600">
                                            Новая ссылка для подтверждения
                                            отправлена на вашу электронную
                                            почту.
                                        </div>
                                    )}
                                </div>
                            )}

                            {emailChanged && (
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Текущий пароль
                                    </Label>

                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Текущий пароль"
                                        aria-describedby="current_password_help"
                                    />

                                    <p
                                        id="current_password_help"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Введите пароль, чтобы подтвердить смену
                                        адреса. На новую почту придёт письмо со
                                        ссылкой для подтверждения.
                                    </p>

                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>
                            )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Сохранить
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser
                unavailableReason={
                    emailUnverified
                        ? 'Удалить аккаунт можно после подтверждения электронной почты.'
                        : undefined
                }
            />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Настройки профиля',
            href: edit(),
        },
    ],
};
