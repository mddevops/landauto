import { Link } from '@inertiajs/react';
import { LayoutTemplate, Plus } from 'lucide-react';
import {
    formatBlockDate,
    versionsLabel,
} from '@/components/block-authoring/block-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/studio/templates';
import type { AuthoringTemplate } from '@/types/templates';
import type { RouteDefinition } from '@/wayfinder';

type TemplateListProps = {
    title: string;
    description: string;
    templates: AuthoringTemplate[];
    createHref: RouteDefinition<'get'>;
};

export function TemplateList({
    title,
    description,
    templates,
    createHref,
}: TemplateListProps) {
    return (
        <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
            <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {templates.length > 0 && (
                    <Button asChild>
                        <Link href={createHref}>
                            <Plus aria-hidden="true" />
                            Создать шаблон
                        </Link>
                    </Button>
                )}
            </header>

            {templates.length === 0 ? (
                <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                    <LayoutTemplate
                        aria-hidden="true"
                        className="size-8 text-muted-foreground"
                    />
                    <p className="text-sm text-muted-foreground">
                        Шаблонов пока нет.
                    </p>
                    <Button asChild>
                        <Link href={createHref}>
                            <Plus aria-hidden="true" />
                            Создать шаблон
                        </Link>
                    </Button>
                </section>
            ) : (
                <ul
                    aria-label={title}
                    className="divide-y rounded-xl border bg-card shadow-sm"
                >
                    {templates.map((template) => (
                        <li
                            key={template.public_id}
                            data-testid="authoring-template"
                        >
                            <Link
                                href={show(template.public_id)}
                                className="flex flex-col gap-2 px-4 py-3 transition-colors outline-none hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:flex-row sm:items-center sm:gap-4"
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <p className="font-medium break-words">
                                        {template.name}
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        <code>{template.slug}</code>
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    {template.site_type_labels.map((label) => (
                                        <Badge key={label} variant="secondary">
                                            {label}
                                        </Badge>
                                    ))}
                                    <Badge variant="outline">
                                        {template.latest_version
                                            ? `Версия ${template.latest_version}`
                                            : versionsLabel(
                                                  template.versions_count,
                                              )}
                                    </Badge>
                                    <span>
                                        Обновлён{' '}
                                        {formatBlockDate(template.updated_at)}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </main>
    );
}
