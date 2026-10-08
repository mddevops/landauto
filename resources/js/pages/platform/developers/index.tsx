import { Form, Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Field, TextField } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    index,
    reactivate,
    store,
    suspend,
} from '@/routes/platform/developers';
import { update as updatePermissions } from '@/routes/platform/developers/permissions';
import type {
    DeveloperPermission,
    DeveloperPermissionOption,
} from '@/types/platform';

type Developer = {
    public_id: string;
    display_name: string;
    slug: string;
    email: string;
    status: 'active' | 'suspended';
    status_label: string;
    permissions: DeveloperPermission[];
    created_at: string | null;
};

type DevelopersProps = {
    developers: Developer[];
    permissionOptions: DeveloperPermissionOption[];
};

const textareaClass =
    'min-h-24 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30';

function CreateDeveloperDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus aria-hidden="true" />
                    Добавить разработчика
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Новый профиль разработчика</DialogTitle>
                    <DialogDescription>
                        Профиль выдаётся существующему пользователю с
                        подтверждённым email. Он не даёт доступа к пространствам
                        и платформенным ролям.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <TextField
                                id="developer-email"
                                name="email"
                                type="email"
                                label="Email пользователя"
                                required
                                maxLength={255}
                                autoComplete="off"
                                error={errors.email}
                            />
                            <TextField
                                id="developer-display_name"
                                name="display_name"
                                label="Название разработчика"
                                required
                                maxLength={100}
                                autoComplete="off"
                                error={errors.display_name}
                            />
                            <TextField
                                id="developer-slug"
                                name="slug"
                                label="Slug"
                                required
                                minLength={3}
                                maxLength={60}
                                autoComplete="off"
                                hint="Строчные латинские буквы, цифры и дефисы, 3–60 символов. Будет адресом страницы автора."
                                error={errors.slug}
                            />
                            <Field
                                id="developer-bio"
                                label="Описание (необязательно)"
                                error={errors.bio}
                            >
                                <textarea
                                    id="developer-bio"
                                    name="bio"
                                    maxLength={1000}
                                    className={textareaClass}
                                    aria-invalid={Boolean(errors.bio)}
                                    aria-describedby={
                                        errors.bio
                                            ? 'developer-bio-error'
                                            : undefined
                                    }
                                />
                            </Field>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Отмена
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Создать профиль разработчика
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PermissionsDialog({
    developer,
    options,
}: {
    developer: Developer;
    options: DeveloperPermissionOption[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ permissions: DeveloperPermission[] }>({
        permissions: developer.permissions,
    });
    const prefix = `developer-${developer.public_id}-permission`;

    function toggle(permission: DeveloperPermission, checked: boolean) {
        form.setData(
            'permissions',
            options
                .map((option) => option.value)
                .filter((value) =>
                    value === permission
                        ? checked
                        : form.data.permissions.includes(value),
                ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(updatePermissions(developer.public_id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    form.setData('permissions', developer.permissions);
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    aria-label={`Права разработчика ${developer.display_name}`}
                >
                    Права
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Права разработчика</DialogTitle>
                    <DialogDescription>
                        {developer.display_name}. Права действуют только для
                        активного профиля и не дают доступа к пространствам или
                        платформенным ролям.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <fieldset className="grid gap-3">
                        <legend className="sr-only">Права разработчика</legend>
                        {options.map((option) => (
                            <div
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`${prefix}-${option.value}`}
                                    checked={form.data.permissions.includes(
                                        option.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        toggle(option.value, checked === true)
                                    }
                                />
                                <Label
                                    htmlFor={`${prefix}-${option.value}`}
                                    className="font-normal"
                                >
                                    {option.label}
                                </Label>
                            </div>
                        ))}
                    </fieldset>
                    {form.data.permissions.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Без прав разработчик сможет открыть панель, но не
                            сможет создавать блоки, шаблоны и отправлять их на
                            модерацию.
                        </p>
                    )}
                    <InputError
                        message={
                            form.errors.permissions ??
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('permissions.'),
                            )?.[1]
                        }
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить права
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PermissionBadges({
    developer,
    options,
}: {
    developer: Developer;
    options: DeveloperPermissionOption[];
}) {
    const granted = options.filter((option) =>
        developer.permissions.includes(option.value),
    );

    if (granted.length === 0) {
        return <p className="text-xs text-muted-foreground">Нет прав</p>;
    }

    return (
        <ul aria-label="Права разработчика" className="flex flex-wrap gap-1">
            {granted.map((option) => (
                <li key={option.value}>
                    <Badge variant="outline" title={option.label}>
                        {option.short_label}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}

function StatusAction({ developer }: { developer: Developer }) {
    const isActive = developer.status === 'active';
    const action = isActive
        ? suspend.form(developer.public_id)
        : reactivate.form(developer.public_id);

    return (
        <Form {...action} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant={isActive ? 'outline' : 'default'}
                    disabled={processing}
                    aria-label={`${isActive ? 'Приостановить' : 'Восстановить'} ${developer.display_name}`}
                >
                    {processing && <Spinner />}
                    {isActive ? 'Приостановить' : 'Восстановить'}
                </Button>
            )}
        </Form>
    );
}

export default function PlatformDevelopers({
    developers,
    permissionOptions,
}: DevelopersProps) {
    return (
        <>
            <Head title="Разработчики" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Разработчики
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Профили разработчиков выдаёт только
                            суперадминистратор. Приостановка закрывает панель
                            разработчика, но не затрагивает пространства
                            пользователя.
                        </p>
                    </div>
                    <CreateDeveloperDialog />
                </header>

                {developers.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Профилей разработчиков пока нет.
                    </p>
                ) : (
                    <ul
                        aria-label="Профили разработчиков"
                        className="divide-y rounded-xl border bg-card shadow-sm"
                    >
                        {developers.map((developer) => (
                            <li
                                key={developer.public_id}
                                data-testid="developer-row"
                                className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <p className="font-medium break-words">
                                        {developer.display_name}
                                    </p>
                                    <p className="text-sm break-all text-muted-foreground">
                                        {developer.email}
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        <code>{developer.slug}</code>
                                    </p>
                                    <div className="pt-1">
                                        <PermissionBadges
                                            developer={developer}
                                            options={permissionOptions}
                                        />
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <Badge
                                        variant={
                                            developer.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {developer.status_label}
                                    </Badge>
                                    <PermissionsDialog
                                        developer={developer}
                                        options={permissionOptions}
                                    />
                                    <StatusAction developer={developer} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </>
    );
}

PlatformDevelopers.layout = {
    breadcrumbs: [{ title: 'Разработчики', href: index() }],
};
