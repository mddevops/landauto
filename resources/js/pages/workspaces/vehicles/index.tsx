import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Car,
    FolderHeart,
    FolderMinus,
    FolderPlus,
    Globe,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { ConfirmDialog } from '@/components/catalog/photo-parts';
import {
    groupVehicles,
    matchesSearch,
    normalizeSearch,
    VehicleGroups,
} from '@/components/catalog/vehicle-cards';
import { TextField } from '@/components/platform/form-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { bulk, create, index, show } from '@/routes/workspace/vehicles';
import * as collectionRoutes from '@/routes/workspace/vehicles/collections';
import type { VehicleCard, VehicleCollection } from '@/types/catalog';

type IndexProps = {
    vehicles: VehicleCard[];
    collections: VehicleCollection[];
    sites: { public_id: string; name: string }[];
};

type StatusFilter = 'active' | 'archived' | 'all';

export default function WorkspaceVehicles({
    vehicles,
    collections,
    sites,
}: IndexProps) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState<StatusFilter>('active');
    const [collection, setCollection] = useState<string | null>(null);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const current =
        collections.find((item) => item.public_id === collection) ?? null;

    const visible = useMemo(() => {
        const needle = normalizeSearch(query);

        return vehicles.filter(
            (vehicle) =>
                (status === 'all' ||
                    vehicle.status === (status === 'active')) &&
                (collection === null ||
                    vehicle.collections.includes(collection)) &&
                matchesSearch(vehicle, needle),
        );
    }, [vehicles, query, status, collection]);
    const groups = useMemo(() => groupVehicles(visible), [visible]);
    const archived = vehicles.filter((vehicle) => !vehicle.status).length;

    const toggle = (ids: string[], value: boolean) =>
        setSelected((previous) => {
            const next = new Set(previous);
            ids.forEach((id) => (value ? next.add(id) : next.delete(id)));

            return next;
        });

    const runBulk = (action: string, extra: Record<string, string> = {}) =>
        router.post(
            bulk.url(),
            { action, vehicles: [...selected], ...extra },
            { preserveScroll: true, onSuccess: () => setSelected(new Set()) },
        );

    return (
        <>
            <Head title="Каталог" />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Каталог
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Ваши автомобили с фото, комплектациями и ценами.
                            Отсюда их можно добавлять на любые сайты — на сайт
                            попадает копия.
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href={create({
                                query: collection ? { collection } : undefined,
                            })}
                        >
                            <Plus aria-hidden="true" />
                            Добавить автомобиль
                        </Link>
                    </Button>
                </header>

                <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                    <aside
                        aria-label="Подборки"
                        className="flex flex-col gap-1"
                    >
                        <div className="mb-1 flex items-center justify-between">
                            <h2 className="text-sm font-medium">Подборки</h2>
                            <CollectionDialog
                                title="Новая подборка"
                                trigger={
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="size-7"
                                        aria-label="Новая подборка"
                                    >
                                        <Plus aria-hidden="true" />
                                    </Button>
                                }
                            />
                        </div>
                        <CollectionButton
                            active={collection === null}
                            onClick={() => setCollection(null)}
                            icon={<Car aria-hidden="true" />}
                            label="Все автомобили"
                            count={vehicles.length}
                        />
                        {collections.map((item) => (
                            <div
                                key={item.public_id}
                                className="group/collection flex items-center"
                            >
                                <CollectionButton
                                    active={collection === item.public_id}
                                    onClick={() =>
                                        setCollection(item.public_id)
                                    }
                                    icon={<FolderHeart aria-hidden="true" />}
                                    label={item.name}
                                    count={item.vehicles_count}
                                />
                                <CollectionMenu
                                    collection={item}
                                    onDeleted={() => setCollection(null)}
                                />
                            </div>
                        ))}
                        {collections.length === 0 && (
                            <p className="px-2 text-xs text-muted-foreground">
                                Объединяйте автомобили в подборки: «Кроссоверы»,
                                «В наличии».
                            </p>
                        )}
                    </aside>

                    <section
                        aria-label="Автомобили"
                        className="flex min-w-0 flex-col gap-4"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Найти: марка, модель, кузов"
                                    aria-label="Поиск автомобиля"
                                    className="pl-9"
                                />
                            </div>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={status}
                                onValueChange={(value) =>
                                    value && setStatus(value as StatusFilter)
                                }
                                aria-label="Статус"
                            >
                                <ToggleGroupItem
                                    value="active"
                                    className="px-3"
                                >
                                    Активные
                                </ToggleGroupItem>
                                <ToggleGroupItem
                                    value="archived"
                                    className="px-3"
                                >
                                    Архив · {archived}
                                </ToggleGroupItem>
                                <ToggleGroupItem value="all" className="px-3">
                                    Все
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        {current && (
                            <p className="text-sm text-muted-foreground">
                                Подборка «{current.name}»: {visible.length}
                            </p>
                        )}

                        {vehicles.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                                <Car
                                    className="size-8 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="font-medium">
                                    В Каталоге пока нет автомобилей
                                </p>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Выберите марку, модель и кузов из каталога
                                    auto.ru, отметьте цвета, фото и комплектации
                                    с ценами.
                                </p>
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus aria-hidden="true" />
                                        Добавить автомобиль
                                    </Link>
                                </Button>
                            </div>
                        ) : groups.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                Ничего не найдено. Измените поиск или фильтр.
                            </p>
                        ) : (
                            <VehicleGroups
                                groups={groups}
                                href={(vehicle) => show.url(vehicle.public_id)}
                                selected={selected}
                                onToggle={toggle}
                            />
                        )}
                    </section>
                </div>

                {selected.size > 0 && (
                    <div
                        role="region"
                        aria-label="Действия с выбранными"
                        className="sticky bottom-4 z-20 mx-auto flex w-full max-w-4xl flex-wrap items-center gap-2 rounded-xl border bg-background/95 p-2 shadow-lg backdrop-blur"
                    >
                        <span className="px-2 text-sm font-medium">
                            Выбрано: {selected.size}
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setSelected(new Set())}
                        >
                            <X aria-hidden="true" />
                            Снять
                        </Button>
                        <div className="ml-auto flex flex-wrap gap-2">
                            <TargetMenu
                                label="На сайт"
                                icon={<Globe aria-hidden="true" />}
                                empty="Нет сайтов, куда можно добавлять автомобили."
                                items={sites.map((site) => ({
                                    id: site.public_id,
                                    name: site.name,
                                }))}
                                onPick={(site) =>
                                    runBulk('copy_to_site', { site })
                                }
                            />
                            <TargetMenu
                                label="В подборку"
                                icon={<FolderPlus aria-hidden="true" />}
                                empty="Сначала создайте подборку."
                                items={collections.map((item) => ({
                                    id: item.public_id,
                                    name: item.name,
                                }))}
                                onPick={(id) =>
                                    runBulk('add_to_collection', {
                                        collection: id,
                                    })
                                }
                            />
                            {current && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        runBulk('remove_from_collection', {
                                            collection: current.public_id,
                                        })
                                    }
                                >
                                    <FolderMinus aria-hidden="true" />
                                    Убрать из подборки
                                </Button>
                            )}
                            {status !== 'archived' && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => runBulk('archive')}
                                >
                                    <Archive aria-hidden="true" />
                                    В архив
                                </Button>
                            )}
                            {status !== 'active' && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => runBulk('restore')}
                                >
                                    <ArchiveRestore aria-hidden="true" />
                                    Из архива
                                </Button>
                            )}
                            <ConfirmDialog
                                title={`Удалить автомобили: ${selected.size}?`}
                                description="Автомобили и их фото будут удалены из Каталога. Копии на сайтах не изменятся."
                                onConfirm={() => runBulk('delete')}
                                trigger={
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="text-destructive"
                                    >
                                        <Trash2 aria-hidden="true" />
                                        Удалить
                                    </Button>
                                }
                            />
                        </div>
                    </div>
                )}
            </main>
        </>
    );
}

function CollectionButton({
    active,
    onClick,
    icon,
    label,
    count,
}: {
    active: boolean;
    onClick: () => void;
    icon: ReactNode;
    label: string;
    count: number;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground',
                active && 'bg-accent font-medium',
            )}
        >
            {icon}
            <span className="truncate">{label}</span>
            <span className="ml-auto text-xs text-muted-foreground">
                {count}
            </span>
        </button>
    );
}

function CollectionMenu({
    collection,
    onDeleted,
}: {
    collection: VehicleCollection;
    onDeleted: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        size="icon"
                        variant="ghost"
                        className="size-7 shrink-0"
                        aria-label={`Действия с подборкой ${collection.name}`}
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setEditing(true)}>
                        <Pencil aria-hidden="true" />
                        Переименовать
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setDeleting(true)}
                    >
                        <Trash2 aria-hidden="true" />
                        Удалить
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            <CollectionDialog
                title="Переименовать подборку"
                collection={collection}
                open={editing}
                onOpenChange={setEditing}
            />
            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Удалить подборку «{collection.name}»?
                        </DialogTitle>
                        <DialogDescription>
                            Автомобили останутся в Каталоге.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Отмена</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    collectionRoutes.destroy.url(
                                        collection.public_id,
                                    ),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setDeleting(false);
                                            onDeleted();
                                        },
                                    },
                                )
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

function CollectionDialog({
    title,
    collection,
    trigger,
    open: controlledOpen,
    onOpenChange,
}: {
    title: string;
    collection?: VehicleCollection;
    trigger?: ReactNode;
    open?: boolean;
    onOpenChange?: (value: boolean) => void;
}) {
    const [localOpen, setLocalOpen] = useState(false);
    const open = controlledOpen ?? localOpen;
    const setOpen = onOpenChange ?? setLocalOpen;
    const form = useForm({ name: collection?.name ?? '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(
            collection
                ? collectionRoutes.update(collection.public_id)
                : collectionRoutes.store(),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);

                    if (!collection) {
                        form.reset();
                    }
                },
            },
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                form.clearErrors();
            }}
        >
            {trigger && <DialogTrigger asChild>{trigger}</DialogTrigger>}
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        Подборки видны только в вашем Каталоге.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <TextField
                        id={`collection-name-${collection?.public_id ?? 'new'}`}
                        label="Название"
                        maxLength={100}
                        autoComplete="off"
                        autoFocus
                        required
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        error={form.errors.name}
                    />
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Отмена
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Сохранить
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TargetMenu({
    label,
    icon,
    empty,
    items,
    onPick,
}: {
    label: string;
    icon: ReactNode;
    empty: string;
    items: { id: string; name: string }[];
    onPick: (id: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button size="sm" variant="outline">
                    {icon}
                    {label}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="max-h-72 overflow-y-auto"
            >
                <DropdownMenuLabel>{label}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {items.length === 0 ? (
                    <p className="px-2 py-1.5 text-sm text-muted-foreground">
                        {empty}
                    </p>
                ) : (
                    items.map((item) => (
                        <DropdownMenuItem
                            key={item.id}
                            onSelect={() => onPick(item.id)}
                        >
                            {item.name}
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

WorkspaceVehicles.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Каталог', href: index() },
    ],
};
