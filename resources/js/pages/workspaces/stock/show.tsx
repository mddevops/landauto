import { Head, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, ImagePlus } from 'lucide-react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { TextField } from '@/components/platform/form-fields';
import { STOCK_COLORS } from '@/components/stock/stock-colors';
import {
    STOCK_PHOTO_MAX_FILES_SHOW,
    StockPhotoUpload,
    StockSavedPhotoGrid,
} from '@/components/stock/stock-photo-upload';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    archive,
    index as stockIndex,
    restore,
    show,
    update,
} from '@/routes/workspace/stock';
import * as photos from '@/routes/workspace/stock/photos';

type Option = { value: string; label: string };

type SpecRow = { label: string; value: string; emphasis: boolean };

type OptionGroup = {
    group: string;
    items: { name: string; availability: string }[];
};

type StockDetail = {
    public_id: string;
    title: string;
    mark: string | null;
    model: string | null;
    condition: string;
    condition_label: string;
    status: string;
    status_label: string;
    year: number;
    body_name: string | null;
    color_name: string;
    color_swatch_hex: string | null;
    mileage_km: number | null;
    owners_count: number | null;
    vin_masked: string | null;
    availability: string;
    availability_label: string;
    price_input: string;
    inner_id: string | null;
    path_key: string;
    stock_number: string;
    pts_document_type: string | null;
    purchased_year: number | null;
    purchased_month: number | null;
    on_warranty: boolean;
    not_customs_cleared: boolean;
    description: string | null;
    cover_url: string | null;
    specs: SpecRow[];
    options: OptionGroup[];
    photos: { public_id: string; url: string; original_name: string }[];
};

type Props = {
    vehicle: StockDetail;
    status_options: Option[];
    availability_options: Option[];
};

function ownersLabel(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return `${count} владелец`;
    }
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return `${count} владельца`;
    }

    return `${count} владельцев`;
}

export default function ShowStockVehicle({
    vehicle,
    status_options,
    availability_options,
}: Props) {
    const id = useId();
    const matchedColor = STOCK_COLORS.find(
        (color) =>
            color.name === vehicle.color_name ||
            color.hex.toLowerCase() ===
                (vehicle.color_swatch_hex ?? '').toLowerCase(),
    );
    const form = useForm({
        year: String(vehicle.year),
        body_name: vehicle.body_name ?? '',
        color_id: matchedColor?.id ?? '',
        color_name: vehicle.color_name,
        color_swatch_hex: vehicle.color_swatch_hex ?? '',
        mileage_km:
            vehicle.mileage_km === null ? '' : String(vehicle.mileage_km),
        owners_count:
            vehicle.owners_count === null ? '' : String(vehicle.owners_count),
        vin: '',
        availability: vehicle.availability,
        price: vehicle.price_input,
        inner_id: vehicle.inner_id ?? '',
        status: vehicle.status,
        pts_document_type: vehicle.pts_document_type ?? '',
        purchased_year:
            vehicle.purchased_year === null
                ? ''
                : String(vehicle.purchased_year),
        purchased_month:
            vehicle.purchased_month === null
                ? ''
                : String(vehicle.purchased_month),
        on_warranty: vehicle.on_warranty,
        not_customs_cleared: vehicle.not_customs_cleared,
        description: vehicle.description ?? '',
    });

    const photoForm = useForm<{ uploads: File[] }>({ uploads: [] });
    const [uploadResetKey, setUploadResetKey] = useState(0);
    const cover = vehicle.photos[0] ?? null;
    const isUsed = vehicle.condition === 'used';

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            on_warranty: data.on_warranty ? 1 : 0,
            not_customs_cleared: data.not_customs_cleared ? 1 : 0,
        }));
        form.submit(update(vehicle.public_id));
    };

    const uploadPhotos = (event: FormEvent) => {
        event.preventDefault();
        photoForm.post(photos.store.url(vehicle.public_id), {
            forceFormData: true,
            onSuccess: () => {
                photoForm.reset('uploads');
                setUploadResetKey((key) => key + 1);
            },
        });
    };

    return (
        <>
            <Head title={`${vehicle.title} — Склад`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 px-4 py-6 sm:px-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {vehicle.title}
                            </h1>
                            <Badge variant="secondary">
                                {vehicle.condition_label}
                            </Badge>
                            <Badge variant="outline">
                                {vehicle.status_label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Адресный ключ:{' '}
                            <span className="font-mono">
                                {vehicle.path_key}
                            </span>
                            {' · '}№ {vehicle.stock_number}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {vehicle.status === 'archived' ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(restore(vehicle.public_id))
                                }
                            >
                                <ArchiveRestore className="size-4" />
                                Восстановить
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(archive(vehicle.public_id))
                                }
                            >
                                <Archive className="size-4" />В архив
                            </Button>
                        )}
                    </div>
                </header>

                <section className="grid gap-4 lg:grid-cols-[minmax(0,22rem)_1fr]">
                    <div className="relative overflow-hidden rounded-xl border bg-muted/20">
                        {cover ? (
                            <img
                                src={cover.url}
                                alt={cover.original_name}
                                className="aspect-[4/3] w-full object-cover"
                            />
                        ) : (
                            <div className="flex aspect-[4/3] items-center justify-center text-sm text-muted-foreground">
                                Нет фото
                            </div>
                        )}
                        {isUsed && vehicle.owners_count !== null && (
                            <div className="absolute top-3 left-3 rounded-md bg-black/70 px-2.5 py-1 text-xs font-medium text-white">
                                {ownersLabel(vehicle.owners_count)}
                            </div>
                        )}
                        {isUsed && vehicle.mileage_km !== null && (
                            <div className="absolute right-3 bottom-3 rounded-md bg-black/70 px-2.5 py-1 text-xs font-medium text-white">
                                {vehicle.mileage_km.toLocaleString('ru-RU')} км
                            </div>
                        )}
                    </div>

                    {vehicle.specs.length > 0 && (
                        <div className="rounded-xl border bg-card p-4 sm:p-5">
                            <h2 className="mb-4 text-sm font-medium text-muted-foreground">
                                Характеристики
                            </h2>
                            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                                {vehicle.specs.map((row) => (
                                    <div key={row.label} className="min-w-0">
                                        <dt className="text-xs text-muted-foreground">
                                            {row.label}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'mt-0.5 truncate text-sm font-medium',
                                                row.emphasis &&
                                                    'inline-flex max-w-full rounded-md bg-sky-50 px-2 py-0.5 text-sky-900',
                                            )}
                                        >
                                            {row.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </section>

                {vehicle.options.length > 0 && (
                    <section className="space-y-4 rounded-xl border p-4 sm:p-5">
                        <h2 className="font-medium">Опции комплектации</h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            {vehicle.options.map((group) => (
                                <div key={group.group} className="space-y-2">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {group.group}
                                    </p>
                                    <ul className="space-y-1.5">
                                        {group.items.map((item) => (
                                            <li
                                                key={item.name}
                                                className="flex items-start justify-between gap-3 text-sm"
                                            >
                                                <span>{item.name}</span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {item.availability ===
                                                    'standard'
                                                        ? 'База'
                                                        : 'Опция'}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <form
                    onSubmit={save}
                    className="grid gap-4 rounded-xl border p-4 md:grid-cols-2"
                >
                    <TextField
                        id={`${id}-year`}
                        label="Год выпуска"
                        value={form.data.year}
                        onChange={(event) =>
                            form.setData('year', event.target.value)
                        }
                        error={form.errors.year}
                    />
                    <TextField
                        id={`${id}-body`}
                        label="Кузов"
                        value={form.data.body_name}
                        onChange={(event) =>
                            form.setData('body_name', event.target.value)
                        }
                        error={form.errors.body_name}
                    />

                    <div className="space-y-2 md:col-span-2">
                        <Label>Цвет</Label>
                        <div className="flex flex-wrap gap-2.5">
                            {STOCK_COLORS.map((color) => {
                                const selected =
                                    form.data.color_id === color.id;

                                return (
                                    <button
                                        key={color.id}
                                        type="button"
                                        title={color.name}
                                        aria-label={color.name}
                                        className={cn(
                                            'size-8 rounded-full border-2',
                                            selected &&
                                                'ring-2 ring-primary ring-offset-2',
                                        )}
                                        style={{
                                            background:
                                                color.gradient ?? color.hex,
                                            borderColor:
                                                color.hex === '#FFFFFF'
                                                    ? 'rgba(0,0,0,0.12)'
                                                    : color.hex,
                                        }}
                                        onClick={() =>
                                            form.setData((data) => ({
                                                ...data,
                                                color_id: color.id,
                                                color_name: color.name,
                                                color_swatch_hex: color.hex,
                                            }))
                                        }
                                    />
                                );
                            })}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {form.data.color_name}
                        </p>
                        <InputError message={form.errors.color_name} />
                    </div>

                    {isUsed && (
                        <>
                            <TextField
                                id={`${id}-mileage`}
                                label="Пробег, км"
                                value={form.data.mileage_km}
                                onChange={(event) =>
                                    form.setData(
                                        'mileage_km',
                                        event.target.value,
                                    )
                                }
                                error={form.errors.mileage_km}
                            />
                            <TextField
                                id={`${id}-owners`}
                                label="Владельцев по ПТС"
                                value={form.data.owners_count}
                                onChange={(event) =>
                                    form.setData(
                                        'owners_count',
                                        event.target.value,
                                    )
                                }
                                error={form.errors.owners_count}
                            />
                            <TextField
                                id={`${id}-vin`}
                                label={
                                    vehicle.vin_masked
                                        ? `VIN (сейчас ${vehicle.vin_masked})`
                                        : 'VIN'
                                }
                                value={form.data.vin}
                                onChange={(event) =>
                                    form.setData(
                                        'vin',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                error={form.errors.vin}
                            />
                        </>
                    )}
                    <TextField
                        id={`${id}-price`}
                        label="Цена, ₽"
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', event.target.value)
                        }
                        error={form.errors.price}
                    />
                    <TextField
                        id={`${id}-inner`}
                        label="Складской номер"
                        value={form.data.inner_id}
                        onChange={(event) =>
                            form.setData('inner_id', event.target.value)
                        }
                        error={form.errors.inner_id}
                    />

                    <div className="grid gap-1.5">
                        <Label>Наличие</Label>
                        <Select
                            value={form.data.availability}
                            onValueChange={(value) =>
                                form.setData('availability', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {availability_options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.availability} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label>Статус</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData('status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {status_options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>

                    {isUsed && (
                        <div className="flex flex-wrap gap-4 md:col-span-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.on_warranty}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'on_warranty',
                                            checked === true,
                                        )
                                    }
                                />
                                На гарантии
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.not_customs_cleared}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'not_customs_cleared',
                                            checked === true,
                                        )
                                    }
                                />
                                Не растаможен
                            </label>
                        </div>
                    )}

                    <div className="grid gap-1.5 md:col-span-2">
                        <Label htmlFor={`${id}-description`}>Описание</Label>
                        <Textarea
                            id={`${id}-description`}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            rows={4}
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="md:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner className="size-4" />}
                            Сохранить
                        </Button>
                    </div>
                </form>

                <section className="space-y-4 rounded-xl border p-4">
                    <div className="flex items-center gap-2">
                        <ImagePlus className="size-4" />
                        <h2 className="font-medium">Фото</h2>
                    </div>

                    {vehicle.photos.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Пока нет фото. Загрузите снимки автомобиля.
                        </p>
                    ) : (
                        <StockSavedPhotoGrid
                            photos={vehicle.photos}
                            coverBadge={
                                isUsed && vehicle.owners_count !== null
                                    ? ownersLabel(vehicle.owners_count)
                                    : 'Обложка'
                            }
                            onReorder={(orderedIds) =>
                                router.put(
                                    photos.reorder.url(vehicle.public_id),
                                    { photos: orderedIds },
                                    { preserveScroll: true },
                                )
                            }
                            onRemove={(photoId) =>
                                router.delete(
                                    photos.destroy.url({
                                        vehicle: vehicle.public_id,
                                        photo: photoId,
                                    }),
                                    { preserveScroll: true },
                                )
                            }
                        />
                    )}

                    <form onSubmit={uploadPhotos} className="space-y-3">
                        <StockPhotoUpload
                            maxFiles={STOCK_PHOTO_MAX_FILES_SHOW}
                            resetKey={uploadResetKey}
                            onChange={(uploads) =>
                                photoForm.setData('uploads', uploads)
                            }
                        />
                        <Button
                            type="submit"
                            disabled={
                                photoForm.processing ||
                                photoForm.data.uploads.length === 0
                            }
                        >
                            {photoForm.processing && (
                                <Spinner className="size-4" />
                            )}
                            Загрузить
                        </Button>
                        <InputError message={photoForm.errors.uploads} />
                    </form>
                </section>
            </main>
        </>
    );
}

ShowStockVehicle.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Все сайты', href: dashboard() },
        { title: 'Склад', href: stockIndex() },
        {
            title: props.vehicle.title,
            href: show(props.vehicle.public_id),
        },
    ],
});
