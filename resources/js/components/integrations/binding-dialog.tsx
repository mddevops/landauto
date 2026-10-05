import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store, update } from '@/routes/sites/integrations';

export type OverrideRow = { key: string; value: string };

export type SiteBinding = {
    public_id: string;
    name: string | null;
    status: string;
    status_label: string;
    overrides: OverrideRow[];
    profile: {
        public_id: string;
        name: string;
        provider_type_label: string;
        status: string;
        status_label: string;
    };
};

export function BindingDialog({
    sitePublicId,
    profiles,
    binding,
    trigger,
}: {
    sitePublicId: string;
    profiles: Choice[];
    binding?: SiteBinding;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const prefix = `binding-${binding?.public_id ?? 'new'}`;
    const form = useForm<{
        profile: string;
        name: string;
        status: string;
        overrides: OverrideRow[];
    }>({
        profile: profiles[0]?.value ?? '',
        name: binding?.name ?? '',
        status: binding?.status ?? 'active',
        overrides: binding?.overrides ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    function setOverride(index: number, patch: Partial<OverrideRow>) {
        form.setData(
            'overrides',
            form.data.overrides.map((row, position) =>
                position === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(
            binding
                ? update({ site: sitePublicId, binding: binding.public_id })
                : store(sitePublicId),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!binding) {
                        form.reset();
                    }
                },
            },
        );
    }

    function remove() {
        if (!binding) {
            return;
        }

        router.delete(
            destroy.url({ site: sitePublicId, binding: binding.public_id }),
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
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
                        {binding
                            ? `Подключение «${binding.profile.name}»`
                            : 'Подключить интеграцию'}
                    </DialogTitle>
                    <DialogDescription>
                        Токен берётся из подключения рабочего пространства.
                        Здесь задаются только параметры этого сайта, например
                        номер дилера. Не указывайте в параметрах токены и
                        пароли.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    {!binding && (
                        <SelectField
                            id={`${prefix}-profile`}
                            label="Подключение"
                            choices={profiles}
                            value={form.data.profile}
                            onChange={(event) =>
                                form.setData('profile', event.target.value)
                            }
                            required
                            error={form.errors.profile}
                        />
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id={`${prefix}-name`}
                            label="Название (необязательно)"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={120}
                            error={form.errors.name}
                        />
                        {binding && (
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
                    <fieldset className="grid gap-3">
                        <legend className="mb-1 text-sm font-medium">
                            Параметры сайта
                        </legend>
                        {form.data.overrides.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Параметров нет.
                            </p>
                        )}
                        {form.data.overrides.map((row, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-start gap-2"
                            >
                                <div className="grid gap-1">
                                    <Input
                                        aria-label={`Ключ параметра ${index + 1}`}
                                        placeholder="site_id"
                                        value={row.key}
                                        maxLength={40}
                                        onChange={(event) =>
                                            setOverride(index, {
                                                key: event.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[`overrides.${index}.key`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Input
                                        aria-label={`Значение параметра ${index + 1}`}
                                        placeholder="101"
                                        value={row.value}
                                        maxLength={255}
                                        onChange={(event) =>
                                            setOverride(index, {
                                                value: event.target.value,
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            errors[`overrides.${index}.value`]
                                        }
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Удалить параметр ${index + 1}`}
                                    onClick={() =>
                                        form.setData(
                                            'overrides',
                                            form.data.overrides.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </div>
                        ))}
                        <InputError message={form.errors.overrides} />
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={form.data.overrides.length >= 20}
                                onClick={() =>
                                    form.setData('overrides', [
                                        ...form.data.overrides,
                                        { key: '', value: '' },
                                    ])
                                }
                            >
                                <Plus aria-hidden="true" />
                                Добавить параметр
                            </Button>
                        </div>
                    </fieldset>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {binding ? (
                            confirmDelete ? (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={remove}
                                >
                                    Подтвердить
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setConfirmDelete(true)}
                                >
                                    Отвязать от сайта
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
