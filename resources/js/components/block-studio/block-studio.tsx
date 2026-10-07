import { Form } from '@inertiajs/react';
import { CircleCheck, FileCode2, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    formatBlockDate,
    versionsLabel,
} from '@/components/block-authoring/block-list';
import { BlockPreview } from '@/components/block-studio/block-preview';
import {
    parseSchema,
    SchemaBuilder,
} from '@/components/block-studio/schema-builder';
import { useDraftAutosave } from '@/components/block-studio/use-draft-autosave';
import type { DraftSaveStatus } from '@/components/block-studio/use-draft-autosave';
import {
    Field,
    SelectField,
    TextField,
} from '@/components/platform/form-fields';
import type { Choice } from '@/components/platform/form-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type {
    AuthoringBlockDetail,
    BlockDraft,
    DraftSourceKey,
} from '@/types/blocks';
import type { RouteFormDefinition } from '@/wayfinder';

type Mode = 'code' | 'schema' | 'preview' | 'settings';

const modes: { value: Mode; label: string }[] = [
    { value: 'code', label: 'Код' },
    { value: 'schema', label: 'Конструктор схемы' },
    { value: 'preview', label: 'Предпросмотр' },
    { value: 'settings', label: 'Настройки' },
];

const files: { key: DraftSourceKey; name: string }[] = [
    { key: 'html', name: 'index.html' },
    { key: 'css', name: 'styles.css' },
    { key: 'js', name: 'script.js' },
    { key: 'schema', name: 'schema.json' },
];

const statusLabels: Record<DraftSaveStatus, string> = {
    saved: 'Черновик сохранён',
    pending: 'Есть несохранённые изменения',
    saving: 'Сохранение…',
    error: 'Не удалось сохранить черновик',
    conflict: 'Черновик изменён в другом месте',
};

const encoder = new TextEncoder();

function byteSize(value: string): number {
    return encoder.encode(value).length;
}

function formatKilobytes(bytes: number): string {
    return `${(bytes / 1024).toLocaleString('ru-RU', { maximumFractionDigits: 1 })} КБ`;
}

type BlockStudioProps = {
    block: AuthoringBlockDetail;
    draft: BlockDraft;
    categories: Choice[];
    sourceMaxBytes: number;
    metadataAction: RouteFormDefinition<'post'>;
    draftUrl: string;
};

export function BlockStudio({
    block,
    draft,
    categories,
    sourceMaxBytes,
    metadataAction,
    draftUrl,
}: BlockStudioProps) {
    const autosave = useDraftAutosave(draftUrl, draft);
    const { sources, status, errors } = autosave;
    const [mode, setMode] = useState<Mode>('code');
    const [file, setFile] = useState<DraftSourceKey>('html');
    const current = files.find((item) => item.key === file) ?? files[0];
    const schema = useMemo(() => parseSchema(sources.schema), [sources.schema]);

    return (
        <main className="flex min-w-0 flex-1 flex-col gap-4 p-4 sm:p-6">
            <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0 space-y-2">
                    <p className="text-sm text-muted-foreground">
                        Студия блоков
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="min-w-0 text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                            {block.name}
                        </h1>
                        <Badge variant="secondary">
                            {block.category_label}
                        </Badge>
                        <Badge variant="outline">
                            {versionsLabel(block.versions_count)}
                        </Badge>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <p
                        role="status"
                        aria-live="polite"
                        data-status={status}
                        className={cn(
                            'text-sm',
                            status === 'error' || status === 'conflict'
                                ? 'font-medium text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {statusLabels[status]}
                    </p>
                    <Button
                        type="button"
                        onClick={autosave.saveNow}
                        disabled={
                            status === 'saved' ||
                            status === 'saving' ||
                            status === 'conflict'
                        }
                    >
                        {status === 'saving' && <Spinner />}
                        Сохранить черновик
                    </Button>
                </div>
            </header>

            {errors.draft && (
                <Alert variant="destructive">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>Сохранение остановлено</AlertTitle>
                    <AlertDescription>
                        <p>{errors.draft}</p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-2"
                            onClick={() => window.location.reload()}
                        >
                            Обновить страницу
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            <div
                role="tablist"
                aria-label="Режимы студии"
                className="flex overflow-x-auto border-b"
            >
                {modes.map((tab) => (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        id={`studio-tab-${tab.value}`}
                        aria-selected={tab.value === mode}
                        aria-controls={`studio-panel-${tab.value}`}
                        onClick={() => setMode(tab.value)}
                        className={cn(
                            'shrink-0 border-b-2 border-transparent px-3 py-2.5 text-sm whitespace-nowrap text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                            tab.value === mode &&
                                'border-primary font-medium text-foreground',
                        )}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            <div className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div
                    role="tabpanel"
                    id={`studio-panel-${mode}`}
                    aria-labelledby={`studio-tab-${mode}`}
                    className="min-w-0"
                >
                    {mode === 'code' && (
                        <div className="grid min-w-0 gap-4 md:grid-cols-[12rem_minmax(0,1fr)]">
                            <nav aria-label="Файлы блока">
                                <ul className="flex flex-wrap gap-2 md:flex-col">
                                    {files.map((item) => (
                                        <li key={item.key} className="shrink-0">
                                            <button
                                                type="button"
                                                aria-pressed={item.key === file}
                                                onClick={() =>
                                                    setFile(item.key)
                                                }
                                                className={cn(
                                                    'flex w-full items-center gap-2 rounded-md border px-3 py-2 text-left font-mono text-sm outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                    item.key === file &&
                                                        'border-primary bg-muted',
                                                )}
                                            >
                                                <FileCode2
                                                    aria-hidden="true"
                                                    className="size-4 shrink-0"
                                                />
                                                {item.name}
                                                {errors[
                                                    `sources.${item.key}`
                                                ] && (
                                                    <TriangleAlert
                                                        aria-label="Ошибка"
                                                        className="ml-auto size-4 text-destructive"
                                                    />
                                                )}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                            <SourceEditor
                                key={current.key}
                                name={current.name}
                                value={sources[current.key]}
                                maxBytes={sourceMaxBytes}
                                error={errors[`sources.${current.key}`]}
                                onChange={(value) =>
                                    autosave.update(current.key, value)
                                }
                            />
                        </div>
                    )}

                    {mode === 'schema' && (
                        <SchemaBuilder
                            source={sources.schema}
                            onChange={(value) =>
                                autosave.update('schema', value)
                            }
                            onOpenSource={() => {
                                setFile('schema');
                                setMode('code');
                            }}
                        />
                    )}

                    {mode === 'preview' && (
                        <BlockPreview
                            sources={sources}
                            fields={schema?.fields ?? null}
                            preview={autosave.preview}
                            onPreviewChange={autosave.updatePreview}
                        />
                    )}

                    {mode === 'settings' && (
                        <BlockSettings
                            block={block}
                            categories={categories}
                            action={metadataAction}
                        />
                    )}
                </div>

                <DraftChecks
                    draft={draft}
                    schemaIsJson={schema !== null}
                    stale={status !== 'saved'}
                />
            </div>
        </main>
    );
}

function SourceEditor({
    name,
    value,
    maxBytes,
    error,
    onChange,
}: {
    name: string;
    value: string;
    maxBytes: number;
    error?: string;
    onChange: (value: string) => void;
}) {
    const id = `studio-source-${name.replace('.', '-')}`;
    const bytes = byteSize(value);
    const tooLarge = bytes > maxBytes;

    return (
        <div className="grid min-w-0 gap-1.5">
            <label htmlFor={id} className="font-mono text-sm font-medium">
                {name}
            </label>
            <textarea
                id={id}
                value={value}
                spellCheck={false}
                autoCapitalize="off"
                autoComplete="off"
                wrap="off"
                aria-invalid={tooLarge || Boolean(error)}
                aria-describedby={`${id}-size${error ? ` ${id}-error` : ''}`}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-[24rem] w-full min-w-0 resize-y rounded-md border border-input bg-muted/30 p-3 font-mono text-sm leading-relaxed shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
            />
            <p
                id={`${id}-size`}
                className={cn(
                    'text-xs',
                    tooLarge ? 'text-destructive' : 'text-muted-foreground',
                )}
            >
                {formatKilobytes(bytes)} из {formatKilobytes(maxBytes)}
                {tooLarge && ' — файл слишком большой, черновик не сохранится.'}
            </p>
            {error && (
                <p id={`${id}-error`} className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
function CheckList({
    label,
    items,
    empty,
}: {
    label: string;
    items: { key: string; location: string; message: string }[];
    empty: string;
}) {
    if (items.length === 0) {
        return (
            <p className="flex gap-2 text-sm">
                <CircleCheck
                    aria-hidden="true"
                    className="size-4 shrink-0 text-green-600"
                />
                {empty}
            </p>
        );
    }

    return (
        <ul className="grid gap-2" aria-label={label}>
            {items.map((item) => (
                <li
                    key={item.key}
                    className="rounded-md border border-destructive/40 p-2 text-sm"
                >
                    <code className="text-xs break-all text-muted-foreground">
                        {item.location}
                    </code>
                    <p className="text-destructive">{item.message}</p>
                </li>
            ))}
        </ul>
    );
}

function DraftChecks({
    draft,
    schemaIsJson,
    stale,
}: {
    draft: BlockDraft;
    schemaIsJson: boolean;
    stale: boolean;
}) {
    return (
        <aside
            aria-labelledby="studio-checks-title"
            className="h-fit space-y-4 rounded-xl border bg-card p-4 shadow-sm"
        >
            <h2 id="studio-checks-title" className="font-semibold">
                Проверки
            </h2>
            <section
                aria-labelledby="studio-checks-schema"
                className="space-y-2"
            >
                <h3 id="studio-checks-schema" className="text-sm font-medium">
                    Схема
                </h3>
                {schemaIsJson ? (
                    <CheckList
                        label="Ошибки схемы"
                        empty="Ошибок в схеме нет."
                        items={draft.schema_errors.map((error) => ({
                            key: error.path,
                            location: error.path,
                            message: error.message,
                        }))}
                    />
                ) : (
                    <p className="flex gap-2 text-sm text-destructive">
                        <TriangleAlert
                            aria-hidden="true"
                            className="size-4 shrink-0"
                        />
                        schema.json сейчас не является корректным JSON со
                        списком fields.
                    </p>
                )}
            </section>
            <section
                aria-labelledby="studio-checks-template"
                className="space-y-2"
            >
                <h3 id="studio-checks-template" className="text-sm font-medium">
                    Шаблон index.html
                </h3>
                <CheckList
                    label="Ошибки шаблона"
                    empty="Ошибок в шаблоне нет."
                    items={draft.template_errors.map((error, index) => ({
                        key: `${index}-${error.line}`,
                        location: `Строка ${error.line}`,
                        message: error.message,
                    }))}
                />
            </section>
            <p className="text-xs text-muted-foreground">
                {stale
                    ? 'Результат обновится после сохранения черновика.'
                    : draft.saved_at
                      ? `По черновику от ${formatBlockDate(draft.saved_at)}.`
                      : 'Черновик ещё не сохранялся.'}
            </p>
        </aside>
    );
}

function BlockSettings({
    block,
    categories,
    action,
}: {
    block: AuthoringBlockDetail;
    categories: Choice[];
    action: RouteFormDefinition<'post'>;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <section
                aria-labelledby="block-metadata-title"
                className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
            >
                <h2 id="block-metadata-title" className="font-semibold">
                    Основные данные
                </h2>
                <Form
                    {...action}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <TextField
                                id="block-name"
                                name="name"
                                label="Название"
                                defaultValue={block.name}
                                required
                                maxLength={100}
                                autoComplete="off"
                                error={errors.name}
                            />
                            <SelectField
                                id="block-category"
                                name="category"
                                label="Категория"
                                choices={categories}
                                defaultValue={block.category}
                                required
                                error={errors.category}
                            />
                            <Field
                                id="block-slug"
                                label="Slug"
                                hint="Технический идентификатор блока не меняется после создания."
                            >
                                <Input
                                    id="block-slug"
                                    value={block.slug}
                                    readOnly
                                    aria-readonly="true"
                                    className="bg-muted/40 font-mono"
                                />
                            </Field>
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Сохранить
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>

            <section
                aria-labelledby="block-ownership-title"
                className="h-fit space-y-3 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
            >
                <h2 id="block-ownership-title" className="font-semibold">
                    Владение
                </h2>
                <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                    <dt className="text-muted-foreground">Владелец</dt>
                    <dd className="break-words">{block.owner_name}</dd>
                    <dt className="text-muted-foreground">Тип владельца</dt>
                    <dd>{block.owner_scope_label}</dd>
                    <dt className="text-muted-foreground">Количество версий</dt>
                    <dd>{block.versions_count}</dd>
                    <dt className="text-muted-foreground">Обновлён</dt>
                    <dd>{formatBlockDate(block.updated_at)}</dd>
                </dl>
            </section>
        </div>
    );
}
