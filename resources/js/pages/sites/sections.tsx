import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    FolderTree,
    PencilRuler,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/catalog/photo-parts';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { designer, preview } from '@/routes/sites';
import {
    destroy as destroySection,
    store as storeSection,
    update as updateSection,
} from '@/routes/sites/sections';

type Section = {
    public_id: string;
    type: string;
    type_label: string;
    title: string;
    prefix: string;
    enabled: boolean;
    redirects: string[];
    pages: { public_id: string; role: string; label: string; path: string }[];
};

type SectionType = {
    value: string;
    label: string;
    description: string;
    prefix: string;
    available: boolean;
    added: boolean;
};

type SectionsProps = {
    site: { public_id: string; name: string };
    baseUrl: string | null;
    sections: Section[];
    types: SectionType[];
    limits: { title: number; prefix: number };
};

function addressOf(baseUrl: string | null, prefix: string): string {
    const base = (baseUrl ?? '').replace(/\/+$/, '');

    return `${base}/${prefix}`;
}

function AddSectionCard({
    site,
    type,
}: {
    site: SectionsProps['site'];
    type: SectionType;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function add() {
        router.post(
            storeSection(site.public_id).url,
            { type: type.value },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setError(null);
                },
                onError: (errors) =>
                    setError(errors.prefix ?? errors.type ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <li className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <p className="font-medium">{type.label}</p>
                    <p className="text-sm text-muted-foreground">
                        {type.description}
                    </p>
                </div>
                {type.added ? (
                    <Badge variant="secondary">Добавлен</Badge>
                ) : (
                    !type.available && <Badge variant="outline">Скоро</Badge>
                )}
            </div>
            <p className="font-mono text-xs break-all text-muted-foreground">
                /{type.prefix}/kia/rio/…
            </p>
            {type.available ? (
                !type.added && (
                    <div>
                        <Button
                            type="button"
                            size="sm"
                            onClick={add}
                            disabled={processing}
                            aria-label={`Добавить раздел «${type.label}»`}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Plus aria-hidden="true" />
                            )}
                            Добавить
                        </Button>
                    </div>
                )
            ) : (
                <p className="text-xs text-muted-foreground">
                    Появится вместе со складом автомобилей.
                </p>
            )}
            <InputError message={error ?? undefined} />
        </li>
    );
}

function SectionCard({
    site,
    section,
    baseUrl,
    limits,
}: {
    site: SectionsProps['site'];
    section: Section;
    baseUrl: string | null;
    limits: SectionsProps['limits'];
}) {
    const id = section.public_id;
    const form = useForm({
        title: section.title,
        prefix: section.prefix,
        enabled: section.enabled,
    });
    const prefixChanged =
        form.data.prefix.trim().toLowerCase() !== section.prefix;

    function save(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ ...data, enabled: data.enabled ? 1 : 0 }));
        form.submit(updateSection({ site: site.public_id, section: id }), {
            preserveScroll: true,
        });
    }

    return (
        <Card
            className="min-w-0"
            role="region"
            aria-label={`Раздел «${section.title}»`}
        >
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="min-w-0 space-y-1">
                        <CardTitle className="break-words">
                            {section.title}
                        </CardTitle>
                        <CardDescription>{section.type_label}</CardDescription>
                    </div>
                    {!section.enabled && (
                        <Badge variant="outline">Скрыт на сайте</Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`section-title-${id}`}>
                            Название раздела
                        </Label>
                        <Input
                            id={`section-title-${id}`}
                            value={form.data.title}
                            maxLength={limits.title}
                            required
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.title)}
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`section-prefix-${id}`}>
                            Адрес раздела
                        </Label>
                        <div className="flex min-w-0 items-stretch overflow-hidden rounded-md border shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                            <span className="hidden max-w-[50%] items-center truncate border-r bg-muted px-3 text-sm text-muted-foreground sm:flex">
                                {addressOf(baseUrl, '')}
                            </span>
                            <input
                                id={`section-prefix-${id}`}
                                value={form.data.prefix}
                                maxLength={limits.prefix}
                                required
                                autoComplete="off"
                                spellCheck={false}
                                onChange={(event) =>
                                    form.setData('prefix', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.prefix)}
                                aria-describedby={`section-prefix-help-${id}`}
                                className="h-9 min-w-0 flex-1 bg-transparent px-3 font-mono text-sm outline-none"
                            />
                        </div>
                        <p
                            id={`section-prefix-help-${id}`}
                            className="text-xs text-muted-foreground"
                        >
                            Строчные латинские буквы, цифры и дефисы, до{' '}
                            {limits.prefix} символов. Пример карточки:{' '}
                            <span className="font-mono break-all">
                                /{form.data.prefix.trim() || section.prefix}
                                /kia/rio-iv-restyling
                            </span>
                        </p>
                        {prefixChanged && (
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                <span className="font-mono">
                                    /{section.prefix}
                                </span>
                                <ArrowRight
                                    className="size-3"
                                    aria-hidden="true"
                                />
                                <span>
                                    старый адрес будет перенаправлять на новый
                                </span>
                            </p>
                        )}
                        <InputError message={form.errors.prefix} />
                    </div>
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id={`section-enabled-${id}`}
                            checked={form.data.enabled}
                            onCheckedChange={(checked) =>
                                form.setData('enabled', checked === true)
                            }
                        />
                        <Label
                            htmlFor={`section-enabled-${id}`}
                            className="leading-snug"
                        >
                            Показывать раздел на сайте
                        </Label>
                    </div>
                    {section.pages.length > 0 && (
                        <div className="grid gap-2">
                            <p className="text-sm font-medium">
                                Страницы раздела
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Шаблоны: одна страница для всех автомобилей.
                                Внешний вид настраивается в дизайнере, данные
                                автомобилей — в разделе «Автомобили».
                            </p>
                            <ul className="divide-y rounded-md border">
                                {section.pages.map((page) => (
                                    <li
                                        key={page.public_id}
                                        className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm">
                                                {page.label}
                                            </p>
                                            <p className="font-mono text-xs break-all text-muted-foreground">
                                                /{page.path}
                                            </p>
                                        </div>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <Link
                                                href={designer.url(
                                                    site.public_id,
                                                    {
                                                        query: {
                                                            page: page.public_id,
                                                        },
                                                    },
                                                )}
                                                aria-label={`Открыть в дизайнере: ${page.label}`}
                                            >
                                                <PencilRuler aria-hidden="true" />
                                                Открыть в дизайнере
                                            </Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <div>
                                <Button asChild variant="ghost" size="sm">
                                    <a
                                        href={preview.url(site.public_id, {
                                            query: { path: section.prefix },
                                        })}
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <Eye aria-hidden="true" />
                                        Посмотреть в предпросмотре
                                    </a>
                                </Button>
                            </div>
                        </div>
                    )}
                    {section.redirects.length > 0 && (
                        <div className="space-y-1 text-sm">
                            <p className="text-muted-foreground">
                                Старые адреса перенаправляют на текущий:
                            </p>
                            <ul className="flex flex-wrap gap-2">
                                {section.redirects.map((prefix) => (
                                    <li key={prefix}>
                                        <Badge
                                            variant="outline"
                                            className="font-mono"
                                        >
                                            /{prefix}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            aria-label={`Сохранить раздел «${section.title}»`}
                        >
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                        <ConfirmDialog
                            title={`Удалить раздел «${section.title}»?`}
                            description="Шаблоны страниц раздела будут удалены вместе с оформлением, а страницы перестанут открываться после следующей публикации. Автомобили сайта останутся."
                            onConfirm={() =>
                                router.delete(
                                    destroySection({
                                        site: site.public_id,
                                        section: id,
                                    }).url,
                                    { preserveScroll: true },
                                )
                            }
                            trigger={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 aria-hidden="true" />
                                    Удалить раздел
                                </Button>
                            }
                        />
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Sections({
    site,
    baseUrl,
    sections,
    types,
    limits,
}: SectionsProps) {
    return (
        <>
            <Head title={`Разделы — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="min-w-0 space-y-1">
                    <p className="truncate text-sm text-muted-foreground">
                        {site.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        Разделы
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Разделы с автомобилями: страницы марок, моделей и
                        карточки автомобилей создаются автоматически. Адрес
                        раздела можно изменить. Изменения появятся на сайте
                        после публикации.
                    </p>
                </header>

                {sections.length > 0 ? (
                    <section
                        aria-label="Разделы сайта"
                        className="grid gap-4 lg:grid-cols-2"
                    >
                        {sections.map((section) => (
                            <SectionCard
                                key={section.public_id}
                                site={site}
                                section={section}
                                baseUrl={baseUrl}
                                limits={limits}
                            />
                        ))}
                    </section>
                ) : (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center">
                        <FolderTree
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <h2 className="font-medium">Разделов пока нет</h2>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Добавьте «Каталог автомобилей», чтобы у каждой
                            машины сайта появилась своя страница.
                        </p>
                    </div>
                )}

                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle>Добавить раздел</CardTitle>
                        <CardDescription>
                            Каждый раздел можно добавить один раз.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-3 md:grid-cols-3">
                            {types.map((type) => (
                                <AddSectionCard
                                    key={type.value}
                                    site={site}
                                    type={type}
                                />
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

Sections.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
