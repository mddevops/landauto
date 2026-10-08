import { ArrowDown, ArrowUp, ChevronDown, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import type { SchemaField, SchemaFieldType } from '@/blocks/schema';
import { NativeSelect } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export const fieldTypeLabels: Record<SchemaFieldType, string> = {
    text: 'Строка',
    textarea: 'Многострочный текст',
    number: 'Число',
    boolean: 'Переключатель',
    select: 'Список',
    image: 'Изображение',
    action: 'Действие',
    vehicle: 'Автомобиль',
    group: 'Группа',
    repeater: 'Повторитель',
};

const builderTypes: SchemaFieldType[] = [
    'text',
    'textarea',
    'number',
    'boolean',
    'select',
    'image',
    'action',
    'group',
    'repeater',
];

const requirable: SchemaFieldType[] = [
    'text',
    'textarea',
    'number',
    'select',
    'image',
    'action',
    'vehicle',
];

type SchemaObject = Record<string, unknown> & { fields: SchemaField[] };

/** The builder edits the parsed canonical schema; unknown keys are kept and reported by the validator. */
export function parseSchema(source: string): SchemaObject | null {
    try {
        const parsed: unknown = JSON.parse(source);

        if (
            parsed !== null &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed) &&
            Array.isArray((parsed as { fields?: unknown }).fields)
        ) {
            return parsed as SchemaObject;
        }
    } catch {
        return null;
    }

    return null;
}

function uniqueKey(fields: SchemaField[], base: string): string {
    const keys = new Set(fields.map((field) => field.key));
    let index = fields.length + 1;

    while (keys.has(`${base}_${index}`)) {
        index++;
    }

    return `${base}_${index}`;
}

function newField(type: SchemaFieldType, siblings: SchemaField[]): SchemaField {
    const field: SchemaField = {
        key: uniqueKey(siblings, type === 'repeater' ? 'items' : 'field'),
        type,
        label: 'Новое поле',
    };

    if (type === 'select') {
        field.options = [{ value: 'option_1', label: 'Вариант 1' }];
    }

    if (type === 'group' || type === 'repeater') {
        field.fields = [{ key: 'title', type: 'text', label: 'Заголовок' }];
    }

    if (type === 'repeater') {
        field.max_items = 6;
    }

    return field;
}

function setOption(
    field: SchemaField,
    key: keyof SchemaField,
    value: unknown,
): SchemaField {
    const next = { ...field } as Record<string, unknown>;

    if (value === undefined || value === '') {
        delete next[key];
    } else {
        next[key] = value;
    }

    return next as SchemaField;
}

function parseNumber(raw: string): number | undefined {
    if (raw.trim() === '') {
        return undefined;
    }

    const value = Number(raw);

    return Number.isFinite(value) ? value : undefined;
}

export function SchemaBuilder({
    source,
    onChange,
    onOpenSource,
}: {
    source: string;
    onChange: (source: string) => void;
    onOpenSource: () => void;
}) {
    const schema = parseSchema(source);

    if (schema === null) {
        return (
            <div
                role="alert"
                className="space-y-3 rounded-xl border border-dashed p-6 text-sm"
            >
                <p>
                    Конструктор открывается только для корректного JSON вида{' '}
                    <code>{'{"fields": [...]}'}</code>. Исправьте schema.json в
                    режиме «Код».
                </p>
                <Button type="button" variant="outline" onClick={onOpenSource}>
                    Открыть schema.json
                </Button>
            </div>
        );
    }

    return (
        <FieldListEditor
            fields={schema.fields}
            path="fields"
            depth={0}
            onChange={(fields) =>
                onChange(JSON.stringify({ ...schema, fields }, null, 2))
            }
        />
    );
}

function FieldListEditor({
    fields,
    path,
    depth,
    onChange,
}: {
    fields: SchemaField[];
    path: string;
    depth: number;
    onChange: (fields: SchemaField[]) => void;
}) {
    const [type, setType] = useState<SchemaFieldType>('text');
    const id = useId();

    const replace = (index: number, field: SchemaField) =>
        onChange(fields.map((item, i) => (i === index ? field : item)));
    const move = (index: number, offset: number) => {
        const next = [...fields];
        const [field] = next.splice(index, 1);
        next.splice(index + offset, 0, field);
        onChange(next);
    };

    return (
        <div className="grid gap-3">
            {fields.length === 0 && (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Полей пока нет.
                </p>
            )}
            <ol className="grid gap-3">
                {fields.map((field, index) => (
                    <li key={index}>
                        <FieldEditor
                            field={field}
                            path={`${path}.${index}`}
                            depth={depth}
                            isFirst={index === 0}
                            isLast={index === fields.length - 1}
                            onChange={(next) => replace(index, next)}
                            onMove={(offset) => move(index, offset)}
                            onRemove={() =>
                                onChange(fields.filter((_, i) => i !== index))
                            }
                        />
                    </li>
                ))}
            </ol>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div className="grid flex-1 gap-1.5">
                    <Label htmlFor={`${id}-type`}>
                        {depth === 0
                            ? 'Тип нового поля'
                            : 'Тип нового вложенного поля'}
                    </Label>
                    <NativeSelect
                        id={`${id}-type`}
                        value={type}
                        onChange={(event) =>
                            setType(event.target.value as SchemaFieldType)
                        }
                    >
                        {builderTypes.map((value) => (
                            <option key={value} value={value}>
                                {fieldTypeLabels[value]}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        onChange([...fields, newField(type, fields)])
                    }
                >
                    <Plus aria-hidden="true" />
                    {depth === 0 ? 'Добавить поле' : 'Добавить вложенное поле'}
                </Button>
            </div>
        </div>
    );
}

function FieldEditor({
    field,
    path,
    depth,
    isFirst,
    isLast,
    onChange,
    onMove,
    onRemove,
}: {
    field: SchemaField;
    path: string;
    depth: number;
    isFirst: boolean;
    isLast: boolean;
    onChange: (field: SchemaField) => void;
    onMove: (offset: number) => void;
    onRemove: () => void;
}) {
    const [open, setOpen] = useState(false);
    const id = `sb-${path.replaceAll('.', '-')}`;
    const name = field.label || field.key;
    const set = (key: keyof SchemaField, value: unknown) =>
        onChange(setOption(field, key, value));

    return (
        <section
            aria-label={`Поле «${name}»`}
            data-testid="schema-field"
            className="rounded-xl border bg-card"
        >
            <div className="flex flex-wrap items-center gap-2 p-3">
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={`${id}-body`}
                    onClick={() => setOpen(!open)}
                    className="flex min-w-0 flex-1 items-center gap-2 rounded-md text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <ChevronDown
                        aria-hidden="true"
                        className={cn(
                            'size-4 shrink-0 transition-transform',
                            !open && '-rotate-90',
                        )}
                    />
                    <span className="min-w-0 break-words">
                        <span className="font-medium">{name}</span>{' '}
                        <code className="text-xs text-muted-foreground">
                            {field.key}
                        </code>
                    </span>
                </button>
                <Badge variant="outline">
                    {fieldTypeLabels[field.type] ?? field.type}
                </Badge>
                <div className="flex gap-1">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        disabled={isFirst}
                        aria-label={`Переместить поле «${name}» выше`}
                        onClick={() => onMove(-1)}
                    >
                        <ArrowUp aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        disabled={isLast}
                        aria-label={`Переместить поле «${name}» ниже`}
                        onClick={() => onMove(1)}
                    >
                        <ArrowDown aria-hidden="true" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={`Удалить поле «${name}»`}
                        onClick={onRemove}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            </div>

            {open && (
                <div
                    id={`${id}-body`}
                    className="grid gap-4 border-t p-3 sm:grid-cols-2"
                >
                    <TextInput
                        id={`${id}-key`}
                        label="Ключ"
                        value={field.key}
                        mono
                        hint="Латиница, цифры и _; используется в шаблоне."
                        onChange={(value) => onChange({ ...field, key: value })}
                    />
                    <TextInput
                        id={`${id}-label`}
                        label="Подпись"
                        value={field.label}
                        onChange={(value) =>
                            onChange({ ...field, label: value })
                        }
                    />
                    <div className="sm:col-span-2">
                        <TextInput
                            id={`${id}-help`}
                            label="Подсказка"
                            value={field.help ?? ''}
                            onChange={(value) => set('help', value)}
                        />
                    </div>
                    {requirable.includes(field.type) && (
                        <CheckboxInput
                            id={`${id}-required`}
                            label="Обязательное поле"
                            checked={field.required === true}
                            onChange={(checked) =>
                                set('required', checked ? true : undefined)
                            }
                        />
                    )}
                    <TypeOptions
                        field={field}
                        id={id}
                        set={set}
                        onChange={onChange}
                    />
                    {(field.type === 'group' || field.type === 'repeater') && (
                        <fieldset className="grid gap-3 rounded-lg border p-3 sm:col-span-2">
                            <legend className="px-1 text-sm font-medium">
                                Вложенные поля
                            </legend>
                            <FieldListEditor
                                fields={field.fields ?? []}
                                path={`${path}.fields`}
                                depth={depth + 1}
                                onChange={(fields) =>
                                    onChange({ ...field, fields })
                                }
                            />
                        </fieldset>
                    )}
                </div>
            )}
        </section>
    );
}

function TypeOptions({
    field,
    id,
    set,
    onChange,
}: {
    field: SchemaField;
    id: string;
    set: (key: keyof SchemaField, value: unknown) => void;
    onChange: (field: SchemaField) => void;
}) {
    switch (field.type) {
        case 'text':
        case 'textarea':
            return (
                <>
                    <NumberInput
                        id={`${id}-max-length`}
                        label="Максимальная длина"
                        value={field.max_length}
                        onChange={(value) => set('max_length', value)}
                    />
                    <TextInput
                        id={`${id}-default`}
                        label="Значение по умолчанию"
                        value={
                            typeof field.default === 'string'
                                ? field.default
                                : ''
                        }
                        onChange={(value) => set('default', value)}
                    />
                </>
            );
        case 'number':
            return (
                <>
                    <NumberInput
                        id={`${id}-min`}
                        label="Минимум"
                        value={field.min}
                        onChange={(value) => set('min', value)}
                    />
                    <NumberInput
                        id={`${id}-max`}
                        label="Максимум"
                        value={field.max}
                        onChange={(value) => set('max', value)}
                    />
                    <NumberInput
                        id={`${id}-step`}
                        label="Шаг"
                        value={field.step}
                        onChange={(value) => set('step', value)}
                    />
                    <NumberInput
                        id={`${id}-default`}
                        label="Значение по умолчанию"
                        value={
                            typeof field.default === 'number'
                                ? field.default
                                : undefined
                        }
                        onChange={(value) => set('default', value)}
                    />
                </>
            );
        case 'boolean':
            return (
                <CheckboxInput
                    id={`${id}-default`}
                    label="Включено по умолчанию"
                    checked={field.default === true}
                    onChange={(checked) => set('default', checked)}
                />
            );
        case 'select':
            return <SelectOptions field={field} id={id} onChange={onChange} />;
        case 'repeater':
            return (
                <>
                    <NumberInput
                        id={`${id}-min-items`}
                        label="Минимум элементов"
                        value={field.min_items}
                        onChange={(value) => set('min_items', value)}
                    />
                    <NumberInput
                        id={`${id}-max-items`}
                        label="Максимум элементов"
                        value={field.max_items}
                        onChange={(value) => set('max_items', value)}
                    />
                </>
            );
        default:
            return null;
    }
}

function SelectOptions({
    field,
    id,
    onChange,
}: {
    field: SchemaField;
    id: string;
    onChange: (field: SchemaField) => void;
}) {
    const options = field.options ?? [];
    const update = (next: { value: string; label: string }[]) =>
        onChange({ ...field, options: next });

    return (
        <fieldset className="grid gap-3 rounded-lg border p-3 sm:col-span-2">
            <legend className="px-1 text-sm font-medium">Варианты</legend>
            {options.map((option, index) => (
                <div
                    key={index}
                    className="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                >
                    <TextInput
                        id={`${id}-option-${index}-value`}
                        label={`Значение варианта ${index + 1}`}
                        value={option.value}
                        mono
                        onChange={(value) =>
                            update(
                                options.map((item, i) =>
                                    i === index ? { ...item, value } : item,
                                ),
                            )
                        }
                    />
                    <TextInput
                        id={`${id}-option-${index}-label`}
                        label={`Подпись варианта ${index + 1}`}
                        value={option.label}
                        onChange={(label) =>
                            update(
                                options.map((item, i) =>
                                    i === index ? { ...item, label } : item,
                                ),
                            )
                        }
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Удалить вариант ${index + 1}`}
                        onClick={() =>
                            update(options.filter((_, i) => i !== index))
                        }
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                className="justify-self-start"
                onClick={() =>
                    update([
                        ...options,
                        {
                            value: `option_${options.length + 1}`,
                            label: `Вариант ${options.length + 1}`,
                        },
                    ])
                }
            >
                <Plus aria-hidden="true" />
                Добавить вариант
            </Button>
            <div className="grid gap-1.5">
                <Label htmlFor={`${id}-default`}>Значение по умолчанию</Label>
                <NativeSelect
                    id={`${id}-default`}
                    value={
                        typeof field.default === 'string' ? field.default : ''
                    }
                    onChange={(event) =>
                        onChange(
                            setOption(
                                field,
                                'default',
                                event.target.value || undefined,
                            ),
                        )
                    }
                >
                    <option value="">Не выбрано</option>
                    {options.map((option, index) => (
                        <option key={index} value={option.value}>
                            {option.label || option.value}
                        </option>
                    ))}
                </NativeSelect>
            </div>
        </fieldset>
    );
}

function InputShell({
    id,
    label,
    hint,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

function TextInput({
    id,
    label,
    value,
    hint,
    mono,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    hint?: string;
    mono?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <InputShell id={id} label={label} hint={hint}>
            <Input
                id={id}
                value={value}
                autoComplete="off"
                spellCheck={mono ? false : undefined}
                className={cn(mono && 'font-mono')}
                onChange={(event) => onChange(event.target.value)}
            />
        </InputShell>
    );
}

function NumberInput({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: number | undefined;
    onChange: (value: number | undefined) => void;
}) {
    // Keeps partial input such as "1." while the canonical value stays numeric.
    const [raw, setRaw] = useState(value === undefined ? '' : String(value));
    const display =
        parseNumber(raw) === value
            ? raw
            : value === undefined
              ? ''
              : String(value);

    return (
        <InputShell id={id} label={label}>
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                step="any"
                value={display}
                onChange={(event) => {
                    setRaw(event.target.value);
                    onChange(parseNumber(event.target.value));
                }}
            />
        </InputShell>
    );
}

function CheckboxInput({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-2 self-end pb-2">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
            />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}
