import { Link } from '@inertiajs/react';
import { Blocks, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AuthoringBlock } from '@/types/blocks';
import type { RouteDefinition } from '@/wayfinder';

const dateFormat = new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatBlockDate(value: string | null): string {
    return value ? dateFormat.format(new Date(value)) : '—';
}

export function versionsLabel(count: number): string {
    if (count === 0) {
        return 'Нет версий';
    }

    const mod10 = count % 10;
    const mod100 = count % 100;
    const word =
        mod10 === 1 && mod100 !== 11
            ? 'версия'
            : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)
              ? 'версии'
              : 'версий';

    return `${count} ${word}`;
}

type BlockListProps = {
    title: string;
    description: string;
    blocks: AuthoringBlock[];
    createHref: RouteDefinition<'get'>;
    createLabel: string;
    emptyText: string;
    emptyCta: string;
    showHref: (publicId: string) => RouteDefinition<'get'>;
};

export function BlockList({
    title,
    description,
    blocks,
    createHref,
    createLabel,
    emptyText,
    emptyCta,
    showHref,
}: BlockListProps) {
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
                {blocks.length > 0 && (
                    <Button asChild>
                        <Link href={createHref}>
                            <Plus aria-hidden="true" />
                            {createLabel}
                        </Link>
                    </Button>
                )}
            </header>

            {blocks.length === 0 ? (
                <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                    <Blocks
                        aria-hidden="true"
                        className="size-8 text-muted-foreground"
                    />
                    <p className="text-sm text-muted-foreground">{emptyText}</p>
                    <Button asChild>
                        <Link href={createHref}>
                            <Plus aria-hidden="true" />
                            {emptyCta}
                        </Link>
                    </Button>
                </section>
            ) : (
                <ul
                    aria-label={title}
                    className="divide-y rounded-xl border bg-card shadow-sm"
                >
                    {blocks.map((block) => (
                        <li key={block.public_id} data-testid="authoring-block">
                            <Link
                                href={showHref(block.public_id)}
                                className="flex flex-col gap-2 px-4 py-3 transition-colors outline-none hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:flex-row sm:items-center sm:gap-4"
                            >
                                <div className="min-w-0 flex-1 space-y-0.5">
                                    <p className="font-medium break-words">
                                        {block.name}
                                    </p>
                                    <p className="text-xs break-all text-muted-foreground">
                                        <code>{block.slug}</code>
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <Badge variant="outline">
                                        {versionsLabel(block.versions_count)}
                                    </Badge>
                                    <span>
                                        Обновлён{' '}
                                        {formatBlockDate(block.updated_at)}
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
