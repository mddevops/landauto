import { router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
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

/** Library categories shown under «Компоненты» instead of «Блоки». */
export const COMPONENT_CATEGORIES = ['menu', 'footer'];

const visit = { preserveScroll: true, preserveState: true } as const;

type NavigatorProps = {
    routes: DesignerBlockRoutes;
    blocks: DesignerBlock[];
    referenceIssues: Record<string, ReferenceIssue[]>;
    selectedId: string | null;
    onSelect: (id: string) => void;
    canEdit: boolean;
};

/** Blocks of the current Page in order, with move / duplicate / hide / delete. */
export function Navigator({
    routes,
    blocks,
    referenceIssues,
    selectedId,
    onSelect,
    canEdit,
}: NavigatorProps) {
    const [pendingDelete, setPendingDelete] = useState<DesignerBlock | null>(
        null,
    );

    return (
        <div className="flex flex-col gap-2">
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
                                block.public_id === selectedId && 'bg-muted',
                            )}
                        >
                            <button
                                type="button"
                                aria-pressed={block.public_id === selectedId}
                                onClick={() => onSelect(block.public_id)}
                                className={cn(
                                    'min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    block.public_id === selectedId &&
                                        'font-medium',
                                    block.is_hidden &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {(referenceIssues[block.public_id]?.length ??
                                    0) > 0 && (
                                    <TriangleAlert
                                        aria-hidden="true"
                                        className="mr-1 inline size-3.5 align-[-2px] text-amber-600"
                                    />
                                )}
                                {block.name}
                                {block.is_hidden && (
                                    <span className="sr-only"> (скрыт)</span>
                                )}
                                {(referenceIssues[block.public_id]?.length ??
                                    0) > 0 && (
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
                                                block.legacy.available
                                                    ? `Обновить «${block.name}» до нативной версии ${block.legacy.target_version}`
                                                    : `Нативное обновление «${block.name}» недоступно`
                                            }
                                            disabled={!block.legacy.available}
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
                                                routes.move(block.public_id),
                                                { direction: 'up' },
                                                visit,
                                            )
                                        }
                                    >
                                        <ArrowUp />
                                    </IconAction>
                                    <IconAction
                                        label={`Переместить «${block.name}» ниже`}
                                        disabled={index === blocks.length - 1}
                                        onClick={() =>
                                            router.post(
                                                routes.move(block.public_id),
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
                                                { hidden: !block.is_hidden },
                                                visit,
                                            )
                                        }
                                    >
                                        {block.is_hidden ? <Eye /> : <EyeOff />}
                                    </IconAction>
                                    <IconAction
                                        label={`Удалить «${block.name}»`}
                                        onClick={() => setPendingDelete(block)}
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

type BlockLibraryProps = {
    routes: DesignerBlockRoutes;
    pageId: string;
    library: DesignerLibraryBlock[];
    /** Only these categories, or every category except them. */
    categories: { only: string[] } | { except: string[] };
    canEdit: boolean;
    emptyText: string;
};

/** Searchable catalog of Blocks grouped by collapsible category; a click adds the Block. */
export function BlockLibrary({
    routes,
    pageId,
    library,
    categories,
    canEdit,
    emptyText,
}: BlockLibraryProps) {
    const [query, setQuery] = useState('');
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());
    const errors = usePage().props.errors as Record<string, string> | undefined;
    const libraryError = errors?.block;

    const groups = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase('ru');
        const map = new Map<
            string,
            { key: string; label: string; items: DesignerLibraryBlock[] }
        >();

        for (const item of library) {
            const included =
                'only' in categories
                    ? categories.only.includes(item.category)
                    : !categories.except.includes(item.category);
            const matches =
                needle === '' ||
                item.name.toLocaleLowerCase('ru').includes(needle) ||
                item.slug.includes(needle) ||
                item.category_label.toLocaleLowerCase('ru').includes(needle);

            if (!included || !matches) {
                continue;
            }

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
    }, [library, categories, query]);

    if (!canEdit) {
        return (
            <p className="text-sm text-muted-foreground">
                Добавлять блоки может участник с правом менять структуру
                страниц.
            </p>
        );
    }

    const toggle = (key: string) =>
        setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    return (
        <div className="flex flex-col gap-3">
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
            {groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {query.trim() === '' ? emptyText : 'Ничего не найдено.'}
                </p>
            ) : (
                <div className="flex flex-col gap-1">
                    {groups.map((group) => {
                        const open = !collapsed.has(group.key);
                        const listId = `library-group-${group.key}`;

                        return (
                            <section key={group.key} className="flex flex-col">
                                <h3>
                                    <button
                                        type="button"
                                        aria-expanded={open}
                                        aria-controls={listId}
                                        onClick={() => toggle(group.key)}
                                        className="flex w-full items-center justify-between rounded-md py-2 text-sm font-semibold transition-colors hover:text-foreground/80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {group.label}
                                        <ChevronDown
                                            aria-hidden="true"
                                            className={cn(
                                                'size-4 text-muted-foreground transition-transform',
                                                !open && '-rotate-90',
                                            )}
                                        />
                                    </button>
                                </h3>
                                {open && (
                                    <ul
                                        id={listId}
                                        className="grid grid-cols-2 gap-2 pb-2"
                                    >
                                        {group.items.map((item) => (
                                            <LibraryThumb
                                                key={item.slug}
                                                item={item}
                                                onAdd={() =>
                                                    router.post(
                                                        routes.add(pageId),
                                                        { block: item.slug },
                                                        visit,
                                                    )
                                                }
                                            />
                                        ))}
                                    </ul>
                                )}
                            </section>
                        );
                    })}
                </div>
            )}
        </div>
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
