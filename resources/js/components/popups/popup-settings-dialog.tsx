import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { PopupRuntime } from '@/blocks/popup';
import InputError from '@/components/input-error';
import type { Choice } from '@/components/platform/form-fields';
import {
    Field,
    SelectField,
    statusChoices,
    TextField,
} from '@/components/platform/form-fields';
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
import { destroy, store, update } from '@/routes/sites/popups';

export type ManagedPopup = PopupRuntime & {
    status: boolean;
    form_public_id: string | null;
};

export type PopupChoices = { sizes: Choice[]; animations: Choice[] };

type Toggle =
    | 'close_on_overlay'
    | 'close_on_escape'
    | 'show_close_button'
    | 'mobile_fullscreen';

const toggles: { key: Toggle; label: string }[] = [
    { key: 'show_close_button', label: 'Показывать кнопку закрытия' },
    { key: 'close_on_escape', label: 'Закрывать клавишей Escape' },
    { key: 'close_on_overlay', label: 'Закрывать по клику вне окна' },
    { key: 'mobile_fullscreen', label: 'На телефоне — на весь экран' },
];

export function PopupSettingsDialog({
    sitePublicId,
    choices,
    forms,
    popup,
    trigger,
}: {
    sitePublicId: string;
    choices: PopupChoices;
    forms: Choice[];
    popup?: ManagedPopup;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const prefix = `popup-${popup?.public_id ?? 'new'}`;
    const form = useForm({
        name: popup?.name ?? '',
        title: popup?.title ?? '',
        text: popup?.text ?? '',
        status: popup && !popup.status ? '0' : '1',
        size: popup?.size ?? 'medium',
        animation: popup?.animation ?? 'fade',
        close_on_overlay: popup?.close_on_overlay ?? true,
        close_on_escape: popup?.close_on_escape ?? true,
        show_close_button: popup?.show_close_button ?? true,
        mobile_fullscreen: popup?.mobile_fullscreen ?? false,
        form: popup?.form_public_id ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, status: data.status === '1' }));
        form.submit(
            popup
                ? update({ site: sitePublicId, popup: popup.public_id })
                : store(sitePublicId),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!popup) {
                        form.reset();
                    }
                },
            },
        );
    }

    function remove() {
        if (!popup) {
            return;
        }

        router.delete(
            destroy.url({ site: sitePublicId, popup: popup.public_id }),
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
                        {popup ? 'Настройки попапа' : 'Новый попап'}
                    </DialogTitle>
                    <DialogDescription>
                        Попап отвечает только за показ окна. Поля заявки
                        настраиваются в форме.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <TextField
                        id={`${prefix}-name`}
                        label="Название"
                        hint="Видно только в редакторе."
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={120}
                        required
                        error={form.errors.name}
                    />
                    <TextField
                        id={`${prefix}-title`}
                        label="Заголовок окна"
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        maxLength={160}
                        error={form.errors.title}
                    />
                    <Field
                        id={`${prefix}-text`}
                        label="Текст"
                        error={form.errors.text}
                    >
                        <textarea
                            id={`${prefix}-text`}
                            value={form.data.text}
                            onChange={(event) =>
                                form.setData('text', event.target.value)
                            }
                            maxLength={2000}
                            rows={3}
                            className="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </Field>
                    <SelectField
                        id={`${prefix}-form`}
                        label="Форма заявки"
                        choices={forms}
                        emptyLabel="Без формы"
                        value={form.data.form}
                        onChange={(event) =>
                            form.setData('form', event.target.value)
                        }
                        error={form.errors.form}
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectField
                            id={`${prefix}-status`}
                            label="Статус"
                            choices={statusChoices}
                            value={form.data.status}
                            onChange={(event) =>
                                form.setData('status', event.target.value)
                            }
                        />
                        <SelectField
                            id={`${prefix}-size`}
                            label="Размер"
                            choices={choices.sizes}
                            value={form.data.size}
                            onChange={(event) =>
                                form.setData(
                                    'size',
                                    event.target.value as PopupRuntime['size'],
                                )
                            }
                            error={form.errors.size}
                        />
                        <SelectField
                            id={`${prefix}-animation`}
                            label="Анимация"
                            choices={choices.animations}
                            value={form.data.animation}
                            onChange={(event) =>
                                form.setData(
                                    'animation',
                                    event.target
                                        .value as PopupRuntime['animation'],
                                )
                            }
                            error={form.errors.animation}
                        />
                    </div>
                    <fieldset className="grid gap-3">
                        <legend className="mb-2 text-sm font-medium">
                            Закрытие и адаптивность
                        </legend>
                        {toggles.map((toggle) => (
                            <div
                                key={toggle.key}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`${prefix}-${toggle.key}`}
                                    checked={form.data[toggle.key]}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            toggle.key,
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`${prefix}-${toggle.key}`}
                                    className="font-normal"
                                >
                                    {toggle.label}
                                </Label>
                            </div>
                        ))}
                        <InputError message={form.errors.show_close_button} />
                    </fieldset>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {popup ? (
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
                                    Удалить попап
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
