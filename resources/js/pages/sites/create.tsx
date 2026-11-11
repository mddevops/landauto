import { Form, Head, Link } from '@inertiajs/react';
import { Eye, FilePlus2, LayoutTemplate, Lock } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create, store } from '@/routes/sites';
import type { CatalogAccessCard } from '@/types/blocks';

type SiteTypeValue = 'multi_page' | 'landing' | 'quiz' | 'chat_selection';

type SiteTypeOption = {
    value: SiteTypeValue;
    label: string;
    description: string;
    allowed: boolean;
    blank_allowed: boolean;
};

type TemplatePagePreview = {
    title: string;
    block_count: number;
};

type TemplateOption = {
    public_id: string;
    name: string;
    site_types: SiteTypeValue[];
    author: string | null;
    access: CatalogAccessCard;
    available: boolean;
    reason: string | null;
    page_count: number;
    pages: TemplatePagePreview[];
};

type CreateSiteProps = {
    currentWorkspace: {
        public_id: string;
        name: string;
    };
    siteTypes: SiteTypeOption[];
    templates: TemplateOption[];
    siteLimit: {
        active: number;
        max: number;
        reached: boolean;
    };
};

const BLANK = 'blank';

const typeCardClass =
    'flex min-w-0 cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors hover:bg-accent has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 has-[:disabled]:hover:bg-card has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring';

const previewPalette = [
    'from-sky-500/80 via-sky-600/40 to-slate-900/90',
    'from-emerald-500/80 via-teal-700/40 to-slate-900/90',
    'from-amber-500/80 via-orange-700/40 to-slate-900/90',
    'from-rose-500/70 via-fuchsia-800/40 to-slate-900/90',
    'from-indigo-500/80 via-violet-700/40 to-slate-900/90',
];

function paletteFor(id: string): string {
    let hash = 0;

    for (let index = 0; index < id.length; index++) {
        hash = (hash + id.charCodeAt(index)) % previewPalette.length;
    }

    return previewPalette[hash] ?? previewPalette[0];
}

function defaultStart(
    type: SiteTypeOption | undefined,
    templates: TemplateOption[],
): string {
    if (!type) {
        return '';
    }

    if (type.blank_allowed) {
        return BLANK;
    }

    return (
        templates.find(
            (template) =>
                template.available && template.site_types.includes(type.value),
        )?.public_id ?? ''
    );
}

function TemplateShowcaseCard({
    template,
    selected,
    disabled,
    onSelect,
    onPreview,
}: {
    template: TemplateOption;
    selected: boolean;
    disabled: boolean;
    onSelect: () => void;
    onPreview: () => void;
}) {
    const palette = paletteFor(template.public_id);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-2xl border bg-card shadow-sm transition',
                selected && 'border-primary ring-2 ring-primary/20',
                disabled && 'opacity-60',
            )}
        >
            <button
                type="button"
                disabled={disabled}
                onClick={onSelect}
                className="block w-full text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                aria-pressed={selected}
            >
                <div
                    className={cn(
                        'relative aspect-video bg-gradient-to-br p-4 text-white',
                        palette,
                    )}
                >
                    <div className="absolute inset-x-4 top-4 space-y-2 rounded-lg bg-white/15 p-3 backdrop-blur-sm">
                        <div className="h-2 w-24 rounded bg-white/70" />
                        <div className="h-2 w-16 rounded bg-white/40" />
                    </div>
                    <div className="absolute inset-x-4 bottom-4 grid grid-cols-3 gap-2">
                        {Array.from({
                            length: Math.min(
                                3,
                                Math.max(1, template.page_count),
                            ),
                        }).map((_, index) => (
                            <div
                                key={index}
                                className="h-12 rounded-md bg-white/20"
                            />
                        ))}
                    </div>
                    <Badge className="absolute top-3 right-3 bg-black/40 text-white hover:bg-black/40">
                        {template.page_count === 1
                            ? '1 страница'
                            : `${template.page_count} стр.`}
                    </Badge>
                </div>
                <div className="space-y-2 p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium break-words">
                            {template.name}
                        </p>
                        {template.access.restricted && (
                            <Badge variant="outline">
                                {template.access.label}
                            </Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {[
                            template.author
                                ? `Автор: ${template.author}`
                                : 'Шаблон Landflow',
                            template.access.detail,
                            template.reason,
                        ]
                            .filter(Boolean)
                            .join('. ')}
                    </p>
                </div>
            </button>
            <div className="flex items-center justify-between gap-2 border-t px-4 py-2">
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="radio"
                        name="start_option"
                        value={template.public_id}
                        checked={selected}
                        disabled={disabled}
                        onChange={onSelect}
                        className="size-4 accent-primary"
                    />
                    Выбрать
                </label>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onPreview}
                >
                    <Eye aria-hidden="true" />
                    Смотреть
                </Button>
            </div>
        </div>
    );
}

export default function CreateSite({
    currentWorkspace,
    siteTypes,
    templates,
    siteLimit,
}: CreateSiteProps) {
    const initialType = siteTypes.find((type) => type.allowed);
    const [siteType, setSiteType] = useState<SiteTypeValue | undefined>(
        initialType?.value,
    );
    const [start, setStart] = useState(() =>
        defaultStart(initialType, templates),
    );
    const [previewId, setPreviewId] = useState<string | null>(null);

    const selectedType = siteTypes.find((type) => type.value === siteType);
    const compatible = templates.filter(
        (template) =>
            siteType !== undefined && template.site_types.includes(siteType),
    );
    const preview = useMemo(
        () => templates.find((template) => template.public_id === previewId),
        [previewId, templates],
    );
    const canSubmit =
        !siteLimit.reached && selectedType?.allowed === true && start !== '';

    const chooseType = (type: SiteTypeOption) => {
        setSiteType(type.value);
        setStart(defaultStart(type, templates));
    };

    return (
        <>
            <Head title="Создание сайта" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {currentWorkspace.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Создание сайта
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Выберите формат и шаблон — после создания сразу
                        откроется дизайнер.
                    </p>
                </header>

                {siteLimit.reached && (
                    <Alert>
                        <AlertTitle>Достигнут лимит активных сайтов</AlertTitle>
                        <AlertDescription>
                            {`Активных сайтов: ${siteLimit.active} из ${siteLimit.max}. Чтобы создать новый сайт, освободите место в текущем рабочем пространстве.`}
                        </AlertDescription>
                    </Alert>
                )}

                <Form
                    {...store.form()}
                    disableWhileProcessing
                    className="flex max-w-5xl flex-col gap-8"
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.site} />

                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-xl font-semibold">
                                    1. Формат
                                </legend>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {siteTypes.map((type) => (
                                        <label
                                            key={type.value}
                                            className={typeCardClass}
                                        >
                                            <input
                                                type="radio"
                                                name="site_type"
                                                value={type.value}
                                                checked={
                                                    siteType === type.value
                                                }
                                                disabled={!type.allowed}
                                                onChange={() =>
                                                    chooseType(type)
                                                }
                                                className="mt-1 size-4 shrink-0 accent-primary"
                                                aria-describedby={`site-type-${type.value}-hint`}
                                            />
                                            <span className="min-w-0 space-y-1">
                                                <span className="flex items-center gap-2 font-medium break-words">
                                                    {type.label}
                                                    {!type.allowed && (
                                                        <Lock
                                                            aria-hidden="true"
                                                            className="size-4 shrink-0 text-muted-foreground"
                                                        />
                                                    )}
                                                </span>
                                                <span
                                                    id={`site-type-${type.value}-hint`}
                                                    className="block text-sm text-muted-foreground"
                                                >
                                                    {type.allowed
                                                        ? type.description
                                                        : 'Недоступно на текущем тарифе.'}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.site_type} />
                            </fieldset>

                            <fieldset
                                className="space-y-3"
                                aria-describedby={
                                    errors.template || errors.start
                                        ? 'template-error'
                                        : undefined
                                }
                            >
                                <legend className="mb-3 text-xl font-semibold">
                                    2. Шаблон
                                </legend>
                                <input
                                    type="hidden"
                                    name="start"
                                    value={start === BLANK ? BLANK : 'template'}
                                />
                                {start !== BLANK && start !== '' && (
                                    <input
                                        type="hidden"
                                        name="template"
                                        value={start}
                                    />
                                )}

                                {!selectedType?.blank_allowed &&
                                compatible.length === 0 ? (
                                    <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                                        Для этого формата пока нет шаблонов.
                                        Пустой старт недоступен: квиз и
                                        чат-подбор создаются только из шаблона.
                                    </p>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        {selectedType?.blank_allowed && (
                                            <label
                                                className={cn(
                                                    'flex min-h-48 cursor-pointer flex-col justify-between rounded-2xl border border-dashed bg-card p-5 shadow-sm transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="start_option"
                                                    value={BLANK}
                                                    checked={start === BLANK}
                                                    onChange={() =>
                                                        setStart(BLANK)
                                                    }
                                                    className="sr-only"
                                                />
                                                <FilePlus2
                                                    aria-hidden="true"
                                                    className="size-8 text-muted-foreground"
                                                />
                                                <div className="space-y-1">
                                                    <p className="font-medium">
                                                        Пустой старт
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Чистый холст без готовых
                                                        секций.
                                                    </p>
                                                </div>
                                            </label>
                                        )}
                                        {compatible.map((template) => (
                                            <TemplateShowcaseCard
                                                key={template.public_id}
                                                template={template}
                                                selected={
                                                    start === template.public_id
                                                }
                                                disabled={!template.available}
                                                onSelect={() =>
                                                    setStart(template.public_id)
                                                }
                                                onPreview={() =>
                                                    setPreviewId(
                                                        template.public_id,
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                )}

                                <InputError
                                    id="template-error"
                                    message={errors.template ?? errors.start}
                                />
                            </fieldset>

                            <fieldset className="space-y-3">
                                <legend className="mb-3 text-xl font-semibold">
                                    3. Название
                                </legend>
                                <div className="grid max-w-md gap-2">
                                    <Label htmlFor="name">Название сайта</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        maxLength={255}
                                        autoComplete="off"
                                        placeholder="Например, Автосалон Север"
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={
                                            errors.name
                                                ? 'name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="name-error"
                                        message={errors.name}
                                    />
                                </div>
                            </fieldset>

                            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                                <Button variant="outline" asChild>
                                    <Link href={dashboard()}>Отмена</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={!canSubmit || processing}
                                >
                                    {processing && <Spinner />}
                                    Создать и открыть дизайнер
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </main>

            <Dialog
                open={preview !== undefined}
                onOpenChange={(open) => !open && setPreviewId(null)}
            >
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {preview?.name ?? 'Просмотр шаблона'}
                        </DialogTitle>
                        <DialogDescription>
                            Структура шаблона до создания сайта. После создания
                            откроется дизайнер с вашей копией.
                        </DialogDescription>
                    </DialogHeader>
                    {preview && (
                        <div className="space-y-4">
                            <div
                                className={cn(
                                    'aspect-video rounded-xl bg-gradient-to-br p-5 text-white',
                                    paletteFor(preview.public_id),
                                )}
                            >
                                <div className="flex h-full flex-col justify-between rounded-lg bg-black/20 p-4 backdrop-blur-sm">
                                    <div className="flex items-center gap-2">
                                        <LayoutTemplate
                                            aria-hidden="true"
                                            className="size-5"
                                        />
                                        <span className="font-medium">
                                            {preview.name}
                                        </span>
                                    </div>
                                    <p className="text-sm text-white/80">
                                        {preview.author
                                            ? `Автор: ${preview.author}`
                                            : 'Официальный шаблон Landflow'}
                                    </p>
                                </div>
                            </div>
                            <ol className="space-y-2">
                                {preview.pages.map((page, index) => (
                                    <li
                                        key={`${page.title}-${index}`}
                                        className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                    >
                                        <span className="font-medium">
                                            {page.title}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {page.block_count === 1
                                                ? '1 блок'
                                                : `${page.block_count} блоков`}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPreviewId(null)}
                        >
                            Закрыть
                        </Button>
                        {preview?.available && (
                            <Button
                                type="button"
                                onClick={() => {
                                    setStart(preview.public_id);
                                    setPreviewId(null);
                                }}
                            >
                                Выбрать этот шаблон
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

CreateSite.layout = {
    breadcrumbs: [
        {
            title: 'Все сайты',
            href: dashboard(),
        },
        {
            title: 'Создание сайта',
            href: create(),
        },
    ],
};
