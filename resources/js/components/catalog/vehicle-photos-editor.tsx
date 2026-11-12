import { router } from '@inertiajs/react';
import { Library, Pencil, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import {
    AngleSlot,
    ColorDialog,
    ConfirmDialog,
    Swatch,
    UploadButton,
} from '@/components/catalog/photo-parts';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { RouteDefinition } from '@/wayfinder';
import type {
    AngleOption,
    AngleValue,
    SeriesLibrary,
    VehicleDetail,
} from '@/types/catalog';

export type VehiclePhotoRoutes = {
    upload: string;
    library: string;
    angle: string;
    colorStore: RouteDefinition<'post'>;
    colorUpdate: (color: string) => RouteDefinition<'patch'>;
    colorDestroy: (color: string) => string;
    photoDestroy: (photo: string) => string;
};

/**
 * Own photos of a customer vehicle: colours with angle photos, the main angle for cards, the
 * gallery, own uploads and extra copies from the platform library.
 */
export function VehiclePhotosEditor({
    vehicle,
    library,
    angles,
    routes,
    canEdit,
}: {
    vehicle: VehicleDetail;
    library: SeriesLibrary;
    angles: AngleOption[];
    routes: VehiclePhotoRoutes;
    canEdit: boolean;
}) {
    const [uploading, setUploading] = useState<string | null>(null);
    const done = { preserveScroll: true, onFinish: () => setUploading(null) };
    const remove = (url: string) =>
        router.delete(url, { preserveScroll: true });

    const upload = (
        key: string,
        files: File[],
        color?: string,
        angle?: AngleValue,
    ) => {
        setUploading(key);
        router.post(
            routes.upload,
            { files, ...(color && angle ? { color, angle } : {}) },
            { ...done, forceFormData: true },
        );
    };

    return (
        <div className="flex flex-col gap-8">
            <section
                aria-labelledby="angle-heading"
                className="flex flex-col gap-3"
            >
                <div>
                    <h2 id="angle-heading" className="text-lg font-semibold">
                        Главный ракурс
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Этот ракурс показывается в карточках и списках. Если
                        фото ракурса у цвета нет, берётся ближайший.
                    </p>
                </div>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="flex-wrap justify-start"
                    value={vehicle.primary_angle ?? ''}
                    disabled={!canEdit}
                    aria-label="Главный ракурс"
                    onValueChange={(value) =>
                        router.put(
                            routes.angle,
                            { primary_angle: value === '' ? null : value },
                            { preserveScroll: true },
                        )
                    }
                >
                    {angles.map((angle) => (
                        <ToggleGroupItem
                            key={angle.value}
                            value={angle.value}
                            className="px-3"
                        >
                            {angle.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </section>

            <section
                aria-labelledby="vehicle-colors-heading"
                className="flex flex-col gap-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="vehicle-colors-heading"
                            className="text-lg font-semibold"
                        >
                            Цвета
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Это ваши копии фото: изменения в медиатеке платформы
                            их не затрагивают.
                        </p>
                    </div>
                    {canEdit && (
                        <div className="flex flex-wrap gap-2">
                            <LibraryDialog
                                vehicle={vehicle}
                                library={library}
                                action={routes.library}
                            />
                            <ColorDialog
                                title="Новый цвет"
                                action={routes.colorStore}
                                trigger={
                                    <Button size="sm">
                                        <Plus aria-hidden="true" />
                                        Свой цвет
                                    </Button>
                                }
                            />
                        </div>
                    )}
                </div>

                {vehicle.colors.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Цветов пока нет. Возьмите их из медиатеки или добавьте
                        свой цвет и загрузите фото.
                    </p>
                ) : (
                    vehicle.colors.map((color) => {
                        const byAngle = new Map(
                            color.photos.map((photo) => [photo.angle, photo]),
                        );

                        return (
                            <article
                                key={color.public_id}
                                aria-label={`Цвет ${color.name}`}
                                className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                            >
                                <header className="flex flex-wrap items-center gap-2">
                                    <Swatch
                                        hex={color.swatch_hex}
                                        className="size-5"
                                    />
                                    <h3 className="font-medium">
                                        {color.name}
                                    </h3>
                                    <Badge variant="outline">
                                        {color.photos.length} из {angles.length}
                                    </Badge>
                                    {canEdit && (
                                        <div className="ml-auto flex gap-1">
                                            <ColorDialog
                                                title="Изменить цвет"
                                                action={routes.colorUpdate(
                                                    color.public_id,
                                                )}
                                                color={color}
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Изменить цвет ${color.name}`}
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                    </Button>
                                                }
                                            />
                                            <ConfirmDialog
                                                title={`Удалить цвет «${color.name}»?`}
                                                description="Фото этого цвета будут удалены у этого автомобиля."
                                                onConfirm={() =>
                                                    remove(
                                                        routes.colorDestroy(
                                                            color.public_id,
                                                        ),
                                                    )
                                                }
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Удалить цвет ${color.name}`}
                                                    >
                                                        <Trash2 aria-hidden="true" />
                                                    </Button>
                                                }
                                            />
                                        </div>
                                    )}
                                </header>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                    {angles.map((angle) => {
                                        const photo = byAngle.get(angle.value);
                                        const key = `${color.public_id}:${angle.value}`;

                                        return (
                                            <AngleSlot
                                                key={angle.value}
                                                label={angle.label}
                                                url={photo?.url ?? null}
                                                highlighted={
                                                    vehicle.primary_angle ===
                                                    angle.value
                                                }
                                                disabled={!canEdit}
                                                uploading={uploading === key}
                                                onUpload={(file) =>
                                                    upload(
                                                        key,
                                                        [file],
                                                        color.public_id,
                                                        angle.value,
                                                    )
                                                }
                                                onRemove={
                                                    photo
                                                        ? () =>
                                                              remove(
                                                                  routes.photoDestroy(
                                                                      photo.public_id,
                                                                  ),
                                                              )
                                                        : undefined
                                                }
                                            />
                                        );
                                    })}
                                </div>
                            </article>
                        );
                    })
                )}
            </section>

            <section
                aria-labelledby="vehicle-gallery-heading"
                className="flex flex-col gap-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="vehicle-gallery-heading"
                            className="text-lg font-semibold"
                        >
                            Галерея
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Салон, детали и другие фото без привязки к цвету.
                        </p>
                    </div>
                    {canEdit && (
                        <UploadButton
                            onFiles={(files) => upload('gallery', files)}
                            uploading={uploading === 'gallery'}
                        >
                            Загрузить фото
                        </UploadButton>
                    )}
                </div>
                {vehicle.gallery.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        В галерее пока нет фото.
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {vehicle.gallery.map((photo, index) => (
                            <li
                                key={photo.public_id}
                                className="group relative aspect-[4/3] overflow-hidden rounded-lg border bg-muted/40"
                            >
                                <img
                                    src={photo.url}
                                    alt={`Фото галереи ${index + 1}`}
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                                {canEdit && (
                                    <Button
                                        size="icon"
                                        variant="secondary"
                                        className="absolute top-1.5 right-1.5 size-7 opacity-90 sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                                        aria-label={`Удалить фото ${index + 1}`}
                                        onClick={() =>
                                            remove(
                                                routes.photoDestroy(
                                                    photo.public_id,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function LibraryDialog({
    vehicle,
    library,
    action,
}: {
    vehicle: VehicleDetail;
    library: SeriesLibrary;
    action: string;
}) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const [colors, setColors] = useState<string[]>([]);
    const [gallery, setGallery] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const owned = new Set(vehicle.colors.map((color) => color.name));
    const available = library.colors.filter((color) => !owned.has(color.name));
    const toggle = (list: string[], value: string, on: boolean) =>
        on ? [...list, value] : list.filter((item) => item !== value);

    if (library.colors.length === 0 && library.gallery.length === 0) {
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                setColors([]);
                setGallery([]);
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Library aria-hidden="true" />
                    Из медиатеки
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Фото из медиатеки</DialogTitle>
                    <DialogDescription>
                        Отмеченные цвета и фото галереи будут скопированы к
                        этому автомобилю.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-5">
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            Цвета
                        </legend>
                        {available.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Все цвета медиатеки уже добавлены.
                            </p>
                        ) : (
                            available.map((color) => (
                                <div
                                    key={color.public_id}
                                    className="flex items-center gap-3 rounded-lg border p-2"
                                >
                                    <Checkbox
                                        id={`${id}-${color.public_id}`}
                                        checked={colors.includes(
                                            color.public_id,
                                        )}
                                        onCheckedChange={(value) =>
                                            setColors((list) =>
                                                toggle(
                                                    list,
                                                    color.public_id,
                                                    value === true,
                                                ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`${id}-${color.public_id}`}
                                        className="flex flex-1 items-center gap-2"
                                    >
                                        <Swatch hex={color.swatch_hex} />
                                        {color.name}
                                        <span className="text-xs font-normal text-muted-foreground">
                                            фото: {color.images.length}
                                        </span>
                                    </Label>
                                    <div className="hidden gap-1 sm:flex">
                                        {color.images
                                            .slice(0, 3)
                                            .map((image) => (
                                                <img
                                                    key={image.public_id}
                                                    src={image.url}
                                                    alt=""
                                                    loading="lazy"
                                                    className="h-9 w-14 rounded border bg-muted object-contain"
                                                />
                                            ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </fieldset>
                    {library.gallery.length > 0 && (
                        <fieldset className="flex flex-col gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Галерея
                            </legend>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                {library.gallery.map((image, index) => {
                                    const checked = gallery.includes(
                                        image.public_id,
                                    );

                                    return (
                                        <label
                                            key={image.public_id}
                                            className={cn(
                                                'relative cursor-pointer overflow-hidden rounded-lg border',
                                                checked &&
                                                    'ring-2 ring-primary',
                                            )}
                                        >
                                            <img
                                                src={image.url}
                                                alt={`Фото галереи ${index + 1}`}
                                                loading="lazy"
                                                className="aspect-[4/3] w-full object-cover"
                                            />
                                            <Checkbox
                                                className="absolute top-1.5 left-1.5 bg-background"
                                                checked={checked}
                                                aria-label={`Фото галереи ${index + 1}`}
                                                onCheckedChange={(value) =>
                                                    setGallery((list) =>
                                                        toggle(
                                                            list,
                                                            image.public_id,
                                                            value === true,
                                                        ),
                                                    )
                                                }
                                            />
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>
                    )}
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Отмена
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        disabled={
                            processing || colors.length + gallery.length === 0
                        }
                        onClick={() => {
                            setProcessing(true);
                            router.post(
                                action,
                                { colors, gallery },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setOpen(false),
                                    onFinish: () => setProcessing(false),
                                },
                            );
                        }}
                    >
                        {processing ? <Spinner /> : <Plus aria-hidden="true" />}
                        Добавить{' '}
                        {colors.length + gallery.length > 0
                            ? `(${colors.length + gallery.length})`
                            : ''}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
