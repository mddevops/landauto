import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FolderPlus, Layers } from 'lucide-react';
import { BlockGroupDialog } from '@/components/block-authoring/block-group-dialog';
import { BlockRows } from '@/components/block-authoring/block-list';
import type { Choice } from '@/components/platform/form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/platform/blocks';
import { move, show as showGroup } from '@/routes/platform/blocks/groups';
import type { AuthoringBlock } from '@/types/blocks';

type GroupCard = {
    public_id: string;
    name: string;
    kind_label: string;
    blocks_count: number;
};

export function blocksLabel(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    const word =
        mod10 === 1 && mod100 !== 11
            ? 'блок'
            : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)
              ? 'блока'
              : 'блоков';

    return `${count} ${word}`;
}

export default function PlatformBlocks({
    groups,
    ungrouped,
    kinds,
}: {
    groups: GroupCard[];
    ungrouped: AuthoringBlock[];
    kinds: Choice[];
}) {
    function reorder(group: GroupCard, direction: 'up' | 'down') {
        router.post(
            move.url(group.public_id),
            { direction },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Блоки Landflow" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Блоки Landflow
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Группы блоков платформы. Откройте группу, чтобы
                            создать в ней новый блок. Клиентам в дизайнере
                            доступны только блоки с опубликованной версией.
                        </p>
                    </div>
                    <BlockGroupDialog
                        title="Новая группа блоков"
                        kinds={kinds}
                        trigger={
                            <Button>
                                <FolderPlus aria-hidden="true" />
                                Создать группу
                            </Button>
                        }
                    />
                </header>

                {groups.length === 0 ? (
                    <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                        <Layers
                            aria-hidden="true"
                            className="size-8 text-muted-foreground"
                        />
                        <p className="text-sm text-muted-foreground">
                            Групп пока нет. Создайте первую группу, например
                            «Шапка».
                        </p>
                    </section>
                ) : (
                    <ul
                        aria-label="Группы блоков"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        {groups.map((group, position) => (
                            <li
                                key={group.public_id}
                                data-testid="block-group"
                                className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm"
                            >
                                <Link
                                    href={showGroup(group.public_id)}
                                    className="min-w-0 rounded-md text-lg font-semibold break-words outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    {group.name}
                                </Link>
                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary">
                                        {group.kind_label}
                                    </Badge>
                                    <Badge variant="outline">
                                        {blocksLabel(group.blocks_count)}
                                    </Badge>
                                </div>
                                <div className="mt-auto flex gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={position === 0}
                                        aria-label={`Поднять группу «${group.name}»`}
                                        onClick={() => reorder(group, 'up')}
                                    >
                                        <ArrowUp aria-hidden="true" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={
                                            position === groups.length - 1
                                        }
                                        aria-label={`Опустить группу «${group.name}»`}
                                        onClick={() => reorder(group, 'down')}
                                    >
                                        <ArrowDown aria-hidden="true" />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {ungrouped.length > 0 && (
                    <section
                        aria-labelledby="ungrouped-blocks"
                        className="space-y-3"
                    >
                        <h2 id="ungrouped-blocks" className="font-semibold">
                            Блоки без группы
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Выберите группу в настройках блока.
                        </p>
                        <BlockRows
                            label="Блоки без группы"
                            blocks={ungrouped}
                            showHref={(publicId) => show(publicId)}
                        />
                    </section>
                )}
            </main>
        </>
    );
}

PlatformBlocks.layout = {
    breadcrumbs: [{ title: 'Блоки Landflow', href: index() }],
};
