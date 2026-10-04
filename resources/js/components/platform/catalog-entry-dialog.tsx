import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/platform/catalog/entries';
import type { CatalogItem, CatalogLevelKey } from '@/types/catalog';

export type CatalogChoices = {
    engine: Choice[];
    transmission: Choice[];
    drive: Choice[];
};

type FieldSpec = {
    name: string;
    label: string;
    kind?: 'text' | 'number' | 'decimal' | 'select';
    required?: boolean;
    maxLength?: number;
    hint?: string;
    choices?: keyof CatalogChoices | 'group';
};

const urlField: FieldSpec = {
    name: 'url',
    label: 'Сегмент адреса',
    required: true,
    maxLength: 160,
    hint: 'Латиница в нижнем регистре, цифры и дефисы, например rio.',
};

const yearFields: FieldSpec[] = [
    { name: 'year_from', label: 'Начало выпуска', kind: 'number' },
    { name: 'year_to', label: 'Конец выпуска', kind: 'number' },
];

const levelFields: Record<CatalogLevelKey, FieldSpec[]> = {
    marks: [
        { name: 'name_ru', label: 'Русское название', maxLength: 255 },
        urlField,
        { name: 'country', label: 'Страна', maxLength: 100 },
        { name: 'logo_min', label: 'Логотип (малый)', maxLength: 1024 },
        { name: 'logo_big', label: 'Логотип (большой)', maxLength: 1024 },
    ],
    models: [
        { name: 'name_ru', label: 'Русское название', maxLength: 255 },
        urlField,
        { name: 'class', label: 'Класс', maxLength: 32 },
        ...yearFields,
        {
            name: 'group',
            label: 'Группа моделей',
            kind: 'select',
            choices: 'group',
        },
    ],
    generations: [urlField, ...yearFields],
    series: [
        urlField,
        { name: 'image', label: 'Изображение', maxLength: 1024 },
    ],
    modifications: [
        {
            name: 'engine_volume',
            label: 'Объём двигателя, см³',
            kind: 'number',
        },
        { name: 'engine_power', label: 'Мощность, л.с.', kind: 'decimal' },
        {
            name: 'engine',
            label: 'Тип двигателя',
            kind: 'select',
            choices: 'engine',
        },
        {
            name: 'transmission',
            label: 'Коробка передач',
            kind: 'select',
            choices: 'transmission',
        },
        { name: 'drive', label: 'Привод', kind: 'select', choices: 'drive' },
        {
            name: 'consumption_100_km',
            label: 'Расход, л/100 км',
            kind: 'decimal',
        },
        {
            name: 'acceleration_0_100',
            label: 'Разгон 0–100 км/ч, с',
            kind: 'decimal',
        },
    ],
    equipments: [],
};

type CatalogEntryDialogProps = {
    level: CatalogLevelKey;
    levelLabel: string;
    parent: string | null;
    entry?: CatalogItem;
    choices: CatalogChoices;
    groupChoices?: Choice[];
    trigger: ReactNode;
};

function stringValue(value: CatalogItem[string] | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

export function CatalogEntryDialog({
    level,
    levelLabel,
    parent,
    entry,
    choices,
    groupChoices = [],
    trigger,
}: CatalogEntryDialogProps) {
    const [open, setOpen] = useState(false);
    const formAction = entry
        ? update.form({ level, entry: entry.public_id })
        : store.form(level);
    const prefix = `${level}-${entry?.public_id ?? 'new'}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {entry ? `Изменить: ${entry.name}` : 'Новая запись'}
                    </DialogTitle>
                    <DialogDescription>{levelLabel}</DialogDescription>
                </DialogHeader>

                <Form
                    {...formAction}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {!entry && parent && (
                                <input
                                    type="hidden"
                                    name="parent"
                                    value={parent}
                                />
                            )}
                            <InputError message={errors.parent} />

                            <TextField
                                id={`${prefix}-name`}
                                name="name"
                                label="Название"
                                required
                                maxLength={255}
                                autoComplete="off"
                                defaultValue={entry?.name ?? ''}
                                error={errors.name}
                            />

                            {levelFields[level].map((field) => {
                                const id = `${prefix}-${field.name}`;
                                const defaultValue = stringValue(
                                    entry?.[field.name],
                                );

                                if (field.kind === 'select') {
                                    const options =
                                        field.choices === 'group'
                                            ? groupChoices.filter(
                                                  (choice) =>
                                                      choice.value !==
                                                      entry?.public_id,
                                              )
                                            : choices[
                                                  field.choices ?? 'engine'
                                              ];

                                    return (
                                        <SelectField
                                            key={field.name}
                                            id={id}
                                            name={field.name}
                                            label={field.label}
                                            choices={options}
                                            emptyLabel="Не указано"
                                            defaultValue={defaultValue}
                                            error={errors[field.name]}
                                        />
                                    );
                                }

                                return (
                                    <TextField
                                        key={field.name}
                                        id={id}
                                        name={field.name}
                                        label={field.label}
                                        hint={field.hint}
                                        required={field.required}
                                        maxLength={field.maxLength}
                                        type={
                                            field.kind === 'number'
                                                ? 'number'
                                                : 'text'
                                        }
                                        inputMode={
                                            field.kind === 'decimal'
                                                ? 'decimal'
                                                : undefined
                                        }
                                        autoComplete="off"
                                        defaultValue={defaultValue}
                                        error={errors[field.name]}
                                    />
                                );
                            })}

                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    id={`${prefix}-status`}
                                    name="status"
                                    label="Активность"
                                    choices={statusChoices}
                                    defaultValue={
                                        entry && !entry.status ? '0' : '1'
                                    }
                                    error={errors.status}
                                />
                                <TextField
                                    id={`${prefix}-sort_order`}
                                    name="sort_order"
                                    label="Сортировка"
                                    type="number"
                                    min={0}
                                    required
                                    defaultValue={String(
                                        entry?.sort_order ?? 0,
                                    )}
                                    error={errors.sort_order}
                                />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Отмена
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Сохранить
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
