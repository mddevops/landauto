import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type {
    DesignerBlock,
    DesignerLibraryBlock,
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
import {
    destroy,
    duplicate,
    move,
    store,
    visibility,
} from '@/routes/sites/blocks';

type NavigatorProps = {
    siteId: string;
    pageId: string;
    blocks: DesignerBlock[];
    library: DesignerLibraryBlock[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    canEdit: boolean;
};

const visit = { preserveScroll: true, preserveState: true } as const;

export function Navigator({
    siteId,
    pageId,
    blocks,
    library,
    selectedId,
    onSelect,
    canEdit,
}: NavigatorProps) {
    const [pendingDelete, setPendingDelete] = useState<DesignerBlock | null>(
        null,
    );
    const args = (block: DesignerBlock) => ({
        site: siteId,
        block: block.public_id,
    });

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
                                    {block.name}
                                    {block.is_hidden && (
                                        <span className="sr-only">
                                            {' '}
                                            (скрыт)
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
                                                    move.url(args(block)),
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
                                                    move.url(args(block)),
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
                                                    duplicate.url(args(block)),
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
                                                    visibility.url(args(block)),
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
                    <ul className="grid grid-cols-2 gap-2">
                        {library.map((item) => (
                            <li key={item.slug}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-full justify-start"
                                    aria-label={`Добавить блок «${item.name}»`}
                                    onClick={() =>
                                        router.post(
                                            store.url({
                                                site: siteId,
                                                page: pageId,
                                            }),
                                            { block: item.slug },
                                            visit,
                                        )
                                    }
                                >
                                    <Plus aria-hidden="true" />
                                    <span className="truncate">
                                        {item.name}
                                    </span>
                                </Button>
                            </li>
                        ))}
                    </ul>
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
                                        destroy.url(args(pendingDelete)),
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
