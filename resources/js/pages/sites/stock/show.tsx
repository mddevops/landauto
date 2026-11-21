import { Head, Link, router, useForm } from '@inertiajs/react';
import { Warehouse } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { destroy, index, update } from '@/routes/sites/stock';
import {
    destroy as destroyPhoto,
    reset,
    store as storePhotos,
} from '@/routes/sites/stock/photos';

type Photo = { public_id: string; url: string };

type Listing = {
    public_id: string;
    title: string;
    year: number;
    condition_label: string;
    status_label: string;
    visible: boolean;
    stock_number: string;
    inner_id: string | null;
    color_name: string;
    mileage_km: number | null;
    availability_label: string;
    stock_price: string | null;
    price_input: string;
    price_from_stock: boolean;
    photos_custom: boolean;
    photos: Photo[];
};

type Props = {
    site: { public_id: string; name: string };
    listing: Listing;
    can: { editVehicles: boolean; editPrices: boolean };
};

export default function SiteStockShow({ site, listing, can }: Props) {
    const price = useForm({ price: listing.price_input });
    const uploads = useForm<{ uploads: File[] }>({ uploads: [] });
    const ids = { site: site.public_id, listing: listing.public_id };

    const savePrice = (event: FormEvent) => {
        event.preventDefault();
        price.patch(update.url(ids), { preserveScroll: true });
    };

    const savePhotos = (event: FormEvent) => {
        event.preventDefault();
        uploads.post(storePhotos.url(ids), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <>
            <Head title={`${listing.title} — ${site.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm text-muted-foreground">
                            <Link
                                href={index(site.public_id)}
                                className="hover:underline"
                            >
                                {site.name} · Со склада
                            </Link>
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {listing.title} {listing.year}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {listing.condition_label}
                            {' · '}
                            {listing.color_name}
                            {listing.mileage_km === null
                                ? ''
                                : ` · ${listing.mileage_km.toLocaleString('ru-RU')} км`}
                            {' · '}
                            {listing.availability_label}
                            {' · '}
                            {listing.inner_id ?? listing.stock_number}
                        </p>
                    </div>
                    <Badge variant={listing.visible ? 'secondary' : 'outline'}>
                        {listing.visible
                            ? 'Показывается на сайте'
                            : listing.status_label}
                    </Badge>
                </header>

                {!listing.visible && (
                    <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                        На опубликованном сайте этой машины нет, пока на складе
                        статус не «Готов».
                    </p>
                )}

                <section className="max-w-xl space-y-3 rounded-xl border p-4">
                    <h2 className="font-medium">Цена на этом сайте</h2>
                    <p className="text-sm text-muted-foreground">
                        {listing.stock_price === null
                            ? 'На складе цена не указана.'
                            : `На складе: ${listing.stock_price}. Пустое поле оставляет эту цену.`}
                    </p>
                    {can.editPrices ? (
                        <form
                            onSubmit={savePrice}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <div className="flex-1 space-y-2">
                                <Label htmlFor="site-price">Цена, ₽</Label>
                                <Input
                                    id="site-price"
                                    inputMode="decimal"
                                    value={price.data.price}
                                    onChange={(event) =>
                                        price.setData(
                                            'price',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Как на складе"
                                    aria-invalid={
                                        price.errors.price !== undefined
                                    }
                                />
                                {price.errors.price && (
                                    <p className="text-sm text-destructive">
                                        {price.errors.price}
                                    </p>
                                )}
                            </div>
                            <Button type="submit" disabled={price.processing}>
                                Сохранить цену
                            </Button>
                        </form>
                    ) : (
                        <p className="text-sm">
                            {listing.price_from_stock
                                ? 'Используется цена склада.'
                                : `Своя цена: ${listing.price_input} ₽`}
                        </p>
                    )}
                </section>

                <section className="space-y-4 rounded-xl border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {listing.photos_custom
                                    ? 'Фото этого сайта'
                                    : 'Фото склада'}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {listing.photos_custom
                                    ? 'Эти фото видит только этот сайт.'
                                    : 'Пока свои фото не загружены, сайт показывает фото склада.'}
                            </p>
                        </div>
                        {can.editVehicles && listing.photos_custom && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.delete(reset.url(ids), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Вернуть фото склада
                            </Button>
                        )}
                    </div>

                    {listing.photos.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Фото нет.
                        </p>
                    ) : (
                        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {listing.photos.map((photo) => (
                                <li key={photo.public_id} className="space-y-2">
                                    <img
                                        src={photo.url}
                                        alt=""
                                        className="aspect-[4/3] w-full rounded-md object-cover"
                                    />
                                    {can.editVehicles &&
                                        listing.photos_custom && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                onClick={() =>
                                                    router.delete(
                                                        destroyPhoto.url({
                                                            ...ids,
                                                            photo: photo.public_id,
                                                        }),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Удалить
                                            </Button>
                                        )}
                                </li>
                            ))}
                        </ul>
                    )}

                    {can.editVehicles && (
                        <form
                            onSubmit={savePhotos}
                            className="flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <div className="flex-1 space-y-2">
                                <Label htmlFor="site-photos">Свои фото</Label>
                                <Input
                                    id="site-photos"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    onChange={(event) =>
                                        uploads.setData(
                                            'uploads',
                                            Array.from(
                                                event.target.files ?? [],
                                            ),
                                        )
                                    }
                                />
                                {uploads.errors.uploads && (
                                    <p className="text-sm text-destructive">
                                        {uploads.errors.uploads}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    uploads.processing ||
                                    uploads.data.uploads.length === 0
                                }
                            >
                                Загрузить
                            </Button>
                        </form>
                    )}
                </section>

                {can.editVehicles && (
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.delete(destroy.url(ids))}
                        >
                            <Warehouse aria-hidden="true" />
                            Снять с сайта
                        </Button>
                    </div>
                )}
            </main>
        </>
    );
}

SiteStockShow.layout = {
    breadcrumbs: [{ title: 'Все сайты', href: dashboard() }],
};
