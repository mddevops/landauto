import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type PickerItem = { public_id: string; name: string; added: boolean };

export type PickerLevel = {
    key: 'marks' | 'models' | 'generations' | 'series';
    label: string;
    selectionKey: string;
    selected: string | null;
    items: PickerItem[];
};

function selectionQuery(
    levels: PickerLevel[],
    position: number,
    item: PickerItem,
): Record<string, string> {
    const query: Record<string, string> = {};

    levels.slice(0, position).forEach((level) => {
        if (level.selected) {
            query[level.selectionKey] = level.selected;
        }
    });
    query[levels[position].selectionKey] = item.public_id;

    return query;
}

const emptyHints: Record<PickerLevel['key'], string> = {
    marks: 'В каталоге пока нет доступных марок.',
    models: 'У этой марки нет доступных моделей.',
    generations: 'У этой модели нет доступных поколений.',
    series: 'У этого поколения нет доступных серий.',
};

export function SeriesPicker({
    levels,
    href,
    addedLabel,
    renderAdd,
}: {
    levels: PickerLevel[];
    href: (query: Record<string, string>) => InertiaLinkProps['href'];
    addedLabel: string;
    renderAdd: (item: PickerItem) => ReactNode;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {levels.map((level, position) => (
                <section
                    key={level.key}
                    aria-labelledby={`picker-${level.key}`}
                    className="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm"
                >
                    <h2
                        id={`picker-${level.key}`}
                        className="border-b px-4 py-3 font-semibold"
                    >
                        {level.label}
                    </h2>
                    {level.items.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-muted-foreground">
                            {emptyHints[level.key]}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {level.items.map((item) =>
                                level.key === 'series' ? (
                                    <li
                                        key={item.public_id}
                                        className="flex items-center gap-2 px-4 py-2"
                                    >
                                        <span className="min-w-0 flex-1 text-sm break-words">
                                            {item.name}
                                        </span>
                                        {item.added ? (
                                            <Badge variant="secondary">
                                                {addedLabel}
                                            </Badge>
                                        ) : (
                                            renderAdd(item)
                                        )}
                                    </li>
                                ) : (
                                    <li key={item.public_id}>
                                        <Link
                                            href={href(
                                                selectionQuery(
                                                    levels,
                                                    position,
                                                    item,
                                                ),
                                            )}
                                            preserveScroll
                                            aria-current={
                                                level.selected ===
                                                item.public_id
                                                    ? 'true'
                                                    : undefined
                                            }
                                            className={cn(
                                                'flex items-center gap-2 px-4 py-2 text-sm hover:bg-accent',
                                                level.selected ===
                                                    item.public_id &&
                                                    'bg-accent font-medium',
                                            )}
                                        >
                                            <span className="min-w-0 flex-1 break-words">
                                                {item.name}
                                            </span>
                                            <ChevronRight
                                                aria-hidden="true"
                                                className="size-4 shrink-0 text-muted-foreground"
                                            />
                                        </Link>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </section>
            ))}
        </div>
    );
}
