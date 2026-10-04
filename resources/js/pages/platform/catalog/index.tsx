import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    Images,
    ListChecks,
    Pencil,
    Plus,
    Power,
} from 'lucide-react';
import type { CatalogChoices } from '@/components/platform/catalog-entry-dialog';
import { CatalogEntryDialog } from '@/components/platform/catalog-entry-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/platform/catalog';
import { show as showDictionary } from '@/routes/platform/catalog/dictionaries';
import { update } from '@/routes/platform/catalog/entries';
import { show as showEquipment } from '@/routes/platform/catalog/equipments';
import { show as showMedia } from '@/routes/platform/catalog/media';
import type { CatalogItem, CatalogLevelColumn } from '@/types/catalog';

type CatalogIndexProps = {
    levels: CatalogLevelColumn[];
    choices: CatalogChoices;
    can: { edit: boolean; manageMedia: boolean };
};

function selectionQuery(
    levels: CatalogLevelColumn[],
    position: number,
    item: CatalogItem,
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

function toggleStatus(level: CatalogLevelColumn, item: CatalogItem) {
    router.patch(
        update.url({ level: level.key, entry: item.public_id }),
        { ...item, status: !item.status },
        { preserveScroll: true },
    );
}

export default function CatalogIndex({
    levels,
    choices,
    can,
}: CatalogIndexProps) {
    const modelGroups = levels
        .find((level) => level.key === 'models')
        ?.items.map((item) => ({ value: item.public_id, label: item.name }));

    return (
        <>
            <Head title="Каталог автомобилей" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Каталог автомобилей
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Глобальный каталог платформы: марка, модель,
                            поколение, серия, модификация и комплектация. Выбор
                            верхнего уровня сбрасывает нижние.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={showDictionary('characteristics')}>
                                Характеристики
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={showDictionary('options')}>Опции</Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {levels.map((level, position) => (
                        <section
                            key={level.key}
                            aria-labelledby={`level-${level.key}`}
                            className="flex min-w-0 flex-col rounded-xl border bg-card shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
                                <h2
                                    id={`level-${level.key}`}
                                    className="font-semibold"
                                >
                                    {level.label}
                                </h2>
                                {can.edit && (
                                    <CatalogEntryDialog
                                        level={level.key}
                                        levelLabel={level.label}
                                        parent={level.parent}
                                        choices={choices}
                                        groupChoices={modelGroups}
                                        trigger={
                                            <Button size="sm" variant="outline">
                                                <Plus aria-hidden="true" />
                                                Добавить
                                            </Button>
                                        }
                                    />
                                )}
                            </div>

                            {level.items.length === 0 ? (
                                <p className="px-4 py-6 text-sm text-muted-foreground">
                                    Записей пока нет.
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {level.items.map((item) => {
                                        const selected =
                                            level.selected === item.public_id;

                                        return (
                                            <li
                                                key={item.public_id}
                                                className={cn(
                                                    'flex items-center gap-1 px-2 py-1.5',
                                                    selected && 'bg-accent',
                                                )}
                                            >
                                                {level.key === 'equipments' ? (
                                                    <Link
                                                        href={showEquipment(
                                                            item.public_id,
                                                        )}
                                                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:underline"
                                                    >
                                                        <ListChecks
                                                            aria-hidden="true"
                                                            className="size-4 shrink-0 text-muted-foreground"
                                                        />
                                                        <span className="truncate">
                                                            {item.name}
                                                        </span>
                                                    </Link>
                                                ) : (
                                                    <Link
                                                        href={index({
                                                            query: selectionQuery(
                                                                levels,
                                                                position,
                                                                item,
                                                            ),
                                                        })}
                                                        preserveScroll
                                                        aria-current={
                                                            selected
                                                                ? 'true'
                                                                : undefined
                                                        }
                                                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-sm"
                                                    >
                                                        <span className="truncate">
                                                            {item.name}
                                                        </span>
                                                        <ChevronRight
                                                            aria-hidden="true"
                                                            className="ml-auto size-4 shrink-0 text-muted-foreground"
                                                        />
                                                    </Link>
                                                )}

                                                {!item.status && (
                                                    <Badge variant="secondary">
                                                        Выключена
                                                    </Badge>
                                                )}

                                                {level.key === 'series' && (
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={showMedia(
                                                                item.public_id,
                                                            )}
                                                            aria-label={`Медиа серии ${item.name}`}
                                                        >
                                                            <Images aria-hidden="true" />
                                                        </Link>
                                                    </Button>
                                                )}

                                                {can.edit && (
                                                    <>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            aria-label={
                                                                item.status
                                                                    ? `Выключить ${item.name}`
                                                                    : `Включить ${item.name}`
                                                            }
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    level,
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <Power aria-hidden="true" />
                                                        </Button>
                                                        <CatalogEntryDialog
                                                            level={level.key}
                                                            levelLabel={
                                                                level.label
                                                            }
                                                            parent={
                                                                level.parent
                                                            }
                                                            entry={item}
                                                            choices={choices}
                                                            groupChoices={
                                                                modelGroups
                                                            }
                                                            trigger={
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    aria-label={`Изменить ${item.name}`}
                                                                >
                                                                    <Pencil aria-hidden="true" />
                                                                </Button>
                                                            }
                                                        />
                                                    </>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    ))}
                </div>
            </main>
        </>
    );
}

CatalogIndex.layout = {
    breadcrumbs: [{ title: 'Каталог автомобилей', href: index() }],
};
