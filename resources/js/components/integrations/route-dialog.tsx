import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import type { Choice } from '@/components/platform/form-fields';
import {
    Field,
    NativeSelect,
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
import { destroy, store, update } from '@/routes/sites/forms/routes';

export type HeaderRow = { name: string; value: string };

export type MappingRow = {
    target: string;
    source: string;
    value?: string;
    missing: string;
};

export type SourceChoice = { value: string; group: string; label: string };

export type FormRouteRow = {
    public_id: string;
    name: string;
    destination_type: string;
    destination_label: string;
    status: string;
    status_label: string;
    recipients: string[];
    subject: string;
    reply_to_field: string;
    binding: {
        public_id: string;
        name: string;
        base_url: string | null;
    } | null;
    method: string;
    path: string;
    headers: HeaderRow[];
    mapping: MappingRow[];
};

export type BindingChoice = Choice & {
    provider_type: string;
    overrides: string[];
};

const missingChoices: Choice[] = [
    { value: 'omit', label: 'Не передавать поле' },
    { value: 'null', label: 'Передать null' },
    { value: 'error', label: 'Ошибка доставки' },
];

export type RouteChoices = {
    destinations: Choice[];
    methods: Choice[];
    subjectPlaceholders: string[];
};

export type RouteField = { key: string; type: string; label: string };

export function RouteDialog({
    sitePublicId,
    formPublicId,
    fields,
    bindings,
    sources,
    choices,
    route,
    trigger,
}: {
    sitePublicId: string;
    formPublicId: string;
    fields: RouteField[];
    bindings: BindingChoice[];
    sources: SourceChoice[];
    choices: RouteChoices;
    route?: FormRouteRow;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const prefix = `route-${route?.public_id ?? 'new'}`;
    const form = useForm<{
        destination_type: string;
        name: string;
        status: string;
        binding: string;
        recipients: string;
        subject: string;
        reply_to_field: string;
        method: string;
        path: string;
        headers: HeaderRow[];
        mapping: MappingRow[];
    }>({
        destination_type: route?.destination_type ?? 'email',
        name: route?.name ?? '',
        status: route?.status ?? 'active',
        binding: '',
        recipients: (route?.recipients ?? []).join(', '),
        subject: route?.subject ?? '',
        reply_to_field: route?.reply_to_field ?? '',
        method: route?.method ?? 'POST',
        path: route?.path ?? '',
        headers: route?.headers ?? [],
        mapping: route?.mapping ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const type = form.data.destination_type;
    const isEmail = type === 'email';
    const typeBindings = bindings.filter(
        (binding) => binding.provider_type === type,
    );
    const emailFields = fields
        .filter((field) => field.type === 'email')
        .map((field) => ({ value: field.key, label: field.label }));
    const recipientError = Object.entries(errors).find(([key]) =>
        key.startsWith('recipients'),
    )?.[1];
    const selectedBinding = route
        ? bindings.find((binding) => binding.value === route.binding?.public_id)
        : (typeBindings.find(
              (binding) => binding.value === form.data.binding,
          ) ?? typeBindings[0]);
    const sourceGroups = [
        {
            group: 'Поля формы',
            options: fields.map((field) => ({
                value: `field.${field.key}`,
                label: field.label,
            })),
        },
        ...Array.from(new Set(sources.map((source) => source.group))).map(
            (group) => ({
                group,
                options: sources.filter((source) => source.group === group),
            }),
        ),
        {
            group: 'Параметры сайта',
            options: (selectedBinding?.overrides ?? []).map((key) => ({
                value: `override.${key}`,
                label: key,
            })),
        },
    ].filter((group) => group.options.length > 0);

    function setRule(index: number, patch: Partial<MappingRow>) {
        form.setData(
            'mapping',
            form.data.mapping.map((row, position) =>
                position === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            binding: data.binding || typeBindings[0]?.value || '',
            recipients: data.recipients
                .split(/[,;\s]+/)
                .map((value) => value.trim())
                .filter(Boolean),
        }));
        form.submit(
            route
                ? update({
                      site: sitePublicId,
                      form: formPublicId,
                      route: route.public_id,
                  })
                : store({ site: sitePublicId, form: formPublicId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!route) {
                        form.reset();
                    }
                },
            },
        );
    }

    function remove() {
        if (!route) {
            return;
        }

        router.delete(
            destroy.url({
                site: sitePublicId,
                form: formPublicId,
                route: route.public_id,
            }),
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    }

    function setHeader(index: number, patch: Partial<HeaderRow>) {
        form.setData(
            'headers',
            form.data.headers.map((row, position) =>
                position === index ? { ...row, ...patch } : row,
            ),
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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {route ? 'Настройки маршрута' : 'Новый маршрут'}
                    </DialogTitle>
                    <DialogDescription>
                        Заявка сначала сохраняется, а затем передаётся по
                        каждому активному маршруту отдельно. Ошибка одного
                        маршрута не мешает остальным.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id={`${prefix}-type`}
                            label="Куда передавать"
                            choices={choices.destinations}
                            value={type}
                            disabled={Boolean(route)}
                            onChange={(event) =>
                                form.setData(
                                    'destination_type',
                                    event.target.value,
                                )
                            }
                            error={form.errors.destination_type}
                        />
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
                    </div>
                    {route && (
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
                    {isEmail ? (
                        <>
                            <TextField
                                id={`${prefix}-recipients`}
                                label="Получатели"
                                hint="До 5 адресов через запятую."
                                value={form.data.recipients}
                                onChange={(event) =>
                                    form.setData(
                                        'recipients',
                                        event.target.value,
                                    )
                                }
                                required
                                error={recipientError}
                            />
                            <TextField
                                id={`${prefix}-subject`}
                                label="Тема письма"
                                hint={`Можно использовать: ${choices.subjectPlaceholders.join(', ')}. Пусто — «Новая заявка: {form.name}».`}
                                value={form.data.subject}
                                onChange={(event) =>
                                    form.setData('subject', event.target.value)
                                }
                                maxLength={150}
                                error={form.errors.subject}
                            />
                            <SelectField
                                id={`${prefix}-reply`}
                                label="Адрес для ответа"
                                choices={emailFields}
                                emptyLabel="Не указывать"
                                value={form.data.reply_to_field}
                                onChange={(event) =>
                                    form.setData(
                                        'reply_to_field',
                                        event.target.value,
                                    )
                                }
                                error={form.errors.reply_to_field}
                            />
                        </>
                    ) : (
                        <>
                            {route ? (
                                <p className="text-sm">
                                    {`Подключение: ${route.binding?.name ?? '—'}`}
                                    {route.binding?.base_url && (
                                        <span className="block break-all text-muted-foreground">
                                            {route.binding.base_url}
                                        </span>
                                    )}
                                </p>
                            ) : typeBindings.length === 0 ? (
                                <p className="text-sm text-destructive">
                                    У сайта нет активного подключения этого
                                    типа. Добавьте его в «Интеграциях сайта».
                                </p>
                            ) : (
                                <SelectField
                                    id={`${prefix}-binding`}
                                    label="Подключение"
                                    choices={typeBindings}
                                    value={
                                        form.data.binding ||
                                        typeBindings[0]?.value
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'binding',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.binding}
                                />
                            )}
                            <div className="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <SelectField
                                    id={`${prefix}-method`}
                                    label="Метод"
                                    choices={choices.methods}
                                    value={form.data.method}
                                    onChange={(event) =>
                                        form.setData(
                                            'method',
                                            event.target.value,
                                        )
                                    }
                                    error={form.errors.method}
                                />
                                <TextField
                                    id={`${prefix}-path`}
                                    label="Путь (необязательно)"
                                    hint="Добавляется к адресу подключения, например /leads."
                                    value={form.data.path}
                                    onChange={(event) =>
                                        form.setData('path', event.target.value)
                                    }
                                    maxLength={512}
                                    error={form.errors.path}
                                />
                            </div>
                            <Field
                                id={`${prefix}-headers`}
                                label="Дополнительные заголовки"
                                hint="Без токенов: авторизация берётся из подключения."
                                error={form.errors.headers}
                            >
                                <div
                                    id={`${prefix}-headers`}
                                    className="grid gap-2"
                                >
                                    {form.data.headers.map((row, index) => (
                                        <div
                                            key={index}
                                            className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-start gap-2"
                                        >
                                            <div className="grid gap-1">
                                                <Input
                                                    aria-label={`Имя заголовка ${index + 1}`}
                                                    placeholder="X-Source"
                                                    value={row.name}
                                                    maxLength={64}
                                                    onChange={(event) =>
                                                        setHeader(index, {
                                                            name: event.target
                                                                .value,
                                                        })
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `headers.${index}.name`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Input
                                                    aria-label={`Значение заголовка ${index + 1}`}
                                                    value={row.value}
                                                    maxLength={255}
                                                    onChange={(event) =>
                                                        setHeader(index, {
                                                            value: event.target
                                                                .value,
                                                        })
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `headers.${index}.value`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Удалить заголовок ${index + 1}`}
                                                onClick={() =>
                                                    form.setData(
                                                        'headers',
                                                        form.data.headers.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    ))}
                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                form.data.headers.length >= 10
                                            }
                                            onClick={() =>
                                                form.setData('headers', [
                                                    ...form.data.headers,
                                                    { name: '', value: '' },
                                                ])
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            Добавить заголовок
                                        </Button>
                                    </div>
                                </div>
                            </Field>
                            <fieldset className="grid gap-3">
                                <legend className="mb-1 text-sm font-medium">
                                    Сопоставление полей
                                </legend>
                                <p className="text-xs text-muted-foreground">
                                    Без сопоставления передаются все поля формы,
                                    автомобиль, цена и метки UTM в стандартном
                                    формате.
                                </p>
                                {form.data.mapping.map((rule, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-2 rounded-md border p-3 sm:grid-cols-2"
                                    >
                                        <TextField
                                            id={`${prefix}-map-${index}-target`}
                                            label="Поле в системе"
                                            placeholder="telephone"
                                            value={rule.target}
                                            maxLength={255}
                                            onChange={(event) =>
                                                setRule(index, {
                                                    target: event.target.value,
                                                })
                                            }
                                            required
                                            error={
                                                errors[
                                                    `mapping.${index}.target`
                                                ]
                                            }
                                        />
                                        <Field
                                            id={`${prefix}-map-${index}-source`}
                                            label="Значение из Landflow"
                                            error={
                                                errors[
                                                    `mapping.${index}.source`
                                                ]
                                            }
                                        >
                                            <NativeSelect
                                                id={`${prefix}-map-${index}-source`}
                                                value={rule.source}
                                                onChange={(event) =>
                                                    setRule(index, {
                                                        source: event.target
                                                            .value,
                                                    })
                                                }
                                                required
                                            >
                                                <option value="">
                                                    Выберите…
                                                </option>
                                                {sourceGroups.map((group) => (
                                                    <optgroup
                                                        key={group.group}
                                                        label={group.group}
                                                    >
                                                        {group.options.map(
                                                            (option) => (
                                                                <option
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </optgroup>
                                                ))}
                                                <option value="constant">
                                                    Постоянное значение
                                                </option>
                                            </NativeSelect>
                                        </Field>
                                        {rule.source === 'constant' && (
                                            <TextField
                                                id={`${prefix}-map-${index}-value`}
                                                label="Постоянное значение"
                                                value={rule.value ?? ''}
                                                maxLength={255}
                                                onChange={(event) =>
                                                    setRule(index, {
                                                        value: event.target
                                                            .value,
                                                    })
                                                }
                                                required
                                                error={
                                                    errors[
                                                        `mapping.${index}.value`
                                                    ]
                                                }
                                            />
                                        )}
                                        <SelectField
                                            id={`${prefix}-map-${index}-missing`}
                                            label="Если значения нет"
                                            choices={missingChoices}
                                            value={rule.missing}
                                            onChange={(event) =>
                                                setRule(index, {
                                                    missing: event.target.value,
                                                })
                                            }
                                            error={
                                                errors[
                                                    `mapping.${index}.missing`
                                                ]
                                            }
                                        />
                                        <div className="sm:col-span-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'mapping',
                                                        form.data.mapping.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                                {`Удалить поле ${index + 1}`}
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                <InputError message={form.errors.mapping} />
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            form.data.mapping.length >= 50
                                        }
                                        onClick={() =>
                                            form.setData('mapping', [
                                                ...form.data.mapping,
                                                {
                                                    target: '',
                                                    source: '',
                                                    missing: 'omit',
                                                },
                                            ])
                                        }
                                    >
                                        <Plus aria-hidden="true" />
                                        Добавить поле
                                    </Button>
                                </div>
                            </fieldset>
                        </>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {route ? (
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
                                    Удалить маршрут
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
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    (!route &&
                                        !isEmail &&
                                        typeBindings.length === 0)
                                }
                            >
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
