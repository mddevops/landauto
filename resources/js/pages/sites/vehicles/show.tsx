import { Head, Link, router } from '@inertiajs/react';
import { Eye, EyeOff, Library, Pencil, Plus, Trash2 } from 'lucide-react';
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
import type { MediaSetChoice } from '@/components/vehicles/media-selection';
import { MediaSelection } from '@/components/vehicles/media-selection';
import type {
    ModificationChoice,
    Offer,
    OfferChoices,
} from '@/components/vehicles/offer-dialog';
import { OfferDialog } from '@/components/vehicles/offer-dialog';
import { VehicleTextForm } from '@/components/vehicles/vehicle-text-form';
import { dashboard } from '@/routes';
import { destroy as destroyOffer } from '@/routes/sites/offers';
import {
    destroy as destroyVehicle,
    index,
    library,
    media,
    update,
} from '@/routes/sites/vehicles';
import type { VehicleCatalogTitle } from '@/types/catalog';

type ShowVehicleProps = {
    site: { public_id: string; name: string };
    vehicle: {
        public_id: string;
        status: boolean;
        sort_order: number;
        custom_name: string | null;
        custom_description: string | null;
        catalog: VehicleCatalogTitle | null;
        in_library: boolean;
    };
    mediaSets: MediaSetChoice[];
    modifications: ModificationChoice[];
    offers: Offer[];
    choices: OfferChoices;
    can: {
        editVehicles: boolean;
        editPrices: boolean;
        editBenefits: boolean;
        manageLibrary: boolean;
    };
};

function SaveToLibrary({
    site,
    vehicle,
}: Pick<ShowVehicleProps, 'site' | 'vehicle'>) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    function save(overwrite: boolean) {
        router.post(
            library.url({ site: site.public_id, vehicle: vehicle.public_id }),
            { overwrite },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setError(undefined);
                    setOpen(false);
                },
                onError: (errors) => setError(errors.library),
            },
        );
    }

    if (!vehicle.in_library) {
        return (
            <div className="space-y-1">
                <Button
                    variant="outline"
                    onClick={() => save(false)}
                    disabled={processing}
                >
                    {processing ? <Spinner /> : <Library aria-hidden="true" />}
                    Сохранить в библиотеку
                </Button>
                <InputError message={error} />
            </div>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Library aria-hidden="true" />
                    Обновить в библиотеке
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Обновить автомобиль в библиотеке?</DialogTitle>
                    <DialogDescription>
                        В библиотеке уже есть эта серия. Её название, описание и
                        выбранные цвета будут заменены данными с этого сайта.
                        Цены и предложения не копируются, другие сайты не
                        изменятся.
                    </DialogDescription>
                </DialogHeader>
                <InputError message={error} />
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Отмена</Button>
                    </DialogClose>
                    <Button onClick={() => save(true)} disabled={processing}>
                        {processing && <Spinner />}
                        Обновить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
    const title =
        vehicle.custom_name ?? vehicle.catalog?.title ?? 'Модель недоступна';
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
                    <div className="flex flex-wrap items-start gap-2">
                        {can.manageLibrary && (
                            <SaveToLibrary site={site} vehicle={vehicle} />
                        )}
                        {can.editVehicles && (
                            <>
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
                                                Будут удалены все предложения
                                                этого автомобиля на сайте.
                                                Каталог платформы не изменится.
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
                            </>
                        )}
                    </div>
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
                        saveUrl={media.url({
                            site: site.public_id,
                            vehicle: vehicle.public_id,
                        })}
                        mediaSets={mediaSets}
                        canEdit={can.editVehicles}
                    />
                </section>

                <section aria-labelledby="text-title" className="space-y-4">
                    <div>
                        <h2 id="text-title" className="text-xl font-semibold">
                            Название и описание
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Название показывается в блоках автомобилей на сайте.
                        </p>
                    </div>
                    <VehicleTextForm
                        url={update.url({
                            site: site.public_id,
                            vehicle: vehicle.public_id,
                        })}
                        customName={vehicle.custom_name}
                        customDescription={vehicle.custom_description}
                        placeholder={vehicle.catalog?.title ?? ''}
                        extra={{
                            status: vehicle.status,
                            sort_order: vehicle.sort_order,
                        }}
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
                                canEditPrices={can.editPrices}
                                canEditBenefits={can.editBenefits}
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
                                    {(can.editPrices || can.editBenefits) && (
                                        <div className="flex gap-2">
                                            <OfferDialog
                                                sitePublicId={site.public_id}
                                                vehiclePublicId={
                                                    vehicle.public_id
                                                }
                                                modifications={modifications}
                                                choices={choices}
                                                canEditPrices={can.editPrices}
                                                canEditBenefits={
                                                    can.editBenefits
                                                }
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
                                            {can.editPrices && (
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
                                            )}
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
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
