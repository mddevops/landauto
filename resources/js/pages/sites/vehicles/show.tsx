import { Head, router } from '@inertiajs/react';
import { Eye, EyeOff, Library, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CatalogImage } from '@/components/catalog/catalog-grids';
import { ConfirmDialog } from '@/components/catalog/photo-parts';
import { VehiclePhotosEditor } from '@/components/catalog/vehicle-photos-editor';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Offer, OfferChoices } from '@/components/vehicles/offer-dialog';
import { OfferDialog } from '@/components/vehicles/offer-dialog';
import { VehicleTextForm } from '@/components/vehicles/vehicle-text-form';
import { dashboard } from '@/routes';
import { destroy as destroyOffer } from '@/routes/sites/offers';
import {
    angle,
    destroy as destroyVehicle,
    index,
    library as saveToLibrary,
    update,
} from '@/routes/sites/vehicles';
import * as colors from '@/routes/sites/vehicles/colors';
import * as photos from '@/routes/sites/vehicles/photos';
import type {
    AngleOption,
    SeriesLibrary,
    Trim,
    VehicleDetail,
} from '@/types/catalog';

type ShowVehicleProps = {
    site: { public_id: string; name: string };
    vehicle: VehicleDetail & { in_library: boolean };
    offers: Offer[];
    trims: Trim[];
    library: SeriesLibrary;
    angles: AngleOption[];
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
            saveToLibrary.url({
                site: site.public_id,
                vehicle: vehicle.public_id,
            }),
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
                    Сохранить в Каталог
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
                    Обновить в Каталоге
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Обновить автомобиль в Каталоге?</DialogTitle>
                    <DialogDescription>
                        Название, описание, фото, комплектации и цены в Каталоге
                        будут заменены данными с этого сайта. Другие сайты не
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
    offers,
    trims,
    library,
    angles,
    choices,
    can,
}: ShowVehicleProps) {
    const ids = { site: site.public_id, vehicle: vehicle.public_id };

    return (
        <>
            <Head title={`${vehicle.title} — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <CatalogImage
                        src={vehicle.catalog?.image ?? null}
                        alt=""
                        className="aspect-[16/10] w-full shrink-0 rounded-xl bg-muted object-cover sm:w-48"
                    />
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            {site.name} · Автомобили
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                                {vehicle.title}
                            </h1>
                            {!vehicle.status && (
                                <Badge variant="secondary">Скрыт</Badge>
                            )}
                        </div>
                        {vehicle.catalog && (
                            <p className="text-sm text-muted-foreground">
                                {[
                                    vehicle.catalog.generation.name,
                                    vehicle.catalog.generation.years,
                                    vehicle.catalog.name,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
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
                                            update.url(ids),
                                            { status: !vehicle.status },
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
                                <ConfirmDialog
                                    title="Удалить автомобиль с сайта?"
                                    description="Будут удалены фото, комплектации и цены этого автомобиля на сайте. Каталог не изменится."
                                    onConfirm={() =>
                                        router.delete(destroyVehicle.url(ids))
                                    }
                                    trigger={
                                        <Button
                                            variant="outline"
                                            aria-label="Удалить автомобиль"
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    }
                                />
                            </>
                        )}
                    </div>
                </header>

                {!vehicle.catalog_available && (
                    <Alert>
                        <AlertDescription>
                            Кузов этого автомобиля больше не найден в каталоге.
                            Фото и цены сохранены, новые комплектации выбрать
                            нельзя.
                        </AlertDescription>
                    </Alert>
                )}

                <Tabs defaultValue="offers" className="gap-6">
                    <TabsList>
                        <TabsTrigger value="offers">
                            Комплектации и цены
                            <Badge variant="secondary" className="ml-1">
                                {offers.length}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="photos">Фото</TabsTrigger>
                        <TabsTrigger value="text">Описание</TabsTrigger>
                    </TabsList>
                    <TabsContent value="offers">
                        <OffersSection
                            site={site}
                            vehicle={vehicle}
                            offers={offers}
                            trims={trims}
                            choices={choices}
                            can={can}
                        />
                    </TabsContent>
                    <TabsContent value="photos">
                        <VehiclePhotosEditor
                            vehicle={vehicle}
                            library={library}
                            angles={angles}
                            canEdit={can.editVehicles}
                            routes={{
                                upload: photos.store.url(ids),
                                library: photos.library.url(ids),
                                angle: angle.url(ids),
                                colorStore: colors.store(ids),
                                colorUpdate: (color) =>
                                    colors.update({ ...ids, color }),
                                colorDestroy: (color) =>
                                    colors.destroy.url({ ...ids, color }),
                                photoDestroy: (photo) =>
                                    photos.destroy.url({ ...ids, photo }),
                            }}
                        />
                    </TabsContent>
                    <TabsContent value="text">
                        <VehicleTextForm
                            url={update.url(ids)}
                            customName={vehicle.custom_name}
                            customDescription={vehicle.custom_description}
                            placeholder={vehicle.catalog?.title ?? ''}
                            extra={{}}
                            canEdit={can.editVehicles}
                        />
                    </TabsContent>
                </Tabs>
            </main>
        </>
    );
}

function OffersSection({
    site,
    vehicle,
    offers,
    trims,
    choices,
    can,
}: Pick<
    ShowVehicleProps,
    'site' | 'vehicle' | 'offers' | 'trims' | 'choices' | 'can'
>) {
    const benefitLabels = Object.fromEntries(
        choices.benefitTypes.map((choice) => [choice.value, choice.label]),
    );
    const availabilityLabels = Object.fromEntries(
        choices.availability.map((choice) => [choice.value, choice.label]),
    );
    const dialog = {
        sitePublicId: site.public_id,
        vehiclePublicId: vehicle.public_id,
        trims,
        choices,
    };

    return (
        <section
            aria-label="Комплектации и цены"
            className="flex flex-col gap-4"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    Комплектации из каталога с вашими ценами и выгодами. Без
                    цены показывается «Цена по запросу».
                </p>
                {can.editPrices && trims.length > 0 && (
                    <OfferDialog
                        {...dialog}
                        canEditPrices={can.editPrices}
                        canEditBenefits={can.editBenefits}
                        trigger={
                            <Button>
                                <Plus aria-hidden="true" />
                                Добавить комплектацию
                            </Button>
                        }
                    />
                )}
            </div>

            {offers.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Комплектаций пока нет.
                </p>
            ) : (
                <ul className="grid gap-3 lg:grid-cols-2">
                    {offers.map((offer) => (
                        <li
                            key={offer.public_id}
                            className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                        >
                            <div className="flex items-start gap-2">
                                <div className="min-w-0 flex-1">
                                    <h3 className="font-semibold break-words">
                                        {offer.name}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {[offer.modification, offer.summary]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                {offer.badge && <Badge>{offer.badge}</Badge>}
                                {!offer.status && (
                                    <Badge variant="secondary">Скрыта</Badge>
                                )}
                                {!offer.available && (
                                    <Badge variant="destructive">
                                        Нет в каталоге
                                    </Badge>
                                )}
                            </div>
                            <div className="flex items-baseline gap-3">
                                <p className="text-xl font-semibold">
                                    {offer.price_label ?? 'Цена по запросу'}
                                </p>
                                {offer.rrp && (
                                    <p className="text-sm text-muted-foreground line-through">
                                        {offer.rrp} ₽
                                    </p>
                                )}
                            </div>
                            {offer.availability && (
                                <p className="text-sm">
                                    {availabilityLabels[offer.availability]}
                                </p>
                            )}
                            {offer.benefits.length > 0 && (
                                <ul className="space-y-1 text-sm text-muted-foreground">
                                    {offer.benefits.map((benefit, position) => (
                                        <li key={position}>
                                            {`${benefit.label || benefitLabels[benefit.type]}: ${benefit.amount} ₽`}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {(can.editPrices || can.editBenefits) && (
                                <div className="flex gap-2">
                                    <OfferDialog
                                        {...dialog}
                                        canEditPrices={can.editPrices}
                                        canEditBenefits={can.editBenefits}
                                        offer={offer}
                                        trigger={
                                            <Button size="sm" variant="outline">
                                                <Pencil aria-hidden="true" />
                                                Изменить
                                            </Button>
                                        }
                                    />
                                    {can.editPrices && (
                                        <ConfirmDialog
                                            title={`Удалить «${offer.name}»?`}
                                            description="Комплектация и её цена будут удалены с сайта."
                                            onConfirm={() =>
                                                router.delete(
                                                    destroyOffer.url({
                                                        site: site.public_id,
                                                        offer: offer.public_id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                            trigger={
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                    Удалить
                                                </Button>
                                            }
                                        />
                                    )}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

ShowVehicle.layout = (props: ShowVehicleProps) => ({
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Автомобили', href: index(props.site.public_id) },
    ],
});
