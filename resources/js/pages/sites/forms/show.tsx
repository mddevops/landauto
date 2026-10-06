import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import type { DesignTokens } from '@/blocks/design';
import { FormView } from '@/blocks/form';
import type { FormRuntime } from '@/blocks/form';
import { SiteTheme } from '@/blocks/theme';
import { FieldDialog } from '@/components/forms/field-dialog';
import type { ManagedField } from '@/components/forms/field-dialog';
import type { Choice } from '@/components/platform/form-fields';
import {
    Field,
    SelectField,
    statusChoices,
    TextField,
} from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index as formsIndex, update } from '@/routes/sites/forms';
import { destroy, order } from '@/routes/sites/forms/fields';
import { index as routesIndex } from '@/routes/sites/forms/routes';

type FormShowProps = {
    site: { public_id: string; name: string };
    design: DesignTokens;
    form: {
        public_id: string;
        name: string;
        status: boolean;
        submit_label: string;
        success_message: string;
    };
    fields: ManagedField[];
    runtime: FormRuntime;
    choices: { fieldTypes: Choice[] };
    can: { editForms: boolean; editFormRoutes: boolean };
};

export default function FormShow({
    site,
    design,
    form,
    fields,
    runtime,
    choices,
    can,
}: FormShowProps) {
    const settings = useForm({
        name: form.name,
        status: form.status ? '1' : '0',
        submit_label: form.submit_label,
        success_message: form.success_message,
    });
    const [pendingDelete, setPendingDelete] = useState<string | null>(null);
    const typeLabel = (value: string) =>
        choices.fieldTypes.find((choice) => choice.value === value)?.label ??
        value;

    function saveSettings(event: FormEvent) {
        event.preventDefault();
        settings.transform((data) => ({
            ...data,
            status: data.status === '1',
        }));
        settings.submit(
            update({ site: site.public_id, form: form.public_id }),
            { preserveScroll: true },
        );
    }

    function move(index: number, offset: number) {
        const keys = fields.map((field) => field.key);
        const [moved] = keys.splice(index, 1);
        keys.splice(index + offset, 0, moved);
        router.put(
            order.url({ site: site.public_id, form: form.public_id }),
            { keys },
            { preserveScroll: true },
        );
    }

    function remove(key: string) {
        router.delete(
            destroy.url({
                site: site.public_id,
                form: form.public_id,
                field: key,
            }),
            {
                preserveScroll: true,
                onFinish: () => setPendingDelete(null),
            },
        );
    }

    return (
        <>
            <Head title={`${form.name} — Формы`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        <Link
                            href={formsIndex(site.public_id)}
                            className="underline-offset-4 hover:underline"
                        >
                            {`${site.name} · Все формы`}
                        </Link>
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                            {form.name}
                        </h1>
                        {!form.status && (
                            <Badge variant="secondary">Выключена</Badge>
                        )}
                        {can.editFormRoutes && (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={routesIndex({
                                        site: site.public_id,
                                        form: form.public_id,
                                    })}
                                >
                                    Передача заявок
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <h2 className="leading-none font-semibold">
                                    Настройки
                                </h2>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={saveSettings}
                                    className="grid gap-4"
                                >
                                    <fieldset
                                        disabled={!can.editForms}
                                        className="grid gap-4 sm:grid-cols-2"
                                    >
                                        <legend className="sr-only">
                                            Настройки формы
                                        </legend>
                                        <TextField
                                            id="form-name"
                                            label="Название"
                                            value={settings.data.name}
                                            onChange={(event) =>
                                                settings.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={120}
                                            required
                                            error={settings.errors.name}
                                        />
                                        <SelectField
                                            id="form-status"
                                            label="Статус"
                                            choices={statusChoices}
                                            value={settings.data.status}
                                            onChange={(event) =>
                                                settings.setData(
                                                    'status',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <TextField
                                            id="form-submit-label"
                                            label="Текст кнопки"
                                            value={settings.data.submit_label}
                                            onChange={(event) =>
                                                settings.setData(
                                                    'submit_label',
                                                    event.target.value,
                                                )
                                            }
                                            maxLength={40}
                                            required
                                            error={settings.errors.submit_label}
                                        />
                                        <Field
                                            id="form-success"
                                            label="Сообщение после отправки"
                                            error={
                                                settings.errors.success_message
                                            }
                                        >
                                            <textarea
                                                id="form-success"
                                                value={
                                                    settings.data
                                                        .success_message
                                                }
                                                onChange={(event) =>
                                                    settings.setData(
                                                        'success_message',
                                                        event.target.value,
                                                    )
                                                }
                                                maxLength={500}
                                                rows={2}
                                                required
                                                className="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                        </Field>
                                    </fieldset>
                                    {can.editForms && (
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={settings.processing}
                                            >
                                                {settings.processing && (
                                                    <Spinner />
                                                )}
                                                Сохранить настройки
                                            </Button>
                                        </div>
                                    )}
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                                <div className="space-y-1.5">
                                    <h2 className="leading-none font-semibold">
                                        Поля
                                    </h2>
                                    <CardDescription>
                                        Порядок полей совпадает с порядком в
                                        форме на сайте.
                                    </CardDescription>
                                </div>
                                {can.editForms && (
                                    <FieldDialog
                                        sitePublicId={site.public_id}
                                        formPublicId={form.public_id}
                                        fieldTypes={choices.fieldTypes}
                                        trigger={
                                            <Button size="sm">
                                                <Plus aria-hidden="true" />
                                                Добавить поле
                                            </Button>
                                        }
                                    />
                                )}
                            </CardHeader>
                            <CardContent>
                                {fields.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Полей пока нет.
                                    </p>
                                ) : (
                                    <ol className="flex flex-col divide-y rounded-md border">
                                        {fields.map((field, index) => (
                                            <li
                                                key={field.key}
                                                className="flex flex-wrap items-center gap-3 p-3"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate font-medium">
                                                        {field.label}
                                                        {field.required && (
                                                            <span className="text-muted-foreground">
                                                                {
                                                                    ' · обязательное'
                                                                }
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {`${typeLabel(field.type)} · ключ `}
                                                        <code>{field.key}</code>
                                                    </p>
                                                </div>
                                                {can.editForms && (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onClick={() =>
                                                                move(index, -1)
                                                            }
                                                            aria-label={`Переместить поле «${field.label}» выше`}
                                                        >
                                                            <ArrowUp aria-hidden="true" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled={
                                                                index ===
                                                                fields.length -
                                                                    1
                                                            }
                                                            onClick={() =>
                                                                move(index, 1)
                                                            }
                                                            aria-label={`Переместить поле «${field.label}» ниже`}
                                                        >
                                                            <ArrowDown aria-hidden="true" />
                                                        </Button>
                                                        <FieldDialog
                                                            sitePublicId={
                                                                site.public_id
                                                            }
                                                            formPublicId={
                                                                form.public_id
                                                            }
                                                            fieldTypes={
                                                                choices.fieldTypes
                                                            }
                                                            field={field}
                                                            trigger={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Изменить поле «${field.label}»`}
                                                                >
                                                                    <Pencil aria-hidden="true" />
                                                                </Button>
                                                            }
                                                        />
                                                        {pendingDelete ===
                                                        field.key ? (
                                                            <Button
                                                                variant="destructive"
                                                                size="sm"
                                                                onClick={() =>
                                                                    remove(
                                                                        field.key,
                                                                    )
                                                                }
                                                            >
                                                                Удалить
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        field.key,
                                                                    )
                                                                }
                                                                aria-label={`Удалить поле «${field.label}»`}
                                                            >
                                                                <Trash2 aria-hidden="true" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <h2 className="leading-none font-semibold">
                                Как выглядит форма
                            </h2>
                            <CardDescription>
                                Сохранённая версия формы.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SiteTheme tokens={design}>
                                <div className="rounded-md border bg-white p-4">
                                    <FormView
                                        key={JSON.stringify(runtime)}
                                        form={runtime}
                                    />
                                </div>
                            </SiteTheme>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}

FormShow.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
