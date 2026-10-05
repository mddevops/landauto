import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { Choice } from '@/components/platform/form-fields';
import {
    SelectField,
    statusChoices,
    TextField,
} from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/integrations';

export type IntegrationProfileRow = {
    public_id: string;
    name: string;
    provider_type: string;
    provider_type_label: string;
    provider_key: string | null;
    base_url: string | null;
    auth_type: string;
    auth_type_label: string;
    api_key_header: string | null;
    credentials_mask: string | null;
    status: string;
    status_label: string;
};

export type IntegrationChoices = { providers: Choice[]; authTypes: Choice[] };

export function ProfileDialog({
    choices,
    profile,
    trigger,
}: {
    choices: IntegrationChoices;
    profile?: IntegrationProfileRow;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const prefix = `profile-${profile?.public_id ?? 'new'}`;
    const form = useForm({
        name: profile?.name ?? '',
        provider_type: profile?.provider_type ?? 'webhook',
        provider_key: profile?.provider_key ?? '',
        base_url: profile?.base_url ?? '',
        auth_type: profile?.auth_type ?? 'bearer',
        api_key_header: profile?.api_key_header ?? '',
        status: profile?.status ?? 'active',
        credential_token: '',
        credential_username: '',
        credential_password: '',
    });
    const authType = form.data.auth_type;
    const replacing = Boolean(profile) && profile?.auth_type === authType;
    const keepHint = replacing
        ? `Сейчас сохранено: ${profile?.credentials_mask ?? ''}. Оставьте пустым, чтобы не менять.`
        : undefined;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(profile ? update(profile.public_id) : store(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset(
                    'credential_token',
                    'credential_username',
                    'credential_password',
                );

                if (!profile) {
                    form.reset();
                }
            },
            onFinish: () =>
                form.reset(
                    'credential_token',
                    'credential_username',
                    'credential_password',
                ),
        });
    }

    function remove() {
        if (!profile) {
            return;
        }

        router.delete(destroy.url(profile.public_id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setConfirmDelete(false);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {profile
                            ? 'Настройки подключения'
                            : 'Новое подключение'}
                    </DialogTitle>
                    <DialogDescription>
                        Подключение хранит адрес и доступ к внешней системе.
                        Сайты рабочего пространства используют его без
                        повторного ввода токена.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    className="grid gap-4"
                    autoComplete="off"
                >
                    <TextField
                        id={`${prefix}-name`}
                        label="Название"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={120}
                        required
                        error={form.errors.name}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id={`${prefix}-provider`}
                            label="Тип подключения"
                            choices={choices.providers}
                            value={form.data.provider_type}
                            disabled={Boolean(profile)}
                            onChange={(event) =>
                                form.setData(
                                    'provider_type',
                                    event.target.value,
                                )
                            }
                            error={form.errors.provider_type}
                        />
                        <TextField
                            id={`${prefix}-key`}
                            label="Код системы (необязательно)"
                            hint="Латиница, цифры, точка, дефис."
                            value={form.data.provider_key}
                            onChange={(event) =>
                                form.setData('provider_key', event.target.value)
                            }
                            maxLength={64}
                            error={form.errors.provider_key}
                        />
                    </div>
                    <TextField
                        id={`${prefix}-url`}
                        label="Адрес"
                        hint="Например, https://crm.example.ru/api/leads"
                        type="url"
                        inputMode="url"
                        value={form.data.base_url}
                        onChange={(event) =>
                            form.setData('base_url', event.target.value)
                        }
                        maxLength={2048}
                        required
                        error={form.errors.base_url}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id={`${prefix}-auth`}
                            label="Авторизация"
                            choices={choices.authTypes}
                            value={authType}
                            onChange={(event) =>
                                form.setData('auth_type', event.target.value)
                            }
                            error={form.errors.auth_type}
                        />
                        {profile && (
                            <SelectField
                                id={`${prefix}-status`}
                                label="Статус"
                                choices={statusChoices.map((choice) => ({
                                    value:
                                        choice.value === '1'
                                            ? 'active'
                                            : 'disabled',
                                    label: choice.label,
                                }))}
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                                error={form.errors.status}
                            />
                        )}
                    </div>
                    {authType === 'api_key_header' && (
                        <TextField
                            id={`${prefix}-header`}
                            label="Заголовок для ключа"
                            hint="Например, X-Api-Key."
                            value={form.data.api_key_header}
                            onChange={(event) =>
                                form.setData(
                                    'api_key_header',
                                    event.target.value,
                                )
                            }
                            maxLength={64}
                            required
                            error={form.errors.api_key_header}
                        />
                    )}
                    {(authType === 'bearer' ||
                        authType === 'api_key_header') && (
                        <TextField
                            id={`${prefix}-token`}
                            label={
                                replacing
                                    ? 'Новый токен или ключ'
                                    : 'Токен или ключ'
                            }
                            hint={keepHint}
                            type="password"
                            autoComplete="new-password"
                            value={form.data.credential_token}
                            onChange={(event) =>
                                form.setData(
                                    'credential_token',
                                    event.target.value,
                                )
                            }
                            maxLength={4096}
                            required={!replacing}
                            error={form.errors.credential_token}
                        />
                    )}
                    {authType === 'basic' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id={`${prefix}-username`}
                                label={replacing ? 'Новый логин' : 'Логин'}
                                hint={
                                    replacing
                                        ? 'Оставьте пустым, чтобы не менять.'
                                        : undefined
                                }
                                autoComplete="off"
                                value={form.data.credential_username}
                                onChange={(event) =>
                                    form.setData(
                                        'credential_username',
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                                required={!replacing}
                                error={form.errors.credential_username}
                            />
                            <TextField
                                id={`${prefix}-password`}
                                label={replacing ? 'Новый пароль' : 'Пароль'}
                                hint={keepHint}
                                type="password"
                                autoComplete="new-password"
                                value={form.data.credential_password}
                                onChange={(event) =>
                                    form.setData(
                                        'credential_password',
                                        event.target.value,
                                    )
                                }
                                maxLength={1024}
                                required={!replacing}
                                error={form.errors.credential_password}
                            />
                        </div>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {profile ? (
                            confirmDelete ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={remove}
                                >
                                    Подтвердить удаление
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    Удалить подключение
                                </Button>
                            )
                        ) : (
                            <span />
                        )}
                        <div className="flex flex-col-reverse gap-2 sm:flex-row">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    Отмена
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />}
                                Сохранить
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
