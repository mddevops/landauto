import { router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    RefreshCw,
    Search,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { LibraryThumb } from '@/components/designer/library-thumb';
import type {
    DesignerBlock,
    DesignerBlockRoutes,
    DesignerLibraryBlock,
    ReferenceIssue,
} from '@/components/designer/types';
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
import { cn } from '@/lib/utils';

type NavigatorProps = {
    routes: DesignerBlockRoutes;
    pageId: string;
    blocks: DesignerBlock[];
    referenceIssues: Record<string, ReferenceIssue[]>;
    library: DesignerLibraryBlock[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    canEdit: boolean;
};

const visit = { preserveScroll: true, preserveState: true } as const;

export function Navigator({
    routes,
    pageId,
    blocks,
    referenceIssues,
    library,
    selectedId,
    onSelect,
    canEdit,
}: NavigatorProps) {
    const [pendingDelete, setPendingDelete] = useState<DesignerBlock | null>(
        null,
    );
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<string>('all');
    const errors = usePage().props.errors as Record<string, string> | undefined;
    const libraryError = errors?.block;
    const add = (item: DesignerLibraryBlock) =>
        router.post(routes.add(pageId), { block: item.slug }, visit);

    const categories = useMemo(() => {
        const map = new Map<string, string>();

        for (const item of library) {
            map.set(item.category, item.category_label);
        }

        return [...map.entries()].sort((a, b) =>
            a[1].localeCompare(b[1], 'ru'),
        );
    }, [library]);

    const filtered = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase('ru');

        return library.filter((item) => {
            if (category !== 'all' && item.category !== category) {
                return false;
            }

            if (needle === '') {
                return true;
            }

            return (
                item.name.toLocaleLowerCase('ru').includes(needle) ||
                item.slug.includes(needle) ||
                item.category_label.toLocaleLowerCase('ru').includes(needle)
            );
        });
    }, [library, query, category]);

    const grouped = useMemo(() => {
        if (category !== 'all') {
            return [
                {
                    key: category,
                    label:
                        categories.find(([value]) => value === category)?.[1] ??
                        category,
                    items: filtered,
                },
            ];
        }

        const map = new Map<
            string,
            { key: string; label: string; items: DesignerLibraryBlock[] }
        >();

        for (const item of filtered) {
            const group = map.get(item.category) ?? {
                key: item.category,
                label: item.category_label,
                items: [],
            };
            group.items.push(item);
            map.set(item.category, group);
        }

        return [...map.values()].sort((a, b) =>
            a.label.localeCompare(b.label, 'ru'),
        );
    }, [filtered, category, categories]);

    return (
        <div className="flex flex-col gap-5">
            <section
                aria-labelledby="navigator-heading"
                className="flex flex-col gap-2"
            >
                <h2 id="navigator-heading" className="text-sm font-semibold">
                    Навигатор
                </h2>
                {blocks.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Блоков пока нет.
                    </p>
                ) : (
                    <ol className="flex flex-col gap-1">
                        {blocks.map((block, index) => (
                            <li
                                key={block.public_id}
                                className={cn(
                                    'flex items-center gap-0.5 rounded-md pr-1',
                                    block.public_id === selectedId &&
                                        'bg-muted',
                                )}
                            >
                                <button
                                    type="button"
                                    aria-pressed={
                                        block.public_id === selectedId
                                    }
                                    onClick={() => onSelect(block.public_id)}
                                    className={cn(
                                        'min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        block.public_id === selectedId &&
                                            'font-medium',
                                        block.is_hidden &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {(referenceIssues[block.public_id]
                                        ?.length ?? 0) > 0 && (
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="mr-1 inline size-3.5 align-[-2px] text-amber-600"
                                        />
                                    )}
                                    {block.name}
                                    {block.is_hidden && (
                                        <span className="sr-only">
                                            {' '}
                                            (скрыт)
                                        </span>
                                    )}
                                    {(referenceIssues[block.public_id]
                                        ?.length ?? 0) > 0 && (
                                        <span className="sr-only">
                                            {' '}
                                            (есть устаревшие ссылки)
                                        </span>
                                    )}
                                    {block.legacy && (
                                        <span className="sr-only">
                                            {' '}
                                            (устаревшая изолированная версия)
                                        </span>
                                    )}
                                </button>
                                {canEdit && (
                                    <>
                                        {block.legacy && (
                                            <IconAction
                                                label={
                                                    block.legacy?.available
                                                        ? `Обновить «${block.name}» до нативной версии ${block.legacy.target_version}`
                                                        : `Нативное обновление «${block.name}» недоступно`
                                                }
                                                disabled={
                                                    !block.legacy.available
                                                }
                                                onClick={() =>
                                                    router.post(
                                                        routes.upgrade(
                                                            block.public_id,
                                                        ),
                                                        {},
                                                        visit,
                                                    )
                                                }
                                            >
                                                <RefreshCw />
                                            </IconAction>
                                        )}
                                        <IconAction
                                            label={`Переместить «${block.name}» выше`}
                                            disabled={index === 0}
                                            onClick={() =>
                                                router.post(
                                                    routes.move(
                                                        block.public_id,
                                                    ),
                                                    { direction: 'up' },
                                                    visit,
                                                )
                                            }
                                        >
                                            <ArrowUp />
                                        </IconAction>
                                        <IconAction
                                            label={`Переместить «${block.name}» ниже`}
                                            disabled={
                                                index === blocks.length - 1
                                            }
                                            onClick={() =>
                                                router.post(
                                                    routes.move(
                                                        block.public_id,
                                                    ),
                                                    { direction: 'down' },
                                                    visit,
                                                )
                                            }
                                        >
                                            <ArrowDown />
                                        </IconAction>
                                        <IconAction
                                            label={`Дублировать «${block.name}»`}
                                            onClick={() =>
                                                router.post(
                                                    routes.duplicate(
                                                        block.public_id,
                                                    ),
                                                    {},
                                                    visit,
                                                )
                                            }
                                        >
                                            <Copy />
                                        </IconAction>
                                        <IconAction
                                            label={
                                                block.is_hidden
                                                    ? `Показать «${block.name}»`
                                                    : `Скрыть «${block.name}»`
                                            }
                                            onClick={() =>
                                                router.patch(
                                                    routes.visibility(
                                                        block.public_id,
                                                    ),
                                                    {
                                                        hidden: !block.is_hidden,
                                                    },
                                                    visit,
                                                )
                                            }
                                        >
                                            {block.is_hidden ? (
                                                <Eye />
                                            ) : (
                                                <EyeOff />
                                            )}
                                        </IconAction>
                                        <IconAction
                                            label={`Удалить «${block.name}»`}
                                            onClick={() =>
                                                setPendingDelete(block)
                                            }
                                        >
                                            <Trash2 />
                                        </IconAction>
                                    </>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
                {blocks
                    .filter((block) => block.legacy)
                    .map((block) => (
                        <p
                            key={`${block.public_id}-legacy`}
                            className="text-xs text-amber-700 dark:text-amber-400"
                        >
                            {block.legacy?.available
                                ? `Блок «${block.name}» можно обновить до нативной версии ${block.legacy.target_version}.`
                                : `Блок «${block.name}» использует устаревшую изолированную версию. ${block.legacy?.reason ?? ''}`}
                        </p>
                    ))}
            </section>

            {canEdit && (
                <section
                    aria-labelledby="library-heading"
                    className="flex flex-col gap-2"
                >
                    <h2 id="library-heading" className="text-sm font-semibold">
                        Добавить блок
                    </h2>
                    {libraryError && (
                        <p
                            role="alert"
                            className="rounded-md border border-destructive/40 bg-destructive/5 px-2 py-1.5 text-xs text-destructive"
                        >
                            {libraryError}
                        </p>
                    )}
                    <div className="relative">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                        />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Поиск блоков"
                            aria-label="Поиск блоков"
                            className="pl-8"
                        />
                    </div>
                    <div
                        role="tablist"
                        aria-label="Категории блоков"
                        className="flex flex-wrap gap-1"
                    >
                        <CategoryChip
                            label="Все"
                            active={category === 'all'}
                            onClick={() => setCategory('all')}
                        />
                        {categories.map(([value, label]) => (
                            <CategoryChip
                                key={value}
                                label={label}
                                active={category === value}
                                onClick={() => setCategory(value)}
                            />
                        ))}
                    </div>
                    {filtered.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Ничего не найдено.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-4">
                            {grouped.map((group) => (
                                <div
                                    key={group.key}
                                    className="flex flex-col gap-2"
                                >
                                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group.label}
                                    </h3>
                                    <ul className="grid grid-cols-2 gap-2">
                                        {group.items.map((item) => (
                                            <LibraryThumb
                                                key={item.slug}
                                                item={item}
                                                onAdd={() => add(item)}
                                            />
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            )}

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Удалить блок?</DialogTitle>
                        <DialogDescription>
                            {pendingDelete &&
                                `Блок «${pendingDelete.name}» будет удалён из черновика страницы.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPendingDelete(null)}
                        >
                            Отмена
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (pendingDelete) {
                                    router.delete(
                                        routes.destroy(pendingDelete.public_id),
                                        visit,
                                    );
                                }

                                setPendingDelete(null);
                            }}
                        >
                            Удалить
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function CategoryChip({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={cn(
                'rounded-full border px-2.5 py-1 text-xs transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'bg-background text-muted-foreground hover:text-foreground',
            )}
        >
            {label}
        </button>
    );
}

function IconAction({
    label,
    onClick,
    disabled,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 [&_svg]:size-3.5"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}
