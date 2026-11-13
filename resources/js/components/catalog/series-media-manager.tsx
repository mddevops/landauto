import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AngleSlot,
    ColorDialog,
    ConfirmDialog,
    Swatch,
    UploadButton,
} from '@/components/catalog/photo-parts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    destroy as destroyGalleryImage,
    reorder as reorderGallery,
    store as storeGallery,
} from '@/routes/platform/catalog/gallery';
import {
    destroy as destroyImage,
    store as storeImage,
} from '@/routes/platform/catalog/media/images';
import {
    destroy as destroySet,
    store as storeSet,
    update as updateSet,
} from '@/routes/platform/catalog/media/sets';
import type { AngleOption, SeriesLibrary } from '@/types/catalog';

/**
 * Platform photo library of one Series (ADR-010): colours with five angle photos each and a
 * gallery. Customers copy these photos into their vehicles; later changes never reach them.
 */
export function SeriesMediaManager({
    seriesRef,
    library,
    angles,
    canManage,
}: {
    seriesRef: string;
    library: SeriesLibrary;
    angles: AngleOption[];
    canManage: boolean;
}) {
    const [uploading, setUploading] = useState<string | null>(null);
    const visit = { preserveScroll: true, onFinish: () => setUploading(null) };

    const uploadAngle = (setId: string, angle: string, file: File) => {
        setUploading(`${setId}:${angle}`);
        router.post(
            storeImage.url(setId),
            { file, angle },
            { ...visit, forceFormData: true },
        );
    };

    const uploadGallery = (files: File[]) => {
        setUploading('gallery');
        router.post(
            storeGallery.url(seriesRef),
            { files },
            { ...visit, forceFormData: true },
        );
    };

    const moveGallery = (index: number, offset: number) => {
        const ids = library.gallery.map((image) => image.public_id);
        const [moved] = ids.splice(index, 1);
        ids.splice(index + offset, 0, moved);
        router.put(
            reorderGallery.url(seriesRef),
            { images: ids },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-8">
            <section
                aria-labelledby="colors-heading"
                className="flex flex-col gap-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="colors-heading"
                            className="text-lg font-semibold"
                        >
                            Цвета и ракурсы
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Для каждого цвета — до пяти фото: слева, 3/4 слева,
                            спереди, 3/4 справа, справа. Клиенты выбирают цвета
                            в мастере добавления машины.
                        </p>
                    </div>
                    {canManage && (
                        <ColorDialog
                            title="Новый цвет"
                            action={storeSet(seriesRef)}
                            withStatus
                            sortOrder={library.colors.length}
                            trigger={
                                <Button size="sm">
                                    <Plus aria-hidden="true" />
                                    Добавить цвет
                                </Button>
                            }
                        />
                    )}
                </div>

                {library.colors.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Цветов пока нет. Добавьте цвет и загрузите фото
                        ракурсов.
                    </p>
                ) : (
                    <div className="flex flex-col gap-4">
                        {library.colors.map((color) => {
                            const byAngle = new Map(
                                color.images.map((image) => [
                                    image.angle,
                                    image,
                                ]),
                            );

                            return (
                                <article
                                    key={color.public_id}
                                    className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs"
                                    aria-label={`Цвет ${color.name}`}
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
                                            {color.images.length} из{' '}
                                            {angles.length}
                                        </Badge>
                                        {!color.status && (
                                            <Badge variant="secondary">
                                                Скрыт от клиентов
                                            </Badge>
                                        )}
                                        {canManage && (
                                            <div className="ml-auto flex gap-1">
                                                <ColorDialog
                                                    title="Изменить цвет"
                                                    action={updateSet(
                                                        color.public_id,
                                                    )}
                                                    color={color}
                                                    withStatus
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
                                                    description="Фото этого цвета будут удалены из медиатеки. Машины клиентов сохранят свои копии."
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroySet.url(
                                                                color.public_id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
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
                                            const image = byAngle.get(
                                                angle.value,
                                            );

                                            return (
                                                <AngleSlot
                                                    key={angle.value}
                                                    label={angle.label}
                                                    url={image?.url ?? null}
                                                    disabled={!canManage}
                                                    uploading={
                                                        uploading ===
                                                        `${color.public_id}:${angle.value}`
                                                    }
                                                    onUpload={(file) =>
                                                        uploadAngle(
                                                            color.public_id,
                                                            angle.value,
                                                            file,
                                                        )
                                                    }
                                                    onRemove={
                                                        image
                                                            ? () =>
                                                                  router.delete(
                                                                      destroyImage.url(
                                                                          image.public_id,
                                                                      ),
                                                                      {
                                                                          preserveScroll: true,
                                                                      },
                                                                  )
                                                            : undefined
                                                    }
                                                />
                                            );
                                        })}
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}
            </section>

            <section
                aria-labelledby="gallery-heading"
                className="flex flex-col gap-4"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2
                            id="gallery-heading"
                            className="text-lg font-semibold"
                        >
                            Галерея
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Салон, детали, фото сзади — всё, что не относится к
                            ракурсам цвета.
                        </p>
                    </div>
                    {canManage && (
                        <UploadButton
                            onFiles={uploadGallery}
                            uploading={uploading === 'gallery'}
                        >
                            Загрузить фото
                        </UploadButton>
                    )}
                </div>
                {library.gallery.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        В галерее пока нет фото.
                    </p>
                ) : (
                    <ol className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {library.gallery.map((image, index) => (
                            <li
                                key={image.public_id}
                                className="group relative aspect-[4/3] overflow-hidden rounded-lg border bg-muted/40"
                            >
                                <img
                                    src={image.url}
                                    alt={`Фото галереи ${index + 1}`}
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                                {canManage && (
                                    <div className="absolute inset-x-1.5 bottom-1.5 flex justify-between gap-1 opacity-90 sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
                                        <div className="flex gap-1">
                                            <Button
                                                size="icon"
                                                variant="secondary"
                                                className="size-7"
                                                aria-label={`Сдвинуть фото ${index + 1} влево`}
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveGallery(index, -1)
                                                }
                                            >
                                                <ArrowLeft aria-hidden="true" />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="secondary"
                                                className="size-7"
                                                aria-label={`Сдвинуть фото ${index + 1} вправо`}
                                                disabled={
                                                    index ===
                                                    library.gallery.length - 1
                                                }
                                                onClick={() =>
                                                    moveGallery(index, 1)
                                                }
                                            >
                                                <ArrowRight aria-hidden="true" />
                                            </Button>
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="secondary"
                                            className="size-7"
                                            aria-label={`Удалить фото ${index + 1}`}
                                            onClick={() =>
                                                router.delete(
                                                    destroyGalleryImage.url(
                                                        image.public_id,
                                                    ),
                                                    {
                                                        preserveScroll: true,
                                                    },
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </div>
    );
}
