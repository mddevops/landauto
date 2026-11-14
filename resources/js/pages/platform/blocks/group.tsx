import { Head, Link, router } from '@inertiajs/react';
import { Blocks, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BlockGroupDialog } from '@/components/block-authoring/block-group-dialog';
import { BlockRows } from '@/components/block-authoring/block-list';
import type { Choice } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { create, index, show } from '@/routes/platform/blocks';
import { destroy, show as showGroup } from '@/routes/platform/blocks/groups';
import type { AuthoringBlock } from '@/types/blocks';

type GroupPageProps = {
    group: {
        public_id: string;
        name: string;
        kind: string;
        kind_label: string;
    };
    blocks: AuthoringBlock[];
    canDelete: boolean;
    kinds: Choice[];
};

export default function PlatformBlockGroup({
    group,
    blocks,
    canDelete,
    kinds,
}: GroupPageProps) {
    const [deleting, setDeleting] = useState(false);
    const createHref = create({ query: { group: group.public_id } });

    return (
        <>
            <Head title={`${group.name} — Блоки Landflow`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                            {group.name}
                        </h1>
                        <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="secondary">
                                {group.kind_label}
                            </Badge>
                            Новые блоки этой группы появятся в дизайнере после
                            публикации версии.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <BlockGroupDialog
                            title="Изменить группу"
                            group={group}
                            kinds={kinds}
                            trigger={
                                <Button variant="outline">
                                    <Pencil aria-hidden="true" />
                                    Изменить группу
                                </Button>
                            }
                        />
                        {canDelete && (
                            <Button
                                variant="outline"
                                onClick={() => setDeleting(true)}
                            >
                                <Trash2 aria-hidden="true" />
                                Удалить группу
                            </Button>
                        )}
                        <Button asChild>
                            <Link href={createHref}>
                                <Plus aria-hidden="true" />
                                Создать блок
                            </Link>
                        </Button>
                    </div>
                </header>

                {blocks.length === 0 ? (
                    <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                        <Blocks
                            aria-hidden="true"
                            className="size-8 text-muted-foreground"
                        />
                        <p className="text-sm text-muted-foreground">
                            В группе пока нет блоков.
                        </p>
                    </section>
                ) : (
                    <BlockRows
                        label={`Блоки группы «${group.name}»`}
                        blocks={blocks}
                        showHref={(publicId) => show(publicId)}
                    />
                )}
            </main>

            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Удалить группу «{group.name}»?
                        </DialogTitle>
                        <DialogDescription>
                            В группе нет блоков. Удаление нельзя отменить.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Отмена</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(destroy.url(group.public_id))
                            }
                        >
                            Удалить
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PlatformBlockGroup.layout = (props: GroupPageProps) => ({
    breadcrumbs: [
        { title: 'Блоки Landflow', href: index() },
        { title: props.group.name, href: showGroup(props.group.public_id) },
    ],
});
