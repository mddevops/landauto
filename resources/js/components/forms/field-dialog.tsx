import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { FormFieldType } from '@/blocks/form';
import type { Choice } from '@/components/platform/form-fields';
import {
    Field,
    SelectField,
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
import { store, update } from '@/routes/sites/forms/fields';

export type ManagedField = {
    key: string;
    type: FormFieldType;
    label: string;
    placeholder: string | null;
    default_value: string | null;
    required: boolean;
    max_length: number | null;
    options: string[];
};

const textareaClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive';

const suggestedKeys: Partial<Record<FormFieldType, string>> = {
    phone: 'phone',
    email: 'email',
    textarea: 'comment',
    consent: 'consent',
};

export function FieldDialog({
    sitePublicId,
    formPublicId,
    fieldTypes,
    field,
    trigger,
}: {
    sitePublicId: string;
    formPublicId: string;
    fieldTypes: Choice[];
    field?: ManagedField;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const prefix = `field-${field?.key ?? 'new'}`;
    const form = useForm({
        key: field?.key ?? '',
        type: (field?.type ?? 'text') as FormFieldType,
        label: field?.label ?? '',
        placeholder: field?.placeholder ?? '',
        default_value: field?.default_value ?? '',
        required: field?.required ?? false,
        max_length: field?.max_length ? String(field.max_length) : '',
        options: (field?.options ?? []).join('\n'),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const type = form.data.type;
    const isBoolean = type === 'checkbox' || type === 'consent';

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => {
            const payload = {
                label: data.label,
                placeholder: data.placeholder,
                default_value: data.default_value,
                required: data.required,
                max_length: data.max_length,
                options:
                    data.type === 'select'
                        ? data.options
                              .split('\n')
                              .map((option) => option.trim())
                              .filter((option) => option !== '')
                        : [],
            };

            return field
                ? payload
                : { ...payload, key: data.key, type: data.type };
        });
        form.submit(
            field
                ? update({
                      site: sitePublicId,
                      form: formPublicId,
                      field: field.key,
                  })
                : store({ site: sitePublicId, form: formPublicId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!field) {
                        form.reset();
                    }
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {field ? `Поле «${field.label}»` : 'Новое поле'}
                    </DialogTitle>
                    <DialogDescription>
                        Ключ — постоянное техническое имя поля для выгрузок и
                        интеграций. После создания его нельзя изменить.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    {!field && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                id={`${prefix}-type`}
                                label="Тип"
                                choices={fieldTypes}
                                value={type}
                                onChange={(event) => {
                                    const next = event.target
                                        .value as FormFieldType;
                                    form.setData((data) => ({
                                        ...data,
                                        type: next,
                                        key:
                                            data.key === '' ||
                                            Object.values(
                                                suggestedKeys,
                                            ).includes(data.key)
                                                ? (suggestedKeys[next] ?? '')
                                                : data.key,
                                        required:
                                            next === 'consent'
                                                ? true
                                                : data.required,
                                    }));
                                }}
                                error={errors.type}
                            />
                            <TextField
                                id={`${prefix}-key`}
                                label="Ключ"
                                hint="Латиница в нижнем регистре, цифры и «_», например phone."
                                value={form.data.key}
                                onChange={(event) =>
                                    form.setData('key', event.target.value)
                                }
                                maxLength={40}
                                pattern="[a-z][a-z0-9_]*"
                                autoComplete="off"
                                required
                                error={errors.key}
                            />
                        </div>
                    )}
                    {type === 'consent' ? (
                        <Field
                            id={`${prefix}-label`}
                            label="Текст согласия"
                            hint="Введите ваш текст согласия. Платформа не подставляет юридические формулировки."
                            error={errors.label}
                        >
                            <textarea
                                id={`${prefix}-label`}
                                value={form.data.label}
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                                maxLength={1000}
                                rows={4}
                                required
                                aria-invalid={Boolean(errors.label)}
                                className={textareaClass}
                            />
                        </Field>
                    ) : (
                        <TextField
                            id={`${prefix}-label`}
                            label="Подпись"
                            value={form.data.label}
                            onChange={(event) =>
                                form.setData('label', event.target.value)
                            }
                            maxLength={120}
                            required
                            error={errors.label}
                        />
                    )}
                    {!isBoolean && type !== 'hidden' && (
                        <TextField
                            id={`${prefix}-placeholder`}
                            label={
                                type === 'select'
                                    ? 'Текст пустого варианта'
                                    : 'Подсказка в поле'
                            }
                            value={form.data.placeholder}
                            onChange={(event) =>
                                form.setData('placeholder', event.target.value)
                            }
                            maxLength={120}
                            error={errors.placeholder}
                        />
                    )}
                    {type === 'hidden' && (
                        <TextField
                            id={`${prefix}-default`}
                            label="Значение"
                            hint="Передаётся вместе с заявкой как служебная пометка. Посетитель может его изменить, поэтому оно не считается достоверным."
                            value={form.data.default_value}
                            onChange={(event) =>
                                form.setData(
                                    'default_value',
                                    event.target.value,
                                )
                            }
                            maxLength={255}
                            error={errors.default_value}
                        />
                    )}
                    {type === 'select' && (
                        <Field
                            id={`${prefix}-options`}
                            label="Варианты (по одному в строке)"
                            error={
                                errors.options ??
                                Object.entries(errors).find(([key]) =>
                                    key.startsWith('options.'),
                                )?.[1]
                            }
                        >
                            <textarea
                                id={`${prefix}-options`}
                                value={form.data.options}
                                onChange={(event) =>
                                    form.setData('options', event.target.value)
                                }
                                rows={4}
                                className={textareaClass}
                            />
                        </Field>
                    )}
                    {(type === 'text' || type === 'textarea') && (
                        <TextField
                            id={`${prefix}-max-length`}
                            label="Максимальная длина"
                            inputMode="numeric"
                            value={form.data.max_length}
                            onChange={(event) =>
                                form.setData('max_length', event.target.value)
                            }
                            error={errors.max_length}
                        />
                    )}
                    {type !== 'hidden' && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`${prefix}-required`}
                                checked={form.data.required}
                                onCheckedChange={(checked) =>
                                    form.setData('required', checked === true)
                                }
                            />
                            <Label
                                htmlFor={`${prefix}-required`}
                                className="font-normal"
                            >
                                {isBoolean
                                    ? 'Обязательно отметить'
                                    : 'Обязательное поле'}
                            </Label>
                        </div>
                    )}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
