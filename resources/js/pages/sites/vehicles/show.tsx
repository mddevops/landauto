import { Head, Link, router } from '@inertiajs/react';
import { Eye, EyeOff, ImageOff, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import type {
    ModificationChoice,
    Offer,
    OfferChoices,
} from '@/components/vehicles/offer-dialog';
import { OfferDialog } from '@/components/vehicles/offer-dialog';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { destroy as destroyOffer } from '@/routes/sites/offers';
import {
    destroy as destroyVehicle,
    index,
    media,
    update,
} from '@/routes/sites/vehicles';
import type { VehicleCatalogTitle } from '@/types/catalog';

type MediaSetChoice = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    selected: boolean;
    preview_url: string | null;
    images_count: number;
};

type ShowVehicleProps = {
    site: { public_id: string; name: string };
    vehicle: {
        public_id: string;
        status: boolean;
        sort_order: number;
        catalog: VehicleCatalogTitle | null;
    };
    mediaSets: MediaSetChoice[];
    modifications: ModificationChoice[];
    offers: Offer[];
    choices: OfferChoices;
    can: { editVehicles: boolean; editPrices: boolean };
};

function MediaSelection({
    site,
    vehicle,
    mediaSets,
    canEdit,
}: Pick<ShowVehicleProps, 'site' | 'vehicle' | 'mediaSets'> & {
    canEdit: boolean;
}) {
    const [selected, setSelected] = useState<string[]>(
        mediaSets.filter((set) => set.selected).map((set) => set.public_id),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();
    const [saved, setSaved] = useState(false);

    function toggle(publicId: string) {
        setSaved(false);
        setSelected((current) =>
            current.includes(publicId)
                ? current.filter((id) => id !== publicId)
                : [...current, publicId],
        );
    }

    function save() {
        router.put(
            media.url({ site: site.public_id, vehicle: vehicle.public_id }),
            {
                sets: mediaSets
                    .map((set) => set.public_id)
                    .filter((id) => selected.includes(id)),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setError(undefined);
                    setSaved(true);
                },
                onError: (errors) => setError(errors.sets),
            },
        );
    }

    if (mediaSets.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Для этой серии пока нет подготовленных изображений.
            </p>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {mediaSets.map((set) => (
                    <label
                        key={set.public_id}
                        className={cn(
                            'flex min-w-0 cursor-pointer flex-col gap-2 rounded-xl border bg-card p-3 shadow-sm transition-colors has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-primary/20 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring',
                            !canEdit && 'cursor-default',
                        )}
                    >
                        <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md bg-muted">
                            {set.preview_url ? (
                                <img
                                    src={set.preview_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-contain"
                                />
                            ) : (
                                <ImageOff
                                    aria-hidden="true"
                                    className="size-6 text-muted-foreground"
                                />
                            )}
                        </div>
                        <span className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 shrink-0 accent-primary"
                                checked={selected.includes(set.public_id)}
                                onChange={() => toggle(set.public_id)}
                                disabled={!canEdit}
                            />
                            {set.swatch_hex && (
                                <span
                                    aria-hidden="true"
                                    className="size-4 shrink-0 rounded-full border"
                                    style={{ backgroundColor: set.swatch_hex }}
                                />
                            )}
                            <span className="min-w-0 flex-1 break-words">
                                {set.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {`Ракурсов: ${set.images_count}`}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <InputError message={error} />
            {canEdit && (
                <div className="flex items-center gap-3">
                    <Button type="button" onClick={save} disabled={processing}>
                        {processing && <Spinner />}
                        Сохранить выбор
                    </Button>
                    {saved && (
                        <p className="text-sm text-muted-foreground">
                            Сохранено
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

export default function ShowVehicle({
    site,
    vehicle,
    mediaSets,
    modifications,
    offers,
    choices,
    can,
}: ShowVehicleProps) {
    const title = vehicle.catalog?.title ?? 'Модель недоступна';
    const benefitLabels = Object.fromEntries(
        choices.benefitTypes.map((choice) => [choice.value, choice.label]),
    );
    const availabilityLabels = Object.fromEntries(
        choices.availability.map((choice) => [choice.value, choice.label]),
    );

    return (
        <>
            <Head title={`${title} — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-8 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <Link
                            href={index(site.public_id)}
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            {`${site.name} · Автомобили`}
                        </Link>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                {title}
                            </h1>
                            {!vehicle.status && (
                                <Badge variant="secondary">Скрыт</Badge>
                            )}
                        </div>
                        {vehicle.catalog && (
                            <p className="text-sm text-muted-foreground">
                                {`${vehicle.catalog.generation} · ${vehicle.catalog.series}`}
                            </p>
                        )}
                    </div>
                    {can.editVehicles && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        update.url({
                                            site: site.public_id,
                                            vehicle: vehicle.public_id,
                                        }),
                                        {
                                            status: !vehicle.status,
                                            sort_order: vehicle.sort_order,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {vehicle.status ? (
                                    <EyeOff aria-hidden="true" />
                                ) : (
                                    <Eye aria-hidden="true" />
                                )}
                                {vehicle.status
                                    ? 'Скрыть с сайта'
                                    : 'Показать на сайте'}
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
                                            Удалить автомобиль?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Будут удалены все предложения этого
                                            автомобиля на сайте. Каталог
                                            платформы не изменится.
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
                                                    destroyVehicle.url({
                                                        site: site.public_id,
                                                        vehicle:
                                                            vehicle.public_id,
                                                    }),
                                                )
                                            }
                                        >
                                            Удалить
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </header>

                <section aria-labelledby="media-title" className="space-y-4">
                    <div>
                        <h2 id="media-title" className="text-xl font-semibold">
                            Цвета и ракурсы
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Изображения подготовлены платформой. Отметьте
                            варианты, которые нужно показывать на сайте.
                        </p>
                    </div>
                    <MediaSelection
                        site={site}
                        vehicle={vehicle}
                        mediaSets={mediaSets}
                        canEdit={can.editVehicles}
                    />
                </section>

                <section aria-labelledby="offers-title" className="space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2
                                id="offers-title"
                                className="text-xl font-semibold"
                            >
                                Предложения
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Комплектации с вашими ценами и выгодами.
                            </p>
                        </div>
                        {can.editPrices && modifications.length > 0 && (
                            <OfferDialog
                                sitePublicId={site.public_id}
                                vehiclePublicId={vehicle.public_id}
                                modifications={modifications}
                                choices={choices}
                                trigger={
                                    <Button>
                                        <Plus aria-hidden="true" />
                                        Добавить предложение
                                    </Button>
                                }
                            />
                        )}
                    </div>

                    {modifications.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            У этой серии пока нет доступных модификаций в
                            каталоге.
                        </p>
                    )}

                    {offers.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Предложений пока нет.
                        </p>
                    ) : (
                        <ul className="grid gap-3 lg:grid-cols-2">
                            {offers.map((offer) => (
                                <li
                                    key={offer.public_id}
                                    className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm"
                                >
                                    <div className="flex items-start gap-2">
                                        <div className="min-w-0 flex-1">
                                            <h3 className="font-semibold break-words">
                                                {offer.equipment?.name ??
                                                    'Комплектация недоступна'}
                                            </h3>
                                            {offer.equipment && (
                                                <p className="text-sm text-muted-foreground">
                                                    {
                                                        offer.equipment
                                                            .modification_name
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        {offer.badge && (
                                            <Badge>{offer.badge}</Badge>
                                        )}
                                        {!offer.status && (
                                            <Badge variant="secondary">
                                                Скрыто
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-xl font-semibold">
                                        {offer.price_label}
                                    </p>
                                    {offer.availability && (
                                        <p className="text-sm">
                                            {
                                                availabilityLabels[
                                                    offer.availability
                                                ]
                                            }
                                        </p>
                                    )}
                                    {offer.benefits.length > 0 && (
                                        <ul className="space-y-1 text-sm text-muted-foreground">
                                            {offer.benefits.map(
                                                (benefit, position) => (
                                                    <li key={position}>
                                                        {`${benefit.label || benefitLabels[benefit.type]}: ${benefit.amount} ₽`}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                    {can.editPrices && (
                                        <div className="flex gap-2">
                                            <OfferDialog
                                                sitePublicId={site.public_id}
                                                vehiclePublicId={
                                                    vehicle.public_id
                                                }
                                                modifications={modifications}
                                                choices={choices}
                                                offer={offer}
                                                trigger={
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                        Изменить
                                                    </Button>
                                                }
                                            />
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    router.delete(
                                                        destroyOffer.url({
                                                            site: site.public_id,
                                                            offer: offer.public_id,
                                                        }),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                                Удалить
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </main>
        </>
    );
}

ShowVehicle.layout = {
    breadcrumbs: [{ title: 'Панель управления', href: dashboard() }],
};
