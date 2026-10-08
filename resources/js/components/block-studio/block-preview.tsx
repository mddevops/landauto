import {
    Monitor,
    Plus,
    RotateCcw,
    Smartphone,
    Tablet,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { SchemaField } from '@/blocks/schema';
import { SandboxFrame } from '@/components/sandbox/sandbox-frame';
import { NativeSelect } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    actionKeys,
    previewProps,
    resolvePreviewData,
} from '@/sandbox/preview-data';
import type { PreviewData } from '@/sandbox/preview-data';
import type { DraftSources } from '@/types/blocks';

const devices = [
    { value: 'desktop', label: 'Компьютер', width: null, icon: Monitor },
    { value: 'tablet', label: 'Планшет', width: 768, icon: Tablet },
    { value: 'mobile', label: 'Телефон', width: 375, icon: Smartphone },
] as const;

type Device = (typeof devices)[number]['value'];

const PREVIEW_DELAY_MS = 300;

function useDebounced<T>(value: T, delay: number): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

export function BlockPreview({
    sources,
    fields,
    preview,
    onPreviewChange,
}: {
    sources: DraftSources;
    fields: SchemaField[] | null;
    preview: PreviewData;
    onPreviewChange: (preview: PreviewData) => void;
}) {
    const [device, setDevice] = useState<Device>('desktop');
    const [errors, setErrors] = useState<string[]>([]);
    const [lastAction, setLastAction] = useState<string | null>(null);
    const schemaFields = useMemo(() => fields ?? [], [fields]);
    const data = useMemo(
        () => resolvePreviewData(schemaFields, preview),
        [schemaFields, preview],
    );
    const input = useMemo(
        () => ({ html: sources.html, css: sources.css, js: sources.js, data }),
        [sources.html, sources.css, sources.js, data],
    );
    const live = useDebounced(input, PREVIEW_DELAY_MS);
    const frameSources = useMemo(
        () => ({ html: live.html, css: live.css, js: live.js }),
        [live.html, live.css, live.js],
    );
    const props = useMemo(
        () => previewProps(schemaFields, live.data),
        [schemaFields, live.data],
    );
    const actions = useMemo(() => actionKeys(schemaFields), [schemaFields]);
    const width = devices.find((item) => item.value === device)?.width ?? null;

    useEffect(() => {
        setErrors([]);
    }, [frameSources, props]);

    const onError = useCallback(
        (message: string) =>
            setErrors((current) => [...current, message].slice(-10)),
        [],
    );

    return (
        <div className="grid min-w-0 gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div
                    role="group"
                    aria-label="Ширина предпросмотра"
                    className="flex gap-1 rounded-lg border p-1"
                >
                    {devices.map((item) => (
                        <Button
                            key={item.value}
                            type="button"
                            size="sm"
                            variant={
                                item.value === device ? 'secondary' : 'ghost'
                            }
                            aria-pressed={item.value === device}
                            onClick={() => setDevice(item.value)}
                        >
                            <item.icon aria-hidden="true" />
                            {item.label}
                        </Button>
                    ))}
                </div>
                {lastAction && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Вызвано действие «{lastAction}». В предпросмотре
                        действия не выполняются.
                    </p>
                )}
            </div>

            <div className="overflow-x-auto rounded-xl border bg-muted/30 p-2">
                <div
                    className="mx-auto bg-white"
                    style={{ width: width ?? '100%' }}
                    data-testid="preview-viewport"
                >
                    <SandboxFrame
                        title="Предпросмотр блока"
                        sources={frameSources}
                        props={props}
                        actions={actions}
                        onError={onError}
                        onAction={setLastAction}
                        className="block w-full border-0"
                    />
                </div>
            </div>

            {errors.length > 0 && (
                <section
                    aria-labelledby="preview-errors-title"
                    className="space-y-2 rounded-xl border border-destructive/40 p-4"
                >
                    <h3
                        id="preview-errors-title"
                        className="font-semibold text-destructive"
                    >
                        Ошибки выполнения
                    </h3>
                    <ul className="grid gap-1 text-sm">
                        {errors.map((error, index) => (
                            <li key={index} className="break-words">
                                {error}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section
                aria-labelledby="preview-data-title"
                className="space-y-4 rounded-xl border bg-card p-4 shadow-sm"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 id="preview-data-title" className="font-semibold">
                            Данные предпросмотра
                        </h3>
                        <p className="text-xs text-muted-foreground">
                            Только для студии; на сайтах блок получает данные
                            клиента. Изображения показываются заглушкой.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onPreviewChange({})}
                    >
                        <RotateCcw aria-hidden="true" />
                        Сбросить по схеме
                    </Button>
                </div>
                {fields === null ? (
                    <p className="text-sm text-muted-foreground">
                        Исправьте schema.json, чтобы заполнить данные
                        предпросмотра.
                    </p>
                ) : schemaFields.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        В схеме пока нет полей.
                    </p>
                ) : (
                    <PreviewFields
                        fields={schemaFields}
                        value={data}
                        path="preview"
                        onChange={onPreviewChange}
                    />
                )}
            </section>
        </div>
    );
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function PreviewFields({
    fields,
    value,
    path,
    onChange,
}: {
    fields: SchemaField[];
    value: PreviewData;
    path: string;
    onChange: (value: PreviewData) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {fields.map((field) => (
                <PreviewField
                    key={field.key}
                    field={field}
                    value={value[field.key]}
                    path={`${path}-${field.key}`}
                    onChange={(next) =>
                        onChange({ ...value, [field.key]: next })
                    }
                />
            ))}
        </div>
    );
}

function PreviewField({
    field,
    value,
    path,
    onChange,
}: {
    field: SchemaField;
    value: unknown;
    path: string;
    onChange: (value: unknown) => void;
}) {
    const id = path;

    switch (field.type) {
        case 'text':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <Input
                        id={id}
                        value={typeof value === 'string' ? value : ''}
                        onChange={(event) => onChange(event.target.value)}
                    />
                </div>
            );
        case 'textarea':
            return (
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor={id}>{field.label}</Label>
                    <textarea
                        id={id}
                        rows={3}
                        value={typeof value === 'string' ? value : ''}
                        onChange={(event) => onChange(event.target.value)}
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                </div>
            );
        case 'number':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <Input
                        id={id}
                        type="number"
                        inputMode="decimal"
                        min={field.min}
                        max={field.max}
                        step={field.step ?? 'any'}
                        value={typeof value === 'number' ? value : ''}
                        onChange={(event) =>
                            onChange(
                                Number.isNaN(event.target.valueAsNumber)
                                    ? undefined
                                    : event.target.valueAsNumber,
                            )
                        }
                    />
                </div>
            );
        case 'boolean':
            return (
                <div className="flex items-center gap-2 self-end pb-2">
                    <Checkbox
                        id={id}
                        checked={value === true}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                    />
                    <Label htmlFor={id}>{field.label}</Label>
                </div>
            );
        case 'select':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{field.label}</Label>
                    <NativeSelect
                        id={id}
                        value={typeof value === 'string' ? value : ''}
                        onChange={(event) => onChange(event.target.value)}
                    >
                        {(field.options ?? []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                </div>
            );
        case 'image':
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={id}>{`${field.label}: подпись`}</Label>
                    <Input
                        id={id}
                        value={
                            isRecord(value) && typeof value.alt === 'string'
                                ? value.alt
                                : ''
                        }
                        onChange={(event) =>
                            onChange({ alt: event.target.value })
                        }
                    />
                </div>
            );
        case 'group':
            return (
                <fieldset className="grid gap-3 rounded-lg border p-3 sm:col-span-2">
                    <legend className="px-1 text-sm font-medium">
                        {field.label}
                    </legend>
                    <PreviewFields
                        fields={field.fields ?? []}
                        value={isRecord(value) ? value : {}}
                        path={path}
                        onChange={onChange}
                    />
                </fieldset>
            );
        case 'repeater':
            return (
                <PreviewRepeater
                    field={field}
                    items={Array.isArray(value) ? value : []}
                    path={path}
                    onChange={onChange}
                />
            );
        default:
            return null;
    }
}

function PreviewRepeater({
    field,
    items,
    path,
    onChange,
}: {
    field: SchemaField;
    items: unknown[];
    path: string;
    onChange: (items: unknown[]) => void;
}) {
    const max = field.max_items ?? 6;

    return (
        <fieldset className="grid gap-3 rounded-lg border p-3 sm:col-span-2">
            <legend className="px-1 text-sm font-medium">{field.label}</legend>
            {items.map((item, index) => (
                <div
                    key={index}
                    className={cn('grid gap-2 rounded-md border p-3')}
                >
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-medium">
                            Элемент {index + 1}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Удалить элемент ${index + 1} из «${field.label}»`}
                            onClick={() =>
                                onChange(items.filter((_, i) => i !== index))
                            }
                        >
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                    <PreviewFields
                        fields={field.fields ?? []}
                        value={isRecord(item) ? item : {}}
                        path={`${path}-${index}`}
                        onChange={(next) =>
                            onChange(
                                items.map((current, i) =>
                                    i === index ? next : current,
                                ),
                            )
                        }
                    />
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="justify-self-start"
                disabled={items.length >= max}
                onClick={() => onChange([...items, {}])}
            >
                <Plus aria-hidden="true" />
                Добавить элемент
            </Button>
        </fieldset>
    );
}
