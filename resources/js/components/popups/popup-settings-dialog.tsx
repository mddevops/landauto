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
import { PopupConditionsEditor } from '@/components/popups/popup-conditions-editor';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { defaultLaunch } from '@/popups/launch';
import type { PopupLaunch } from '@/popups/launch';
import { destroy, store, update } from '@/routes/sites/popups';

export type PopupBinding = {
    kind: string;
    label: string;
    page?: string;
};

export type ManagedPopup = PopupRuntime & {
    status: boolean;
    form_public_id: string | null;
    bindings: PopupBinding[];
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

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function PopupSettingsDialog({
    sitePublicId,
    choices,
    forms,
    popups = [],
    popup,
    trigger,
}: {
    sitePublicId: string;
    choices: PopupChoices;
    forms: Choice[];
    popups?: ManagedPopup[];
    popup?: ManagedPopup;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [replaceWith, setReplaceWith] = useState('');
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
        launch: popup?.launch ?? defaultLaunch(),
    });

    function setLaunch(launch: PopupLaunch) {
        form.setData('launch', launch);
    }

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
            {
                preserveScroll: true,
                data: replaceWith ? { replace_with: replaceWith } : {},
                onSuccess: () => setOpen(false),
            },
        );
    }

    const replacements = popups.filter(
        (item) => item.public_id !== popup?.public_id,
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setConfirmDelete(false);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {popup ? 'Настройки попапа' : 'Новый попап'}
                    </DialogTitle>
                    <DialogDescription>
                        Попап — отдельная сущность сайта. Поля заявки
                        настраиваются в форме, показ — здесь.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Tabs defaultValue="content">
                        <TabsList>
                            <TabsTrigger value="content">Окно</TabsTrigger>
                            <TabsTrigger value="launch">Запуск</TabsTrigger>
                            <TabsTrigger value="conditions">
                                Условия
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent
                            value="content"
                            className="mt-4 grid gap-4"
                        >
                            <TextField
                                id={`${prefix}-name`}
                                label="Название"
                                hint="Видно только в редакторе. Переименование сохраняет привязки."
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
                            <div className="grid gap-1.5">
                                <Label htmlFor={`${prefix}-confirmation`}>
                                    Подтверждение заявки
                                </Label>
                                <select
                                    id={`${prefix}-confirmation`}
                                    className={selectClass}
                                    value={form.data.launch.confirmation}
                                    onChange={(event) =>
                                        setLaunch({
                                            ...form.data.launch,
                                            confirmation: event.target
                                                .value as PopupLaunch['confirmation'],
                                        })
                                    }
                                >
                                    <option value="replace">
                                        Заменить форму сообщением
                                    </option>
                                    <option value="notice">
                                        Показать уведомление в окне
                                    </option>
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    Поля и согласие настраиваются в библиотеке
                                    форм. Здесь только оболочка попапа.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <SelectField
                                    id={`${prefix}-status`}
                                    label="Статус"
                                    choices={statusChoices}
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
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
                                            event.target
                                                .value as PopupRuntime['size'],
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
                                <InputError
                                    message={form.errors.show_close_button}
                                />
                            </fieldset>
                        </TabsContent>
                        <TabsContent value="launch" className="mt-4 grid gap-4">
                            <p className="text-sm text-muted-foreground">
                                Автопоказ можно сочетать с кнопками. Если
                                совпали несколько попапов, откроется первый по
                                названию, затем по идентификатору. Одновременно
                                одно окно.
                            </p>
                            <div className="grid gap-1.5">
                                <Label htmlFor={`${prefix}-auto`}>
                                    Автоматический показ
                                </Label>
                                <select
                                    id={`${prefix}-auto`}
                                    className={selectClass}
                                    value={form.data.launch.auto_show}
                                    onChange={(event) => {
                                        const auto_show = event.target
                                            .value as PopupLaunch['auto_show'];
                                        setLaunch({
                                            ...form.data.launch,
                                            auto_show,
                                            shows:
                                                auto_show === 'custom' &&
                                                form.data.launch.shows
                                                    .length === 0
                                                    ? [{ delay_seconds: 8 }]
                                                    : form.data.launch.shows,
                                        });
                                    }}
                                >
                                    <option value="off">Выключен</option>
                                    <option value="auto">
                                        Автоматический расчёт (8 сек)
                                    </option>
                                    <option value="once_session">
                                        Один раз за сессию
                                    </option>
                                    <option value="periodic">
                                        Периодический показ
                                    </option>
                                    <option value="custom">
                                        Индивидуальные интервалы
                                    </option>
                                </select>
                            </div>
                            {(form.data.launch.auto_show === 'once_session' ||
                                form.data.launch.auto_show === 'periodic') && (
                                <div className="grid gap-1.5">
                                    <Label>Задержка, сек</Label>
                                    <input
                                        type="number"
                                        min={0}
                                        max={300}
                                        className={selectClass}
                                        value={form.data.launch.delay_seconds}
                                        onChange={(event) =>
                                            setLaunch({
                                                ...form.data.launch,
                                                delay_seconds: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                </div>
                            )}
                            {form.data.launch.auto_show === 'periodic' && (
                                <div className="grid gap-1.5">
                                    <Label>Период, сек</Label>
                                    <input
                                        type="number"
                                        min={10}
                                        max={86400}
                                        className={selectClass}
                                        value={form.data.launch.period_seconds}
                                        onChange={(event) =>
                                            setLaunch({
                                                ...form.data.launch,
                                                period_seconds: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                </div>
                            )}
                            {form.data.launch.auto_show === 'custom' && (
                                <div className="grid gap-2">
                                    <Label>Интервалы показа, сек</Label>
                                    {form.data.launch.shows.map(
                                        (show, index) => (
                                            <input
                                                key={index}
                                                type="number"
                                                min={0}
                                                max={300}
                                                className={selectClass}
                                                value={show.delay_seconds}
                                                onChange={(event) =>
                                                    setLaunch({
                                                        ...form.data.launch,
                                                        shows: form.data.launch.shows.map(
                                                            (item, current) =>
                                                                current ===
                                                                index
                                                                    ? {
                                                                          delay_seconds:
                                                                              Number(
                                                                                  event
                                                                                      .target
                                                                                      .value,
                                                                              ),
                                                                      }
                                                                    : item,
                                                        ),
                                                    })
                                                }
                                            />
                                        ),
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            form.data.launch.shows.length >= 10
                                        }
                                        onClick={() =>
                                            setLaunch({
                                                ...form.data.launch,
                                                shows: [
                                                    ...form.data.launch.shows,
                                                    { delay_seconds: 8 },
                                                ],
                                            })
                                        }
                                    >
                                        Добавить интервал
                                    </Button>
                                </div>
                            )}
                        </TabsContent>
                        <TabsContent
                            value="conditions"
                            className="mt-4 grid gap-4"
                        >
                            <PopupConditionsEditor
                                value={form.data.launch.conditions}
                                onChange={(conditions) =>
                                    setLaunch({
                                        ...form.data.launch,
                                        conditions,
                                    })
                                }
                            />
                            <InputError message={form.errors.launch} />
                        </TabsContent>
                    </Tabs>
                    <DialogFooter className="gap-2 sm:justify-between">
                        {popup ? (
                            confirmDelete ? (
                                <div className="grid w-full gap-2 sm:max-w-sm">
                                    {popup.bindings.length > 0 && (
                                        <>
                                            <p className="text-sm text-muted-foreground">
                                                Попап привязан:{' '}
                                                {popup.bindings
                                                    .map((binding) =>
                                                        binding.page
                                                            ? `${binding.label} (${binding.page})`
                                                            : binding.label,
                                                    )
                                                    .join(', ')}
                                            </p>
                                            <select
                                                className={selectClass}
                                                value={replaceWith}
                                                onChange={(event) =>
                                                    setReplaceWith(
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Заменить на другой попап
                                                </option>
                                                {replacements.map((item) => (
                                                    <option
                                                        key={item.public_id}
                                                        value={item.public_id}
                                                    >
                                                        {item.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </>
                                    )}
                                    <InputError
                                        message={
                                            (
                                                form.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            ).popup
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={remove}
                                        disabled={
                                            popup.bindings.length > 0 &&
                                            replaceWith === ''
                                        }
                                    >
                                        Подтвердить удаление
                                    </Button>
                                </div>
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
