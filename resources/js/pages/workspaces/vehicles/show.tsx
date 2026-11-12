import { Head, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    FolderHeart,
    Globe,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { CatalogImage } from '@/components/catalog/catalog-grids';
import { ConfirmDialog } from '@/components/catalog/photo-parts';
import {
    offerPayload,
    TrimOfferPicker,
} from '@/components/catalog/trim-offer-picker';
import type { OfferPrices } from '@/components/catalog/trim-offer-picker';
import { VehiclePhotosEditor } from '@/components/catalog/vehicle-photos-editor';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { VehicleTextForm } from '@/components/vehicles/vehicle-text-form';
import { dashboard } from '@/routes';
import {
    angle,
    collections as syncCollections,
    copy,
    destroy,
    index,
    offers as saveOffers,
    update,
} from '@/routes/workspace/vehicles';
import * as colors from '@/routes/workspace/vehicles/colors';
import * as photos from '@/routes/workspace/vehicles/photos';
import type {
    AngleOption,
    CatalogOffer,
    OptionSection,
    SeriesLibrary,
    Trim,
    VehicleCollection,
    VehicleDetail,
} from '@/types/catalog';

type DestinationSite = {
    public_id: string;
    name: string;
    has_vehicle: boolean;
};

type ShowProps = {
    vehicle: VehicleDetail & { collections: string[] };
    offers: CatalogOffer[];
    trims: Trim[];
    library: SeriesLibrary;
    options: OptionSection[];
    angles: AngleOption[];
    collections: VehicleCollection[];
    sites: DestinationSite[];
};

export default function ShowWorkspaceVehicle({
    vehicle,
    offers,
    trims,
    library,
    options,
    angles,
    collections,
    sites,
}: ShowProps) {
    const id = vehicle.public_id;

    return (
        <>
            <Head title={`${vehicle.title} — Каталог`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <CatalogImage
                        src={vehicle.catalog?.image ?? null}
                        alt=""
                        className="aspect-[16/10] w-full shrink-0 rounded-xl bg-muted object-cover sm:w-48"
                    />
                    <div className="min-w-0 flex-1 space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight break-words sm:text-3xl">
                                {vehicle.title}
                            </h1>
                            {!vehicle.status && (
                                <Badge variant="secondary">В архиве</Badge>
                            )}
                        </div>
                        {vehicle.catalog && (
                            <p className="text-sm text-muted-foreground">
                                {[
                                    vehicle.catalog.title,
                                    vehicle.catalog.generation.name,
                                    vehicle.catalog.generation.years,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                        <CollectionBadges
                            ids={vehicle.collections}
                            collections={collections}
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <CopyToSiteDialog vehicle={vehicle} sites={sites} />
                        <CollectionsDialog
                            vehicle={vehicle}
                            collections={collections}
                        />
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    update.url(id),
                                    { status: !vehicle.status },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {vehicle.status ? (
                                <Archive aria-hidden="true" />
                            ) : (
                                <ArchiveRestore aria-hidden="true" />
                            )}
                            {vehicle.status ? 'В архив' : 'Из архива'}
                        </Button>
                        <ConfirmDialog
                            title="Удалить автомобиль из Каталога?"
                            description="Копии на сайтах не изменятся. Фото этого автомобиля будут удалены."
                            onConfirm={() => router.delete(destroy.url(id))}
                            trigger={
                                <Button
                                    variant="outline"
                                    aria-label="Удалить автомобиль"
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            }
                        />
                    </div>
                </header>

                {!vehicle.catalog_available && (
                    <Alert>
                        <AlertDescription>
                            Кузов этого автомобиля больше не найден в каталоге.
                            Фото и цены сохранены, но комплектации выбрать
                            нельзя.
                        </AlertDescription>
                    </Alert>
                )}

                <Tabs defaultValue="photos" className="gap-6">
                    <TabsList>
                        <TabsTrigger value="photos">Фото</TabsTrigger>
                        <TabsTrigger value="offers">
                            Комплектации и цены
                            <Badge variant="secondary" className="ml-1">
                                {offers.length}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="text">Описание</TabsTrigger>
                    </TabsList>
                    <TabsContent value="photos">
                        <VehiclePhotosEditor
                            vehicle={vehicle}
                            library={library}
                            angles={angles}
                            canEdit
                            routes={{
                                upload: photos.store.url(id),
                                library: photos.library.url(id),
                                angle: angle.url(id),
                                colorStore: colors.store(id),
                                colorUpdate: (color) =>
                                    colors.update({ vehicle: id, color }),
                                colorDestroy: (color) =>
                                    colors.destroy.url({ vehicle: id, color }),
                                photoDestroy: (photo) =>
                                    photos.destroy.url({ vehicle: id, photo }),
                            }}
                        />
                    </TabsContent>
                    <TabsContent value="offers">
                        <OffersEditor
                            vehicleId={id}
                            offers={offers}
                            trims={trims}
                            options={options}
                        />
                    </TabsContent>
                    <TabsContent value="text">
                        <VehicleTextForm
                            url={update.url(id)}
                            customName={vehicle.custom_name}
                            customDescription={vehicle.custom_description}
                            placeholder={vehicle.catalog?.title ?? ''}
                            extra={{}}
                            canEdit
                        />
                    </TabsContent>
                </Tabs>
            </main>
        </>
    );
}

function CollectionBadges({
    ids,
    collections,
}: {
    ids: string[];
    collections: VehicleCollection[];
}) {
    const names = collections.filter((collection) =>
        ids.includes(collection.public_id),
    );

    if (names.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {names.map((collection) => (
                <Badge
                    key={collection.public_id}
                    variant="outline"
                    className="gap-1"
                >
                    <FolderHeart aria-hidden="true" />
                    {collection.name}
                </Badge>
            ))}
        </div>
    );
}

function OffersEditor({
    vehicleId,
    offers,
    trims,
    options,
}: {
    vehicleId: string;
    offers: CatalogOffer[];
    trims: Trim[];
    options: OptionSection[];
}) {
    const available = offers.filter((offer) => offer.available);
    const [selected, setSelected] = useState(
        available.map((offer) => offer.equipment),
    );
    const [prices, setPrices] = useState<OfferPrices>(
        Object.fromEntries(
            available.map((offer) => [
                offer.equipment,
                { price: offer.price, rrp: offer.rrp },
            ]),
        ),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const missing = offers.length - available.length;

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Комплектации и цены по умолчанию. При добавлении на сайт они
                копируются; дальше цены на сайте меняются отдельно.
            </p>
            {missing > 0 && (
                <Alert>
                    <AlertDescription>
                        Комплектаций, которых больше нет в каталоге: {missing}.
                        При сохранении они будут убраны.
                    </AlertDescription>
                </Alert>
            )}
            <TrimOfferPicker
                trims={trims}
                options={options}
                selected={selected}
                onSelectedChange={setSelected}
                prices={prices}
                onPricesChange={setPrices}
                withPrices
                errors={errors}
            />
            {trims.length > 0 && (
                <div className="sticky bottom-0 flex justify-end border-t bg-background/95 py-3 backdrop-blur">
                    <Button
                        disabled={processing}
                        onClick={() => {
                            setProcessing(true);
                            router.put(
                                saveOffers.url(vehicleId),
                                {
                                    offers: offerPayload(
                                        selected,
                                        prices,
                                        true,
                                    ),
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setErrors({}),
                                    onError: (value) => setErrors(value),
                                    onFinish: () => setProcessing(false),
                                },
                            );
                        }}
                    >
                        {processing && <Spinner />}
                        Сохранить комплектации
                    </Button>
                </div>
            )}
        </div>
    );
}

function CopyToSiteDialog({
    vehicle,
    sites,
}: {
    vehicle: VehicleDetail;
    sites: DestinationSite[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ site: '' });

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={!vehicle.status || sites.length === 0}>
                    <Globe aria-hidden="true" />
                    Добавить на сайт
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Добавить на сайт</DialogTitle>
                    <DialogDescription>
                        Копируются фото, комплектации и цены. Дальнейшие
                        изменения в Каталоге сайт не затрагивают.
                    </DialogDescription>
                </DialogHeader>
                <div
                    role="radiogroup"
                    aria-label="Сайт"
                    className="flex flex-col gap-2"
                >
                    {sites.map((site) => (
                        <button
                            key={site.public_id}
                            type="button"
                            role="radio"
                            aria-checked={form.data.site === site.public_id}
                            disabled={site.has_vehicle}
                            onClick={() => form.setData('site', site.public_id)}
                            className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 aria-checked:border-primary aria-checked:ring-1 aria-checked:ring-primary"
                        >
                            <span className="truncate font-medium">
                                {site.name}
                            </span>
                            {site.has_vehicle && (
                                <Badge variant="secondary">Уже на сайте</Badge>
                            )}
                        </button>
                    ))}
                </div>
                <InputError message={form.errors.site} />
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Отмена
                        </Button>
                    </DialogClose>
                    <Button
                        disabled={form.processing || form.data.site === ''}
                        onClick={() =>
                            form.submit(copy(vehicle.public_id), {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setOpen(false);
                                    form.reset();
                                },
                            })
                        }
                    >
                        {form.processing && <Spinner />}
                        Добавить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CollectionsDialog({
    vehicle,
    collections,
}: {
    vehicle: VehicleDetail & { collections: string[] };
    collections: VehicleCollection[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ collections: vehicle.collections });

    if (collections.length === 0) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                form.setData('collections', vehicle.collections);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <FolderHeart aria-hidden="true" />
                    Подборки
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Подборки</DialogTitle>
                    <DialogDescription>
                        Отметьте подборки, в которых должен быть автомобиль.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-2">
                    {collections.map((collection) => (
                        <div
                            key={collection.public_id}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`collection-${collection.public_id}`}
                                checked={form.data.collections.includes(
                                    collection.public_id,
                                )}
                                onCheckedChange={(value) =>
                                    form.setData(
                                        'collections',
                                        value === true
                                            ? [
                                                  ...form.data.collections,
                                                  collection.public_id,
                                              ]
                                            : form.data.collections.filter(
                                                  (item) =>
                                                      item !==
                                                      collection.public_id,
                                              ),
                                    )
                                }
                            />
                            <Label
                                htmlFor={`collection-${collection.public_id}`}
                                className="font-normal"
                            >
                                {collection.name}
                            </Label>
                        </div>
                    ))}
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Отмена
                        </Button>
                    </DialogClose>
                    <Button
                        disabled={form.processing}
                        onClick={() =>
                            form.submit(syncCollections(vehicle.public_id), {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            })
                        }
                    >
                        {form.processing && <Spinner />}
                        Сохранить
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ShowWorkspaceVehicle.layout = {
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Каталог', href: index() },
    ],
};
