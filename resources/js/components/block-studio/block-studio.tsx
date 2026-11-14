import { Form, router } from '@inertiajs/react';
import {
    CircleCheck,
    FileCode2,
    ImageIcon,
    Trash2,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    formatBlockDate,
    versionsLabel,
} from '@/components/block-authoring/block-list';
import { PreviewImageField } from '@/components/block-authoring/preview-image-field';
import { BlockPreview } from '@/components/block-studio/block-preview';
import {
    parseSchema,
    SchemaBuilder,
} from '@/components/block-studio/schema-builder';
import { useDraftAutosave } from '@/components/block-studio/use-draft-autosave';
import { CatalogAccessForm } from '@/components/catalog/catalog-access-form';
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
import { cn } from '@/lib/utils';
import type {
    AuthoringBlockDetail,
    BlockAccessSettings,
    BlockCheckIssue,
    BlockDraft,
    DraftSourceKey,
    PublishedBlockVersion,
} from '@/types/blocks';
import type { RouteFormDefinition } from '@/wayfinder';

type Mode = 'code' | 'schema' | 'preview' | 'versions' | 'settings';

const modes: { value: Mode; label: string }[] = [
    { value: 'code', label: 'Код' },
    { value: 'schema', label: 'Конструктор схемы' },
    { value: 'preview', label: 'Предпросмотр' },
    { value: 'versions', label: 'Версии' },
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

/** Server props of the platform and developer Block Studio pages. */
export type BlockStudioPageProps = {
    block: AuthoringBlockDetail;
    draft: BlockDraft;
    versions: PublishedBlockVersion[];
    publishBlockedReason: string | null;
    canApproveNative: boolean;
    access: BlockAccessSettings;
    accessModes: Choice[];
    groups: Choice[];
    sourceMaxBytes: number;
};

type BlockStudioProps = BlockStudioPageProps & {
    metadataAction: RouteFormDefinition<'post'>;
    draftUrl: string;
    publishUrl: string;
    accessUrl: string;
    copyLegacyVersionUrl: (version: string) => string;
    versionPreviewUrl: (version: string) => string;
};

export function BlockStudio({
    block,
    draft,
    versions,
    publishBlockedReason,
    canApproveNative,
    access,
    accessModes,
    groups,
    sourceMaxBytes,
    metadataAction,
    draftUrl,
    publishUrl,
    accessUrl,
    copyLegacyVersionUrl,
    versionPreviewUrl,
}: BlockStudioProps) {
    const autosave = useDraftAutosave(draftUrl, draft);
    const { sources, status, errors } = autosave;
    const [mode, setMode] = useState<Mode>('code');
    const [publishing, setPublishing] = useState(false);
    const [publishError, setPublishError] = useState<string | null>(null);
    const [publishOpen, setPublishOpen] = useState(false);
    const [previewFile, setPreviewFile] = useState<File | null>(null);
    const [previewError, setPreviewError] = useState<string | undefined>();
    const canPublish =
        status === 'saved' &&
        draft.saved_at !== null &&
        draft.checks.length === 0 &&
        publishBlockedReason === null &&
        canApproveNative &&
        !publishing;

    const publish = () => {
        setPublishError(null);
        setPreviewError(undefined);
        router.post(
            publishUrl,
            previewFile
                ? { revision: draft.revision, preview: previewFile }
                : { revision: draft.revision },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setPublishing(true),
                onError: (failed) => {
                    if (failed.preview) {
                        setPreviewError(failed.preview);

                        return;
                    }

                    setPublishOpen(false);
                    setPublishError(
                        failed.publish ??
                            failed.revision ??
                            'Не удалось опубликовать блок.',
                    );
                },
                onSuccess: () => {
                    setPublishOpen(false);
                    setPreviewFile(null);
                    setMode('versions');
                },
                onFinish: () => setPublishing(false),
            },
        );
    };
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
                        <Badge variant="secondary">{block.group_label}</Badge>
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
                        variant="outline"
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
                    <Button
                        type="button"
                        onClick={() => setPublishOpen(true)}
                        disabled={!canPublish}
                        aria-describedby="studio-publish-hint"
                    >
                        {publishing ? (
                            <Spinner />
                        ) : (
                            <Upload aria-hidden="true" />
                        )}
                        Одобрить и опубликовать
                    </Button>
                </div>
            </header>
            <p
                id="studio-publish-hint"
                className="-mt-2 text-xs text-muted-foreground lg:text-right"
            >
                {!canApproveNative
                    ? 'Нет права одобрять нативные блоки.'
                    : publishHint({
                          blockedReason: publishBlockedReason,
                          saved: status === 'saved' && draft.saved_at !== null,
                          issues: draft.checks.length,
                      })}
            </p>

            {publishError && (
                <Alert variant="destructive">
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>Версия не опубликована</AlertTitle>
                    <AlertDescription>{publishError}</AlertDescription>
                </Alert>
            )}

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

                    {mode === 'versions' && (
                        <VersionList
                            versions={versions}
                            revision={draft.revision}
                            copyLegacyVersionUrl={copyLegacyVersionUrl}
                            versionPreviewUrl={versionPreviewUrl}
                            onCopied={() => setMode('code')}
                        />
                    )}

                    {mode === 'settings' && (
                        <BlockSettings
                            block={block}
                            groups={groups}
                            action={metadataAction}
                            access={access}
                            accessModes={accessModes}
                            accessUrl={accessUrl}
                        />
                    )}
                </div>

                <DraftChecks draft={draft} stale={status !== 'saved'} />
            </div>

            <Dialog
                open={publishOpen}
                onOpenChange={(open) => {
                    if (!publishing) {
                        setPublishOpen(open);
                        setPreviewError(undefined);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Одобрить и опубликовать</DialogTitle>
                        <DialogDescription>
                            Сохранённый черновик станет новой неизменяемой
                            версией блока. Превью необязательно — его можно
                            добавить и позже во вкладке «Версии».
                        </DialogDescription>
                    </DialogHeader>
                    <PreviewImageField
                        value={previewFile}
                        onChange={(file) => {
                            setPreviewFile(file);
                            setPreviewError(undefined);
                        }}
                        error={previewError}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={publishing}
                            >
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={publish}
                            disabled={!canPublish}
                        >
                            {publishing ? (
                                <Spinner />
                            ) : (
                                <Upload aria-hidden="true" />
                            )}
                            Опубликовать
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
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

function publishHint({
    blockedReason,
    saved,
    issues,
}: {
    blockedReason: string | null;
    saved: boolean;
    issues: number;
}): string {
    if (blockedReason !== null) {
        return blockedReason;
    }

    if (!saved) {
        return 'Опубликовать можно только сохранённый черновик.';
    }

    if (issues > 0) {
        return 'Исправьте проблемы из панели проверок, чтобы опубликовать.';
    }

    return 'Публикация создаст новую неизменяемую версию; сайты, где блок уже используется, не изменятся.';
}

function VersionList({
    versions,
    revision,
    copyLegacyVersionUrl,
    versionPreviewUrl,
    onCopied,
}: {
    versions: PublishedBlockVersion[];
    revision: number;
    copyLegacyVersionUrl: (version: string) => string;
    versionPreviewUrl: (version: string) => string;
    onCopied: () => void;
}) {
    return (
        <section
            aria-labelledby="studio-versions-title"
            className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
        >
            <div>
                <h2 id="studio-versions-title" className="font-semibold">
                    Опубликованные версии
                </h2>
                <p className="text-sm text-muted-foreground">
                    Версии неизменяемы: черновик и автосохранение их не
                    затрагивают.
                </p>
            </div>
            {versions.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Опубликованных версий пока нет. Версия появится после
                    нажатия «Опубликовать».
                </p>
            ) : (
                <ul className="divide-y rounded-md border">
                    {versions.map((version) => (
                        <li
                            key={version.version}
                            data-testid="block-version"
                            className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                        >
                            {version.preview_url ? (
                                <img
                                    src={version.preview_url}
                                    alt={`Превью версии ${version.version}`}
                                    className="h-12 w-20 shrink-0 rounded border object-cover object-top"
                                />
                            ) : null}
                            <span className="font-mono font-medium">
                                {version.version}
                            </span>
                            <Badge variant="outline">
                                {version.runtime_label}
                            </Badge>
                            {version.legacy && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            copyLegacyVersionUrl(
                                                version.version,
                                            ),
                                            { revision },
                                            {
                                                preserveScroll: true,
                                                onSuccess: onCopied,
                                            },
                                        )
                                    }
                                >
                                    <FileCode2 aria-hidden="true" />
                                    Скопировать в черновик
                                </Button>
                            )}
                            <VersionPreviewDialog
                                version={version}
                                url={versionPreviewUrl(version.version)}
                            />
                            <span className="text-muted-foreground">
                                {formatBlockDate(version.published_at)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function VersionPreviewDialog({
    version,
    url,
}: {
    version: PublishedBlockVersion;
    url: string;
}) {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
        onError: (failed: Record<string, string>) =>
            setError(failed.preview ?? 'Не удалось сохранить превью.'),
        onSuccess: () => {
            setOpen(false);
            setFile(null);
        },
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                setFile(null);
                setError(undefined);
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <ImageIcon aria-hidden="true" />
                    {version.preview_url
                        ? 'Заменить превью'
                        : 'Добавить превью'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Превью версии {version.version}</DialogTitle>
                    <DialogDescription>
                        Превью показывается на карточке блока в группе и в
                        библиотеке дизайнера.
                    </DialogDescription>
                </DialogHeader>
                <PreviewImageField
                    value={file}
                    onChange={(value) => {
                        setFile(value);
                        setError(undefined);
                    }}
                    currentUrl={version.preview_url}
                    error={error}
                />
                <DialogFooter>
                    {version.preview_url && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="sm:mr-auto"
                            disabled={processing}
                            onClick={() => router.delete(url, options)}
                        >
                            <Trash2 aria-hidden="true" />
                            Удалить превью
                        </Button>
                    )}
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Отмена
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        disabled={!file || processing}
                        onClick={() =>
                            file && router.post(url, { preview: file }, options)
                        }
                    >
                        {processing && <Spinner />}
                        Сохранить превью
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function issueLocation(issue: BlockCheckIssue): string | null {
    if (issue.line !== null) {
        return `Строка ${issue.line}`;
    }

    return issue.path;
}

function DraftChecks({ draft, stale }: { draft: BlockDraft; stale: boolean }) {
    const groups = files
        .map((item) => ({
            ...item,
            issues: draft.checks.filter((issue) => issue.source === item.key),
        }))
        .filter((group) => group.issues.length > 0);

    return (
        <aside
            aria-labelledby="studio-checks-title"
            className="h-fit space-y-4 rounded-xl border bg-card p-4 shadow-sm"
        >
            <h2 id="studio-checks-title" className="font-semibold">
                Проверки перед публикацией
            </h2>
            {groups.length === 0 ? (
                <p className="flex gap-2 text-sm">
                    <CircleCheck
                        aria-hidden="true"
                        className="size-4 shrink-0 text-green-600"
                    />
                    Все проверки пройдены.
                </p>
            ) : (
                <>
                    <p className="flex gap-2 text-sm text-destructive">
                        <TriangleAlert
                            aria-hidden="true"
                            className="size-4 shrink-0"
                        />
                        Найдено проблем: {draft.checks.length}. Опубликовать
                        блок можно будет после их исправления.
                    </p>
                    {groups.map((group) => (
                        <section
                            key={group.key}
                            aria-labelledby={`studio-checks-${group.key}`}
                            className="space-y-2"
                        >
                            <h3
                                id={`studio-checks-${group.key}`}
                                className="font-mono text-sm font-medium"
                            >
                                {group.name}
                            </h3>
                            <ul className="grid gap-2">
                                {group.issues.map((issue, index) => {
                                    const location = issueLocation(issue);

                                    return (
                                        <li
                                            key={index}
                                            className="rounded-md border border-destructive/40 p-2 text-sm"
                                        >
                                            {location && (
                                                <code className="text-xs break-all text-muted-foreground">
                                                    {location}
                                                </code>
                                            )}
                                            <p className="text-destructive">
                                                {issue.message}
                                            </p>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                </>
            )}
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
    groups,
    action,
    access,
    accessModes,
    accessUrl,
}: {
    block: AuthoringBlockDetail;
    groups: Choice[];
    action: RouteFormDefinition<'post'>;
    access: BlockAccessSettings;
    accessModes: Choice[];
    accessUrl: string;
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
                                id="block-group"
                                name="group"
                                label="Группа"
                                choices={groups}
                                defaultValue={block.group ?? undefined}
                                emptyLabel="Выберите группу"
                                required
                                error={errors.group}
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

            <CatalogAccessForm
                idPrefix="block-access"
                description="Как клиенты могут добавлять опубликованный блок на свои сайты. Платный блок продаётся лицензией на один сайт, на всё пространство или обеими; покупка лицензий в Landflow пока недоступна. Сайты, которые уже установили версию блока, сохраняют её при смене доступа."
                access={access}
                modes={accessModes}
                url={accessUrl}
                className="lg:col-span-2"
            />
        </div>
    );
}
