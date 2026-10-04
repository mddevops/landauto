import { Form, Head, Link, router } from '@inertiajs/react';
import { ImageOff, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import type { Choice } from '@/components/platform/form-fields';
import {
    SelectField,
    statusChoices,
    TextField,
} from '@/components/platform/form-fields';
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
import { index } from '@/routes/platform/catalog';
import {
    destroy as destroyImage,
    store as storeImage,
} from '@/routes/platform/catalog/media/images';
import {
    store as storeSet,
    update as updateSet,
} from '@/routes/platform/catalog/media/sets';

type MediaImage = {
    public_id: string;
    angle: string;
    url: string;
    width: number;
    height: number;
};

type MediaSet = {
    public_id: string;
    name: string;
    swatch_hex: string | null;
    status: boolean;
    sort_order: number;
    images: MediaImage[];
};

type MediaProps = {
    series: {
        public_id: string;
        name: string;
        title: string;
        mark: string;
        model: string;
        generation: string;
    };
    sets: MediaSet[];
    angles: Choice[];
    can: { manageMedia: boolean };
};

function SetDialog({
    seriesPublicId,
    set,
    trigger,
}: {
    seriesPublicId: string;
    set?: MediaSet;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const formAction = set
        ? updateSet.form(set.public_id)
        : storeSet.form(seriesPublicId);
    const prefix = `set-${set?.public_id ?? 'new'}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {set ? `Изменить: ${set.name}` : 'Новый набор'}
                    </DialogTitle>
                    <DialogDescription>
                        Набор объединяет изображения одного цвета или варианта в
                        разных ракурсах.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...formAction}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <TextField
                                id={`${prefix}-name`}
                                name="name"
                                label="Название"
                                required
                                maxLength={100}
                                autoComplete="off"
                                placeholder="Например, Белый перламутр"
                                defaultValue={set?.name ?? ''}
                                error={errors.name}
                            />
                            <TextField
                                id={`${prefix}-swatch_hex`}
                                name="swatch_hex"
                                label="Цвет образца"
                                maxLength={7}
                                autoComplete="off"
                                placeholder="#ffffff"
                                hint="Необязательно. Формат #rrggbb."
                                defaultValue={set?.swatch_hex ?? ''}
                                error={errors.swatch_hex}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    id={`${prefix}-status`}
                                    name="status"
                                    label="Активность"
                                    choices={statusChoices}
                                    defaultValue={
                                        set && !set.status ? '0' : '1'
                                    }
                                    error={errors.status}
                                />
                                <TextField
                                    id={`${prefix}-sort_order`}
                                    name="sort_order"
                                    label="Сортировка"
                                    type="number"
                                    min={0}
                                    required
                                    defaultValue={String(set?.sort_order ?? 0)}
                                    error={errors.sort_order}
                                />
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Отмена
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Сохранить
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AngleSlot({
    set,
    angle,
    image,
    canManage,
}: {
    set: MediaSet;
    angle: Choice;
    image?: MediaImage;
    canManage: boolean;
}) {
    const inputId = `upload-${set.public_id}-${angle.value}`;

    return (
        <figure className="flex min-w-0 flex-col gap-2 rounded-lg border p-2">
            <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md bg-muted">
                {image ? (
                    <img
                        src={image.url}
                        alt={`${set.name}: ${angle.label}`}
                        width={image.width}
                        height={image.height}
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
            <figcaption className="flex items-center gap-1 text-sm">
                <span className="min-w-0 flex-1 truncate">{angle.label}</span>
                {image && canManage && (
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`Удалить изображение: ${angle.label}`}
                        onClick={() =>
                            router.delete(destroyImage.url(image.public_id), {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                )}
            </figcaption>
            {!image && canManage && (
                <Form
                    {...storeImage.form(set.public_id)}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing
                    resetOnSuccess
                    className="grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="angle"
                                value={angle.value}
                            />
                            <label htmlFor={inputId} className="sr-only">
                                {`Файл для ракурса «${angle.label}»`}
                            </label>
                            <input
                                id={inputId}
                                type="file"
                                name="file"
                                required
                                accept="image/jpeg,image/png,image/webp"
                                className="w-full text-xs file:mr-2 file:rounded-md file:border file:bg-background file:px-2 file:py-1"
                            />
                            <InputError message={errors.file ?? errors.angle} />
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Upload aria-hidden="true" />
                                )}
                                Загрузить
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </figure>
    );
}

export default function CatalogMedia({
    series,
    sets,
    angles,
    can,
}: MediaProps) {
    return (
        <>
            <Head title={`Медиа серии ${series.name}`} />
            <main className="flex min-w-0 flex-1 flex-col gap-6 p-4 sm:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {series.title}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {`Медиа серии ${series.name}`}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            JPEG, PNG или WebP до 10 МБ. Прозрачный фон
                            сохраняется.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={index({
                                    query: {
                                        mark: series.mark,
                                        model: series.model,
                                        generation: series.generation,
                                        series: series.public_id,
                                    },
                                })}
                            >
                                Вернуться к каталогу
                            </Link>
                        </Button>
                        {can.manageMedia && (
                            <SetDialog
                                seriesPublicId={series.public_id}
                                trigger={
                                    <Button>
                                        <Plus aria-hidden="true" />
                                        Добавить набор
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </header>

                {sets.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Наборов пока нет.
                    </p>
                ) : (
                    sets.map((set) => (
                        <section
                            key={set.public_id}
                            aria-labelledby={`set-${set.public_id}`}
                            className="space-y-3 rounded-xl border bg-card p-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                {set.swatch_hex && (
                                    <span
                                        aria-hidden="true"
                                        className="size-5 rounded-full border"
                                        style={{
                                            backgroundColor: set.swatch_hex,
                                        }}
                                    />
                                )}
                                <h2
                                    id={`set-${set.public_id}`}
                                    className="font-semibold"
                                >
                                    {set.name}
                                </h2>
                                {!set.status && (
                                    <Badge variant="secondary">Выключен</Badge>
                                )}
                                {can.manageMedia && (
                                    <SetDialog
                                        seriesPublicId={series.public_id}
                                        set={set}
                                        trigger={
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Изменить ${set.name}`}
                                            >
                                                <Pencil aria-hidden="true" />
                                            </Button>
                                        }
                                    />
                                )}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                                {angles.map((angle) => (
                                    <AngleSlot
                                        key={angle.value}
                                        set={set}
                                        angle={angle}
                                        image={set.images.find(
                                            (image) =>
                                                image.angle === angle.value,
                                        )}
                                        canManage={can.manageMedia}
                                    />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </main>
        </>
    );
}

CatalogMedia.layout = {
    breadcrumbs: [{ title: 'Каталог автомобилей', href: index() }],
};
