// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Подтверждение электронной почты" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    Новая ссылка для подтверждения отправлена на электронную
                    почту, указанную при регистрации.
                </div>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            Отправить письмо повторно
                        </Button>

                        <TextLink
                            href={editProfile()}
                            className="mx-auto block text-sm"
                        >
                            Исправить электронную почту в профиле
                        </TextLink>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Выйти
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Подтверждение электронной почты',
    description:
        'Подтвердите электронную почту — перейдите по ссылке из письма, которое мы только что отправили.',
};
