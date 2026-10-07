import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleCheck,
    Eye,
    LayoutTemplate,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { formatBlockDate } from '@/components/block-authoring/block-list';
import { CatalogAccessForm } from '@/components/catalog/catalog-access-form';
import { TextField } from '@/components/platform/form-fields';
import type { Choice } from '@/components/platform/form-fields';
import { SiteTypeFields } from '@/components/template-authoring/site-type-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index as developerTemplates } from '@/routes/developer/templates';
import { index as platformTemplates } from '@/routes/platform/templates';
import {
    access as accessRoute,
    designer,
    preview,
    publish,
    update,
} from '@/routes/studio/templates';
import type { BlockAccessSettings } from '@/types/blocks';
import type {
    PublishedTemplateVersion,
    TemplateDetail,
} from '@/types/templates';

export default function TemplateShow({
    template,
    siteTypes,
    checks,
    versions,
    access,
    accessModes,
    accessEntitlements,
}: {
    template: TemplateDetail;
    siteTypes: Choice[];
    checks: string[];
    versions: PublishedTemplateVersion[];
    access: BlockAccessSettings;
    accessModes: Choice[];
    accessEntitlements: Choice[];
}) {
    const backHref =
        template.owner_scope === 'developer'
            ? developerTemplates()
            : platformTemplates();

    return (
        <>
            <Head title={`${template.name} — шаблон`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                        >
                            <Link href={backHref}>
                                <ArrowLeft aria-hidden="true" />
                                Все шаблоны
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                            {template.name}
                        </h1>
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <code className="break-all">{template.slug}</code>
                            <Badge variant="outline">
                                {`Автор: ${template.owner_label}`}
                            </Badge>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={designer(template.public_id)}>
                                <LayoutTemplate aria-hidden="true" />
                                Открыть дизайнер
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={preview.url(template.public_id)}
                                target="_blank"
                                rel="noopener"
                            >
                                <Eye aria-hidden="true" />
                                Предпросмотр
                            </a>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        aria-labelledby="template-publish-title"
                        className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
                    >
                        <div className="space-y-1">
                            <h2
                                id="template-publish-title"
                                className="font-semibold"
                            >
                                Публикация
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Публикация создаёт неизменяемую версию после
                                автоматических проверок, без ручной модерации.
                                Черновик можно продолжать менять.
                            </p>
                        </div>
                        {checks.length === 0 ? (
                            <p
                                role="status"
                                className="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400"
                            >
                                <CircleCheck
                                    aria-hidden="true"
                                    className="size-4"
                                />
                                Проверки пройдены.
                            </p>
                        ) : (
                            <div
                                role="status"
                                aria-label="Проблемы шаблона"
                                className="flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
                            >
                                <TriangleAlert
                                    aria-hidden="true"
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                <ul className="list-disc space-y-0.5 pl-4">
                                    {checks.map((check) => (
                                        <li key={check}>{check}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <Form {...publish.form(template.public_id)}>
                            {({ processing, errors }) => (
                                <div className="space-y-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || checks.length > 0
                                        }
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Upload aria-hidden="true" />
                                        )}
                                        Опубликовать версию
                                    </Button>
                                    <InputError message={errors.template} />
                                </div>
                            )}
                        </Form>

                        <div className="space-y-2">
                            <h3 className="text-sm font-medium">Версии</h3>
                            {versions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Опубликованных версий пока нет.
                                </p>
                            ) : (
                                <ul
                                    aria-label="Версии шаблона"
                                    className="divide-y rounded-md border text-sm"
                                >
                                    {versions.map((version) => (
                                        <li
                                            key={version.version}
                                            className="flex items-center justify-between gap-3 px-3 py-2"
                                        >
                                            <span className="font-medium">
                                                {`Версия ${version.version}`}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {formatBlockDate(
                                                    version.published_at,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>

                    <CatalogAccessForm
                        idPrefix="template-access"
                        description="Как клиенты могут создавать сайты из опубликованного шаблона. Бесплатный шаблон и шаблон по тарифу доступны при создании сайта; лицензия на шаблон покрывает его блоки для одного сайта. Покупка лицензий в Landflow пока недоступна."
                        access={access}
                        modes={accessModes}
                        entitlements={accessEntitlements}
                        url={accessRoute.url(template.public_id)}
                        className="lg:col-span-2"
                    />

                    <section
                        aria-labelledby="template-settings-title"
                        className="space-y-4 rounded-xl border bg-card p-4 shadow-sm sm:p-6"
                    >
                        <h2
                            id="template-settings-title"
                            className="font-semibold"
                        >
                            Настройки
                        </h2>
                        <Form
                            {...update.form(template.public_id)}
                            disableWhileProcessing
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <TextField
                                        id="template-name"
                                        name="name"
                                        label="Название"
                                        required
                                        maxLength={100}
                                        autoComplete="off"
                                        defaultValue={template.name}
                                        error={errors.name}
                                    />
                                    <SiteTypeFields
                                        choices={siteTypes}
                                        selected={template.site_types}
                                        error={errors.site_types}
                                    />
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Сохранить настройки
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                </div>
            </main>
        </>
    );
}
