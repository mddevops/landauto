import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { MediaSetChoice } from '@/components/vehicles/media-selection';
import { MediaSelection } from '@/components/vehicles/media-selection';
import { VehicleTextForm } from '@/components/vehicles/vehicle-text-form';
import { dashboard } from '@/routes';
import {
    copy,
    destroy,
    index,
    media,
    update,
} from '@/routes/workspace/vehicles';
import type { VehicleCatalogTitle } from '@/types/catalog';

type LibraryVehicle = {
    public_id: string;
    custom_name: string | null;
    custom_description: string | null;
    status: boolean;
    catalog: VehicleCatalogTitle | null;
};

type DestinationSite = {
    public_id: string;
    name: string;
    has_vehicle: boolean;
};

type ShowProps = {
    vehicle: LibraryVehicle;
    mediaSets: MediaSetChoice[];
    sites: DestinationSite[];
};

function AddToSite({
    vehicle,
    sites,
}: {
    vehicle: LibraryVehicle;
    sites: DestinationSite[];
}) {
    const available = sites.filter((site) => !site.has_vehicle);
    const form = useForm({ site: available[0]?.public_id ?? '' });

    if (!vehicle.status) {
        return (
            <p className="text-sm text-muted-foreground">
                Автомобиль в архиве. Верните его из архива, чтобы добавить на
                сайт.
            </p>
        );
    }

    if (sites.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Нет сайтов, на которые у вас есть право добавлять автомобили.
            </p>
        );
    }

    return (
        <form
            className="flex max-w-2xl flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(copy.url(vehicle.public_id), {
                    preserveScroll: true,
                });
            }}
        >
            <div className="grid min-w-0 flex-1 gap-2">
                <Label htmlFor="copy-site">Сайт</Label>
                <select
                    id="copy-site"
                    value={form.data.site}
                    onChange={(event) =>
                        form.setData('site', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.site)}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                >
                    <option value="">Выберите сайт</option>
                    {sites.map((site) => (
                        <option
                            key={site.public_id}
                            value={site.public_id}
                            disabled={site.has_vehicle}
                        >
                            {site.has_vehicle
                                ? `${site.name} — уже добавлен`
                                : site.name}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.site} />
            </div>
            <Button
                type="submit"
                disabled={form.processing || form.data.site === ''}
            >
                {form.processing ? <Spinner /> : <Plus aria-hidden="true" />}
                Добавить на сайт
            </Button>
        </form>
    );
}

export default function ShowWorkspaceVehicle({
    vehicle,
    mediaSets,
    sites,
}: ShowProps) {
    const title =
        vehicle.custom_name ?? vehicle.catalog?.title ?? 'Модель недоступна';

    function setStatus(status: boolean) {
        router.patch(
            update.url(vehicle.public_id),
            {
                custom_name: vehicle.custom_name,
                custom_description: vehicle.custom_description,
                status,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={`${title} — Библиотека автомобилей`} />
            <main className="flex min-w-0 flex-1 flex-col gap-8 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <Link
                            href={index()}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            Библиотека автомобилей
                        </Link>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                                {title}
                            </h1>
                            {!vehicle.status && (
                                <Badge variant="secondary">В архиве</Badge>
                            )}
                        </div>
                        {vehicle.catalog && (
                            <p className="text-sm text-muted-foreground">
                                {`${vehicle.catalog.generation} · ${vehicle.catalog.series}`}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setStatus(!vehicle.status)}
                        >
                            {vehicle.status ? (
                                <Archive aria-hidden="true" />
                            ) : (
                                <ArchiveRestore aria-hidden="true" />
                            )}
                            {vehicle.status
                                ? 'Перенести в архив'
                                : 'Вернуть из архива'}
                        </Button>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Trash2 aria-hidden="true" />
                                    Удалить
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Удалить автомобиль из библиотеки?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Автомобили, уже добавленные на сайты, не
                                        изменятся. Каталог платформы тоже не
                                        изменится.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="outline">
                                            Отмена
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        onClick={() =>
                                            router.delete(
                                                destroy.url(vehicle.public_id),
                                            )
                                        }
                                    >
                                        Удалить
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </header>

                <section aria-labelledby="copy-title" className="space-y-4">
                    <div>
                        <h2 id="copy-title" className="text-xl font-semibold">
                            Добавить на сайт
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            На сайт копируются серия, название, описание и
                            выбранные цвета. Цены и предложения задаются на
                            сайте; дальнейшие изменения библиотеки сайт не
                            затрагивают.
                        </p>
                    </div>
                    <AddToSite vehicle={vehicle} sites={sites} />
                </section>

                <section aria-labelledby="text-title" className="space-y-4">
                    <h2 id="text-title" className="text-xl font-semibold">
                        Название и описание
                    </h2>
                    <VehicleTextForm
                        url={update.url(vehicle.public_id)}
                        customName={vehicle.custom_name}
                        customDescription={vehicle.custom_description}
                        placeholder={vehicle.catalog?.title ?? ''}
                        extra={{ status: vehicle.status }}
                        canEdit
                    />
                </section>

                <section aria-labelledby="media-title" className="space-y-4">
                    <div>
                        <h2 id="media-title" className="text-xl font-semibold">
                            Цвета и ракурсы
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Изображения подготовлены платформой. Отметьте
                            варианты, которые нужно копировать на сайты.
                        </p>
                    </div>
                    <MediaSelection
                        saveUrl={media.url(vehicle.public_id)}
                        mediaSets={mediaSets}
                        canEdit
                    />
                </section>
            </main>
        </>
    );
}

ShowWorkspaceVehicle.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Библиотека автомобилей', href: index() },
    ],
};
