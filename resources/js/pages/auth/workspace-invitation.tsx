import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login, logout, register } from '@/routes';
import { accept } from '@/routes/invitations';
import { notice } from '@/routes/verification';

type InvitationState =
    | 'ready'
    | 'guest'
    | 'unverified'
    | 'email_mismatch'
    | 'already_member'
    | 'expired'
    | 'inactive'
    | 'invalid';

type InvitationProps = {
    state: InvitationState;
    invitation: {
        workspace_name: string;
        role_label: string;
        inviter_name: string | null;
        email: string;
        expires_at: string;
    } | null;
};

const closedMessages: Partial<Record<InvitationState, string>> = {
    expired:
        'Срок действия приглашения истёк. Попросите администратора пространства отправить новое приглашение.',
    inactive: 'Это приглашение больше не действует.',
    invalid:
        'Ссылка приглашения недействительна. Возможно, приглашение отправили повторно — откройте ссылку из последнего письма.',
};

export default function WorkspaceInvitation({
    state,
    invitation,
}: InvitationProps) {
    const closed = closedMessages[state];

    return (
        <>
            <Head title="Приглашение в пространство" />

            {closed || invitation === null ? (
                <div className="space-y-6 text-center">
                    <p className="text-sm text-muted-foreground">
                        {closed ?? closedMessages.invalid}
                    </p>
                    <TextLink href={dashboard()} className="text-sm">
                        Перейти в Landflow
                    </TextLink>
                </div>
            ) : (
                <div className="space-y-6">
                    <dl className="grid gap-3 rounded-lg border p-4 text-sm">
                        <div className="grid gap-1">
                            <dt className="text-muted-foreground">
                                Пространство
                            </dt>
                            <dd className="font-medium break-words">
                                {invitation.workspace_name}
                            </dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="text-muted-foreground">Роль</dt>
                            <dd className="font-medium">
                                {invitation.role_label}
                            </dd>
                        </div>
                        {invitation.inviter_name && (
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">
                                    Пригласил(а)
                                </dt>
                                <dd className="font-medium break-words">
                                    {invitation.inviter_name}
                                </dd>
                            </div>
                        )}
                        <div className="grid gap-1">
                            <dt className="text-muted-foreground">
                                Адрес приглашения
                            </dt>
                            <dd className="font-medium break-all">
                                {invitation.email}
                            </dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="text-muted-foreground">
                                Действует до
                            </dt>
                            <dd className="font-medium">
                                {new Date(invitation.expires_at).toLocaleString(
                                    'ru-RU',
                                    { dateStyle: 'short', timeStyle: 'short' },
                                )}
                            </dd>
                        </div>
                    </dl>

                    {state === 'ready' && (
                        <Form {...accept.form()} className="grid gap-2">
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Принять приглашение
                                    </Button>
                                    <InputError message={errors.invitation} />
                                </>
                            )}
                        </Form>
                    )}

                    {state === 'guest' && (
                        <div className="grid gap-3 text-center text-sm">
                            <p className="text-muted-foreground">
                                Войдите или зарегистрируйтесь с адресом, на
                                который пришло приглашение. После входа вы
                                вернётесь на эту страницу.
                            </p>
                            <Button asChild className="w-full">
                                <Link href={login()}>Войти</Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="w-full"
                            >
                                <Link href={register()}>
                                    Зарегистрироваться
                                </Link>
                            </Button>
                        </div>
                    )}

                    {state === 'unverified' && (
                        <div className="grid gap-3 text-center text-sm">
                            <p className="text-muted-foreground">
                                Подтвердите адрес электронной почты, чтобы
                                принять приглашение. После подтверждения вы
                                вернётесь на эту страницу.
                            </p>
                            <Button asChild className="w-full">
                                <Link href={notice()}>
                                    Подтвердить электронную почту
                                </Link>
                            </Button>
                        </div>
                    )}

                    {state === 'email_mismatch' && (
                        <div className="grid gap-3 text-center text-sm">
                            <p className="text-muted-foreground">
                                Приглашение отправлено на другой адрес
                                электронной почты. Выйдите, войдите под учётной
                                записью с этим адресом и снова откройте ссылку
                                из письма.
                            </p>
                            <TextLink href={logout()}>Выйти</TextLink>
                        </div>
                    )}

                    {state === 'already_member' && (
                        <div className="grid gap-3 text-center text-sm">
                            <p className="text-muted-foreground">
                                Вы уже состоите в этом пространстве.
                            </p>
                            <TextLink href={dashboard()}>
                                Перейти к сайтам
                            </TextLink>
                        </div>
                    )}
                </div>
            )}
        </>
    );
}

WorkspaceInvitation.layout = {
    title: 'Приглашение в пространство',
    description: 'Присоединитесь к рабочему пространству в Landflow.',
};
