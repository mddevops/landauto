import { router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    Plus,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
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

/** Official Landflow Blocks without access conditions stay compact buttons. */
function isPlainOfficial(item: DesignerLibraryBlock): boolean {
    return item.author === null && !item.access.restricted;
}

function CatalogCard({
    item,
    onAdd,
}: {
    item: DesignerLibraryBlock;
    onAdd: () => void;
}) {
    const reasonId = `library-${item.slug}-reason`;

    return (
        <li
            data-testid="catalog-block"
            className="flex items-start gap-2 rounded-md border p-2"
        >
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="text-sm font-medium break-words">{item.name}</p>
                {item.author && (
                    <p className="text-xs break-words text-muted-foreground">
                        {`Автор: ${item.author}`}
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    {[item.access.label, item.access.detail]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                {item.reason && (
                    <p
                        id={reasonId}
                        className="text-xs text-amber-700 dark:text-amber-400"
                    >
                        {item.reason}
                    </p>
                )}
            </div>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-8 shrink-0"
                aria-label={`Добавить блок «${item.name}»`}
                aria-describedby={item.reason ? reasonId : undefined}
                disabled={!item.available}
                onClick={onAdd}
            >
                <Plus aria-hidden="true" />
            </Button>
        </li>
    );
}

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
    const errors = usePage().props.errors as Record<string, string> | undefined;
    const libraryError = errors?.block;
    const add = (item: DesignerLibraryBlock) =>
        router.post(routes.add(pageId), { block: item.slug }, visit);

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
                                </button>
                                {canEdit && (
                                    <>
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
                    <ul className="grid grid-cols-2 gap-2">
                        {library.filter(isPlainOfficial).map((item) => (
                            <li key={item.slug}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-full justify-start"
                                    aria-label={`Добавить блок «${item.name}»`}
                                    onClick={() => add(item)}
                                >
                                    <Plus aria-hidden="true" />
                                    <span className="truncate">
                                        {item.name}
                                    </span>
                                </Button>
                            </li>
                        ))}
                    </ul>
                    {library.some((item) => !isPlainOfficial(item)) && (
                        <ul
                            aria-label="Блоки с условиями доступа и блоки разработчиков"
                            className="flex flex-col gap-2"
                        >
                            {library
                                .filter((item) => !isPlainOfficial(item))
                                .map((item) => (
                                    <CatalogCard
                                        key={item.slug}
                                        item={item}
                                        onAdd={() => add(item)}
                                    />
                                ))}
                        </ul>
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
